<?php

declare(strict_types=1);

class facilitiesImagesModel extends baseModel {

    protected const TABLE = 'wi_facilities_images';
    protected const PRIMARY_KEY = 'id';

    private int $id = 0;
    private int $facility_id = 0;
    private string $filename = '';
    private int $is_cover = 0;
    private int $sort_order = 1;
    private string $created_at = '';
    private string $updated_at = '';

    public function findByFacilityId(
            int $facility_id,
            bool $asObject = false
    ): array {

        $sql = '
        SELECT *
        FROM ' . self::TABLE . '
        WHERE facility_id = ?
        ORDER BY is_cover DESC, sort_order ASC, id ASC';

        return $this->db()->select(
                        $sql,
                        [$facility_id],
                        $asObject
                );
    }

    public function findCoverByFacilityId(int $facility_id): array|object|null {

        $sql = '
        SELECT *
        FROM ' . self::TABLE . '
        WHERE facility_id = ?
          AND is_cover = 1
        LIMIT 1';

        return $this->db()->selectOne($sql, [$facility_id]);
    }
    
    public function getNextSortOrder(int $facility_id): int {

        $sql = '
        SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order
        FROM ' . self::TABLE . '
        WHERE facility_id = ?';

        $result = $this->db()->selectOne($sql, [$facility_id], true);

        return (int) ($result->next_order ?? 1);
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
                filename,
                is_cover,
                sort_order
            )
            VALUES
            (
                ?,?,?,?
            )',
            [
                $this->facility_id,
                $this->filename,
                $this->is_cover,
                $this->sort_order
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
                    filename = ?,
                    is_cover = ?,
                    sort_order = ?,
                    updated_at = NOW()
              WHERE id = ?',
            [
                $this->facility_id,
                $this->filename,
                $this->is_cover,
                $this->sort_order,
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

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getIsCover(): int
    {
        return $this->is_cover;
    }

    public function getSortOrder(): int
    {
        return $this->sort_order;
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

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function setIsCover(int $is_cover): void
    {
        $this->is_cover = $is_cover;
    }

    public function setSortOrder(int $sort_order): void
    {
        $this->sort_order = $sort_order;
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
