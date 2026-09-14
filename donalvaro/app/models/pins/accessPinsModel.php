<?php

declare(strict_types=1);

class accessPinsModel extends baseModel
{
    protected const TABLE = 'wi_access_pins';
    protected const PRIMARY_KEY = 'id';
    
    public const PIN_TYPE_RESERVATION = 'RESERVA';
    public const PIN_TYPE_MANUAL = 'MANUAL';
    public const PIN_TYPE_BONUS = 'BONO';
    public const ACCESS_TYPE_TEMPORARY = 'TEMPORAL';
    public const ACCESS_TYPE_PERMANENT = 'PERMANENT';
    public const ACCESS_TYPE_ONE_TIME = 'ONE_TIME';

    private int $id = 0;
    private ?int $reservation_id = null;
    private ?int $user_bonus_id = null;
    private ?int $facility_id = null;
    private string $pin_type = self::PIN_TYPE_RESERVATION;
    private string $access_type = self::ACCESS_TYPE_TEMPORARY;
    private ?string $description = null;
    private string $pin_code_encrypted = '';
    private string $pin_code_hash = '';
    private string $valid_from = '';
    private string $valid_until = '';
    private int $is_permanent = 0;
    private int $pin_status_id = 2;
    private string $generated_at = '';
    private ?string $revoked_at = null;
    private string $created_at = '';
    private string $updated_at = '';
    
    public function findAll(bool $asObject = false): array|object|null {

        $sql = '
        SELECT 
            ap.*,
            ps.name AS pin_status_code,
            r.reference,
            f.name AS facility_name
        FROM ' . self::TABLE . ' ap
        LEFT JOIN wi_pin_statuses ps
            ON ap.pin_status_id = ps.id
        LEFT JOIN wi_reservations r
            ON ap.reservation_id = r.id
        LEFT JOIN wi_facilities f
            ON ap.facility_id = f.id
        WHERE ap.revoked_at IS NULL
        ORDER BY ap.id DESC';

        $valores = [];

        return $this->db()->select($sql, $valores, $asObject);
    }

    /**
     * Busca un PIN por dispositivo y hash.
     *
     * @param int $ttlock_device_id
     * @param string $pin_code_hash
     * @param bool $asObject
     *
     * @return array|object|null
     */
    public function findByDeviceAndPinHash(int $ttlock_device_id, string $pin_code_hash, bool $asObject = false): array|object|null {

        $sql = '
    SELECT ap.*
    FROM ' . self::TABLE . ' ap
    INNER JOIN wi_access_pin_devices apd
        ON apd.access_pin_id = ap.id
    WHERE apd.ttlock_device_id = ?
      AND ap.pin_code_hash = ?
    LIMIT 1';

        return $this->db()->selectOne(
                        $sql,
                        [
                            $ttlock_device_id,
                            $pin_code_hash
                        ],
                        $asObject
                );
    }

    public function findActiveByDeviceAndPinHash(int $ttlock_device_id, string $pin_code_hash, bool $asObject = false): array|object|null {

        $sql = '
        SELECT ap.*
        FROM ' . self::TABLE . ' ap
        INNER JOIN wi_access_pin_devices apd ON apd.access_pin_id = ap.id
        WHERE apd.ttlock_device_id = ?
          AND ap.pin_code_hash = ?
          AND ap.pin_status_id IN (1, 2)
          AND ap.revoked_at IS NULL
        LIMIT 1';

        return $this->db()->selectOne($sql, [$ttlock_device_id, $pin_code_hash], $asObject);
    }

    public function findActiveByDevicesAndPinHash(array $device_ids, string $pin_code_hash, bool $asObject = false): array|object|null {

        if (empty($device_ids) || $pin_code_hash === '') {
            return null;
        }

        $device_ids = array_values(array_unique(array_map('intval', $device_ids)));
        $placeholders = implode(',', array_fill(0, count($device_ids), '?'));

        $sql = '
        SELECT ap.*
        FROM ' . self::TABLE . ' ap
        INNER JOIN wi_access_pin_devices apd
            ON apd.access_pin_id = ap.id
        WHERE ap.pin_code_hash = ?
          AND ap.pin_status_id IN (1, 2)
          AND ap.revoked_at IS NULL
          AND apd.ttlock_device_id IN (' . $placeholders . ')
        LIMIT 1
    ';

        $params = [$pin_code_hash];

        foreach ($device_ids as $device_id) {
            $params[] = $device_id;
        }

        return $this->db()->selectOne($sql, $params, $asObject);
    }
    
