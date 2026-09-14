<?php

declare(strict_types=1);

/**
 * Servicio para la gestión de PINs de acceso.
 *
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 11 ago 2026
 */
class accessPinsService
{
    private ttlockService $ttlock_service;
    private pinEncryptionService $encryption_service;
    private auditLogsService $audit_service;

    public function __construct()
    {
        $this->ttlock_service = new ttlockService();
        $this->encryption_service = new pinEncryptionService();
        $this->audit_service = new auditLogsService();
    }


    /**
     * Crea un PIN asociado a una reserva.
     *
     * El PIN se genera automáticamente.
     */
    public function createReservationPin(int $reservation_id, int $facility_id, string $valid_from, string $valid_until): array {

        return $this->createPin(
                        $facility_id,
                        $valid_from,
                        $valid_until,
                        'RESERVA',
                        'TEMPORAL',
                        null,
                        $reservation_id,
                        null,
                        null
                );
    }

    /**
     * Crea un PIN manual.
     *
     * Si $pin es null, se genera automáticamente.
     */
    public function createManualPin(int $facility_id, string $valid_from, string $valid_until, string $description, string $access_type = 'TEMPORAL', ?string $pin = null): array {

        return $this->createPin(
                        $facility_id,
                        $valid_from,
                        $valid_until,
                        'MANUAL',
                        $access_type,
                        $description,
                        null,
                        null,
                        $pin
                );
    }
    
