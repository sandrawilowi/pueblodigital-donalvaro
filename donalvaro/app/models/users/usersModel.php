<?php

declare(strict_types=1);

class usersModel extends baseModel
{
    protected const TABLE = 'wi_users';
    protected const PRIMARY_KEY = 'id';

    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_BLOCKED = 2;
    public const STATUS_DELETED = 3;
    
    private int $id = 0;
    private string $first_name = '';
    private ?string $last_name = null;
    private ?string $display_name = null;
    private string $email = '';
    private string $password = '';
    private int $status = 0;
    private ?string $last_login_at = null;
    private ?string $email_verified_at = null;
    private ?int $attempts = 0;
    private string $created_at = '';
    private ?string $updated_at = null;
    private ?string $deleted_at = null;


    public function findByEmail(string $email): ?array
    {
        return $this->db()->selectOne(
            'SELECT *
               FROM ' . self::TABLE . '
               INNER JOIN wi_users_roles ON(wi_users.id=wi_users_roles.user_id)
              WHERE email = ?
                AND deleted_at IS NULL
              LIMIT 1',
            [strtolower(trim($email))]
                );
    }

    /**
     * Obtiene todos los usuarios de un rol que no estén eliminados.
     *
     * @param int $role_id
     * @param bool $asObject
     * @return array
     */
    public function findByRole(int $role_id, bool $asObject = false): array {

        $sql = "SELECT 
                u.*,
                up.phone
            FROM wi_users u
            INNER JOIN wi_users_roles ur ON ur.user_id = u.id
            LEFT JOIN wi_users_profile up ON up.user_id = u.id
            WHERE ur.role_id = ?
            AND u.deleted_at IS NULL
            ORDER BY u.id DESC, u.first_name ASC, u.last_name ASC";

        return $this->db()->select($sql, [$role_id], $asObject);
    }
    
    /**
     * Obtiene usuarios según los filtros recibidos.
     *
     * @param string $where
     * @param array $params
     * @param bool $asObject
     *
     * @return array
     */
    public function findByFilters(string $where = '', array $params = [], bool $asObject = false): array {

        $sql = "SELECT 
                u.*,
                up.phone
            FROM wi_users u
            INNER JOIN wi_users_roles ur ON ur.user_id = u.id
            LEFT JOIN wi_users_profile up ON up.user_id = u.id
            $where
            ORDER BY u.first_name ASC, u.last_name ASC";

        return $this->db()->select($sql, $params, $asObject);
    }
    
    /**
     * Obtiene los datos completos de un usuario para edición.
     *
     * @param int $user_id
     * @param bool $asObject
     *
     * @return array|object|null
     */
    public function findEditData(int $user_id, bool $asObject = false): array|object|null {

        $sql = "SELECT
                u.*,
                up.phone,
                up.photo,
                up.country_id,
                up.province_id,
                up.address,
                up.postal_code,
                up.city,
                ur.role_id
            FROM wi_users u
            LEFT JOIN wi_users_profile up ON up.user_id = u.id
            INNER JOIN wi_users_roles ur ON ur.user_id = u.id
            WHERE u.id = ?
            AND u.deleted_at IS NULL
            LIMIT 1";

        return $this->db()->selectOne($sql, [$user_id], $asObject);
    }
    
    /**
     * Obtiene todos los usuarios activos de un rol.
     *
     * @param int $role_id
     * @param bool $asObject
     *
     * @return array
     */
    public function findAllActiveByRole(int $role_id, bool $asObject = false): array {

        $sql = "SELECT
                u.*
            FROM wi_users u
            INNER JOIN wi_users_roles ur ON ur.user_id = u.id
            WHERE ur.role_id = ?
            AND u.status = ?
            AND u.deleted_at IS NULL
            ORDER BY u.first_name ASC, u.last_name ASC";

        return $this->db()->select(
                        $sql,
                        [
                            $role_id,
                            self::STATUS_ACTIVE
                        ],
                        $asObject
                );
    }

