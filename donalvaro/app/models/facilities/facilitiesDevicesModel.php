<?php

declare(strict_types=1);

class facilitiesDevicesModel extends baseModel {

    protected const TABLE = 'wi_facilities_devices';
    protected const PRIMARY_KEY = 'id';

    private int $id = 0;
    private int $facility_id = 0;
    private int $ttlock_device_id = 0;
    private int $is_primary = 0;
    private string $created_at = '';
    private string $updated_at = '';

    public function findAvailableDevices(bool $asObject = false): array {

        $sql = '
        SELECT 
            td.*
        FROM wi_ttlock_devices td
        LEFT JOIN ' . self::TABLE . ' fd
            ON fd.ttlock_device_id = td.id
        WHERE fd.id IS NULL
          AND td.status = ?
        ORDER BY td.lock_alias ASC';

        return $this->db()->select(
                        $sql,
                        [
                            ttlockDevicesModel::STATUS_ACTIVE
                        ],
                        $asObject
                );
    }

    public function findByFacilityId(int $facility_id, bool $asObject = false): array {

        $sql = '
        SELECT
            fd.*,
            td.lock_alias,
            td.lock_name,
            td.lock_mac,
            td.is_online,
            td.status AS device_status
        FROM ' . self::TABLE . ' fd
        INNER JOIN wi_ttlock_devices td
            ON td.id = fd.ttlock_device_id
        WHERE fd.facility_id = ?
        ORDER BY fd.is_primary DESC, fd.id ASC';

        return $this->db()->select(
                        $sql,
                        [$facility_id],
                        $asObject
                );
    }

    public function findByDeviceId(int $device_id, bool $asObject = false): array|object|null {

        $sql = 'SELECT * FROM ' . self::TABLE . ' WHERE ttlock_device_id = ? LIMIT 1';

        return $this->db()->selectOne($sql, [$device_id], $asObject);
    }

    public function findPrimaryByFacilityId(int $facility_id, bool $asObject = false): array|object|null {

        $sql = '
        SELECT *
        FROM ' . self::TABLE . '
        WHERE facility_id = ?
          AND is_primary = 1
        LIMIT 1';

        return $this->db()->selectOne($sql, [$facility_id], $asObject);
    }

    public function findByFacilityAndDevice(int $facility_id, int $device_id, bool $asObject = false): array|object|null {

        $sql = '
        SELECT *
        FROM ' . self::TABLE . '
        WHERE facility_id = ?
          AND ttlock_device_id = ?
        LIMIT 1';

        return $this->db()->selectOne($sql, [$facility_id, $device_id], $asObject);
    }

    public function deleteById(int $id): bool {

        return $this->db()->execute(
                        'DELETE FROM ' . self::TABLE . ' WHERE id = ?',
                        [$id]
                );
    }

    public function unsetPrimaryByFacilityId(int $facility_id, int $exclude_id): bool {

        $sql = '
        UPDATE ' . self::TABLE . '
        SET is_primary = 0,
            updated_at = NOW()
        WHERE facility_id = ?
          AND is_primary = 1
          AND id <> ?';

        return $this->db()->execute($sql, [$facility_id, $exclude_id]);
    }

    public function setPrimaryById(int $id): bool {

        return $this->db()->execute(
                        'UPDATE ' . self::TABLE . '
         SET is_primary = 1,
             updated_at = NOW()
         WHERE id = ?',
                        [$id]
                );
    }

    public function add(): int {
        return $this->db()->insert(
                        'INSERT INTO ' . self::TABLE . '
            (
                facility_id,
                ttlock_device_id,
                is_primary
            )
            VALUES
            (
                ?,?,?
            )',
                        [
                            $this->facility_id,
                            $this->ttlock_device_id,
                            $this->is_primary
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
                    ttlock_device_id = ?,
                    is_primary = ?,
                    updated_at = NOW()
              WHERE id = ?',
            [
                $this->facility_id,
                $this->ttlock_device_id,
                $this->is_primary,
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

    public function getTtlockDeviceId(): int
    {
        return $this->ttlock_device_id;
    }

    public function getIsPrimary(): int
    {
        return $this->is_primary;
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

    public function setFacilityId(int $facility_id): void
    {
        $this->facility_id = $facility_id;
    }

    public function setTtlockDeviceId(int $ttlock_device_id): void
    {
        $this->ttlock_device_id = $ttlock_device_id;
    }

    public function setIsPrimary(int $is_primary): void
    {
        $this->is_primary = $is_primary;
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
