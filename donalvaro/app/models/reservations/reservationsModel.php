<?php

declare(strict_types=1);

class reservationsModel extends baseModel
{
    protected const TABLE = 'wi_reservations';
    protected const PRIMARY_KEY = 'id';
    public const STATUS_PENDING = 1;
    public const STATUS_CONFIRMED = 2;
    public const STATUS_CANCELLED = 3;
    public const STATUS_COMPLETED = 4;
    public const STATUS_REJECTED = 5;
    public const STATUS_NO_SHOW = 6;
    public const CODE_PENDING = 'pending';
    public const CODE_CONFIRMED = 'confirmed';
    public const CODE_CANCELLED = 'cancelled';
    public const CODE_COMPLETED = 'completed';
    public const CODE_REJECTED = 'rejected';
    public const CODE_NO_SHOW = 'no_show';

    private int $id = 0;
    private string $reference = '';
    private int $user_id = 0;
    private int $facility_id = 0;
    private ?int $facility_price_id = null;
    private ?int $user_bonus_id = null;
    private ?int $payment_method_id = null;
    private ?string $payment_reference = null;
    private string $start_at = '';
    private string $end_at = '';
    private int $people_count = 1;
    private int $reserved_units = 1;
    private float $unit_price = 0.00;
    private float $billable_units = 1.00;
    private float $total_amount = 0.00;
    private int $reservation_status_id = 1;
    private ?string $notes = null;
    private ?string $cancellation_reason = null;
    private ?string $cancelled_at = null;
    private ?int $created_by_user_id = null;
    private string $created_at = '';
    private string $updated_at = '';

    public function findAll(bool $asObject = false): array {
        
        $sql ='SELECT r.*, u.first_name,u.last_name,rs.name as status_name,
            f.name as facility_name, pm.name as payment_name,rs.code AS status_code,
            pm.name AS payment_name, fb.name AS bonus_name
           FROM ' . self::TABLE . ' as r'
                . ' INNER JOIN wi_reservation_statuses rs ON(r.reservation_status_id=rs.id)'
                . ' LEFT JOIN wi_facilities f ON(r.facility_id=f.id)'
                . ' LEFT JOIN wi_users u ON(r.user_id=u.id)'
                . ' LEFT JOIN wi_payment_methods pm ON(r.payment_method_id=pm.id)'
                . 'LEFT JOIN wi_users_bonuses ub ON ub.id = r.user_bonus_id 
                    LEFT JOIN wi_bonuses fb ON fb.id = ub.bonus_id'
                . ' ORDER BY r.created_at DESC';
        
        return $this->db()->select($sql,[],$asObject);
    }
    
    public function findByFacilityAndPeriod(
            int $facility_id,
            string $start_at,
            string $end_at,
            bool $asObject = false
    ): array {

        $sql = 'SELECT
                r.*,
                u.first_name,
                u.last_name,
                rs.code AS reservation_status_code,
                rs.name AS reservation_status_name
            FROM ' . self::TABLE . ' r
            INNER JOIN wi_users u
                ON u.id = r.user_id
            INNER JOIN wi_reservation_statuses rs
                ON rs.id = r.reservation_status_id
            WHERE r.facility_id = ?
            AND rs.code IN (?, ?, ?, ?)
            AND r.start_at < ?
            AND r.end_at > ?
            ORDER BY r.start_at ASC';

        return $this->db()->select(
                        $sql,
                        [
                            $facility_id,
                            'pending',
                            'confirmed',
                            'completed',
                            'no_show',
                            $end_at,
                            $start_at
                        ],
                        $asObject
                );
    }
    
    public function findByReference(string $reference, bool $asObject = false): array|object|null {

        $sql = 'SELECT *
            FROM ' . self::TABLE . '
            WHERE reference = ?
            LIMIT 1';

        return $this->db()->selectOne(
                        $sql,
                        [$reference],
                        $asObject
                );
    }

