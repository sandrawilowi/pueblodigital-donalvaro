<?php
declare(strict_types=1);

/**
 * Admin Panel Controller
 *
 * @author Wilowi - Sandra Campos
 * @since 06/07/2020
 *
 */


/**
 * Class for control the panel
 */
final class reservasController extends controller{

    
    /**
     * Construct
     */
    public function __construct() {

        parent::__construct();
        $this->logs = new logsModel('reservas','reservas.log');
        $this->link = _URL_ENVIRONMENT;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->getSessionData();
        $this->checkSession();
    }

    /**
     * Main function
     */
    public function main(string $extra = "") {

        parent::main();
        
        if ($this->exitSession) {

	    $this->extra = 'SESSION_FALSE';
	    header('Location: ' . $this->link);
	    echo $this->redirectTemplate();	    
	    die();
	}

	if(!empty($this->user_id)){

	    $this->printHeaderHTML($extra);            
            $this->chargeAllHtml(true,$extra);
            
            echo $this->template->fetch("template.html");
            
            $this->printFooterJs($extra);
	    $this->printHeaderPos($extra);
	    
	}else {

	    header('Location: ' . $this->link);	    
	    echo $this->redirectTemplate();	    
	    die();
	}	
        
    }
    
    
   

    
    
    /**
     * Print the head for the web. Included all libraries and styles.
     */
    protected function printHeaderHTML(string $extra = "") {

        parent::printHeaderHTML($extra);

	$this->template_header->assign('robots',_ROBOTS_FALSE);
	$this->template_header->assign('urlCssCntrl',$extra._CSS.'panel/panel.css'._ASSET_VERSION);
        $this->template_header->assign('extraUrl',$extra);
	$header = $this->template_header->fetch("common/panel/header_html.html");	
	echo $header;

    }
    
    /**
     * Print the end of the web page.
     */
    protected function printHeaderPos(string $extra = '') {
	
	$modals_tp = new newSmarty();
        $modals_tp->assign('extraUrl',$extra);
        $modals = $modals_tp->fetch("common/modals.html");
        echo $modals;
	
	echo '</body>';
	echo '</html>';
    }
    
