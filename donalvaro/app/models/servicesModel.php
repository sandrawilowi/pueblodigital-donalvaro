<?php

declare(strict_types=1);

class servicesModel extends baseModel {

    protected const TABLE = 'wi_services';
    protected const PRIMARY_KEY = 'id';
    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_BLOCKED = 2;
    public const STATUS_DELETED = 3;

    private int $id = 0;
    private string $name = '';
    private ?string $description = null;
    private ?string $icon = null;
    private int $status = 1;
    private string $created_at = '';
    private string $updated_at = '';
    private ?string $deleted_at = null;

    public function findAllActive(
            bool $asObject = false
    ): array {

        $sql = '
        SELECT *
        FROM ' . self::TABLE . '
        WHERE status = ?
          AND deleted_at IS NULL
        ORDER BY name ASC';

        return $this->db()->select(
                        $sql,
                        [
                            self::STATUS_ACTIVE
                        ],
                        $asObject
                );
    }

    public function add(): int {
        return $this->db()->insert(
                        'INSERT INTO ' . self::TABLE . '
            (
                name,
                description,
                icon,
                status
            )
            VALUES
            (
                ?,?,?,?
            )',
            [
                $this->name,
                $this->description,
                $this->icon,
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
                    name = ?,
                    description = ?,
                    icon = ?,
                    status = ?,
                    updated_at = NOW()
              WHERE id = ?',
            [
                $this->name,
                $this->description,
                $this->icon,
                $this->status,
                $this->id
            ]
        );
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
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

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
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
