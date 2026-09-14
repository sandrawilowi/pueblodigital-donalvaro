<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 25 ago 2026
 */
class reservationsRepository {

    private reservationsModel $model;

    public function __construct() {
        $this->model = new reservationsModel();
    }

    public function searchReservations(array $filters = [], bool $asObject = false, ?int $limit = null): array {

        $where = [];
        $params = [];

        /*
         * Referencia.
         */
        if (!empty($filters['reference'])) {
            $where[] = 'r.reference LIKE ?';
            $params[] = '%' . $filters['reference'] . '%';
        }

        /*
         * Cliente.
         */
        if (!empty($filters['user_id'])) {
            $where[] = 'r.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        /*
         * Instalación.
         */
        if (!empty($filters['facility_id'])) {
            $where[] = 'r.facility_id = ?';
            $params[] = (int) $filters['facility_id'];
        }

        /*
         * Estado.
         */
        if (!empty($filters['status_id'])) {
            if (is_array($filters['status_id'])) {

                $status_ids = array_values(array_filter(array_map('intval', $filters['status_id']), fn($id) => $id > 0));

                if (!empty($status_ids)) {
                    $placeholders = implode(',', array_fill(0, count($status_ids), '?'));
                    $where[] = 'r.reservation_status_id IN (' . $placeholders . ')';

                    foreach ($status_ids as $status_id) {
                        $params[] = $status_id;
                    }
                }
            } else {

                $where[] = 'r.reservation_status_id = ?';
                $params[] = (int) $filters['status_id'];
            }
        }

        /*
         * Fecha desde.
         *
         * Buscamos reservas cuya entrada sea igual
         * o posterior a esta fecha.
         */
        if (!empty($filters['date_from'])) {
            $where[] = 'r.start_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        /*
         * Fecha hasta.
         *
         * Incluimos el día completo.
         */
        if (!empty($filters['date_until'])) {
            $where[] = 'r.start_at < DATE_ADD(?, INTERVAL 1 DAY)';
            $params[] = $filters['date_until'];
        }

        /*
         * Forma de pago.
         *
         * BONUS es un valor especial del select.
         */
        if (!empty($filters['payment_method'])) {

            if ($filters['payment_method'] === 'BONUS') {

                $where[] = 'r.user_bonus_id IS NOT NULL';

            } else {

                $where[] = 'r.payment_method_id = ?';
                $params[] = (int) $filters['payment_method'];
            }
        }

        $where_sql = '';

        if (!empty($where)) {
            $where_sql = ' WHERE ' . implode(' AND ', $where);
        }

        return $this->model->findByFilters(
                        $where_sql,
                $params,
                $asObject,
                $limit
        );
    }
    
    public function getReservationEditData(int $reservation_id): ?object {

        if ($reservation_id <= 0) {
            return null;
        }

        $reservation = $this->model->findEditDataById(
                $reservation_id,
                true
        );

        if (empty($reservation)) {
            return null;
        }

        $reservation_guests_model = new reservationsGuestsModel();

        $reservation->guests = $reservation_guests_model->findByReservationId(
                $reservation_id,
                true
        );

        return $reservation;
    }
    
    public function findCalendarReservations(string $start, string $end, int $facility_id = 0): array {

        if ($start === '' || $end === '') {
            return [];
        }

        $model = new reservationsModel();

        return $model->findCalendarReservations(
                        $start,
                        $end,
                        $facility_id,
                        true
                );
    }
}
