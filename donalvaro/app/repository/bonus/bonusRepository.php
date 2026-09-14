<?php

declare(strict_types=1);

class bonusRepository
{
    private bonusesModel $model;

    public function __construct()
    {
        $this->model = new bonusesModel();
    }

    public function searchBonuses(array $filters = [], bool $asObject = true): array {

        $conditions = [];
        $params = [];

        $name = isset($filters['name']) ? trim(strip_tags((string) $filters['name'])) : '';
        $facility_id = (int) ($filters['facility_id'] ?? 0);
        $status = $filters['status'] ?? '';

        /*
         * Excluir bonos eliminados.
         */
        $conditions[] = 'b.status <> ?';
        $params[] = bonusesModel::STATUS_DELETED;

        /*
         * Buscar por nombre.
         */
        if ($name !== '') {
            $conditions[] = 'b.name LIKE ?';
            $params[] = '%' . $name . '%';
        }

        /*
         * Filtrar por instalación.
         */
        if ($facility_id > 0) {
            $conditions[] = 'EXISTS (
            SELECT 1
            FROM wi_bonuses_facilities bff
            WHERE bff.bonus_id = b.id
            AND bff.facility_id = ?
        )';
            $params[] = $facility_id;
        }

        /*
         * Filtrar por estado.
         */
        if ($status !== '') {
            $conditions[] = 'b.status = ?';
            $params[] = (int) $status;
        }

        $where = '';

        if (!empty($conditions)) {
            $where = ' WHERE ' . implode(' AND ', $conditions);
        }

        return $this->model->findByFilters($where, $params, true);
    }

    public function getPinEditData(int $pin_id): ?object {

        if ($pin_id <= 0) {
            return null;
        }

        $model = new accessPinsModel();

        $pin = $model->findEditDataById($pin_id, true);

        if (empty($pin)) {
            return null;
        }

        $encryption_service = new pinEncryptionService();

        $pin->pin = $encryption_service->decrypt(
                $pin->pin_code_encrypted
        );

        return $pin;
    }
}
