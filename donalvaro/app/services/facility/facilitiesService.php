<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 14 ago 2026
 */
class facilitiesService {

    use debugTrait;

    private facilitiesModel $facilities_model;
    private facilitiesImagesModel $facilities_images_model;
    private auditLogsService $audit_service;

    private const IMAGES_PATH = __DIR__ . '/../../../assets/images/facilities/';

    public function __construct() {
        $this->facilities_model = new facilitiesModel();
        $this->facilities_images_model = new facilitiesImagesModel();
        $this->audit_service = new auditLogsService();
    }

    public function createFacility(stdClass $params, array $files = []): array {

        $result = [
            'success' => false,
            'message' => '',
            'facility_id' => null
        ];

        try {

            /*
             * Datos básicos.
             */
            $name = trim($params->facility_name ?? '');
            $description = trim($params->facility_description ?? '');
            $address = trim($params->facility_address ?? '');
            $city = trim($params->facility_city ?? '');
            $postal_code = trim($params->facility_postal_code ?? '');

            /*
             * Provincia opcional.
             */
            $province_id = null;

            if (
                    isset($params->facility_province_id) && $params->facility_province_id !== ''
            ) {
                $province_id = (int) $params->facility_province_id;

                if ($province_id <= 0) {
                    throw new InvalidArgumentException(
                                    'La provincia seleccionada no es válida.'
                            );
                }
            }

            /*
             * Campos obligatorios.
             */
            if ($name === '') {
                throw new InvalidArgumentException(
                                'El nombre de la instalación es obligatorio.'
                        );
            }

            if ($description === '') {
                throw new InvalidArgumentException(
                                'La descripción de la instalación es obligatoria.'
                        );
            }

            /*
             * Capacidad reservable.
             * Como mínimo siempre existe una unidad reservable.
             */
            $capacity = 1;

            if (isset($params->facility_capacity) && $params->facility_capacity !== '') {

                $capacity = (int) $params->facility_capacity;

                if ($capacity < 1) {
                    throw new InvalidArgumentException(
                                    'La capacidad de la instalación no es válida.'
                            );
                }
            }

            /*
             * Los checkbox que no están marcados
             * no llegan en el formulario.
             */
            $booking_enabled = isset($params->booking_enabled) ? 1 : 0;

            $access_enabled = isset($params->access_enabled) ? 1 : 0;

            $requires_guest_information = isset($params->requires_guest_information) ? 1 : 0;
            
            $booking_type_id = null;

            if ($booking_enabled) {

                $booking_type_id = isset($params->booking_type_id) ? (int) $params->booking_type_id : 0;

                if ($booking_type_id <= 0) {
                    throw new InvalidArgumentException(
                                    'Debe seleccionar un tipo de reserva.'
                            );
                }
            }

            /*
             * Cargamos el modelo.
             */
            $this->facilities_model->setName(
                    $name
            );

            $this->facilities_model->setDescription(
                    $description
            );

            $this->facilities_model->setAddress(
                    $address !== '' ? $address : null
            );

            $this->facilities_model->setCity(
                    $city !== '' ? $city : null
            );

            $this->facilities_model->setProvinceId(
                    $province_id
            );

            $this->facilities_model->setPostalCode(
                    $postal_code !== '' ? $postal_code : null
            );

            $this->facilities_model->setCapacity(
                    $capacity
            );

            $this->facilities_model->setBookingEnabled($booking_enabled);
            
            $this->facilities_model->setBookingTypeId($booking_type_id);

            $this->facilities_model->setAccessEnabled(
                    $access_enabled
            );

            $this->facilities_model->setRequiresGuestInformation(
                    $requires_guest_information
            );

            /*
             * No tocamos:
             *
             * pin_valid_before_minutes = 0
             * pin_valid_after_minutes  = 0
             * status                   = 1
             *
             * El modelo ya tiene esos valores por defecto.
             */

            $facility_id = $this->facilities_model->add();

            if (empty($facility_id)) {
                throw new RuntimeException(
                                'No se ha podido crear la instalación.'
                        );
            }


            $facility_data = [
                'name' => $name,
                'description' => $description,
                'address' => $address !== '' ? $address : null,
                'city' => $city !== '' ? $city : null,
                'province_id' => $province_id,
                'postal_code' => $postal_code !== '' ? $postal_code : null,
                'capacity' => $capacity,
                'booking_enabled' => $booking_enabled,
                'booking_type_id' => $booking_type_id,
                'access_enabled' => $access_enabled,
                'requires_guest_information' => $requires_guest_information,
                'pin_valid_before_minutes' => 0,
                'pin_valid_after_minutes' => 0,
                'status' => facilitiesModel::STATUS_ACTIVE
            ];

            /*
             * Auditoría.
             */
            $this->audit_service->insert(
                    'wi_facilities',
                    $facility_id,
                    $facility_data,
                    __METHOD__,
                    'Creación de instalación'
            );

            /*
             * Imagen de portada opcional.
             */
            if (
                    isset($files['facility_image']) && $files['facility_image']['error'] !== UPLOAD_ERR_NO_FILE
            ) {

                $this->saveCoverImage(
                        $facility_id,
                        $files['facility_image']
                );
            }



            $result['success'] = true;
            $result['message'] = 'La instalación se ha creado correctamente.';

            $result['facility_id'] = $facility_id;
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    public function changeFacilityStatus(int $facility_id, int $status): array {

        $facility = $this->facilities_model->findById($facility_id);

        if ($facility === null) {
            return [
                'success' => false,
                'message' => 'La instalación no existe.'
            ];
        }

        $this->facilities_model->updateStatusById($facility_id, $status);

        $this->audit_service->update(
                'wi_facilities',
                $facility_id,
                $facility,
                [
                    'status' => $status
                ],
                __METHOD__,
                [],
                $status == facilitiesModel::STATUS_ACTIVE ? 'Activación de instalación' : 'Desactivación de instalación'
        );

        return [
            'success' => true,
            'message' => $status == facilitiesModel::STATUS_ACTIVE ? 'Instalación activada correctamente.' : 'Instalación desactivada correctamente.'
        ];
    }
    
    public function changeFacilityPriceStatus(int $facility_id, int $price_id, int $status): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if ($facility_id <= 0) {
                throw new InvalidArgumentException('El identificador de la instalación no es válido.');
            }

            if ($price_id <= 0) {
                throw new InvalidArgumentException('El identificador del precio no es válido.');
            }

            if (!in_array($status, [facilitiesPricesModel::STATUS_INACTIVE, facilitiesPricesModel::STATUS_ACTIVE], true)) {
                throw new InvalidArgumentException('El estado del precio no es válido.');
            }

            $price_model = new facilitiesPricesModel();
            $facility_price = $price_model->findById($price_id);

            if ($facility_price === null) {
                throw new RuntimeException('El precio no existe.');
            }

            if ((int) $facility_price['facility_id'] !== $facility_id) {
                throw new RuntimeException('El precio no pertenece a esta instalación.');
            }

            if ((int) $facility_price['status'] === facilitiesPricesModel::STATUS_DELETED) {
                throw new RuntimeException('El precio está eliminado.');
            }

            $updated = $price_model->updateStatusById($price_id, $status);

            if (!$updated) {
                throw new RuntimeException('No se ha podido actualizar el estado del precio.');
            }

            $new_values = [
                'id' => $price_id,
                'facility_id' => $facility_id,
                'status' => $status
            ];

            $this->audit_service->update(
                    'wi_facilities_prices',
                    $facility_id,
                    $facility_price,
                    $new_values,
                    __METHOD__,
                    [],
                    $status === facilitiesPricesModel::STATUS_ACTIVE ? 'Activación del precio de la instalación' : 'Desactivación del precio de la instalación'
            );

            $result['success'] = true;
            $result['message'] = $status === facilitiesPricesModel::STATUS_ACTIVE ? 'Precio activado correctamente.' : 'Precio desactivado correctamente.';
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    public function updateFacility(int $facility_id, stdClass $params): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if ($facility_id <= 0) {
                throw new InvalidArgumentException('El identificador de la instalación no es válido.');
            }

            /*
             * Recuperamos los datos anteriores.
             */
            $facility = $this->facilities_model->findById($facility_id);

            if ($facility === null) {
                throw new RuntimeException('La instalación no existe.');
            }

            /*
             * Datos principales.
             */
            $name = trim($params->name ?? '');
            $description = trim($params->description ?? '');
            $address = trim($params->address ?? '');
            $city = trim($params->city ?? '');
            $postal_code = trim($params->postal_code ?? '');
            $google_maps_url = $this->normalizeGoogleMapsUrl(trim($params->google_maps_url ?? ''));
            $pending_payment_instructions = trim($params->pending_payment_instructions ?? '');
            $booking_conditions = trim($params->booking_conditions ?? '');

            /*
             * Campos obligatorios.
             */
            if ($name === '') {
                throw new InvalidArgumentException('El nombre de la instalación es obligatorio.');
            }

            if ($description === '') {
                throw new InvalidArgumentException('La descripción de la instalación es obligatoria.');
            }

            /*
             * Provincia opcional.
             */
            $province_id = null;

            if (isset($params->province_id) && $params->province_id !== '') {

                $province_id = (int) $params->province_id;

                if ($province_id <= 0) {
                    throw new InvalidArgumentException('La provincia seleccionada no es válida.');
                }
            }

            /*
             * Capacidad opcional.
             */
            $capacity = null;

            if (isset($params->capacity) && $params->capacity !== '') {

                $capacity = (int) $params->capacity;

                if ($capacity < 1) {
                    throw new InvalidArgumentException('La capacidad de la instalación no es válida.');
                }
            }

            /*
             * Checkbox.
             */
            $booking_enabled = isset($params->booking_enabled) ? 1 : 0;
            $access_enabled = isset($params->access_enabled) ? 1 : 0;
            $requires_guest_information = isset($params->requires_guest_information) ? 1 : 0;

            /*
             * Tipo de reserva.
             */
            $booking_type_id = null;

            if ($booking_enabled) {

                $booking_type_id = isset($params->booking_type_id) ? (int) $params->booking_type_id : 0;

                if ($booking_type_id <= 0) {
                    throw new InvalidArgumentException('Debe seleccionar un tipo de reserva.');
                }
            }

            /*
             * Validez adicional del PIN.
             */
            $pin_valid_before_minutes = isset($params->pin_valid_before_minutes) ? max(0, (int) $params->pin_valid_before_minutes) : (int) $facility['pin_valid_before_minutes'];

            $pin_valid_after_minutes = isset($params->pin_valid_after_minutes) ? max(0, (int) $params->pin_valid_after_minutes) : (int) $facility['pin_valid_after_minutes'];

            /*
             * Configuración según tipo de reserva.
             */
            $booking_interval_minutes = null;
            $minimum_booking_minutes = null;
            $check_in_time = '00:00:00';
            $check_out_time = '00:00:00';

            if ($booking_enabled && $booking_type_id !== null) {

                $booking_types_model = new bookingTypesModel();
                $booking_type = $booking_types_model->findById($booking_type_id, false);

                if (empty($booking_type)) {
                    throw new InvalidArgumentException('El tipo de reserva seleccionado no es válido.');
                }

                /*
                 * Reservas por horas.
                 */
                if ($booking_type['code'] === 'SPECIFIC_HOUR') {

                    $booking_interval_minutes = isset($params->booking_interval_minutes) ? (int) $params->booking_interval_minutes : 0;

                    $minimum_booking_minutes = isset($params->minimum_booking_minutes) ? (int) $params->minimum_booking_minutes : 0;

                    if ($booking_interval_minutes <= 0) {
                        throw new InvalidArgumentException('Debe seleccionar el intervalo de reserva.');
                    }

                    if ($minimum_booking_minutes <= 0) {
                        throw new InvalidArgumentException('Debe seleccionar la duración mínima de la reserva.');
                    }
                }

                /*
                 * Reservas por rango de fechas.
                 */
                if ($booking_type['code'] === 'DATE_RANGE') {

                    $check_in_time = trim((string) ($params->check_in_time ?? ''));
                    $check_out_time = trim((string) ($params->check_out_time ?? ''));

                    if ($check_in_time === '') {
                        throw new InvalidArgumentException('Debe indicar la hora de entrada.');
                    }

                    if ($check_out_time === '') {
                        throw new InvalidArgumentException('Debe indicar la hora de salida.');
                    }

                    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $check_in_time)) {
                        throw new InvalidArgumentException('La hora de entrada no es válida.');
                    }

                    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $check_out_time)) {
                        throw new InvalidArgumentException('La hora de salida no es válida.');
                    }

