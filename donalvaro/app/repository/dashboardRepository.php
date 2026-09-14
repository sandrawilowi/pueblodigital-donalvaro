<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 16 ago 2026
 */

/**
 * Consultas necesarias para el dashboard.
 */
class dashboardRepository extends baseModel
{
    public function countPendingReservations(): int {

        $sql = '
            SELECT COUNT(*) AS total
            FROM wi_reservations r
            INNER JOIN wi_reservation_statuses rs ON rs.id = r.reservation_status_id
            WHERE rs.code = ?
              AND rs.status = 1';

        $result = $this->db()->selectOne($sql, ['PENDING'], true);

        return (int) ($result->total ?? 0);
    }

    public function countTodayReservations(): int {

        $sql = '
            SELECT COUNT(*) AS total
            FROM wi_reservations r
            INNER JOIN wi_reservation_statuses rs ON rs.id = r.reservation_status_id
            WHERE DATE(r.start_at) = CURDATE()
              AND rs.code <> ?
              AND rs.status = 1';

        $result = $this->db()->selectOne($sql, ['CANCELLED'], true);

        return (int) ($result->total ?? 0);
    }

    public function countActiveFacilities(): int {

        $sql = '
            SELECT COUNT(*) AS total
            FROM wi_facilities
            WHERE status = ?
              AND deleted_at IS NULL';

        $result = $this->db()->selectOne($sql, [facilitiesModel::STATUS_ACTIVE], true);

        return (int) ($result->total ?? 0);
    }

    public function countTodayAccesses(): int {

        $sql = '
            SELECT COUNT(*) AS total
            FROM wi_ttlock_access_logs
            WHERE DATE(event_at) = CURDATE()
              AND access_granted = 1';

        $result = $this->db()->selectOne($sql, [], true);

        return (int) ($result->total ?? 0);
    }

    public function findPendingReservations(int $limit = 10): array {

        $limit = max(1, min($limit, 50));

        $sql = '
            SELECT
                r.id,
                r.reference,
                r.start_at,
                r.end_at,
                r.people_count,
                r.total_amount,
                r.user_id,
                r.facility_id,
                f.name AS facility_name,
                rs.code AS reservation_status_code,
                rs.name AS reservation_status_name,
                CONCAT_WS(" ", u.first_name, u.last_name) AS user_name
            FROM wi_reservations r
            INNER JOIN wi_reservation_statuses rs ON rs.id = r.reservation_status_id
            INNER JOIN wi_facilities f ON f.id = r.facility_id
            INNER JOIN wi_users u ON u.id = r.user_id
            WHERE rs.code = ?
              AND rs.status = 1
            ORDER BY r.created_at ASC
            LIMIT ' . $limit;

        return $this->db()->select($sql, ['PENDING'], true);
    }

    public function findLatestAccesses(int $limit = 10): array {

        $limit = max(1, min($limit, 50));

        $sql = '
        SELECT
            al.id,
            al.access_pin_id,
            al.event_type,
            al.access_granted,
            al.failure_reason,
            al.event_at,
            al.received_at,

            d.lock_alias,

            f.name AS facility_name,

            ap.pin_type,
            ap.description AS pin_description,

            r.reference AS reservation_reference,

            CONCAT_WS(" ", u.first_name, u.last_name) AS user_name

        FROM wi_ttlock_access_logs al

        LEFT JOIN wi_ttlock_devices d
            ON d.id = al.ttlock_device_id

        LEFT JOIN wi_facilities f
            ON f.id = al.facility_id

        LEFT JOIN wi_access_pins ap
            ON ap.id = al.access_pin_id

        LEFT JOIN wi_reservations r
            ON r.id = ap.reservation_id

        LEFT JOIN wi_users u
            ON u.id = r.user_id

        ORDER BY al.event_at DESC
        LIMIT ' . $limit;

        return $this->db()->select($sql, [], true);
    }
}
