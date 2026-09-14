<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 23 ago 2026
 */

/**
 * reservationsService
 *
 * Gestión de reservas y disponibilidad.
 */
class reservationsService {

    use debugTrait;

    private facilitiesModel $facilities_model;
    private facilitiesAvailabilityModel $availability_model;
    private reservationsModel $reservations_model;
    private usersModel $users_model;
    private facilitiesPricesModel $prices_model;
    private ?auditLogsService $audit_service = null;
    private accessPinsService $access_pins_service;
    private mailService $mail_service;

    public function __construct() {

        $this->facilities_model = new facilitiesModel();
        $this->availability_model = new facilitiesAvailabilityModel();
        $this->reservations_model = new reservationsModel();
        $this->users_model = new usersModel();
        $this->prices_model = new facilitiesPricesModel();
        $this->audit_service = new auditLogsService();
        $this->access_pins_service = new accessPinsService();
        $this->mail_service = new mailService();
    }

    /**
     * Comprueba la disponibilidad de una instalación para una reserva.
     *
     * @param stdClass $params
     *
     * @return array
     */
    public function searchAvailability(stdClass $params): array {

        try {

            $facility_id = isset($params->facility_id) ? (int) $params->facility_id : 0;
            $user_id = isset($params->user_id) ? (int) $params->user_id : 0;
            $people_count = isset($params->people_count) ? (int) $params->people_count : 0;

            if ($facility_id <= 0) {
                return serviceResponse::error(
                                'Debe seleccionar una instalación.',
                                'FACILITY_REQUIRED'
                        );
            }

            if ($user_id <= 0) {
                return serviceResponse::error(
                                'Debe seleccionar un cliente.',
                                'USER_REQUIRED'
                        );
            }

            if ($people_count <= 0) {
                return serviceResponse::error(
                                'El número de personas no es válido.',
                                'PEOPLE_COUNT_INVALID'
                        );
            }


            /*
             * Instalación y tipo de reserva.
             */
            $facility = $this->facilities_model->findByIdWithBookingType(
                    $facility_id,
                    false
            );

            if (empty($facility)) {
                return serviceResponse::error(
                                'La instalación no existe.',
                                'FACILITY_NOT_FOUND'
                        );
            }

            if ((int) $facility['booking_enabled'] !== 1) {
                return serviceResponse::error(
                                'La instalación no permite reservas.',
                                'FACILITY_BOOKING_DISABLED'
                        );
            }

            if (empty($facility['booking_type_code'])) {
                return serviceResponse::error(
                                'La instalación no tiene configurado un tipo de reserva.',
                                'BOOKING_TYPE_NOT_CONFIGURED'
                        );
            }


            /*
             * Comprobamos que el usuario exista.
             *
             * No comprobamos que esté activo porque desde administración
             * permitimos reservar para un cliente recién creado pendiente
             * de establecer su contraseña.
             */
            $user = $this->users_model->findById($user_id, false);

            if (empty($user)) {
                return serviceResponse::error(
                                'El cliente seleccionado no existe.',
                                'USER_NOT_FOUND'
                        );
            }


            $booking_type = (string) $facility['booking_type_code'];
            $capacity = max(1, (int) $facility['capacity']);

            /*
             * RESERVA POR RANGO DE FECHAS.
             */
            if ($booking_type === 'DATE_RANGE') {

                return $this->searchDateRangeAvailability(
                                $facility,
                                $user_id,
                                $people_count,
                                $capacity,
                                $params
                        );
            }


            /*
             * RESERVA POR HORA CONCRETA.
             */
            if ($booking_type === 'SPECIFIC_HOUR') {

                return $this->searchSpecificHourAvailability(
                                $facility,
                                $user_id,
                                $people_count,
                                $capacity,
                                $params
                        );
            }


            return serviceResponse::error(
                            'El tipo de reserva de la instalación no es válido.',
                            'BOOKING_TYPE_INVALID'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'SEARCH_AVAILABILITY_EXCEPTION'
                    );
        }
    }

    public function prepareReservation(stdClass $params): array {

        try {

            $facility_id = isset($params->facility_id) ? (int) $params->facility_id : 0;
            $user_id = isset($params->user_id) ? (int) $params->user_id : 0;
            $people_count = isset($params->people_count) ? (int) $params->people_count : 0;

            $start_at = trim((string) ($params->start_at ?? ''));
            $end_at = trim((string) ($params->end_at ?? ''));
            $duration_minutes = 0;

            if (
                    $facility_id <= 0 ||
                    $user_id <= 0 ||
                    $people_count <= 0 ||
                    $start_at === '' ||
                    $end_at === ''
            ) {
                return serviceResponse::error(
                                'Los datos de la reserva no son válidos.',
                                'RESERVATION_DATA_INVALID'
                        );
            }

            /*
             * Instalación.
             */
            $facility = $this->facilities_model->findByIdWithBookingType(
                    $facility_id,
                    false
            );

            if (empty($facility)) {
                return serviceResponse::error(
                                'La instalación no existe.',
                                'FACILITY_NOT_FOUND'
                        );
            }

            if ((int) $facility['booking_enabled'] !== 1) {
                return serviceResponse::error(
                                'La instalación no permite reservas.',
                                'FACILITY_BOOKING_DISABLED'
                        );
            }

            /*
             * Cliente.
             */
            $user = $this->users_model->findById(
                    $user_id,
                    false
            );

            if (empty($user)) {
                return serviceResponse::error(
                                'El cliente seleccionado no existe.',
                                'USER_NOT_FOUND'
                        );
            }

            $booking_type = (string) $facility['booking_type_code'];

            /*
             * Volvemos a comprobar la disponibilidad desde cero.
             *
             * No confiamos únicamente en el resultado anterior.
             */
            $availability_params = new stdClass();

            $availability_params->facility_id = $facility_id;
            $availability_params->user_id = $user_id;
            $availability_params->people_count = $people_count;

            if ($booking_type === 'DATE_RANGE') {

                $availability_params->start_at = substr($start_at, 0, 10);
                $availability_params->end_at = substr($end_at, 0, 10);
            } elseif ($booking_type === 'SPECIFIC_HOUR') {

                $start = new DateTimeImmutable($start_at);
                $end = new DateTimeImmutable($end_at);

                $duration_minutes = (int) (($end->getTimestamp() - $start->getTimestamp()) / 60);

                if ($duration_minutes <= 0) {
                    return serviceResponse::error(
                                    'La duración de la reserva no es válida.',
                                    'RESERVATION_DURATION_INVALID'
                            );
                }

                $availability_params->reservation_date = $start->format('Y-m-d');
                $availability_params->reservation_hour = $start->format('H:i');
                $availability_params->duration_minutes = $duration_minutes;
            } else {

                return serviceResponse::error(
                                'El tipo de reserva de la instalación no es válido.',
                                'BOOKING_TYPE_INVALID'
                        );
            }

            $availability_result = $this->searchAvailability(
                    $availability_params
            );

            if (!$availability_result['success']) {
                return $availability_result;
            }

            /*
             * Usamos los datos recalculados por searchAvailability().
             *
             * De esta forma también obtenemos el precio actualizado.
             */
            $availability = $availability_result['data'];
            
            $available_bonuses = [];

            if (defined('_BONUSES_ENABLED') && _BONUSES_ENABLED) {

                /*
                 * Bonos utilizables por el cliente para esta instalación.
                 */
                $users_bonuses_model = new usersBonusesModel();

                $bonuses = $users_bonuses_model->findAvailableByUserAndFacility(
                        $user_id,
                        $facility_id,
                        $availability['start_at'],
                        $availability['end_at'],
                        true
                );

                /*
                 * Filtramos los bonos que realmente tengan capacidad
                 * suficiente para cubrir esta reserva.
                 */


                foreach ($bonuses as $bonus) {

                    $bonus_type = (string) $bonus->bonus_type;

                    if ($bonus_type === bonusesModel::TYPE_USES) {

                        if ((int) $bonus->remaining_uses >= (int) $availability['requested_units']) {
                            $available_bonuses[] = $bonus;
                        }
                    } elseif ($bonus_type === bonusesModel::TYPE_TIME) {

                        $available_bonuses[] = $bonus;
                    }
                }
            }

            /*
             * Métodos de pago activos asignados a la instalación.
             */
            $payment_methods_model = new facilitiesPaymentMethodsModel();

            $payment_methods = $payment_methods_model->findActiveByFacilityId(
                    $facility_id,
                    true
            );

            /*
             * Debe existir al menos una forma de pago:
             * bono o método de pago.
             */
            if (
                    empty($available_bonuses) &&
                    empty($payment_methods)
            ) {
                return serviceResponse::error(
                                'No hay ninguna forma de pago disponible para esta instalación.',
                                'PAYMENT_OPTION_NOT_AVAILABLE'
                        );
            }

            return serviceResponse::success(
                            'Datos de la reserva preparados correctamente.',
                            'RESERVATION_PREPARED',
                            [
                                'facility_id' => $facility_id,
                                'facility_name' => $facility['name'],
                                'user_id' => $user_id,
                                'user_name' => trim(
                                        ($user['first_name'] ?? '') . ' ' .
                                        ($user['last_name'] ?? '')
                                ),
                                'people_count' => $people_count,
                                'requested_units' => (int) $availability['requested_units'],
                                'booking_type' => $booking_type,
                                'start_at' => $availability['start_at'],
                                'end_at' => $availability['end_at'],
                                'duration_minutes' => $booking_type === 'SPECIFIC_HOUR' ? (int) ($availability['duration_minutes'] ?? 0) : null,
                                /*
                                 * Precio recalculado.
                                 */
                                'facility_price_id' => (int) $availability['facility_price_id'],
                                'price_name' => $availability['price_name'],
                                'price_description' => $availability['price_description'],
                                'unit_price' => (float) $availability['unit_price'],
                                'billing_unit_type_code' => $availability['billing_unit_type_code'],
                                'billing_unit_type_name' => $availability['billing_unit_type_name'],
                                'billing_period_code' => $availability['billing_period_code'],
                                'billing_period_name' => $availability['billing_period_name'],
                                'billable_units' => (float) $availability['billable_units'],
                                'unit_multiplier' => (int) $availability['unit_multiplier'],
                                'total_amount' => (float) $availability['total_amount'],
                                /*
                                 * Formas de pago.
                                 */
                                'bonuses' => $available_bonuses,
                                'payment_methods' => $payment_methods,
                                /*
                                 * Texto configurado en la instalación.
                                 */
                                'pending_payment_instructions' =>
                                $facility['pending_payment_instructions'] ?? null,
                                'booking_conditions' =>
                                $facility['booking_conditions'] ?? null
                            ]
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'PREPARE_RESERVATION_EXCEPTION'
                    );
        }
    }

