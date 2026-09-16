<?php

declare(strict_types=1);

class bonusesModel extends baseModel {

    protected const TABLE = 'wi_bonuses';
    protected const PRIMARY_KEY = 'id';
    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_BLOCKED = 2;
    public const STATUS_DELETED = 3;
    public const TYPE_USES = 'USES';
    public const TYPE_TIME = 'TIME';
    public const VALIDITY_NONE = 'NONE';
    public const VALIDITY_DAYS = 'DAYS';
    public const VALIDITY_CALENDAR_WEEK = 'CALENDAR_WEEK';
    public const VALIDITY_CALENDAR_MONTH = 'CALENDAR_MONTH';
    public const VALIDITY_CALENDAR_YEAR = 'CALENDAR_YEAR';

    private int $id = 0;
    private string $name = '';
    private ?string $description = null;
    private string $bonus_type = self::TYPE_USES;
    private string $validity_mode = self::VALIDITY_DAYS;
    private int $total_uses = 0;
    private float $price = 0.0;
    private ?string $pending_payment_instructions = null;
    private ?int $validity_days = null;
    private int $status = 1;
    private string $created_at = '';
    private string $updated_at = '';
    private ?string $deleted_at = null;

    public function findAllNotDeleted(bool $asObject): array {

        return $this->db()->select(
                        'SELECT b.*,
                GROUP_CONCAT(DISTINCT f.name ORDER BY f.name SEPARATOR \', \') AS facilities,
                GROUP_CONCAT(DISTINCT bf.facility_id ORDER BY bf.facility_id) AS facility_ids
         FROM ' . self::TABLE . ' b
         LEFT JOIN wi_bonuses_facilities bf ON bf.bonus_id = b.id
         LEFT JOIN wi_facilities f ON f.id = bf.facility_id
         WHERE b.status <> ?
         GROUP BY b.id
         ORDER BY b.id DESC',
                        [self::STATUS_DELETED],
                        $asObject
                );
    }

    public function findAllActive(bool $asObject = false, ?int $limit = null): array {

        $sql = '
        SELECT *
        FROM ' . self::TABLE . '
        WHERE status = ?
        ORDER BY name ASC';

        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        return $this->db()->select(
                        $sql,
                        [self::STATUS_ACTIVE],
                        $asObject
                );
    }
    
    public function findByFilters(string $where, array $params = [], bool $asObject = false): array {

        return $this->db()->select(
                        'SELECT
            b.*,
            GROUP_CONCAT(DISTINCT f.name ORDER BY f.name SEPARATOR \', \') AS facilities,
            GROUP_CONCAT(DISTINCT bf.facility_id ORDER BY bf.facility_id) AS facility_ids
         FROM ' . self::TABLE . ' b
         LEFT JOIN wi_bonuses_facilities bf ON bf.bonus_id = b.id
         LEFT JOIN wi_facilities f ON f.id = bf.facility_id
         ' . $where . '
         GROUP BY b.id
         ORDER BY b.id DESC',
                        $params,
                        $asObject
                );
    }

    public function updateStatusById(int $id, int $status): bool {

        return $this->db()->execute(
                        'UPDATE ' . self::TABLE . '
         SET status = ?, updated_at = NOW()
         WHERE id = ?',
                        [$status, $id]
                );
    }

    public function add(): int {

        return $this->db()->insert(
                        'INSERT INTO ' . self::TABLE . '
        (
            name,
            description,
            bonus_type,
            total_uses,
            price,
            pending_payment_instructions,
            validity_days,
            validity_mode,
            status
        )
        VALUES
        (
            ?,?,?,?,?,?,?,?,?
        )',
                        [
                            $this->name,
                            $this->description,
                            $this->bonus_type,
                            $this->total_uses,
                            $this->price,
                            $this->pending_payment_instructions,
                            $this->validity_days,
                            $this->validity_mode,
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
            name = ?,
            description = ?,
            bonus_type = ?,
            total_uses = ?,
            price = ?,
            pending_payment_instructions = ?,
            validity_days = ?,
            validity_mode = ?,
            status = ?,
            updated_at = NOW()
      WHERE id = ?',
                        [
                            $this->name,
                            $this->description,
                            $this->bonus_type,
                            $this->total_uses,
                            $this->price,
                            $this->pending_payment_instructions,
                            $this->validity_days,
                            $this->validity_mode,
                            $this->status,
                            $this->id
                        ]
                );
    }

    public function softDelete(): bool {
        if (empty($this->id)) {
            throw new RuntimeException('No se puede eliminar sin id.');
        }

        return $this->db()->execute(
                        'UPDATE ' . self::TABLE . '
                SET
                    status = ?,
                    updated_at = NOW(),
                    deleted_at = NOW()
              WHERE id = ?',
                        [
                            self::STATUS_DELETED,
                            $this->id
                        ]
                );
    }

    public function getId(): int {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function getTotalUses(): int {
        return $this->total_uses;
    }

    public function getPrice(): float {
        return $this->price;
    }

    public function getPendingPaymentInstructions(): ?string {
        return $this->pending_payment_instructions;
    }

    public function getValidityDays(): ?int {
        return $this->validity_days;
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

    public function getBonusType(): string {
        return $this->bonus_type;
    }

    public function getValidityMode(): string {
        return $this->validity_mode;
    }

    public function setId(int $id): void {
        $this->id = $id;
    }

    public function setName(string $name): void {
        $this->name = $name;
    }

    public function setDescription(?string $description): void {
        $this->description = $description;
    }

    public function setTotalUses(int $total_uses): void {
        $this->total_uses = $total_uses;
    }

    public function setPrice(float $price): void {
        $this->price = $price;
    }

    public function setPendingPaymentInstructions(?string $pending_payment_instructions): void {
        $this->pending_payment_instructions = $pending_payment_instructions;
    }

    public function setValidityDays(?int $validity_days): void {
        $this->validity_days = $validity_days;
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

    public function setBonusType(string $bonus_type): void {
        $this->bonus_type = $bonus_type;
    }

    public function setValidityMode(string $validity_mode): void {
        $this->validity_mode = $validity_mode;
    }
}