    public function getReservedUnitsByPeriod(
            int $facility_id,
            string $start_at,
            string $end_at
    ): int {

        $sql = 'SELECT COALESCE(SUM(r.reserved_units), 0) AS reserved_units
            FROM ' . self::TABLE . ' r
            INNER JOIN wi_reservation_statuses rs
                ON rs.id = r.reservation_status_id
            WHERE r.facility_id = ?
            AND rs.code IN (?, ?, ?, ?)
            AND r.start_at < ?
            AND r.end_at > ?';

        $result = $this->db()->selectOne(
                $sql,
                [
                    $facility_id,
                    'pending',
                    'confirmed',
                    'completed',
                    'no_show',
                    $end_at,
                    $start_at
                ],
                false
        );

        return (int) ($result['reserved_units'] ?? 0);
    }

    /**
     * Obtiene las reservas de un usuario.
     *
     * @param int $user_id
     * @param bool $asObject
     * @return array
     */
    public function findByUserId(int $user_id, bool $asObject = false): array {

        $sql = "SELECT
                r.*,
                f.name AS facility_name,
                rs.code AS reservation_status_code,
                rs.name AS reservation_status_name
            FROM wi_reservations r
            INNER JOIN wi_facilities f ON f.id = r.facility_id
            INNER JOIN wi_reservation_statuses rs ON rs.id = r.reservation_status_id
            WHERE r.user_id = ?
            ORDER BY r.start_at DESC";

        return $this->db()->select($sql, [$user_id], $asObject);
    }
    
    public function findByFilters(string $where = '',array $params = [],bool $asObject = false, ?int $limit = null): array {

        $sql = 'SELECT
                r.*,
                u.first_name,
                u.last_name,
                rs.code AS status_code,
                rs.name AS status_name,
                f.name AS facility_name,
                f.requires_guest_information,
                pm.name AS payment_name,
                fb.name AS bonus_name,
            (
                SELECT COUNT(*)
                FROM wi_reservations_guests rg
                WHERE rg.reservation_id = r.id
            ) AS guests_count
            FROM ' . self::TABLE . ' r
            INNER JOIN wi_users u
                ON u.id = r.user_id
            INNER JOIN wi_reservation_statuses rs
                ON rs.id = r.reservation_status_id
            INNER JOIN wi_facilities f
                ON f.id = r.facility_id
            LEFT JOIN wi_payment_methods pm
                ON pm.id = r.payment_method_id
            LEFT JOIN wi_users_bonuses ub
                ON ub.id = r.user_bonus_id
            LEFT JOIN wi_bonuses fb
                ON fb.id = ub.bonus_id
            ' . $where . '
            ORDER BY r.created_at DESC';

        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        return $this->db()->select(
                        $sql,
                        $params,
                        $asObject
                );
    }
    
    public function findEditDataById(int $reservation_id, bool $asObject = false): array|object|null {

        $sql = 'SELECT
                r.*,

                u.first_name,
                u.last_name,
                u.email,

                f.name AS facility_name,
                f.booking_type_id,
                f.requires_guest_information,
                f.access_enabled,

                rs.code AS status_code,
                rs.name AS status_name,

                fp.name AS price_name,
                fp.description AS price_description,

                but.code AS billing_unit_type_code,
                but.name AS billing_unit_type_name,

                bp.code AS billing_period_code,
                bp.name AS billing_period_name,
                
                bt.code AS booking_type_code,
                bt.name AS booking_type_name,

                pm.code AS payment_code,
                pm.name AS payment_name,

                fb.name AS bonus_name

            FROM ' . self::TABLE . ' r

            INNER JOIN wi_users u
                ON u.id = r.user_id

            INNER JOIN wi_facilities f
                ON f.id = r.facility_id

            INNER JOIN wi_reservation_statuses rs
                ON rs.id = r.reservation_status_id
                
            LEFT JOIN wi_booking_types bt
                ON bt.id = f.booking_type_id

            LEFT JOIN wi_facilities_prices fp
                ON fp.id = r.facility_price_id

            LEFT JOIN wi_billing_unit_types but
                ON but.id = fp.billing_unit_type_id

            LEFT JOIN wi_billing_periods bp
                ON bp.id = fp.billing_period_id

            LEFT JOIN wi_payment_methods pm
                ON pm.id = r.payment_method_id

            LEFT JOIN wi_users_bonuses ub
                ON ub.id = r.user_bonus_id

            LEFT JOIN wi_bonuses fb
                ON fb.id = ub.bonus_id

            WHERE r.id = ?
            LIMIT 1';

        return $this->db()->selectOne(
                        $sql,
                        [$reservation_id],
                        $asObject
                );
    }
    
