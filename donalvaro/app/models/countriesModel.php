<?php

declare(strict_types=1);

class countriesModel extends baseModel
{
    protected const TABLE = 'wi_countries';
    protected const PRIMARY_KEY = 'id';

    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_BLOCKED = 2;
    public const STATUS_DELETED = 3;

    private int $id = 0;
    private string $country_name = '';
    private ?string $nom = null;
    private ?string $iso2 = null;
    private ?string $iso3 = null;
    private ?string $num_code = null;
    private int $status = 1;
    private string $created_at = '';
    private ?string $updated_at = null;
    private ?string $deleted_at = null;

    /**
     * Obtiene todos los países activos.
     *
     * @param bool $asObject
     * @return array
     */
    public function findAllActive(bool $asObject = false): array {

        $sql = "SELECT *
            FROM " . self::TABLE . "
            WHERE status = ?
            ORDER BY (id = 209) DESC, nom ASC";

        return $this->db()->select($sql, [self::STATUS_ACTIVE], $asObject);
    }

    public function add(): int
    {
        return $this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                country_name,
                nom,
                iso2,
                iso3,
                num_code,
                status
            )
            VALUES
            (
                ?,?,?,?,?,?
            )',
            [
                $this->country_name,
                $this->nom,
                $this->iso2,
                $this->iso3,
                $this->num_code,
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
                    country_name = ?,
                    nom = ?,
                    iso2 = ?,
                    iso3 = ?,
                    num_code = ?,
                    status = ?,
                    updated_at = NOW()
              WHERE id = ?',
            [
                $this->country_name,
                $this->nom,
                $this->iso2,
                $this->iso3,
                $this->num_code,
                $this->status,
                $this->id
            ]
        );
    }

    public function softDelete(): bool
    {
        if ($this->id === null) {
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

    public function getCountryName(): string
    {
        return $this->country_name;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function getIso2(): ?string
    {
        return $this->iso2;
    }

    public function getIso3(): ?string
    {
        return $this->iso3;
    }

    public function getNumCode(): ?string
    {
        return $this->num_code;
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

    public function setCountryName(string $country_name): void
    {
        $this->country_name = $country_name;
    }

    public function setNom(?string $nom): void
    {
        $this->nom = $nom;
    }

    public function setIso2(?string $iso2): void
    {
        $this->iso2 = $iso2;
    }

    public function setIso3(?string $iso3): void
    {
        $this->iso3 = $iso3;
    }

    public function setNumCode(?string $num_code): void
    {
        $this->num_code = $num_code;
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
