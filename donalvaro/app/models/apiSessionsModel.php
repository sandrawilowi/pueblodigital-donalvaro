<?php

declare(strict_types=1);

class apiSessionsModel extends baseModel
{
    protected const TABLE = 'wi_api_sessions';
    protected const PRIMARY_KEY = 'id';

    private int $id = 0;
    private int $user_id = 0;
    private string $token_hash = '';
    private ?string $device_name = null;
    private ?string $device_id = null;
    private ?string $device_os = null;
    private ?string $app_version = null;
    private ?string $ip_address = null;
    private ?string $user_agent = null;
    private ?string $last_used_at = null;
    private string $expires_at = '';
    private ?string $revoked_at = null;
    private string $created_at = '';
    private string $updated_at = '';


    public function add(): int
    {
        return $this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                user_id,
                token_hash,
                device_name,
                device_id,
                device_os,
                app_version,
                ip_address,
                user_agent,
                last_used_at,
                expires_at,
                revoked_at
            )
            VALUES
            (
                ?,?,?,?,?,?,?,?,?,?,?
            )',
            [
                $this->user_id,
                $this->token_hash,
                $this->device_name,
                $this->device_id,
                $this->device_os,
                $this->app_version,
                $this->ip_address,
                $this->user_agent,
                $this->last_used_at,
                $this->expires_at,
                $this->revoked_at
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
                    user_id = ?,
                    token_hash = ?,
                    device_name = ?,
                    device_id = ?,
                    device_os = ?,
                    app_version = ?,
                    ip_address = ?,
                    user_agent = ?,
                    last_used_at = ?,
                    expires_at = ?,
                    revoked_at = ?,
                    updated_at = NOW()
              WHERE id = ?',
            [
                $this->user_id,
                $this->token_hash,
                $this->device_name,
                $this->device_id,
                $this->device_os,
                $this->app_version,
                $this->ip_address,
                $this->user_agent,
                $this->last_used_at,
                $this->expires_at,
                $this->revoked_at,
                $this->id
            ]
        );
    }


    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function getTokenHash(): string
    {
        return $this->token_hash;
    }

    public function getDeviceName(): ?string
    {
        return $this->device_name;
    }

    public function getDeviceId(): ?string
    {
        return $this->device_id;
    }

    public function getDeviceOs(): ?string
    {
        return $this->device_os;
    }

    public function getAppVersion(): ?string
    {
        return $this->app_version;
    }

    public function getIpAddress(): ?string
    {
        return $this->ip_address;
    }

    public function getUserAgent(): ?string
    {
        return $this->user_agent;
    }

    public function getLastUsedAt(): ?string
    {
        return $this->last_used_at;
    }

    public function getExpiresAt(): string
    {
        return $this->expires_at;
    }

    public function getRevokedAt(): ?string
    {
        return $this->revoked_at;
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

    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function setTokenHash(string $token_hash): void
    {
        $this->token_hash = $token_hash;
    }

    public function setDeviceName(?string $device_name): void
    {
        $this->device_name = $device_name;
    }

    public function setDeviceId(?string $device_id): void
    {
        $this->device_id = $device_id;
    }

    public function setDeviceOs(?string $device_os): void
    {
        $this->device_os = $device_os;
    }

    public function setAppVersion(?string $app_version): void
    {
        $this->app_version = $app_version;
    }

    public function setIpAddress(?string $ip_address): void
    {
        $this->ip_address = $ip_address;
    }

    public function setUserAgent(?string $user_agent): void
    {
        $this->user_agent = $user_agent;
    }

    public function setLastUsedAt(?string $last_used_at): void
    {
        $this->last_used_at = $last_used_at;
    }

    public function setExpiresAt(string $expires_at): void
    {
        $this->expires_at = $expires_at;
    }

    public function setRevokedAt(?string $revoked_at): void
    {
        $this->revoked_at = $revoked_at;
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