    public function createBonusPin(int $user_bonus_id): array {

        $result = [
            'success' => false,
            'partial' => false,
            'message' => '',
            'pin' => null,
            'pin_ids' => [],
            'success_facilities' => 0,
            'error_facilities' => 0,
            'access_facilities' => [],
            'reservation_facilities' => []
        ];

        try {

            if ($user_bonus_id <= 0) {
                throw new InvalidArgumentException('El identificador del bono del usuario no es válido.');
            }

            /*
             * Bono asignado/comprado por el usuario.
             */
            $users_bonuses_model = new usersBonusesModel();

            $user_bonus = $users_bonuses_model->findDetailById(
                    $user_bonus_id,
                    false
            );

            if (empty($user_bonus)) {
                throw new RuntimeException('El bono del usuario no existe.');
            }

            /*
             * Solo los bonos TIME generan PIN propio.
             */
            if ((string) $user_bonus['bonus_type'] !== bonusesModel::TYPE_TIME) {

                $result['success'] = true;
                $result['message'] = 'El bono no requiere PIN de acceso.';

                return $result;
            }

            /*
             * Solo se genera acceso cuando el bono ya está activo.
             */
            if ((int) $user_bonus['status'] !== usersBonusesModel::STATUS_ACTIVE) {
                throw new RuntimeException('El bono debe estar activo para generar el PIN de acceso.');
            }

            $valid_from = trim((string) ($user_bonus['valid_from'] ?? ''));
            $valid_until = trim((string) ($user_bonus['expires_at'] ?? ''));

            if ($valid_from === '' || $valid_until === '') {
                throw new RuntimeException('El bono no tiene configurado un periodo de validez correcto.');
            }

            /*
             * Evitamos generar los PIN dos veces si por cualquier motivo
             * vuelve a ejecutarse la activación.
             */
            $access_pins_model = new accessPinsModel();

            $existing_pins = $access_pins_model->findByUserBonusId(
                    $user_bonus_id,
                    false
            );

            if (!empty($existing_pins)) {

                $pin = $this->encryption_service->decrypt(
                        $existing_pins[0]['pin_code_encrypted']
                );

                $result['success'] = true;
                $result['pin'] = $pin;
                $result['pin_ids'] = array_map(
                        fn($item) => (int) $item['id'],
                        $existing_pins
                );
                $result['message'] = 'El bono ya tiene un PIN de acceso asociado.';

                return $result;
            }

            /*
             * Instalaciones asociadas al bono maestro.
             */
            $bonuses_facilities_model = new bonusesFacilitiesModel();

            $facilities = $bonuses_facilities_model->findFacilitiesByBonusId(
                    (int) $user_bonus['bonus_id'],
                    false
            );

            if (empty($facilities)) {

                $result['success'] = true;
                $result['message'] = 'El bono no tiene instalaciones asociadas.';

                return $result;
            }

            /*
             * Separamos:
             *
             * booking_enabled = 0 + access_enabled = 1
             *      -> PIN general del bono.
             *
             * booking_enabled = 1
             *      -> requiere reserva y tendrá su PIN de reserva.
             */
            $access_facilities = [];
            $reservation_facilities = [];
            $all_device_ids = [];

            $facilities_devices_model = new facilitiesDevicesModel();

            foreach ($facilities as $facility) {

                $facility_id = (int) $facility['id'];

                if ((int) $facility['booking_enabled'] === 1) {

                    $reservation_facilities[] = [
                        'id' => $facility_id,
                        'name' => $facility['name']
                    ];

                    continue;
                }

                if ((int) $facility['access_enabled'] !== 1) {
                    continue;
                }

                $devices = $facilities_devices_model->findByFacilityId(
                        $facility_id,
                        false
                );

                /*
                 * Si tiene activado el control de acceso pero no dispone
                 * de cerraduras, no podemos crear PIN para esa instalación.
                 */
                if (empty($devices)) {
                    continue;
                }

                $device_ids = [];

                foreach ($devices as $device) {

                    $device_id = (int) $device['ttlock_device_id'];

                    if ($device_id <= 0) {
                        continue;
                    }

                    $device_ids[] = $device_id;
                    $all_device_ids[] = $device_id;
                }

                if (empty($device_ids)) {
                    continue;
                }

                $access_facilities[] = [
                    'id' => $facility_id,
                    'name' => $facility['name']
                ];
            }

            $result['access_facilities'] = $access_facilities;
            $result['reservation_facilities'] = $reservation_facilities;

            /*
             * Puede ser un TIME compuesto únicamente por instalaciones
             * reservables o instalaciones sin control de acceso.
             *
             * No es un error.
             */
            if (empty($access_facilities)) {

                $result['success'] = true;
                $result['message'] = 'El bono no requiere un PIN general de acceso.';

                return $result;
            }

            /*
             * Generamos UN único PIN comprobando todas las cerraduras
             * de todas las instalaciones donde tendrá validez.
             */
            $all_device_ids = array_values(
                    array_unique($all_device_ids)
            );

            $pin = $this->generatePin($all_device_ids);

            /*
             * Creamos un registro de wi_access_pins por instalación,
             * pero siempre utilizando el mismo código PIN.
             */
            foreach ($access_facilities as $facility) {

                $pin_result = $this->createPin(
                        (int) $facility['id'],
                        $valid_from,
                        $valid_until,
                        accessPinsModel::PIN_TYPE_BONUS,
                        accessPinsModel::ACCESS_TYPE_TEMPORARY,
                        'Acceso mediante bono',
                        null,
                        $user_bonus_id,
                        $pin
                );

                if ($pin_result['success']) {

                    $result['pin_ids'][] = (int) $pin_result['id'];
                    $result['success_facilities']++;

                    if (!empty($pin_result['partial'])) {
                        $result['partial'] = true;
                    }
                } else {

                    $result['error_facilities']++;
                }
            }

            $result['pin'] = $pin;

            /*
             * Resultado global.
             */
            if ($result['error_facilities'] === 0) {

                $result['success'] = true;

                if ($result['partial']) {
                    $result['message'] = 'El PIN del bono se ha creado, pero queda pendiente de sincronización en alguna cerradura.';
                } else {
                    $result['message'] = 'El PIN del bono se ha creado y sincronizado correctamente.';
                }
            } elseif ($result['success_facilities'] > 0) {

                $result['success'] = true;
                $result['partial'] = true;
                $result['message'] = 'El PIN del bono se ha creado en algunas instalaciones, pero se han producido errores en otras.';
            } else {

                $result['message'] = 'No se ha podido crear el PIN del bono en ninguna instalación.';
            }
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Revoca el PIN asociado a una reserva.
     *
     * El PIN se elimina primero de todas las cerraduras.
     * Solo cuando todas las eliminaciones han sido correctas
     * se considera completamente revocado.
     */
    public function revokeReservationPin(int $reservation_id): array {

        $result = [
            'success' => false,
            'message' => '',
            'id' => 0
        ];

        try {

            if ($reservation_id <= 0) {
                throw new InvalidArgumentException(
                                'El identificador de la reserva no es válido.'
                        );
            }

            $access_pins_model = new accessPinsModel();

            $pin = $access_pins_model->findByReservationId(
                    $reservation_id,
                    false
            );

            /*
             * No tener PIN no debe considerarse un error.
             *
             * Puede ocurrir, por ejemplo, si la instalación
             * no tiene control de acceso.
             */
            if (empty($pin)) {

                $result['success'] = true;
                $result['message'] = 'La reserva no tiene ningún PIN asociado.';

                return $result;
            }

            $access_pin_id = (int) $pin['id'];

            $access_pin_devices_model = new accessPinDevicesModel();

            $devices = $access_pin_devices_model->findByAccessPinId(
                    $access_pin_id,
                    false
            );

            $error_devices = 0;

            foreach ($devices as $device) {

                $relation_id = (int) $device['id'];
                $device_id = (int) $device['ttlock_device_id'];
                $keyboard_pwd_id = isset($device['ttlock_keyboard_pwd_id']) ? (int) $device['ttlock_keyboard_pwd_id'] : 0;

                /*
                 * Si no existe keyboardPwdId significa que este
                 * PIN nunca llegó a sincronizarse con esa cerradura
                 * o ya fue eliminado anteriormente.
                 */
                if ($keyboard_pwd_id <= 0) {
                    continue;
                }

                $ttlock_result = $this->ttlock_service->deleteKeyboardPwd(
                        $device_id,
                        $keyboard_pwd_id
                );

                if ($ttlock_result['success']) {

                    $access_pin_devices_model->updateDeletionResult(
                            $relation_id,
                            null,
                            null
                    );

                    $this->audit_service->update(
                            'wi_access_pin_devices',
                            $access_pin_id,
                            [
                                'ttlock_keyboard_pwd_id' => $keyboard_pwd_id
                            ],
                            [
                                'ttlock_keyboard_pwd_id' => null
                            ],
                            __METHOD__,
                            [],
                            'Eliminación de PIN de cerradura'
                    );
                } else {

                    $error_devices++;

                    $error_message = $ttlock_result['message'] ?? 'No se ha podido eliminar el PIN de la cerradura.';

                    /*
                     * Conservamos keyboard_pwd_id para poder
                     * volver a intentar la eliminación.
                     */
                    $access_pin_devices_model->updateDeletionResult(
                            $relation_id,
                            $keyboard_pwd_id,
                            $error_message
                    );

                    $this->audit_service->update(
                            'wi_access_pin_devices',
                            $access_pin_id,
                            [
                                'error_message' => $device['error_message'] ?? null
                            ],
                            [
                                'error_message' => $error_message
                            ],
                            __METHOD__,
                            [],
                            'Error al eliminar PIN de cerradura'
                    );
                }
            }

            /*
             * Si alguna cerradura mantiene el PIN,
             * NO eliminamos todavía los datos de BD.
             */
            if ($error_devices > 0) {

                $result['id'] = $access_pin_id;
                $result['message'] = 'No se ha podido eliminar el PIN de todas las cerraduras.';

                return $result;
            }

            /*
             * Llegados aquí, ninguna cerradura mantiene
             * un PIN TTLock asociado.
             *
             * Ahora podemos eliminar los registros locales.
             */
            $this->audit_service->delete(
                    'wi_access_pins',
                    $access_pin_id,
                    [
                        'access_pin_id' => $access_pin_id,
                        'reservation_id' => $reservation_id,
                        'facility_id' => $pin['facility_id'],
                        'pin_type' => $pin['pin_type'],
                        'access_type' => $pin['access_type'],
                        'valid_from' => $pin['valid_from'],
                        'valid_until' => $pin['valid_until']
                    ],
                    __METHOD__,
                    'Eliminación de PIN de reserva'
            );

            /*
             * Primero relaciones y después PIN principal
             * por la FK.
             */
            foreach ($devices as $device) {

                $access_pin_devices_model->deleteById(
                        (int) $device['id']
                );
            }

            $access_pins_model->deleteById($access_pin_id);

            $result['success'] = true;
            $result['message'] = 'El PIN de la reserva se ha eliminado correctamente.';
            $result['id'] = $access_pin_id;
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }
    
    public function revokeBonusPin(int $user_bonus_id): array {

        $result = [
            'success' => false,
            'partial' => false,
            'message' => '',
            'total_pins' => 0,
            'revoked_pins' => 0,
            'error_pins' => 0
        ];

        try {

            if ($user_bonus_id <= 0) {
                throw new InvalidArgumentException('El identificador del bono del usuario no es válido.');
            }

            $access_pins_model = new accessPinsModel();
            $access_pin_devices_model = new accessPinDevicesModel();

            $pins = $access_pins_model->findByUserBonusId(
                    $user_bonus_id,
                    false
            );

            if (empty($pins)) {

                $result['success'] = true;
                $result['message'] = 'El bono no tiene PIN de acceso asociado.';

                return $result;
            }

            $result['total_pins'] = count($pins);

            foreach ($pins as $pin) {

                /*
                 * Solo tratamos PIN propios de bono.
                 */
                if ((string) $pin['pin_type'] !== accessPinsModel::PIN_TYPE_BONUS) {
                    continue;
                }

                /*
                 * Si ya está revocado no volvemos a intentar eliminarlo.
                 */
                if (!empty($pin['revoked_at'])) {
                    $result['revoked_pins']++;
                    continue;
                }

                $pin_id = (int) $pin['id'];

                $devices = $access_pin_devices_model->findByAccessPinId(
                        $pin_id,
                        false
                );

                $pin_has_errors = false;

                foreach ($devices as $device) {

                    if (empty($device['ttlock_keyboard_pwd_id'])) {
                        continue;
                    }

                    $delete_result = $this->ttlock_service->deleteKeyboardPwd(
                            (int) $device['ttlock_device_id'],
                            (int) $device['ttlock_keyboard_pwd_id']
                    );

                    if (empty($delete_result['success'])) {
                        $pin_has_errors = true;
                    }
                }

                /*
                 * Si algún dispositivo sigue teniendo el PIN,
                 * no marcamos todavía este registro como revocado.
                 */
                if ($pin_has_errors) {
                    $result['error_pins']++;
                    continue;
                }

                /*
                 * Conservamos el PIN como histórico.
                 */
                if (!$access_pins_model->revokeById($pin_id)) {
                    $result['error_pins']++;
                    continue;
                }

                $this->audit_service->add(
                        $pin_id,
                        'access_pin',
                        'REVOKE_BONUS_PIN',
                        [
                            'user_bonus_id' => $user_bonus_id,
                            'facility_id' => $pin['facility_id'] ?? null
                        ],
                        $pin,
                        [
                            'pin_status_id' => 4,
                            'revoked_at' => date('Y-m-d H:i:s')
                        ]
                );

                $result['revoked_pins']++;
            }

            if ($result['error_pins'] === 0) {

                $result['success'] = true;
                $result['message'] = 'El acceso del bono se ha revocado correctamente.';
            } elseif ($result['revoked_pins'] > 0) {

                $result['success'] = false;
                $result['partial'] = true;
                $result['message'] = 'El acceso se ha revocado parcialmente. Quedan PIN pendientes de eliminar en alguna cerradura.';
            } else {

                $result['message'] = 'No se ha podido revocar el acceso del bono.';
            }
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }
    
    public function revokeExpiredBonusPins(): array {

        $result = [
            'success' => true,
            'message' => '',
            'total' => 0,
            'revoked' => 0,
            'errors' => 0,
            'error_details' => []
        ];

        try {

            $access_pins_model = new accessPinsModel();

            $expired_bonuses = $access_pins_model->findExpiredBonusUserIds(false);

            if (empty($expired_bonuses)) {
                $result['message'] = 'No hay PIN de bonos caducados pendientes de revocar.';
                return $result;
            }

            $result['total'] = count($expired_bonuses);

            foreach ($expired_bonuses as $expired_bonus) {

                $user_bonus_id = (int) $expired_bonus['user_bonus_id'];

                if ($user_bonus_id <= 0) {
                    continue;
                }

                $revoke_result = $this->revokeBonusPin($user_bonus_id);

                if ($revoke_result['success']) {

                    $result['revoked']++;
                } else {

                    $result['success'] = false;
                    $result['errors']++;

                    $result['error_details'][] = [
                        'user_bonus_id' => $user_bonus_id,
                        'message' => $revoke_result['message']
                    ];
                }
            }

            if ($result['errors'] > 0) {

                $result['message'] = 'Proceso finalizado con errores. '
                        . $result['revoked'] . ' bonos revocados y '
                        . $result['errors'] . ' con errores.';
            } else {

                $result['message'] = 'Se han revocado correctamente los PIN de '
                        . $result['revoked'] . ' bonos caducados.';
            }
        } catch (Throwable $e) {

            $result['success'] = false;
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Crea un PIN y lo sincroniza con todas las cerraduras
     * asociadas a la instalación.
     */
    private function createPin(
            int $facility_id,
            string $valid_from,
            string $valid_until,
            string $pin_type,
            string $access_type,
            ?string $description = null,
            ?int $reservation_id = null,
            ?int $user_bonus_id = null,
            ?string $pin = null
    ): array {

        $result = [
            'success' => false,
            'partial' => false,
            'message' => '',
            'id' => 0,
            'pin' => null,
            'success_devices' => 0,
            'error_devices' => 0
        ];

        try {

            if ($facility_id <= 0) {
                throw new InvalidArgumentException('El identificador de la instalación no es válido.');
            }

            $facilities_model = new facilitiesModel();
            $facility = $facilities_model->findById($facility_id);

            if ($facility === null) {
                throw new RuntimeException('La instalación no existe.');
            }

            /*
             * Obtenemos las cerraduras asociadas.
             */
            $facilities_devices_model = new facilitiesDevicesModel();
            $devices = $facilities_devices_model->findByFacilityId($facility_id);

            if (empty($devices)) {
                throw new RuntimeException('La instalación no tiene ninguna cerradura asociada.');
            }

            /*
             * IDs internos de las cerraduras.
             */
            $device_ids = array_map(function ($device) {
                return (int) $device['ttlock_device_id'];
            }, $devices);

            $pin_type = trim($pin_type);
            $access_type = strtoupper(trim($access_type));
            $description = $description !== null ? trim($description) : null;

            $allowed_access_types = [
                'TEMPORAL',
                'PERMANENT',
                'ONE_TIME'
            ];

            if (!in_array($access_type, $allowed_access_types, true)) {
                throw new InvalidArgumentException('El tipo de acceso del PIN no es válido.');
            }

            if ($pin_type === 'MANUAL' && empty($description)) {
                throw new InvalidArgumentException('La descripción del PIN es obligatoria.');
            }

            /*
             * Fechas.
             */

            /*
             * Fechas de validez.
             *
             * Conservamos horas y minutos.
             * Los segundos se normalizan a 00.
             */
            $start_timestamp = strtotime($valid_from);

            if ($start_timestamp === false) {
                throw new InvalidArgumentException('La fecha de inicio no es correcta.');
            }

            $valid_from = date('Y-m-d H:i:00', $start_timestamp);

            if ($access_type !== 'PERMANENT') {

                $end_timestamp = strtotime($valid_until);

                if ($end_timestamp === false) {
                    throw new InvalidArgumentException('La fecha final no es correcta.');
                }

                $valid_until = date('Y-m-d H:i:00', $end_timestamp);
            }

            if ($access_type === 'PERMANENT') {
                $valid_until = '2099-12-31 23:59:00';
            }

            if (strtotime($valid_until) <= strtotime($valid_from)) {
                throw new InvalidArgumentException('La fecha final debe ser posterior a la fecha inicial.');
            }

            /*
             * PIN automático o manual.
             */
            $repository = new accessPinsRepository();

            if ($pin === null || trim($pin) === '') {

                $pin = $this->generatePin($device_ids);
            } else {

                $pin = trim($pin);

                $this->validateManualPin($pin);

                if ($repository->existsActivePinForDevices($pin, $device_ids)) {
                    throw new RuntimeException('El PIN indicado ya está siendo utilizado en una de las cerraduras de la instalación.');
                }
            }

            /*
             * Ciframos el PIN.
             */
            $pin_encrypted = $this->encryption_service->encrypt($pin);

            /*
             * Hash para búsquedas.
             */
            $pin_hash = hash('sha256', $pin);

            /*
             * Creamos el registro principal.
             */
            $model = new accessPinsModel();

            $model->setReservationId($reservation_id);
            $model->setUserBonusId($user_bonus_id);
            $model->setFacilityId($facility_id);
            $model->setPinCodeEncrypted($pin_encrypted);
            $model->setPinCodeHash($pin_hash);
            $model->setPinType($pin_type);
            $model->setAccessType($access_type);
            $model->setDescription($description);
            $model->setValidFrom($valid_from);
            $model->setValidUntil($valid_until);
            $model->setPinStatusId(1);

            /*
             * PENDING.
             */

            $id = $model->add();

            if ($id <= 0) {
                throw new RuntimeException('No se ha podido guardar el PIN.');
            }

            /*
             * Auditoría del PIN.
             *
             * El target_id es el id del pin.
             */
            $this->audit_service->insert(
                    'wi_access_pins',
                    $id,
                    [
                        'access_pin_id' => $id,
                        'facility_id' => $facility_id,
                        'reservation_id' => $reservation_id,
                        'user_bonus_id' => $user_bonus_id,
                        'pin_type' => $pin_type,
                        'access_type' => $access_type,
                        'description' => $description,
                        'valid_from' => $valid_from,
                        'valid_until' => $valid_until,
                        'pin_status_id' => 1
                    ],
                    __METHOD__,
                    'Creación de PIN de acceso'
            );

            /*
             * Sincronizamos el mismo PIN en todas
             * las cerraduras de la instalación.
             */
            $success_devices = 0;
            $error_devices = 0;

            foreach ($devices as $device) {

                $device_id = (int) $device['ttlock_device_id'];

                $ttlock_result = $this->ttlock_service->createKeyboardPwd(
                        $device_id,
                        $pin,
                        'PD-' . $id,
                        $valid_from,
                        $valid_until,
                        $access_type
                );

                $pin_device_model = new accessPinDevicesModel();

                $pin_device_model->setAccessPinId($id);
                $pin_device_model->setTtlockDeviceId($device_id);

                if ($ttlock_result['success']) {

                    $keyboard_pwd_id = (int) $ttlock_result['keyboard_pwd_id'];

                    $pin_device_model->setTtlockKeyboardPwdId($keyboard_pwd_id);
                    $pin_device_model->setSyncedAt(date('Y-m-d H:i:s'));
                    $pin_device_model->setErrorMessage(null);

                    $success_devices++;
                } else {

                    $keyboard_pwd_id = null;
                    $error_message = $ttlock_result['message'] ?? 'No se ha podido crear el PIN en TTLock.';

                    $pin_device_model->setTtlockKeyboardPwdId(null);
                    $pin_device_model->setSyncedAt(null);
                    $pin_device_model->setErrorMessage($error_message);

                    $error_devices++;
                }

                /*
                 * Guardamos siempre la relación, incluso
                 * si TTLock devuelve error.
                 *
                 * Así sabemos qué cerradura queda pendiente
                 * o ha fallado.
                 */
                $pin_device_id = $pin_device_model->add();

                if ($pin_device_id <= 0) {
                    throw new RuntimeException('No se ha podido guardar la relación entre el PIN y una de las cerraduras.');
                }

                $this->audit_service->insert(
                        'wi_access_pin_devices',
                        $id,
                        [
                            'access_pin_device_id' => $pin_device_id,
                            'access_pin_id' => $id,
                            'facility_id' => $facility_id,
                            'ttlock_device_id' => $device_id,
                            'ttlock_keyboard_pwd_id' => $keyboard_pwd_id,
                            'synced' => $ttlock_result['success'] ? 1 : 0
                        ],
                        __METHOD__,
                        $ttlock_result['success'] ? 'Creación de PIN en cerradura' : 'Error al crear PIN en cerradura'
                );
            }

            /*
             * Estado global.
             */
            $model->setId($id);

            if ($error_devices === 0) {

                $model->updateStatusById($id,2);

                $result['success'] = true;
                $result['partial'] = false;
                $result['message'] = 'PIN creado y sincronizado correctamente.';
            } elseif ($success_devices > 0) {

                $model->updateStatusById($id,5);

                $result['success'] = true;
                $result['partial'] = true;
                $result['message'] = 'El PIN se ha creado, pero queda pendiente de sincronización en algunas cerraduras.';
            } else {

                $model->updateStatusById($id,5);

                $result['success'] = false;
                $result['partial'] = false;
                $result['message'] = 'No se ha podido sincronizar el PIN con ninguna cerradura.';
            }

            $result['id'] = $id;
            $result['pin'] = $pin;
            $result['success_devices'] = $success_devices;
            $result['error_devices'] = $error_devices;

            /*
             * Auditoría del cambio de estado.
             */
            $this->audit_service->update(
                    'wi_access_pins',
                    $id,
                    [
                        'access_pin_id' => $id,
                        'facility_id' => $facility_id,
                        'pin_status_id' => 1
                    ],
                    [
                        'access_pin_id' => $id,
                        'facility_id' => $facility_id,
                        'pin_status_id' => $error_devices === 0 ? 2 : 5
                    ],
                    __METHOD__,
                    [],
                    $error_devices === 0 ? 'PIN sincronizado correctamente' : 'PIN creado con errores de sincronización'
            );

            $result['id'] = $id;
            $result['pin'] = $pin;
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }
    
    public function retryPendingPins(): array {

        $result = [
            'success' => true,
            'message' => '',
            'total' => 0,
            'synced' => 0,
            'partial' => 0,
            'errors' => 0,
            'error_details' => []
        ];

        try {

            $access_pins_model = new accessPinsModel();

            $pins = $access_pins_model->findPendingSync(false);

            if (empty($pins)) {
                $result['message'] = 'No hay PIN pendientes de sincronización.';
                return $result;
            }

            $result['total'] = count($pins);

            foreach ($pins as $pin) {

                $pin_id = (int) $pin['id'];

                $retry_result = $this->retryPinSync($pin_id);

                if ($retry_result['success'] && empty($retry_result['partial'])) {

                    $result['synced']++;
                } elseif (!empty($retry_result['partial'])) {

                    $result['success'] = false;
                    $result['partial']++;

                    $result['error_details'][] = [
                        'pin_id' => $pin_id,
                        'message' => $retry_result['message']
                    ];
                } else {

                    $result['success'] = false;
                    $result['errors']++;

                    $result['error_details'][] = [
                        'pin_id' => $pin_id,
                        'message' => $retry_result['message']
                    ];
                }
            }

            if ($result['errors'] > 0 || $result['partial'] > 0) {

                $result['message'] = 'Proceso finalizado con incidencias. '
                        . $result['synced'] . ' PIN sincronizados, '
                        . $result['partial'] . ' parciales y '
                        . $result['errors'] . ' con errores.';
            } else {

                $result['message'] = 'Se han sincronizado correctamente '
                        . $result['synced'] . ' PIN pendientes.';
            }
        } catch (Throwable $e) {

            $result['success'] = false;
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    public function retryPinSync(int $access_pin_id): array {

        $result = [
            'success' => false,
            'message' => '',
            'synced' => 0,
            'errors' => 0
        ];

        try {

            if ($access_pin_id <= 0) {
                throw new InvalidArgumentException('El identificador del PIN no es válido.');
            }

            $access_pins_model = new accessPinsModel();
            $pin = $access_pins_model->findById($access_pin_id, false);

            if (empty($pin)) {
                throw new RuntimeException('El PIN no existe.');
            }

            /*
             * Si ya ha caducado no tiene sentido sincronizarlo.
             */
            if (strtotime($pin['valid_until']) <= time()) {
                throw new RuntimeException('El PIN ya ha caducado.');
            }

            $pin_code = $this->encryption_service->decrypt(
                    $pin['pin_code_encrypted']
            );

            $pin_devices_model = new accessPinDevicesModel();

            $devices = $pin_devices_model->findByAccessPinId(
                    $access_pin_id,
                    false
            );

            foreach ($devices as $device) {

                /*
                 * Ya está sincronizado.
                 */
                if (!empty($device['ttlock_keyboard_pwd_id'])) {
                    continue;
                }

                /*
                 * Si no existe error tampoco es una relación
                 * pendiente de reintento.
                 */
                if (empty($device['error_message'])) {
                    continue;
                }

                $device_id = (int) $device['ttlock_device_id'];
                $retry_count = (int) ($device['retry_count'] ?? 0);

                $ttlock_result = $this->ttlock_service->createKeyboardPwd(
                        $device_id,
                        $pin_code,
                        'PD-' . $access_pin_id,
                        $pin['valid_from'],
                        $pin['valid_until'],
                        $pin['access_type']
                );

                if ($ttlock_result['success']) {

                    $keyboard_pwd_id = (int) $ttlock_result['keyboard_pwd_id'];

                    $pin_devices_model->updateRetryResult(
                            (int) $device['id'],
                            true,
                            $keyboard_pwd_id,
                            null,
                            null
                    );

                    $result['synced']++;
                } else {

                    $error_message = $ttlock_result['message'] ?? 'No se ha podido sincronizar el PIN con TTLock.';

                    $next_retry_at = $this->calculateNextRetry(
                            $retry_count + 1
                    );

                    $pin_devices_model->updateRetryResult(
                            (int) $device['id'],
                            false,
                            null,
                            $error_message,
                            $next_retry_at
                    );

                    $result['errors']++;
                }
            }

            /*
             * Volvemos a comprobar todas las relaciones.
             */
            $devices = $pin_devices_model->findByAccessPinId(
                    $access_pin_id,
                    false
            );

            $pending = false;

            foreach ($devices as $device) {

                if (empty($device['ttlock_keyboard_pwd_id'])) {
                    $pending = true;
                    break;
                }
            }

            /*
             * Si ya están todas sincronizadas,
             * el PIN pasa a ACTIVE.
             */
            if (!$pending) {

                $access_pins_model->updateStatusById(
                        $access_pin_id,
                        2
                );

                $result['success'] = true;
                $result['message'] = 'El PIN se ha sincronizado correctamente con todas las cerraduras.';
            } else {

                $result['success'] = false;
                $result['message'] = 'El PIN todavía tiene cerraduras pendientes de sincronización.';
            }
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }
    
    public function revokeManualPin(int $access_pin_id): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if ($access_pin_id <= 0) {
                throw new InvalidArgumentException('El identificador del PIN no es válido.');
            }

            $access_pins_model = new accessPinsModel();

            $pin = $access_pins_model->findById($access_pin_id, false);

            if (empty($pin)) {
                throw new RuntimeException('El PIN no existe.');
            }

            /*
             * Los PIN de reserva se gestionan desde la propia reserva.
             */
            if ($pin['pin_type'] !== 'MANUAL') {
                throw new RuntimeException(
                                'Los PIN asociados a reservas deben gestionarse desde la propia reserva.'
                        );
            }

            /*
             * Si ya está revocado, no hacemos nada.
             */
            if (!empty($pin['revoked_at']) || (int) $pin['pin_status_id'] === 4) {
                throw new RuntimeException('El PIN ya está revocado.');
            }

            $access_pin_devices_model = new accessPinDevicesModel();

            $devices = $access_pin_devices_model->findByAccessPinId(
                    $access_pin_id,
                    false
            );

            $error_devices = 0;

            foreach ($devices as $device) {

                $relation_id = (int) $device['id'];
                $device_id = (int) $device['ttlock_device_id'];
                $keyboard_pwd_id = !empty($device['ttlock_keyboard_pwd_id']) ? (int) $device['ttlock_keyboard_pwd_id'] : 0;

                /*
                 * Si nunca llegó a sincronizarse con TTLock,
                 * no hay nada que eliminar.
                 */
                if ($keyboard_pwd_id <= 0) {
                    continue;
                }

                $ttlock_result = $this->ttlock_service->deleteKeyboardPwd(
                        $device_id,
                        $keyboard_pwd_id
                );

                if ($ttlock_result['success']) {

                    /*
                     * El PIN ya no existe en TTLock.
                     * Conservamos la relación como histórico,
                     * pero eliminamos el keyboardPwdId.
                     */
                    $access_pin_devices_model->updateDeletionResult(
                            $relation_id,
                            null,
                            null
                    );

                    $this->audit_service->update(
                            'wi_access_pin_devices',
                            $access_pin_id,
                            [
                                'ttlock_keyboard_pwd_id' => $keyboard_pwd_id,
                                'error_message' => $device['error_message'] ?? null
                            ],
                            [
                                'ttlock_keyboard_pwd_id' => null,
                                'error_message' => null
                            ],
                            __METHOD__,
                            [],
                            'Revocación de PIN manual en cerradura'
                    );
                } else {

                    $error_devices++;

                    $error_message = $ttlock_result['message'] ?? 'No se ha podido eliminar el PIN de la cerradura.';

                    /*
                     * Conservamos keyboardPwdId para poder saber
                     * que el PIN continúa existiendo en TTLock.
                     */
                    $access_pin_devices_model->updateDeletionResult(
                            $relation_id,
                            $keyboard_pwd_id,
                            $error_message
                    );

                    $this->audit_service->update(
                            'wi_access_pin_devices',
                            $access_pin_id,
                            [
                                'error_message' => $device['error_message'] ?? null
                            ],
                            [
                                'error_message' => $error_message
                            ],
                            __METHOD__,
                            [],
                            'Error al revocar PIN manual en cerradura'
                    );
                }
            }

            /*
             * Si alguna cerradura mantiene el PIN,
             * todavía no lo marcamos como revocado.
             */
            if ($error_devices > 0) {
                $result['message'] = 'No se ha podido revocar el PIN en todas las cerraduras.';
                return $result;
            }

            /*
             * Todas las cerraduras lo han eliminado correctamente.
             *
             * REVOKED = 4.
             */
            $revoked_at = date('Y-m-d H:i:s');

            if (!$access_pins_model->revokeById($access_pin_id, 4)) {
                throw new RuntimeException(
                                'El PIN se ha eliminado de TTLock, pero no se ha podido marcar como revocado en la base de datos.'
                        );
            }

            $this->audit_service->update(
                    'wi_access_pins',
                    $access_pin_id,
                    [
                        'pin_status_id' => (int) $pin['pin_status_id'],
                        'revoked_at' => $pin['revoked_at'] ?? null
                    ],
                    [
                        'pin_status_id' => 4,
                        'revoked_at' => $revoked_at
                    ],
                    __METHOD__,
                    [],
                    'Revocación de PIN manual'
            );

            $result['success'] = true;
            $result['message'] = 'El PIN se ha revocado correctamente.';
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Genera un PIN que no esté siendo utilizado
     * actualmente en ninguna de las cerraduras indicadas.
     */
    private function generatePin(array $device_ids): string {

        $settings_model = new settingsModel();
        $repository = new accessPinsRepository();

        $min_length = (int) ($settings_model->findValueByKey('pin_min_length') ?? 6);
        $max_length = (int) ($settings_model->findValueByKey('pin_max_length') ?? 6);
        
        if ($min_length < 1 || $max_length < $min_length) {
            throw new RuntimeException('La configuración de longitud de PIN no es válida.');
        }

        /*
         * Para la generación automática usamos
         * la longitud máxima configurada.
         */
        $length = $max_length;

        $min = (int) ('1' . str_repeat('0', $length - 1));
        $max = (int) str_repeat('9', $length);

        do {
            $pin = (string) random_int($min, $max);
        } while ($repository->existsActivePinForDevices($pin, $device_ids));

        return $pin;
    }

    /**
     * Valida un PIN introducido manualmente.
     */
    private function validateManualPin(string $pin): void {

        if (!ctype_digit($pin)) {
            throw new InvalidArgumentException('El PIN debe contener únicamente números.');
        }

        $settings_model = new settingsModel();

        $min_length = (int) ($settings_model->findValueByKey('pin_min_length') ?? 6);
        $max_length = (int) ($settings_model->findValueByKey('pin_max_length') ?? 6);
        
        if ($min_length < 1 || $max_length < $min_length) {
            throw new RuntimeException('La configuración de longitud de PIN no es válida.');
        }

        $length = strlen($pin);

        if ($length < $min_length || $length > $max_length) {
            throw new InvalidArgumentException('El PIN debe tener entre ' . $min_length . ' y ' . $max_length . ' dígitos.');
        }
    }
    
    private function calculateNextRetry(int $retry_count): string {

        $minutes = match (true) {
            $retry_count <= 1 => 5,
            $retry_count === 2 => 10,
            $retry_count === 3 => 30,
            $retry_count === 4 => 60,
            default => 120
        };

        return date(
                'Y-m-d H:i:s',
                strtotime('+' . $minutes . ' minutes')
        );
    }
}
