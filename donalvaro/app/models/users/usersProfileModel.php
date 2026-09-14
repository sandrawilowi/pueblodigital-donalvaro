<?php

declare(strict_types=1);

class usersProfileModel extends baseModel
{
    protected const TABLE = 'wi_users_profile';
    protected const PRIMARY_KEY = 'user_id';

    private int $user_id = 0;
    private ?string $phone = null;
    private ?string $photo = null;
    private ?int $country_id = null;
    private ?int $province_id = null;
    private ?string $address = null;
    private ?string $postal_code = null;
    private ?string $city = null;
    private string $created_at = '';
    private string $updated_at = '';

    public function existsByUserId(int $user_id): bool {

        $sql = "SELECT user_id
            FROM " . self::TABLE . "
            WHERE user_id = ?
            LIMIT 1";

        return $this->db()->selectOne($sql, [$user_id]) !== null;
    }

    public function add(): bool {

        return $this->db()->execute(
            'INSERT INTO ' . self::TABLE . '
            (
                user_id,
                phone,
                photo,
                country_id,
                province_id,
                address,
                postal_code,
                city
            )
            VALUES
            (
                ?,?,?,?,?,?,?,?
            )',
            [
                $this->user_id,
                $this->phone,
                $this->photo,
                $this->country_id,
                $this->province_id,
                $this->address,
                $this->postal_code,
                $this->city
            ]
        );
    }

    public function update(): bool
    {
        if (empty($this->user_id)) {
            throw new RuntimeException('No se puede actualizar sin user_id.');
        }

        return $this->db()->execute(
            'UPDATE ' . self::TABLE . '
                SET
                    phone = ?,
                    photo = ?,
                    country_id = ?,
                    province_id = ?,
                    address = ?,
                    postal_code = ?,
                    city = ?,
                    updated_at = NOW()
              WHERE user_id = ?',
            [
                $this->phone,
                $this->photo,
                $this->country_id,
                $this->province_id,
                $this->address,
                $this->postal_code,
                $this->city,
                $this->user_id
            ]
        );
    }
    
    public function updatePhoto(?string $photo): bool {

        if (empty($this->user_id)) {
            throw new RuntimeException('No se puede actualizar la imagen sin user_id.');
        }

        return $this->db()->execute(
                        'UPDATE ' . self::TABLE . '
            SET photo = ?,
                updated_at = NOW()
          WHERE user_id = ?',
                        [
                            $photo,
                            $this->user_id
                        ]
                );
    }
    
    public function updateProfileData(): bool {

        if (empty($this->user_id)) {
            throw new RuntimeException('No se puede actualizar sin user_id.');
        }

        $sql = '
        UPDATE ' . self::TABLE . '
        SET
            phone = ?,
            country_id = ?,
            province_id = ?,
            address = ?,
            postal_code = ?,
            city = ?,
            updated_at = NOW()
        WHERE user_id = ?';

        return $this->db()->execute(
                        $sql,
                        [
                            $this->phone,
                            $this->country_id,
                            $this->province_id,
                            $this->address,
                            $this->postal_code,
                            $this->city,
                            $this->user_id
                        ]
                );
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function getCountryId(): ?int
    {
        return $this->country_id;
    }

    public function getProvinceId(): ?int
    {
        return $this->province_id;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getPostalCode(): ?string
    {
        return $this->postal_code;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getCreatedAt(): string
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): string
    {
        return $this->updated_at;
    }


    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function setPhoto(?string $photo): void
    {
        $this->photo = $photo;
    }

    public function setCountryId(?int $country_id): void
    {
        $this->country_id = $country_id;
    }

    public function setProvinceId(?int $province_id): void
    {
        $this->province_id = $province_id;
    }

    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    public function setPostalCode(?string $postal_code): void
    {
        $this->postal_code = $postal_code;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
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