    public function confirmReservation(stdClass $params): array {

        try {

            $facility_id = isset($params->facility_id) ? (int) $params->facility_id : 0;
            $user_id = isset($params->user_id) ? (int) $params->user_id : 0;
            $people_count = isset($params->people_count) ? (int) $params->people_count : 0;

            $start_at = trim((string) ($params->start_at ?? ''));
            $end_at = trim((string) ($params->end_at ?? ''));
            $payment_option = trim((string) ($params->payment_option ?? ''));

            if (
                    $facility_id <= 0 ||
                    $user_id <= 0 ||
                    $people_count <= 0 ||
                    $start_at === '' ||
                    $end_at === '' ||
                    $payment_option === ''
            ) {

                return serviceResponse::error(
                                'Los datos de la reserva no son válidos.',
                                'RESERVATION_DATA_INVALID'
                        );
            }

            /*
             * Instalación.
             */
            $facility = $this->facilities_model->findByIdWithBookingType($facility_id, false);

            if (empty($facility)) {
                return serviceResponse::error(
                                'La instalación no existe.',
                                'FACILITY_NOT_FOUND'
                        );
            }

            if ((int) $facility['booking_enabled'] !== 1) {
                return serviceResponse::error(
                                'La instalación no permite reservas.',
                                'FACILITY_BOOKING_DISABLED'
                        );
            }

            /*
             * Condiciones de reserva.
             */
            $booking_conditions = trim((string) ($facility['booking_conditions'] ?? ''));

            if ($booking_conditions !== '') {

                $booking_conditions_accepted = isset($params->booking_conditions_accepted) && $params->booking_conditions_accepted === true;

                if (!$booking_conditions_accepted) {
                    return serviceResponse::error(
                                    'Debe leer y aceptar las condiciones de reserva.',
                                    'BOOKING_CONDITIONS_NOT_ACCEPTED'
                            );
                }
            }

            /*
             * Cliente.
             */
            $user = $this->users_model->findById($user_id, false);

            if (empty($user)) {
                return serviceResponse::error(
                                'El cliente seleccionado no existe.',
                                'USER_NOT_FOUND'
                        );
            }

            $booking_type = (string) $facility['booking_type_code'];

            /*
             * Recalculamos disponibilidad y precio.
             */
            $availability_params = new stdClass();

            $availability_params->facility_id = $facility_id;
            $availability_params->user_id = $user_id;
            $availability_params->people_count = $people_count;

            if ($booking_type === 'DATE_RANGE') {

                $availability_params->start_at = substr($start_at, 0, 10);
                $availability_params->end_at = substr($end_at, 0, 10);
            } elseif ($booking_type === 'SPECIFIC_HOUR') {

                try {

                    $reservation_start = new DateTimeImmutable($start_at);
                    $reservation_end = new DateTimeImmutable($end_at);
                } catch (Throwable $e) {

                    return serviceResponse::error(
                                    'Las fechas de la reserva no son válidas.',
                                    'RESERVATION_DATETIME_INVALID'
                            );
                }

                $duration_seconds = $reservation_end->getTimestamp() - $reservation_start->getTimestamp();

                if ($duration_seconds <= 0 || $duration_seconds % 60 !== 0) {
                    return serviceResponse::error(
                                    'La duración de la reserva no es válida.',
                                    'RESERVATION_DURATION_INVALID'
                            );
                }

                $duration_minutes = (int) ($duration_seconds / 60);

                $availability_params->reservation_date = $reservation_start->format('Y-m-d');
                $availability_params->reservation_hour = $reservation_start->format('H:i');
                $availability_params->duration_minutes = $duration_minutes;
            } else {

                return serviceResponse::error(
                                'El tipo de reserva no es válido.',
                                'BOOKING_TYPE_INVALID'
                        );
            }

            $availability_result = $this->searchAvailability($availability_params);

            if (!$availability_result['success']) {
                return $availability_result;
            }

            $availability = $availability_result['data'];

            $requested_units = (int) $availability['requested_units'];
            $facility_price_id = (int) $availability['facility_price_id'];
            $unit_price = (float) $availability['unit_price'];
            $billable_units = (float) $availability['billable_units'];
            $total_amount = (float) $availability['total_amount'];

            /*
             * Forma de pago.
             *
             * BONUS:15
             * PAYMENT_METHOD:3
             */
            $payment_parts = explode(':', $payment_option, 2);

            if (count($payment_parts) !== 2) {
                return serviceResponse::error(
                                'La forma de pago seleccionada no es válida.',
                                'PAYMENT_OPTION_INVALID'
                        );
            }

            $payment_type = $payment_parts[0];
            $payment_id = (int) $payment_parts[1];

            if ($payment_id <= 0) {
                return serviceResponse::error(
                                'La forma de pago seleccionada no es válida.',
                                'PAYMENT_OPTION_INVALID'
                        );
            }

            $user_bonus_id = null;
            $payment_method_id = null;
            $payment_method_code = null;

            /*
             * Los métodos de pago normales crean inicialmente
             * una reserva pendiente.
             */
            $reservation_status_code = 'pending';

            /*
             * BONO.
             */
            if ($payment_type === 'BONUS') {
                
                if (!defined('_BONUSES_ENABLED') || !_BONUSES_ENABLED) {
                    return serviceResponse::error(
                                    'El sistema de bonos no está disponible.',
                                    'BONUSES_DISABLED'
                            );
                }

                $users_bonuses_model = new usersBonusesModel();

                $bonus = $users_bonuses_model->findAvailableById(
                        $payment_id,
                        $user_id,
                        $facility_id,
                        $availability['start_at'],
                        $availability['end_at'],
                        false
                );

                if (empty($bonus)) {
                    return serviceResponse::error(
                                    'El bono seleccionado no está disponible.',
                                    'USER_BONUS_NOT_AVAILABLE'
                            );
                }

                if (
                        (string) $bonus['bonus_type'] === bonusesModel::TYPE_USES &&
                        (int) $bonus['remaining_uses'] < $requested_units
                ) {
                    return serviceResponse::error(
                                    'El bono seleccionado no dispone de usos suficientes.',
                                    'USER_BONUS_INSUFFICIENT_USES'
                            );
                }

                $user_bonus_id = $payment_id;
                $reservation_status_code = 'confirmed';

                /*
                 * MÉTODO DE PAGO.
                 */
            } elseif ($payment_type === 'PAYMENT_METHOD') {

                $payment_methods_model = new facilitiesPaymentMethodsModel();

                $payment_methods = $payment_methods_model->findActiveByFacilityId($facility_id, false);

                $selected_payment_method = null;

                foreach ($payment_methods as $method) {

                    if ((int) $method['payment_method_id'] === $payment_id) {
                        $selected_payment_method = $method;
                        break;
                    }
                }

                if ($selected_payment_method === null) {
                    return serviceResponse::error(
                                    'El método de pago seleccionado no está disponible.',
                                    'PAYMENT_METHOD_NOT_AVAILABLE'
                            );
                }

                $payment_method_id = $payment_id;
                $payment_method_code = (string) $selected_payment_method['code'];
                $reservation_status_code = 'pending';
            } else {

                return serviceResponse::error(
                                'La forma de pago seleccionada no es válida.',
                                'PAYMENT_OPTION_INVALID'
                        );
            }

            /*
             * Estado de reserva.
             */
            $reservation_statuses_model = new reservationStatusesModel();

            $reservation_status = $reservation_statuses_model->findByCode(
                    $reservation_status_code,
                    false
            );

            if (empty($reservation_status)) {
                throw new RuntimeException('No se ha podido determinar el estado de la reserva.');
            }

            /*
             * Referencia.
             */
            $reference = $this->generateReference();

            /*
             * =====================================================
             * TRANSACCIÓN
             * =====================================================
             */
            $db = $this->reservations_model->getDb();

            $transaction_reservations_model = new reservationsModel($db);
            $transaction_facilities_model = new facilitiesModel($db);
            $transaction_availability_model = new facilitiesAvailabilityModel($db);
            $transaction_bonuses_model = new usersBonusesModel($db);
            $transaction_bonus_movements_model = new bonusMovementsModel($db);

            $transaction_reservations_model->beginTransaction();

            try {

                /*
                 * Bloqueamos la instalación.
                 */
                $locked_facility = $transaction_facilities_model->lockByIdForUpdate(
                        $facility_id,
                        false
                );

                if (empty($locked_facility)) {
                    throw new RuntimeException(
                                    'No se ha podido bloquear la instalación para realizar la reserva.'
                            );
                }

                $capacity = max(1, (int) $locked_facility['capacity']);

                /*
                 * =================================================
                 * SEGUNDA COMPROBACIÓN DE DISPONIBILIDAD
                 * DENTRO DE LA TRANSACCIÓN.
                 * =================================================
                 */
                if ($booking_type === 'DATE_RANGE') {

                    /*
                     * Para recorrer los días usamos exclusivamente
                     * la parte de fecha.
                     */
                    $reservation_start = new DateTimeImmutable(
                            substr($availability['start_at'], 0, 10) . ' 00:00:00'
                    );

                    $reservation_end = new DateTimeImmutable(
                            substr($availability['end_at'], 0, 10) . ' 00:00:00'
                    );

                    /*
                     * Las horas se vuelven a obtener de la instalación
                     * bloqueada dentro de la propia transacción.
                     */
                    $check_in_time = trim((string) ($locked_facility['check_in_time'] ?? '00:00:00'));
                    $check_out_time = trim((string) ($locked_facility['check_out_time'] ?? '00:00:00'));

                    if ($check_in_time === '') {
                        $check_in_time = '00:00:00';
                    }

                    if ($check_out_time === '') {
                        $check_out_time = '00:00:00';
                    }

                    $current = $reservation_start;

                    while ($current < $reservation_end) {

                        $day_start = $current;
                        $day_end = $current->modify('+1 day');

                        /*
                         * Disponibilidad configurada:
                         * comprobamos el día completo.
                         */
                        $day_start_db = $day_start->format('Y-m-d H:i:s');
                        $day_end_db = $day_end->format('Y-m-d H:i:s');

                        $day_availability = $transaction_availability_model->findCoveringPeriod(
                                $facility_id,
                                $day_start_db,
                                $day_end_db,
                                false
                        );

                        if (empty($day_availability)) {
                            throw new RuntimeException(
                                            'La disponibilidad ha cambiado. El día '
                                            . $day_start->format('d/m/Y')
                                            . ' ya no está disponible.'
                                    );
                        }

                        /*
                         * Periodo REAL de ocupación.
                         */
                        $occupancy_start = new DateTimeImmutable(
                                $day_start->format('Y-m-d') . ' ' . $check_in_time
                        );

                        $occupancy_end = new DateTimeImmutable(
                                $day_end->format('Y-m-d') . ' ' . $check_out_time
                        );

                        $occupancy_start_db = $occupancy_start->format('Y-m-d H:i:s');
                        $occupancy_end_db = $occupancy_end->format('Y-m-d H:i:s');

                        /*
                         * Volvemos a calcular ocupación utilizando
                         * las horas reales.
                         */
                        $reserved_units = $transaction_reservations_model->getReservedUnitsByPeriod(
                                $facility_id,
                                $occupancy_start_db,
                                $occupancy_end_db
                        );

                        $available_units = max(0, $capacity - $reserved_units);

                        if ($requested_units > $available_units) {
                            throw new RuntimeException(
                                            'La disponibilidad ha cambiado. No quedan plazas suficientes para el día '
                                            . $day_start->format('d/m/Y') . '.'
                                    );
                        }

                        $current = $day_end;
                    }
                } elseif ($booking_type === 'SPECIFIC_HOUR') {

                    $period_availability = $transaction_availability_model->findCoveringPeriod(
                            $facility_id,
                            $availability['start_at'],
                            $availability['end_at'],
                            false
                    );

                    if (empty($period_availability)) {
                        throw new RuntimeException(
                                        'La disponibilidad seleccionada ya no está disponible.'
                                );
                    }

                    $reserved_units = $transaction_reservations_model->getReservedUnitsByPeriod(
                            $facility_id,
                            $availability['start_at'],
                            $availability['end_at']
                    );

                    $available_units = max(0, $capacity - $reserved_units);

                    if ($requested_units > $available_units) {
                        throw new RuntimeException(
                                        'La disponibilidad ha cambiado. Ya no quedan unidades suficientes para realizar la reserva.'
                                );
                    }
                }

                /*
                 * =================================================
                 * SEGUNDA COMPROBACIÓN DEL BONO
                 * =================================================
                 */
                if ($payment_type === 'BONUS') {

                    $bonus = $transaction_bonuses_model->findAvailableById(
                            $user_bonus_id,
                            $user_id,
                            $facility_id,
                            $availability['start_at'],
                            $availability['end_at'],
                            false
                    );

                    if (empty($bonus)) {
                        throw new RuntimeException(
                                        'El bono seleccionado ya no está disponible.'
                                );
                    }

                    if (
                            (string) $bonus['bonus_type'] === bonusesModel::TYPE_USES &&
                            (int) $bonus['remaining_uses'] < $requested_units
                    ) {
                        throw new RuntimeException(
                                        'El bono seleccionado ya no dispone de usos suficientes.'
                                );
                    }
                }

                /*
                 * =================================================
                 * INSERT RESERVA
                 * =================================================
                 */
                $transaction_reservations_model->setReference($reference);
                $transaction_reservations_model->setUserId($user_id);
                $transaction_reservations_model->setFacilityId($facility_id);
                $transaction_reservations_model->setFacilityPriceId($facility_price_id);
                $transaction_reservations_model->setUserBonusId($user_bonus_id);
                $transaction_reservations_model->setPaymentMethodId($payment_method_id);
                $transaction_reservations_model->setPaymentReference(null);
                $transaction_reservations_model->setStartAt($availability['start_at']);
                $transaction_reservations_model->setEndAt($availability['end_at']);
                $transaction_reservations_model->setPeopleCount($people_count);
                $transaction_reservations_model->setReservedUnits($requested_units);
                $transaction_reservations_model->setUnitPrice($unit_price);
                $transaction_reservations_model->setBillableUnits($billable_units);
                $transaction_reservations_model->setTotalAmount($total_amount);
                $transaction_reservations_model->setReservationStatusId((int) $reservation_status['id']);
                $transaction_reservations_model->setCreatedByUserId(
                        isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null
                );

                $reservation_id = $transaction_reservations_model->add();

                if (!$reservation_id) {
                    throw new RuntimeException('No se ha podido crear la reserva.');
                }

                /*
 * Descontar bono y registrar movimiento.
 */
                if (
                        $payment_type === 'BONUS' &&
                        (string) $bonus['bonus_type'] === bonusesModel::TYPE_USES
                ) {

                    if (!$transaction_bonuses_model->decreaseRemainingUses($user_bonus_id, $requested_units)) {
                        throw new RuntimeException(
                                        'No se han podido descontar los usos del bono.'
                                );
                    }

                    $balance_after = (int) $bonus['remaining_uses'] - $requested_units;

                    $transaction_bonus_movements_model->setUserBonusId($user_bonus_id);
                    $transaction_bonus_movements_model->setReservationId($reservation_id);
                    $transaction_bonus_movements_model->setMovementType(bonusMovementsModel::TYPE_CONSUMPTION);
                    $transaction_bonus_movements_model->setQuantity(-$requested_units);
                    $transaction_bonus_movements_model->setBalanceAfter($balance_after);
                    $transaction_bonus_movements_model->setNotes('Consumo de bono por reserva');
                    $transaction_bonus_movements_model->setCreatedByUserId(
                            isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null
                    );

                    if (!$transaction_bonus_movements_model->add()) {
                        throw new RuntimeException(
                                        'No se ha podido registrar el movimiento del bono.'
                                );
                    }
                }

                $transaction_reservations_model->commit();
                
            } catch (Throwable $e) {

                $transaction_reservations_model->rollBack();

                throw $e;
            }

            /*
             * Control de acceso.
             */
            $access_warning = null;

            if ($reservation_status_code === 'confirmed') {

                $reservation_data = [
                    'id' => $reservation_id,
                    'reference' => $reference,
                    'user_id' => $user_id,
                    'facility_id' => $facility_id,
                    'start_at' => $availability['start_at'],
                    'end_at' => $availability['end_at'],
                    'people_count' => $people_count,
                    'reserved_units' => $requested_units,
                    'total_amount' => $total_amount
                ];

                $access_result = $this->createReservationAccess($reservation_data);

                if (!$access_result['success']) {

                    $access_warning = ' La reserva está confirmada, pero no se ha podido generar el acceso: '
                            . $access_result['message'];
                } elseif ($access_result['partial']) {

                    $access_warning = ' La reserva está confirmada, pero el PIN queda pendiente de sincronización en alguna cerradura.';
                }
            }

            /*
             * Correo.
             */
            $reservation_mail_data = [
                'id' => $reservation_id,
                'reference' => $reference,
                'user_id' => $user_id,
                'facility_id' => $facility_id,
                'start_at' => $availability['start_at'],
                'end_at' => $availability['end_at'],
                'people_count' => $people_count,
                'total_amount' => $total_amount
            ];

            $mail_warning = null;

            if ($reservation_status_code === 'pending') {

                $mail_result = $this->mail_service->sendPendingReservation(
                        $user,
                        $facility,
                        $reservation_mail_data
                );

                if (!$mail_result['success']) {
                    $mail_warning = ' ' . $mail_result['message'];
                }
            }

            /*
             * Auditoría.
             */
            $audit_data = [
                'reference' => $reference,
                'user_id' => $user_id,
                'facility_id' => $facility_id,
                'facility_price_id' => $facility_price_id,
                'user_bonus_id' => $user_bonus_id,
                'payment_method_id' => $payment_method_id,
                'start_at' => $availability['start_at'],
                'end_at' => $availability['end_at'],
                'people_count' => $people_count,
                'reserved_units' => $requested_units,
                'unit_price' => $unit_price,
                'billable_units' => $billable_units,
                'total_amount' => $total_amount,
                'reservation_status_id' => (int) $reservation_status['id']
            ];

            $this->audit_service->insert(
                    'wi_reservations',
                    $reservation_id,
                    $audit_data,
                    __METHOD__,
                    'Creación de reserva'
            );

            /*
             * Pago online.
             */
            $payment_url = null;

            if ($payment_method_code === 'online_payment') {

                /*
                 * Pendiente de integrar pasarela.
                 */
            }

            /*
             * Mensaje.
             */
            if ($reservation_status_code === 'confirmed') {

                $message = 'La reserva se ha creado y confirmado correctamente.';

                if ($access_warning !== null) {
                    $message .= $access_warning;
                }
            } else {

                $message = 'La reserva se ha creado correctamente y queda pendiente de confirmación.';
            }

            if ($mail_warning !== null) {
                $message .= $mail_warning;
            }

            return serviceResponse::success(
                            $message,
                            'RESERVATION_CREATED',
                            [
                                'reservation_id' => $reservation_id,
                                'reference' => $reference,
                                'status' => $reservation_status_code,
                                'payment_method_code' => $payment_method_code,
                                'payment_url' => $payment_url
                            ]
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'RESERVATION_CREATE_EXCEPTION'
                    );
        }
    }

