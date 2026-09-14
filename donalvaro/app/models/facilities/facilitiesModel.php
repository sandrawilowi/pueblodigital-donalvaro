<?php

declare(strict_types=1);

class facilitiesModel extends baseModel {

    protected const TABLE = 'wi_facilities';
    protected const PRIMARY_KEY = 'id';
    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_BLOCKED = 2;
    public const STATUS_DELETED = 3;

    private int $id = 0;
    private string $name = '';
    private ?string $description = null;
    private ?string $address = null;
    private ?string $city = null;
    private ?int $province_id = null;
    private ?string $postal_code = null;
    private ?string $google_maps_url = null;
    private ?int $capacity = null;
    private int $booking_enabled = 1;
    private ?int $booking_type_id = null;
    private ?int $booking_interval_minutes = null;
    private ?int $minimum_booking_minutes = null;
    private string $check_in_time = '00:00:00';
    private string $check_out_time = '00:00:00';
    private ?string $pending_payment_instructions = null;
    private ?string $booking_conditions = null;
    private int $access_enabled = 1;
    private int $requires_guest_information = 0;
    private int $pin_valid_before_minutes = 0;
    private int $pin_valid_after_minutes = 0;
    private int $status = 1;
    private string $created_at = '';
    private string $updated_at = '';
    private ?string $deleted_at = null;

    public function findAll(bool $asObject = false): array {

        $sql = '
        SELECT *
        FROM ' . self::TABLE . '
        ORDER BY name ASC';

        return $this->db()->select($sql, [], $asObject);
    }

    public function findAllActive(bool $asObject = false): array {
        return $this->db()->select(
                        'SELECT *
           FROM ' . self::TABLE . ' WHERE status=' . self::STATUS_ACTIVE . ' ORDER BY id DESC',
                        [],
                        $asObject
                );
    }

    public function findAllNotDeleted(bool $asObject): array {

        return $this->db()->select(
                        'SELECT *
         FROM ' . self::TABLE . '
         WHERE status <> ?
           AND deleted_at IS NULL
         ORDER BY id DESC',
                        [self::STATUS_DELETED],
                        $asObject
                );
    }

    public function findActiveWithDevices(bool $asObject = false): array {

        $sql = '
        SELECT DISTINCT f.*
        FROM wi_facilities f
        INNER JOIN wi_facilities_devices fd ON fd.facility_id = f.id
        WHERE f.status = ?
          AND f.deleted_at IS NULL
        ORDER BY f.name ASC';

        return $this->db()->select($sql, [facilitiesModel::STATUS_ACTIVE], $asObject);
    }

    public function findByFilters(string $where, array $params = [], bool $asObject = false): array {

        $sql = '
        SELECT
            f.*,
            p.name AS province_name
        FROM ' . self::TABLE . ' f
        LEFT JOIN wi_provinces p
            ON p.id = f.province_id
        ' . $where . '
        ORDER BY f.id DESC';

        return $this->db()->select(
                        $sql,
                        $params,
                        $asObject
                );
    }

    public function findAllBookingEnabled(bool $asObject = false, ?int $limit = null): array {

         $sql = '
        SELECT 
            f.*,
            bt.code AS booking_type_code,
            bt.name AS booking_type_name,
            fi.filename AS image
        FROM ' . self::TABLE . ' f
        LEFT JOIN wi_booking_types bt
            ON bt.id = f.booking_type_id
        LEFT JOIN wi_facilities_images fi
            ON fi.facility_id = f.id
            AND fi.is_cover = 1
        WHERE f.booking_enabled = 1
            AND f.status = ?
            AND f.deleted_at IS NULL
        ORDER BY f.id DESC';

        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        return $this->db()->select(
                        $sql,
                        [self::STATUS_ACTIVE],
                        $asObject
                );
    }

