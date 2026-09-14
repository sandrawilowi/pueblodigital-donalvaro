<?php

declare(strict_types=1);

/**
 * ttlockAccessLogsRepository
 */
class ttlockAccessLogsRepository
{
    private ttlockAccessLogsModel $model;

    public function __construct()
    {
        $this->model = new ttlockAccessLogsModel();
    }

    /**
     * Comprueba si ya existe un registro recibido desde TTLock.
     *
     * @param int $ttlock_record_id Identificador del registro en TTLock.
     *
     * @return bool
     */
    public function existsByTtlockRecordId(int $ttlock_record_id): bool
    {
        if ($ttlock_record_id <= 0) {
            return false;
        }

        return !empty($this->model->findByTtlockRecordId($ttlock_record_id));
    }
    
    /**
     * Comprueba si ya existe un evento de TTLock
     * sin identificador de registro.
     */
    public function existsEvent(
            int $ttlock_device_id,
            int $ttlock_record_type_fromlock,
            string $event_at
    ): bool {

        $result = $this->model->findEvent(
                $ttlock_device_id,
                $ttlock_record_type_fromlock,
                $event_at
        );

        return !empty($result);
    }

    public function findAllWithDetails(bool $asObject = false): array {

        $results = $this->model->findAllWithDetails($asObject);

        return $this->prepareHistoryResults($results, $asObject);
    }

    public function searchHistorial(array $filters = [], bool $asObject = true): array {

        $conditions = [];
        $params = [];

        $date_from = trim((string) ($filters['date_from'] ?? ''));
        $date_to = trim((string) ($filters['date_to'] ?? ''));
        $facility_id = (int) ($filters['facility_id'] ?? 0);
        $access_granted = $filters['access_granted'] ?? '';

        if ($date_from !== '') {
            $conditions[] = 'al.event_at >= ?';
            $params[] = $date_from . ' 00:00:00';
        }

        if ($date_to !== '') {
            $conditions[] = 'al.event_at <= ?';
            $params[] = $date_to . ' 23:59:59';
        }

        if ($facility_id > 0) {
            $conditions[] = 'al.facility_id = ?';
            $params[] = $facility_id;
        }

        if ($access_granted !== '' && in_array((string) $access_granted, ['0', '1'], true)) {
            $conditions[] = 'al.access_granted = ?';
            $params[] = (int) $access_granted;
        }

        $where = '';

        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        $results = $this->model->findByFilters($where, $params, $asObject);

        return $this->prepareHistoryResults($results, $asObject);
    }

    public function getAccessLogViewData(int $id): ?object {

        if ($id <= 0) {
            return null;
        }

        $result = $this->model->findViewDataById($id, true);

        if (empty($result)) {
            return null;
        }

        /*
         * PIN conocido.
         */
        $result->pin = null;

        if (!empty($result->pin_code_encrypted)) {

            $encryption_service = new pinEncryptionService();

            $result->pin = $encryption_service->decrypt(
                    $result->pin_code_encrypted
            );

            /*
             * PIN desconocido recibido directamente desde TTLock.
             */
        } elseif (!empty($result->payload_json)) {

            $payload = json_decode($result->payload_json, true);

            if (
                    json_last_error() === JSON_ERROR_NONE &&
                    is_array($payload) &&
                    !empty($payload['keyboardPwd'])
            ) {
                $result->pin = trim((string) $payload['keyboardPwd']);
            }
        }

        /*
         * JSON formateado para mostrarlo en la vista.
         */
        $result->payload_pretty = '';

        if (!empty($result->payload_json)) {

            $payload = json_decode($result->payload_json, true);

            if (json_last_error() === JSON_ERROR_NONE) {

                $result->payload_pretty = json_encode(
                        $payload,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                );
            } else {

                /*
                 * Si por cualquier motivo no fuera JSON válido,
                 * mostramos exactamente lo almacenado.
                 */
                $result->payload_pretty = $result->payload_json;
            }
        }

        return $result;
    }

    private function prepareHistoryResults(array $results, bool $asObject): array {

        $encryption_service = new pinEncryptionService();

        foreach ($results as &$result) {

            if ($asObject) {

                $result->pin = null;

                if (!empty($result->pin_code_encrypted)) {

                    $result->pin = $encryption_service->decrypt(
                            $result->pin_code_encrypted
                    );

                    continue;
                }

                if (!empty($result->payload_json)) {

                    $payload = json_decode($result->payload_json, true);

                    if (
                            json_last_error() === JSON_ERROR_NONE &&
                            is_array($payload) &&
                            isset($payload['keyboardPwd']) &&
                            trim((string) $payload['keyboardPwd']) !== ''
                    ) {
                        $result->pin = trim((string) $payload['keyboardPwd']);
                    }
                }
            } else {

                $result['pin'] = null;

                if (!empty($result['pin_code_encrypted'])) {

                    $result['pin'] = $encryption_service->decrypt(
                            $result['pin_code_encrypted']
                    );

                    continue;
                }

                if (!empty($result['payload_json'])) {

                    $payload = json_decode($result['payload_json'], true);

                    if (
                            json_last_error() === JSON_ERROR_NONE &&
                            is_array($payload) &&
                            isset($payload['keyboardPwd']) &&
                            trim((string) $payload['keyboardPwd']) !== ''
                    ) {
                        $result['pin'] = trim((string) $payload['keyboardPwd']);
                    }
                }
            }
        }

        return $results;
    }
}
