<?php

declare(strict_types=1);

/**
 * usersRepository
 *
 * Prepara los filtros del listado de usuarios.
 *
 * @author Sandra Campos
 * @copyright Wilowi
 * @version 1.0.0
 * @since 17 ago 2026
 */
class usersRepository
{
    use debugTrait;

    private usersModel $model_user;

    public function __construct()
    {
        $this->model_user = new usersModel();
    }

    /**
     * Busca usuarios según los filtros recibidos.
     *
     * @param array $filters
     * @param int $role_id
     *
     * @return array
     */
    public function searchUsers(array $filters = [], int $role_id): array {

        $conditions = [];
        $params = [];

        $name = isset($filters['name']) ? trim(strip_tags((string) $filters['name'])) : '';
        $last_name = isset($filters['last_name']) ? trim(strip_tags((string) $filters['last_name'])) : '';
        $email = isset($filters['email']) ? trim(strip_tags((string) $filters['email'])) : '';
        $status = $filters['status'] ?? '';

        /*
         * Filtrar por rol.
         */
        $conditions[] = 'ur.role_id = ?';
        $params[] = $role_id;

        /*
         * Excluir usuarios eliminados.
         */
        $conditions[] = 'u.deleted_at IS NULL';

        /*
         * Buscar por nombre.
         */
        if ($name !== '') {
            $conditions[] = 'u.first_name LIKE ?';
            $params[] = '%' . $name . '%';
        }

        /*
         * Buscar por apellidos.
         */
        if ($last_name !== '') {
            $conditions[] = 'u.last_name LIKE ?';
            $params[] = '%' . $last_name . '%';
        }

        /*
         * Buscar por email.
         */
        if ($email !== '') {
            $conditions[] = 'u.email LIKE ?';
            $params[] = '%' . $email . '%';
        }

        /*
         * Filtrar por estado.
         */
        if ($status !== '') {
            $conditions[] = 'u.status = ?';
            $params[] = (int) $status;
        }

        $where = '';

        if (!empty($conditions)) {
            $where = ' WHERE ' . implode(' AND ', $conditions);
        }

        return $this->model_user->findByFilters($where, $params, true);
    }
}