    /**
     * Main function to control the ajax requests.
     * @param Object $params params from ajax
     * @params Object $files -> files like photos
     * @return json
     */
    public function doAction(stdClass $params, array $files = array()) {

        $actionsMap = [
            
            'cargarOpcionesReserva' => ['method' => 'cargarOpcionesReserva', 'params' => true, 'files' => false],
            'addQuickClient' => ['method' => 'addQuickClient', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'searchAvailability' => ['method' => 'searchAvailability', 'params' => true, 'files' => false],
            'prepareReservation' => ['method' => 'prepareReservation', 'params' => true, 'files' => false],
            'confirmReservation' => ['method' => 'confirmReservation', 'params' => true, 'files' => false],
            'changeReservationPayment' => ['method' => 'changeReservationPayment', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'changeReservationStatus' => ['method' => 'changeReservationStatus', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'editReservation' => ['method' => 'editReservation', 'params' => true, 'files' => false], 'roles' => ['system', 'manager'],
            'addReservationGuest' => ['method' => 'addReservationGuest', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'editReservationGuest' => ['method' => 'editReservationGuest', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'deleteReservationGuest' => ['method' => 'deleteReservationGuest', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'loadCalendarReservations' => ['method' => 'loadCalendarReservations', 'params' => true, 'files' => false]

        ];

        return parent::executeAction($actionsMap, $params, $files);

    }

    /**
     * Carga todo el html necesario que es común
     * @param type $show_crear
     */
    private function chargeAllHtml(bool $show_crear = true, string $extra = '') {

        $footer = $this->chargeFooter();
        $menu = $this->chargeMenu($extra);
        $navBar = parent::chargeNavBar($extra);
        $this->template->assign('footer', $footer);
        $this->template->assign('menu', $menu);
        $this->template->assign('navBar', $navBar);

        if($this->accessPage($this->page)){
            $this->chargePage($extra);
        }
        else{
            $this->chargeNotAccessPage();
        }
    }
    
    
    /**
     * Cargar html de la página solicitada
     */
    private function chargePage(string $extra = '') {

        $plantilla_html = new newSmarty();
        $array_bread = array();
        $old_page = '';

        // -- Mapeo de páginas a funciones y categorías
        $pageMappings = [
            'reservas' => ['method' => 'cargarReservas', 'prefix' => 'reservations'],
            'pagos' => ['method' => 'cargarPagos', 'prefix' => 'reservations'],
            'calendario-reservas' => ['method' => 'cargarCalendario', 'prefix' => 'reservations'],
            'nueva-reserva' => ['method' => 'cargarNuevaReserva', 'prefix' => 'reservations'],
            'editreserva' => ['method' => 'editReserva', 'prefix' => 'reservations',
                'breadcrumb' => ['reservas' => 'Reservas', 'title' => 'Editar Reserva']],
            'auditreserva' => ['method' => 'auditReserva', 'prefix' => 'reservations',
                'breadcrumb' => ['reservas' => 'Reservas', 'editreserva/'.$this->model_id => 'Editar Reserva', 'title' => 'Actividad']],
            

        ];

        // Verificamos si existe la página en el mapeo
        if (isset($pageMappings[$this->page])) {
            $pageData = $pageMappings[$this->page];

            // Función específica
            if (isset($pageData['method'])) {
                $this->{$pageData['method']}($plantilla_html);
            }

            // Breadcrumbs
            if (isset($pageData['breadcrumb'])) {
                foreach ($pageData['breadcrumb'] as $key => $value) {
                    if ($key === 'title') {
                        $this->chargeLastPageBreadCrumb($array_bread, $value);
                    } else {
                        $this->chargePageBreadCrumb($array_bread, $value, $extra . $key);
                    }
                }
            }

            if (isset($pageData['prefix'])) {
                $old_page = $this->page;
                $this->page = "{$pageData['prefix']}/$old_page";
            }
        }
        
        $page = $plantilla_html->fetch("panel/$this->page.html");

        if (!empty($old_page)) {
            $this->page = $old_page;
        }

        $this->template->assign('page', $page);
        $breadcrumb = parent::chargeBreadCrumb($extra, $array_bread);
        $this->template->assign('breadcrumb', $breadcrumb);
    }


    /**
     * Print the html for the footer
     * @return string
     */
    private function chargeFooter(){

        $plantilla_footer = new newSmarty();
	$plantilla_footer->assign('year', $this->today->format('Y'));
	$footer = $plantilla_footer->fetch('common/panel/footer.html');

	return $footer;
	
    }

    
    /**
     * Print the html for the menu
     * @return string
     */
    private function chargeMenu(string $extraUrl = ''){
        
        $plantilla_html = new newSmarty();
	$plantilla_html->assign('year', $this->today->format('Y'));
        $plantilla_html->assign('extraUrl', $extraUrl);
        $plantilla_html->assign('nameUser', $this->name_user);
        $plantilla_html->assign('bonusesEnabled', _BONUSES_ENABLED);
	$html = $plantilla_html->fetch('common/panel/menu.html');
	
	return $html;
	
    }
    
        
    private function cargarReservas(&$plantilla_html){

        $reservas = new reservationsModel();
        $reservation_statuses_model = new reservationStatusesModel();
        $payments_method_model = new paymentMethodsModel();
        $facilities_model = new facilitiesModel();
        $users_model = new usersModel();

        $results = $reservas->findAll(true);
        $result_res_status = $reservation_statuses_model->findAllActive(true);
        $payment_method_result = $payments_method_model->findAllActive(true);
        $facilities_result = $facilities_model->findAllBookingEnabled(true);
        $clients = $users_model->findAllActiveByRole(3, true);
        
        $facility_payment_methods_model = new facilitiesPaymentMethodsModel();

        $payment_methods_by_facility = [];

        foreach ($results as $reservation) {

            $facility_id = (int) $reservation->facility_id;

            if (!isset($payment_methods_by_facility[$facility_id])) {
                $payment_methods_by_facility[$facility_id] = $facility_payment_methods_model->findActiveByFacilityId($facility_id, true);
            }
        }

        $plantilla_html->assign('payment_methods_by_facility', $payment_methods_by_facility);

        $plantilla_html->assign('title', $this->chargeTitleHeader('Reservas', false, 'nueva-reserva'));
        $plantilla_html->assign('results', $results);
        $plantilla_html->assign('reservation_statuses', $result_res_status);
        $plantilla_html->assign('payment_methods', $payment_method_result);
        $plantilla_html->assign('facilities', $facilities_result);
        $plantilla_html->assign('clients', $clients);
        

    }
    
    private function cargarPagos(&$plantilla_html){
        
        $plantilla_html->assign('title', $this->chargeTitleHeader('Pagos', true));
    }

    private function cargarCalendario(&$plantilla_html) {

        $facilities_model = new facilitiesModel();

        /*
         * En calendario cargamos todas las instalaciones,
         * también las inactivas o borradas, porque podemos
         * consultar reservas históricas.
         */
        $facilities = $facilities_model->findAll(true);

        $plantilla_html->assign('facilities', $facilities);
        $plantilla_html->assign('title', $this->chargeTitleHeader('Calendario de Reservas', true));
    }

    private function cargarNuevaReserva(&$plantilla_html) {

        $model_facilities = new facilitiesModel();
        $model_users = new usersModel();
        $facilities = $model_facilities->findAllBookingEnabled(true);
        $clients = $model_users->findAllActiveByRole(3, true);

        $plantilla_html->assign('facilities', $facilities);
        $plantilla_html->assign('clients', $clients);
        $plantilla_html->assign('title', $this->chargeTitleHeader('Nueva Reserva'));
    }

    private function editReserva(&$plantilla_html) {

        $repository = new reservationsRepository();
        $reservation_statuses_model = new reservationStatusesModel();
        $payments_method_model = new paymentMethodsModel();
        $facility_payment_methods_model = new facilitiesPaymentMethodsModel();
        $document_types_model = new documentTypesModel();

        $result = $repository->getReservationEditData(
                (int) $this->model_id
        );

        if (empty($result)) {
            $this->chargeNotFoundPage(
                    'La reserva solicitada no existe o ya no está disponible.',
                    '../reservas',
                    'Volver a reservas'
            );
            return;
        }

        $result_res_status = $reservation_statuses_model->findAllActive(true);
        $payment_method_result = $payments_method_model->findAllActive(true);
        $document_types_result = $document_types_model->findAllActive(true);

        $facility_id = (int) $result->facility_id;

        $payment_methods_by_facility = [];

        $payment_methods_by_facility[$facility_id] = $facility_payment_methods_model->findActiveByFacilityId(
                $facility_id,
                true
        );

        $plantilla_html->assign(
                'payment_methods_by_facility',
                $payment_methods_by_facility
        );

        $plantilla_html->assign(
                'reservation_statuses',
                $result_res_status
        );

        $plantilla_html->assign(
                'payment_methods',
                $payment_method_result
        );

        $plantilla_html->assign(
                'document_types',
                $document_types_result
        );

        $plantilla_html->assign(
                'model',
                $result
        );

        $plantilla_html->assign(
                'title',
                $this->chargeTitleHeader(
                        'Editar Reserva: ' . $result->reference
                )
        );
    }
    
    private function auditReserva(&$plantilla_html){
        
        $model = new reservationsModel();
        $reservation = $model->findById(intval($this->model_id), true);
        
        $audit = new auditLogsRepository();
        $results = $audit->getReservationAudit(intval($this->model_id));
        
        $plantilla_audit = new newSmarty();
        $plantilla_audit->assign('results',$results);
        $audit_tmp = $plantilla_audit->fetch('panel/audit_template.html');
        
        $plantilla_html->assign('title',$this->chargeTitleHeader('Actividad: '.$reservation->reference));
        $plantilla_html->assign('audit_tmp',$audit_tmp);
    }


    /**
     * Print the js libraries
     * @param type $extra
     */
    protected function printFooterJs(string $extra='') {

        parent::printFooterJs($extra);
        
        $this->template_footer->assign('urlJsCntrl', $extra . _JS . 'panel/reservas.js'._ASSET_VERSION);
        
        $footerJs = $this->template_footer->fetch('common/panel/footer_js.html');	
	echo $footerJs;
    }

    protected function cargarOpcionesReserva(stdClass $params) {

        $facility_id = isset($params->facility_id) ? (int) $params->facility_id : 0;

        if (empty($facility_id)) {
            return $this->getJSONEncode('');
        }

        $model = new facilitiesModel();

        $facility = $model->findByIdWithBookingType($facility_id, true);

        if (empty($facility)) {
            return $this->getJSONEncode('');
        }

        $this->template->assign('facility', $facility);

        if ($facility->booking_type_code === 'SPECIFIC_HOUR') {

            $minimum_booking_minutes = (int) $facility->minimum_booking_minutes;
            $maximum_booking_minutes = 240;

            $durations = [];

            if ($minimum_booking_minutes > 0) {

                for ($minutes = $minimum_booking_minutes; $minutes <= $maximum_booking_minutes; $minutes += $minimum_booking_minutes) {
                    $durations[] = [
                        'minutes' => $minutes,
                        'label' => formatReservationDuration($minutes)
                    ];
                }
            }

            $this->template->assign('durations', $durations);

            $send = $this->template->fetch('panel/reservations/booking/hour.html');
        } else {

            $send = $this->template->fetch('panel/reservations/booking/date.html');
        }

        return $this->getJSONEncode($send);
    }

    protected function addQuickClient(stdClass $params) {

        $usersService = new usersService();

        /*
         * Creamos el cliente con rol 3.
         */
        $response = $usersService->createUserFromAdmin(3, $params);

        $this->type_msg = 'INFO';

        if (!$response['success']) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = $response['message'];

            return $this->getJSONEncode(false);
        }

        $user_id = (int) $response['data']['user_id'];
        $email = $response['data']['email'];
        $name = $response['data']['name'];

        /*
         * Generamos el token para establecer la contraseña
         * y activar la cuenta.
         */
        $tokenResponse = $usersService->createSetPasswordToken($user_id);

        if (!$tokenResponse['success']) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = $tokenResponse['message'];

            return $this->getJSONEncode(false);
        }

        $token = $tokenResponse['data']['token'];

        $activationUrl = rtrim(_URL_ENVIRONMENT, '/')
                . '/establecer-password/'
                . urlencode($token);

        /*
         * Enviamos el correo.
         */
        $mailService = new mailService();

        $mailResponse = $mailService->sendSetPassword(
                $email,
                $name,
                $activationUrl
        );

        if (!$mailResponse['success']) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = $mailResponse['message'];

            return $this->getJSONEncode(false);
        }

        /*
         * Datos que necesita el JS para añadir el nuevo
         * cliente al select y dejarlo seleccionado.
         */
        $this->extra = [
            'id' => $user_id,
            'name' => $name,
            'email' => $email
        ];

        $this->msg = 'El cliente se ha creado correctamente. Se ha enviado un correo para establecer la contraseña y activar la cuenta.';

        return $this->getJSONEncode(true);
    }

    protected function searchAvailability(stdClass $params) {

        $service = new reservationsService();

        $result = $service->searchAvailability($params);

        $this->type_msg = 'INFO';

        if (!$result['success']) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = $result['message'];

            return $this->getJSONEncode(false);
        }

        $this->template->assign('availability', $result['data']);

        $html = $this->template->fetch(
                'panel/reservations/availability_result.html'
        );

        $this->extra = $html;
        $this->msg = $result['message'];

        return $this->getJSONEncode(true);
    }

    protected function prepareReservation(stdClass $params) {

        $service = new reservationsService();

        $result = $service->prepareReservation($params);

        $this->type_msg = 'INFO';

        if (!$result['success']) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = $result['message'];

            return $this->getJSONEncode(false);
        }
        
        $result['data']['duration_formatted'] = !empty($result['data']['duration_minutes'])
        ? formatReservationDuration((int) $result['data']['duration_minutes'])
        : '';

        $this->template->assign('reservation', $result['data']);
        

        $html = $this->template->fetch(
                'panel/reservations/reservation_prepare.html'
        );

        $this->extra = $html;
        $this->msg = $result['message'];

        return $this->getJSONEncode(true);
    }

    protected function confirmReservation(stdClass $params) {

        $service = new reservationsService();

        $result = $service->confirmReservation($params);

        $this->type_msg = 'INFO';

        if (!$result['success']) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = $result['message'];

            return $this->getJSONEncode(false);
        }

        $this->msg = $result['message'];
        $this->extra = $result['data'] ?? [];

        return $this->getJSONEncode(true);
    }
    
    
    protected function changeReservationPayment(stdClass $params) {

        $reservation_id = isset($params->reservation_id) ? (int) $params->reservation_id : 0;
        $payment_method_id = isset($params->payment_method_id) ? (int) $params->payment_method_id : 0;

        $service = new reservationsService();

        $result = $service->changeReservationPayment(
                $reservation_id,
                $payment_method_id
        );

        $this->type_msg = 'INFO';

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }

