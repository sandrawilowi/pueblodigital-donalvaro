<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 29 ago 2026
 */

class reportsRepository {

    private reservationsModel $reservations_model;
    private ttlockAccessLogsModel $access_logs_model;
    private usersModel $users_model;
    private usersBonusesModel $users_bonuses_model;

    public function __construct() {
        $this->reservations_model = new reservationsModel();
        $this->access_logs_model = new ttlockAccessLogsModel();
        $this->users_model = new usersModel();
        $this->users_bonuses_model = new usersBonusesModel();
    }

    public function getReservationsReport(array $filters = []): array {

        $conditions = [];
        $params = [];

        $date_from = trim((string) ($filters['date_from'] ?? ''));
        $date_until = trim((string) ($filters['date_until'] ?? ''));
        $facility_id = (int) ($filters['facility_id'] ?? 0);
        $status_id = (int) ($filters['status_id'] ?? 0);

        if ($date_from !== '') {
            $conditions[] = 'r.start_at >= ?';
            $params[] = $date_from . ' 00:00:00';
        }

        if ($date_until !== '') {
            $conditions[] = 'r.start_at <= ?';
            $params[] = $date_until . ' 23:59:59';
        }

        if ($facility_id > 0) {
            $conditions[] = 'r.facility_id = ?';
            $params[] = $facility_id;
        }

        if ($status_id > 0) {
            $conditions[] = 'r.reservation_status_id = ?';
            $params[] = $status_id;
        }

        $where = '';

        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        return $this->reservations_model->findReportData($where, $params, true);
    }

    public function getIncomeReport(array $filters = []): array {

        $conditions = [
            'rs.code IN ("confirmed", "completed")'
        ];

        $params = [];

        $date_from = trim((string) ($filters['date_from'] ?? ''));
        $date_until = trim((string) ($filters['date_until'] ?? ''));
        $facility_id = (int) ($filters['facility_id'] ?? 0);

        if ($date_from !== '') {
            $conditions[] = 'r.start_at >= ?';
            $params[] = $date_from . ' 00:00:00';
        }

        if ($date_until !== '') {
            $conditions[] = 'r.start_at <= ?';
            $params[] = $date_until . ' 23:59:59';
        }

        if ($facility_id > 0) {
            $conditions[] = 'r.facility_id = ?';
            $params[] = $facility_id;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        return $this->reservations_model->findIncomeReportData(
                        $where,
                        $params,
                        true
                );
    }
    
    public function getAccessesReport(array $filters = []): array {

        $conditions = [];
        $params = [];

        $date_from = trim((string) ($filters['date_from'] ?? ''));
        $date_until = trim((string) ($filters['date_until'] ?? ''));
        $facility_id = (int) ($filters['facility_id'] ?? 0);

        if ($date_from !== '') {
            $conditions[] = 'al.event_at >= ?';
            $params[] = $date_from . ' 00:00:00';
        }

        if ($date_until !== '') {
            $conditions[] = 'al.event_at <= ?';
            $params[] = $date_until . ' 23:59:59';
        }

        if ($facility_id > 0) {
            $conditions[] = 'fd.facility_id = ?';
            $params[] = $facility_id;
        }

        $where = '';

        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        $results = $this->access_logs_model->findReportData($where, $params, true);

        /*
         * Si el evento no está relacionado con un PIN de nuestra BD,
         * intentamos recuperar el PIN introducido desde el payload.
         */
        $encryption_service = new pinEncryptionService();
        foreach ($results as $result) {

            $result->pin = null;

            if (!empty($result->pin_code_encrypted)) {

                $result->pin = $encryption_service->decrypt(
                        $result->pin_code_encrypted
                );
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
        }

        return $results;
    }

    public function getClientsReport(array $filters = []): array {

        $conditions = [];
        $params = [];

        $date_from = trim((string) ($filters['date_from'] ?? ''));
        $date_until = trim((string) ($filters['date_until'] ?? ''));
        $facility_id = (int) ($filters['facility_id'] ?? 0);

        if ($date_from !== '') {
            $conditions[] = 'r.start_at >= ?';
            $params[] = $date_from . ' 00:00:00';
        }

        if ($date_until !== '') {
            $conditions[] = 'r.start_at <= ?';
            $params[] = $date_until . ' 23:59:59';
        }

        if ($facility_id > 0) {
            $conditions[] = 'r.facility_id = ?';
            $params[] = $facility_id;
        }

        $where = '';

        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        return $this->users_model->findClientsReportData($where, $params, true);
    }

    public function getOriginReport(array $filters = []): array {

        $conditions = [];
        $params = [];

        $date_from = trim((string) ($filters['date_from'] ?? ''));
        $date_until = trim((string) ($filters['date_until'] ?? ''));
        $facility_id = (int) ($filters['facility_id'] ?? 0);

        if ($date_from !== '') {
            $conditions[] = 'r.start_at >= ?';
            $params[] = $date_from . ' 00:00:00';
        }

        if ($date_until !== '') {
            $conditions[] = 'r.start_at <= ?';
            $params[] = $date_until . ' 23:59:59';
        }

        if ($facility_id > 0) {
            $conditions[] = 'r.facility_id = ?';
            $params[] = $facility_id;
        }

        $where = '';

        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        return $this->users_model->findOriginReportData($where, $params, true);
    }

    public function getBonusesReport(array $filters = []): array {

        $conditions = [];
        $params = [];

        $date_from = trim((string) ($filters['date_from'] ?? ''));
        $date_until = trim((string) ($filters['date_until'] ?? ''));
        $facility_id = (int) ($filters['facility_id'] ?? 0);

        /*
         * El periodo del informe se basa en la fecha de adquisición
         * o asignación del bono al usuario.
         */
        if ($date_from !== '') {
            $conditions[] = 'ub.purchased_at >= ?';
            $params[] = $date_from . ' 00:00:00';
        }

        if ($date_until !== '') {
            $conditions[] = 'ub.purchased_at <= ?';
            $params[] = $date_until . ' 23:59:59';
        }

        /*
         * El bono puede estar asociado a varias instalaciones.
         *
         * Utilizamos EXISTS para filtrar por una instalación concreta
         * sin perder el resto de instalaciones del GROUP_CONCAT.
         */
        if ($facility_id > 0) {

            $conditions[] = '
            EXISTS (
                SELECT 1
                FROM wi_bonuses_facilities bff
                WHERE bff.bonus_id = b.id
                  AND bff.facility_id = ?
            )';

            $params[] = $facility_id;
        }

        $where = '';

        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        return $this->users_bonuses_model->findReportData(
                        $where,
                        $params,
                        true
                );
    }
}
