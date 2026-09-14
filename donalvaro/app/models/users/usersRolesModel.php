<?php

declare(strict_types=1);

class usersRolesModel extends baseModel
{
    protected const TABLE = 'wi_users_roles';
    protected const PRIMARY_KEY = 'user_id';

    private int $user_id = 0;
    private int $role_id = 0;


    public function add(): bool {

        return $this->db()->execute(
                        'INSERT INTO ' . self::TABLE . '
        (
            user_id,
            role_id
        )
        VALUES
        (
            ?,?
        )',
                        [
                            $this->user_id,
                            $this->role_id
                        ]
                );
    }

    public function update(): bool
    {
        if (empty($this->user_id)) {
            throw new RuntimeException('No se puede actualizar sin user_id.');
        }

        return $this->db()->execute(
            'UPDATE ' . self::TABLE . '
                SET
                    role_id = ?
              WHERE user_id = ?',
            [
                $this->role_id,
                $this->user_id
            ]
        );
    }


    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function getRoleId(): int
    {
        return $this->role_id;
    }


    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function setRoleId(int $role_id): void
    {
        $this->role_id = $role_id;
    }

}
