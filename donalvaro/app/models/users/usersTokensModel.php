<?php

declare(strict_types=1);

class usersTokensModel extends baseModel
{
    protected const TABLE = 'wi_users_tokens';
    protected const PRIMARY_KEY = 'id';

    private int $id = 0;
    private int $user_id = 0;
    private string $token_type = '';
    private string $token_hash = '';
    private string $expires_at = '';
    private ?string $used_at = null;
    private string $created_at = '';

    public function findValidToken(
            string $tokenHash,
            string $tokenType
    ): ?array {
        return $this->db()->selectOne(
                        'SELECT *
           FROM '.self::TABLE.'
          WHERE token_hash = ?
            AND token_type = ?
            AND used_at IS NULL
            AND expires_at > NOW()
          LIMIT 1',
                        [
                            $tokenHash,
                            $tokenType
                        ]
                );
    }
    
    /**
     * Obtiene el último token de activación de un usuario.
     *
     * @param int $user_id
     * @param bool $asObject
     *
     * @return array|object|null
     */
    public function findAccountActivationByUserId(int $user_id, bool $asObject = false): array|object|null {

        $sql = "SELECT *
            FROM wi_users_tokens
            WHERE user_id = ?
            AND token_type IN (?, ?)
            ORDER BY created_at DESC
            LIMIT 1";

        return $this->db()->selectOne(
                        $sql,
                        [
                            $user_id,
                            'ACCOUNT_ACTIVATION',
                            'SET_PASSWORD'
                        ],
                        $asObject
                );
    }

    public function add(): int {
        return $this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                user_id,
                token_type,
                token_hash,
                expires_at,
                used_at
            )
            VALUES
            (
                ?,?,?,?,?
            )',
            [
                $this->user_id,
                $this->token_type,
                $this->token_hash,
                $this->expires_at,
                $this->used_at
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
                    user_id = ?,
                    token_type = ?,
                    token_hash = ?,
                    expires_at = ?,
                    used_at = ?
              WHERE id = ?',
            [
                $this->user_id,
                $this->token_type,
                $this->token_hash,
                $this->expires_at,
                $this->used_at,
                $this->id
            ]
        );
    }

    
    public function markAsUsed(int $tokenId): bool {
        return $this->db()->execute(
                        'UPDATE '.self::TABLE.'
            SET used_at = NOW()
          WHERE id = ?
            AND used_at IS NULL',
                        [
                            $tokenId
                        ]
                );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function getTokenType(): string
    {
        return $this->token_type;
    }

    public function getTokenHash(): string
    {
        return $this->token_hash;
    }

    public function getExpiresAt(): string
    {
        return $this->expires_at;
    }

    public function getUsedAt(): ?string
    {
        return $this->used_at;
    }

    public function getCreatedAt(): string
    {
        return $this->created_at;
    }


    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function setTokenType(string $token_type): void
    {
        $this->token_type = $token_type;
    }

    public function setTokenHash(string $token_hash): void
    {
        $this->token_hash = $token_hash;
    }

    public function setExpiresAt(string $expires_at): void
    {
        $this->expires_at = $expires_at;
    }

    public function setUsedAt(?string $used_at): void
    {
        $this->used_at = $used_at;
    }

    public function setCreatedAt(string $created_at): void
    {
        $this->created_at = $created_at;
    }

}
