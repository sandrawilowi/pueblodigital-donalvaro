<?php

declare(strict_types=1);

class permissionsModel extends baseModel
{
    protected const TABLE = 'wi_permissions';
    protected const PRIMARY_KEY = 'id';

    private int $id = 0;
    private string $code = '';
    private string $module = '';
    private ?string $description = null;
    private string $created_at = '';


    public function add(): int
    {
        return $this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                code,
                module,
                description
            )
            VALUES
            (
                ?,?,?
            )',
            [
                $this->code,
                $this->module,
                $this->description
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
                    module = ?,
                    description = ?
              WHERE id = ?',
            [
                $this->code,
                $this->module,
                $this->description,
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

    public function getModule(): string
    {
        return $this->module;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreatedAt(): string
    {
        return $this->created_at;
    }


    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function setModule(string $module): void
    {
        $this->module = $module;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function setCreatedAt(string $created_at): void
    {
        $this->created_at = $created_at;
    }

}
