<?php

declare(strict_types=1);

class bookingTypesModel extends baseModel
{
    protected const TABLE = 'wi_booking_types';
    protected const PRIMARY_KEY = 'id';

    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_BLOCKED = 2;
    public const STATUS_DELETED = 3;

    private int $id = 0;
    private string $code = '';
    private string $name = '';
    private ?string $description = null;
    private int $status = 1;
    private string $created_at = '';
    private ?string $updated_at = null;

    public function findAllActive(bool $asObject = false): array {

        return $this->db()->select(
                        'SELECT *
             FROM ' . self::TABLE . '
             WHERE status = ?
             ORDER BY name ASC',
                        [self::STATUS_ACTIVE],
                        $asObject
                );
    }

    public function add(): int
    {
        return $this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                code,
                name,
                description,
                status
            )
            VALUES
            (
                ?,?,?,?
            )',
            [
                $this->code,
                $this->name,
                $this->description,
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
                    code = ?,
                    name = ?,
                    description = ?,
                    status = ?,
                    updated_at = NOW()
              WHERE id = ?',
            [
                $this->code,
                $this->name,
                $this->description,
                $this->status,
                $this->id
            ]
        );
    }


    public function getId(): int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
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


    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
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

}
