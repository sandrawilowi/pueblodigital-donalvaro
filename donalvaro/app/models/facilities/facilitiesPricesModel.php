<?php

declare(strict_types=1);

class facilitiesPricesModel extends baseModel {

    protected const TABLE = 'wi_facilities_prices';
    protected const PRIMARY_KEY = 'id';
    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_BLOCKED = 2;
    public const STATUS_DELETED = 3;

    private int $id = 0;
    private int $facility_id = 0;
    private string $name = '';
    private ?string $description = null;
    private float $price = 0.0;
    private int $billing_unit_type_id = 0;
    private int $billing_period_id = 0;
    private ?string $valid_from = null;
    private ?string $valid_until = null;
    private int $status = 1;
    private string $created_at = '';
    private string $updated_at = '';
    private ?string $deleted_at = null;

    public function findByFacilityId(int $facility_id, bool $asObject = false): array {

        $sql = '
        SELECT 
            fp.*,
            but.code AS billing_unit_type_code,
            but.name AS billing_unit_type_name,
            bp.code AS billing_period_code,
            bp.name AS billing_period_name
        FROM ' . self::TABLE . ' fp
        INNER JOIN wi_billing_unit_types but ON but.id = fp.billing_unit_type_id
        INNER JOIN wi_billing_periods bp ON bp.id = fp.billing_period_id
        WHERE fp.facility_id = ?
          AND fp.status <> ?
          AND fp.deleted_at IS NULL
        ORDER BY fp.id ASC';

        return $this->db()->select($sql, [$facility_id, self::STATUS_DELETED], $asObject);
    }

    /**
     * Obtiene los precios activos de una instalación
     * vigentes durante todo el periodo indicado.
     */
    public function findActiveByFacilityAndPeriod(
            int $facility_id,
            string $start_at,
            string $end_at,
            bool $asObject = false
    ): array {

        $sql = '
        SELECT
            fp.*,
            but.code AS billing_unit_type_code,
            but.name AS billing_unit_type_name,
            bp.code AS billing_period_code,
            bp.name AS billing_period_name
        FROM ' . self::TABLE . ' fp
        INNER JOIN wi_billing_unit_types but
            ON but.id = fp.billing_unit_type_id
        INNER JOIN wi_billing_periods bp
            ON bp.id = fp.billing_period_id
        WHERE fp.facility_id = ?
          AND fp.status = ?
          AND fp.deleted_at IS NULL
          AND (fp.valid_from IS NULL OR fp.valid_from <= ?)
          AND (fp.valid_until IS NULL OR fp.valid_until >= ?)
        ORDER BY fp.id ASC';

        return $this->db()->select(
                        $sql,
                        [
                            $facility_id,
                            self::STATUS_ACTIVE,
                            substr($start_at, 0, 10),
                            substr($end_at, 0, 10)
                        ],
                        $asObject
                );
    }

    public function add(): int {
        return $this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                facility_id,
                name,
                description,
                price,
                billing_unit_type_id,
                billing_period_id,
                valid_from,
                valid_until,
                status
            )
            VALUES
            (
                ?,?,?,?,?,?,?,?,?
            )',
            [
                $this->facility_id,
                $this->name,
                $this->description,
                $this->price,
                $this->billing_unit_type_id,
                $this->billing_period_id,
                $this->valid_from,
                $this->valid_until,
                $this->status
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
                    facility_id = ?,
                    name = ?,
                    description = ?,
                    price = ?,
                    billing_unit_type_id = ?,
                    billing_period_id = ?,
                    valid_from = ?,
                    valid_until = ?,
                    status = ?,
                    updated_at = NOW()
              WHERE id = ?',
            [
                $this->facility_id,
                $this->name,
                $this->description,
                $this->price,
                $this->billing_unit_type_id,
                $this->billing_period_id,
                $this->valid_from,
                $this->valid_until,
                $this->status,
                $this->id
            ]
        );
    }
    
    public function updateStatusById(int $id, int $status): bool {

        $sql ='UPDATE ' . self::TABLE . '
         SET status = ?, updated_at = NOW()
         WHERE id = ?';
        
        return $this->db()->execute($sql,[$status, $id]);
    }

    public function softDelete(): bool
    {
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


    public function getId(): int
    {
        return $this->id;
    }

    public function getFacilityId(): int
    {
        return $this->facility_id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getBillingUnitTypeId(): int
    {
        return $this->billing_unit_type_id;
    }

    public function getBillingPeriodId(): int
    {
        return $this->billing_period_id;
    }

    public function getValidFrom(): ?string
    {
        return $this->valid_from;
    }

    public function getValidUntil(): ?string
    {
        return $this->valid_until;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getCreatedAt(): string
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): string
    {
        return $this->updated_at;
    }

    public function getDeletedAt(): ?string
    {
        return $this->deleted_at;
    }


    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setFacilityId(int $facility_id): void
    {
        $this->facility_id = $facility_id;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function setBillingUnitTypeId(int $billing_unit_type_id): void
    {
        $this->billing_unit_type_id = $billing_unit_type_id;
    }

    public function setBillingPeriodId(int $billing_period_id): void
    {
        $this->billing_period_id = $billing_period_id;
    }

    public function setValidFrom(?string $valid_from): void
    {
        $this->valid_from = $valid_from;
    }

    public function setValidUntil(?string $valid_until): void
    {
        $this->valid_until = $valid_until;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function setCreatedAt(string $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function setUpdatedAt(string $updated_at): void
    {
        $this->updated_at = $updated_at;
    }

    public function setDeletedAt(?string $deleted_at): void
    {
        $this->deleted_at = $deleted_at;
    }

}
