<?php

declare(strict_types=1);

class settingsModel extends baseModel
{
    protected const TABLE = 'wi_settings';
    protected const PRIMARY_KEY = 'id';

    private int $id = 0;
    private string $setting_key = '';
    private string $setting_value = '';
    private ?string $description = null;
    private string $created_at = '';
    private string $updated_at = '';

    public function findValueByKey(
            string $setting_key
    ): ?string {

        $sql = '
        SELECT setting_value
        FROM ' . self::TABLE . '
        WHERE setting_key = ?
        LIMIT 1';

        $result = $this->db()->selectOne(
                $sql,
                [$setting_key],
                true
        );

        return $result?->setting_value ?? null;
    }

    public function add(): int
    {
        return $this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                setting_key,
                setting_value,
                description
            )
            VALUES
            (
                ?,?,?
            )',
            [
                $this->setting_key,
                $this->setting_value,
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
                    setting_key = ?,
                    setting_value = ?,
                    description = ?,
                    updated_at = NOW()
              WHERE id = ?',
            [
                $this->setting_key,
                $this->setting_value,
                $this->description,
                $this->id
            ]
        );
    }


    public function getId(): int
    {
        return $this->id;
    }

    public function getSettingKey(): string
    {
        return $this->setting_key;
    }

    public function getSettingValue(): string
    {
        return $this->setting_value;
    }

    public function getDescription(): ?string
    {
        return $this->description;
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

    public function setSettingKey(string $setting_key): void
    {
        $this->setting_key = $setting_key;
    }

    public function setSettingValue(string $setting_value): void
    {
        $this->setting_value = $setting_value;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
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
