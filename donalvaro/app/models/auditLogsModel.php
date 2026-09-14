<?php

declare(strict_types=1);

class auditLogsModel extends baseModel {

    protected const TABLE = 'wi_audit_logs';
    protected const PRIMARY_KEY = 'id';

    private int $id = 0;
    private ?int $user_id = null;
    private ?int $target_id = null;
    private ?string $table_name = null;
    private string $event = '';
    private ?string $function_name = null;
    private ?string $event_description = null;
    private ?string $old_values = null;
    private ?string $new_values = null;
    private ?string $url = null;
    private ?string $ip_address = null;
    private ?string $user_agent = null;
    private string $created_at = '';

    /**
     * Obtiene los registros de auditoría asociados
     * a un target y una o varias tablas.
     *
     * @param int $target_id
     * @param array $table_names
     *
     * @return array
     */
    public function findByTarget(int $target_id, array $table_names, bool $asObject = false): array {

        if ($target_id <= 0 || empty($table_names)) {
            return [];
        }

        $placeholders = implode(
                ',',
                array_fill(
                        0,
                        count($table_names),
                        '?'
                )
        );

        $params = array_merge(
                [$target_id],
                $table_names
        );

        $sql = 'SELECT
            a.*,
            up.first_name,
            up.last_name
         FROM ' . self::TABLE . ' a
         LEFT JOIN wi_users up
                ON up.id = a.user_id
         WHERE a.target_id = ?
           AND a.table_name IN (' . $placeholders . ')
         ORDER BY a.created_at DESC,
                  a.id DESC';

        return $this->db()->select($sql, $params, $asObject);
    }

    /**
     * Obtiene los registros de auditoría asociados
     * a una o varias tablas.
     *
     * @param array $table_names
     * @param bool $asObject
     *
     * @return array
     */
    public function findByTables(array $table_names, bool $asObject = false): array {

        if (empty($table_names)) {
            return [];
        }

        $placeholders = implode(
                ',',
                array_fill(
                        0,
                        count($table_names),
                        '?'
                )
        );

        $sql = 'SELECT
            a.*,
            up.first_name,
            up.last_name
        FROM ' . self::TABLE . ' a
        LEFT JOIN wi_users up
            ON up.id = a.user_id
        WHERE a.table_name IN (' . $placeholders . ')
        ORDER BY a.created_at DESC,
                 a.id DESC';

        return $this->db()->select(
                        $sql,
                        $table_names,
                        $asObject
                );
    }

    /**
     * Obtiene los registros de auditoría de usuarios
     * pertenecientes a un rol concreto.
     *
     * @param int $role_id
     * @param bool $asObject
     *
     * @return array
     */
    public function findUsersAuditByRole(int $role_id, bool $asObject = false): array {

        if ($role_id <= 0) {
            return [];
        }

        $table_names = [
            'wi_users',
            'wi_users_profile',
            'wi_users_bonuses',
            'wi_users_tokens',
            'wi_users_roles'
        ];

        $placeholders = implode(
                ',',
                array_fill(
                        0,
                        count($table_names),
                        '?'
                )
        );

        $params = $table_names;
        $params[] = $role_id;

        $sql = 'SELECT DISTINCT
            a.*,
            up.first_name,
            up.last_name
        FROM ' . self::TABLE . ' a

        LEFT JOIN wi_users up
            ON up.id = a.user_id

        INNER JOIN wi_users_roles ur
            ON ur.user_id = a.target_id

        WHERE a.table_name IN (' . $placeholders . ')
          AND ur.role_id = ?

        ORDER BY a.created_at DESC,
                 a.id DESC';

        return $this->db()->select(
                        $sql,
                        $params,
                        $asObject
                );
    }

    public function add(): int {
        return $this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                user_id,
                target_id,
                table_name,
                event,
                function_name,
                event_description,
                old_values,
                new_values,
                url,
                ip_address,
                user_agent
            )
            VALUES
            (
                ?,?,?,?,?,?,?,?,?,?,?
            )',
            [
                $this->user_id,
                $this->target_id,
                $this->table_name,
                $this->event,
                $this->function_name,
                $this->event_description,
                $this->old_values,
                $this->new_values,
                $this->url,
                $this->ip_address,
                $this->user_agent
            ]
        );
    }

    
    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function getTargetId(): ?int
    {
        return $this->target_id;
    }

    public function getTableName(): ?string
    {
        return $this->table_name;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function getFunctionName(): ?string
    {
        return $this->function_name;
    }

    public function getEventDescription(): ?string
    {
        return $this->event_description;
    }

    public function getOldValues(): ?string
    {
        return $this->old_values;
    }

    public function getNewValues(): ?string
    {
        return $this->new_values;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getIpAddress(): ?string
    {
        return $this->ip_address;
    }

    public function getUserAgent(): ?string
    {
        return $this->user_agent;
    }

    public function getCreatedAt(): string
    {
        return $this->created_at;
    }


    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setUserId(?int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function setTargetId(?int $target_id): void
    {
        $this->target_id = $target_id;
    }

    public function setTableName(?string $table_name): void
    {
        $this->table_name = $table_name;
    }

    public function setEvent(string $event): void
    {
        $this->event = $event;
    }

    public function setFunctionName(?string $function_name): void
    {
        $this->function_name = $function_name;
    }

    public function setEventDescription(?string $event_description): void
    {
        $this->event_description = $event_description;
    }

    public function setOldValues(?string $old_values): void
    {
        $this->old_values = $old_values;
    }

    public function setNewValues(?string $new_values): void
    {
        $this->new_values = $new_values;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function setIpAddress(?string $ip_address): void
    {
        $this->ip_address = $ip_address;
    }

    public function setUserAgent(?string $user_agent): void
    {
        $this->user_agent = $user_agent;
    }

    public function setCreatedAt(string $created_at): void
    {
        $this->created_at = $created_at;
    }

}
