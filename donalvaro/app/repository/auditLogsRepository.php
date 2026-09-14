<?php

declare(strict_types=1);

/**
 * auditLogsRepository
 *
 * Repositorio encargado de preparar los registros
 * de auditoría para su visualización.
 *
 * @author Sandra Campos
 * @copyright Wilowi
 * @version 1.0.0
 * @since 9 ago 2026
 */
class auditLogsRepository
{
    private auditLogsModel $audit_model;


    public function __construct()
    {
        $this->audit_model = new auditLogsModel();
    }


    /**
     * Obtiene la auditoría de una cerradura.
     *
     * @param int $device_id
     *
     * @return array
     */
    public function getDeviceAudit(int $device_id): array
    {
        if ($device_id <= 0) {
            return [];
        }

        $logs = $this->audit_model->findByTarget(
            $device_id,
            [
                'wi_ttlock_devices'
            ],
            true
        );

        return $this->prepareLogs($logs);
    }

    /**
     * Obtiene la auditoría de un gateway.
     *
     * @param int $gateway_id
     *
     * @return array
     */
    public function getGatewayAudit(int $gateway_id): array
    {
        if ($gateway_id <= 0) {
            return [];
        }

        $logs = $this->audit_model->findByTarget(
            $gateway_id,
            [
                'wi_ttlock_gateways'
            ],
            true
        );

        return $this->prepareLogs($logs);
    }

    /**
     * Obtiene la auditoría de una instalación.
     *
     * @param int $facility_id
     *
     * @return array
     */
    public function getFacilityAudit(int $facility_id): array
    {
        if ($facility_id <= 0) {
            return [];
        }

        $logs = $this->audit_model->findByTarget(
            $facility_id,
            [
                'wi_facilities',
                'wi_facilities_devices',
                'wi_facilities_images',
                'wi_facilities_payment_methods',
                'wi_facilities_prices',
                'wi_facilities_services',
                'wi_facilities_availability'
            ],
            true
        );

        return $this->prepareLogs($logs);
    }
    
    /**
     * Obtiene la auditoría de un código pin.
     *
     * @param int $pin_id
     *
     * @return array
     */
    public function getPinAudit(int $pin_id): array
    {
        if ($pin_id <= 0) {
            return [];
        }

        $logs = $this->audit_model->findByTarget(
            $pin_id,
            [
                'wi_access_pins',
                'wi_access_pin_devices'
            ],
            true
        );

        return $this->prepareLogs($logs);
    }

    /**
     * Obtiene la auditoría de un usuario.
     *
     * @param int $user_id
     *
     * @return array
     */
    public function getUserAudit(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        $logs = $this->audit_model->findByTarget(
            $user_id,
            [
                'wi_users',
                'wi_users_profile',
                'wi_users_bonuses',
                'wi_users_tokens',
                'wi_users_roles'
            ],
            true
        );

        return $this->prepareLogs($logs);
    }
    
    
    public function getReservationAudit(int $reservation_id): array {
        if ($reservation_id <= 0) {
            return [];
        }

        $logs = $this->audit_model->findByTarget(
                $reservation_id,
                [
                    'wi_reservations',
                    'wi_reservations_guests'
                ],
                true
        );

        return $this->prepareLogs($logs);
    }

    /**
     * Obtiene toda la auditoría de cerraduras.
     */
    public function getDevicesAudit(): array {

        $logs = $this->audit_model->findByTables(
                [
                    'wi_ttlock_devices'
                ],
                true
        );

        $logs = $this->prepareLogs($logs);

        $model = new ttlockDevicesModel();

        foreach ($logs as $log) {

            $device = $model->findById((int) $log->target_id, true);

            $log->target_name = !empty($device) ? $device->lock_alias : 'Cerradura #' . $log->target_id;

            $log->target_url = 'editcerradura/' . $log->target_id;
        }

        return $logs;
    }

    /**
     * Obtiene toda la auditoría de gateways.
     */
    public function getGatewaysAudit(): array {

        $logs = $this->audit_model->findByTables(
                [
                    'wi_ttlock_gateways'
                ],
                true
        );

        $logs = $this->prepareLogs($logs);

        $model = new ttlockGatewaysModel();

        foreach ($logs as $log) {

            $gateway = $model->findById((int) $log->target_id, true);

            $log->target_name = !empty($gateway) ? $gateway->gateway_name : 'Gateway #' . $log->target_id;

            $log->target_url = 'editgateway/' . $log->target_id;
        }

        return $logs;
    }

    /**
     * Obtiene toda la auditoría de instalaciones.
     */
    public function getFacilitiesAudit(): array {

        $logs = $this->audit_model->findByTables(
                [
                    'wi_facilities',
                    'wi_facilities_devices',
                    'wi_facilities_images',
                    'wi_facilities_payment_methods',
                    'wi_facilities_prices',
                    'wi_facilities_services',
                    'wi_facilities_availability'
                ],
                true
        );

        $logs = $this->prepareLogs($logs);

        $model = new facilitiesModel();

        foreach ($logs as $log) {

            $facility = $model->findById((int) $log->target_id, true);

            $log->target_name = !empty($facility) ? $facility->name : 'Instalación #' . $log->target_id;

            $log->target_url = 'editinstalacion/' . $log->target_id;
        }

        return $logs;
    }

