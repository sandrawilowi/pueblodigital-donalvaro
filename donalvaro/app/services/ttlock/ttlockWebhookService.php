<?php

declare(strict_types=1);

/**
 * ttlockWebhookService
 *
 * Procesa las notificaciones recibidas mediante el webhook de TTLock.
 *
 * @author Sandra Campos
 * @copyright Wilowi
 * @version 1.0.0
 * @since 10 ago 2026
 */
class ttlockWebhookService {

    private ttlockDevicesModel $devices_model;
    private ttlockAccessLogsRepository $access_logs_repository;

    public function __construct() {
        $this->devices_model = new ttlockDevicesModel();
        $this->access_logs_repository = new ttlockAccessLogsRepository();
    }

    /**
     * Procesa una notificación recibida desde TTLock.
     *
     * @param array $data Datos POST recibidos.
     *
     * @return void
     */
    public function process(array $data): void {
        $notify_type = (int) ($data['notifyType'] ?? 0);

        if ($notify_type <= 0) {
            throw new InvalidArgumentException(
                            'No se ha recibido un notifyType válido.'
                    );
        }

        switch ($notify_type) {

            case 1:
                $this->processLockRecords($data);
                break;

            /*
             * Los demás tipos los iremos añadiendo después.
             */
            default:
                break;
        }
    }

    /**
     * Procesa los registros enviados por una cerradura.
     *
     * @param array $data Datos POST recibidos por el webhook.
     *
     * @return void
     */
    private function processLockRecords(array $data): void {
        $records_json = trim(
                (string) ($data['records'] ?? '')
        );

        if ($records_json === '') {
            throw new RuntimeException(
                            'La notificación de la cerradura no contiene registros.'
                    );
        }

        $records = json_decode(
                $records_json,
                true
        );

        if (
                json_last_error() !== JSON_ERROR_NONE || !is_array($records)
        ) {
            throw new RuntimeException(
                            'El campo records recibido desde TTLock no contiene un JSON válido.'
                    );
        }

        foreach ($records as $record) {

            if (!is_array($record)) {
                continue;
            }

            $this->processRecord($record);
        }
    }

