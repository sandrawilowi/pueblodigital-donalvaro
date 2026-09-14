<?php

declare(strict_types=1);

class bonusMovementsModel extends baseModel
{
    protected const TABLE = 'wi_bonus_movements';
    protected const PRIMARY_KEY = 'id';
    
    public const TYPE_CONSUMPTION = 1;
    public const TYPE_REFUND = 2;
    public const TYPE_ADJUSTMENT = 3;

    private int $id = 0;
    private int $user_bonus_id = 0;
    private ?int $reservation_id = null;
    private int $movement_type = 0;
    private int $quantity = 0;
    private int $balance_after = 0;
    private ?string $notes = null;
    private ?int $created_by_user_id = null;
    private string $created_at = '';


    public function add(): int
    {
        return $this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                user_bonus_id,
                reservation_id,
                movement_type,
                quantity,
                balance_after,
                notes,
                created_by_user_id
            )
            VALUES
            (
                ?,?,?,?,?,?,?
            )',
            [
                $this->user_bonus_id,
                $this->reservation_id,
                $this->movement_type,
                $this->quantity,
                $this->balance_after,
                $this->notes,
                $this->created_by_user_id
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
                    user_bonus_id = ?,
                    reservation_id = ?,
                    movement_type = ?,
                    quantity = ?,
                    balance_after = ?,
                    notes = ?,
                    created_by_user_id = ?
              WHERE id = ?',
            [
                $this->user_bonus_id,
                $this->reservation_id,
                $this->movement_type,
                $this->quantity,
                $this->balance_after,
                $this->notes,
                $this->created_by_user_id,
                $this->id
            ]
        );
    }


    public function getId(): int
    {
        return $this->id;
    }

    public function getUserBonusId(): int
    {
        return $this->user_bonus_id;
    }

    public function getReservationId(): ?int
    {
        return $this->reservation_id;
    }

    public function getMovementType(): int
    {
        return $this->movement_type;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getBalanceAfter(): int
    {
        return $this->balance_after;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCreatedByUserId(): ?int
    {
        return $this->created_by_user_id;
    }

    public function getCreatedAt(): string
    {
        return $this->created_at;
    }


    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setUserBonusId(int $user_bonus_id): void
    {
        $this->user_bonus_id = $user_bonus_id;
    }

    public function setReservationId(?int $reservation_id): void
    {
        $this->reservation_id = $reservation_id;
    }

    public function setMovementType(int $movement_type): void
    {
        $this->movement_type = $movement_type;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function setBalanceAfter(int $balance_after): void
    {
        $this->balance_after = $balance_after;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function setCreatedByUserId(?int $created_by_user_id): void
    {
        $this->created_by_user_id = $created_by_user_id;
    }

    public function setCreatedAt(string $created_at): void
    {
        $this->created_at = $created_at;
    }

}
