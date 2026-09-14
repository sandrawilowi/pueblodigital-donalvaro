<?php

declare(strict_types=1);

/**
 * ttlockService
 *
 * Gestiona la lógica relacionada con la sincronización de TTLock.
 *
 * @author Sandra Campos
 * @copyright Wilowi
 * @version 1.0.0
 * @since 24 jul 2026
 */
class ttlockService {
    
    use debugTrait;

    private ttlockModel $ttlock_model;
    private ttlockDevicesModel $devices_model;
    private ttlockGatewaysModel $gateways_model;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->ttlock_model = new ttlockModel();
        $this->devices_model = new ttlockDevicesModel();
        $this->gateways_model = new ttlockGatewaysModel();
    }

    /**
     * Sincroniza las cerraduras de TTLock con la base de datos.
     *
     * @return array
     */
    public function syncLocks(): array {
        $result = [
            'success' => false,
            'message' => '',
            'total' => 0,
            'inserted' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'disabled' => 0,
            'gateway_related' => 0,
        ];

        try {

            $sync_date = date('Y-m-d H:i:s');

            $audit_service = new auditLogsService();

            $locks = $this->getLocks();

            $result['total'] = count($locks);

            $stored_locks = $this->devices_model->findAllForSync();

            $stored_locks_by_ttlock_id = [];

            foreach ($stored_locks as $stored_lock) {

                $stored_locks_by_ttlock_id[
                        (int) $stored_lock['ttlock_lock_id']
                        ] = $stored_lock;
            }

            $received_ttlock_ids = [];

            foreach ($locks as $lock) {

                $data = $this->prepareLockData(
                        $lock,
                        $sync_date
                );

                $ttlock_lock_id = (int) $data['ttlock_lock_id'];

                $received_ttlock_ids[$ttlock_lock_id] = true;

                $stored_lock = $stored_locks_by_ttlock_id[$ttlock_lock_id] ?? null;

                $current_gateway_id = null;

                if (
                        $stored_lock !== null && $stored_lock['gateway_id'] !== null
                ) {
                    $current_gateway_id = (int) $stored_lock['gateway_id'];
                }

                if ((int) $data['has_gateway'] === 1) {

                    $resolved_gateway_id = $this->resolveLockGatewayId($lock);

                    $data['gateway_id'] = $resolved_gateway_id ?? $current_gateway_id;
                } else {

                    $data['gateway_id'] = null;
                }

                /*
                 * CERRADURA NUEVA
                 */
                if ($stored_lock === null) {

                    $device = new ttlockDevicesModel();

                    $data['status'] = ttlockDevicesModel::STATUS_ACTIVE;

                    $this->fillDeviceModel(
                            $device,
                            $data
                    );

                    $device_id = $device->add();

                    if ($device_id <= 0) {

                        throw new RuntimeException(
                                        'No se pudo guardar la cerradura '
                                        . $ttlock_lock_id
                                        . ' en la base de datos.'
                                );
                    }

                    $audit_service->insert(
                            'wi_ttlock_devices',
                            $device_id,
                            $data,
                            __METHOD__,
                            'Sincronización de cerraduras'
                    );

                    $result['inserted']++;

                    continue;
                }

                /*
                 * CERRADURA EXISTENTE
                 */

                $gateway_changed = $current_gateway_id !== $data['gateway_id'];

                $data['status'] = (int) $stored_lock['status'];

                $has_changes = $this->lockHasChanges(
                        $stored_lock,
                        $data
                );

                $device = new ttlockDevicesModel();

                $this->fillDeviceModel(
                        $device,
                        $data
                );

                $device->setId(
                        (int) $stored_lock['id']
                );

                /*
                 * Se actualiza siempre para guardar last_sync_at.
                 */
                $device->update();

                if ($has_changes || $gateway_changed) {

                    $audit_service->update(
                            'wi_ttlock_devices',
                            (int) $stored_lock['id'],
                            $stored_lock,
                            $data,
                            __METHOD__,
                            [
                                'last_sync_at',
                                'updated_at'
                            ],
                            'Sincronización de cerraduras'
                    );

                    $result['updated']++;
                } else {

                    $result['unchanged']++;
                }

                if ($gateway_changed) {
                    $result['gateway_related']++;
                }
            }

            /*
             * CERRADURAS QUE YA NO APARECEN EN TTLOCK
             */
            foreach ($stored_locks as $stored_lock) {

                $ttlock_lock_id = (int) $stored_lock['ttlock_lock_id'];

                if (
                        isset(
                                $received_ttlock_ids[$ttlock_lock_id]
                        )
                ) {
                    continue;
                }

                if (
                        (int) $stored_lock['status'] === ttlockDevicesModel::STATUS_INACTIVE
                ) {
                    continue;
                }

                $this->devices_model->disableById(
                        (int) $stored_lock['id'],
                        $sync_date
                );

                $audit_service->update(
                        'wi_ttlock_devices',
                        (int) $stored_lock['id'],
                        $stored_lock,
                        [
                            'status' =>
                            ttlockDevicesModel::STATUS_INACTIVE
                        ],
                        __METHOD__,
                        [],
                        'Desactivación de cerradura durante sincronización'
                );

                $result['disabled']++;
            }

            $result['success'] = true;
            $result['message'] = 'Las cerraduras se han sincronizado correctamente.';
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Sincroniza los gateways de TTLock con la base de datos.
     *
     * @return array
     */
    public function syncGateways(): array {
        $result = [
            'success' => false,
            'message' => '',
            'total' => 0,
            'inserted' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'disabled' => 0,
            'locks_related' => 0,
        ];

        try {

            $sync_date = date('Y-m-d H:i:s');

            $audit_service = new auditLogsService();

            $gateways = $this->getGateways();

            $result['total'] = count($gateways);

            $stored_gateways = $this->gateways_model->findAllForSync();

            $stored_gateways_by_ttlock_id = [];

            foreach ($stored_gateways as $stored_gateway) {

                $stored_gateways_by_ttlock_id[
                        (int) $stored_gateway['ttlock_gateway_id']
                        ] = $stored_gateway;
            }

            $received_ttlock_ids = [];

            foreach ($gateways as $gateway) {

                $data = $this->prepareGatewayData(
                        $gateway,
                        $sync_date
                );

                $ttlock_gateway_id = (int) $data['ttlock_gateway_id'];

                $received_ttlock_ids[
                        $ttlock_gateway_id
                        ] = true;

                $stored_gateway = $stored_gateways_by_ttlock_id[$ttlock_gateway_id] ?? null;

                /*
                 * GATEWAY NUEVO
                 */
                if ($stored_gateway === null) {

                    $data['status'] = ttlockGatewaysModel::STATUS_ACTIVE;

                    $gateway_model = new ttlockGatewaysModel();

                    $this->fillGatewayModel(
                            $gateway_model,
                            $data
                    );

                    $gateway_id = $gateway_model->add();

                    if ($gateway_id <= 0) {

                        throw new RuntimeException(
                                        'No se pudo guardar el gateway '
                                        . $ttlock_gateway_id
                                        . ' en la base de datos.'
                                );
                    }

                    $audit_service->insert(
                            'wi_ttlock_gateways',
                            $gateway_id,
                            $data,
                            __METHOD__,
                            'Sincronización de gateways'
                    );

                    $result['inserted']++;
                } else {

                    /*
                     * GATEWAY EXISTENTE
                     */
                    $data['status'] = (int) $stored_gateway['status'];

                    $has_changes = $this->gatewayHasChanges(
                            $stored_gateway,
                            $data
                    );

                    $gateway_model = new ttlockGatewaysModel();

                    $this->fillGatewayModel(
                            $gateway_model,
                            $data
                    );

                    $gateway_model->setId(
                            (int) $stored_gateway['id']
                    );

                    /*
                     * Se actualiza siempre para guardar last_sync_at.
                     */
                    $gateway_model->update();

                    $gateway_id = (int) $stored_gateway['id'];

                    if ($has_changes) {

                        $audit_service->update(
                                'wi_ttlock_gateways',
                                $gateway_id,
                                $stored_gateway,
                                $data,
                                __METHOD__,
                                [
                                    'last_sync_at',
                                    'updated_at'
                                ],
                                'Sincronización de gateways'
                        );

                        $result['updated']++;
                    } else {

                        $result['unchanged']++;
                    }
                }

                /*
                 * RELACIÓN GATEWAY - CERRADURAS
                 */
                $locks_response = $this->ttlock_model->listLocksByGateway(
                        $ttlock_gateway_id
                );

                if (
                        !isset($locks_response['list']) || !is_array($locks_response['list'])
                ) {

                    throw new RuntimeException(
                                    'TTLock ha devuelto una respuesta no válida '
                                    . 'al consultar las cerraduras asociadas al gateway '
                                    . $ttlock_gateway_id
                                    . '.'
                            );
                }

                foreach ($locks_response['list'] as $lock) {

                    if (
                            !isset($lock['lockId']) || (int) $lock['lockId'] <= 0
                    ) {
                        continue;
                    }

                    $stored_lock = $this->devices_model->findByTtlockLockId(
                            (int) $lock['lockId']
                    );

                    /*
                     * Esta sincronización no crea cerraduras.
                     */
                    if ($stored_lock === null) {
                        continue;
                    }

                    $current_gateway_id = $stored_lock['gateway_id'] !== null ? (int) $stored_lock['gateway_id'] : null;

                    /*
                     * Ya está relacionada correctamente.
                     */
                    if ($current_gateway_id === $gateway_id) {
                        continue;
                    }

                    $this->devices_model->updateGatewayId(
                            (int) $stored_lock['id'],
                            $gateway_id
                    );

                    $audit_service->update(
                            'wi_ttlock_devices',
                            (int) $stored_lock['id'],
                            $stored_lock,
                            [
                                'gateway_id' => $gateway_id
                            ],
                            __METHOD__,
                            [],
                            'Asignación de gateway a cerradura durante sincronización'
                    );

                    $result['locks_related']++;
                }
            }

            /*
             * GATEWAYS QUE YA NO APARECEN EN TTLOCK
             */
            foreach ($stored_gateways as $stored_gateway) {

                $ttlock_gateway_id = (int) $stored_gateway['ttlock_gateway_id'];

                if (
                        isset(
                                $received_ttlock_ids[$ttlock_gateway_id]
                        )
                ) {
                    continue;
                }

                if (
                        (int) $stored_gateway['status'] === ttlockGatewaysModel::STATUS_INACTIVE
                ) {
                    continue;
                }

                $this->gateways_model->disableById(
                        (int) $stored_gateway['id'],
                        $sync_date
                );

                $audit_service->update(
                        'wi_ttlock_gateways',
                        (int) $stored_gateway['id'],
                        $stored_gateway,
                        [
                            'status' =>
                            ttlockGatewaysModel::STATUS_INACTIVE
                        ],
                        __METHOD__,
                        [],
                        'Desactivación de gateway durante sincronización'
                );

                $result['disabled']++;
            }

            $result['success'] = true;
            $result['message'] = 'Los gateways se han sincronizado correctamente.';
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Cambia el alias de una cerradura en TTLock
     * y actualiza el nombre en la base de datos.
     *
     * @param int $device_id Id interno de wi_ttlock_devices.
     * @param string $lock_alias Nuevo alias de la cerradura.
     *
     * @return array
     */
    public function renameLock(int $device_id, string $lock_alias): array {
        $result = [
            'success' => false,
            'message' => '',
        ];

        try {
            $lock_alias = trim($lock_alias);

            if (empty($device_id)) {
                throw new InvalidArgumentException(
                                'El identificador de la cerradura no es válido.'
                        );
            }

            if (empty($lock_alias)) {
                throw new InvalidArgumentException(
                                'El nombre de la cerradura no puede estar vacío.'
                        );
            }

            /*
             * Buscamos la cerradura por el id interno de nuestra BD.
             */
            $device = $this->devices_model->findById($device_id);

            if ($device === null) {
                throw new RuntimeException(
                                'No se ha encontrado la cerradura.'
                        );
            }

            $ttlock_lock_id = (int) $device['ttlock_lock_id'];

            if ($ttlock_lock_id <= 0) {
                throw new RuntimeException(
                                'La cerradura no tiene un identificador válido de TTLock.'
                        );
            }

            /*
             * Primero actualizamos el alias en TTLock.
             */
            $response = $this->ttlock_model->renameLock(
                    $ttlock_lock_id,
                    $lock_alias
            );

            if ((int) ($response['errcode'] ?? -1) !== 0) {
                throw new RuntimeException(
                                $response['errmsg'] ?? 'No se pudo cambiar el nombre de la cerradura en TTLock.'
                        );
            }

            /*
             * Solo cuando TTLock responde correctamente,
             * actualizamos nuestra base de datos.
             */
            $updated = $this->devices_model->updateLockAlias(
                    $device_id,
                    $lock_alias
            );

            if (!$updated) {
                throw new RuntimeException(
                                'El nombre se cambió en TTLock, pero no se pudo actualizar en la base de datos.'
                        );
            }
            
            $audit_service = new auditLogsService();

            $audit_service->update(
                    'wi_ttlock_gateways',
                    $device_id,
                    $device,
                    ['lock_alias' => $lock_alias],
                    __METHOD__,
                    [],
                    'Renombrar cerradura'
            );

            $result['success'] = true;
            $result['message'] = 'El nombre de la cerradura se ha actualizado correctamente.';
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Cambia el nombre de un gateway en TTLock
     * y actualiza el nombre en la base de datos.
     *
     * @param int $gateway_id Id interno de wi_ttlock_gateways.
     * @param string $gateway_name Nuevo nombre del gateway.
     *
     * @return array
     */
    public function renameGateway(int $gateway_id, string $gateway_name): array {
        $result = [
            'success' => false,
            'message' => '',
        ];

        try {
            $gateway_name = trim($gateway_name);

            if (empty($gateway_id)) {
                throw new InvalidArgumentException(
                                'El identificador del gateway no es válido.'
                        );
            }

            if (empty($gateway_name)) {
                throw new InvalidArgumentException(
                                'El nombre del gateway no puede estar vacío.'
                        );
            }

            /*
             * Buscamos el gateway por el id interno de nuestra BD.
             */
            $gateway = $this->gateways_model->findById($gateway_id);

            if ($gateway === null) {
                throw new RuntimeException(
                                'No se ha encontrado el gateway.'
                        );
            }

            $ttlock_gateway_id = (int) $gateway['ttlock_gateway_id'];

            if ($ttlock_gateway_id <= 0) {
                throw new RuntimeException(
                                'El gateway no tiene un identificador válido de TTLock.'
                        );
            }

            /*
             * Primero actualizamos el nombre en TTLock.
             */
            $response = $this->ttlock_model->renameGateway(
                    $ttlock_gateway_id,
                    $gateway_name
            );

            if ((int) ($response['errcode'] ?? -1) !== 0) {
                throw new RuntimeException(
                                $response['errmsg'] ?? 'No se pudo cambiar el nombre del gateway en TTLock.'
                        );
            }

            /*
             * Solo cuando TTLock responde correctamente,
             * actualizamos nuestra base de datos.
             */
            $updated = $this->gateways_model->updateGatewayName(
                    $gateway_id,
                    $gateway_name
            );

            if (!$updated) {
                throw new RuntimeException(
                                'El nombre se cambió en TTLock, pero no se pudo actualizar en la base de datos.'
                        );
            }
            
            $audit_service = new auditLogsService();

            $audit_service->update(
                    'wi_ttlock_gateways',
                    $gateway_id,
                    $gateway,
                    ['gateway_name' => $gateway_name],
                    __METHOD__,
                    [],
                    'Renombrar gateway'
            );

            $result['success'] = true;
            $result['message'] = 'El nombre del gateway se ha actualizado correctamente.';
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    public function changeDeviceStatus(int $device_id, int $status): array {

        $device = $this->devices_model->findById($device_id);

        if ($device === null) {
            return [
                'success' => false,
                'message' => 'La cerradura no existe.'
            ];
        }

        $this->devices_model->updateStatusById(
                $device_id,
                $status
        );
        
        $audit_service = new auditLogsService();
        $audit_service->update(
                'wi_ttlock_devices',
                $device_id,
                $device,
                [
                    'status' => $status
                ],
                __METHOD__,
                [],
                $status == ttlockDevicesModel::STATUS_ACTIVE ? 'Activación de cerradura' : 'Desactivación de cerradura'
        );

        return [
            'success' => true,
            'message' => $status == ttlockDevicesModel::STATUS_ACTIVE ? 'Cerradura activada correctamente.' : 'Cerradura desactivada correctamente.'
        ];
    }

    public function changeGatewayStatus(int $gateway_id, int $status): array {

        $device = $this->gateways_model->findById($gateway_id);

        if ($device === null) {
            return [
                'success' => false,
                'message' => 'El gateway no existe.'
            ];
        }

        $this->gateways_model->updateStatusById(
                $gateway_id,
                $status
        );
        
        $audit_service = new auditLogsService();
        $audit_service->update(
                'wi_ttlock_gateways',
                $gateway_id,
                $device,
                [
                    'status' => $status
                ],
                __METHOD__,
                [],
                $status == ttlockGatewaysModel::STATUS_ACTIVE ? 'Activación de gateway' : 'Desactivación de gateway'
        );

        return [
            'success' => true,
            'message' => $status == ttlockGatewaysModel::STATUS_ACTIVE ? 'Gateway activado correctamente.' : 'Gateway desactivado correctamente.'
        ];
    }
    
    /**
     * Abre remotamente una cerradura.
     *
     * @param int $device_id Id interno de wi_ttlock_devices.
     *
     * @return array
     */
    public function unlockLock(int $device_id): array {
        $result = [
            'success' => false,
            'message' => '',
        ];

        try {

            if (empty($device_id)) {
                throw new InvalidArgumentException(
                                'El identificador de la cerradura no es válido.'
                        );
            }

            $device = $this->devices_model->findById($device_id, true);

            if (empty($device)) {
                throw new RuntimeException(
                                'No se ha encontrado la cerradura.'
                        );
            }

            if (empty($device->ttlock_lock_id)) {
                throw new RuntimeException(
                                'La cerradura no tiene asociado un identificador de TTLock.'
                        );
            }

            if (empty($device->gateway_id)) {
                throw new RuntimeException(
                                'La cerradura no tiene ningún gateway asociado.'
                        );
            }

            $response = $this->ttlock_model->unlock(
                    (int) $device->ttlock_lock_id
            );

            if (
                    !isset($response['errcode']) ||
                    (int) $response['errcode'] !== 0
            ) {

                $message = $response['errmsg'] ?? 'TTLock no ha podido abrir la cerradura.';

                throw new RuntimeException($message);
            }
            
            

            $result['success'] = true;
            $result['message'] = 'La cerradura se ha abierto correctamente.';
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Cierra remotamente una cerradura.
     *
     * @param int $device_id Id interno de wi_ttlock_devices.
     *
     * @return array
     */
    public function lockLock(int $device_id): array {
        $result = [
            'success' => false,
            'message' => '',
        ];

        try {

            if (empty($device_id)) {
                throw new InvalidArgumentException(
                                'El identificador de la cerradura no es válido.'
                        );
            }

            $device = $this->devices_model->findById($device_id, true);

            if (empty($device)) {
                throw new RuntimeException(
                                'No se ha encontrado la cerradura.'
                        );
            }

            if (empty($device->ttlock_lock_id)) {
                throw new RuntimeException(
                                'La cerradura no tiene asociado un identificador de TTLock.'
                        );
            }

            if (empty($device->gateway_id)) {
                throw new RuntimeException(
                                'La cerradura no tiene ningún gateway asociado.'
                        );
            }

            $response = $this->ttlock_model->lock(
                    (int) $device->ttlock_lock_id
            );

            if (
                    !isset($response['errcode']) ||
                    (int) $response['errcode'] !== 0
            ) {

                $message = $response['errmsg'] ?? 'TTLock no ha podido cerrar la cerradura.';

                throw new RuntimeException($message);
            }

            $result['success'] = true;
            $result['message'] = 'La cerradura se ha cerrado correctamente.';
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Crea un PIN personalizado en TTLock mediante gateway.
     *
     * @param int $device_id Id interno de wi_ttlock_devices.
     * @param string $pin PIN que se va a crear.
     * @param string $name Nombre del PIN en TTLock.
     * @param string $valid_from Fecha inicial en formato DATETIME.
     * @param string $valid_until Fecha final en formato DATETIME.
     *
     * @return array
     */
    public function createKeyboardPwd(
            int $device_id,
            string $pin,
            string $name,
            string $valid_from,
            string $valid_until,
            string $access_type
    ): array {

        $result = [
            'success' => false,
            'message' => '',
            'keyboard_pwd_id' => null,
        ];

        try {

            $pin = trim($pin);
            $name = trim($name);
            $access_type = strtoupper(trim($access_type));

            if ($device_id <= 0) {
                throw new InvalidArgumentException(
                                'El identificador de la cerradura no es válido.'
                        );
            }

            if ($pin === '') {
                throw new InvalidArgumentException(
                                'El PIN no puede estar vacío.'
                        );
            }

            if ($name === '') {
                throw new InvalidArgumentException(
                                'El nombre del PIN no puede estar vacío.'
                        );
            }

            /*
             * Traducimos nuestro tipo de acceso
             * al tipo utilizado por TTLock.
             *
             * 1 = Un solo uso
             * 2 = Permanente
             * 3 = Periodo
             */
            $keyboard_pwd_type = match ($access_type) {
                'ONE_TIME' => 1,
                'PERMANENT' => 2,
                'TEMPORAL' => 3,
                default => throw new InvalidArgumentException(
                        'El tipo de acceso del PIN no es válido.'
                )
            };

            /*
             * Buscamos la cerradura por su id interno.
             */
            $device = $this->devices_model->findById($device_id);

            if (empty($device)) {
                throw new RuntimeException(
                                'No se ha encontrado la cerradura.'
                        );
            }

            $lock_id = (int) $device['ttlock_lock_id'];

            if ($lock_id <= 0) {
                throw new RuntimeException(
                                'La cerradura no tiene un identificador TTLock válido.'
                        );
            }

            /*
             * Convertimos los DATETIME a timestamp.
             */
            $start_date = strtotime($valid_from);
            $end_date = strtotime($valid_until);

            if ($start_date === false || $end_date === false) {
                throw new InvalidArgumentException(
                                'Las fechas de validez no son correctas.'
                        );
            }

            if ($end_date <= $start_date) {
                throw new InvalidArgumentException(
                                'La fecha final debe ser posterior a la fecha inicial.'
                        );
            }

            /*
             * TTLock necesita las fechas en milisegundos.
             */
            $start_date_ms = $start_date * 1000;
            $end_date_ms = $end_date * 1000;

            /*
             * Creamos nuestro PIN personalizado mediante gateway.
             */
            $response = $this->ttlock_model->addKeyboardPassword(
                    $lock_id,
                    $pin,
                    $name,
                    $keyboard_pwd_type,
                    $start_date_ms,
                    $end_date_ms
            );

            if (empty($response['keyboardPwdId'])) {
                throw new RuntimeException(
                                'TTLock no ha devuelto el identificador del PIN.'
                        );
            }

            $result['success'] = true;
            $result['message'] = 'PIN creado correctamente en TTLock.';
            $result['keyboard_pwd_id'] = (int) $response['keyboardPwdId'];
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }
    
    /**
     * Elimina un PIN de una cerradura TTLock.
     */
    public function deleteKeyboardPwd(
            int $device_id,
            int $keyboard_pwd_id
    ): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if ($device_id <= 0) {
                throw new InvalidArgumentException(
                                'El identificador de la cerradura no es válido.'
                        );
            }

            if ($keyboard_pwd_id <= 0) {
                throw new InvalidArgumentException(
                                'El identificador del PIN TTLock no es válido.'
                        );
            }

            $device = $this->devices_model->findById($device_id, false);

            if (empty($device)) {
                throw new RuntimeException(
                                'La cerradura no existe.'
                        );
            }

            $ttlock_lock_id = (int) $device['ttlock_lock_id'];

            if ($ttlock_lock_id <= 0) {
                throw new RuntimeException(
                                'La cerradura no tiene un identificador TTLock válido.'
                        );
            }

            $this->ttlock_model->deleteKeyboardPassword(
                    $ttlock_lock_id,
                    $keyboard_pwd_id
            );

            $result['success'] = true;
            $result['message'] = 'PIN eliminado correctamente de la cerradura.';
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }
    
    /**
 * Comprueba el estado de conexión de los gateways TTLock.
 *
 * @return array
 */
    public function checkGatewaysStatus(): array {

        $result = [
            'success' => false,
            'message' => '',
            'total' => 0,
            'online' => 0,
            'offline' => 0,
            'changed' => 0,
            'disconnected' => [],
            'reconnected' => [],
        ];

        try {

            $sync_date = date('Y-m-d H:i:s');

            $gateways = $this->getGateways();

            $result['total'] = count($gateways);

            foreach ($gateways as $gateway) {

                $ttlock_gateway_id = (int) ($gateway['gatewayId'] ?? 0);

                if ($ttlock_gateway_id <= 0) {
                    continue;
                }

                $stored_gateway = $this->gateways_model->findByTtlockGatewayId($ttlock_gateway_id);

                if ($stored_gateway === null) {
                    continue;
                }

                $old_status = (int) $stored_gateway['is_online'];
                $new_status = (int) ($gateway['isOnline'] ?? 0);

                if ($new_status === 1) {
                    $result['online']++;
                } else {
                    $result['offline']++;
                }

                $this->gateways_model->updateOnlineStatus(
                        (int) $stored_gateway['id'],
                        $new_status,
                        $sync_date
                );

                if ($old_status === $new_status) {
                    continue;
                }

                $result['changed']++;

                if ($old_status === 1 && $new_status === 0) {

                    $result['disconnected'][] = [
                        'id' => (int) $stored_gateway['id'],
                        'ttlock_gateway_id' => $ttlock_gateway_id,
                        'gateway_name' => $stored_gateway['gateway_name'],
                        'gateway_mac' => $stored_gateway['gateway_mac'],
                    ];
                } elseif ($old_status === 0 && $new_status === 1) {

                    $result['reconnected'][] = [
                        'id' => (int) $stored_gateway['id'],
                        'ttlock_gateway_id' => $ttlock_gateway_id,
                        'gateway_name' => $stored_gateway['gateway_name'],
                        'gateway_mac' => $stored_gateway['gateway_mac'],
                    ];
                }
            }

            $result['success'] = true;
            $result['message'] = 'El estado de los gateways se ha comprobado correctamente.';
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Obtiene todas las cerraduras disponibles en TTLock.
     *
     * Gestiona automáticamente la paginación de la API.
     *
     * @return array
     *
     * @throws RuntimeException
     */
    private function getLocks(): array {
        $page_no = 1;
        $page_size = 100;
        $locks = [];

        do {
            $response = $this->ttlock_model->listLocks(
                    $page_no,
                    $page_size
            );

            $this->validateLocksResponse($response);

            $locks = array_merge(
                    $locks,
                    $response['list']
            );

            $total_pages = (int) $response['pages'];
            $page_no++;
        } while ($page_no <= $total_pages);

        return $locks;
    }

    /**
     * Obtiene todos los gateways disponibles en TTLock.
     *
     * Gestiona automáticamente la paginación.
     *
     * @return array
     *
     * @throws RuntimeException
     */
    private function getGateways(): array {
        $page_no = 1;
        $page_size = 100;
        $gateways = [];

        do {

            $response = $this->ttlock_model->listGateways(
                    $page_no,
                    $page_size
            );

            $this->validateGatewaysResponse($response);

            $gateways = array_merge(
                    $gateways,
                    $response['list']
            );

            $total_pages = (int) $response['pages'];

            $page_no++;
        } while ($page_no <= $total_pages);

        return $gateways;
    }

    /**
     * Comprueba que la respuesta de listLocks sea válida.
     *
     * @param array $response
     *
     * @return void
     *
     * @throws RuntimeException
     */
    private function validateLocksResponse(array $response): void {
        $required_fields = [
            'list',
            'pageNo',
            'pageSize',
            'pages',
            'total',
        ];

        foreach ($required_fields as $field) {
            if (!array_key_exists($field, $response)) {
                throw new RuntimeException(
                                'La respuesta de TTLock no contiene el campo requerido: '
                                . $field
                        );
            }
        }

        if (!is_array($response['list'])) {
            throw new RuntimeException(
                            'El listado de cerraduras recibido desde TTLock no es válido.'
                    );
        }

        if ((int) $response['pageNo'] <= 0) {
            throw new RuntimeException(
                            'El número de página recibido desde TTLock no es válido.'
                    );
        }

        if ((int) $response['pageSize'] <= 0) {
            throw new RuntimeException(
                            'El tamaño de página recibido desde TTLock no es válido.'
                    );
        }

        if ((int) $response['pages'] < 0) {
            throw new RuntimeException(
                            'El número total de páginas recibido desde TTLock no es válido.'
                    );
        }

        if ((int) $response['total'] < 0) {
            throw new RuntimeException(
                            'El número total de cerraduras recibido desde TTLock no es válido.'
                    );
        }

        foreach ($response['list'] as $lock) {
            if (
                    !is_array($lock) || !isset($lock['lockId']) || (int) $lock['lockId'] <= 0
            ) {
                throw new RuntimeException(
                                'TTLock ha devuelto una cerradura sin un identificador válido.'
                        );
            }

            if (
                    !isset($lock['lockMac']) || trim((string) $lock['lockMac']) === ''
            ) {
                throw new RuntimeException(
                                'TTLock ha devuelto una cerradura sin una dirección MAC válida.'
                        );
            }
        }
    }

    /**
     * Comprueba que la respuesta de listGateways sea válida.
     *
     * @param array $response
     *
     * @return void
     *
     * @throws RuntimeException
     */
    private function validateGatewaysResponse(array $response): void {
        $required_fields = [
            'list',
            'pageNo',
            'pageSize',
            'pages',
            'total',
        ];

        foreach ($required_fields as $field) {

            if (!array_key_exists($field, $response)) {

                throw new RuntimeException(
                                'La respuesta de TTLock no contiene el campo requerido: '
                                . $field
                        );
            }
        }

        if (!is_array($response['list'])) {

            throw new RuntimeException(
                            'El listado de gateways recibido desde TTLock no es válido.'
                    );
        }

        if ((int) $response['pageNo'] <= 0) {

            throw new RuntimeException(
                            'El número de página recibido desde TTLock no es válido.'
                    );
        }

        if ((int) $response['pageSize'] <= 0) {

            throw new RuntimeException(
                            'El tamaño de página recibido desde TTLock no es válido.'
                    );
        }

        if ((int) $response['pages'] < 0) {

            throw new RuntimeException(
                            'El número total de páginas recibido desde TTLock no es válido.'
                    );
        }

        if ((int) $response['total'] < 0) {

            throw new RuntimeException(
                            'El número total de gateways recibido desde TTLock no es válido.'
                    );
        }

        foreach ($response['list'] as $gateway) {

            if (
                    !is_array($gateway) || !isset($gateway['gatewayId']) || (int) $gateway['gatewayId'] <= 0
            ) {

                throw new RuntimeException(
                                'TTLock ha devuelto un gateway sin un identificador válido.'
                        );
            }

            if (
                    !isset($gateway['gatewayMac']) || trim((string) $gateway['gatewayMac']) === ''
            ) {

                throw new RuntimeException(
                                'TTLock ha devuelto un gateway sin una dirección MAC válida.'
                        );
            }
        }
    }

    /**
     * Convierte los datos de una cerradura de TTLock
     * al formato utilizado en la base de datos.
     *
     * @param array $lock
     *
     * @return array
     */
    private function prepareLockData(array $lock, string $sync_date): array {
        return [
            'ttlock_lock_id' => (int) $lock['lockId'],
            'lock_alias' => $lock['lockAlias'] ?? null,
            'lock_name' => $lock['lockName'] ?? null,
            'lock_mac' => $lock['lockMac'] ?? '',
            'electric_quantity' => isset($lock['electricQuantity']) ? (int) $lock['electricQuantity'] : null,
            'is_online' => (int) ($lock['isOnline'] ?? 0),
            'has_gateway' => (int) ($lock['hasGateway'] ?? 0),
            'passage_mode' => isset($lock['passageMode']) ? (int) $lock['passageMode'] : null,
            'timezone_raw_offset' => isset($lock['timezoneRawOffset']) ? (int) $lock['timezoneRawOffset'] : null,
            'keyboard_pwd_version' => isset($lock['keyboardPwdVersion']) ? (int) $lock['keyboardPwdVersion'] : null,
            'feature_value' => $lock['featureValue'] ?? null,
            'wireless_keypad_feature_value' =>
            $lock['wirelessKeypadFeatureValue'] ?? null,
            'bind_date' => $this->millisecondsToDateTime(
                    isset($lock['bindDate']) ? (int) $lock['bindDate'] : null
            ),
            'electric_quantity_update_date' =>
            $this->millisecondsToDateTime(
                    isset($lock['electricQuantityUpdateDate']) ? (int) $lock['electricQuantityUpdateDate'] : null
            ),
            'last_sync_at' => $sync_date,
        ];
    }

    /**
     * Convierte los datos de un gateway de TTLock
     * al formato utilizado en la base de datos.
     *
     * @param array $gateway
     * @param string $sync_date
     *
     * @return array
     */
    private function prepareGatewayData(
            array $gateway,
            string $sync_date
    ): array {

        return [
            'ttlock_gateway_id' =>
            (int) $gateway['gatewayId'],
            'gateway_name' =>
            $gateway['gatewayName'] ?? '',
            'gateway_mac' =>
            $gateway['gatewayMac'] ?? '',
            'network_mac' =>
            $gateway['networkMac'] ?? null,
            'serial_number' =>
            $gateway['serialNumber'] ?? null,
            'gateway_version' =>
            isset($gateway['gatewayVersion']) ? (int) $gateway['gatewayVersion'] : null,
            'device_num' =>
            (int) ($gateway['deviceNum'] ?? 0),
            'lock_num' =>
            (int) ($gateway['lockNum'] ?? 0),
            'electric_meter_count' =>
            (int) ($gateway['electricMeterCount'] ?? 0),
            'water_meter_count' =>
            (int) ($gateway['waterMeterCount'] ?? 0),
            'lift_control_count' =>
            (int) ($gateway['liftControlCount'] ?? 0),
            'is_online' =>
            (int) ($gateway['isOnline'] ?? 0),
            'last_sync_at' =>
            $sync_date,
        ];
    }

    /**
     * Convierte una fecha expresada en milisegundos
     * a formato DATETIME de MySQL.
     *
     * @param int|null $milliseconds
     *
     * @return string|null
     */
    private function millisecondsToDateTime(?int $milliseconds): ?string {
        if ($milliseconds === null || $milliseconds <= 0) {
            return null;
        }

        return date(
                'Y-m-d H:i:s',
                (int) floor($milliseconds / 1000)
        );
    }

    /**
     * Asigna al modelo los datos preparados de una cerradura.
     *
     * @param ttlockDevicesModel $device
     * @param array $data
     *
     * @return void
     */
    private function fillDeviceModel(
            ttlockDevicesModel $device,
            array $data
    ): void {
        $device->setGatewayId(
                isset($data['gateway_id']) ? (int) $data['gateway_id'] : null
        );

        $device->setTtlockLockId(
                (int) $data['ttlock_lock_id']
        );

        $device->setLockAlias(
                $data['lock_alias']
        );

        $device->setLockName(
                $data['lock_name']
        );

        $device->setLockMac(
                $data['lock_mac']
        );

        $device->setElectricQuantity(
                $data['electric_quantity']
        );

        $device->setIsOnline(
                (int) $data['is_online']
        );

        $device->setHasGateway(
                (int) $data['has_gateway']
        );

        $device->setPassageMode(
                $data['passage_mode']
        );

        $device->setTimezoneRawOffset(
                $data['timezone_raw_offset']
        );

        $device->setKeyboardPwdVersion(
                $data['keyboard_pwd_version']
        );

        $device->setFeatureValue(
                $data['feature_value']
        );

        $device->setWirelessKeypadFeatureValue(
                $data['wireless_keypad_feature_value']
        );

        $device->setBindDate(
                $data['bind_date']
        );

        $device->setElectricQuantityUpdateDate(
                $data['electric_quantity_update_date']
        );

        $device->setStatus(
                (int) $data['status']
        );

        $device->setLastSyncAt(
                $data['last_sync_at']
        );
    }

    /**
     * Asigna al modelo los datos preparados de un gateway.
     *
     * @param ttlockGatewaysModel $gateway_model
     * @param array $data
     *
     * @return void
     */
    private function fillGatewayModel(
            ttlockGatewaysModel $gateway_model,
            array $data
    ): void {

        $gateway_model->setTtlockGatewayId(
                (int) $data['ttlock_gateway_id']
        );

        $gateway_model->setGatewayName(
                $data['gateway_name']
        );

        $gateway_model->setGatewayMac(
                $data['gateway_mac']
        );

        $gateway_model->setNetworkMac(
                $data['network_mac']
        );

        $gateway_model->setSerialNumber(
                $data['serial_number']
        );

        $gateway_model->setGatewayVersion(
                $data['gateway_version']
        );

        $gateway_model->setDeviceNum(
                (int) $data['device_num']
        );

        $gateway_model->setLockNum(
                (int) $data['lock_num']
        );

        $gateway_model->setElectricMeterCount(
                (int) $data['electric_meter_count']
        );

        $gateway_model->setWaterMeterCount(
                (int) $data['water_meter_count']
        );

        $gateway_model->setLiftControlCount(
                (int) $data['lift_control_count']
        );

        $gateway_model->setIsOnline(
                (int) $data['is_online']
        );

        $gateway_model->setStatus(
                (int) $data['status']
        );

        $gateway_model->setLastSyncAt(
                $data['last_sync_at']
        );
    }

    /**
     * Comprueba si los datos sincronizables de una cerradura han cambiado.
     *
     * @param array $stored_lock
     * @param array $new_data
     *
     * @return bool
     */
    private function lockHasChanges(
            array $stored_lock,
            array $new_data
    ): bool {
        $fields = [
            'lock_alias',
            'lock_name',
            'lock_mac',
            'electric_quantity',
            'is_online',
            'has_gateway',
            'passage_mode',
            'timezone_raw_offset',
            'keyboard_pwd_version',
            'feature_value',
            'wireless_keypad_feature_value',
            'bind_date',
            'electric_quantity_update_date',
            'status',
        ];

        foreach ($fields as $field) {
            $stored_value = $stored_lock[$field] ?? null;
            $new_value = $new_data[$field] ?? null;

            /*
             * Convertimos ambos a string para evitar diferencias entre:
             *
             * 1 y "1"
             * 0 y "0"
             *
             * Los valores null también se comparan correctamente.
             */
            if ((string) $stored_value !== (string) $new_value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Comprueba si los datos sincronizables de un gateway han cambiado.
     *
     * @param array $stored_gateway
     * @param array $new_data
     *
     * @return bool
     */
    private function gatewayHasChanges(
            array $stored_gateway,
            array $new_data
    ): bool {

        $fields = [
            'gateway_name',
            'gateway_mac',
            'network_mac',
            'serial_number',
            'gateway_version',
            'device_num',
            'lock_num',
            'electric_meter_count',
            'water_meter_count',
            'lift_control_count',
            'is_online',
        ];

        foreach ($fields as $field) {

            $stored_value = $stored_gateway[$field] ?? null;

            $new_value = $new_data[$field] ?? null;

            if (
                    (string) $stored_value !== (string) $new_value
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtiene el id interno del gateway asociado a una cerradura.
     *
     * Si la cerradura no tiene gateway, el gateway no existe en nuestra
     * base de datos o la respuesta de TTLock no es válida, devuelve null.
     *
     * @param array $lock
     *
     * @return int|null
     */
    private function resolveLockGatewayId(array $lock): ?int {
        if ((int) ($lock['hasGateway'] ?? 0) !== 1) {
            return null;
        }

        $response = $this->ttlock_model->listGatewaysByLock(
                (int) $lock['lockId']
        );

        if (
                !isset($response['list']) || !is_array($response['list']) || empty($response['list'])
        ) {
            return null;
        }

        /*
         * Normalmente una cerradura estará asociada a un único gateway.
         */
        $gateway = $response['list'][0];

        if (
                !isset($gateway['gatewayId']) || (int) $gateway['gatewayId'] <= 0
        ) {
            return null;
        }

        $stored_gateway = $this->gateways_model->findByTtlockGatewayId(
                (int) $gateway['gatewayId']
        );

        if ($stored_gateway === null) {
            return null;
        }

        return (int) $stored_gateway['id'];
    }
}
