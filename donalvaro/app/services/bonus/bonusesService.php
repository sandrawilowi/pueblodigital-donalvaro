<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 7 sept 2026
 */

class bonusesService {

    use debugTrait;

    private bonusesModel $bonuses_model;
    private bonusesFacilitiesModel $bonuses_facilities_model;
    private usersBonusesModel $users_bonuses_model;
    private auditLogsService $audit_service;

    public function __construct() {
        
        $this->bonuses_model = new bonusesModel();
        $db = $this->bonuses_model->getDb();

        $this->bonuses_facilities_model = new bonusesFacilitiesModel($db);
        $this->users_bonuses_model = new usersBonusesModel($db);
        $this->audit_service = new auditLogsService();
    }

    public function addBonus(stdClass $params): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {
            
            $this->checkModuleEnabled();

            $name = trim($params->bonus_name ?? '');
            $description = trim($params->bonus_description ?? '');
            $bonus_type = trim($params->bonus_type ?? '');
            $total_uses = isset($params->bonus_total_uses) ? (int) $params->bonus_total_uses : 0;
            $price = isset($params->bonus_price) && $params->bonus_price !== '' ? (float) $params->bonus_price : null;
            $validity_mode = trim($params->bonus_validity_mode ?? '');
            $validity_days = isset($params->bonus_validity_days) && $params->bonus_validity_days !== '' ? (int) $params->bonus_validity_days : null;
            $facility_ids = isset($params->facility_ids) && is_array($params->facility_ids) ? $params->facility_ids : [];

            $facility_ids = array_values(array_unique(array_filter(array_map('intval', $facility_ids), fn($id) => $id > 0)));

            if ($name === '') {
                throw new InvalidArgumentException('El nombre del bono es obligatorio.');
            }

            if (empty($facility_ids)) {
                throw new InvalidArgumentException('Debe seleccionar al menos una instalación.');
            }

            if (!in_array($bonus_type, [
                        bonusesModel::TYPE_USES,
                        bonusesModel::TYPE_TIME
                            ], true)) {
                throw new InvalidArgumentException('El tipo de bono no es válido.');
            }

            if ($bonus_type === bonusesModel::TYPE_USES) {

                if ($total_uses <= 0) {
                    throw new InvalidArgumentException('El número de usos del bono no es válido.');
                }
            } else {
                $total_uses = 0;
            }

            if ($price === null || $price < 0) {
                throw new InvalidArgumentException('El precio del bono no es válido.');
            }

            if (!in_array($validity_mode, [
                        bonusesModel::VALIDITY_NONE,
                        bonusesModel::VALIDITY_DAYS,
                        bonusesModel::VALIDITY_CALENDAR_WEEK,
                        bonusesModel::VALIDITY_CALENDAR_MONTH,
                        bonusesModel::VALIDITY_CALENDAR_YEAR
                            ], true)) {
                throw new InvalidArgumentException('El tipo de validez del bono no es válido.');
            }

            if ($validity_mode === bonusesModel::VALIDITY_DAYS) {

                if ($bonus_type === bonusesModel::TYPE_TIME) {

                    if ($validity_days === null || $validity_days <= 0) {
                        throw new InvalidArgumentException('La validez del bono no es válida.');
                    }
                } elseif ($validity_days !== null && $validity_days <= 0) {

                    throw new InvalidArgumentException('La validez del bono no es válida.');
                }
            } else {

                $validity_days = null;
            }

            /*
             * Comprobamos que todas las instalaciones existen
             * y están disponibles.
             */
            $facilities_model = new facilitiesModel();

            foreach ($facility_ids as $facility_id) {

                $facility = $facilities_model->findById($facility_id);

                if ($facility === null) {
                    throw new RuntimeException('Una de las instalaciones seleccionadas no existe.');
                }

                if ((int) $facility['status'] === facilitiesModel::STATUS_DELETED) {
                    throw new RuntimeException('Una de las instalaciones seleccionadas está eliminada.');
                }
            }

            /*
             * Bono.
             */
            $this->bonuses_model->setName($name);
            $this->bonuses_model->setDescription($description !== '' ? $description : null);
            $this->bonuses_model->setBonusType($bonus_type);
            $this->bonuses_model->setTotalUses($total_uses);
            $this->bonuses_model->setPrice($price);
            $this->bonuses_model->setValidityDays($validity_days);
            $this->bonuses_model->setValidityMode($validity_mode);
            $this->bonuses_model->setStatus(bonusesModel::STATUS_ACTIVE);

            /*
             * Todo debe quedar guardado o no guardar nada.
             */
            $this->bonuses_model->beginTransaction();

            try {

                $bonus_id = $this->bonuses_model->add();

                if ($bonus_id <= 0) {
                    throw new RuntimeException('No se ha podido guardar el bono.');
                }

                foreach ($facility_ids as $facility_id) {

                    $this->bonuses_facilities_model->setBonusId($bonus_id);
                    $this->bonuses_facilities_model->setFacilityId($facility_id);

                    $relation_id = $this->bonuses_facilities_model->add();

                    if ($relation_id <= 0) {
                        throw new RuntimeException('No se han podido asociar las instalaciones al bono.');
                    }
                }

                $this->bonuses_model->commit();

            } catch (Throwable $e) {

                $this->bonuses_model->rollBack();

                throw $e;
            }

            $new_values = [
                'bonus_id' => $bonus_id,
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'bonus_type' => $bonus_type,
                'total_uses' => $total_uses,
                'price' => $price,
                'validity_days' => $validity_days,
                'validity_mode' => $validity_mode,
                'facility_ids' => $facility_ids,
                'status' => bonusesModel::STATUS_ACTIVE
            ];

            $this->audit_service->insert(
                    'wi_bonuses',
                    $bonus_id,
                    $new_values,
                    __METHOD__,
                    'Creación de bono'
            );

            $result['success'] = true;
            $result['message'] = 'El bono se ha añadido correctamente.';
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    public function changeBonusStatus(int $bonus_id, int $status): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {
            
            $this->checkModuleEnabled();

            if ($bonus_id <= 0) {
                throw new InvalidArgumentException('El identificador del bono no es válido.');
            }

            if (!in_array($status, [
                        bonusesModel::STATUS_INACTIVE,
                        bonusesModel::STATUS_ACTIVE
                            ], true)) {
                throw new InvalidArgumentException('El estado del bono no es válido.');
            }

            $bonus = $this->bonuses_model->findById($bonus_id);

            if ($bonus === null) {
                throw new RuntimeException('El bono no existe.');
            }

            if ((int) $bonus['status'] === bonusesModel::STATUS_DELETED) {
                throw new RuntimeException('El bono está eliminado.');
            }

            $updated = $this->bonuses_model->updateStatusById($bonus_id, $status);

            if (!$updated) {
                throw new RuntimeException('No se ha podido actualizar el estado del bono.');
            }

            $this->audit_service->update(
                    'wi_bonuses',
                    $bonus_id,
                    $bonus,
                    [
                        'id' => $bonus_id,
                        'status' => $status
                    ],
                    __METHOD__,
                    [],
                    $status === bonusesModel::STATUS_ACTIVE ? 'Activación de bono' : 'Desactivación de bono'
            );

            $result['success'] = true;
            $result['message'] = $status === bonusesModel::STATUS_ACTIVE ? 'Bono activado correctamente.' : 'Bono desactivado correctamente.';
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    public function editBonus(stdClass $params): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {
            
            $this->checkModuleEnabled();

            $bonus_id = isset($params->bonus_id) ? (int) $params->bonus_id : 0;

            if ($bonus_id <= 0) {
                throw new InvalidArgumentException('El identificador del bono no es válido.');
            }

            $bonus = $this->bonuses_model->findById($bonus_id);

            if ($bonus === null) {
                throw new RuntimeException('El bono no existe.');
            }

            if ((int) $bonus['status'] === bonusesModel::STATUS_DELETED) {
                throw new RuntimeException('El bono está eliminado.');
            }
            
            
            $old_facilities = $this->bonuses_facilities_model->findByBonusId($bonus_id, false);
            $old_facility_ids = array_map(
                    fn($item) => (int) $item['facility_id'],
                    $old_facilities
            );

            $bonus['facility_ids'] = $old_facility_ids;

            $name = trim($params->bonus_name ?? '');
            $description = trim($params->bonus_description ?? '');
            $bonus_type = trim($params->bonus_type ?? '');
            $total_uses = isset($params->bonus_total_uses) ? (int) $params->bonus_total_uses : 0;
            $price = isset($params->bonus_price) && $params->bonus_price !== '' ? (float) $params->bonus_price : null;
            $validity_mode = trim($params->bonus_validity_mode ?? '');
            $validity_days = isset($params->bonus_validity_days) && $params->bonus_validity_days !== '' ? (int) $params->bonus_validity_days : null;
            $pending_payment_instructions = trim($params->pending_payment_instructions ?? '');
            $facility_ids = isset($params->facility_ids) && is_array($params->facility_ids) ? $params->facility_ids : [];
            $facility_ids = array_values(array_unique(array_filter(array_map('intval', $facility_ids), fn($id) => $id > 0)));

            if ($name === '') {
                throw new InvalidArgumentException('El nombre del bono es obligatorio.');
            }

            if (empty($facility_ids)) {
                throw new InvalidArgumentException('Debe seleccionar al menos una instalación.');
            }

            if (!in_array($bonus_type, [
                        bonusesModel::TYPE_USES,
                        bonusesModel::TYPE_TIME
                            ], true)) {
                throw new InvalidArgumentException('El tipo de bono no es válido.');
            }

            if ($bonus_type === bonusesModel::TYPE_USES) {

                if ($total_uses <= 0) {
                    throw new InvalidArgumentException('El número de usos del bono no es válido.');
                }
            } else {
                $total_uses = 0;
            }

            if ($price === null || $price < 0) {
                throw new InvalidArgumentException('El precio del bono no es válido.');
            }

            if (!in_array($validity_mode, [
                        bonusesModel::VALIDITY_NONE,
                        bonusesModel::VALIDITY_DAYS,
                        bonusesModel::VALIDITY_CALENDAR_WEEK,
                        bonusesModel::VALIDITY_CALENDAR_MONTH,
                        bonusesModel::VALIDITY_CALENDAR_YEAR
                            ], true)) {
                throw new InvalidArgumentException('El tipo de validez del bono no es válido.');
            }

            if ($validity_mode === bonusesModel::VALIDITY_DAYS) {

                if ($bonus_type === bonusesModel::TYPE_TIME) {

                    if ($validity_days === null || $validity_days <= 0) {
                        throw new InvalidArgumentException('La validez del bono no es válida.');
                    }
                } elseif ($validity_days !== null && $validity_days <= 0) {

                    throw new InvalidArgumentException('La validez del bono no es válida.');
                }
            } else {

                $validity_days = null;
            }

            /*
             * Validamos las instalaciones antes de modificar nada.
             */
            $facilities_model = new facilitiesModel();

            foreach ($facility_ids as $facility_id) {

                $facility = $facilities_model->findById($facility_id);

                if ($facility === null) {
                    throw new RuntimeException('Una de las instalaciones seleccionadas no existe.');
                }

                if ((int) $facility['status'] === facilitiesModel::STATUS_DELETED) {
                    throw new RuntimeException('Una de las instalaciones seleccionadas está eliminada.');
                }
            }

            $new_values = [
                'id' => $bonus_id,
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'bonus_type' => $bonus_type,
                'total_uses' => $total_uses,
                'price' => $price,
                'validity_days' => $validity_days,
                'validity_mode' => $validity_mode,
                'pending_payment_instructions' => $pending_payment_instructions !== '' ? $pending_payment_instructions : null,
                'facility_ids' => $facility_ids,
                'status' => (int) $bonus['status']
            ];

            $this->bonuses_model->setId($bonus_id);
            $this->bonuses_model->setName($name);
            $this->bonuses_model->setDescription($new_values['description']);
            $this->bonuses_model->setBonusType($bonus_type);
            $this->bonuses_model->setTotalUses($total_uses);
            $this->bonuses_model->setPrice($price);
            $this->bonuses_model->setValidityDays($validity_days);
            $this->bonuses_model->setValidityMode($validity_mode);
            $this->bonuses_model->setPendingPaymentInstructions($new_values['pending_payment_instructions']);
            $this->bonuses_model->setStatus((int) $bonus['status']);

            $this->bonuses_model->beginTransaction();

            try {

                $updated = $this->bonuses_model->update();

                if (!$updated) {
                    throw new RuntimeException('No se ha podido actualizar el bono.');
                }

                /*
                 * Sincronizamos las instalaciones.
                 *
                 * Como son muy pocas relaciones, aquí es más
                 * sencillo borrar las relaciones y recrearlas.
                 */
                if (!$this->bonuses_facilities_model->deleteByBonusId($bonus_id)) {
                    throw new RuntimeException('No se han podido actualizar las instalaciones del bono.');
                }

                foreach ($facility_ids as $facility_id) {

                    $this->bonuses_facilities_model->setBonusId($bonus_id);
                    $this->bonuses_facilities_model->setFacilityId($facility_id);

                    $relation_id = $this->bonuses_facilities_model->add();

                    if ($relation_id <= 0) {
                        throw new RuntimeException('No se han podido asociar las instalaciones al bono.');
                    }
                }

                $this->bonuses_model->commit();
            } catch (Throwable $e) {

                $this->bonuses_model->rollBack();

                throw $e;
            }

            $this->audit_service->update(
                    'wi_bonuses',
                    $bonus_id,
                    $bonus,
                    $new_values,
                    __METHOD__,
                    [],
                    'Edición de bono'
            );

            $result['success'] = true;
            $result['message'] = 'El bono se ha actualizado correctamente.';
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    public function deleteBonus(int $bonus_id): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            $this->checkModuleEnabled();

            if ($bonus_id <= 0) {
                throw new InvalidArgumentException('El identificador del bono no es válido.');
            }

            $bonus = $this->bonuses_model->findById($bonus_id);

            if ($bonus === null) {
                throw new RuntimeException('El bono no existe.');
            }

            if ((int) $bonus['status'] === bonusesModel::STATUS_DELETED) {
                throw new RuntimeException('El bono ya está eliminado.');
            }

            if ($this->users_bonuses_model->existsByBonusId($bonus_id)) {
                throw new RuntimeException(
                                'No se puede eliminar este bono porque ha sido adquirido o asignado a clientes. Puede desactivarlo para impedir nuevas compras o asignaciones.'
                        );
            }

            $this->bonuses_model->setId($bonus_id);

            $deleted = $this->bonuses_model->softDelete();

            if (!$deleted) {
                throw new RuntimeException('No se ha podido eliminar el bono.');
            }

            $this->audit_service->delete(
                    'wi_bonuses',
                    $bonus_id,
                    $bonus,
                    __METHOD__,
                    'Eliminación de bono'
            );

            $result['success'] = true;
            $result['message'] = 'El bono se ha eliminado correctamente.';
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }
    
    public function addBonusPaymentMethod(int $bonus_id, stdClass $params): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if ($bonus_id <= 0) {
                throw new InvalidArgumentException('El identificador del bono no es válido.');
            }

            $bonus = $this->bonuses_model->findById($bonus_id);

            if (empty($bonus)) {
                throw new RuntimeException('El bono no existe.');
            }

            $payment_method_ids = isset($params->payment_method_ids) && is_array($params->payment_method_ids) ? $params->payment_method_ids : [];

            $payment_method_ids = array_values(array_unique(array_filter(array_map('intval', $payment_method_ids), fn($id) => $id > 0)));

            if (empty($payment_method_ids)) {
                throw new InvalidArgumentException('Debe seleccionar al menos un método de pago.');
            }

            $payment_methods_model = new paymentMethodsModel();
            $bonus_payment_method_model = new bonusesPaymentMethodsModel();

            $inserted = 0;

            foreach ($payment_method_ids as $payment_method_id) {

                $payment_method = $payment_methods_model->findById($payment_method_id);

                if ($payment_method === null) {
                    throw new RuntimeException('Uno de los métodos de pago seleccionados no existe.');
                }

                if ((int) $payment_method['status'] !== paymentMethodsModel::STATUS_ACTIVE) {
                    throw new RuntimeException('Uno de los métodos de pago seleccionados no está disponible.');
                }

                $existing = $bonus_payment_method_model->findByBonusAndPaymentMethod($bonus_id, $payment_method_id);

                if ($existing !== null) {
                    continue;
                }

                $bonus_payment_method_model = new bonusesPaymentMethodsModel();
                $bonus_payment_method_model->setBonusId($bonus_id);
                $bonus_payment_method_model->setPaymentMethodId($payment_method_id);

                $bonus_payment_method_id = $bonus_payment_method_model->add();

                if ($bonus_payment_method_id <= 0) {
                    throw new RuntimeException('No se ha podido añadir uno de los métodos de pago al bono.');
                }

                $new_values = [
                    'bonus_payment_method_id' => $bonus_payment_method_id,
                    'bonus_id' => $bonus_id,
                    'payment_method_id' => $payment_method_id
                ];

                $this->audit_service->insert(
                        'wi_bonuses_payment_methods',
                        $bonus_id,
                        $new_values,
                        __METHOD__,
                        'Añadir método de pago a bono'
                );

                $inserted++;
            }

            if ($inserted === 0) {
                throw new RuntimeException('Los métodos de pago seleccionados ya están asociados al bono.');
            }

            $result['success'] = true;
            $result['message'] = $inserted === 1 ? 'El método de pago se ha añadido correctamente.' : 'Los métodos de pago se han añadido correctamente.';
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }
    
    public function removeBonusPaymentMethod(int $bonus_id, int $method_id): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if ($bonus_id <= 0) {
                throw new InvalidArgumentException('El identificador del bono no es válido.');
            }

            if ($method_id <= 0) {
                throw new InvalidArgumentException('El identificador del método de pago no es válido.');
            }

            $bonus_payment_method_model = new bonusesPaymentMethodsModel();

            $bonus_payment_method = $bonus_payment_method_model->findByBonusAndPaymentMethod(
                    $bonus_id,
                    $method_id
            );

            if ($bonus_payment_method === null) {
                throw new RuntimeException('El método de pago no está asociado a este bono.');
            }

            $deleted = $bonus_payment_method_model->deleteById((int) $bonus_payment_method['id']);

            if (!$deleted) {
                throw new RuntimeException('No se ha podido quitar el método de pago de la instalación.');
            }

            $this->audit_service->delete(
                    'wi_bonuses_payment_methods',
                    $bonus_id,
                    [
                        'bonus_payment_method_id' => (int) $bonus_payment_method['id'],
                        'bonus_id' => $bonus_id,
                        'payment_method_id' => $method_id
                    ],
                    __METHOD__,
                    'Quitar método de pago del bono'
            );

            $result['success'] = true;
            $result['message'] = 'El método de pago se ha quitado correctamente.';
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    private function checkModuleEnabled(): void {

        if (!_BONUSES_ENABLED) {
            throw new RuntimeException('El módulo de bonos no está habilitado.');
        }
    }
}
