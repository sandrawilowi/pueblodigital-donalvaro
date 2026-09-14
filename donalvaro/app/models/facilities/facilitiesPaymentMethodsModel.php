<?php

declare(strict_types=1);

class facilitiesPaymentMethodsModel extends baseModel {

    protected const TABLE = 'wi_facilities_payment_methods';
    protected const PRIMARY_KEY = 'id';

    private int $id = 0;
    private int $facility_id = 0;
    private int $payment_method_id = 0;
    private string $created_at = '';

    public function findByFacilityId(int $facility_id,bool $asObject = false): array {

        $sql = '
        SELECT 
            fpm.*,
            pm.code,
            pm.name,
            pm.description,
            pm.status AS payment_method_status
        FROM ' . self::TABLE . ' fpm
        INNER JOIN wi_payment_methods pm
            ON pm.id = fpm.payment_method_id
        WHERE fpm.facility_id = ?
        ORDER BY pm.name ASC';

        return $this->db()->select(
                        $sql,
                        [$facility_id],
                        $asObject
                );
    }
    
    public function findActiveByFacilityId(int $facility_id, bool $asObject = false): array {

        $sql = '
        SELECT 
            fpm.*,
            pm.code,
            pm.name,
            pm.description,
            pm.status AS payment_method_status
        FROM ' . self::TABLE . ' fpm
        INNER JOIN wi_payment_methods pm
            ON pm.id = fpm.payment_method_id
        WHERE fpm.facility_id = ?
          AND pm.status = ?
        ORDER BY pm.name ASC';

        return $this->db()->select(
                        $sql,
                        [
                            $facility_id,
                            paymentMethodsModel::STATUS_ACTIVE
                        ],
                        $asObject
                );
    }

    public function findAvailableByFacilityId(int $facility_id, bool $asObject = false): array {

        $sql = '
        SELECT pm.*
        FROM wi_payment_methods pm
        LEFT JOIN ' . self::TABLE . ' fpm
            ON fpm.payment_method_id = pm.id
            AND fpm.facility_id = ?
        WHERE fpm.id IS NULL
          AND pm.status = ?
        ORDER BY pm.name ASC';

        return $this->db()->select(
                        $sql,
                        [
                            $facility_id,
                            paymentMethodsModel::STATUS_ACTIVE
                        ],
                        $asObject
                );
    }

    public function findByFacilityAndPaymentMethod(int $facility_id, int $payment_method_id, bool $asObject = false): array|object|null {

        $sql = '
        SELECT *
        FROM ' . self::TABLE . '
        WHERE facility_id = ?
          AND payment_method_id = ?
        LIMIT 1';

        return $this->db()->selectOne($sql, [$facility_id, $payment_method_id], $asObject);
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
                payment_method_id
            )
            VALUES
            (
                ?,?
            )',
                        [
                            $this->facility_id,
                            $this->payment_method_id
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
                    payment_method_id = ?
              WHERE id = ?',
            [
                $this->facility_id,
                $this->payment_method_id,
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

    public function getPaymentMethodId(): int
    {
        return $this->payment_method_id;
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

    public function setPaymentMethodId(int $payment_method_id): void
    {
        $this->payment_method_id = $payment_method_id;
    }

    public function setCreatedAt(string $created_at): void
    {
        $this->created_at = $created_at;
    }

}
