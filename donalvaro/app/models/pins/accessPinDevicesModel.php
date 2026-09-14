<?php

declare(strict_types=1);

class accessPinDevicesModel extends baseModel {

    protected const TABLE = 'wi_access_pin_devices';
    protected const PRIMARY_KEY = 'id';

    private int $id = 0;
    private int $access_pin_id = 0;
    private int $ttlock_device_id = 0;
    private ?int $ttlock_keyboard_pwd_id = null;
    private ?string $synced_at = null;
    private ?string $error_message = null;
    private int $retry_count = 0;
    private ?string $last_retry_at = null;
    private ?string $next_retry_at = null;
    private string $created_at = '';
    private string $updated_at = '';

    public function findByAccessPinId(
            int $access_pin_id,
            bool $asObject = false
    ): array {

        $sql = '
        SELECT apd.*, td.ttlock_lock_id, td.lock_alias
        FROM ' . self::TABLE . ' apd
        INNER JOIN wi_ttlock_devices td
            ON apd.ttlock_device_id = td.id
        WHERE apd.access_pin_id = ?
        ORDER BY apd.id ASC';

        return $this->db()->select(
                        $sql,
                        [$access_pin_id],
                        $asObject
                );
    }

    public function findByAccessPinAndDevice(
            int $access_pin_id,
            int $ttlock_device_id,
            bool $asObject = false
    ): array|object|null {

        $sql = '
        SELECT *
        FROM ' . self::TABLE . '
        WHERE access_pin_id = ?
          AND ttlock_device_id = ?
        LIMIT 1';

        $valores = [$access_pin_id,$ttlock_device_id];
        return $this->db()->selectOne($sql,$valores,$asObject);
    }

    public function findByTtlockKeyboardPwdId(
            int $ttlock_keyboard_pwd_id,
            bool $asObject = false
    ): array|object|null {

        $sql = '
        SELECT *
        FROM ' . self::TABLE . '
        WHERE ttlock_keyboard_pwd_id = ?
        LIMIT 1';

        return $this->db()->selectOne(
                        $sql,
                        [$ttlock_keyboard_pwd_id],
                        $asObject
                );
    }

    public function findPendingRetries(int $max_retries = 10, bool $asObject = false): array {

        $sql = '
        SELECT
            apd.*,
            ap.facility_id,
            ap.reservation_id,
            ap.pin_code_encrypted,
            ap.valid_from,
            ap.valid_until,
            ap.access_type,
            ap.pin_status_id,
            td.lock_alias
        FROM ' . self::TABLE . ' apd
        INNER JOIN wi_access_pins ap
            ON ap.id = apd.access_pin_id
        INNER JOIN wi_ttlock_devices td
            ON td.id = apd.ttlock_device_id
        WHERE apd.ttlock_keyboard_pwd_id IS NULL
          AND apd.error_message IS NOT NULL
          AND apd.retry_count < ?
          AND (apd.next_retry_at IS NULL OR apd.next_retry_at <= NOW())
          AND ap.valid_until > NOW()
        ORDER BY apd.next_retry_at ASC, apd.id ASC';

        return $this->db()->select(
                        $sql,
                        [$max_retries],
                        $asObject
                );
    }

   

