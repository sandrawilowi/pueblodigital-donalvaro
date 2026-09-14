<?php

declare(strict_types=1);

class reservationsGuestsModel extends baseModel {

    protected const TABLE = 'wi_reservations_guests';
    protected const PRIMARY_KEY = 'id';

    private int $id = 0;
    private int $reservation_id = 0;
    private string $full_name = '';
    private ?int $document_type_id = null;
    private ?string $document_number = null;
    private int $is_holder = 0;
    private string $created_at = '';
    private string $updated_at = '';

    public function findByReservationId(int $reservation_id, bool $asObject = false): array {

        $sql = 'SELECT
                rg.*,
                dt.name AS document_type_name
            FROM ' . self::TABLE . ' rg
            LEFT JOIN wi_document_types dt
                ON dt.id = rg.document_type_id
            WHERE rg.reservation_id = ?
            ORDER BY rg.is_holder DESC, rg.id ASC';

        return $this->db()->select(
                        $sql,
                        [$reservation_id],
                        $asObject
                );
    }
    
    public function countByReservationId(int $reservation_id): int {

        $result = $this->db()->selectOne(
                'SELECT COUNT(*) AS total
             FROM ' . self::TABLE . '
             WHERE reservation_id = ?',
                [$reservation_id],
                false
        );

        return (int) ($result['total'] ?? 0);
    }
    
    public function findHolderByReservationId(int $reservation_id, bool $asObject = false): array|object|null {

        return $this->db()->selectOne(
                        'SELECT *
             FROM ' . self::TABLE . '
             WHERE reservation_id = ?
             AND is_holder = 1
             LIMIT 1',
                        [$reservation_id],
                        $asObject
                );
    }

    public function findHolderByReservationIdExceptId(int $reservation_id, int $guest_id, bool $asObject = false): array|object|null {

        return $this->db()->selectOne(
                        'SELECT *
             FROM ' . self::TABLE . '
             WHERE reservation_id = ?
             AND is_holder = 1
             AND id <> ?
             LIMIT 1',
                        [$reservation_id, $guest_id],
                        $asObject
                );
    }

    public function add(): int {
        return $this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                reservation_id,
                full_name,
                document_type_id,
                document_number,
                is_holder
            )
            VALUES
            (
                ?,?,?,?,?
            )',
            [
                $this->reservation_id,
                $this->full_name,
                $this->document_type_id,
                $this->document_number,
                $this->is_holder
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
                    reservation_id = ?,
                    full_name = ?,
                    document_type_id = ?,
                    document_number = ?,
                    is_holder = ?,
                    updated_at = NOW()
              WHERE id = ?',
            [
                $this->reservation_id,
                $this->full_name,
                $this->document_type_id,
                $this->document_number,
                $this->is_holder,
                $this->id
            ]
        );
    }

    public function deleteById(int $guest_id): bool {

        return $this->db()->execute(
                        'DELETE FROM ' . self::TABLE . '
             WHERE id = ?',
                        [$guest_id]
                );
    }

    public function getId(): int {
        return $this->id;
    }

    public function getReservationId(): int
    {
        return $this->reservation_id;
    }

    public function getFullName(): string
    {
        return $this->full_name;
    }

    public function getDocumentTypeId(): ?int
    {
        return $this->document_type_id;
    }

    public function getDocumentNumber(): ?string
    {
        return $this->document_number;
    }

    public function getIsHolder(): int
    {
        return $this->is_holder;
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

    public function setReservationId(int $reservation_id): void
    {
        $this->reservation_id = $reservation_id;
    }

    public function setFullName(string $full_name): void
    {
        $this->full_name = $full_name;
    }

    public function setDocumentTypeId(?int $document_type_id): void
    {
        $this->document_type_id = $document_type_id;
    }

    public function setDocumentNumber(?string $document_number): void
    {
        $this->document_number = $document_number;
    }

    public function setIsHolder(int $is_holder): void
    {
        $this->is_holder = $is_holder;
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
