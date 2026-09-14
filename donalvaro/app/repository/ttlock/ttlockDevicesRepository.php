<?php

declare(strict_types=1);

/**
 * ttlockDevicesRepository
 *
 * Prepara los filtros del listado de cerraduras.
 *
 * @author Sandra Campos
 * @copyright Wilowi
 * @version 1.0.0
 * @since 5 ago 2026
 */
class ttlockDevicesRepository
{    
    use debugTrait;
    
    private ttlockDevicesModel $model_device;
    private ttlockGatewaysModel $model_gw;

    public function __construct()
    {
        $this->model_device = new ttlockDevicesModel();
        $this->model_gw = new ttlockGatewaysModel();
    }

    /**
     * Busca cerraduras según los filtros recibidos.
     *
     * @param array $filters
     *
     * @return array
     */
    public function searchLocks(array $filters = []): array {
        $conditions = [];
        $params = [];

        $name = isset($filters['name']) ? trim(strip_tags((string) $filters['name'])) : '';

        $status = $filters['status'] ?? '';

        /*
         * Buscar por alias.
         */
        if ($name !== '') {
            $conditions[] = 'lock_alias LIKE ?';
            $params[] = '%' . $name . '%';
        }

        /*
         * Filtrar por estado.
         */
        if ($status !== '') {
            $conditions[] = 'status = ?';
            $params[] = (int) $status;
        }

        $where = '';

        if (!empty($conditions)) {
            $where = ' WHERE ' . implode(' AND ', $conditions);
        }

        return $this->model_device->findByFilters($where, $params, true);
    }
    
    /**
     * Busca gateways según los filtros recibidos.
     *
     * @param array $filters
     *
     * @return array
     */
    public function searchGateways(array $filters = []): array {
        $conditions = [];
        $params = [];

        $name = isset($filters['name']) ? trim(strip_tags((string) $filters['name'])) : '';

        $status = $filters['status'] ?? '';

        /*
         * Buscar por alias.
         */
        if ($name !== '') {
            $conditions[] = 'gateway_name LIKE ?';
            $params[] = '%' . $name . '%';
        }

        /*
         * Filtrar por estado.
         */
        if ($status !== '') {
            $conditions[] = 'status = ?';
            $params[] = (int) $status;
        }

        $where = '';

        if (!empty($conditions)) {
            $where = ' WHERE ' . implode(' AND ', $conditions);
        }

        return $this->model_gw->findByFilters($where, $params, true);
    }
    
}