    /**
     * Obtiene el PIN asociado a una reserva.
     *
     * @param int $reservation_id
     * @param bool $asObject
     *
     * @return array|object|null
     */
    public function findByReservationId(int $reservation_id, bool $asObject = false): array|object|null {

        $sql = '
        SELECT *
        FROM ' . self::TABLE . '
        WHERE reservation_id = ?
        ORDER BY id DESC
        LIMIT 1';

        return $this->db()->selectOne(
                        $sql,
                        [$reservation_id],
                        $asObject
                );
    }
    
    public function findByFilters(string $where = '', array $params = [], bool $asObject = false): array {

        $sql = '
        SELECT
            ap.*,
            ps.name AS pin_status_code,
            r.reference,
            f.name AS facility_name
        FROM ' . self::TABLE . ' ap
        LEFT JOIN wi_pin_statuses ps
            ON ps.id = ap.pin_status_id
        LEFT JOIN wi_reservations r
            ON r.id = ap.reservation_id
        LEFT JOIN wi_facilities f
            ON f.id = ap.facility_id
        ' . $where . '
        ORDER BY ap.id DESC';

        return $this->db()->select($sql, $params, $asObject);
    }

    public function findEditDataById(int $pin_id, bool $asObject = true): array|object|null {

        $sql = '
        SELECT
            ap.*,
            ps.name AS pin_status_name,
            ps.code AS pin_status_code,
            f.name AS facility_name,

            r.reference AS reservation_reference,
            r.start_at AS reservation_start_at,
            r.end_at AS reservation_end_at,
            r.people_count AS reservation_people_count,

            rs.name AS reservation_status_name,
            rs.code AS reservation_status_code,

            u.id AS reservation_user_id,
            CONCAT(u.first_name, " ", u.last_name) AS reservation_user_name

        FROM ' . self::TABLE . ' ap

        LEFT JOIN wi_pin_statuses ps
            ON ps.id = ap.pin_status_id

        LEFT JOIN wi_facilities f
            ON f.id = ap.facility_id

        LEFT JOIN wi_reservations r
            ON r.id = ap.reservation_id

        LEFT JOIN wi_reservation_statuses rs
            ON rs.id = r.reservation_status_id

        LEFT JOIN wi_users u
            ON u.id = r.user_id

        WHERE ap.id = ?
        LIMIT 1';

        return $this->db()->selectOne($sql, [$pin_id], $asObject);
    }
    
    public function findByUserBonusId(int $user_bonus_id, bool $asObject = false): array {

        return $this->db()->select(
                        'SELECT *
         FROM ' . self::TABLE . '
         WHERE user_bonus_id = ?
         ORDER BY id ASC',
                        [$user_bonus_id],
                        $asObject
                );
    }
    
    public function findExpiredBonusUserIds(bool $asObject = false): array {

        return $this->db()->select(
                        'SELECT DISTINCT user_bonus_id
             FROM ' . self::TABLE . '
             WHERE pin_type = ?
               AND access_type = ?
               AND user_bonus_id IS NOT NULL
               AND valid_until < NOW()
               AND revoked_at IS NULL
             ORDER BY user_bonus_id ASC',
                        [
                            self::PIN_TYPE_BONUS,
                            self::ACCESS_TYPE_TEMPORARY
                        ],
                        $asObject
                );
    }
    
    public function findPendingSync(bool $asObject = false): array {

        return $this->db()->select(
                        'SELECT *
             FROM ' . self::TABLE . '
             WHERE pin_status_id = ?
               AND revoked_at IS NULL
             ORDER BY id ASC',
                        [5],
                        $asObject
                );
    }

    public function revokeById(int $id, int $pin_status_id): bool {

        $sql = '
        UPDATE ' . self::TABLE . '
        SET
            pin_status_id = ?,
            revoked_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
          AND revoked_at IS NULL';

        return $this->db()->execute($sql, [$pin_status_id, $id]);
    }

