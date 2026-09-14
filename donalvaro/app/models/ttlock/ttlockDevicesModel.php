<?php

declare(strict_types=1);

class ttlockDevicesModel extends baseModel {

    protected const TABLE = 'wi_ttlock_devices';
    protected const PRIMARY_KEY = 'id';
    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_BLOCKED = 2;
    public const STATUS_DELETED = 3;

    private int $id = 0;
    private ?int $gateway_id = null;
    private int $ttlock_lock_id = 0;
    private ?string $lock_alias = null;
    private ?string $lock_name = null;
    private string $lock_mac = '';
    private ?int $electric_quantity = null;
    private int $is_online = 0;
    private int $has_gateway = 0;
    private ?int $passage_mode = null;
    private ?int $timezone_raw_offset = null;
    private ?int $keyboard_pwd_version = null;
    private ?string $feature_value = null;
    private ?string $wireless_keypad_feature_value = null;
    private ?string $bind_date = null;
    private ?string $electric_quantity_update_date = null;
    private int $status = 1;
    private ?string $last_sync_at = null;
    private string $created_at = '';
    private string $updated_at = '';

    public function findByTtlockLockId(int $ttlock_lock_id): ?array {
        return $this->db()->selectOne(
                        'SELECT *
           FROM ' . self::TABLE . '
          WHERE ttlock_lock_id = ?
          LIMIT 1',
                        [$ttlock_lock_id]
                );
    }

    public function findAllForSync(): array {
        return $this->db()->select(
                        'SELECT *
           FROM ' . self::TABLE . '
          ORDER BY id ASC'
                );
    }

    public function findAllActive(bool $asObject=false): array {
        return $this->db()->select(
                        'SELECT *
           FROM ' . self::TABLE . ' WHERE status=1
          ORDER BY id DESC',
                        [],
                        $asObject
                );
    }

    /**
     * Obtiene las cerraduras aplicando los filtros indicados.
     *
     * @param string $where
     * @param array $params
     *
     * @return array
     */
    public function findByFilters(string $where = '', array $params = [], bool $asObject = false): array {
        return $this->db()->select(
                        'SELECT
            id,
            gateway_id,
            ttlock_lock_id,
            lock_alias,
            lock_name,
            lock_mac,
            electric_quantity,
            is_online,
            has_gateway,
            passage_mode,
            timezone_raw_offset,
            keyboard_pwd_version,
            feature_value,
            wireless_keypad_feature_value,
            bind_date,
            electric_quantity_update_date,
            status,
            last_sync_at,
            created_at,
            updated_at
         FROM ' . self::TABLE . '
         ' . $where . '
         ORDER BY lock_alias ASC, id ASC',
                        $params,
                        $asObject
                );
    }

    public function findByIdWithGateway(int $id, bool $asObject = false): array|object|null {        
        
        $sql = 'SELECT d.*, g.gateway_name FROM ' . self::TABLE . ' d
         LEFT JOIN wi_ttlock_gateways g
                ON g.id = d.gateway_id
         WHERE d.id = ?
         LIMIT 1';
        
        $valores = [$id];
        
        return $this->db()->selectOne($sql, $valores, $asObject);
    }