    public function findByIdWithBookingType(int $id, bool $asObject = false): array|object|null {

        $sql = 'SELECT
                f.*,
                bt.code AS booking_type_code,
                bt.name AS booking_type_name
            FROM ' . self::TABLE . ' f
            LEFT JOIN wi_booking_types bt ON bt.id = f.booking_type_id
            WHERE f.id = ?
            AND f.deleted_at IS NULL
            LIMIT 1';

        return $this->db()->selectOne($sql, [$id], $asObject);
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

    public function add(): int {

        return $this->db()->insert(
                        'INSERT INTO ' . self::TABLE . '
        (
            name,
            description,
            address,
            city,
            province_id,
            postal_code,
            google_maps_url,
            capacity,
            booking_enabled,
            booking_type_id,
            booking_interval_minutes,
            minimum_booking_minutes,
            check_in_time,
            check_out_time,
            pending_payment_instructions,
            access_enabled,
            requires_guest_information,
            pin_valid_before_minutes,
            pin_valid_after_minutes,
            status
        )
        VALUES
        (
            ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
        )',
                        [
                            $this->name,
                            $this->description,
                            $this->address,
                            $this->city,
                            $this->province_id,
                            $this->postal_code,
                            $this->google_maps_url,
                            $this->capacity,
                            $this->booking_enabled,
                            $this->booking_type_id,
                            $this->booking_interval_minutes,
                            $this->minimum_booking_minutes,
                            $this->check_in_time,
                            $this->check_out_time,
                            $this->pending_payment_instructions,
                            $this->access_enabled,
                            $this->requires_guest_information,
                            $this->pin_valid_before_minutes,
                            $this->pin_valid_after_minutes,
                            $this->status
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
            name = ?,
            description = ?,
            address = ?,
            city = ?,
            province_id = ?,
            postal_code = ?,
            google_maps_url = ?,
            capacity = ?,
            booking_enabled = ?,
            booking_type_id = ?,
            booking_interval_minutes = ?,
            minimum_booking_minutes = ?,
            check_in_time = ?,
            check_out_time = ?,
            pending_payment_instructions = ?,
            booking_conditions = ?,
            access_enabled = ?,
            requires_guest_information = ?,
            pin_valid_before_minutes = ?,
            pin_valid_after_minutes = ?,
            status = ?,
            updated_at = NOW()
        WHERE id = ?',
                        [
                            $this->name,
                            $this->description,
                            $this->address,
                            $this->city,
                            $this->province_id,
                            $this->postal_code,
                            $this->google_maps_url,
                            $this->capacity,
                            $this->booking_enabled,
                            $this->booking_type_id,
                            $this->booking_interval_minutes,
                            $this->minimum_booking_minutes,
                            $this->check_in_time,
                            $this->check_out_time,
                            $this->pending_payment_instructions,
                            $this->booking_conditions,
                            $this->access_enabled,
                            $this->requires_guest_information,
                            $this->pin_valid_before_minutes,
                            $this->pin_valid_after_minutes,
                            $this->status,
                            $this->id
                        ]
                );
    }

    public function softDelete(): bool {
        if (empty($this->id)) {
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

    public function lockByIdForUpdate(int $id, bool $asObject = false): array|object|null {

        $sql = 'SELECT *
            FROM ' . self::TABLE . '
            WHERE id = ?
            FOR UPDATE';

        return $this->db()->selectOne(
                        $sql,
                        [$id],
                        $asObject
                );
    }

    public function getId(): int {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function getAddress(): ?string {
        return $this->address;
    }

    public function getCity(): ?string {
        return $this->city;
    }

    public function getProvinceId(): ?int {
        return $this->province_id;
    }

    public function getPostalCode(): ?string {
        return $this->postal_code;
    }

    public function getGoogleMapsUrl(): ?string {
        return $this->google_maps_url;
    }

    public function getCapacity(): ?int {
        return $this->capacity;
    }

    public function getBookingEnabled(): int {
        return $this->booking_enabled;
    }

    public function getBookingTypeId(): ?int {
        return $this->booking_type_id;
    }

    public function getBookingIntervalMinutes(): ?int {
        return $this->booking_interval_minutes;
    }

    public function getMinimumBookingMinutes(): ?int {
        return $this->minimum_booking_minutes;
    }

    public function getPendingPaymentInstructions(): ?string {
        return $this->pending_payment_instructions;
    }

    public function getAccessEnabled(): int {
        return $this->access_enabled;
    }

    public function getRequiresGuestInformation(): int {
        return $this->requires_guest_information;
    }

    public function getPinValidBeforeMinutes(): int {
        return $this->pin_valid_before_minutes;
    }

    public function getPinValidAfterMinutes(): int {
        return $this->pin_valid_after_minutes;
    }

    public function getStatus(): int {
        return $this->status;
    }

    public function getCreatedAt(): string {
        return $this->created_at;
    }

    public function getUpdatedAt(): string {
        return $this->updated_at;
    }

    public function getDeletedAt(): ?string {
        return $this->deleted_at;
    }

    public function getCheckInTime(): string {
        return $this->check_in_time;
    }

    public function getCheckOutTime(): string {
        return $this->check_out_time;
    }
    
    public function getBookingConditions(): ?string {
    return $this->booking_conditions;
}

    public function setId(int $id): void {
        $this->id = $id;
    }

    public function setName(string $name): void {
        $this->name = $name;
    }

    public function setDescription(?string $description): void {
        $this->description = $description;
    }

    public function setAddress(?string $address): void {
        $this->address = $address;
    }

    public function setCity(?string $city): void {
        $this->city = $city;
    }

    public function setProvinceId(?int $province_id): void {
        $this->province_id = $province_id;
    }

    public function setPostalCode(?string $postal_code): void {
        $this->postal_code = $postal_code;
    }

    public function setGoogleMapsUrl(?string $google_maps_url): void {
        $this->google_maps_url = $google_maps_url;
    }

    public function setCapacity(?int $capacity): void {
        $this->capacity = $capacity;
    }

    public function setBookingEnabled(int $booking_enabled): void {
        $this->booking_enabled = $booking_enabled;
    }

    public function setBookingTypeId(?int $booking_type_id): void {
        $this->booking_type_id = $booking_type_id;
    }

    public function setBookingIntervalMinutes(?int $booking_interval_minutes): void {
        $this->booking_interval_minutes = $booking_interval_minutes;
    }

    public function setMinimumBookingMinutes(?int $minimum_booking_minutes): void {
        $this->minimum_booking_minutes = $minimum_booking_minutes;
    }

    public function setPendingPaymentInstructions(?string $pending_payment_instructions): void {
        $this->pending_payment_instructions = $pending_payment_instructions;
    }

    public function setAccessEnabled(int $access_enabled): void {
        $this->access_enabled = $access_enabled;
    }

    public function setRequiresGuestInformation(int $requires_guest_information): void {
        $this->requires_guest_information = $requires_guest_information;
    }

    public function setPinValidBeforeMinutes(int $pin_valid_before_minutes): void {
        $this->pin_valid_before_minutes = $pin_valid_before_minutes;
    }

    public function setPinValidAfterMinutes(int $pin_valid_after_minutes): void {
        $this->pin_valid_after_minutes = $pin_valid_after_minutes;
    }

    public function setStatus(int $status): void {
        $this->status = $status;
    }

    public function setCreatedAt(string $created_at): void {
        $this->created_at = $created_at;
    }

    public function setUpdatedAt(string $updated_at): void {
        $this->updated_at = $updated_at;
    }

    public function setDeletedAt(?string $deleted_at): void {
        $this->deleted_at = $deleted_at;
    }

    public function setCheckInTime(string $check_in_time): void {
        $this->check_in_time = $check_in_time;
    }

    public function setCheckOutTime(string $check_out_time): void {
        $this->check_out_time = $check_out_time;
    }
    
    public function setBookingConditions(?string $booking_conditions): void {
    $this->booking_conditions = $booking_conditions;
}
}