    public function add(): int
    {
        return $this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                access_pin_id,
                ttlock_device_id,
                ttlock_keyboard_pwd_id,
                synced_at,
                error_message
            )
            VALUES
            (
                ?,?,?,?,?
            )',
            [
                $this->access_pin_id,
                $this->ttlock_device_id,
                $this->ttlock_keyboard_pwd_id,
                $this->synced_at,
                $this->error_message
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
                    access_pin_id = ?,
                    ttlock_device_id = ?,
                    ttlock_keyboard_pwd_id = ?,
                    synced_at = ?,
                    error_message = ?,
                    retry_count = ?,
                    last_retry_at = ?,
                    next_retry_at = ?,
                    updated_at = NOW()
              WHERE id = ?',
                        [
                            $this->access_pin_id,
                            $this->ttlock_device_id,
                            $this->ttlock_keyboard_pwd_id,
                            $this->synced_at,
                            $this->error_message,
                            $this->retry_count,
                            $this->last_retry_at,
                            $this->next_retry_at,
                            $this->id
                        ]
                );
    }
    
     public function updateDeletionResult(
            int $id,
            ?int $ttlock_keyboard_pwd_id,
            ?string $error_message
    ): bool {

        $sql = '
        UPDATE ' . self::TABLE . '
        SET
            ttlock_keyboard_pwd_id = ?,
            error_message = ?,
            synced_at = ?,
            updated_at = NOW()
        WHERE id = ?';

        return $this->db()->execute(
                        $sql,
                        [
                            $ttlock_keyboard_pwd_id,
                            $error_message,
                            $error_message === null ? date('Y-m-d H:i:s') : null,
                            $id
                        ]
                );
    }
    
    public function updateRetryResult(
            int $id,
            bool $success,
            ?int $ttlock_keyboard_pwd_id,
            ?string $error_message,
            ?string $next_retry_at
    ): bool {

        $sql = '
        UPDATE ' . self::TABLE . '
        SET
            ttlock_keyboard_pwd_id = ?,
            synced_at = ?,
            error_message = ?,
            retry_count = retry_count + 1,
            last_retry_at = NOW(),
            next_retry_at = ?,
            updated_at = NOW()
        WHERE id = ?';

        return $this->db()->execute(
                        $sql,
                        [
                            $ttlock_keyboard_pwd_id,
                            $success ? date('Y-m-d H:i:s') : null,
                            $error_message,
                            $success ? null : $next_retry_at,
                            $id
                        ]
                );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAccessPinId(): int
    {
        return $this->access_pin_id;
    }

    public function getTtlockDeviceId(): int
    {
        return $this->ttlock_device_id;
    }

    public function getTtlockKeyboardPwdId(): ?int
    {
        return $this->ttlock_keyboard_pwd_id;
    }

    public function getSyncedAt(): ?string
    {
        return $this->synced_at;
    }

    public function getErrorMessage(): ?string
    {
        return $this->error_message;
    }

    public function getCreatedAt(): string {
        return $this->created_at;
    }

    public function getUpdatedAt(): string {
        return $this->updated_at;
    }

    public function getRetryCount(): int {
        return $this->retry_count;
    }

    public function getLastRetryAt(): ?string {
        return $this->last_retry_at;
    }

    public function getNextRetryAt(): ?string {
        return $this->next_retry_at;
    }

    public function setId(int $id): void {
        $this->id = $id;
    }

    public function setAccessPinId(int $access_pin_id): void {
        $this->access_pin_id = $access_pin_id;
    }

    public function setTtlockDeviceId(int $ttlock_device_id): void {
        $this->ttlock_device_id = $ttlock_device_id;
    }

    public function setTtlockKeyboardPwdId(?int $ttlock_keyboard_pwd_id): void {
        $this->ttlock_keyboard_pwd_id = $ttlock_keyboard_pwd_id;
    }

    public function setSyncedAt(?string $synced_at): void {
        $this->synced_at = $synced_at;
    }

    public function setErrorMessage(?string $error_message): void {
        $this->error_message = $error_message;
    }

    public function setCreatedAt(string $created_at): void {
        $this->created_at = $created_at;
    }

    public function setUpdatedAt(string $updated_at): void {
        $this->updated_at = $updated_at;
    }

    public function setRetryCount(int $retry_count): void {
        $this->retry_count = $retry_count;
    }

    public function setLastRetryAt(?string $last_retry_at): void {
        $this->last_retry_at = $last_retry_at;
    }

    public function setNextRetryAt(?string $next_retry_at): void {
        $this->next_retry_at = $next_retry_at;
    }
}
