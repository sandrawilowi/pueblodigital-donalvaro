<?php

declare(strict_types=1);

class facilitiesServicesModel extends baseModel {

    protected const TABLE = 'wi_facilities_services';
    protected const PRIMARY_KEY = 'id';

    private int $id = 0;
    private int $facility_id = 0;
    private int $service_id = 0;
    private string $created_at = '';

    public function findByFacilityId(int $facility_id,bool $asObject = false): array {

        $sql = '
        SELECT
            fs.*,
            s.name,
            s.description,
            s.icon
        FROM ' . self::TABLE . ' fs
        INNER JOIN wi_services s
            ON s.id = fs.service_id
        WHERE fs.facility_id = ?
          AND s.status = ?
          AND s.deleted_at IS NULL
        ORDER BY s.name ASC';

        return $this->db()->select(
                        $sql,
                        [
                            $facility_id,
                            servicesModel::STATUS_ACTIVE
                        ],
                        $asObject
                );
    }

    public function findAvailableByFacilityId(int $facility_id,bool $asObject = false): array {

        $sql = '
        SELECT s.*
        FROM wi_services s
        LEFT JOIN ' . self::TABLE . ' fs
            ON fs.service_id = s.id
            AND fs.facility_id = ?
        WHERE fs.id IS NULL
          AND s.status = ?
          AND s.deleted_at IS NULL
        ORDER BY s.name ASC';

        return $this->db()->select(
                        $sql,
                        [
                            $facility_id,
                            servicesModel::STATUS_ACTIVE
                        ],
                        $asObject
                );
    }
    
    public function findByFacilityAndService(int $facility_id, int $service_id, bool $asObject = false): array|object|null {

        $sql = '
        SELECT *
        FROM ' . self::TABLE . '
        WHERE facility_id = ?
          AND service_id = ?
        LIMIT 1';

        return $this->db()->selectOne($sql, [$facility_id, $service_id], $asObject);
    }

    public function deleteById(int $id): bool {

        return $this->db()->execute(
                        'DELETE FROM ' . self::TABLE . ' WHERE id = ?',
                        [$id]
                );
    }

    public function add(): int {
        return $this->db()->insert(
                        'INSERT INTO ' . self::TABLE . '
            (
                facility_id,
                service_id
            )
            VALUES
            (
                ?,?
            )',
            [
                $this->facility_id,
                $this->service_id
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
                    facility_id = ?,
                    service_id = ?
              WHERE id = ?',
            [
                $this->facility_id,
                $this->service_id,
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

    public function getServiceId(): int
    {
        return $this->service_id;
    }

    public function getCreatedAt(): string
    {
        return $this->created_at;
    }


    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setFacilityId(int $facility_id): void
    {
        $this->facility_id = $facility_id;
    }

    public function setServiceId(int $service_id): void
    {
        $this->service_id = $service_id;
    }

    public function setCreatedAt(string $created_at): void
    {
        $this->created_at = $created_at;
    }

}