    public function findClientsReportData(string $where = '', array $params = [], bool $asObject = false): array {

        $sql = '
        SELECT
            u.id,
            CONCAT_WS(" ", u.first_name, u.last_name) AS user_name,
            u.email,

            up.city,
            up.province_id,
            up.country_id,

            p.name AS province_name,

            COALESCE(c.nom, c.country_name) AS country_name,

            COUNT(r.id) AS reservations_count,

            COALESCE(
                SUM(
                    CASE
                        WHEN rs.code IN ("confirmed", "completed")
                        THEN r.total_amount
                        ELSE 0
                    END
                ),
                0
            ) AS total_amount

        FROM ' . self::TABLE . ' u

        INNER JOIN wi_reservations r
            ON r.user_id = u.id

        LEFT JOIN wi_users_profile up
            ON up.user_id = u.id

        LEFT JOIN wi_provinces p
            ON p.id = up.province_id

        LEFT JOIN wi_countries c
            ON c.id = up.country_id

        LEFT JOIN wi_reservation_statuses rs
            ON rs.id = r.reservation_status_id

        ' . $where . '

        GROUP BY
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            up.city,
            up.province_id,
            up.country_id,
            p.name,
            c.nom,
            c.country_name

        ORDER BY reservations_count DESC, user_name ASC';

        return $this->db()->select($sql, $params, $asObject);
    }
    
    public function findOriginReportData(string $where = '', array $params = [], bool $asObject = false): array {

        $sql = '
        SELECT
            up.country_id,
            up.province_id,

            COALESCE(c.nom, c.country_name, "Sin especificar") AS country_name,
            COALESCE(p.name, "Sin especificar") AS province_name,

            COUNT(DISTINCT u.id) AS clients_count,
            COUNT(r.id) AS reservations_count

        FROM ' . self::TABLE . ' u

        INNER JOIN wi_reservations r
            ON r.user_id = u.id

        LEFT JOIN wi_users_profile up
            ON up.user_id = u.id

        LEFT JOIN wi_countries c
            ON c.id = up.country_id

        LEFT JOIN wi_provinces p
            ON p.id = up.province_id

        ' . $where . '

        GROUP BY
            up.country_id,
            up.province_id,
            c.nom,
            c.country_name,
            p.name

        ORDER BY
            clients_count DESC,
            country_name ASC,
            province_name ASC';

        return $this->db()->select($sql, $params, $asObject);
    }
    
    public function findByIdWithRole(int $user_id, bool $asObject = false): array|object|null {

        $sql = '
        SELECT
            u.*,
            ur.role_id
        FROM ' . self::TABLE . ' u
        INNER JOIN wi_users_roles ur ON ur.user_id = u.id
        WHERE u.id = ?
            AND u.deleted_at IS NULL
        LIMIT 1';

        return $this->db()->selectOne($sql, [$user_id], $asObject);
    }
    
    public function findBlockedByAttempts(bool $asObject = false): array {

        return $this->db()->select(
                        'SELECT *
             FROM ' . self::TABLE . '
             WHERE status = ?
               AND attempts >= ?
             ORDER BY id ASC',
                        [
                            2,
                            5
                        ],
                        $asObject
                );
    }

