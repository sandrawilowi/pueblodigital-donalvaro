<?php

declare(strict_types=1);

class ttlockAccessLogsModel extends baseModel
{
    protected const TABLE = 'wi_ttlock_access_logs';
    protected const PRIMARY_KEY = 'id';

    private int $id = 0;
    private int $ttlock_device_id = 0;
    private ?int $facility_id = null;
    private ?int $access_pin_id = null;
    private ?int $ttlock_record_id = null;
    private ?int $ttlock_record_type_fromlock = null;
    private ?string $event_type = null;
    private ?int $ttlock_record_type = null;
    private int $access_granted = 0;
    private ?string $failure_reason = null;
    private string $event_at = '';
    private string $received_at = '';
    private ?string $payload_json = null;
    private string $created_at = '';

    /**
     * Busca un registro por su identificador de TTLock.
     *
     * @param int $ttlock_record_id
     * @param bool $asObject
     *
     * @return array|object|null
     */
    public function findByTtlockRecordId(int $ttlock_record_id,bool $asObject = false): array|object|null {

        $sql = '
        SELECT *
        FROM ' . self::TABLE . '
        WHERE ttlock_record_id = ?
        LIMIT 1';

        return $this->db()->selectOne($sql,[$ttlock_record_id],$asObject);
    }
    
    /**
     * Busca un evento por cerradura, tipo original y fecha.
     *
     * @param int $ttlock_device_id
     * @param int $ttlock_record_type_fromlock
     * @param string $event_at
     * @param bool $asObject
     *
     * @return array|object|null
     */
    public function findEvent(int $ttlock_device_id, int $ttlock_record_type_fromlock, string $event_at, bool $asObject = false): array|object|null {

        $sql = '
        SELECT *
        FROM ' . self::TABLE . '
        WHERE ttlock_device_id = ?
        AND ttlock_record_type_fromlock = ?
        AND event_at = ?
        LIMIT 1';
        
        $valores = [$ttlock_device_id, $ttlock_record_type_fromlock, $event_at];

        return $this->db()->selectOne($sql, $valores, $asObject);
    }

    public function findAllWithDetails(bool $asObject = false): array {

        $sql = '
        SELECT
            al.*,

            d.lock_alias,

            f.name AS facility_name,

            ap.pin_type,
            ap.description AS pin_description,
            ap.pin_code_encrypted,

            r.id AS reservation_id,
            r.reference AS reservation_reference,

            u.id AS user_id,
            CONCAT(u.first_name, " ", u.last_name) AS user_name

        FROM ' . self::TABLE . ' al

        LEFT JOIN wi_ttlock_devices d
            ON d.id = al.ttlock_device_id

        LEFT JOIN wi_facilities f
            ON f.id = al.facility_id

        LEFT JOIN wi_access_pins ap
            ON ap.id = al.access_pin_id

        LEFT JOIN wi_reservations r
            ON r.id = ap.reservation_id
            
        LEFT JOIN wi_users_bonuses ub
            ON ub.id = ap.user_bonus_id

        LEFT JOIN wi_users u
            ON u.id = COALESCE(r.user_id, ub.user_id)

        ORDER BY al.event_at DESC, al.id DESC';

        return $this->db()->select($sql, [], $asObject);
    }
    
    public function findByFilters(string $where = '', array $params = [], bool $asObject = false): array {

        $sql = '
        SELECT
            al.*,

            d.lock_alias,

            f.name AS facility_name,

            ap.pin_type,
            ap.description AS pin_description,
            ap.pin_code_encrypted,

            r.id AS reservation_id,
            r.reference AS reservation_reference,

            u.id AS user_id,
            CONCAT(u.first_name, " ", u.last_name) AS user_name

        FROM ' . self::TABLE . ' al

        LEFT JOIN wi_ttlock_devices d
            ON d.id = al.ttlock_device_id

        LEFT JOIN wi_facilities f
            ON f.id = al.facility_id

        LEFT JOIN wi_access_pins ap
            ON ap.id = al.access_pin_id

        LEFT JOIN wi_reservations r
            ON r.id = ap.reservation_id

        LEFT JOIN wi_users_bonuses ub
            ON ub.id = ap.user_bonus_id

        LEFT JOIN wi_users u
            ON u.id = COALESCE(r.user_id, ub.user_id)

        ' . $where . '

        ORDER BY al.event_at DESC, al.id DESC';

        return $this->db()->select($sql, $params, $asObject);
    }
    