    protected function changeReservationStatus(stdClass $params) {

        $reservation_id = isset($params->reservation_id) ? (int) $params->reservation_id : 0;
        $status_id = isset($params->status_id) ? (int) $params->status_id : 0;
        $cancellation_reason = isset($params->cancellation_reason) ? trim(strip_tags((string) $params->cancellation_reason)) : null;

        $service = new reservationsService();

        $result = $service->changeReservationStatus(
                $reservation_id,
                $status_id,
                $cancellation_reason
        );

        $this->type_msg = 'INFO';

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }

    protected function editReservation(stdClass $params) {

        $reservation_id = isset($params->reservation_id) ? (int) $params->reservation_id : 0;
        $notes = isset($params->notes) ? trim(strip_tags((string) $params->notes)) : null;

        $service = new reservationsService();

        $result = $service->updateReservationNotes($reservation_id, $notes);

        $this->type_msg = 'INFO';

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }

    protected function addReservationGuest(stdClass $params) {

        $reservation_id = isset($params->reservation_id) ? (int) $params->reservation_id : 0;
        $full_name = trim(strip_tags((string) ($params->full_name ?? '')));
        $document_type_id = isset($params->document_type_id) && $params->document_type_id !== '' ? (int) $params->document_type_id : null;
        $document_number = trim(strip_tags((string) ($params->document_number ?? '')));
        $is_holder = isset($params->is_holder) ? (int) $params->is_holder : 0;

        $service = new reservationsService();

        $result = $service->addReservationGuest(
                $reservation_id,
                $full_name,
                $document_type_id,
                $document_number,
                $is_holder
        );

        $this->type_msg = 'INFO';

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }

    protected function editReservationGuest(stdClass $params) {

        $reservation_id = isset($params->reservation_id) ? (int) $params->reservation_id : 0;
        $guest_id = isset($params->guest_id) ? (int) $params->guest_id : 0;
        $full_name = trim(strip_tags((string) ($params->full_name ?? '')));
        $document_type_id = isset($params->document_type_id) && $params->document_type_id !== '' ? (int) $params->document_type_id : null;
        $document_number = trim(strip_tags((string) ($params->document_number ?? '')));
        $is_holder = isset($params->is_holder) ? (int) $params->is_holder : 0;

        $service = new reservationsService();

        $result = $service->editReservationGuest($reservation_id, $guest_id, $full_name, $document_type_id, $document_number, $is_holder);

        $this->type_msg = 'INFO';

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }
    
    protected function deleteReservationGuest(stdClass $params) {

        $reservation_id = isset($params->reservation_id) ? (int) $params->reservation_id : 0;
        $guest_id = isset($params->guest_id) ? (int) $params->guest_id : 0;

        $service = new reservationsService();

        $result = $service->deleteReservationGuest($reservation_id, $guest_id);

        $this->type_msg = 'INFO';

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }

    protected function loadCalendarReservations(stdClass $params) {

        $start = trim((string) ($params->start ?? ''));
        $end = trim((string) ($params->end ?? ''));
        $facility_id = isset($params->facility_id) && $params->facility_id !== '' ? (int) $params->facility_id : 0;

        $repository = new reservationsRepository();

        $reservations = $repository->findCalendarReservations(
                $start,
                $end,
                $facility_id
        );

        $events = [];

        foreach ($reservations as $reservation) {

            $events[] = [
                'id' => (string) $reservation->id,
                'title' => $reservation->user_name,
                'start' => $reservation->start_at,
                'end' => $reservation->end_at,
                'extendedProps' => [
                    'reservation_id' => (int) $reservation->id,
                    'reference' => $reservation->reference,
                    'facility_id' => (int) $reservation->facility_id,
                    'facility_name' => $reservation->facility_name,
                    'user_id' => (int) $reservation->user_id,
                    'user_name' => $reservation->user_name,
                    'status_name' => $reservation->status_name
                ]
            ];
        }

        $this->extra = $events;

        return $this->getJSONEncode(true);
    }
}