    /**
     * Procesa un registro individual de una cerradura.
     *
     * @param array $record Registro original enviado por TTLock.
     *
     * @return void
     */
    private function processRecord(array $record): void {

        $lock_id = (int) ($record['lockId'] ?? 0);

        /*
         * Buscamos la cerradura en nuestra base de datos
         * mediante el identificador de TTLock.
         */
        $device = $this->devices_model->findByTtlockLockId($lock_id);

        if (empty($device)) {
            throw new RuntimeException(
                            'No se ha encontrado la cerradura TTLock '
                            . $lock_id
                            . ' en la base de datos.'
                    );
        }

        $device_id = (int) $device['id'];

        /*
         * Instalación asociada a la cerradura en el momento
         * en el que recibimos el evento.
         *
         * Guardamos este id en el propio histórico para que,
         * aunque posteriormente la cerradura cambie de instalación,
         * el acceso siga perteneciendo a la instalación original.
         */
        $facilities_devices_model = new facilitiesDevicesModel();

        $facility_device = $facilities_devices_model->findByDeviceId(
                $device_id,
                false
        );

        $facility_id = !empty($facility_device) ? (int) $facility_device['facility_id'] : null;

        $record_type = (int) ($record['recordType'] ?? 0);
        $record_type_from_lock = (int) ($record['recordTypeFromLock'] ?? 0);
        $success = (int) ($record['success'] ?? 0);

        $lock_mac = trim((string) ($record['lockMac'] ?? ''));
        $keyboard_pwd = trim((string) ($record['keyboardPwd'] ?? ''));
        $username = trim((string) ($record['username'] ?? ''));

        $lock_date = (int) ($record['lockDate'] ?? 0);
        $server_date = (int) ($record['serverDate'] ?? 0);

        $electric_quantity = isset($record['electricQuantity']) ? (int) $record['electricQuantity'] : null;

        $access_pin_id = null;

        /*
         * Clasificamos el evento recibido.
         */
        switch ($record_type) {

            case 4:

                /*
     * Evento relacionado con PIN.
     *
     * Intentamos identificar el PIN independientemente
     * de que el acceso haya sido permitido o denegado.
     */
                if ($keyboard_pwd !== '') {

                    $pins_repository = new accessPinsRepository();

                    $access_pin = $pins_repository->findByDeviceAndPin(
                            $device_id,
                            $keyboard_pwd
                    );

                    if (!empty($access_pin)) {
                        $access_pin_id = (int) $access_pin['id'];
                    }
                }

                if ($success === 1 && $record_type_from_lock === 4) {

                    $event_type = 'PASSCODE_UNLOCK';
                } elseif ($success === 0 && $record_type_from_lock === 7) {

                    $event_type = 'PASSCODE_UNLOCK_FAILED';
                } else {

                    $event_type = 'PASSCODE_UNKNOWN';
                }

                break;

            case 11:

                /*
                 * Cierre mediante APP.
                 *
                 * En nuestros registros:
                 * recordTypeFromLock = 26
                 */
                $event_type = 'APP_LOCK';
                break;

            case 12:

                /*
                 * Apertura mediante gateway.
                 *
                 * En nuestros registros:
                 * recordTypeFromLock = 28
                 */
                $event_type = 'GATEWAY_UNLOCK';
                break;

            default:

                $event_type = 'UNKNOWN';
                break;
        }

        /*
         * Guardamos también el payload original completo.
         */
        $payload_json = json_encode(
                $record,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($payload_json === false) {
            throw new RuntimeException(
                            'No se ha podido codificar el registro recibido de TTLock.'
                    );
        }

        /*
         * Motivo del fallo.
         */
        $failure_reason = null;

        if ($event_type === 'PASSCODE_UNLOCK_FAILED') {
            $failure_reason = 'INVALID_PASSCODE';
        }

        /*
         * Datos que se almacenarán en wi_ttlock_access_logs.
         */
        $data = [
            'ttlock_device_id' => $device_id,
            'facility_id' => $facility_id,
            'access_pin_id' => $access_pin_id,
            'ttlock_record_id' => isset($record['recordId']) ? (int) $record['recordId'] : null,
            'ttlock_record_type' => $record_type,
            'ttlock_record_type_fromlock' => $record_type_from_lock,
            'event_type' => $event_type,
            'access_granted' => $success,
            'failure_reason' => $failure_reason,
            'event_at' => $this->millisecondsToDate($lock_date),
            'received_at' => $this->millisecondsToDate($server_date),
            'payload_json' => $payload_json
        ];

        $this->saveAccessLog($data);
    }

    /**
     * Guarda un evento de acceso recibido desde TTLock.
     */
    private function saveAccessLog(array $data): int {

        $model = new ttlockAccessLogsModel();

        /*
         * Si TTLock proporciona recordId, lo utilizamos
         * como identificador único del registro.
         */
        if (!empty($data['ttlock_record_id'])) {

            if (
                    $this->access_logs_repository->existsByTtlockRecordId(
                            (int) $data['ttlock_record_id']
                    )
            ) {
                return 0;
            }
        } else {

            /*
             * Algunos webhooks no proporcionan recordId.
             *
             * En ese caso identificamos el evento mediante:
             * - cerradura
             * - tipo original de TTLock
             * - fecha del evento
             */
            if (
                    $this->access_logs_repository->existsEvent(
                            (int) $data['ttlock_device_id'],
                            (int) $data['ttlock_record_type_fromlock'],
                            (string) $data['event_at']
                    )
            ) {
                return 0;
            }
        }

        $model->setTtlockDeviceId($data['ttlock_device_id']);
        $model->setFacilityId($data['facility_id']);
        $model->setAccessPinId($data['access_pin_id']);
        $model->setTtlockRecordId($data['ttlock_record_id']);
        $model->setTtlockRecordType($data['ttlock_record_type']);
        $model->setTtlockRecordTypeFromlock(
                $data['ttlock_record_type_fromlock']
        );
        $model->setEventType($data['event_type']);
        $model->setAccessGranted($data['access_granted']);
        $model->setFailureReason($data['failure_reason']);
        $model->setEventAt($data['event_at']);
        $model->setReceivedAt($data['received_at']);
        $model->setPayloadJson($data['payload_json']);

        return $model->add();
    }

    /**
     * Convierte una fecha expresada en milisegundos
     * a formato DATETIME de MySQL.
     *
     * @param int|null $milliseconds
     *
     * @return string|null
     */
    private function millisecondsToDate(?int $milliseconds): ?string {
        if ($milliseconds === null || $milliseconds <= 0) {
            return null;
        }

        return date(
                'Y-m-d H:i:s',
                (int) floor($milliseconds / 1000)
        );
    }
}