    public function add(): int {
        return $this->db()->insert(
                        'INSERT INTO ' . self::TABLE . '
            (
                first_name,
                last_name,
                display_name,
                email,
                password,
                status,
                last_login_at,
                email_verified_at,
                attempts
            )
            VALUES
            (
                ?,?,?,?,?,?,?,?,?
            )',
            [
                $this->first_name,
                $this->last_name,
                $this->display_name,
                $this->email,
                $this->password,
                $this->status,
                $this->last_login_at,
                $this->email_verified_at,
                $this->attempts
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
                    first_name = ?,
                    last_name = ?,
                    display_name = ?,
                    email = ?,
                    password = ?,
                    status = ?,
                    last_login_at = ?,
                    email_verified_at = ?,
                    attempts = ?,
                    updated_at = NOW()
              WHERE id = ?',
            [
                $this->first_name,
                $this->last_name,
                $this->display_name,
                $this->email,
                $this->password,
                $this->status,
                $this->last_login_at,
                $this->email_verified_at,
                $this->attempts,
                $this->id
            ]
        );
    }

    public function softDelete(): bool
    {
        if ($this->id === null) {
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
    
    public function updatePassword(): bool
    {
        if ($this->id === null) {
            throw new RuntimeException('No se puede actualizar la contraseña sin id.');
        }

        return $this->db()->execute(
            'UPDATE ' . self::TABLE . '
                SET password = ?,
                    updated_at = NOW()
              WHERE id = ?',
            [
                $this->password,
                $this->id
            ]
        );
    }

    public function updateLastLogin(): bool
    {
        if ($this->id === null) {
            throw new RuntimeException('No se puede actualizar el último login sin id.');
        }

        return $this->db()->execute(
            'UPDATE ' . self::TABLE . '
                SET last_login_at = NOW(),
                    attempts = 0,
                    updated_at = NOW()
              WHERE id = ?',
            [$this->id]
        );
    }
    
    public function updateStatus(int $status): bool{
        
        if ($this->id === null) {
            throw new RuntimeException('No se puede actualizar el último login sin id.');
        }
        
        if (!in_array($status, [
            self::STATUS_INACTIVE,
            self::STATUS_ACTIVE,
            self::STATUS_BLOCKED,
            self::STATUS_DELETED
        ], true)) {
            throw new InvalidArgumentException('Estado de usuario no válido.');
        }

        return $this->db()->execute(
            'UPDATE ' . self::TABLE . '
                SET status = '.$status.'
                    , updated_at = NOW()
              WHERE id = ?',
            [$this->id]
        );
    }
    
    public function updateDisplayName(?string $display_name): bool {

        if (empty($this->id)) {
            throw new RuntimeException('No se puede actualizar el nombre para mostrar sin id.');
        }
        
        $sql = 'UPDATE ' . self::TABLE . '
            SET display_name = ?,
                updated_at = NOW()
          WHERE id = ?';

        return $this->db()->execute($sql,[$display_name,$this->id]);
    }
    
    public function updateProfileData(): bool {

        if (empty($this->id)) {
            throw new RuntimeException('No se puede actualizar sin id.');
        }

        $sql = '
        UPDATE ' . self::TABLE . '
        SET
            first_name = ?,
            last_name = ?,
            email = ?,
            updated_at = NOW()
        WHERE id = ?';

        return $this->db()->execute(
                        $sql,
                        [
                            $this->first_name,
                            $this->last_name,
                            $this->email,
                            $this->id
                        ]
                );
    }

    public function increaseAttempts(): bool
    {
        if ($this->id === null) {
            throw new RuntimeException('No se pueden actualizar intentos sin id.');
        }

        return $this->db()->execute(
            'UPDATE ' . self::TABLE . '
                SET attempts = attempts + 1,
                    updated_at = NOW()
              WHERE id = ?',
            [$this->id]
        );
    }

    public function resetAttempts(): bool
    {
        if ($this->id === null) {
            throw new RuntimeException('No se pueden resetear intentos sin id.');
        }

        return $this->db()->execute(
            'UPDATE ' . self::TABLE . '
                SET attempts = 0,
                    updated_at = NOW()
              WHERE id = ?',
            [$this->id]
        );
    }

    public function unlockByAttempts(int $user_id): bool {

        return $this->db()->execute(
                        'UPDATE ' . self::TABLE . '
             SET status = 1,
                 attempts = 0,
                 updated_at = NOW()
             WHERE id = ?
               AND status = 2
               AND attempts >= 5',
                        [$user_id]
                );
    }

    public function markEmailVerified(): bool {
        if ($this->id === null) {
            throw new RuntimeException('No se puede verificar email sin id.');
        }

        return $this->db()->execute(
            'UPDATE ' . self::TABLE . '
                SET email_verified_at = NOW(),
                    updated_at = NOW()
              WHERE id = ?',
            [$this->id]
        );
    }


    public function getId(): int
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->first_name;
    }

    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    public function getDisplayName(): ?string
    {
        return $this->display_name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getLastLoginAt(): ?string
    {
        return $this->last_login_at;
    }

    public function getEmailVerifiedAt(): ?string
    {
        return $this->email_verified_at;
    }

    public function getAttempts(): ?int
    {
        return $this->attempts;
    }

    public function getCreatedAt(): string
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updated_at;
    }

    public function getDeletedAt(): ?string
    {
        return $this->deleted_at;
    }


    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setFirstName(string $first_name): void
    {
        $this->first_name = $first_name;
    }

    public function setLastName(?string $last_name): void
    {
        $this->last_name = $last_name;
    }

    public function setDisplayName(?string $display_name): void
    {
        $this->display_name = $display_name;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function setLastLoginAt(?string $last_login_at): void
    {
        $this->last_login_at = $last_login_at;
    }

    public function setEmailVerifiedAt(?string $email_verified_at): void
    {
        $this->email_verified_at = $email_verified_at;
    }

    public function setAttempts(?int $attempts): void
    {
        $this->attempts = $attempts;
    }

    public function setCreatedAt(string $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function setUpdatedAt(?string $updated_at): void
    {
        $this->updated_at = $updated_at;
    }

    public function setDeletedAt(?string $deleted_at): void
    {
        $this->deleted_at = $deleted_at;
    }

}
