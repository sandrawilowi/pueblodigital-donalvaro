<?php

declare(strict_types=1);

class accessPinsRepository
{
    private accessPinsModel $model;

    public function __construct()
    {
        $this->model = new accessPinsModel();
    }

    /**
     * Busca un PIN por dispositivo y código PIN.
     */
    public function findByDeviceAndPin(
        int $ttlock_device_id,
        string $pin
    ): array {

        if ($ttlock_device_id <= 0 || $pin === '') {
            return [];
        }

        $pin_hash = hash('sha256', $pin);

        $result = $this->model->findByDeviceAndPinHash(
            $ttlock_device_id,
            $pin_hash
        );

        return is_array($result)
            ? $result
            : [];
    }

    public function existsActivePin(int $device_id,string $pin): bool {

        $pin_hash = hash('sha256', $pin);

        $result = $this->model->findActiveByDeviceAndPinHash(
                $device_id,
                $pin_hash
        );

        return !empty($result);
    }

    public function existsActivePinForDevices(string $pin, array $device_ids): bool {

        if ($pin === '' || empty($device_ids)) {
            return false;
        }

        $pin_hash = hash('sha256', $pin);

        $device_ids = array_values(array_unique(array_filter(array_map('intval', $device_ids), fn($id) => $id > 0)));

        if (empty($device_ids)) {
            return false;
        }

        $result = $this->model->findActiveByDevicesAndPinHash($device_ids, $pin_hash);

        return !empty($result);
    }

    public function searchPins(array $filters = [], bool $asObject = true): array {

        $conditions = [];
        $params = [];

        $pin = trim((string) ($filters['pin'] ?? ''));
        $facility_id = (int) ($filters['facility_id'] ?? 0);
        $status_id = (int) ($filters['status_id'] ?? 0);

        if ($pin !== '') {
            $conditions[] = 'ap.pin_code_hash = ?';
            $params[] = hash('sha256', $pin);
        }

        if ($facility_id > 0) {
            $conditions[] = 'ap.facility_id = ?';
            $params[] = $facility_id;
        }

        if ($status_id > 0) {
            $conditions[] = 'ap.pin_status_id = ?';
            $params[] = $status_id;
        } else {
            $conditions[] = 'ap.revoked_at IS NULL';
        }

        $where = '';

        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        $model = new accessPinsModel();

        $results = $model->findByFilters($where, $params, $asObject);

        $encryption_service = new pinEncryptionService();

        foreach ($results as &$result) {

            if ($asObject) {
                $result->pin = $encryption_service->decrypt($result->pin_code_encrypted);
            } else {
                $result['pin'] = $encryption_service->decrypt($result['pin_code_encrypted']);
            }
        }

        return $results;
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
