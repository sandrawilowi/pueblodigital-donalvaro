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
final class clientController extends controller{

    
    /**
     * Construct
     */
    public function __construct() {

        parent::__construct();
        $this->logs = new logsModel('client','client.log');
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
        $this->template_header->assign('urlCssCntrlClient',$extra._CSS.'panel/client.css'._ASSET_VERSION);
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
            'buyBonus' => ['method' => 'buyBonus', 'params' => true, 'files' => false, 'roles' => ['client']],
            'searchAvailability' => ['method' => 'searchAvailability', 'params' => true, 'files' => false, 'roles' => ['client']],
            'prepareReservation' => ['method' => 'prepareReservation', 'params' => true, 'files' => false, 'roles' => ['client']],
            'confirmReservation' => ['method' => 'confirmReservation', 'params' => true, 'files' => false, 'roles' => ['client']],
            'filterReservasCliente' => ['method' => 'filterReservasCliente', 'params' => true, 'files' => false, 'roles' => ['client']],
            'addReservationGuest' => ['method' => 'addReservationGuest', 'params' => true, 'files' => false],
            'editReservationGuest' => ['method' => 'editReservationGuest', 'params' => true, 'files' => false],
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
            'instalaciones-cliente' => ['method' => 'cargarInstalaciones', 'prefix' => 'cliente'],
            'reservas-cliente' => ['method' => 'cargarReservas', 'prefix' => 'cliente'],
            'ver-instalacion' => ['method' => 'cargarVerInstalacion', 'prefix' => 'cliente'],
            'bonos-cliente' => ['method' => 'cargarBonos', 'prefix' => 'cliente'],
            'cliente-nueva-reserva' => ['method' => 'cargarNuevaReserva', 'prefix' => 'cliente'],
            'ver-reserva-cliente' => ['method' => 'cargarVerReservaCliente', 'prefix' => 'cliente']

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

    
    private function cargarInstalaciones(&$plantilla_html) {

        $facilities_model = new facilitiesModel();

        $facilities = $facilities_model->findAllBookingEnabled(true);

        $plantilla_html->assign('title', $this->chargeTitleHeader('Instalaciones'));
        $plantilla_html->assign('facilities', $facilities);
    }

    private function cargarReservas(&$plantilla_html) {

        $repository = new reservationsRepository();
        $reservation_statuses_model = new reservationStatusesModel();
        $facilities_model = new facilitiesModel();
        $payment_methods_model = new paymentMethodsModel();

        /*
         * Solo reservas del usuario logueado.
         */
        $reservations = $repository->searchReservations([
            'user_id' => $this->user_id
                ], true);
        /*
         * Datos para filtros.
         */
        $reservation_statuses = $reservation_statuses_model->findAllActive(true);
        $facilities = $facilities_model->findAllBookingEnabled(true);
        $payment_methods = $payment_methods_model->findAllActive(true);

        $plantilla_html->assign('title', $this->chargeTitleHeader('Mis reservas'));
        $plantilla_html->assign('results', $reservations);
        $plantilla_html->assign('reservation_statuses', $reservation_statuses);
        $plantilla_html->assign('facilities', $facilities);
        $plantilla_html->assign('payment_methods', $payment_methods);
    }
    
    private function cargarVerReservaCliente(&$plantilla_html) {

        $reservation_id = (int) $this->model_id;

        if ($reservation_id <= 0) {
            throw new RuntimeException(
                            'La reserva no es válida.'
                    );
        }

        $repository = new reservationsRepository();

        $result = $repository->getReservationEditData(
                $reservation_id
        );

        if (empty($result)) {
            throw new RuntimeException(
                            'La reserva no existe.'
                    );
        }

        /*
         * Comprobamos que la reserva pertenece
         * al usuario que ha iniciado sesión.
         */
        if ((int) $result->user_id !== (int) $this->user_id) {
            throw new RuntimeException(
                            'No tiene permisos para acceder a esta reserva.'
                    );
        }

        /*
         * Tipos de documento para los datos
         * de huéspedes, cuando sean necesarios.
         */
        $document_types = [];

        if ((int) $result->requires_guest_information === 1) {

            $document_types_model = new documentTypesModel();

            $document_types = $document_types_model->findAllActive(
                    true
            );
        }

        $plantilla_html->assign('model', $result);
        $plantilla_html->assign('document_types', $document_types);
        
        $can_edit_guests = true;

        $locked_statuses = [
            'cancelled',
            'rejected',
            'no_show',
            'completed'
        ];

        if (in_array($result->status_code, $locked_statuses, true)) {
            $can_edit_guests = false;
        }

        if (!empty($result->end_at) && strtotime($result->end_at) < time()) {
            $can_edit_guests = false;
        }

        $plantilla_html->assign('can_edit_guests', $can_edit_guests);

        $plantilla_html->assign(
                'title',
                $this->chargeTitleHeader(
                        'Reserva: ' . $result->reference
                )
        );
    }

    private function cargarVerInstalacion(&$plantilla_html) {

        $facility_id = (int) $this->model_id;

        if ($facility_id <= 0) {
            $this->chargeNotAccessPage();
            return;
        }

        $facilities_repository = new facilitiesRepository();

        try {

            $facility_data = $facilities_repository->getFacilityEditData($facility_id);

            $facility = $facility_data['facility'];

            /*
             * Solo permitimos instalaciones activas
             * y con reservas habilitadas.
             */
            if (
                    (int) $facility->status !== facilitiesModel::STATUS_ACTIVE ||
                    (int) $facility->booking_enabled !== 1 ||
                    !empty($facility->deleted_at)
            ) {
                $this->chargeNotAccessPage();
                return;
            }
            
            /*
             * Métodos de pago disponibles para cada bono.
             */
            $bonuses = $facility_data['bonuses'];
            $bonus_payment_methods_model = new bonusesPaymentMethodsModel();
            $bonus_facilities_model = new bonusesFacilitiesModel();

            foreach ($bonuses as $bonus) {

                $bonus->payment_methods = $bonus_payment_methods_model->findActiveByBonusId(
                        (int) $bonus->id,
                        true
                );

                $bonus->facilities = $bonus_facilities_model->findFacilitiesByBonusId(
                        (int) $bonus->id,
                        true
                );
            }

            $plantilla_html->assign('title', $this->chargeTitleHeader('Información'));
            $plantilla_html->assign('facility', $facility);
            $plantilla_html->assign('images', $facility_data['images']);
            $plantilla_html->assign('services', $facility_data['services']);
            $plantilla_html->assign('bonuses', $bonuses);
            $plantilla_html->assign('prices', $facility_data['prices']);
            $plantilla_html->assign('extraUrl', '../');
            $plantilla_html->assign('bonuses_enabled', defined('_BONUSES_ENABLED') && _BONUSES_ENABLED);
        } catch (Throwable $e) {

            $this->chargeNotAccessPage();
        }
    }

    private function cargarBonos(&$plantilla_html) {

        if (!defined('_BONUSES_ENABLED') || !_BONUSES_ENABLED) {
            $this->chargeNotAccessPage();
            return;
        }

        $users_bonuses_model = new usersBonusesModel();
        $bonuses_model = new bonusesModel();
        $bonus_facilities_model = new bonusesFacilitiesModel();
        $bonus_payment_methods_model = new bonusesPaymentMethodsModel();

        /*
         * Bonos adquiridos por el usuario.
         */
        $bonuses = $users_bonuses_model->findByUserId(
                $this->user_id,
                true
        );

        foreach ($bonuses as $bonus) {

            $bonus->facilities = $bonus_facilities_model->findFacilitiesByBonusId(
                    (int) $bonus->bonus_id,
                    true
            );
        }

        /*
         * Bonos disponibles para comprar.
         */
        $available_bonuses = $bonuses_model->findAllActive(true);

        foreach ($available_bonuses as $bonus) {

            $bonus->facilities = $bonus_facilities_model->findFacilitiesByBonusId(
                    (int) $bonus->id,
                    true
            );

            $bonus->payment_methods = $bonus_payment_methods_model->findActiveByBonusId(
                    (int) $bonus->id,
                    true
            );
        }

        $plantilla_html->assign('title', $this->chargeTitleHeader('Bonos'));
        $plantilla_html->assign('bonuses', $bonuses);
        $plantilla_html->assign('available_bonuses', $available_bonuses);
    }

    /**
     * Print the js libraries
     * @param type $extra
     */
    protected function printFooterJs(string $extra='') {

        parent::printFooterJs($extra);
        
        $this->template_footer->assign('urlJsCntrl', $extra . _JS . 'panel/client.js'._ASSET_VERSION);
        
        $footerJs = $this->template_footer->fetch('common/panel/footer_js.html');	
	echo $footerJs;
    }
    
    protected function buyBonus(stdClass $params) {

        $users_service = new usersService();

        $result = $users_service->buyBonus(
                $this->user_id,
                $params
        );

        return $this->getJSONEncode($result);
    }

    private function cargarNuevaReserva(&$plantilla_html) {

        $facility_id = (int) $this->model_id;

        if ($facility_id <= 0) {
            $this->chargeNotAccessPage();
            return;
        }

        $model = new facilitiesModel();

        $facility = $model->findByIdWithBookingType($facility_id, true);

        if (
                empty($facility) ||
                (int) $facility->status !== facilitiesModel::STATUS_ACTIVE ||
                (int) $facility->booking_enabled !== 1
        ) {
            $this->chargeNotAccessPage();
            return;
        }

        $durations = [];

        if ($facility->booking_type_code === 'SPECIFIC_HOUR') {

            $minimum_booking_minutes = (int) $facility->minimum_booking_minutes;
            $maximum_booking_minutes = 240;

            if ($minimum_booking_minutes > 0) {

                for ($minutes = $minimum_booking_minutes; $minutes <= $maximum_booking_minutes; $minutes += $minimum_booking_minutes) {
                    $durations[] = [
                        'minutes' => $minutes,
                        'label' => formatReservationDuration($minutes)
                    ];
                }
            }
        }

        $plantilla_html->assign('facility', $facility);
        $plantilla_html->assign('durations', $durations);
        $plantilla_html->assign('title', $this->chargeTitleHeader('Nueva Reserva'));
    }

    protected function searchAvailability(stdClass $params) {

        $params->user_id = (int) $this->user_id;

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

        $this->template->assign(
                'continue_reservation_function',
                'continueClientReservation'
        );

        $html = $this->template->fetch(
                'panel/reservations/availability_result.html'
        );

        $this->extra = $html;
        $this->msg = $result['message'];

        return $this->getJSONEncode(true);
    }

    protected function prepareReservation(stdClass $params) {

        $params->user_id = (int) $this->user_id;

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
        $this->template->assign('client_reservation', true);
        $this->template->assign(
                'confirm_reservation_function',
                'confirmClientReservation'
        );

        $html = $this->template->fetch(
                'panel/reservations/reservation_prepare.html'
        );

        $this->extra = $html;
        $this->msg = $result['message'];

        return $this->getJSONEncode(true);
    }

    protected function confirmReservation(stdClass $params) {

        $params->user_id = (int) $this->user_id;

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
    
    protected function filterReservasCliente(stdClass $params) {

        $reference = trim(strip_tags((string) ($params->referenceFilter ?? '')));
        $facility_id = isset($params->facilityFilter) && $params->facilityFilter !== '' ? (int) $params->facilityFilter : null;
        $status_id = isset($params->statusFilter) && $params->statusFilter !== '' ? (int) $params->statusFilter : null;
        $date_from = trim((string) ($params->dateFromFilter ?? ''));
        $date_until = trim((string) ($params->dateUntilFilter ?? ''));
        $payment_method = trim((string) ($params->paymentMethodFilter ?? ''));

        $repository = new reservationsRepository();

        /*
         * El usuario SIEMPRE es el usuario logueado.
         * No aceptamos ningún user_id recibido desde el navegador.
         */
        $reservations = $repository->searchReservations([
            'reference' => $reference,
            'user_id' => $this->user_id,
            'facility_id' => $facility_id,
            'status_id' => $status_id,
            'date_from' => $date_from,
            'date_until' => $date_until,
            'payment_method' => $payment_method
                ], true);

        $this->template->assign('results', $reservations);

        $send = $this->template->fetch(
                'panel/client/tables/tableReservationsClient.html'
        );

        return $this->getJSONEncode($send);
    }
    
    protected function addReservationGuest(stdClass $params) {

        $reservation_id = isset($params->reservation_id) ? (int) $params->reservation_id : 0;
        $full_name = trim(strip_tags((string) ($params->full_name ?? '')));
        $document_type_id = isset($params->document_type_id) && $params->document_type_id !== '' ? (int) $params->document_type_id : null;
        $document_number = trim(strip_tags((string) ($params->document_number ?? '')));
        $is_holder = isset($params->is_holder) ? (int) $params->is_holder : 0;

        /*
         * Comprobamos que la reserva pertenece
         * al usuario logueado.
         */
        $reservations_model = new reservationsModel();
        $reservation = $reservations_model->findById($reservation_id, false);

        if (empty($reservation) || (int) $reservation['user_id'] !== (int) $this->user_id) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = 'No tiene permisos para modificar esta reserva.';

            return $this->getJSONEncode(false);
        }

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

        /*
         * Comprobamos que la reserva pertenece
         * al usuario logueado.
         */
        $reservations_model = new reservationsModel();
        $reservation = $reservations_model->findById($reservation_id, false);

        if (empty($reservation) || (int) $reservation['user_id'] !== (int) $this->user_id) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = 'No tiene permisos para modificar esta reserva.';

            return $this->getJSONEncode(false);
        }

        $service = new reservationsService();

        $result = $service->editReservationGuest(
                $reservation_id,
                $guest_id,
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
}