    /**
     * Obtiene toda la auditoría de códigos PIN.
     */
    public function getPinsAudit(): array {

        $logs = $this->audit_model->findByTables(
                [
                    'wi_access_pins',
                    'wi_access_pin_devices'
                ],
                true
        );

        $logs = $this->prepareLogs($logs);

        $repository = new accessPinsRepository();

        foreach ($logs as $log) {

            $pin = $repository->getPinEditData((int) $log->target_id);

            $log->target_name = !empty($pin) ? 'PIN ' . $pin->pin : 'PIN #' . $log->target_id;

            $log->target_url = 'editpin/' . $log->target_id;
        }

        return $logs;
    }

    /**
     * Obtiene toda la auditoría de reservas.
     */
    public function getReservationsAudit(): array {

        $logs = $this->audit_model->findByTables(
                [
                    'wi_reservations',
                    'wi_reservations_guests'
                ],
                true
        );

        $logs = $this->prepareLogs($logs);

        $model = new reservationsModel();

        foreach ($logs as $log) {

            $reservation = $model->findById((int) $log->target_id, true);

            $log->target_name = !empty($reservation) ? $reservation->reference : 'Reserva #' . $log->target_id;

            $log->target_url = 'editreserva/' . $log->target_id;
        }

        return $logs;
    }

    /**
     * Obtiene toda la auditoría correspondiente a clientes.
     */
    public function getClientsAudit(): array {

        $logs = $this->audit_model->findUsersAuditByRole(3, true);

        $logs = $this->prepareLogs($logs);

        $model = new usersModel();

        foreach ($logs as $log) {

            $user = $model->findEditData((int) $log->target_id, true);

            $log->target_name = !empty($user) ? trim($user->first_name . ' ' . $user->last_name) : 'Cliente #' . $log->target_id;

            $log->target_url = 'editcliente/' . $log->target_id;
        }

        return $logs;
    }

    /**
     * Obtiene toda la auditoría correspondiente a usuarios
     * del sistema, excluyendo clientes.
     */
    public function getUsersAudit(): array {

        $logs = $this->audit_model->findUsersAuditByRole(2, true);

        $logs = $this->prepareLogs($logs);

        $model = new usersModel();

        foreach ($logs as $log) {

            $user = $model->findEditData((int) $log->target_id, true);

            $log->target_name = !empty($user) ? trim($user->first_name . ' ' . $user->last_name) : 'Usuario #' . $log->target_id;

            $log->target_url = 'editusuario/' . $log->target_id;
        }

        return $logs;
    }
    
    public function getBonusAudit(int $bonus_id): array
    {
        if ($bonus_id <= 0) {
            return [];
        }

        $logs = $this->audit_model->findByTarget(
            $bonus_id,
            [
                'wi_bonuses',
                'wi_bonuses_facilities',
                'wi_bonuses_payment_methods',
                'wi_bonus_movements'
            ],
            true
        );

        return $this->prepareLogs($logs);
    }
    
    public function getBonusesAudit(): array {

        $logs = $this->audit_model->findByTables(
                [
                    'wi_bonuses',
                    'wi_bonuses_facilities',
                    'wi_bonuses_payment_methods',
                    'wi_bonus_movements'
                ],
                true
        );

        $logs = $this->prepareLogs($logs);

        $model = new bonusesModel();

        foreach ($logs as $log) {

            $bonus = $model->findById((int) $log->target_id, true);

            $log->target_name = !empty($bonus) ? $bonus->name : 'Bonus #' . $log->target_id;

            $log->target_url = 'editbono/' . $log->target_id;
        }

        return $logs;
    }

    /**
     * Prepara los registros de auditoría
     * para mostrarlos en la vista.
     *
     * @param array $logs
     *
     * @return array
     */
    private function prepareLogs(array $logs): array
    {
        foreach ($logs as $log) {

            /*
             * Si no existe usuario asociado significa que
             * la acción ha sido realizada automáticamente.
             */
            if (
                empty($log->user_id)
                || empty($log->first_name)
            ) {
                $log->first_name = 'Sistema';
                $log->last_name = '';
            }

            /*
             * Convertimos los JSON almacenados en la BD
             * a arrays para utilizarlos posteriormente
             * en los modales.
             */
            $log->old_values = $this->decodeValues(
                $log->old_values ?? null
            );

            $log->new_values = $this->decodeValues(
                $log->new_values ?? null
            );
            
            // Preparar url
            
            if (!empty($log->url)) {
                $path = parse_url($log->url, PHP_URL_PATH);
                $parts = array_values(array_filter(explode('/', $path)));

                $count = count($parts);

                if ($count >= 2) {
                    $log->url_name = $parts[$count - 2] . '/' . $parts[$count - 1];
                } elseif ($count === 1) {
                    $log->url_name = $parts[0];
                } else {
                    $log->url_name = '';
                }
            }
        }

        return $logs;
    }

    /**
     * Convierte un JSON almacenado en auditoría
     * a un array PHP.
     *
     * @param string|null $values
     *
     * @return array
     */
    private function decodeValues(?string $values): array
    {
        if (empty($values)) {
            return [];
        }

        $decoded = json_decode(
            $values,
            true
        );

        return is_array($decoded)
            ? $decoded
            : [];
    }
}