    public function findViewDataById(int $id, bool $asObject = true): array|object|null {

        $sql = '
        SELECT
            al.*,

            d.lock_alias,
            d.lock_name,
            d.lock_mac,

            f.name AS facility_name,

            ap.pin_type,
            ap.access_type,
            ap.description AS pin_description,
            ap.pin_code_encrypted,
            ap.valid_from AS pin_valid_from,
            ap.valid_until AS pin_valid_until,
            ap.revoked_at AS pin_revoked_at,

            r.id AS reservation_id,
            r.reference AS reservation_reference,
            r.start_at AS reservation_start_at,
            r.end_at AS reservation_end_at,
            r.people_count AS reservation_people_count,

            u.id AS user_id,
            CONCAT(u.first_name, " ", u.last_name) AS user_name,
            u.email AS user_email

        FROM ' . self::TABLE . ' al

        LEFT JOIN wi_ttlock_devices d
            ON d.id = al.ttlock_device_id

        LEFT JOIN wi_facilities f
            ON f.id = al.facility_id

        LEFT JOIN wi_access_pins ap
            ON ap.id = al.access_pin_id

        LEFT JOIN wi_reservations r
            ON r.id = ap.reservation_id

        LEFT JOIN wi_users_bonuses ub
            ON ub.id = ap.user_bonus_id

        LEFT JOIN wi_users u
            ON u.id = COALESCE(r.user_id, ub.user_id)

        WHERE al.id = ?
        LIMIT 1';

        return $this->db()->selectOne($sql, [$id], $asObject);
    }
    
    public function getStatisticsKpis(string $where, array $params): array {

        $sql = '
        SELECT COUNT(*) AS accesses
        FROM ' . self::TABLE . ' al
        ' . $where;

        $result = $this->db()->selectOne($sql, $params, false);

        return is_array($result) ? $result : [];
    }

    public function getStatisticsTrend(string $where, array $params, bool $group_by_day): array {

        $period = $group_by_day ? 'DAY(al.event_at)' : 'MONTH(al.event_at)';

        $sql = '
        SELECT
            ' . $period . ' AS period,
            COUNT(*) AS total
        FROM ' . self::TABLE . ' al
        ' . $where . '
        GROUP BY ' . $period . '
        ORDER BY period ASC';

        return $this->db()->select($sql, $params, false);
    }

    public function getStatisticsResults(string $where, array $params): array {

        $sql = '
        SELECT
            al.access_granted,
            COUNT(*) AS total
        FROM ' . self::TABLE . ' al
        ' . $where . '
        GROUP BY al.access_granted';

        return $this->db()->select($sql, $params, false);
    }

    public function findReportData(string $where = '', array $params = [], bool $asObject = false): array {

        $sql = '
        SELECT
            al.id,
            al.access_pin_id,
            al.event_type,
            al.access_granted,
            al.failure_reason,
            al.event_at,
            al.payload_json,

            d.lock_alias,

            fd.facility_id,
            f.name AS facility_name,

            ap.pin_code_encrypted,
            ap.pin_type,
            ap.description AS pin_description,

            r.id AS reservation_id,
            r.reference AS reservation_reference,

            u.id AS user_id,
            CONCAT_WS(" ", u.first_name, u.last_name) AS user_name

        FROM ' . self::TABLE . ' al

        LEFT JOIN wi_ttlock_devices d
            ON d.id = al.ttlock_device_id

        LEFT JOIN wi_facilities_devices fd
            ON fd.ttlock_device_id = al.ttlock_device_id

        LEFT JOIN wi_facilities f
            ON f.id = fd.facility_id

        LEFT JOIN wi_access_pins ap
            ON ap.id = al.access_pin_id

        LEFT JOIN wi_reservations r
            ON r.id = ap.reservation_id

        LEFT JOIN wi_users_bonuses ub
            ON ub.id = ap.user_bonus_id

        LEFT JOIN wi_users u
            ON u.id = COALESCE(r.user_id, ub.user_id)

        ' . $where . '

        ORDER BY al.event_at DESC, al.id DESC';

        return $this->db()->select($sql, $params, $asObject);
    }
    
    public function existsGrantedByAccessPinId(int $access_pin_id): bool {

        $result = $this->db()->selectOne(
                'SELECT id
             FROM ' . self::TABLE . '
             WHERE access_pin_id = ?
               AND access_granted = 1
             LIMIT 1',
                [$access_pin_id],
                false
        );

        return !empty($result);
    }

