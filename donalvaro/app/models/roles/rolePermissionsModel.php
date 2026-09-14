<?php

declare(strict_types=1);

class rolePermissionsModel extends baseModel
{
    protected const TABLE = 'wi_role_permissions';
    protected const PRIMARY_KEY = 'role_id';

    private int $role_id = 0;
    private int $permission_id = 0;


    public function add(): int
    {
        return $this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                role_id,
                permission_id
            )
            VALUES
            (
                ?,?
            )',
            [
                $this->role_id,
                $this->permission_id
            ]
        );
    }

    public function update(): bool
    {
        if (empty($this->id)) {
            throw new RuntimeException('No se puede actualizar sin role_id.');
        }

        return $this->db()->execute(
            'UPDATE ' . self::TABLE . '
                SET
                    permission_id = ?
              WHERE role_id = ?',
            [
                $this->permission_id,
                $this->role_id
            ]
        );
    }


    public function getRoleId(): int
    {
        return $this->role_id;
    }

    public function getPermissionId(): int
    {
        return $this->permission_id;
    }


    public function setRoleId(int $role_id): void
    {
        $this->role_id = $role_id;
    }

    public function setPermissionId(int $permission_id): void
    {
        $this->permission_id = $permission_id;
    }

}
