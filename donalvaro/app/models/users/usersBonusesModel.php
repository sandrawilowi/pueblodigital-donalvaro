<?php

declare(strict_types=1);

class usersBonusesModel extends baseModel {

    protected const TABLE = 'wi_users_bonuses';
    protected const PRIMARY_KEY = 'id';
    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_BLOCKED = 2;
    public const STATUS_DELETED = 3;

    private int $id = 0;
    private int $user_id = 0;
    private int $bonus_id = 0;
    private int $initial_uses = 0;
    private int $remaining_uses = 0;
    private float $purchase_price = 0.0;
    private string $purchased_at = '';
    private ?string $valid_from = null;
    private ?string $expires_at = null;
    private int $status = 1;
    private string $created_at = '';
    private string $updated_at = '';
    private ?string $deleted_at = null;
    private ?int $payment_method_id = null;

    /**
     * Obtiene los bonos adquiridos por un usuario.
     *
     * @param int $user_id
     * @param bool $asObject
     * @return array
     */
    public function findByUserId(int $user_id, bool $asObject = false): array {

        return $this->db()->select(
                        'SELECT ub.*,
                    b.name AS bonus_name,
                    b.description AS bonus_description,
                    b.total_uses,
                    b.validity_days,
                    b.validity_mode,
                    b.bonus_type,
                    (
                        SELECT GROUP_CONCAT(DISTINCT f.name ORDER BY f.name SEPARATOR \', \')
                        FROM wi_bonuses_facilities bf
                        INNER JOIN wi_facilities f ON f.id = bf.facility_id
                        WHERE bf.bonus_id = b.id
                    ) AS facilities
             FROM ' . self::TABLE . ' ub
             INNER JOIN wi_bonuses b ON b.id = ub.bonus_id
             WHERE ub.user_id = ?
             AND ub.deleted_at IS NULL
             ORDER BY ub.purchased_at DESC',
                        [$user_id],
                        $asObject
                );
    }

    public function findAvailableByUserAndFacility(
            int $user_id,
            int $facility_id,
            string $start_at,
            string $end_at,
            bool $asObject = false
    ): array {

        $sql = 'SELECT
        ub.*,
        b.name AS bonus_name,
        b.description AS bonus_description,
        b.bonus_type
    FROM ' . self::TABLE . ' ub
    INNER JOIN wi_bonuses b
        ON b.id = ub.bonus_id
    INNER JOIN wi_bonuses_facilities bf
        ON bf.bonus_id = b.id
    WHERE ub.user_id = ?
    AND bf.facility_id = ?
    AND ub.status = ?
    AND (
        b.bonus_type = ?
        OR (
            b.bonus_type = ?
            AND ub.remaining_uses > 0
        )
    )
    AND ub.deleted_at IS NULL
    AND (
    ub.valid_from IS NULL
    OR ub.valid_from <= ?
)
AND (
    ub.expires_at IS NULL
    OR ub.expires_at >= ?
)
    ORDER BY ub.valid_from ASC';

        return $this->db()->select(
                        $sql,
                        [
                            $user_id,
                            $facility_id,
                            self::STATUS_ACTIVE,
                            bonusesModel::TYPE_TIME,
                            bonusesModel::TYPE_USES,
                            substr($start_at, 0, 10),
                            substr($end_at, 0, 10)
                        ],
                        $asObject
                );
    }

    public function findAvailableById(
            int $user_bonus_id,
            int $user_id,
            int $facility_id,
            string $start_at,
            string $end_at,
            bool $asObject = false
    ): array|object|null {

        $sql = 'SELECT
        ub.*,
        bf.facility_id,
        b.name AS bonus_name,
        b.description AS bonus_description,
        b.bonus_type
    FROM ' . self::TABLE . ' ub
    INNER JOIN wi_bonuses b
        ON b.id = ub.bonus_id
    INNER JOIN wi_bonuses_facilities bf
        ON bf.bonus_id = b.id
    WHERE ub.id = ?
    AND ub.user_id = ?
    AND bf.facility_id = ?
    AND ub.status = ?
    AND (
        b.bonus_type = ?
        OR (
            b.bonus_type = ?
            AND ub.remaining_uses > 0
        )
    )
    AND ub.deleted_at IS NULL
    AND (
    ub.valid_from IS NULL
    OR ub.valid_from <= ?
)
AND (
    ub.expires_at IS NULL
    OR ub.expires_at >= ?
)
    LIMIT 1';

        return $this->db()->selectOne(
                        $sql,
                        [
                            $user_bonus_id,
                            $user_id,
                            $facility_id,
                            self::STATUS_ACTIVE,
                            bonusesModel::TYPE_TIME,
                            bonusesModel::TYPE_USES,
                            substr($start_at, 0, 10),
                            substr($end_at, 0, 10)
                        ],
                        $asObject
                );
    }

    public function findDetailById(int $bonus_id, bool $asObject = false): array|object|null {

        $sql = '
    SELECT
        ub.*,
        b.name AS bonus_name,
        b.description AS bonus_description,
        b.total_uses,
        b.bonus_type,
        b.validity_mode,
        b.validity_days,
        u.email,
        u.first_name,
        u.last_name
    FROM ' . self::TABLE . ' ub
    INNER JOIN wi_bonuses b
        ON b.id = ub.bonus_id
    INNER JOIN wi_users u
        ON u.id = ub.user_id
    WHERE ub.id = ?
      AND ub.deleted_at IS NULL
    LIMIT 1';

        return $this->db()->selectOne($sql, [$bonus_id], $asObject);
    }

    public function findPending(bool $asObject = false, ?int $limit = null): array {

        $sql = '
    SELECT
        ub.*,
        b.name AS bonus_name,
        b.bonus_type,
        u.id AS user_id,
        CONCAT(u.first_name, " ", u.last_name) AS user_name,
        pm.name AS payment_method_name
    FROM ' . self::TABLE . ' ub
    INNER JOIN wi_bonuses b
        ON b.id = ub.bonus_id
    INNER JOIN wi_users u
        ON u.id = ub.user_id
    LEFT JOIN wi_payment_methods pm
        ON pm.id = ub.payment_method_id
    WHERE ub.status = ' . self::STATUS_INACTIVE . '
        AND ub.deleted_at IS NULL
    ORDER BY ub.purchased_at ASC';

        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        return $this->db()->select($sql, [], $asObject);
    }
    
    public function existsByBonusId(int $bonus_id): bool {

        $result = $this->db()->select(
                'SELECT id
         FROM ' . self::TABLE . '
         WHERE bonus_id = ?
         LIMIT 1',
                [$bonus_id],
                false
        );

        return !empty($result);
    }

    public function decreaseRemainingUses(int $user_bonus_id, int $uses): bool {

        if ($user_bonus_id <= 0 || $uses <= 0) {
            return false;
        }

        $sql = 'UPDATE ' . self::TABLE . '
            SET
                remaining_uses = remaining_uses - ?,
                updated_at = NOW()
            WHERE id = ?
            AND remaining_uses >= ?';

        return $this->db()->execute(
                        $sql,
                        [
                            $uses,
                            $user_bonus_id,
                            $uses
                        ]
                );
    }

    public function increaseRemainingUses(int $user_bonus_id, int $uses): bool {

        if ($user_bonus_id <= 0 || $uses <= 0) {
            return false;
        }

        return $this->db()->execute(
                        'UPDATE ' . self::TABLE . '
             SET
                remaining_uses = remaining_uses + ?,
                updated_at = NOW()
             WHERE id = ?',
                        [
                            $uses,
                            $user_bonus_id
                        ]
                );
    }

    public function softDelete(): bool {

        if (empty($this->id)) {
            throw new RuntimeException('No se puede eliminar el bono sin id.');
        }

        return $this->db()->execute(
                        'UPDATE ' . self::TABLE . '
            SET status = ?,
                deleted_at = NOW(),
                updated_at = NOW()
          WHERE id = ?',
                        [
                            self::STATUS_DELETED,
                            $this->id
                        ]
                );
    }

    public function findActiveByUserAndFacility(
            int $user_id,
            int $facility_id,
            bool $asObject = false
    ): array {

        $sql = '
    SELECT
        ub.*,
        b.name AS bonus_name,
        b.description AS bonus_description,
        b.bonus_type
    FROM ' . self::TABLE . ' ub
    INNER JOIN wi_bonuses b
        ON b.id = ub.bonus_id
    INNER JOIN wi_bonuses_facilities bf
        ON bf.bonus_id = b.id
    WHERE ub.user_id = ?
        AND bf.facility_id = ?
        AND ub.status = ?
        AND (
            b.bonus_type = ?
            OR (
                b.bonus_type = ?
                AND ub.remaining_uses > 0
            )
        )
        AND ub.deleted_at IS NULL
    ORDER BY ub.valid_from ASC';

        return $this->db()->select(
                        $sql,
                        [
                            $user_id,
                            $facility_id,
                            self::STATUS_ACTIVE,
                            bonusesModel::TYPE_TIME,
                            bonusesModel::TYPE_USES
                        ],
                        $asObject
                );
    }

    public function findReportData(string $where = '', array $params = [], bool $asObject = false): array {

        $sql = '
        SELECT
            ub.id,
            ub.user_id,
            ub.bonus_id,
            ub.initial_uses,
            ub.remaining_uses,
            ub.purchase_price,
            ub.payment_method_id,
            ub.purchased_at,
            ub.valid_from,
            ub.expires_at,
            ub.status,

            b.name AS bonus_name,
            b.bonus_type,

            u.first_name,
            u.last_name,
            CONCAT_WS(" ", u.first_name, u.last_name) AS user_name,

            pm.name AS payment_method_name,

            GROUP_CONCAT(
                DISTINCT f.name
                ORDER BY f.name
                SEPARATOR ", "
            ) AS facilities

        FROM ' . self::TABLE . ' ub

        INNER JOIN wi_bonuses b
            ON b.id = ub.bonus_id

        INNER JOIN wi_users u
            ON u.id = ub.user_id

        LEFT JOIN wi_payment_methods pm
            ON pm.id = ub.payment_method_id

        LEFT JOIN wi_bonuses_facilities bf
            ON bf.bonus_id = b.id

        LEFT JOIN wi_facilities f
            ON f.id = bf.facility_id

        ' . $where . '

        GROUP BY
            ub.id,
            ub.user_id,
            ub.bonus_id,
            ub.initial_uses,
            ub.remaining_uses,
            ub.purchase_price,
            ub.payment_method_id,
            ub.purchased_at,
            ub.valid_from,
            ub.expires_at,
            ub.status,
            b.name,
            b.bonus_type,
            u.first_name,
            u.last_name,
            pm.name

        ORDER BY ub.purchased_at DESC, ub.id DESC';

        return $this->db()->select(
                        $sql,
                        $params,
                        $asObject
                );
    }

    public function getStatisticsKpis(string $where, array $params = []): array {

        $result = $this->db()->selectOne(
                'SELECT
                COUNT(ub.id) AS bonuses,
                COALESCE(SUM(ub.purchase_price), 0) AS income
             FROM ' . self::TABLE . ' ub
             ' . $where,
                $params,
                false
        );

        return $result ?: [
            'bonuses' => 0,
            'income' => 0
        ];
    }

    public function getStatisticsTrend(string $where, array $params = [], bool $group_by_day = false): array {

        $period = $group_by_day ? 'DAY(ub.purchased_at)' : 'MONTH(ub.purchased_at)';

        return $this->db()->select(
                        'SELECT
                ' . $period . ' AS period,
                COUNT(ub.id) AS total
             FROM ' . self::TABLE . ' ub
             ' . $where . '
             GROUP BY period
             ORDER BY period ASC',
                        $params,
                        false
                );
    }

    public function getStatisticsIncomeTrend(string $where, array $params = [], bool $group_by_day = false): array {

        $period = $group_by_day ? 'DAY(ub.purchased_at)' : 'MONTH(ub.purchased_at)';

        return $this->db()->select(
                        'SELECT
                ' . $period . ' AS period,
                COALESCE(SUM(ub.purchase_price), 0) AS total
             FROM ' . self::TABLE . ' ub
             ' . $where . '
             GROUP BY period
             ORDER BY period ASC',
                        $params,
                        false
                );
    }

    public function add(): int {

        return $this->db()->insert(
                        'INSERT INTO ' . self::TABLE . '
            (
                user_id,
                bonus_id,
                initial_uses,
                remaining_uses,
                purchase_price,
                payment_method_id,
                valid_from,
                expires_at,
                status
            )
            VALUES
            (
                ?,?,?,?,?,?,?,?,?
            )',
                        [
                            $this->user_id,
                            $this->bonus_id,
                            $this->initial_uses,
                            $this->remaining_uses,
                            $this->purchase_price,
                            $this->payment_method_id,
                            $this->valid_from,
                            $this->expires_at,
                            $this->status
                        ]
                );
    }

    public function update(): bool {
        if (empty($this->id)) {
            throw new RuntimeException('No se puede actualizar sin id.');
        }

        return $this->db()->execute(
                        'UPDATE ' . self::TABLE . '
                SET
                    user_id = ?,
                    bonus_id = ?,
                    initial_uses = ?,
                    remaining_uses = ?,
                    purchase_price = ?,
                    purchased_at = ?,
                    valid_from = ?,
                    expires_at = ?,
                    status = ?,
                    updated_at = NOW()
              WHERE id = ?',
                        [
                            $this->user_id,
                            $this->bonus_id,
                            $this->initial_uses,
                            $this->remaining_uses,
                            $this->purchase_price,
                            $this->purchased_at,
                            $this->valid_from,
                            $this->expires_at,
                            $this->status,
                            $this->id
                        ]
                );
    }

    public function activate(int $bonus_id, string $valid_from, ?string $expires_at): bool {

        if ($bonus_id <= 0) {
            throw new InvalidArgumentException('El identificador del bono no es válido.');
        }

        return $this->db()->execute(
                        'UPDATE ' . self::TABLE . '
            SET
                status = ?,
                valid_from = ?,
                expires_at = ?,
                updated_at = NOW()
            WHERE id = ?
              AND status = ?
              AND deleted_at IS NULL',
                        [
                            self::STATUS_ACTIVE,
                            $valid_from,
                            $expires_at,
                            $bonus_id,
                            self::STATUS_INACTIVE
                        ]
                );
    }

    public function getId(): int {
        return $this->id;
    }

    public function getUserId(): int {
        return $this->user_id;
    }

    public function getBonusId(): int {
    return $this->bonus_id;
}

    public function getInitialUses(): int {
        return $this->initial_uses;
    }

    public function getRemainingUses(): int {
        return $this->remaining_uses;
    }

    public function getPurchasePrice(): float {
        return $this->purchase_price;
    }

    public function getPurchasedAt(): string {
        return $this->purchased_at;
    }

    public function getValidFrom(): ?string {
        return $this->valid_from;
    }

    public function getExpiresAt(): ?string {
        return $this->expires_at;
    }

    public function getStatus(): int {
        return $this->status;
    }

    public function getCreatedAt(): string {
        return $this->created_at;
    }

    public function getUpdatedAt(): string {
        return $this->updated_at;
    }

    public function getDeletedAt(): ?string {
        return $this->deleted_at;
    }

    public function setId(int $id): void {
        $this->id = $id;
    }

    public function setUserId(int $user_id): void {
        $this->user_id = $user_id;
    }

    public function setBonusId(int $bonus_id): void {
    $this->bonus_id = $bonus_id;
}

    public function setInitialUses(int $initial_uses): void {
        $this->initial_uses = $initial_uses;
    }

    public function setRemainingUses(int $remaining_uses): void {
        $this->remaining_uses = $remaining_uses;
    }

    public function setPurchasePrice(float $purchase_price): void {
        $this->purchase_price = $purchase_price;
    }

    public function setPurchasedAt(string $purchased_at): void {
        $this->purchased_at = $purchased_at;
    }

    public function setValidFrom(?string $valid_from): void {
        $this->valid_from = $valid_from;
    }

    public function setExpiresAt(?string $expires_at): void {
        $this->expires_at = $expires_at;
    }

    public function setStatus(int $status): void {
        $this->status = $status;
    }

    public function setCreatedAt(string $created_at): void {
        $this->created_at = $created_at;
    }

    public function setUpdatedAt(string $updated_at): void {
        $this->updated_at = $updated_at;
    }

    public function setDeletedAt(?string $deleted_at): void {
        $this->deleted_at = $deleted_at;
    }

    public function getPaymentMethodId(): ?int {
        return $this->payment_method_id;
    }

    public function setPaymentMethodId(?int $payment_method_id): void {
        $this->payment_method_id = $payment_method_id;
    }
}
