<?php

declare(strict_types=1);

class rolesModel extends baseModel
{
    protected const TABLE = 'wi_roles';
    protected const PRIMARY_KEY = 'id';

    private int $id = 0;
    private string $name = '';
    private ?string $description = null;
    private int $is_system = 0;
    private string $created_at = '';
    private ?string $updated_at = null;


    public function add(): int
    {
        return $this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                name,
                description,
                is_system
            )
            VALUES
            (
                ?,?,?
            )',
            [
                $this->name,
                $this->description,
                $this->is_system
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
                    is_system = ?,
                    updated_at = NOW()
              WHERE id = ?',
            [
                $this->name,
                $this->description,
                $this->is_system,
                $this->id
            ]
        );
    }

    public function findBySystem(int $is_system): ?array
    {
        return $this->db->select(
            'SELECT *
               FROM ' . static::TABLE . '
              WHERE is_system = ?',
            [$is_system]
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

    public function getIsSystem(): int
    {
        return $this->is_system;
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

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function setIsSystem(int $is_system): void
    {
        $this->is_system = $is_system;
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
