<?php

declare(strict_types=1);

class bonusesFacilitiesModel extends baseModel {

    protected const TABLE = 'wi_bonuses_facilities';
    protected const PRIMARY_KEY = 'id';

    private int $id = 0;
    private int $bonus_id = 0;
    private int $facility_id = 0;
    private string $created_at = '';

    public function findByBonusId(int $bonus_id, bool $asObject = false): array {

        return $this->db()->select(
                        'SELECT *
                     FROM ' . self::TABLE . '
                     WHERE bonus_id = ?
                     ORDER BY id ASC',
                        [$bonus_id],
                        $asObject
                );
    }

    public function findByFacilityId(int $facility_id, bool $asObject = false): array {

        return $this->db()->select(
                        'SELECT b.*
                     FROM ' . self::TABLE . ' bf
                     INNER JOIN wi_bonuses b ON b.id = bf.bonus_id
                     WHERE bf.facility_id = ?
                       AND b.status <> ?
                     ORDER BY b.name ASC',
                        [
                            $facility_id,
                            bonusesModel::STATUS_DELETED
                        ],
                        $asObject
                );
    }

    public function findFacilitiesByBonusId(int $bonus_id, bool $asObject = false): array {

        return $this->db()->select(
                        'SELECT f.*
         FROM ' . self::TABLE . ' bf
         INNER JOIN wi_facilities f ON f.id = bf.facility_id
         WHERE bf.bonus_id = ?
         ORDER BY f.name ASC',
                        [$bonus_id],
                        $asObject
                );
    }

    public function exists(int $bonus_id, int $facility_id): bool {

        $result = $this->db()->select(
                'SELECT id
                     FROM ' . self::TABLE . '
                     WHERE bonus_id = ?
                       AND facility_id = ?
                     LIMIT 1',
                [$bonus_id, $facility_id],
                false
        );

        return !empty($result);
    }

    public function deleteByBonusId(int $bonus_id): bool {

        return $this->db()->execute(
                        'DELETE FROM ' . self::TABLE . '
                     WHERE bonus_id = ?',
                        [$bonus_id]
                );
    }

    public function deleteByBonusAndFacility(int $bonus_id, int $facility_id): bool {

        return $this->db()->execute(
                        'DELETE FROM ' . self::TABLE . '
                     WHERE bonus_id = ?
                       AND facility_id = ?',
                        [$bonus_id, $facility_id]
                );
    }

    public function add(): int {
        return $this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                bonus_id,
                facility_id
            )
            VALUES
            (
                ?,?
            )',
            [
                $this->bonus_id,
                $this->facility_id
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
                    bonus_id = ?,
                    facility_id = ?
              WHERE id = ?',
            [
                $this->bonus_id,
                $this->facility_id,
                $this->id
            ]
        );
    }


    public function getId(): int
    {
        return $this->id;
    }

    public function getBonusId(): int
    {
        return $this->bonus_id;
    }

    public function getFacilityId(): int
    {
        return $this->facility_id;
    }

    public function getCreatedAt(): string
    {
        return $this->created_at;
    }


    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setBonusId(int $bonus_id): void
    {
        $this->bonus_id = $bonus_id;
    }

    public function setFacilityId(int $facility_id): void
    {
        $this->facility_id = $facility_id;
    }

    public function setCreatedAt(string $created_at): void
    {
        $this->created_at = $created_at;
    }

}
