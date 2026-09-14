<?php

declare(strict_types=1);

class facilitiesAvailabilityModel extends baseModel
{
    protected const TABLE = 'wi_facilities_availability';
    protected const PRIMARY_KEY = 'id';

    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_BLOCKED = 2;
    public const STATUS_DELETED = 3;

    private int $id = 0;
    private int $facility_id = 0;
    private string $available_from = '';
    private string $available_until = '';
    private int $status = 1;
    private string $created_at = '';
    private ?string $updated_at = null;
    private ?string $deleted_at = null;


    /**
     * Obtiene toda la disponibilidad activa de una instalación.
     *
     * @param int $facility_id
     * @param bool $asObject
     *
     * @return array
     */
    public function findActiveByFacilityId(int $facility_id, bool $asObject = false): array {

        $sql = 'SELECT *
                FROM ' . self::TABLE . '
                WHERE facility_id = ?
                AND status = ?
                AND deleted_at IS NULL
                ORDER BY available_from ASC';

        return $this->db()->select(
                $sql,
                [
                    $facility_id,
                    self::STATUS_ACTIVE
                ],
                $asObject
        );
    }


    /**
     * Obtiene la disponibilidad activa de una instalación
     * dentro de un rango de fechas.
     *
     * Útil para cargar únicamente el periodo visible
     * en FullCalendar.
     *
     * @param int $facility_id
     * @param string $start_at
     * @param string $end_at
     * @param bool $asObject
     *
     * @return array
     */
    public function findActiveByRange(
            int $facility_id,
            string $start_at,
            string $end_at,
            bool $asObject = false
    ): array {

        $sql = 'SELECT *
                FROM ' . self::TABLE . '
                WHERE facility_id = ?
                AND status = ?
                AND deleted_at IS NULL
                AND available_from < ?
                AND available_until > ?
                ORDER BY available_from ASC';

        return $this->db()->select(
                $sql,
                [
                    $facility_id,
                    self::STATUS_ACTIVE,
                    $end_at,
                    $start_at
                ],
                $asObject
        );
    }


    /**
     * Busca una franja de disponibilidad que cubra
     * completamente el periodo solicitado.
     *
     * @param int $facility_id
     * @param string $start_at
     * @param string $end_at
     * @param bool $asObject
     *
     * @return array|object|null
     */
    public function findCoveringPeriod(
            int $facility_id,
            string $start_at,
            string $end_at,
            bool $asObject = false
    ): array|object|null {

        $sql = 'SELECT *
                FROM ' . self::TABLE . '
                WHERE facility_id = ?
                AND status = ?
                AND deleted_at IS NULL
                AND available_from <= ?
                AND available_until >= ?
                ORDER BY available_from ASC
                LIMIT 1';

        return $this->db()->selectOne(
                        $sql,
                        [
                            $facility_id,
                            self::STATUS_ACTIVE,
                            $start_at,
                            $end_at
                        ],
                        $asObject
                );
    }

    public function existsPeriod(int $facility_id, string $available_from, string $available_until): bool {

        $sql = 'SELECT id
            FROM ' . self::TABLE . '
            WHERE facility_id = ?
            AND available_from = ?
            AND available_until = ?
            AND status = ?
            AND deleted_at IS NULL
            LIMIT 1';

        $result = $this->db()->selectOne(
                $sql,
                [
                    $facility_id,
                    $available_from,
                    $available_until,
                    self::STATUS_ACTIVE
                ],
                false
        );

        return !empty($result);
    }

    public function existsOverlap(
            int $facility_id,
            string $available_from,
            string $available_until
    ): bool {

        $sql = 'SELECT id
            FROM ' . self::TABLE . '
            WHERE facility_id = ?
            AND status = ?
            AND deleted_at IS NULL
            AND available_from < ?
            AND available_until > ?
            LIMIT 1';

        $result = $this->db()->selectOne(
                $sql,
                [
                    $facility_id,
                    self::STATUS_ACTIVE,
                    $available_until,
                    $available_from
                ],
                false
        );

        return !empty($result);
    }


    public function add(): int {

        return $this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                facility_id,
                available_from,
                available_until,
                status
            )
            VALUES
            (
                ?,?,?,?
            )',
            [
                $this->facility_id,
                $this->available_from,
                $this->available_until,
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
                    facility_id = ?,
                    available_from = ?,
                    available_until = ?,
                    status = ?,
                    updated_at = NOW()
              WHERE id = ?',
            [
                $this->facility_id,
                $this->available_from,
                $this->available_until,
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


    public function getId(): int
    {
        return $this->id;
    }

    public function getFacilityId(): int
    {
        return $this->facility_id;
    }

    public function getAvailableFrom(): string
    {
        return $this->available_from;
    }

    public function getAvailableUntil(): string
    {
        return $this->available_until;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getCreatedAt(): string
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): ?string
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

    public function setAvailableFrom(string $available_from): void
    {
        $this->available_from = $available_from;
    }

    public function setAvailableUntil(string $available_until): void
    {
        $this->available_until = $available_until;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function setCreatedAt(string $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function setUpdatedAt(?string $updated_at): void
    {
        $this->updated_at = $updated_at;
    }

    public function setDeletedAt(?string $deleted_at): void
    {
        $this->deleted_at = $deleted_at;
    }
}