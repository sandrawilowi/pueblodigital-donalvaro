<?php

declare(strict_types=1);

class bonusesPaymentMethodsModel extends baseModel
{
    protected const TABLE = 'wi_bonuses_payment_methods';
    protected const PRIMARY_KEY = 'id';

    private int $id = 0;
    private int $bonus_id = 0;
    private int $payment_method_id = 0;
    private string $created_at = '';

    public function findByBonusId(int $bonus_id,bool $asObject = false): array {

        $sql = '
        SELECT 
            bpm.*,
            pm.code,
            pm.name,
            pm.description,
            pm.status AS payment_method_status
        FROM ' . self::TABLE . ' bpm
        INNER JOIN wi_payment_methods pm
            ON pm.id = bpm.payment_method_id
        WHERE bpm.bonus_id = ?
        ORDER BY pm.name ASC';

        return $this->db()->select(
                        $sql,
                        [$bonus_id],
                        $asObject
                );
    }
    
    public function findActiveByBonusId(int $bonus_id, bool $asObject = false): array {

        $sql = '
        SELECT 
            bpm.*,
            pm.code,
            pm.name,
            pm.description,
            pm.status AS payment_method_status
        FROM ' . self::TABLE . ' bpm
        INNER JOIN wi_payment_methods pm
            ON pm.id = bpm.payment_method_id
        WHERE bpm.bonus_id = ?
          AND pm.status = ?
        ORDER BY pm.name ASC';

        return $this->db()->select(
                        $sql,
                        [
                            $bonus_id,
                            paymentMethodsModel::STATUS_ACTIVE
                        ],
                        $asObject
                );
    }

    public function findAvailableByBonusId(int $bonus_id, bool $asObject = false): array {

        $sql = '
        SELECT pm.*
        FROM wi_payment_methods pm
        LEFT JOIN ' . self::TABLE . ' bpm
            ON bpm.payment_method_id = pm.id
            AND bpm.bonus_id = ?
        WHERE bpm.id IS NULL
          AND pm.status = ?
        ORDER BY pm.name ASC';

        return $this->db()->select(
                        $sql,
                        [
                            $bonus_id,
                            paymentMethodsModel::STATUS_ACTIVE
                        ],
                        $asObject
                );
    }

    public function findByBonusAndPaymentMethod(int $bonus_id, int $payment_method_id, bool $asObject = false): array|object|null {

        $sql = '
        SELECT *
        FROM ' . self::TABLE . '
        WHERE bonus_id = ?
          AND payment_method_id = ?
        LIMIT 1';

        return $this->db()->selectOne($sql, [$bonus_id, $payment_method_id], $asObject);
    }

    public function deleteById(int $id): bool {

        return $this->db()->execute(
                        'DELETE FROM ' . self::TABLE . ' WHERE id = ?',
                        [$id]
                );
    }

    public function add(): int
    {
        return $this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                bonus_id,
                payment_method_id
            )
            VALUES
            (
                ?,?
            )',
            [
                $this->bonus_id,
                $this->payment_method_id
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
                    payment_method_id = ?
              WHERE id = ?',
            [
                $this->bonus_id,
                $this->payment_method_id,
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

    public function setBonusId(int $bonus_id): void
    {
        $this->bonus_id = $bonus_id;
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