    public function changeReservationPayment(int $reservation_id, int $payment_method_id): array {

        try {

            if ($reservation_id <= 0) {
                return serviceResponse::error(
                                'La reserva seleccionada no es válida.',
                                'RESERVATION_ID_INVALID'
                        );
            }

            if ($payment_method_id <= 0) {
                return serviceResponse::error(
                                'El método de pago seleccionado no es válido.',
                                'PAYMENT_METHOD_ID_INVALID'
                        );
            }

            /*
             * Reserva actual.
             */
            $reservation = $this->reservations_model->findById($reservation_id,false);

            if (empty($reservation)) {
                return serviceResponse::error(
                                'La reserva no existe.',
                                'RESERVATION_NOT_FOUND'
                        );
            }

            /*
             * Si la reserva usa bono, no permitimos cambiar
             * el método de pago desde aquí.
             */
            if (!empty($reservation['user_bonus_id'])) {
                return serviceResponse::error(
                                'No se puede cambiar el método de pago de una reserva realizada con bono.',
                                'RESERVATION_USES_BONUS'
                        );
            }

            $facility_id = (int) $reservation['facility_id'];

            /*
             * Comprobamos que el nuevo método de pago
             * esté activo y asignado a esta instalación.
             */
            $facilities_payment_methods_model = new facilitiesPaymentMethodsModel();

            $payment_methods = $facilities_payment_methods_model->findActiveByFacilityId(
                    $facility_id,
                    false
            );

            $selected_payment_method = null;

            foreach ($payment_methods as $payment_method) {

                if ((int) $payment_method['payment_method_id'] === $payment_method_id) {
                    $selected_payment_method = $payment_method;
                    break;
                }
            }

            if ($selected_payment_method === null) {
                return serviceResponse::error(
                                'El método de pago seleccionado no está disponible para esta instalación.',
                                'PAYMENT_METHOD_NOT_AVAILABLE'
                        );
            }

            /*
             * Si ya tiene ese método, no hacemos nada.
             */
            if ((int) $reservation['payment_method_id'] === $payment_method_id) {
                return serviceResponse::success(
                                'La reserva ya tiene seleccionado ese método de pago.',
                                'RESERVATION_PAYMENT_UNCHANGED'
                        );
            }

            $old_values = [
                'payment_method_id' => $reservation['payment_method_id']
            ];

            $new_values = [
                'payment_method_id' => $payment_method_id
            ];

            $updated = $this->reservations_model->updatePaymentMethodById(
                    $reservation_id,
                    $payment_method_id
            );

            if (!$updated) {
                throw new RuntimeException(
                                'No se ha podido actualizar el método de pago de la reserva.'
                        );
            }

            /*
             * Auditoría.
             */
            $this->audit_service->update(
                    'wi_reservations',
                    $reservation_id,
                    $old_values,
                    $new_values,
                    __METHOD__,
                    [],
                    'Cambio de método de pago'
            );

            return serviceResponse::success(
                            'El método de pago se ha actualizado correctamente.',
                            'RESERVATION_PAYMENT_UPDATED'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'RESERVATION_PAYMENT_UPDATE_EXCEPTION'
                    );
        }
    }