    public function add(): int {
        return $this->db()->insert(
                        'INSERT INTO ' . self::TABLE . '
            (
                gateway_id,
                ttlock_lock_id,
                lock_alias,
                lock_name,
                lock_mac,
                electric_quantity,
                is_online,
                has_gateway,
                passage_mode,
                timezone_raw_offset,
                keyboard_pwd_version,
                feature_value,
                wireless_keypad_feature_value,
                bind_date,
                electric_quantity_update_date,
                status,
                last_sync_at
            )
            VALUES
            (
                ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
            )',
                        [
                            $this->gateway_id,
                            $this->ttlock_lock_id,
                            $this->lock_alias,
                            $this->lock_name,
                            $this->lock_mac,
                            $this->electric_quantity,
                            $this->is_online,
                            $this->has_gateway,
                            $this->passage_mode,
                            $this->timezone_raw_offset,
                            $this->keyboard_pwd_version,
                            $this->feature_value,
                            $this->wireless_keypad_feature_value,
                            $this->bind_date,
                            $this->electric_quantity_update_date,
                            $this->status,
                            $this->last_sync_at
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
                    gateway_id = ?,
                    ttlock_lock_id = ?,
                    lock_alias = ?,
                    lock_name = ?,
                    lock_mac = ?,
                    electric_quantity = ?,
                    is_online = ?,
                    has_gateway = ?,
                    passage_mode = ?,
                    timezone_raw_offset = ?,
                    keyboard_pwd_version = ?,
                    feature_value = ?,
                    wireless_keypad_feature_value = ?,
                    bind_date = ?,
                    electric_quantity_update_date = ?,
                    status = ?,
                    last_sync_at = ?,
                    updated_at = NOW()
              WHERE id = ?',
                        [
                            $this->gateway_id,
                            $this->ttlock_lock_id,
                            $this->lock_alias,
                            $this->lock_name,
                            $this->lock_mac,
                            $this->electric_quantity,
                            $this->is_online,
                            $this->has_gateway,
                            $this->passage_mode,
                            $this->timezone_raw_offset,
                            $this->keyboard_pwd_version,
                            $this->feature_value,
                            $this->wireless_keypad_feature_value,
                            $this->bind_date,
                            $this->electric_quantity_update_date,
                            $this->status,
                            $this->last_sync_at,
                            $this->id
                        ]
                );
    }

    /**
     * Actualiza el alias local de una cerradura.
     *
     * @param int $id
     * @param string $lock_alias
     *
     * @return bool
     */
    public function updateLockAlias(int $id, string $lock_alias): bool {
        $sql = '
        UPDATE wi_ttlock_devices
        SET
            lock_alias = ?,
            updated_at = NOW()
        WHERE id = ?
    ';

        return $this->db->execute(
                        $sql,
                        [
                            $lock_alias,
                            $id,
                        ]
                );
    }
    
    public function updateStatusById(int $id,int $status): bool {

        $sql = 'UPDATE ' . self::TABLE . '
            SET
                status = ?,
                updated_at = NOW()
          WHERE id = ?';
        
        $valores = [$status,$id];
        
        return $this->db()->execute($sql,$valores);
    }

    public function updateGatewayId(int $id, ?int $gateway_id): bool {
        
        $sql ='UPDATE ' . self::TABLE . '
            SET gateway_id = ?,
                updated_at = NOW()
          WHERE id = ?';
        
        $valores = [$gateway_id, $id];
        
        return $this->db()->execute($sql, $valores);
    }

    public function disableById(int $id, string $last_sync_at): bool {
        return $this->db()->execute(
                        'UPDATE ' . self::TABLE . '
            SET status = ?,
                last_sync_at = ?,
                updated_at = NOW()
          WHERE id = ?',
                        [
                            self::STATUS_INACTIVE,
                            $last_sync_at,
                            $id
                        ]
                );
    }

    public function getId(): int {
        return $this->id;
    }

    public function getGatewayId(): ?int {
        return $this->gateway_id;
    }

    public function getTtlockLockId(): int {
        return $this->ttlock_lock_id;
    }

    public function getLockAlias(): ?string {
        return $this->lock_alias;
    }

    public function getLockName(): ?string {
        return $this->lock_name;
    }

    public function getLockMac(): string {
        return $this->lock_mac;
    }

    public function getElectricQuantity(): ?int {
        return $this->electric_quantity;
    }

    public function getIsOnline(): int {
        return $this->is_online;
    }

    public function getHasGateway(): int {
        return $this->has_gateway;
    }

    public function getPassageMode(): ?int {
        return $this->passage_mode;
    }

    public function getTimezoneRawOffset(): ?int {
        return $this->timezone_raw_offset;
    }

    public function getKeyboardPwdVersion(): ?int {
        return $this->keyboard_pwd_version;
    }

    public function getFeatureValue(): ?string {
        return $this->feature_value;
    }

    public function getWirelessKeypadFeatureValue(): ?string {
        return $this->wireless_keypad_feature_value;
    }

    public function getBindDate(): ?string {
        return $this->bind_date;
    }

    public function getElectricQuantityUpdateDate(): ?string {
        return $this->electric_quantity_update_date;
    }

    public function getStatus(): int {
        return $this->status;
    }

    public function getLastSyncAt(): ?string {
        return $this->last_sync_at;
    }

    public function getCreatedAt(): string {
        return $this->created_at;
    }

    public function getUpdatedAt(): string {
        return $this->updated_at;
    }

    public function setId(int $id): void {
        $this->id = $id;
    }

    public function setGatewayId(?int $gateway_id): void {
        $this->gateway_id = $gateway_id;
    }

    public function setTtlockLockId(int $ttlock_lock_id): void {
        $this->ttlock_lock_id = $ttlock_lock_id;
    }

    public function setLockAlias(?string $lock_alias): void {
        $this->lock_alias = $lock_alias;
    }

    public function setLockName(?string $lock_name): void {
        $this->lock_name = $lock_name;
    }

    public function setLockMac(string $lock_mac): void {
        $this->lock_mac = $lock_mac;
    }

    public function setElectricQuantity(?int $electric_quantity): void {
        $this->electric_quantity = $electric_quantity;
    }

    public function setIsOnline(int $is_online): void {
        $this->is_online = $is_online;
    }

    public function setHasGateway(int $has_gateway): void {
        $this->has_gateway = $has_gateway;
    }

    public function setPassageMode(?int $passage_mode): void {
        $this->passage_mode = $passage_mode;
    }

    public function setTimezoneRawOffset(?int $timezone_raw_offset): void {
        $this->timezone_raw_offset = $timezone_raw_offset;
    }

    public function setKeyboardPwdVersion(?int $keyboard_pwd_version): void {
        $this->keyboard_pwd_version = $keyboard_pwd_version;
    }

    public function setFeatureValue(?string $feature_value): void {
        $this->feature_value = $feature_value;
    }

    public function setWirelessKeypadFeatureValue(?string $wireless_keypad_feature_value): void {
        $this->wireless_keypad_feature_value = $wireless_keypad_feature_value;
    }

    public function setBindDate(?string $bind_date): void {
        $this->bind_date = $bind_date;
    }

    public function setElectricQuantityUpdateDate(?string $electric_quantity_update_date): void {
        $this->electric_quantity_update_date = $electric_quantity_update_date;
    }

    public function setStatus(int $status): void {
        $this->status = $status;
    }

    public function setLastSyncAt(?string $last_sync_at): void {
        $this->last_sync_at = $last_sync_at;
    }

    public function setCreatedAt(string $created_at): void {
        $this->created_at = $created_at;
    }

    public function setUpdatedAt(string $updated_at): void {
        $this->updated_at = $updated_at;
    }
}