                    $check_in_time .= ':00';
                    $check_out_time .= ':00';
                }
            }

            /*
             * Valores que vamos a actualizar.
             *
             * Nos sirve también para auditoría.
             */
            $new_values = [
                'name' => $name,
                'description' => $description,
                'address' => $address !== '' ? $address : null,
                'city' => $city !== '' ? $city : null,
                'province_id' => $province_id,
                'postal_code' => $postal_code !== '' ? $postal_code : null,
                'google_maps_url' => $google_maps_url,
                'capacity' => $capacity,
                'booking_enabled' => $booking_enabled,
                'booking_type_id' => $booking_type_id,
                'booking_interval_minutes' => $booking_interval_minutes,
                'minimum_booking_minutes' => $minimum_booking_minutes,
                'check_in_time' => $check_in_time,
                'check_out_time' => $check_out_time,
                'pending_payment_instructions' => $pending_payment_instructions !== '' ? $pending_payment_instructions : null,
                'booking_conditions' => $booking_conditions !== '' ? $booking_conditions : null,
                'access_enabled' => $access_enabled,
                'requires_guest_information' => $requires_guest_information,
                'pin_valid_before_minutes' => $pin_valid_before_minutes,
                'pin_valid_after_minutes' => $pin_valid_after_minutes,
                'status' => (int) $facility['status']
            ];

            /*
             * Cargamos el modelo.
             */
            $this->facilities_model->setId($facility_id);
            $this->facilities_model->setName($new_values['name']);
            $this->facilities_model->setDescription($new_values['description']);
            $this->facilities_model->setAddress($new_values['address']);
            $this->facilities_model->setCity($new_values['city']);
            $this->facilities_model->setProvinceId($new_values['province_id']);
            $this->facilities_model->setPostalCode($new_values['postal_code']);
            $this->facilities_model->setGoogleMapsUrl($new_values['google_maps_url']);
            $this->facilities_model->setCapacity($new_values['capacity']);
            $this->facilities_model->setBookingEnabled($new_values['booking_enabled']);
            $this->facilities_model->setBookingTypeId($new_values['booking_type_id']);
            $this->facilities_model->setBookingIntervalMinutes($new_values['booking_interval_minutes']);
            $this->facilities_model->setMinimumBookingMinutes($new_values['minimum_booking_minutes']);
            $this->facilities_model->setCheckInTime($new_values['check_in_time']);
            $this->facilities_model->setCheckOutTime($new_values['check_out_time']);
            $this->facilities_model->setPendingPaymentInstructions($new_values['pending_payment_instructions']);
            $this->facilities_model->setAccessEnabled($new_values['access_enabled']);
            $this->facilities_model->setRequiresGuestInformation($new_values['requires_guest_information']);
            $this->facilities_model->setPinValidBeforeMinutes($new_values['pin_valid_before_minutes']);
            $this->facilities_model->setPinValidAfterMinutes($new_values['pin_valid_after_minutes']);
            $this->facilities_model->setBookingConditions(
                    $booking_conditions !== '' ? $booking_conditions : null
            );

            /*
             * Muy importante: update() también actualiza status.
             * Conservamos el que tenía.
             */
            $this->facilities_model->setStatus($new_values['status']);

            $updated = $this->facilities_model->update();

            if (!$updated) {
                throw new RuntimeException('No se han podido actualizar los datos de la instalación.');
            }

            /*
             * Auditoría.
             *
             * auditLogsService se encargará de guardar
             * únicamente los valores que hayan cambiado.
             */
            $this->audit_service->update(
                    'wi_facilities',
                    $facility_id,
                    $facility,
                    $new_values,
                    __METHOD__,
                    [
                        'updated_at'
                    ],
                    'Actualización de instalación'
            );

            $result['success'] = true;
            $result['message'] = 'Los datos de la instalación se han actualizado correctamente.';
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    public function deleteFacility(int $facility_id): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if ($facility_id <= 0) {
                throw new InvalidArgumentException(
                                'El identificador de la instalación no es válido.'
                        );
            }

            /*
             * Recuperamos los datos actuales para auditoría.
             */
            $facility = $this->facilities_model->findById($facility_id);

            if ($facility === null) {
                throw new RuntimeException(
                                'La instalación no existe.'
                        );
            }

            /*
             * Si ya está eliminada no hacemos nada.
             */
            if ((int) $facility['status'] === facilitiesModel::STATUS_DELETED) {

                throw new RuntimeException(
                                'La instalación ya está eliminada.'
                        );
            }

            /*
             * Soft delete.
             */
            $this->facilities_model->setId($facility_id);

            $deleted = $this->facilities_model->softDelete();

            if (!$deleted) {
                throw new RuntimeException(
                                'No se ha podido eliminar la instalación.'
                        );
            }

            /*
             * Auditoría.
             */
            $this->audit_service->delete(
                    'wi_facilities',
                    $facility_id,
                    $facility,
                    __METHOD__,
                    'Eliminación de instalación'
            );

            $result['success'] = true;
            $result['message'] = 'La instalación se ha eliminado correctamente.';
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    public function addFacilityPrice(int $facility_id, stdClass $params): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if ($facility_id <= 0) {
                throw new InvalidArgumentException('El identificador de la instalación no es válido.');
            }

            $facility = $this->facilities_model->findById($facility_id);

            if ($facility === null) {
                throw new RuntimeException('La instalación no existe.');
            }

            $name = trim($params->price_name ?? '');
            $description = trim($params->price_description ?? '');
            $price = isset($params->price_value) && $params->price_value !== '' ? (float) $params->price_value : null;
            $billing_unit_type_id = isset($params->billing_unit_type_id) ? (int) $params->billing_unit_type_id : 0;
            $billing_period_id = isset($params->billing_period_id) ? (int) $params->billing_period_id : 0;
            $valid_from = trim($params->price_valid_from ?? '');
            $valid_until = trim($params->price_valid_until ?? '');

            if ($name === '') {
                throw new InvalidArgumentException('El nombre del precio es obligatorio.');
            }

            if ($price === null || $price < 0) {
                throw new InvalidArgumentException('El precio no es válido.');
            }

            if ($billing_unit_type_id <= 0) {
                throw new InvalidArgumentException('Debe seleccionar una unidad de facturación.');
            }

            if ($billing_period_id <= 0) {
                throw new InvalidArgumentException('Debe seleccionar un periodo de facturación.');
            }

            if ($valid_from !== '' && $valid_until !== '' && strtotime($valid_until) < strtotime($valid_from)) {
                throw new InvalidArgumentException('La fecha final no puede ser anterior a la fecha inicial.');
            }

            $valid_from = $valid_from !== '' ? $valid_from : null;
            $valid_until = $valid_until !== '' ? $valid_until : null;

            $price_model = new facilitiesPricesModel();

            $price_model->setFacilityId($facility_id);
            $price_model->setName($name);
            $price_model->setDescription($description !== '' ? $description : null);
            $price_model->setPrice($price);
            $price_model->setBillingUnitTypeId($billing_unit_type_id);
            $price_model->setBillingPeriodId($billing_period_id);
            $price_model->setValidFrom($valid_from);
            $price_model->setValidUntil($valid_until);
            $price_model->setStatus(facilitiesPricesModel::STATUS_ACTIVE);

            $price_id = $price_model->add();

            if ($price_id <= 0) {
                throw new RuntimeException('No se ha podido guardar el precio.');
            }

            $new_values = [
                'facility_price_id' => $price_id,
                'facility_id' => $facility_id,
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'price' => $price,
                'billing_unit_type_id' => $billing_unit_type_id,
                'billing_period_id' => $billing_period_id,
                'valid_from' => $valid_from,
                'valid_until' => $valid_until,
                'status' => facilitiesPricesModel::STATUS_ACTIVE
            ];

            $this->audit_service->insert(
                    'wi_facilities_prices',
                    $facility_id,
                    $new_values,
                    __METHOD__,
                    'Añadir precio a instalación'
            );

            $result['success'] = true;
            $result['message'] = 'El precio se ha añadido correctamente.';
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    public function editFacilityPrice(int $facility_id, stdClass $params): array {

        $price_id = isset($params->price_id) ? (int) $params->price_id : 0;

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if ($facility_id <= 0) {
                throw new InvalidArgumentException('El identificador de la instalación no es válido.');
            }

            if ($price_id <= 0) {
                throw new InvalidArgumentException('El identificador del precio no es válido.');
            }

            $price_model = new facilitiesPricesModel();
            $facility_price = $price_model->findById($price_id);

            if ($facility_price === null) {
                throw new RuntimeException('El precio no existe.');
            }

            /*
             * Comprobamos que el precio realmente pertenece
             * a la instalación que estamos editando.
             */
            if ((int) $facility_price['facility_id'] !== $facility_id) {
                throw new RuntimeException('El precio no pertenece a esta instalación.');
            }

            $name = trim($params->price_name ?? '');
            $description = trim($params->price_description ?? '');
            $price = isset($params->price_value) && $params->price_value !== '' ? (float) $params->price_value : null;
            $billing_unit_type_id = isset($params->billing_unit_type_id) ? (int) $params->billing_unit_type_id : 0;
            $billing_period_id = isset($params->billing_period_id) ? (int) $params->billing_period_id : 0;
            $valid_from = trim($params->price_valid_from ?? '');
            $valid_until = trim($params->price_valid_until ?? '');

            if ($name === '') {
                throw new InvalidArgumentException('El nombre del precio es obligatorio.');
            }

            if ($price === null || $price < 0) {
                throw new InvalidArgumentException('El precio no es válido.');
            }

            if ($billing_unit_type_id <= 0) {
                throw new InvalidArgumentException('Debe seleccionar una unidad de facturación.');
            }

            if ($billing_period_id <= 0) {
                throw new InvalidArgumentException('Debe seleccionar un periodo de facturación.');
            }

            if ($valid_from !== '' && $valid_until !== '' && strtotime($valid_until) < strtotime($valid_from)) {
                throw new InvalidArgumentException('La fecha final no puede ser anterior a la fecha inicial.');
            }

            $valid_from = $valid_from !== '' ? $valid_from : null;
            $valid_until = $valid_until !== '' ? $valid_until : null;

            $new_values = [
                'id' => $price_id,
                'facility_id' => $facility_id,
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'price' => $price,
                'billing_unit_type_id' => $billing_unit_type_id,
                'billing_period_id' => $billing_period_id,
                'valid_from' => $valid_from,
                'valid_until' => $valid_until,
                'status' => (int) $facility_price['status']
            ];

            $price_model->setId($price_id);
            $price_model->setFacilityId($facility_id);
            $price_model->setName($name);
            $price_model->setDescription($new_values['description']);
            $price_model->setPrice($price);
            $price_model->setBillingUnitTypeId($billing_unit_type_id);
            $price_model->setBillingPeriodId($billing_period_id);
            $price_model->setValidFrom($valid_from);
            $price_model->setValidUntil($valid_until);
            $price_model->setStatus((int) $facility_price['status']);

            $result_update = $price_model->update();

            if (!$result_update) {
                throw new RuntimeException('No se ha podido actualizar el precio.');
            }

            $this->audit_service->update(
                    'wi_facilities_prices',
                    $facility_id,
                    $facility_price,
                    $new_values,
                    __METHOD__,
                    [],
                    'Editar precio de instalación'
            );

            $result['success'] = true;
            $result['message'] = 'El precio se ha actualizado correctamente.';
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    public function deleteFacilityPrice(int $facility_id, int $price_id): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if ($facility_id <= 0) {
                throw new InvalidArgumentException('El identificador de la instalación no es válido.');
            }

            if ($price_id <= 0) {
                throw new InvalidArgumentException('El identificador del precio no es válido.');
            }

            $price_model = new facilitiesPricesModel();
            $facility_price = $price_model->findById($price_id);

            if ($facility_price === null) {
                throw new RuntimeException('El precio no existe.');
            }

            if ((int) $facility_price['facility_id'] !== $facility_id) {
                throw new RuntimeException('El precio no pertenece a esta instalación.');
            }

            if ((int) $facility_price['status'] === facilitiesPricesModel::STATUS_DELETED) {
                throw new RuntimeException('El precio ya está eliminado.');
            }

            $price_model->setId($price_id);

            $deleted = $price_model->softDelete();

            if (!$deleted) {
                throw new RuntimeException('No se ha podido eliminar el precio.');
            }

            $this->audit_service->delete(
                    'wi_facilities_prices',
                    $facility_id,
                    $facility_price,
                    __METHOD__,
                    'Eliminación del precio de la instalación'
            );

            $result['success'] = true;
            $result['message'] = 'El precio se ha eliminado correctamente.';
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    public function saveFacilityImages(int $facility_id, stdClass $params, array $files = []): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if ($facility_id <= 0) {
                throw new InvalidArgumentException('El identificador de la instalación no es válido.');
            }

            $facility = $this->facilities_model->findById($facility_id);

            if ($facility === null) {
                throw new RuntimeException('La instalación no existe.');
            }

            $delete_gallery_ids = [];

            if (!empty($params->delete_gallery_ids)) {
                $decoded = json_decode((string) $params->delete_gallery_ids, true);

                if (is_array($decoded)) {
                    $delete_gallery_ids = array_values(array_filter(array_map('intval', $decoded), fn($id) => $id > 0));
                }
            }

            $changes = 0;

            /*
             * Eliminar imágenes existentes marcadas.
             */
            foreach ($delete_gallery_ids as $image_id) {
                $this->deleteFacilityGalleryImage($facility_id, $image_id);
                $changes++;
            }

            /*
             * Sustituir portada si viene una nueva.
             */
            if (isset($files['facility_cover_image']) && $files['facility_cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $this->replaceCoverImage($facility_id, $files['facility_cover_image']);
                $changes++;
            }

            /*
             * Añadir nuevas imágenes de galería.
             */
            if (isset($files['facility_gallery_images'])) {

                $gallery_files = $this->normalizeMultipleFiles($files['facility_gallery_images']);

                foreach ($gallery_files as $file) {

                    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    $this->saveGalleryImage($facility_id, $file);
                    $changes++;
                }
            }

            if ($changes === 0) {
                throw new RuntimeException('No se ha realizado ningún cambio en las imágenes.');
            }

            $result['success'] = true;
            $result['message'] = 'Las imágenes se han actualizado correctamente.';
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }
    
    public function addFacilityDevice(int $facility_id, stdClass $params): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if ($facility_id <= 0) {
                throw new InvalidArgumentException('El identificador de la instalación no es válido.');
            }

            $device_id = isset($params->ttlock_device_id) ? (int) $params->ttlock_device_id : 0;
            $is_primary = isset($params->is_primary) ? 1 : 0;

            if ($device_id <= 0) {
                throw new InvalidArgumentException('Debe seleccionar una cerradura.');
            }

            $facility = $this->facilities_model->findById($facility_id);

            if ($facility === null) {
                throw new RuntimeException('La instalación no existe.');
            }

            $device_model = new ttlockDevicesModel();
            $device = $device_model->findById($device_id);

            if ($device === null) {
                throw new RuntimeException('La cerradura no existe.');
            }

            $facility_device_model = new facilitiesDevicesModel();

            /*
             * Una cerradura solo puede pertenecer
             * a una instalación.
             */
            $existing = $facility_device_model->findByDeviceId($device_id);

            if ($existing !== null) {
                throw new RuntimeException('La cerradura ya está asociada a una instalación.');
            }

            $current_devices = $facility_device_model->findByFacilityId($facility_id);

            if (empty($current_devices)) {
                $is_primary = 1;
            }

            /*
             * Si vamos a ponerla como principal,
             * guardamos la principal anterior para auditoría.
             */
            $previous_primary = null;

            if ($is_primary === 1) {
                $previous_primary = $facility_device_model->findPrimaryByFacilityId($facility_id);
            }

            /*
             * Creamos la nueva relación.
             */
            $facility_device_model->setFacilityId($facility_id);
            $facility_device_model->setTtlockDeviceId($device_id);
            $facility_device_model->setIsPrimary($is_primary);

            $facility_device_id = $facility_device_model->add();

            if ($facility_device_id <= 0) {
                throw new RuntimeException('No se ha podido asociar la cerradura a la instalación.');
            }

            /*
             * Si la nueva cerradura es principal,
             * quitamos esa condición al resto.
             */
            if ($is_primary === 1 && $previous_primary !== null) {

                $updated = $facility_device_model->unsetPrimaryByFacilityId(
                        $facility_id,
                        $facility_device_id
                );

                if (!$updated) {
                    throw new RuntimeException('No se ha podido actualizar la cerradura principal de la instalación.');
                }

                /*
                 * Auditoría de la cerradura que deja
                 * de ser principal.
                 */
                $this->audit_service->update(
                        'wi_facilities_devices',
                        $facility_id,
                        $previous_primary,
                        [
                            'facility_device_id' => (int) $previous_primary['id'],
                            'facility_id' => $facility_id,
                            'ttlock_device_id' => (int) $previous_primary['ttlock_device_id'],
                            'is_primary' => 0
                        ],
                        __METHOD__,
                        [],
                        'Cambio de cerradura principal de la instalación'
                );
            }

            /*
             * Auditoría de la nueva relación.
             */
            $new_values = [
                'facility_device_id' => $facility_device_id,
                'facility_id' => $facility_id,
                'ttlock_device_id' => $device_id,
                'is_primary' => $is_primary
            ];

            $this->audit_service->insert(
                    'wi_facilities_devices',
                    $facility_id,
                    $new_values,
                    __METHOD__,
                    $is_primary === 1 ? 'Añadir cerradura principal a instalación' : 'Añadir cerradura a instalación'
            );

            $result['success'] = true;
            $result['message'] = 'La cerradura se ha añadido correctamente.';
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }
    
    public function removeFacilityDevice(int $facility_id, int $device_id): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if ($facility_id <= 0) {
                throw new InvalidArgumentException('El identificador de la instalación no es válido.');
            }

            if ($device_id <= 0) {
                throw new InvalidArgumentException('El identificador de la cerradura no es válido.');
            }

            $facility_device_model = new facilitiesDevicesModel();

            $facility_device = $facility_device_model->findByFacilityAndDevice(
                    $facility_id,
                    $device_id
            );

            if ($facility_device === null) {
                throw new RuntimeException('La cerradura no está asociada a esta instalación.');
            }

            $was_primary = (int) $facility_device['is_primary'] === 1;

            $deleted = $facility_device_model->deleteById(
                    (int) $facility_device['id']
            );

            if (!$deleted) {
                throw new RuntimeException('No se ha podido desvincular la cerradura de la instalación.');
            }

            /*
             * Si era la principal y quedan más cerraduras,
             * elegimos otra como principal.
             */
            $new_primary = null;

            if ($was_primary) {

                $remaining_devices = $facility_device_model->findByFacilityId(
                        $facility_id
                );

                if (!empty($remaining_devices)) {

                    $new_primary = $remaining_devices[0];

                    $updated = $facility_device_model->setPrimaryById(
                            (int) $new_primary['id']
                    );

                    if (!$updated) {
                        throw new RuntimeException('No se ha podido establecer una nueva cerradura principal.');
                    }

                    $this->audit_service->update(
                            'wi_facilities_devices',
                            $facility_id,
                            $new_primary,
                            [
                                'facility_device_id' => (int) $new_primary['id'],
                                'facility_id' => $facility_id,
                                'ttlock_device_id' => (int) $new_primary['ttlock_device_id'],
                                'is_primary' => 1
                            ],
                            __METHOD__,
                            [],
                            'Asignación automática de nueva cerradura principal'
                    );
                }
            }

            $this->audit_service->delete(
                    'wi_facilities_devices',
                    $facility_id,
                    [
                        'facility_device_id' => (int) $facility_device['id'],
                        'facility_id' => $facility_id,
                        'ttlock_device_id' => $device_id,
                        'is_primary' => (int) $facility_device['is_primary']
                    ],
                    __METHOD__,
                    'Desvinculación de cerradura de instalación'
            );

            $result['success'] = true;
            $result['message'] = 'La cerradura se ha desvinculado correctamente.';
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }
    
    public function addFacilityService(int $facility_id, stdClass $params): array {

    $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if ($facility_id <= 0) {
                throw new InvalidArgumentException('El identificador de la instalación no es válido.');
            }

            $facility = $this->facilities_model->findById($facility_id);

            if ($facility === null) {
                throw new RuntimeException('La instalación no existe.');
            }

            $service_ids = isset($params->service_ids) && is_array($params->service_ids) ? $params->service_ids : [];

            $service_ids = array_values(array_unique(array_filter(array_map('intval', $service_ids), fn($id) => $id > 0)));

            if (empty($service_ids)) {
                throw new InvalidArgumentException('Debe seleccionar al menos un servicio.');
            }

            $services_model = new servicesModel();
            $facility_service_model = new facilitiesServicesModel();

            $inserted = 0;

            foreach ($service_ids as $service_id) {

                $service = $services_model->findById($service_id);

                if ($service === null) {
                    throw new RuntimeException('Uno de los servicios seleccionados no existe.');
                }

                if ((int) $service['status'] !== servicesModel::STATUS_ACTIVE || !empty($service['deleted_at'])) {
                    throw new RuntimeException('Uno de los servicios seleccionados no está disponible.');
                }

                $existing = $facility_service_model->findByFacilityAndService($facility_id, $service_id);

                if ($existing !== null) {
                    continue;
                }

                $facility_service_model = new facilitiesServicesModel();
                $facility_service_model->setFacilityId($facility_id);
                $facility_service_model->setServiceId($service_id);

                $facility_service_id = $facility_service_model->add();

                if ($facility_service_id <= 0) {
                    throw new RuntimeException('No se ha podido añadir uno de los servicios a la instalación.');
                }

                $new_values = [
                    'facility_service_id' => $facility_service_id,
                    'facility_id' => $facility_id,
                    'service_id' => $service_id
                ];

                $this->audit_service->insert(
                        'wi_facilities_services',
                        $facility_id,
                        $new_values,
                        __METHOD__,
                        'Añadir servicio a instalación'
                );

                $inserted++;
            }

            if ($inserted === 0) {
                throw new RuntimeException('Los servicios seleccionados ya están asociados a la instalación.');
            }

            $result['success'] = true;
            $result['message'] = $inserted === 1 ? 'El servicio se ha añadido correctamente.' : 'Los servicios se han añadido correctamente.';
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }
    
    public function removeFacilityService(int $facility_id, int $service_id): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if ($facility_id <= 0) {
                throw new InvalidArgumentException('El identificador de la instalación no es válido.');
            }

            if ($service_id <= 0) {
                throw new InvalidArgumentException('El identificador del servicio no es válido.');
            }

            $facility_service_model = new facilitiesServicesModel();

            $facility_service = $facility_service_model->findByFacilityAndService(
                    $facility_id,
                    $service_id
            );

            if ($facility_service === null) {
                throw new RuntimeException('El servicio no está asociado a esta instalación.');
            }

            $deleted = $facility_service_model->deleteById((int) $facility_service['id']);

            if (!$deleted) {
                throw new RuntimeException('No se ha podido quitar el servicio de la instalación.');
            }

            $this->audit_service->delete(
                    'wi_facilities_services',
                    $facility_id,
                    [
                        'facility_service_id' => (int) $facility_service['id'],
                        'facility_id' => $facility_id,
                        'service_id' => $service_id
                    ],
                    __METHOD__,
                    'Quitar servicio de instalación'
            );

            $result['success'] = true;
            $result['message'] = 'El servicio se ha quitado correctamente.';
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }
    
    public function addFacilityPaymentMethod(int $facility_id, stdClass $params): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if ($facility_id <= 0) {
                throw new InvalidArgumentException('El identificador de la instalación no es válido.');
            }

            $facility = $this->facilities_model->findById($facility_id);

            if (empty($facility)) {
                throw new RuntimeException('La instalación no existe.');
            }

            $payment_method_ids = isset($params->payment_method_ids) && is_array($params->payment_method_ids) ? $params->payment_method_ids : [];

            $payment_method_ids = array_values(array_unique(array_filter(array_map('intval', $payment_method_ids), fn($id) => $id > 0)));

            if (empty($payment_method_ids)) {
                throw new InvalidArgumentException('Debe seleccionar al menos un método de pago.');
            }

            $payment_methods_model = new paymentMethodsModel();
            $facility_payment_method_model = new facilitiesPaymentMethodsModel();

            $inserted = 0;

            foreach ($payment_method_ids as $payment_method_id) {

                $payment_method = $payment_methods_model->findById($payment_method_id);

                if ($payment_method === null) {
                    throw new RuntimeException('Uno de los métodos de pago seleccionados no existe.');
                }

                if ((int) $payment_method['status'] !== paymentMethodsModel::STATUS_ACTIVE) {
                    throw new RuntimeException('Uno de los métodos de pago seleccionados no está disponible.');
                }

                $existing = $facility_payment_method_model->findByFacilityAndPaymentMethod($facility_id, $payment_method_id);

                if ($existing !== null) {
                    continue;
                }

                $facility_payment_method_model = new facilitiesPaymentMethodsModel();
                $facility_payment_method_model->setFacilityId($facility_id);
                $facility_payment_method_model->setPaymentMethodId($payment_method_id);

                $facility_payment_method_id = $facility_payment_method_model->add();

                if ($facility_payment_method_id <= 0) {
                    throw new RuntimeException('No se ha podido añadir uno de los métodos de pago a la instalación.');
                }

                $new_values = [
                    'facility_payment_method_id' => $facility_payment_method_id,
                    'facility_id' => $facility_id,
                    'payment_method_id' => $payment_method_id
                ];

                $this->audit_service->insert(
                        'wi_facilities_payment_methods',
                        $facility_id,
                        $new_values,
                        __METHOD__,
                        'Añadir método de pago a instalación'
                );

                $inserted++;
            }

            if ($inserted === 0) {
                throw new RuntimeException('Los métodos de pago seleccionados ya están asociados a la instalación.');
            }

            $result['success'] = true;
            $result['message'] = $inserted === 1 ? 'El método de pago se ha añadido correctamente.' : 'Los métodos de pago se han añadido correctamente.';
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }
    
    public function removeFacilityPaymentMethod(int $facility_id, int $method_id): array {

        $result = [
            'success' => false,
            'message' => ''
        ];

        try {

            if ($facility_id <= 0) {
                throw new InvalidArgumentException('El identificador de la instalación no es válido.');
            }

            if ($method_id <= 0) {
                throw new InvalidArgumentException('El identificador del método de pago no es válido.');
            }

            $facility_payment_method_model = new facilitiesPaymentMethodsModel();

            $facility_payment_method = $facility_payment_method_model->findByFacilityAndPaymentMethod(
                    $facility_id,
                    $method_id
            );

            if ($facility_payment_method === null) {
                throw new RuntimeException('El método de pago no está asociado a esta instalación.');
            }

            $deleted = $facility_payment_method_model->deleteById((int) $facility_payment_method['id']);

            if (!$deleted) {
                throw new RuntimeException('No se ha podido quitar el método de pago de la instalación.');
            }

            $this->audit_service->delete(
                    'wi_facilities_payment_methods',
                    $facility_id,
                    [
                        'facility_payment_method_id' => (int) $facility_payment_method['id'],
                        'facility_id' => $facility_id,
                        'payment_method_id' => $method_id
                    ],
                    __METHOD__,
                    'Quitar método de pago de instalación'
            );

            $result['success'] = true;
            $result['message'] = 'El método de pago se ha quitado correctamente.';
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    
    public function addAvailability(int $facility_id, stdClass $params): array {

        try {

            if ($facility_id <= 0) {
                return serviceResponse::error(
                                'El identificador de la instalación no es válido.',
                                'FACILITY_ID_INVALID'
                        );
            }

            $facility = $this->facilities_model->findByIdWithBookingType($facility_id);

            if (empty($facility)) {
                return serviceResponse::error(
                                'La instalación no existe.',
                                'FACILITY_NOT_FOUND'
                        );
            }

            if ((int) $facility['booking_enabled'] !== 1) {
                return serviceResponse::error(
                                'La instalación no tiene habilitadas las reservas.',
                                'FACILITY_BOOKING_DISABLED'
                        );
            }

            $date_from = trim((string) ($params->date_from ?? ''));
            $date_until = trim((string) ($params->date_until ?? ''));

            if ($date_from === '' || $date_until === '') {
                return serviceResponse::error(
                                'Debe indicar el periodo de disponibilidad.',
                                'AVAILABILITY_DATES_REQUIRED'
                        );
            }

            try {
                $from = new DateTimeImmutable($date_from);
                $until = new DateTimeImmutable($date_until);
            } catch (Throwable $e) {
                return serviceResponse::error(
                                'Las fechas indicadas no son válidas.',
                                'AVAILABILITY_DATES_INVALID'
                        );
            }

            if ($until < $from) {
                return serviceResponse::error(
                                'La fecha hasta no puede ser anterior a la fecha desde.',
                                'AVAILABILITY_DATE_RANGE_INVALID'
                        );
            }

            /*
             * Los checkbox days[] pueden llegar como array.
             */
            $days = $params->days ?? [];

            if (!is_array($days) || empty($days)) {
                return serviceResponse::error(
                                'Debe seleccionar al menos un día de la semana.',
                                'AVAILABILITY_DAYS_REQUIRED'
                        );
            }

            $days = array_map('intval', $days);
            $days = array_values(array_unique($days));

            foreach ($days as $day) {
                if ($day < 1 || $day > 7) {
                    return serviceResponse::error(
                                    'Uno de los días seleccionados no es válido.',
                                    'AVAILABILITY_DAY_INVALID'
                            );
                }
            }

            $booking_type_code = (string) $facility['booking_type_code'];

            $start_time = null;
            $end_time = null;

            if ($booking_type_code === 'SPECIFIC_HOUR') {

                $start_time = trim((string) ($params->start_time ?? ''));
                $end_time = trim((string) ($params->end_time ?? ''));

                if ($start_time === '' || $end_time === '') {
                    return serviceResponse::error(
                                    'Debe indicar la hora de inicio y la hora de fin.',
                                    'AVAILABILITY_HOURS_REQUIRED'
                            );
                }

                if ($end_time <= $start_time) {
                    return serviceResponse::error(
                                    'La hora de fin debe ser posterior a la hora de inicio.',
                                    'AVAILABILITY_HOURS_INVALID'
                            );
                }
            }

            $availability_model = new facilitiesAvailabilityModel();

            $created = 0;

            /*
             * Recorremos todas las fechas del periodo.
             */
            $current = $from;
            $skipped = 0;

            while ($current <= $until) {

                $day_of_week = (int) $current->format('N');

                if (in_array($day_of_week, $days, true)) {

                    if ($booking_type_code === 'SPECIFIC_HOUR') {

                        $available_from = $current->format('Y-m-d') . ' ' . $start_time . ':00';
                        $available_until = $current->format('Y-m-d') . ' ' . $end_time . ':00';
                    } else {

                        $available_from = $current->format('Y-m-d') . ' 00:00:00';
                        $available_until = $current->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
                    }

                    // AQUÍ comprobamos si ya existe o hay solapes
                    if (!$availability_model->existsOverlap($facility_id, $available_from, $available_until)) {

                        $availability_model->setFacilityId($facility_id);
                        $availability_model->setAvailableFrom($available_from);
                        $availability_model->setAvailableUntil($available_until);
                        $availability_model->setStatus(facilitiesAvailabilityModel::STATUS_ACTIVE);

                        $availability_id = $availability_model->add();

                        if (!$availability_id) {
                            throw new RuntimeException('No se ha podido guardar la disponibilidad.');
                        }

                        $created++;
                    }else{
                        $skipped++;
                    }
                }

                $current = $current->modify('+1 day');
            }

            if ($created === 0) {
                return serviceResponse::error(
                                'No se ha generado ninguna disponibilidad para el periodo seleccionado.',
                                'AVAILABILITY_NOT_CREATED'
                        );
            }

            /*
             * Auditoría.
             */
            $day_names = [
                1 => 'Lunes',
                2 => 'Martes',
                3 => 'Miércoles',
                4 => 'Jueves',
                5 => 'Viernes',
                6 => 'Sábado',
                7 => 'Domingo'
            ];
            $days_text = implode(', ', array_map(fn($day) => $day_names[$day],$days));
            $this->audit_service->insert(
                    'wi_facilities_availability',
                    $facility_id,
                    [
                        'facility_id' => $facility_id,
                        'date_from' => $date_from,
                        'date_until' => $date_until,
                        'days' => $days_text,
                        'start_time' => $start_time,
                        'end_time' => $end_time,
                        'created_rows' => $created
                    ],
                    __METHOD__,
                    'Creación de disponibilidad'
            );

            $message = 'La disponibilidad se ha añadido correctamente.';

            if ($skipped > 0) {
                $message .= ' Se han omitido ' . $skipped . ' periodos porque ya existía disponibilidad solapada.';
            }
            return serviceResponse::success(
                            $message,
                            'FACILITY_AVAILABILITY_CREATED',
                            [
                                'created' => $created
                            ]
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'FACILITY_AVAILABILITY_CREATE_EXCEPTION'
                    );
        }
    }
    
    

    private function saveCoverImage(int $facility_id, array $file): void {

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                            'Se ha producido un error al subir la imagen.'
                    );
        }

        /*
         * Comprobamos el MIME real del fichero.
         */
        $mime_type = mime_content_type(
                $file['tmp_name']
        );

        $allowed_types = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowed_types[$mime_type])) {
            throw new InvalidArgumentException(
                            'La imagen debe ser JPG, PNG o WEBP.'
                    );
        }

        $extension = $allowed_types[$mime_type];

        /*
         * Generamos nosotros el nombre.
         */
        $filename = 'facility_' .
                $facility_id .
                '_' .
                bin2hex(random_bytes(8)) .
                '.' .
                $extension;

        $directory = rtrim(
                self::IMAGES_PATH,
                '/\\'
        );

        if (!is_dir($directory)) {

            if (!mkdir($directory, 0755, true)) {
                throw new RuntimeException(
                                'No se ha podido crear el directorio de imágenes.'
                        );
            }
        }

        $destination = $directory .
                DIRECTORY_SEPARATOR .
                $filename;

        if (!move_uploaded_file(
                        $file['tmp_name'],
                        $destination
                )) {

            throw new RuntimeException(
                            'No se ha podido guardar la imagen de portada.'
                    );
        }

        /*
         * Primera imagen:
         * portada y primera posición.
         */
        $this->facilities_images_model->setFacilityId(
                $facility_id
        );

        $this->facilities_images_model->setFilename(
                $filename
        );

        $this->facilities_images_model->setIsCover(1);

        $this->facilities_images_model->setSortOrder(1);

        $image_id = $this->facilities_images_model->add();

        if (empty($image_id)) {

            /*
             * Evitamos dejar el fichero huérfano.
             */
            if (is_file($destination)) {
                unlink($destination);
            }

            throw new RuntimeException(
                            'No se ha podido guardar la imagen de portada.'
                    );
        }

        $this->audit_service->insert(
                'wi_facilities_images',
                $facility_id,
                [
                    'facility_id' => $facility_id,
                    'filename' => $filename,
                    'is_cover' => 1,
                    'sort_order' => 1
                ],
                __METHOD__,
                'Añadir imagen de portada a instalación'
        );
    }
    
    public function deleteAvailability(int $facility_id,int $availability_id,stdClass $params): array {

        try {

            if ($facility_id <= 0) {
                return serviceResponse::error(
                                'El identificador de la instalación no es válido.',
                                'FACILITY_ID_INVALID'
                        );
            }

            if ($availability_id <= 0) {
                return serviceResponse::error(
                                'La disponibilidad seleccionada no es válida.',
                                'AVAILABILITY_ID_INVALID'
                        );
            }

            $start_at = trim((string) ($params->start_at ?? ''));
            $end_at = trim((string) ($params->end_at ?? ''));

            if ($start_at === '' || $end_at === '') {
                return serviceResponse::error(
                                'El periodo seleccionado no es válido.',
                                'AVAILABILITY_PERIOD_INVALID'
                        );
            }

            $availability_model = new facilitiesAvailabilityModel();

            /*
             * Recuperamos la disponibilidad original.
             */
            $availability = $availability_model->findById(
                    $availability_id,
                    false
            );

            if (empty($availability)) {
                return serviceResponse::error(
                                'La disponibilidad seleccionada no existe.',
                                'AVAILABILITY_NOT_FOUND'
                        );
            }

            /*
             * Comprobamos que pertenece a la instalación indicada.
             */
            if ((int) $availability['facility_id'] !== $facility_id) {
                return serviceResponse::error(
                                'La disponibilidad no pertenece a la instalación indicada.',
                                'AVAILABILITY_FACILITY_INVALID'
                        );
            }

            /*
             * Periodo original de disponibilidad.
             */
            $original_start = new DateTimeImmutable(
                    $availability['available_from']
            );

            $original_end = new DateTimeImmutable(
                    $availability['available_until']
            );

            /*
             * Periodo concreto que queremos eliminar.
             */
            $remove_start = new DateTimeImmutable($start_at);
            $remove_end = new DateTimeImmutable($end_at);

            /*
             * El tramo a eliminar debe estar completamente
             * contenido en la disponibilidad original.
             */
            if (
                    $remove_start < $original_start ||
                    $remove_end > $original_end ||
                    $remove_end <= $remove_start
            ) {

                return serviceResponse::error(
                                'El periodo seleccionado no pertenece a la disponibilidad indicada.',
                                'AVAILABILITY_PERIOD_OUT_OF_RANGE'
                        );
            }

            /*
             * Volvemos a comprobar en servidor que no existen
             * reservas para el periodo que se quiere eliminar.
             */
            $reservations_model = new reservationsModel();

            $reserved_units = $reservations_model->getReservedUnitsByPeriod(
                    $facility_id,
                    $remove_start->format('Y-m-d H:i:s'),
                    $remove_end->format('Y-m-d H:i:s')
            );

            if ($reserved_units > 0) {
                return serviceResponse::error(
                                'No se puede eliminar esta disponibilidad porque tiene reservas asociadas.',
                                'AVAILABILITY_HAS_RESERVATIONS'
                        );
            }

            /*
             * Datos originales para auditoría.
             */
            $old_values = [
                'id' => (int) $availability['id'],
                'facility_id' => (int) $availability['facility_id'],
                'available_from' => $availability['available_from'],
                'available_until' => $availability['available_until'],
                'status' => (int) $availability['status']
            ];

            $resulting_periods = [];

            /*
             * A partir de aquí modificamos físicamente la BD.
             * Todo se realiza en la misma transacción.
             */
            $availability_model->beginTransaction();

            try {

                /*
                 * Eliminamos la disponibilidad original completa.
                 *
                 * Después recrearemos únicamente las partes
                 * que deban seguir disponibles.
                 */
                if (!$availability_model->deleteById($availability_id)) {
                    throw new RuntimeException(
                                    'No se ha podido eliminar la disponibilidad.'
                            );
                }

                /*
                 * TRAMO ANTERIOR
                 *
                 * Original:
                 * 18:00 ---------------- 22:00
                 *
                 * Eliminamos:
                 *          20:00 -- ...
                 *
                 * Conservamos:
                 * 18:00 --- 20:00
                 */
                if ($original_start < $remove_start) {

                    $available_from = $original_start->format('Y-m-d H:i:s');
                    $available_until = $remove_start->format('Y-m-d H:i:s');

                    $availability_model->setFacilityId($facility_id);
                    $availability_model->setAvailableFrom($available_from);
                    $availability_model->setAvailableUntil($available_until);
                    $availability_model->setStatus(
                            facilitiesAvailabilityModel::STATUS_ACTIVE
                    );

                    $new_id = $availability_model->add();

                    if (!$new_id) {
                        throw new RuntimeException(
                                        'No se ha podido reconstruir la disponibilidad.'
                                );
                    }

                    $resulting_periods[] = [
                        'id' => $new_id,
                        'available_from' => $available_from,
                        'available_until' => $available_until
                    ];
                }

                /*
                 * TRAMO POSTERIOR
                 *
                 * Original:
                 * 18:00 ---------------- 22:00
                 *
                 * Eliminamos:
                 *             ... -- 21:00
                 *
                 * Conservamos:
                 *                    21:00 --- 22:00
                 */
                if ($remove_end < $original_end) {

                    $available_from = $remove_end->format('Y-m-d H:i:s');
                    $available_until = $original_end->format('Y-m-d H:i:s');

                    $availability_model->setFacilityId($facility_id);
                    $availability_model->setAvailableFrom($available_from);
                    $availability_model->setAvailableUntil($available_until);
                    $availability_model->setStatus(
                            facilitiesAvailabilityModel::STATUS_ACTIVE
                    );

                    $new_id = $availability_model->add();

                    if (!$new_id) {
                        throw new RuntimeException(
                                        'No se ha podido reconstruir la disponibilidad.'
                                );
                    }

                    $resulting_periods[] = [
                        'id' => $new_id,
                        'available_from' => $available_from,
                        'available_until' => $available_until
                    ];
                }

                $availability_model->commit();
            } catch (Throwable $e) {

                $availability_model->rollBack();

                throw $e;
            }

            /*
             * Auditoría.
             *
             * Guardamos la disponibilidad original, qué parte
             * se eliminó y qué periodos quedaron después.
             */
            $audit_data = [
                'facility_id' => $facility_id,
                'availability_id' => $availability_id,
                'available_from' => $old_values['available_from'],
                'available_until' => $old_values['available_until'],
                'removed_from' => $remove_start->format('Y-m-d H:i:s'),
                'removed_until' => $remove_end->format('Y-m-d H:i:s'),
                'resulting_periods' => json_encode(
                        $resulting_periods,
                        JSON_UNESCAPED_UNICODE
                )
            ];

            $this->audit_service->delete(
                    'wi_facilities_availability',
                    $facility_id,
                    $audit_data,
                    __METHOD__,
                    'Eliminación de disponibilidad'
            );

            return serviceResponse::success(
                            'La disponibilidad se ha eliminado correctamente.',
                            'FACILITY_AVAILABILITY_DELETED'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'FACILITY_AVAILABILITY_DELETE_EXCEPTION'
                    );
        }
    }

    private function normalizeGoogleMapsUrl(string $value): ?string {

        if ($value === '') {
            return null;
        }

        /*
         * Si han pegado el iframe completo,
         * extraemos únicamente el atributo src.
         */
        if (stripos($value, '<iframe') !== false) {

            if (!preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $value, $matches)) {
                throw new InvalidArgumentException('El código de Google Maps no es válido.');
            }

            $value = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        /*
         * Validamos que sea una URL.
         */
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('La URL de Google Maps no es válida.');
        }

        /*
         * Solo permitimos mapas incrustados de Google.
         */
        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        $path = (string) parse_url($value, PHP_URL_PATH);

        if (!in_array($host, ['www.google.com', 'google.com'], true) || !str_starts_with($path, '/maps/embed')) {
            throw new InvalidArgumentException(
                            'Debe introducir una URL de Google Maps obtenida desde Compartir → Insertar un mapa.'
                    );
        }

        return $value;
    }

    private function normalizeMultipleFiles(array $files): array {

        $result = [];

        if (!is_array($files['name'] ?? null)) {
            return $result;
        }

        foreach ($files['name'] as $index => $name) {

            $result[] = [
                'name' => $name,
                'full_path' => $files['full_path'][$index] ?? $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0
            ];
        }

        return $result;
    }
    
    private function replaceCoverImage(int $facility_id, array $file): void {

        $current_cover = $this->facilities_images_model->findCoverByFacilityId($facility_id);

        $new_image_id = $this->saveImageFile($facility_id, $file, 1);

        if ($new_image_id <= 0) {
            throw new RuntimeException('No se ha podido guardar la nueva imagen de portada.');
        }

        if ($current_cover !== null) {

            $old_filename = $current_cover['filename'];

            $this->facilities_images_model->deleteById((int) $current_cover['id']);

            $old_path = __DIR__ . '/../../../assets/images/facilities/' . $old_filename;

            if (is_file($old_path)) {
                unlink($old_path);
            }

            $this->audit_service->delete(
                    'wi_facilities_images',
                    $facility_id,
                    [
                        'facility_image_id' => (int) $current_cover['id'],
                        'facility_id' => $facility_id,
                        'filename' => $old_filename,
                        'is_cover' => 1
                    ],
                    __METHOD__,
                    'Sustitución de imagen de portada'
            );
        }
    }
    
    private function saveGalleryImage(int $facility_id, array $file): void {

        $image_id = $this->saveImageFile($facility_id, $file, 0);

        if ($image_id <= 0) {
            throw new RuntimeException('No se ha podido guardar una imagen de la galería.');
        }
    }

    private function saveImageFile(int $facility_id, array $file, int $is_cover): int {

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Se ha producido un error al subir la imagen.');
        }

        $mime_type = mime_content_type($file['tmp_name']);

        $allowed_types = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowed_types[$mime_type])) {
            throw new InvalidArgumentException('La imagen debe ser JPG, PNG o WEBP.');
        }

        $extension = $allowed_types[$mime_type];
        $filename = 'facility_' . $facility_id . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $directory = __DIR__ . '/../../../assets/images/facilities';
        $destination = $directory . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('No se ha podido guardar la imagen.');
        }

        $sort_order = $is_cover === 1 ? 1 : $this->facilities_images_model->getNextSortOrder($facility_id);

        $image_model = new facilitiesImagesModel();
        $image_model->setFacilityId($facility_id);
        $image_model->setFilename($filename);
        $image_model->setIsCover($is_cover);
        $image_model->setSortOrder($sort_order);

        $image_id = $image_model->add();

        if ($image_id <= 0) {

            if (is_file($destination)) {
                unlink($destination);
            }

            throw new RuntimeException('No se ha podido guardar la imagen en la base de datos.');
        }

        $this->audit_service->insert(
                'wi_facilities_images',
                $facility_id,
                [
                    'facility_image_id' => $image_id,
                    'facility_id' => $facility_id,
                    'filename' => $filename,
                    'is_cover' => $is_cover,
                    'sort_order' => $sort_order
                ],
                __METHOD__,
                $is_cover === 1 ? 'Añadir imagen de portada' : 'Añadir imagen a galería'
        );

        return $image_id;
    }
    
    private function deleteFacilityGalleryImage(int $facility_id, int $image_id): void {

        $image = $this->facilities_images_model->findById($image_id);

        if ($image === null) {
            throw new RuntimeException('La imagen no existe.');
        }

        if ((int) $image['facility_id'] !== $facility_id) {
            throw new RuntimeException('La imagen no pertenece a esta instalación.');
        }

        if ((int) $image['is_cover'] === 1) {
            throw new RuntimeException('La imagen de portada no se puede eliminar desde la galería.');
        }

        $deleted = $this->facilities_images_model->deleteById($image_id);

        if (!$deleted) {
            throw new RuntimeException('No se ha podido eliminar la imagen.');
        }

        $path = __DIR__ . '/../../../assets/images/facilities/' . $image['filename'];

        if (is_file($path)) {
            unlink($path);
        }

        $this->audit_service->delete(
                'wi_facilities_images',
                $facility_id,
                [
                    'facility_image_id' => $image_id,
                    'facility_id' => $facility_id,
                    'filename' => $image['filename'],
                    'is_cover' => 0,
                    'sort_order' => $image['sort_order']
                ],
                __METHOD__,
                'Eliminación de imagen de la galería'
        );
    }
    
    
}