    public function changeReservationStatus(int $reservation_id, int $status_id, ?string $cancellation_reason = null): array {

        try {

            if ($reservation_id <= 0) {
                return serviceResponse::error(
                                'La reserva seleccionada no es válida.',
                                'RESERVATION_ID_INVALID'
                        );
            }

            if ($status_id <= 0) {
                return serviceResponse::error(
                                'El estado seleccionado no es válido.',
                                'RESERVATION_STATUS_INVALID'
                        );
            }

            $reservation = $this->reservations_model->findById(
                    $reservation_id,
                    false
            );

            if (empty($reservation)) {
                return serviceResponse::error(
                                'La reserva no existe.',
                                'RESERVATION_NOT_FOUND'
                        );
            }

            $reservation_statuses_model = new reservationStatusesModel();

            $current_status = $reservation_statuses_model->findById(
                    (int) $reservation['reservation_status_id'],
                    false
            );

            if (empty($current_status)) {
                return serviceResponse::error(
                                'No se ha podido determinar el estado actual de la reserva.',
                                'CURRENT_RESERVATION_STATUS_NOT_FOUND'
                        );
            }

            /*
             * Estados finales que ya no pueden modificarse.
             */
            $locked_statuses = [
                'cancelled',
                'rejected',
                'no_show',
                'completed'
            ];

            if (in_array($current_status['code'], $locked_statuses, true)) {
                return serviceResponse::error(
                                'El estado de esta reserva ya no puede modificarse.',
                                'RESERVATION_STATUS_LOCKED'
                        );
            }

            $new_status = $reservation_statuses_model->findById(
                    $status_id,
                    false
            );

            if (empty($new_status)) {
                return serviceResponse::error(
                                'El estado seleccionado no existe.',
                                'RESERVATION_STATUS_NOT_FOUND'
                        );
            }

            if ((int) $reservation['reservation_status_id'] === $status_id) {
                return serviceResponse::success(
                                'La reserva ya tiene seleccionado ese estado.',
                                'RESERVATION_STATUS_UNCHANGED'
                        );
            }
            
            if ($current_status['code'] === 'confirmed' && $new_status['code'] === 'pending') {
                return serviceResponse::error(
                                'Una reserva confirmada no puede volver al estado pendiente.',
                                'RESERVATION_STATUS_TRANSITION_NOT_ALLOWED'
                        );
            }


            $cancellation_reason = $cancellation_reason !== null ? trim($cancellation_reason) : null;

            if ($new_status['code'] === 'cancelled' && empty($cancellation_reason)) {
                return serviceResponse::error(
                                'Debe indicar el motivo de cancelación.',
                                'CANCELLATION_REASON_REQUIRED'
                        );
            }

            /*
             * Si pasa a cancelada o rechazada y utilizó bono,
             * devolvemos las unidades consumidas.
             */
            //$return_bonus = !empty($reservation['user_bonus_id']) && in_array($new_status['code'], ['cancelled', 'rejected'], true);
            $return_bonus = false;

            /*
             * Compartimos conexión para que el cambio de estado
             * y la devolución del bono sean atómicos.
             */
            $db = $this->reservations_model->getDb();

            $transaction_reservations_model = new reservationsModel($db);
            $transaction_bonuses_model = new usersBonusesModel($db);
            $transaction_bonus_movements_model = new bonusMovementsModel($db);

            $transaction_reservations_model->beginTransaction();

            try {

                $bonus = null;

                if (
                        !empty($reservation['user_bonus_id']) &&
                        in_array($new_status['code'], ['cancelled', 'rejected'], true)
                ) {

                    $bonus = $transaction_bonuses_model->findDetailById(
                            (int) $reservation['user_bonus_id'],
                            false
                    );

                    if (empty($bonus)) {
                        throw new RuntimeException(
                                        'No se ha podido obtener el bono utilizado en la reserva.'
                                );
                    }

                    $return_bonus = (string) $bonus['bonus_type'] === bonusesModel::TYPE_USES;
                }

                if ($new_status['code'] === 'cancelled') {
                    $updated = $transaction_reservations_model->cancelById($reservation_id, $status_id, $cancellation_reason);
                } else {
                    $updated = $transaction_reservations_model->updateStatusById($reservation_id, $status_id);
                }

                if (!$updated) {
                    throw new RuntimeException('No se ha podido actualizar el estado de la reserva.');
                }


                if ($return_bonus) {

                    $returned_units = (int) $reservation['reserved_units'];
                    $balance_after = (int) $bonus['remaining_uses'] + $returned_units;

                    $returned = $transaction_bonuses_model->increaseRemainingUses(
                            (int) $reservation['user_bonus_id'],
                            $returned_units
                    );

                    if (!$returned) {
                        throw new RuntimeException(
                                        'No se han podido devolver los usos al bono.'
                                );
                    }

                    $transaction_bonus_movements_model->setUserBonusId(
                            (int) $reservation['user_bonus_id']
                    );
                    $transaction_bonus_movements_model->setReservationId($reservation_id);
                    $transaction_bonus_movements_model->setMovementType(
                            bonusMovementsModel::TYPE_REFUND
                    );
                    $transaction_bonus_movements_model->setQuantity($returned_units);
                    $transaction_bonus_movements_model->setBalanceAfter($balance_after);
                    $transaction_bonus_movements_model->setNotes('Devolución de bono por cancelación o rechazo de reserva');
                    $transaction_bonus_movements_model->setCreatedByUserId(
                            isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null
                    );

                    if (!$transaction_bonus_movements_model->add()) {
                        throw new RuntimeException(
                                        'No se ha podido registrar la devolución del bono.'
                                );
                    }
                }

                $transaction_reservations_model->commit();
            } catch (Throwable $e) {

                $transaction_reservations_model->rollBack();

                throw $e;
            }

            $old_values = [
                'reservation_status_id' => (int) $reservation['reservation_status_id']
            ];

            $new_values = [
                'reservation_status_id' => $status_id
            ];
            
            if ($new_status['code'] === 'cancelled') {
                $new_values['cancellation_reason'] = $cancellation_reason;
            }

            if ($return_bonus) {
                $new_values['bonus_returned_units'] = (int) $reservation['reserved_units'];
            }

            $access_warning = null;
            $mail_warning = null;
            $access_revoked = false;

            /*
             * La reserva entra en CONFIRMED.
             */
            if (
                    $current_status['code'] !== 'confirmed' && $new_status['code'] === 'confirmed'
            ) {

                $access_result = $this->createReservationAccess(
                        $reservation
                );

                if (!$access_result['success']) {

                    $access_warning = ' La reserva se ha confirmado, pero no se ha podido generar el acceso: '
                            . $access_result['message'];
                } elseif ($access_result['partial']) {

                    $access_warning = ' La reserva se ha confirmado, pero el PIN queda pendiente de sincronización en alguna cerradura.';
                }
            }

            /*
             * La reserva deja de estar CONFIRMED.
             */
            if (
                    $current_status['code'] === 'confirmed' && $new_status['code'] !== 'confirmed'
            ) {

                $access_result = $this->revokeReservationAccess(
                        $reservation
                );

                $access_revoked = (bool) ($access_result['access_revoked'] ?? false);

                if (!$access_result['success']) {

                    $access_warning = ' El estado se ha actualizado, pero no se ha podido eliminar el PIN de todas las cerraduras: '
                            . $access_result['message'];
                }
            }

            /*
             * Correo para cancelada o rechazada.
             */
            if (in_array($new_status['code'], ['cancelled', 'rejected'], true)) {

                $facility = $this->facilities_model->findById(
                        (int) $reservation['facility_id'],
                        false
                );

                $user = $this->users_model->findById(
                        (int) $reservation['user_id'],
                        false
                );

                if (!empty($facility) && !empty($user)) {

                    $mail_result = $this->mail_service->sendReservationCancelled(
                            $user,
                            $facility,
                            $reservation,
                            $new_status['code'],
                            $access_revoked,
                            $cancellation_reason
                    );

                    if (!$mail_result['success']) {
                        $mail_warning = ' ' . $mail_result['message'];
                    }
                }
            }

            /*
             * Auditoría.
             */
            $this->audit_service->update(
                    'wi_reservations',
                    $reservation_id,
                    $old_values,
                    $new_values,
                    __METHOD__,
                    [],
                    'Cambio de estado de reserva'
            );

            $message = 'El estado de la reserva se ha actualizado correctamente.';

            if ($access_warning !== null) {
                $message .= $access_warning;
            }

            if ($mail_warning !== null) {
                $message .= $mail_warning;
            }

            return serviceResponse::success(
                            $message,
                            'RESERVATION_STATUS_UPDATED'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'RESERVATION_STATUS_UPDATE_EXCEPTION'
                    );
        }
    }
    