    public function add(): int {

        return $this->db()->insert(
                        'INSERT INTO ' . self::TABLE . '
            (
                ttlock_device_id,
                facility_id,
                access_pin_id,
                ttlock_record_id,
                ttlock_record_type_fromlock,
                event_type,
                ttlock_record_type,
                access_granted,
                failure_reason,
                event_at,
                received_at,
                payload_json
            )
            VALUES
            (
                ?,?,?,?,?,?,?,?,?,?,?,?
            )',
                        [
                            $this->ttlock_device_id,
                            $this->facility_id,
                            $this->access_pin_id,
                            $this->ttlock_record_id,
                            $this->ttlock_record_type_fromlock,
                            $this->event_type,
                            $this->ttlock_record_type,
                            $this->access_granted,
                            $this->failure_reason,
                            $this->event_at,
                            $this->received_at,
                            $this->payload_json
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
                    ttlock_device_id = ?,
                    facility_id = ?,
                    access_pin_id = ?,
                    ttlock_record_id = ?,
                    ttlock_record_type_fromlock = ?,
                    event_type = ?,
                    ttlock_record_type = ?,
                    access_granted = ?,
                    failure_reason = ?,
                    event_at = ?,
                    received_at = ?,
                    payload_json = ?
              WHERE id = ?',
                        [
                            $this->ttlock_device_id,
                            $this->facility_id,
                            $this->access_pin_id,
                            $this->ttlock_record_id,
                            $this->ttlock_record_type_fromlock,
                            $this->event_type,
                            $this->ttlock_record_type,
                            $this->access_granted,
                            $this->failure_reason,
                            $this->event_at,
                            $this->received_at,
                            $this->payload_json,
                            $this->id
                        ]
                );
    }

    public function getId(): int {
        return $this->id;
    }

    public function getTtlockDeviceId(): int
    {
        return $this->ttlock_device_id;
    }

    public function getAccessPinId(): ?int
    {
        return $this->access_pin_id;
    }

    public function getTtlockRecordId(): ?int
    {
        return $this->ttlock_record_id;
    }

    public function getTtlockRecordTypeFromlock(): ?int
    {
        return $this->ttlock_record_type_fromlock;
    }

    public function getEventType(): ?string
    {
        return $this->event_type;
    }

    public function getTtlockRecordType(): ?int
    {
        return $this->ttlock_record_type;
    }

    public function getAccessGranted(): int
    {
        return $this->access_granted;
    }

    public function getFailureReason(): ?string
    {
        return $this->failure_reason;
    }

    public function getEventAt(): string
    {
        return $this->event_at;
    }

    public function getReceivedAt(): string
    {
        return $this->received_at;
    }

    public function getPayloadJson(): ?string
    {
        return $this->payload_json;
    }

    public function getCreatedAt(): string
    {
        return $this->created_at;
    }


    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setTtlockDeviceId(int $ttlock_device_id): void
    {
        $this->ttlock_device_id = $ttlock_device_id;
    }

    public function setAccessPinId(?int $access_pin_id): void
    {
        $this->access_pin_id = $access_pin_id;
    }

    public function setTtlockRecordId(?int $ttlock_record_id): void
    {
        $this->ttlock_record_id = $ttlock_record_id;
    }

    public function setTtlockRecordTypeFromlock(?int $ttlock_record_type_fromlock): void
    {
        $this->ttlock_record_type_fromlock = $ttlock_record_type_fromlock;
    }

    public function setEventType(?string $event_type): void
    {
        $this->event_type = $event_type;
    }

    public function setTtlockRecordType(?int $ttlock_record_type): void
    {
        $this->ttlock_record_type = $ttlock_record_type;
    }

    public function setAccessGranted(int $access_granted): void
    {
        $this->access_granted = $access_granted;
    }

    public function setFailureReason(?string $failure_reason): void
    {
        $this->failure_reason = $failure_reason;
    }

    public function setEventAt(string $event_at): void
    {
        $this->event_at = $event_at;
    }

    public function setReceivedAt(string $received_at): void
    {
        $this->received_at = $received_at;
    }

    public function setPayloadJson(?string $payload_json): void
    {
        $this->payload_json = $payload_json;
    }

    public function setCreatedAt(string $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function getFacilityId(): ?int {
        return $this->facility_id;
    }

    public function setFacilityId(?int $facility_id): void {
        $this->facility_id = $facility_id;
    }
}