    public function findCalendarReservations(string $start, string $end, int $facility_id = 0, bool $asObject = true): array {

        $params = [$end, $start];

        $sql = '
        SELECT
            r.*,
            f.name AS facility_name,
            rs.name AS status_name,
            rs.code AS status_code,
            CONCAT(u.first_name, " ", u.last_name) AS user_name

        FROM ' . self::TABLE . ' r

        LEFT JOIN wi_facilities f
            ON f.id = r.facility_id

        LEFT JOIN wi_reservation_statuses rs
            ON rs.id = r.reservation_status_id

        LEFT JOIN wi_users u
            ON u.id = r.user_id

        WHERE r.start_at < ?
          AND r.end_at > ?
          AND rs.code NOT IN ("cancelled", "rejected")';

        if ($facility_id > 0) {
            $sql .= ' AND r.facility_id = ?';
            $params[] = $facility_id;
        }

        $sql .= ' ORDER BY r.start_at ASC';

        return $this->db()->select($sql, $params, $asObject);
    }
    
    public function findExpiredConfirmed(bool $asObject = false): array {

        return $this->db()->select(
                        'SELECT *
             FROM ' . self::TABLE . '
             WHERE reservation_status_id = ?
               AND end_at < NOW()
             ORDER BY end_at ASC',
                        [
                            self::STATUS_CONFIRMED
                        ],
                        $asObject
                );
    }

    public function getStatisticsKpis(string $where, array $params): array {

        $sql = '
        SELECT
            COUNT(*) AS reservations,
            COUNT(DISTINCT r.user_id) AS clients,
            COALESCE(
                SUM(
                    CASE
                        WHEN rs.code IN ("confirmed", "completed")
                        THEN r.total_amount
                        ELSE 0
                    END
                ),
                0
            ) AS income
        FROM ' . self::TABLE . ' r
        LEFT JOIN wi_reservation_statuses rs
            ON rs.id = r.reservation_status_id
        ' . $where;

        $result = $this->db()->selectOne($sql, $params, false);

        return is_array($result) ? $result : [];
    }

    public function getStatisticsTrend(string $where, array $params, bool $group_by_day): array {

        $period = $group_by_day ? 'DAY(r.start_at)' : 'MONTH(r.start_at)';

        $sql = '
        SELECT
            ' . $period . ' AS period,
            COUNT(*) AS total
        FROM ' . self::TABLE . ' r
        ' . $where . '
        GROUP BY ' . $period . '
        ORDER BY period ASC';

        return $this->db()->select($sql, $params, false);
    }

    public function getStatisticsIncomeTrend(string $where, array $params, bool $group_by_day): array {

        $period = $group_by_day ? 'DAY(r.start_at)' : 'MONTH(r.start_at)';

        $sql = '
        SELECT
            ' . $period . ' AS period,
            COALESCE(SUM(r.total_amount), 0) AS total
        FROM ' . self::TABLE . ' r
        INNER JOIN wi_reservation_statuses rs
            ON rs.id = r.reservation_status_id
        ' . $where . '
        AND rs.code IN ("confirmed", "completed")
        GROUP BY ' . $period . '
        ORDER BY period ASC';

        return $this->db()->select($sql, $params, false);
    }

    public function getStatisticsStatuses(string $where, array $params): array {

        $sql = '
        SELECT
            rs.name,
            COUNT(*) AS total
        FROM ' . self::TABLE . ' r
        INNER JOIN wi_reservation_statuses rs
            ON rs.id = r.reservation_status_id
        ' . $where . '
        GROUP BY rs.id, rs.name
        ORDER BY total DESC';

        return $this->db()->select($sql, $params, false);
    }
    
    public function findReportData(string $where = '', array $params = [], bool $asObject = false): array {

        $sql = '
        SELECT
            r.id,
            r.reference,
            r.start_at,
            r.end_at,
            r.people_count,
            r.total_amount,
            r.facility_id,
            f.name AS facility_name,

            rs.code AS status_code,
            rs.name AS status_name,

            u.id AS user_id,
            CONCAT_WS(" ", u.first_name, u.last_name) AS user_name

        FROM ' . self::TABLE . ' r

        LEFT JOIN wi_facilities f
            ON f.id = r.facility_id

        LEFT JOIN wi_reservation_statuses rs
            ON rs.id = r.reservation_status_id

        LEFT JOIN wi_users u
            ON u.id = r.user_id

        ' . $where . '

        ORDER BY r.start_at DESC, r.id DESC';

        return $this->db()->select($sql, $params, $asObject);
    }