    public function updateReservationNotes(int $reservation_id, ?string $notes): array {

        try {

            if ($reservation_id <= 0) {
                return serviceResponse::error(
                                'La reserva seleccionada no es válida.',
                                'RESERVATION_ID_INVALID'
                        );
            }

            $reservation = $this->reservations_model->findById($reservation_id, false);

            if (empty($reservation)) {
                return serviceResponse::error(
                                'La reserva no existe.',
                                'RESERVATION_NOT_FOUND'
                        );
            }

            $notes = $notes !== null ? trim($notes) : null;
            $notes = $notes !== '' ? $notes : null;

            $updated = $this->reservations_model->updateNotesById($reservation_id, $notes);

            if (!$updated) {
                throw new RuntimeException('No se han podido actualizar las notas de la reserva.');
            }

            $this->audit_service->update(
                    'wi_reservations',
                    $reservation_id,
                    [
                        'notes' => $reservation['notes']
                    ],
                    [
                        'notes' => $notes
                    ],
                    __METHOD__,
                    [],
                    'Actualización de notas de reserva'
            );

            return serviceResponse::success(
                            'Las notas de la reserva se han actualizado correctamente.',
                            'RESERVATION_NOTES_UPDATED'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'RESERVATION_NOTES_UPDATE_EXCEPTION'
                    );
        }
    }
    
    public function addReservationGuest(int $reservation_id, string $full_name, ?int $document_type_id, string $document_number, int $is_holder): array {

        try {

            if ($reservation_id <= 0) {
                return serviceResponse::error(
                                'La reserva seleccionada no es válida.',
                                'RESERVATION_ID_INVALID'
                        );
            }

            $reservation = $this->reservations_model->findById($reservation_id, false);

            if (empty($reservation)) {
                return serviceResponse::error(
                                'La reserva no existe.',
                                'RESERVATION_NOT_FOUND'
                        );
            }

            $facility = $this->facilities_model->findById((int) $reservation['facility_id'], false);

            if (empty($facility)) {
                return serviceResponse::error(
                                'La instalación de la reserva no existe.',
                                'FACILITY_NOT_FOUND'
                        );
            }

            if ((int) $facility['requires_guest_information'] !== 1) {
                return serviceResponse::error(
                                'Esta instalación no requiere datos de huéspedes.',
                                'GUEST_INFORMATION_NOT_REQUIRED'
                        );
            }
            
            $validation = $this->validateGuestModification($reservation_id);

            if (!$validation['success']) {
                return $validation;
            }

            $full_name = trim($full_name);
            $document_number = trim($document_number);
            $is_holder = $is_holder === 1 ? 1 : 0;

            if ($full_name === '') {
                return serviceResponse::error(
                                'Debe indicar el nombre completo del huésped.',
                                'GUEST_NAME_REQUIRED'
                        );
            }

            $guests_model = new reservationsGuestsModel();

            $current_guests = $guests_model->countByReservationId($reservation_id);

            if ($current_guests >= (int) $reservation['people_count']) {
                return serviceResponse::error(
                                'Ya se han registrado todos los huéspedes de la reserva.',
                                'RESERVATION_GUEST_LIMIT_REACHED'
                        );
            }

            if ($document_type_id !== null) {

                $document_types_model = new documentTypesModel();
                $document_type = $document_types_model->findById($document_type_id, false);

                if (empty($document_type) || (int) $document_type['status'] !== documentTypesModel::STATUS_ACTIVE) {
                    return serviceResponse::error(
                                    'El tipo de documento seleccionado no es válido.',
                                    'DOCUMENT_TYPE_INVALID'
                            );
                }

                if ($document_number === '') {
                    return serviceResponse::error(
                                    'Debe indicar el número de documento.',
                                    'DOCUMENT_NUMBER_REQUIRED'
                            );
                }
            } elseif ($document_number !== '') {

                return serviceResponse::error(
                                'Debe seleccionar el tipo de documento.',
                                'DOCUMENT_TYPE_REQUIRED'
                        );
            }

            if ($is_holder === 1) {

                $holder = $guests_model->findHolderByReservationId($reservation_id, false);

                if (!empty($holder)) {
                    return serviceResponse::error(
                                    'La reserva ya tiene un huésped marcado como titular.',
                                    'RESERVATION_HOLDER_EXISTS'
                            );
                }
            }

            $guests_model->setReservationId($reservation_id);
            $guests_model->setFullName($full_name);
            $guests_model->setDocumentTypeId($document_type_id);
            $guests_model->setDocumentNumber($document_number !== '' ? $document_number : null);
            $guests_model->setIsHolder($is_holder);

            $guest_id = $guests_model->add();

            if ($guest_id <= 0) {
                throw new RuntimeException('No se ha podido guardar el huésped.');
            }

            $this->audit_service->insert(
                    'wi_reservations_guests',
                    $reservation_id,
                    [
                        'guest_id' => $guest_id,
                        'reservation_id' => $reservation_id,
                        'full_name' => $full_name,
                        'document_type_id' => $document_type_id,
                        'document_number' => $document_number !== '' ? $document_number : null,
                        'is_holder' => $is_holder
                    ],
                    __METHOD__,
                    'Añadir huésped a reserva'
            );

            return serviceResponse::success(
                            'El huésped se ha añadido correctamente.',
                            'RESERVATION_GUEST_ADDED'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'RESERVATION_GUEST_ADD_EXCEPTION'
                    );
        }
    }

    public function editReservationGuest(int $reservation_id, int $guest_id, string $full_name, ?int $document_type_id, string $document_number, int $is_holder): array {

        try {

            if ($reservation_id <= 0 || $guest_id <= 0) {
                return serviceResponse::error(
                                'El huésped seleccionado no es válido.',
                                'RESERVATION_GUEST_INVALID'
                        );
            }

            $reservation = $this->reservations_model->findById($reservation_id, false);

            if (empty($reservation)) {
                return serviceResponse::error(
                                'La reserva no existe.',
                                'RESERVATION_NOT_FOUND'
                        );
            }

            $guests_model = new reservationsGuestsModel();

            $guest = $guests_model->findById($guest_id, false);

            if (empty($guest) || (int) $guest['reservation_id'] !== $reservation_id) {
                return serviceResponse::error(
                                'El huésped no pertenece a esta reserva.',
                                'RESERVATION_GUEST_NOT_FOUND'
                        );
            }
            
            $validation = $this->validateGuestModification($reservation_id);

            if (!$validation['success']) {
                return $validation;
            }

            $full_name = trim($full_name);
            $document_number = trim($document_number);
            $is_holder = $is_holder === 1 ? 1 : 0;

            if ($full_name === '') {
                return serviceResponse::error(
                                'Debe indicar el nombre completo del huésped.',
                                'GUEST_NAME_REQUIRED'
                        );
            }

            if ($document_type_id !== null) {

                $document_types_model = new documentTypesModel();
                $document_type = $document_types_model->findById($document_type_id, false);

                if (empty($document_type) || (int) $document_type['status'] !== documentTypesModel::STATUS_ACTIVE) {
                    return serviceResponse::error(
                                    'El tipo de documento seleccionado no es válido.',
                                    'DOCUMENT_TYPE_INVALID'
                            );
                }

                if ($document_number === '') {
                    return serviceResponse::error(
                                    'Debe indicar el número de documento.',
                                    'DOCUMENT_NUMBER_REQUIRED'
                            );
                }
            } elseif ($document_number !== '') {

                return serviceResponse::error(
                                'Debe seleccionar el tipo de documento.',
                                'DOCUMENT_TYPE_REQUIRED'
                        );
            }

            /*
             * Solo puede existir un titular.
             * Excluimos al propio huésped que estamos editando.
             */
            if ($is_holder === 1) {

                $holder = $guests_model->findHolderByReservationIdExceptId($reservation_id, $guest_id, false);

                if (!empty($holder)) {
                    return serviceResponse::error(
                                    'La reserva ya tiene otro huésped marcado como titular.',
                                    'RESERVATION_HOLDER_EXISTS'
                            );
                }
            }

            $old_values = [
                'full_name' => $guest['full_name'],
                'document_type_id' => $guest['document_type_id'],
                'document_number' => $guest['document_number'],
                'is_holder' => (int) $guest['is_holder']
            ];

            $guests_model->setId($guest_id);
            $guests_model->setReservationId($reservation_id);
            $guests_model->setFullName($full_name);
            $guests_model->setDocumentTypeId($document_type_id);
            $guests_model->setDocumentNumber($document_number !== '' ? $document_number : null);
            $guests_model->setIsHolder($is_holder);

            if (!$guests_model->update()) {
                throw new RuntimeException('No se ha podido actualizar el huésped.');
            }

            $new_values = [
                'full_name' => $full_name,
                'document_type_id' => $document_type_id,
                'document_number' => $document_number !== '' ? $document_number : null,
                'is_holder' => $is_holder
            ];

            $this->audit_service->update(
                    'wi_reservations_guests',
                    $reservation_id,
                    $old_values,
                    $new_values,
                    __METHOD__,
                    [],
                    'Actualización de huésped de reserva'
            );

            return serviceResponse::success(
                            'Los datos del huésped se han actualizado correctamente.',
                            'RESERVATION_GUEST_UPDATED'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'RESERVATION_GUEST_UPDATE_EXCEPTION'
                    );
        }
    }

