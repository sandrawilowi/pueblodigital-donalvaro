<?php

declare(strict_types=1);

class ttlockGatewaysModel extends baseModel {

    protected const TABLE = 'wi_ttlock_gateways';
    protected const PRIMARY_KEY = 'id';
    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_BLOCKED = 2;
    public const STATUS_DELETED = 3;

    private int $id = 0;
    private int $ttlock_gateway_id = 0;
    private string $gateway_name = '';
    private string $gateway_mac = '';
    private ?string $network_mac = null;
    private ?string $serial_number = null;
    private ?int $gateway_version = null;
    private int $device_num = 0;
    private int $lock_num = 0;
    private int $electric_meter_count = 0;
    private int $water_meter_count = 0;
    private int $lift_control_count = 0;
    private int $is_online = 0;
    private int $status = 1;
    private ?string $last_sync_at = null;
    private string $created_at = '';
    private string $updated_at = '';

    public function findByTtlockGatewayId(int $ttlock_gateway_id): ?array {
        return $this->db()->selectOne(
                        'SELECT *
           FROM ' . self::TABLE . '
          WHERE ttlock_gateway_id = ?
          LIMIT 1',
                        [$ttlock_gateway_id]
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
     * Obtiene todos los gateways para el proceso de sincronización.
     *
     * @return array
     */
    public function findAllForSync(): array {
        
        $sql = 'SELECT * FROM ' . self::TABLE . ' ORDER BY id ASC';
        
        return $this->db()->select($sql);
    }
    
    public function findLocksByGatewayId(int $id, bool $asObject=false): ?array{
        
        $sql = 'SELECT d.id, d.lock_alias FROM wi_ttlock_devices d WHERE gateway_id = ?';
        $valores = [$id];
        
        return $this->db()->select($sql, $valores, $asObject);
        
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
        
        $sql = 'SELECT *
         FROM ' . self::TABLE . '
         ' . $where . '
         ORDER BY gateway_name ASC, id ASC';
        
        return $this->db()->select($sql,$params,$asObject);
    }

    /**
     * Desactiva un gateway.
     *
     * @param int $id
     * @param string $last_sync_at
     *
     * @return bool
     */
    public function disableById(int $id, string $last_sync_at): bool {
        
        $sql = 'UPDATE ' . self::TABLE . '
            SET status = ?,
                last_sync_at = ?,
                updated_at = NOW()
          WHERE id = ?';
        
        $valores = [self::STATUS_INACTIVE, $last_sync_at, $id];
        
        return $this->db()->execute($sql, $valores);
    }

    public function add(): int {
        return $this->db()->insert(
                        'INSERT INTO ' . self::TABLE . '
            (
                ttlock_gateway_id,
                gateway_name,
                gateway_mac,
                network_mac,
                serial_number,
                gateway_version,
                device_num,
                lock_num,
                electric_meter_count,
                water_meter_count,
                lift_control_count,
                is_online,
                status,
                last_sync_at
            )
            VALUES
            (
                ?,?,?,?,?,?,?,?,?,?,?,?,?,?
            )',
                        [
                            $this->ttlock_gateway_id,
                            $this->gateway_name,
                            $this->gateway_mac,
                            $this->network_mac,
                            $this->serial_number,
                            $this->gateway_version,
                            $this->device_num,
                            $this->lock_num,
                            $this->electric_meter_count,
                            $this->water_meter_count,
                            $this->lift_control_count,
                            $this->is_online,
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
                    ttlock_gateway_id = ?,
                    gateway_name = ?,
                    gateway_mac = ?,
                    network_mac = ?,
                    serial_number = ?,
                    gateway_version = ?,
                    device_num = ?,
                    lock_num = ?,
                    electric_meter_count = ?,
                    water_meter_count = ?,
                    lift_control_count = ?,
                    is_online = ?,
                    status = ?,
                    last_sync_at = ?,
                    updated_at = NOW()
              WHERE id = ?',
                        [
                            $this->ttlock_gateway_id,
                            $this->gateway_name,
                            $this->gateway_mac,
                            $this->network_mac,
                            $this->serial_number,
                            $this->gateway_version,
                            $this->device_num,
                            $this->lock_num,
                            $this->electric_meter_count,
                            $this->water_meter_count,
                            $this->lift_control_count,
                            $this->is_online,
                            $this->status,
                            $this->last_sync_at,
                            $this->id
                        ]
                );
    }

    public function updateStatusById(int $id, int $status): bool {
        
        $sql = 'UPDATE ' . self::TABLE . '
            SET
                status = ?,
                updated_at = NOW()
          WHERE id = ?';
        
        $valores = [$status, $id];

        return $this->db()->execute($sql, $valores);
    }

    public function updateGatewayName(int $id, string $gateway_name): bool {
        
        $sql = 'UPDATE ' . self::TABLE . ' SET gateway_name = ?, updated_at = NOW() WHERE id = ?';
        $valores = [$gateway_name, $id];
        
        return $this->db()->execute($sql, $valores);
    }
    
    /**
     * Actualiza el estado de conexión de un gateway.
     *
     * @param int $id
     * @param int $is_online
     * @param string $last_sync_at
     *
     * @return bool
     */
    public function updateOnlineStatus(int $id, int $is_online, string $last_sync_at): bool {

        $sql = 'UPDATE ' . self::TABLE . '
        SET is_online = ?,
            last_sync_at = ?,
            updated_at = NOW()
        WHERE id = ?';

        $valores = [$is_online, $last_sync_at, $id];

        return $this->db()->execute($sql, $valores);
    }

    public function getId(): int {
        return $this->id;
    }

    public function getTtlockGatewayId(): int {
        return $this->ttlock_gateway_id;
    }

    public function getGatewayName(): string {
        return $this->gateway_name;
    }

    public function getGatewayMac(): string {
        return $this->gateway_mac;
    }

    public function getNetworkMac(): ?string {
        return $this->network_mac;
    }

    public function getSerialNumber(): ?string {
        return $this->serial_number;
    }

    public function getGatewayVersion(): ?int {
        return $this->gateway_version;
    }

    public function getDeviceNum(): int {
        return $this->device_num;
    }

    public function getLockNum(): int {
        return $this->lock_num;
    }

    public function getElectricMeterCount(): int {
        return $this->electric_meter_count;
    }

    public function getWaterMeterCount(): int {
        return $this->water_meter_count;
    }

    public function getLiftControlCount(): int {
        return $this->lift_control_count;
    }

    public function getIsOnline(): int {
        return $this->is_online;
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

    public function setTtlockGatewayId(int $ttlock_gateway_id): void {
        $this->ttlock_gateway_id = $ttlock_gateway_id;
    }

    public function setGatewayName(string $gateway_name): void {
        $this->gateway_name = $gateway_name;
    }

    public function setGatewayMac(string $gateway_mac): void {
        $this->gateway_mac = $gateway_mac;
    }

    public function setNetworkMac(?string $network_mac): void {
        $this->network_mac = $network_mac;
    }

    public function setSerialNumber(?string $serial_number): void {
        $this->serial_number = $serial_number;
    }

    public function setGatewayVersion(?int $gateway_version): void {
        $this->gateway_version = $gateway_version;
    }

    public function setDeviceNum(int $device_num): void {
        $this->device_num = $device_num;
    }

    public function setLockNum(int $lock_num): void {
        $this->lock_num = $lock_num;
    }

    public function setElectricMeterCount(int $electric_meter_count): void {
        $this->electric_meter_count = $electric_meter_count;
    }

    public function setWaterMeterCount(int $water_meter_count): void {
        $this->water_meter_count = $water_meter_count;
    }

    public function setLiftControlCount(int $lift_control_count): void {
        $this->lift_control_count = $lift_control_count;
    }

    public function setIsOnline(int $is_online): void {
        $this->is_online = $is_online;
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