    public function findIncomeReportData(string $where = '', array $params = [], bool $asObject = false): array {

        $sql = '
        SELECT
            r.id,
            r.reference,
            r.facility_id,
            r.start_at,
            r.end_at,
            r.total_amount,

            f.name AS facility_name,

            rs.code AS status_code,
            rs.name AS status_name,

            u.id AS user_id,
            CONCAT_WS(" ", u.first_name, u.last_name) AS user_name

        FROM ' . self::TABLE . ' r

        LEFT JOIN wi_facilities f
            ON f.id = r.facility_id

        INNER JOIN wi_reservation_statuses rs
            ON rs.id = r.reservation_status_id

        LEFT JOIN wi_users u
            ON u.id = r.user_id

        ' . $where . '

        ORDER BY r.start_at DESC, r.id DESC';

        return $this->db()->select($sql, $params, $asObject);
    }
    

    public function add(): int {
        return $this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                reference,
                user_id,
                facility_id,
                facility_price_id,
                user_bonus_id,
                payment_method_id,
                payment_reference,
                start_at,
                end_at,
                people_count,
                reserved_units,
                unit_price,
                billable_units,
                total_amount,
                reservation_status_id,
                notes,
                cancellation_reason,
                cancelled_at,
                created_by_user_id
            )
            VALUES
            (
                ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
            )',
            [
                $this->reference,
                $this->user_id,
                $this->facility_id,
                $this->facility_price_id,
                $this->user_bonus_id,
                $this->payment_method_id,
                $this->payment_reference,
                $this->start_at,
                $this->end_at,
                $this->people_count,
                $this->reserved_units,
                $this->unit_price,
                $this->billable_units,
                $this->total_amount,
                $this->reservation_status_id,
                $this->notes,
                $this->cancellation_reason,
                $this->cancelled_at,
                $this->created_by_user_id
            ]
        );
    }

    public function update(): bool
    {
        if (empty($this->id)) {
            throw new RuntimeException('No se puede actualizar sin id.');
        }

        return $this->db()->execute(
            'UPDATE ' . self::TABLE . '
                SET
                    reference = ?,
                    user_id = ?,
                    facility_id = ?,
                    facility_price_id = ?,
                    user_bonus_id = ?,
                    payment_method_id = ?,
                    payment_reference = ?,
                    start_at = ?,
                    end_at = ?,
                    people_count = ?,
                    reserved_units = ?,
                    unit_price = ?,
                    billable_units = ?,
                    total_amount = ?,
                    reservation_status_id = ?,
                    notes = ?,
                    cancellation_reason = ?,
                    cancelled_at = ?,
                    created_by_user_id = ?,
                    updated_at = NOW()
              WHERE id = ?',
            [
                $this->reference,
                $this->user_id,
                $this->facility_id,
                $this->facility_price_id,
                $this->user_bonus_id,
                $this->payment_method_id,
                $this->payment_reference,
                $this->start_at,
                $this->end_at,
                $this->people_count,
                $this->reserved_units,
                $this->unit_price,
                $this->billable_units,
                $this->total_amount,
                $this->reservation_status_id,
                $this->notes,
                $this->cancellation_reason,
                $this->cancelled_at,
                $this->created_by_user_id,
                $this->id
            ]
        );
    }
    
    public function updatePaymentMethodById(int $reservation_id, int $payment_method_id): bool {

        return $this->db()->execute(
                        'UPDATE ' . self::TABLE . '
             SET
                payment_method_id = ?,
                updated_at = NOW()
             WHERE id = ?',
                        [
                            $payment_method_id,
                            $reservation_id
                        ]
                );
    }

    public function updateStatusById(int $reservation_id, int $status_id): bool {

        return $this->db()->execute(
                        'UPDATE ' . self::TABLE . '
             SET
                reservation_status_id = ?,
                updated_at = NOW()
             WHERE id = ?',
                        [
                            $status_id,
                            $reservation_id
                        ]
                );
    }
    
    public function updateNotesById(int $reservation_id, ?string $notes): bool {

        return $this->db()->execute(
                        'UPDATE ' . self::TABLE . '
             SET notes = ?,
                 updated_at = NOW()
             WHERE id = ?',
                        [$notes, $reservation_id]
                );
    }

    public function cancelById(int $reservation_id, int $status_id, string $cancellation_reason): bool {

        return $this->db()->execute(
                        'UPDATE ' . self::TABLE . '
             SET reservation_status_id = ?,
                 cancellation_reason = ?,
                 updated_at = NOW()
             WHERE id = ?',
                        [
                            $status_id,
                            $cancellation_reason,
                            $reservation_id
                        ]
                );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function getFacilityId(): int
    {
        return $this->facility_id;
    }

    public function getFacilityPriceId(): ?int
    {
        return $this->facility_price_id;
    }

    public function getUserBonusId(): ?int
    {
        return $this->user_bonus_id;
    }

    public function getPaymentMethodId(): ?int
    {
        return $this->payment_method_id;
    }

    public function getPaymentReference(): ?string
    {
        return $this->payment_reference;
    }

    public function getStartAt(): string
    {
        return $this->start_at;
    }

    public function getEndAt(): string
    {
        return $this->end_at;
    }

    public function getPeopleCount(): int
    {
        return $this->people_count;
    }

    public function getReservedUnits(): int
    {
        return $this->reserved_units;
    }

    public function getUnitPrice(): float
    {
        return $this->unit_price;
    }

    public function getBillableUnits(): float
    {
        return $this->billable_units;
    }

    public function getTotalAmount(): float
    {
        return $this->total_amount;
    }

    public function getReservationStatusId(): int
    {
        return $this->reservation_status_id;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellation_reason;
    }

    public function getCancelledAt(): ?string
    {
        return $this->cancelled_at;
    }

    public function getCreatedByUserId(): ?int
    {
        return $this->created_by_user_id;
    }

    public function getCreatedAt(): string
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): string
    {
        return $this->updated_at;
    }


    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setReference(string $reference): void
    {
        $this->reference = $reference;
    }

    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function setFacilityId(int $facility_id): void
    {
        $this->facility_id = $facility_id;
    }

    public function setFacilityPriceId(?int $facility_price_id): void
    {
        $this->facility_price_id = $facility_price_id;
    }

    public function setUserBonusId(?int $user_bonus_id): void
    {
        $this->user_bonus_id = $user_bonus_id;
    }

    public function setPaymentMethodId(?int $payment_method_id): void
    {
        $this->payment_method_id = $payment_method_id;
    }

    public function setPaymentReference(?string $payment_reference): void
    {
        $this->payment_reference = $payment_reference;
    }

    public function setStartAt(string $start_at): void
    {
        $this->start_at = $start_at;
    }

    public function setEndAt(string $end_at): void
    {
        $this->end_at = $end_at;
    }

    public function setPeopleCount(int $people_count): void
    {
        $this->people_count = $people_count;
    }

    public function setReservedUnits(int $reserved_units): void
    {
        $this->reserved_units = $reserved_units;
    }

    public function setUnitPrice(float $unit_price): void
    {
        $this->unit_price = $unit_price;
    }

    public function setBillableUnits(float $billable_units): void
    {
        $this->billable_units = $billable_units;
    }

    public function setTotalAmount(float $total_amount): void
    {
        $this->total_amount = $total_amount;
    }

    public function setReservationStatusId(int $reservation_status_id): void
    {
        $this->reservation_status_id = $reservation_status_id;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function setCancellationReason(?string $cancellation_reason): void
    {
        $this->cancellation_reason = $cancellation_reason;
    }

    public function setCancelledAt(?string $cancelled_at): void
    {
        $this->cancelled_at = $cancelled_at;
    }

    public function setCreatedByUserId(?int $created_by_user_id): void
    {
        $this->created_by_user_id = $created_by_user_id;
    }

    public function setCreatedAt(string $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function setUpdatedAt(string $updated_at): void
    {
        $this->updated_at = $updated_at;
    }

}