    public function deleteReservationGuest(int $reservation_id, int $guest_id): array {

        try {

            if ($reservation_id <= 0 || $guest_id <= 0) {
                return serviceResponse::error(
                                'El huésped seleccionado no es válido.',
                                'RESERVATION_GUEST_INVALID'
                        );
            }

            $reservation = $this->reservations_model->findById($reservation_id, false);

            if (empty($reservation)) {
                return serviceResponse::error(
                                'La reserva no existe.',
                                'RESERVATION_NOT_FOUND'
                        );
            }

            $guests_model = new reservationsGuestsModel();
            $guest = $guests_model->findById($guest_id, false);

            if (empty($guest) || (int) $guest['reservation_id'] !== $reservation_id) {
                return serviceResponse::error(
                                'El huésped no pertenece a esta reserva.',
                                'RESERVATION_GUEST_NOT_FOUND'
                        );
            }

            $old_values = [
                'id' => (int) $guest['id'],
                'reservation_id' => (int) $guest['reservation_id'],
                'full_name' => $guest['full_name'],
                'document_type_id' => $guest['document_type_id'],
                'document_number' => $guest['document_number'],
                'is_holder' => (int) $guest['is_holder']
            ];

            if (!$guests_model->deleteById($guest_id)) {
                throw new RuntimeException('No se ha podido eliminar el huésped.');
            }

            $this->audit_service->delete(
                    'wi_reservations_guests',
                    $reservation_id,
                    $old_values,
                    __METHOD__,
                    'Eliminar huésped de reserva'
            );

            return serviceResponse::success(
                            'El huésped se ha eliminado correctamente.',
                            'RESERVATION_GUEST_DELETED'
                    );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            $e->getMessage(),
                            'RESERVATION_GUEST_DELETE_EXCEPTION'
                    );
        }
    }
    
    public function completeExpiredReservations(): array {

        $result = [
            'success' => true,
            'message' => '',
            'total' => 0,
            'completed' => 0,
            'no_show' => 0,
            'errors' => 0,
            'error_details' => []
        ];

        $access_pins_model = new accessPinsModel();
        $access_logs_model = new ttlockAccessLogsModel();

        try {

            $reservations = $this->reservations_model->findExpiredConfirmed(false);

            if (empty($reservations)) {

                $result['message'] = 'No hay reservas finalizadas pendientes de completar.';

                return $result;
            }

            $result['total'] = count($reservations);

            foreach ($reservations as $reservation) {

                $reservation_id = (int) $reservation['id'];

                /*
                 * Determinamos el estado final de la reserva.
                 *
                 * Si la reserva tiene PIN de acceso, comprobamos si se utilizó
                 * correctamente al menos una vez.
                 *
                 * Si no tiene PIN, no podemos determinar la asistencia mediante
                 * el control de acceso y se considera completada.
                 */
                $pin = $access_pins_model->findByReservationId(
                        $reservation_id,
                        false
                );

                $has_access = true;

                if (!empty($pin)) {
                    $has_access = $access_logs_model->existsGrantedByAccessPinId((int) $pin['id']);
                }

                $new_status_id = $has_access ? reservationsModel::STATUS_COMPLETED : reservationsModel::STATUS_NO_SHOW;

                /*
                 * Primero eliminamos el acceso.
                 *
                 * Si falla, dejamos la reserva CONFIRMED para que
                 * el siguiente cron vuelva a intentarlo.
                 */
                $access_result = $this->revokeReservationAccess(
                        $reservation
                );

                if (!$access_result['success']) {

                    $result['success'] = false;
                    $result['errors']++;

                    $result['error_details'][] = [
                        'reservation_id' => $reservation_id,
                        'reference' => $reservation['reference'] ?? '',
                        'message' => $access_result['message']
                    ];

                    continue;
                }

                /*
                 * Marcamos la reserva como completada.
                 */
                $updated = $this->reservations_model->updateStatusById(
                        $reservation_id,
                        $new_status_id
                );

                if (!$updated) {

                    $result['success'] = false;
                    $result['errors']++;

                    $result['error_details'][] = [
                        'reservation_id' => $reservation_id,
                        'reference' => $reservation['reference'] ?? '',
                        'message' => 'No se ha podido marcar la reserva como completada.'
                    ];

                    continue;
                }

                /*
                 * Auditoría.
                 */
                $this->audit_service->update(
                        'wi_reservations',
                        $reservation_id,
                        [
                            'reservation_status_id' => (int) $reservation['reservation_status_id']
                        ],
                        [
                            'reservation_status_id' => $new_status_id
                        ],
                        __METHOD__,
                        [],
                        'Reserva completada automáticamente'
                );

                if ($new_status_id === reservationsModel::STATUS_COMPLETED) {
                    $result['completed']++;
                } else {
                    $result['no_show']++;
                }
            }

            if ($result['errors'] > 0) {

                $result['message'] = 'Proceso finalizado con errores. '
                        . $result['completed'] . ' reservas completadas, '
                        . $result['no_show'] . ' no presentadas y '
                        . $result['errors'] . ' con errores.';
            } else {

                $result['message'] = 'Proceso finalizado correctamente. '
                        . $result['completed'] . ' reservas completadas y '
                        . $result['no_show'] . ' no presentadas.';
            }
        } catch (Throwable $e) {

            $result['success'] = false;
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    private function validateGuestModification(int $reservation_id): array {

        if ($reservation_id <= 0) {
            return serviceResponse::error(
                            'La reserva no es válida.',
                            'RESERVATION_INVALID'
                    );
        }

        $reservations_model = new reservationsModel();

        $reservation = $reservations_model->findById(
                $reservation_id,
                false
        );

        if (empty($reservation)) {
            return serviceResponse::error(
                            'La reserva no existe.',
                            'RESERVATION_NOT_FOUND'
                    );
        }

        /*
         * Comprobamos el estado de la reserva.
         */
        $reservation_statuses_model = new reservationStatusesModel();

        $status = $reservation_statuses_model->findById(
                (int) $reservation['reservation_status_id'],
                false
        );

        if (empty($status)) {
            return serviceResponse::error(
                            'No se ha podido determinar el estado de la reserva.',
                            'RESERVATION_STATUS_NOT_FOUND'
                    );
        }

        /*
         * Estados en los que ya no se permite modificar
         * la información de los huéspedes.
         */
        $locked_statuses = [
            'CANCELLED',
            'REJECTED',
            'NO_SHOW',
            'COMPLETED'
        ];

        if (in_array(strtoupper((string) $status['code']), $locked_statuses, true)) {
            return serviceResponse::error(
                            'Ya no se pueden modificar los datos de huéspedes de esta reserva.',
                            'RESERVATION_GUESTS_LOCKED'
                    );
        }

        /*
         * Tampoco permitimos modificar huéspedes cuando
         * la reserva ya ha finalizado.
         */
        if (!empty($reservation['end_at']) && strtotime($reservation['end_at']) < time()) {
            return serviceResponse::error(
                            'Ya no se pueden modificar los datos de huéspedes porque la reserva ha finalizado.',
                            'RESERVATION_FINISHED'
                    );
        }

        return serviceResponse::success(
                        '',
                        'RESERVATION_GUESTS_ALLOWED',
                        [
                            'reservation' => $reservation
                        ]
                );
    }

    private function createReservationAccess(array $reservation): array {

    $result = [
        'success' => false,
        'partial' => false,
        'message' => '',
        'pin_id' => 0,
        'pin' => null
    ];

    try {

        $reservation_id = (int) ($reservation['id'] ?? 0);
        $facility_id = (int) ($reservation['facility_id'] ?? 0);
        $user_id = (int) ($reservation['user_id'] ?? 0);

        if ($reservation_id <= 0 || $facility_id <= 0 || $user_id <= 0) {
            throw new InvalidArgumentException(
                    'Los datos de la reserva para generar el acceso no son válidos.'
            );
        }

        /*
         * Instalación.
         */
        $facility = $this->facilities_model->findById(
                $facility_id,
                false
        );

        if (empty($facility)) {
            throw new RuntimeException(
                    'La instalación de la reserva no existe.'
            );
        }

        /*
         * Cliente.
         */
        $user = $this->users_model->findById(
                $user_id,
                false
        );

        if (empty($user)) {
            throw new RuntimeException(
                    'No se ha podido obtener el cliente de la reserva.'
            );
        }

        /*
         * Comprobamos si realmente existe control de acceso
         * operativo en la instalación.
         */
        $devices = [];

        if ((int) $facility['access_enabled'] === 1) {

            $facilities_devices_model = new facilitiesDevicesModel();

            $devices = $facilities_devices_model->findByFacilityId(
                    $facility_id,
                    false
            );
        }

        $requires_access = (int) $facility['access_enabled'] === 1
                && !empty($devices);

        /*
         * Sin control de acceso real:
         *
         * - access_enabled = 0
         * - access_enabled = 1 pero no hay cerraduras
         *
         * En ambos casos enviamos el correo normal de confirmación.
         */
        if (!$requires_access) {

            $mail_result = $this->mail_service->sendReservationConfirmed(
                    $user,
                    $facility,
                    $reservation
            );

            $result['success'] = true;

            if ((int) $facility['access_enabled'] === 1 && empty($devices)) {

                $result['message'] = 'La reserva se ha confirmado correctamente, pero la instalación tiene activado el control de acceso y no tiene ninguna cerradura asociada.';

            } else {

                $result['message'] = 'La reserva se ha confirmado correctamente.';
            }

            if (!$mail_result['success']) {
                $result['message'] .= ' ' . $mail_result['message'];
            }

            return $result;
        }

        /*
         * Evitamos generar dos PIN para la misma reserva.
         */
        $access_pins_model = new accessPinsModel();

        $existing_pin = $access_pins_model->findByReservationId(
                $reservation_id,
                false
        );

        if (!empty($existing_pin)) {

            $result['success'] = true;
            $result['partial'] = (int) $existing_pin['pin_status_id'] === 5;
            $result['pin_id'] = (int) $existing_pin['id'];
            $result['message'] = 'La reserva ya tiene un PIN asociado.';

            return $result;
        }

        /*
         * Fechas reales de acceso.
         */
        $start = new DateTimeImmutable($reservation['start_at']);
        $end = new DateTimeImmutable($reservation['end_at']);

        $before_minutes = max(
                0,
                (int) $facility['pin_valid_before_minutes']
        );

        $after_minutes = max(
                0,
                (int) $facility['pin_valid_after_minutes']
        );

        if ($before_minutes > 0) {
            $start = $start->modify('-' . $before_minutes . ' minutes');
        }

        if ($after_minutes > 0) {
            $end = $end->modify('+' . $after_minutes . ' minutes');
        }

        $valid_from = $start->format('Y-m-d H:i:s');
        $valid_until = $end->format('Y-m-d H:i:s');

        /*
         * Generamos el PIN y se sincroniza automáticamente
         * con todos los dispositivos de la instalación.
         */
        $pin_result = $this->access_pins_service->createReservationPin(
                $reservation_id,
                $facility_id,
                $valid_from,
                $valid_until
        );

        $result['success'] = (bool) $pin_result['success'];
        $result['partial'] = (bool) ($pin_result['partial'] ?? false);
        $result['message'] = $pin_result['message'];
        $result['pin_id'] = (int) ($pin_result['id'] ?? 0);
        $result['pin'] = $pin_result['pin'] ?? null;

        /*
         * Solo enviamos el correo con PIN cuando está
         * completamente sincronizado.
         */
        if (
                $result['success']
                && !$result['partial']
                && !empty($result['pin'])
        ) {

            $mail_result = $this->mail_service->sendReservationAccess(
                    $user,
                    $facility,
                    $reservation,
                    $result['pin'],
                    $valid_from,
                    $valid_until
            );

                if (!$mail_result['success']) {
                    $result['message'] .= ' ' . $mail_result['message'];
                }
            }
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    private function revokeReservationAccess(array $reservation): array {

        $result = [
            'success' => false,
            'access_revoked' => false,
            'message' => ''
        ];

        try {

            $reservation_id = (int) ($reservation['id'] ?? 0);

            if ($reservation_id <= 0) {
                throw new InvalidArgumentException(
                                'Los datos de la reserva para eliminar el acceso no son válidos.'
                        );
            }

            /*
             * Comprobamos directamente si la reserva tiene PIN.
             *
             * No dependemos del estado actual de access_enabled,
             * porque la instalación podría haberse modificado después
             * de crear la reserva.
             */
            $access_pins_model = new accessPinsModel();

            $pin = $access_pins_model->findByReservationId(
                    $reservation_id,
                    false
            );

            if (empty($pin)) {

                $result['success'] = true;
                $result['message'] = 'La reserva no tiene ningún PIN asociado.';

                return $result;
            }

            $pin_result = $this->access_pins_service->revokeReservationPin(
                    $reservation_id
            );

            $result['success'] = (bool) $pin_result['success'];
            $result['message'] = $pin_result['message'];

            if ($result['success']) {
                $result['access_revoked'] = true;
            }
        } catch (Throwable $e) {

            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Comprueba disponibilidad para una reserva por fechas.
     */
    private function searchDateRangeAvailability(array $facility, int $user_id, int $people_count, int $capacity, stdClass $params): array {

        $start_date = trim((string) ($params->start_at ?? ''));
        $end_date = trim((string) ($params->end_at ?? ''));

        if ($start_date === '' || $end_date === '') {
            return serviceResponse::error(
                            'Debe indicar las fechas de la reserva.',
                            'RESERVATION_DATES_REQUIRED'
                    );
        }

        $check_in_time = trim((string) ($facility['check_in_time'] ?? '00:00:00'));
        $check_out_time = trim((string) ($facility['check_out_time'] ?? '00:00:00'));

        if ($check_in_time === '') {
            $check_in_time = '00:00:00';
        }

        if ($check_out_time === '') {
            $check_out_time = '00:00:00';
        }

        try {

            /*
             * Fechas de calendario.
             *
             * Se utilizan para recorrer los días de la reserva
             * y comprobar la disponibilidad configurada.
             */
            $range_start = new DateTimeImmutable($start_date . ' 00:00:00');
            $range_end = new DateTimeImmutable($end_date . ' 00:00:00');

            /*
             * Fechas reales de la reserva.
             */
            $start = new DateTimeImmutable($start_date . ' ' . $check_in_time);
            $end = new DateTimeImmutable($end_date . ' ' . $check_out_time);
        } catch (Throwable $e) {

            return serviceResponse::error(
                            'Las fechas indicadas no son válidas.',
                            'RESERVATION_DATES_INVALID'
                    );
        }

        /*
         * En DATE_RANGE comparamos los días seleccionados,
         * no las horas reales.
         *
         * Ejemplo:
         *
         * entrada: 02/09 15:00
         * salida:  03/09 11:00
         *
         * es una reserva válida.
         */
        if ($range_end <= $range_start) {
            return serviceResponse::error(
                            'La fecha de fin debe ser posterior a la fecha de inicio.',
                            'RESERVATION_DATE_RANGE_INVALID'
                    );
        }

        /*
         * En DATE_RANGE cada persona consume una unidad reservable.
         */
        $requested_units = $people_count;

        if ($requested_units > $capacity) {
            return serviceResponse::error(
                            'El número de personas supera la capacidad de la instalación.',
                            'CAPACITY_EXCEEDED'
                    );
        }

        /*
         * Comprobamos cada día de forma independiente.
         *
         * La fecha final es exclusiva.
         *
         * 01/09 -> 05/09
         *
         * comprobará:
         *
         * 01
         * 02
         * 03
         * 04
         */
        $current = $range_start;

        $minimum_available_units = $capacity;
        $days = [];

        while ($current < $range_end) {

            /*
             * Día de calendario.
             *
             * Esto se utiliza exclusivamente para comprobar
             * que exista disponibilidad configurada.
             */
            $day_start = $current;
            $day_end = $current->modify('+1 day');

            $day_start_db = $day_start->format('Y-m-d H:i:s');
            $day_end_db = $day_end->format('Y-m-d H:i:s');

            /*
             * Debe existir disponibilidad para todo ese día.
             */
            $availability = $this->availability_model->findCoveringPeriod(
                    (int) $facility['id'],
                    $day_start_db,
                    $day_end_db,
                    false
            );

            if (empty($availability)) {
                return serviceResponse::error(
                                'No hay disponibilidad para el día ' . $day_start->format('d/m/Y') . '.',
                                'FACILITY_DATE_NOT_AVAILABLE'
                        );
            }

            /*
             * Periodo REAL de ocupación.
             *
             * Ejemplo:
             *
             * check_in  = 15:00
             * check_out = 11:00
             *
             * Para el 02/09 comprobamos:
             *
             * 02/09 15:00 -> 03/09 11:00
             */
            $occupancy_start = new DateTimeImmutable(
                    $day_start->format('Y-m-d') . ' ' . $check_in_time
            );

            $occupancy_end = new DateTimeImmutable(
                    $day_end->format('Y-m-d') . ' ' . $check_out_time
            );

            $occupancy_start_db = $occupancy_start->format('Y-m-d H:i:s');
            $occupancy_end_db = $occupancy_end->format('Y-m-d H:i:s');

            /*
             * Calculamos ocupación utilizando las horas reales.
             */
            $reserved_units = $this->reservations_model->getReservedUnitsByPeriod(
                    (int) $facility['id'],
                    $occupancy_start_db,
                    $occupancy_end_db
            );

            $available_units = max(0, $capacity - $reserved_units);

            if ($requested_units > $available_units) {
                return serviceResponse::error(
                                'No hay capacidad suficiente para el día ' . $day_start->format('d/m/Y') . '.',
                                'INSUFFICIENT_CAPACITY'
                        );
            }

            $minimum_available_units = min($minimum_available_units, $available_units);

            $days[] = [
                'date' => $day_start->format('Y-m-d'),
                'availability_id' => (int) $availability['id'],
                'reserved_units' => $reserved_units,
                'available_units' => $available_units
            ];

            $current = $day_end;
        }

        /*
         * Fechas reales que tendrá finalmente la reserva.
         */
        $start_at = $start->format('Y-m-d H:i:s');
        $end_at = $end->format('Y-m-d H:i:s');

        $price = $this->calculatePrice(
                $facility,
                $start_at,
                $end_at,
                $people_count,
                $requested_units
        );

        return serviceResponse::success(
                        'Hay disponibilidad para realizar la reserva.',
                        'AVAILABILITY_FOUND',
                        [
                            'facility_id' => (int) $facility['id'],
                            'facility_name' => $facility['name'],
                            'user_id' => $user_id,
                            'booking_type' => 'DATE_RANGE',
                            'start_at' => $start_at,
                            'end_at' => $end_at,
                            'people_count' => $people_count,
                            'requested_units' => $requested_units,
                            'capacity' => $capacity,
                            'reserved_units' => $capacity - $minimum_available_units,
                            'available_units' => $minimum_available_units,
                            'remaining_units' => $minimum_available_units - $requested_units,
                            'facility_price_id' => $price['facility_price_id'],
                            'price_name' => $price['price_name'],
                            'price_description' => $price['price_description'],
                            'unit_price' => $price['unit_price'],
                            'billing_unit_type_code' => $price['billing_unit_type_code'],
                            'billing_unit_type_name' => $price['billing_unit_type_name'],
                            'billing_period_code' => $price['billing_period_code'],
                            'billing_period_name' => $price['billing_period_name'],
                            'billable_units' => $price['billable_units'],
                            'unit_multiplier' => $price['unit_multiplier'],
                            'total_amount' => $price['total_amount'],
                            'days' => $days
                        ]
                );
    }

    /**
     * Comprueba disponibilidad para una reserva por horas.
     */
    private function searchSpecificHourAvailability(array $facility, int $user_id, int $people_count, int $capacity, stdClass $params): array {

        $reservation_date = trim((string) ($params->reservation_date ?? ''));
        $reservation_hour = trim((string) ($params->reservation_hour ?? ''));
        $duration_minutes = isset($params->duration_minutes) ? (int) $params->duration_minutes : 0;

        if ($reservation_date === '' || $reservation_hour === '') {
            return serviceResponse::error(
                            'Debe indicar la fecha y hora de la reserva.',
                            'RESERVATION_DATETIME_REQUIRED'
                    );
        }

        if ($duration_minutes <= 0) {
            return serviceResponse::error(
                            'Debe seleccionar la duración de la reserva.',
                            'RESERVATION_DURATION_REQUIRED'
                    );
        }

        $interval_minutes = (int) ($facility['booking_interval_minutes'] ?? 0);
        $minimum_booking_minutes = (int) ($facility['minimum_booking_minutes'] ?? 0);
        $maximum_booking_minutes = 240;

        if ($interval_minutes <= 0) {
            return serviceResponse::error(
                            'La instalación no tiene configurado el intervalo de reserva.',
                            'BOOKING_INTERVAL_NOT_CONFIGURED'
                    );
        }

        if ($minimum_booking_minutes <= 0) {
            return serviceResponse::error(
                            'La instalación no tiene configurada la duración mínima de reserva.',
                            'MINIMUM_BOOKING_NOT_CONFIGURED'
                    );
        }

        if ($duration_minutes < $minimum_booking_minutes) {
            return serviceResponse::error(
                            'La duración seleccionada es inferior a la duración mínima permitida.',
                            'RESERVATION_DURATION_TOO_SHORT'
                    );
        }

        if ($duration_minutes > $maximum_booking_minutes) {
            return serviceResponse::error(
                            'La duración máxima de una reserva es de 4 horas.',
                            'RESERVATION_DURATION_TOO_LONG'
                    );
        }

        if ($duration_minutes % $minimum_booking_minutes !== 0) {
            return serviceResponse::error(
                            'La duración seleccionada debe ser múltiplo de la duración mínima configurada.',
                            'RESERVATION_DURATION_INVALID'
                    );
        }

        try {

            $start = new DateTimeImmutable(
                    $reservation_date . ' ' . $reservation_hour . ':00'
            );
        } catch (Throwable $e) {

            return serviceResponse::error(
                            'La fecha u hora indicada no es válida.',
                            'RESERVATION_DATETIME_INVALID'
                    );
        }

        /*
         * Comprobamos que la hora seleccionada respete
         * el intervalo configurado.
         */
        $minutes = (int) $start->format('i');

        if ($minutes % $interval_minutes !== 0) {
            return serviceResponse::error(
                            'La hora seleccionada no respeta el intervalo permitido para esta instalación.',
                            'BOOKING_INTERVAL_INVALID'
                    );
        }

        /*
         * Calculamos la hora final según la duración seleccionada.
         */
        $end = $start->modify('+' . $duration_minutes . ' minutes');

        $start_at = $start->format('Y-m-d H:i:s');
        $end_at = $end->format('Y-m-d H:i:s');

        /*
         * Debe existir una disponibilidad que cubra completamente
         * todo el periodo solicitado.
         */
        $availability = $this->availability_model->findCoveringPeriod(
                (int) $facility['id'],
                $start_at,
                $end_at,
                false
        );

        if (empty($availability)) {
            return serviceResponse::error(
                            'La instalación no tiene disponibilidad para la fecha, hora y duración seleccionadas.',
                            'FACILITY_NOT_AVAILABLE'
                    );
        }

        /*
         * En reservas por horas cada reserva consume una unidad.
         */
        $requested_units = 1;

        $reserved_units = $this->reservations_model->getReservedUnitsByPeriod(
                (int) $facility['id'],
                $start_at,
                $end_at
        );

        $available_units = max(0, $capacity - $reserved_units);

        if ($requested_units > $available_units) {
            return serviceResponse::error(
                            'No hay disponibilidad para la fecha, hora y duración seleccionadas.',
                            'INSUFFICIENT_CAPACITY'
                    );
        }

        $price = $this->calculatePrice(
                $facility,
                $start_at,
                $end_at,
                $people_count,
                $requested_units
        );

        return serviceResponse::success(
                        'Hay disponibilidad para realizar la reserva.',
                        'AVAILABILITY_FOUND',
                        [
                            'facility_id' => (int) $facility['id'],
                            'facility_name' => $facility['name'],
                            'user_id' => $user_id,
                            'booking_type' => 'SPECIFIC_HOUR',
                            'start_at' => $start_at,
                            'end_at' => $end_at,
                            'duration_minutes' => $duration_minutes,
                            'people_count' => $people_count,
                            'requested_units' => $requested_units,
                            'capacity' => $capacity,
                            'reserved_units' => $reserved_units,
                            'available_units' => $available_units,
                            'availability_id' => (int) $availability['id'],
                            'booking_interval_minutes' => $interval_minutes,
                            'minimum_booking_minutes' => $minimum_booking_minutes,
                            'facility_price_id' => $price['facility_price_id'],
                            'price_name' => $price['price_name'],
                            'price_description' => $price['price_description'],
                            'unit_price' => $price['unit_price'],
                            'billing_unit_type_code' => $price['billing_unit_type_code'],
                            'billing_unit_type_name' => $price['billing_unit_type_name'],
                            'billing_period_code' => $price['billing_period_code'],
                            'billing_period_name' => $price['billing_period_name'],
                            'billable_units' => $price['billable_units'],
                            'unit_multiplier' => $price['unit_multiplier'],
                            'total_amount' => $price['total_amount']
                        ]
                );
    }

    private function calculatePrice(array $facility, string $start_at, string $end_at, int $people_count, int $requested_units): array {

        $prices = $this->prices_model->findActiveByFacilityAndPeriod(
                (int) $facility['id'],
                $start_at,
                $end_at,
                false
        );

        if (empty($prices)) {
            throw new RuntimeException(
                            'No hay ningún precio configurado para esta instalación y periodo.'
                    );
        }

        $start = new DateTimeImmutable($start_at);
        $end = new DateTimeImmutable($end_at);

        $seconds = $end->getTimestamp() - $start->getTimestamp();

        if ($seconds <= 0) {
            throw new RuntimeException('El periodo de la reserva no es válido.');
        }

        $hours = $seconds / 3600;

        /*
         * DATE_RANGE se factura según los días de calendario
         * seleccionados, independientemente de la hora de
         * entrada y salida.
         */
        if (($facility['booking_type_code'] ?? '') === 'DATE_RANGE') {

            $calendar_start = new DateTimeImmutable($start->format('Y-m-d') . ' 00:00:00');
            $calendar_end = new DateTimeImmutable($end->format('Y-m-d') . ' 00:00:00');

            $days = (int) $calendar_start->diff($calendar_end)->days;
        } else {

            $calendar_start = $start;
            $calendar_end = $end;

            $days = $seconds / 86400;
        }

        $options = [];

        foreach ($prices as $price) {

            $period_code = $price['billing_period_code'];
            $unit_code = $price['billing_unit_type_code'];

            $billable_units = null;

            switch ($period_code) {

                case 'hour':

                    if ($hours < 1) {
                        continue 2;
                    }

                    $billable_units = $hours;
                    break;

                case 'day':

                    if ($days < 1 || floor((float) $days) != $days) {
                        continue 2;
                    }

                    $billable_units = $days;
                    break;

                case 'week':

                    if ($days < 7 || ((int) $days % 7) !== 0) {
                        continue 2;
                    }

                    $billable_units = $days / 7;
                    break;

                case 'month':

                    /*
                     * Para DATE_RANGE utilizamos las fechas de calendario.
                     * Para SPECIFIC_HOUR seguimos utilizando los DateTime
                     * reales.
                     */
                    $interval = $calendar_start->diff($calendar_end);

                    if (
                            $interval->d !== 0 ||
                            $interval->h !== 0 ||
                            $interval->i !== 0 ||
                            $interval->s !== 0
                    ) {
                        continue 2;
                    }

                    $billable_units = ($interval->y * 12) + $interval->m;

                    if ($billable_units <= 0) {
                        continue 2;
                    }

                    break;

                case 'fixed':

                    $billable_units = 1;
                    break;

                default:
                    continue 2;
            }

            /*
             * Multiplicador según unidad de facturación.
             */
            switch ($unit_code) {

                case 'person':
                    $unit_multiplier = $people_count;
                    break;

                case 'facility':
                    $unit_multiplier = $requested_units;
                    break;

                case 'booking':
                    $unit_multiplier = 1;
                    break;

                default:
                    continue 2;
            }

            $unit_price = (float) $price['price'];

            $total_amount = $unit_price * $billable_units * $unit_multiplier;

            $options[] = [
                'facility_price_id' => (int) $price['id'],
                'price_name' => $price['name'],
                'price_description' => $price['description'],
                'unit_price' => $unit_price,
                'billing_unit_type_code' => $unit_code,
                'billing_unit_type_name' => $price['billing_unit_type_name'],
                'billing_period_code' => $period_code,
                'billing_period_name' => $price['billing_period_name'],
                'billable_units' => (float) $billable_units,
                'unit_multiplier' => $unit_multiplier,
                'total_amount' => round($total_amount, 2)
            ];
        }

        if (empty($options)) {
            throw new RuntimeException(
                            'No hay ninguna tarifa aplicable al periodo seleccionado.'
                    );
        }

        /*
         * Si varias tarifas son válidas, aplicamos
         * automáticamente la más económica.
         */
        usort(
                $options,
                fn(array $a, array $b): int => $a['total_amount'] <=> $b['total_amount']
        );

        return $options[0];
    }

    private function generateReference(): string {

        do {

            $reference = 'RES-'
                    . date('Ymd')
                    . '-'
                    . strtoupper(bin2hex(random_bytes(3)));

            $exists = $this->reservations_model->findByReference(
                    $reference,
                    false
            );
        } while (!empty($exists));

        return $reference;
    }
}