    public function add(): int {
        return $this->db()->insert(
                        'INSERT INTO ' . self::TABLE . '
        (
            reservation_id,
            user_bonus_id,
            facility_id,
            pin_type,
            access_type,
            description,
            pin_code_encrypted,
            pin_code_hash,
            valid_from,
            valid_until,
            is_permanent,
            pin_status_id,
            revoked_at
        )
        VALUES
        (
            ?,?,?,?,?,?,?,?,?,?,?,?,?
        )',
                        [
                            $this->reservation_id,
                            $this->user_bonus_id,
                            $this->facility_id,
                            $this->pin_type,
                            $this->access_type,
                            $this->description,
                            $this->pin_code_encrypted,
                            $this->pin_code_hash,
                            $this->valid_from,
                            $this->valid_until,
                            $this->is_permanent,
                            $this->pin_status_id,
                            $this->revoked_at
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
            reservation_id = ?,
            user_bonus_id = ?,
            facility_id = ?,
            pin_type = ?,
            access_type = ?,
            description = ?,
            pin_code_encrypted = ?,
            pin_code_hash = ?,
            valid_from = ?,
            valid_until = ?,
            is_permanent = ?,
            pin_status_id = ?,
            revoked_at = ?,
            updated_at = NOW()
        WHERE id = ?',
                        [
                            $this->reservation_id,
                            $this->user_bonus_id,
                            $this->facility_id,
                            $this->pin_type,
                            $this->access_type,
                            $this->description,
                            $this->pin_code_encrypted,
                            $this->pin_code_hash,
                            $this->valid_from,
                            $this->valid_until,
                            $this->is_permanent,
                            $this->pin_status_id,
                            $this->revoked_at,
                            $this->id
                        ]
                );
    }

    public function updateStatusById(int $id, int $pin_status_id): bool {

        $sql = '
        UPDATE ' . self::TABLE . '
        SET
            pin_status_id = ?,
            updated_at = NOW()
        WHERE id = ?';

        return $this->db()->execute(
                        $sql,
                        [
                            $pin_status_id,
                            $id
                        ]
                );
    }

    public function getId(): int {
        return $this->id;
    }

    public function getReservationId(): ?int {
        return $this->reservation_id;
    }

    public function getUserBonusId(): ?int {
        return $this->user_bonus_id;
    }

    public function getFacilityId(): ?int {
        return $this->facility_id;
    }

    public function getPinType(): string {
        return $this->pin_type;
    }

    public function getAccessType(): string {
        return $this->access_type;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function getPinCodeEncrypted(): string {
        return $this->pin_code_encrypted;
    }

    public function getPinCodeHash(): string {
        return $this->pin_code_hash;
    }

    public function getValidFrom(): string {
        return $this->valid_from;
    }

    public function getValidUntil(): string {
        return $this->valid_until;
    }

    public function getIsPermanent(): int {
        return $this->is_permanent;
    }

    public function getPinStatusId(): int {
        return $this->pin_status_id;
    }

    public function getGeneratedAt(): string {
        return $this->generated_at;
    }

    public function getRevokedAt(): ?string {
        return $this->revoked_at;
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

    public function setReservationId(?int $reservation_id): void {
        $this->reservation_id = $reservation_id;
    }

    public function setUserBonusId(?int $user_bonus_id): void {
        $this->user_bonus_id = $user_bonus_id;
    }

    public function setFacilityId(?int $facility_id): void {
        $this->facility_id = $facility_id;
    }

    public function setPinType(string $pin_type): void
    {
        $this->pin_type = $pin_type;
    }

    public function setAccessType(string $access_type): void
    {
        $this->access_type = $access_type;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function setPinCodeEncrypted(string $pin_code_encrypted): void
    {
        $this->pin_code_encrypted = $pin_code_encrypted;
    }

    public function setPinCodeHash(string $pin_code_hash): void
    {
        $this->pin_code_hash = $pin_code_hash;
    }

    public function setValidFrom(string $valid_from): void
    {
        $this->valid_from = $valid_from;
    }

    public function setValidUntil(string $valid_until): void
    {
        $this->valid_until = $valid_until;
    }

    public function setIsPermanent(int $is_permanent): void
    {
        $this->is_permanent = $is_permanent;
    }

    public function setPinStatusId(int $pin_status_id): void
    {
        $this->pin_status_id = $pin_status_id;
    }

    public function setGeneratedAt(string $generated_at): void
    {
        $this->generated_at = $generated_at;
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
