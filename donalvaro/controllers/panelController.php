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
final class panelController extends controller{

    
    /**
     * Construct
     */
    public function __construct() {

        parent::__construct();
        $this->logs = new logsModel('panel','panel.log');
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
            'generatePasswordUser' => ['method' => 'generatePasswordUser', 'params' => false, 'files' => false, 'auth' => false],
            'formFilter' => ['method' => 'formFilter', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'confirmPendingReservation' => ['method' => 'confirmPendingReservation', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'cargarDatosAuditoria' => ['method' => 'cargarDatosAuditoria', 'params' => true, 'files' => false, 'roles' => ['system']]

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
            'dashboard' => ['method' => 'chargeDashboard'],
            'perfil' => ['method' => 'chargePerfil'],
            'historial-accesos' => ['method' => 'cargarHistorial', 'prefix' => 'historical'],
            'viewhistorial' => ['method' => 'cargarViewHistorial', 'prefix' => 'historical',
                'breadcrumb' => ['historial-accesos' => 'Historial', 'title' => 'Ver Historial']],
            'auditoria' => ['method' => 'chargeAudit'],
            'documentacion' => ['method' => 'chargeDocumentacion', 'prefix' => 'documentation']

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
        
    private function filterCerraduras($params){
        
        $nombre = trim(strip_tags((string) ($params->nameFilter ?? '')));
        $estado = trim((string) ($params->statusFilter ?? ''));
        
        $repository = new ttlockDevicesRepository();

        $devices = $repository->searchLocks([
            'name' => $nombre,
            'status' => $estado,
        ]);

        $this->template->assign('results', $devices);

        $send = $this->template->fetch('panel/devices/tables/tableCerraduras.html');

        return $this->getJSONEncode($send);
        
    }
    
    private function filterGateway($params){
        
        $nombre = trim(strip_tags((string) ($params->nameFilter ?? '')));
        $estado = trim((string) ($params->statusFilter ?? ''));
        
        $repository = new ttlockDevicesRepository();

        $gateways = $repository->searchGateways([
            'name' => $nombre,
            'status' => $estado,
        ]);

        $this->template->assign('results', $gateways);

        $send = $this->template->fetch('panel/devices/tables/tableGateways.html');

        return $this->getJSONEncode($send);
    }
    
    private function chargeDashboardAdmin(newSmarty &$dashboard) {

        $repository = new dashboardRepository();
        $facilities_model = new facilitiesModel();
        $facilities_devices_model = new facilitiesDevicesModel();

        $pending_reservations = $repository->findPendingReservations(10);
        $latest_accesses = $repository->findLatestAccesses(20);

        $bonuses_enabled = defined('_BONUSES_ENABLED') && _BONUSES_ENABLED;
        $pending_bonuses = [];

        if ($bonuses_enabled) {
            $users_bonuses_model = new usersBonusesModel();
            $pending_bonuses = $users_bonuses_model->findPending(true, 5);
        }

        $facilities = $facilities_model->findAllActive(true);

        foreach ($facilities as $facility) {
            $facility->devices = $facilities_devices_model->findByFacilityId((int) $facility->id, true);
        }

        $pending_reservations_count = $repository->countPendingReservations();
        $today_reservations_count = $repository->countTodayReservations();
        $active_facilities_count = $repository->countActiveFacilities();
        $today_accesses_count = $repository->countTodayAccesses();

        $dashboard->assign('title', $this->chargeTitleHeader('Panel de administración'));
        $dashboard->assign('pending_reservations_count', $pending_reservations_count);
        $dashboard->assign('today_reservations_count', $today_reservations_count);
        $dashboard->assign('active_facilities_count', $active_facilities_count);
        $dashboard->assign('today_accesses_count', $today_accesses_count);
        $dashboard->assign('pending_reservations', $pending_reservations);
        $dashboard->assign('latest_accesses', $latest_accesses);
        $dashboard->assign('facilities', $facilities);
        $dashboard->assign('pending_bonuses', $pending_bonuses);
        $dashboard->assign('bonuses_enabled', $bonuses_enabled);
    }

    private function chargeDashboardClient(newSmarty &$dashboard) {

        $facilities_model = new facilitiesModel();
        $reservations_repository = new reservationsRepository();
        $bonuses_model = new bonusesModel();
        $bonus_facilities_model = new bonusesFacilitiesModel();
        $bonus_payment_methods_model = new bonusesPaymentMethodsModel();

        $facilities = $facilities_model->findAllBookingEnabled(true, 4);

        $reservations = $reservations_repository->searchReservations([
            'user_id' => (int) $_SESSION['user_id'],
            'date_from' => date('Y-m-d'),
            'status_id' => [
                reservationsModel::STATUS_PENDING,
                reservationsModel::STATUS_CONFIRMED
            ],
                ], true, 3);

        $bonuses = [];

        if (defined('_BONUSES_ENABLED') && _BONUSES_ENABLED) {

            $bonuses = $bonuses_model->findAllActive(true,4);

            foreach ($bonuses as $bonus) {

                $bonus->facilities = $bonus_facilities_model->findFacilitiesByBonusId(
                        (int) $bonus->id,
                        true
                );

                $bonus->payment_methods = $bonus_payment_methods_model->findActiveByBonusId(
                        (int) $bonus->id,
                        true
                );
            }
        }

        $dashboard->assign('title', $this->chargeTitleHeader('Bienvenido'));
        $dashboard->assign('facilities', $facilities);
        $dashboard->assign('reservations', $reservations);
        $dashboard->assign('bonuses', $bonuses);
        $dashboard->assign('bonuses_enabled', defined('_BONUSES_ENABLED') && _BONUSES_ENABLED);
    }

    private function filterInstalaciones(stdClass $params){
        
        $nombre = trim(strip_tags((string) ($params->nameFilter ?? '')));
        $localidad = trim(strip_tags((string) ($params->cityFilter ?? '')));
        $estado = trim((string) ($params->statusFilter ?? ''));
        $provincias = [];

        if (isset($params->provinceFilter) && is_array($params->provinceFilter)) {
            $provincias = array_map('intval',$params->provinceFilter);
        }

        $repository = new facilitiesRepository();

        $facilities = $repository->searchFacilities([
            'name' => $nombre,
            'city' => $localidad,
            'status' => $estado,
            'provinces' => $provincias
        ]);

        $this->template->assign('results', $facilities);
        $send = $this->template->fetch('panel/facilities/tables/tableInstalaciones.html');

        return $this->getJSONEncode($send);
    }
    
    private function filterClientes(stdClass $params) {

        $nombre = trim(strip_tags((string) ($params->nameFilter ?? '')));
        $apellidos = trim(strip_tags((string) ($params->lastNameFilter ?? '')));
        $email = trim(strip_tags((string) ($params->emailFilter ?? '')));
        $estado = trim((string) ($params->statusFilter ?? ''));

        $repository = new usersRepository();

        $usuarios = $repository->searchUsers([
            'name' => $nombre,
            'last_name' => $apellidos,
            'email' => $email,
            'status' => $estado
                ], 3);

        $this->template->assign('results', $usuarios);
        $send = $this->template->fetch('panel/users/tables/tableUsuarios.html');

        return $this->getJSONEncode($send);
    }
    
    private function filterUsuarios(stdClass $params) {

        $nombre = trim(strip_tags((string) ($params->nameFilter ?? '')));
        $apellidos = trim(strip_tags((string) ($params->lastNameFilter ?? '')));
        $email = trim(strip_tags((string) ($params->emailFilter ?? '')));
        $estado = trim((string) ($params->statusFilter ?? ''));

        $repository = new usersRepository();

        $usuarios = $repository->searchUsers([
            'name' => $nombre,
            'last_name' => $apellidos,
            'email' => $email,
            'status' => $estado
                ], 2);
        
        $this->template->assign('results', $usuarios);
        $send = $this->template->fetch('panel/users/tables/tableUsuarios.html');

        return $this->getJSONEncode($send);
    }
    
    private function filterReservas(stdClass $params) {

        $reference = trim(strip_tags((string) ($params->referenceFilter ?? '')));
        $client_id = isset($params->clientFilter) && $params->clientFilter !== '' ? (int) $params->clientFilter : null;
        $facility_id = isset($params->facilityFilter) && $params->facilityFilter !== '' ? (int) $params->facilityFilter : null;
        $status_id = isset($params->statusFilter) && $params->statusFilter !== '' ? (int) $params->statusFilter : null;
        $date_from = trim((string) ($params->dateFromFilter ?? ''));
        $date_until = trim((string) ($params->dateUntilFilter ?? ''));
        $payment_method = trim((string) ($params->paymentMethodFilter ?? ''));

        $reservation_statuses_model = new reservationStatusesModel();
        $repository = new reservationsRepository();

        $reservations = $repository->searchReservations([
            'reference' => $reference,
            'user_id' => $client_id,
            'facility_id' => $facility_id,
            'status_id' => $status_id,
            'date_from' => $date_from,
            'date_until' => $date_until,
            'payment_method' => $payment_method
                ], true);

        $facility_payment_methods_model = new facilitiesPaymentMethodsModel();

        $payment_methods_by_facility = [];

        foreach ($reservations as $reservation) {

            $facility_id = (int) $reservation->facility_id;

            if (!isset($payment_methods_by_facility[$facility_id])) {
                $payment_methods_by_facility[$facility_id] = $facility_payment_methods_model->findActiveByFacilityId($facility_id, true);
            }
        }

        $this->template->assign('payment_methods_by_facility', $payment_methods_by_facility);

        $result_res_status = $reservation_statuses_model->findAllActive(true);
        $this->template->assign('reservation_statuses', $result_res_status);

        $this->template->assign('results', $reservations);

        $send = $this->template->fetch(
                'panel/reservations/tables/tableReservations.html'
        );

        return $this->getJSONEncode($send);
    }    
    

    private function filterPins(stdClass $params) {

        $pin = trim(strip_tags((string) ($params->pinFilter ?? '')));
        $facility_id = isset($params->facilityFilter) && $params->facilityFilter !== '' ? (int) $params->facilityFilter : 0;
        $status_id = isset($params->statusFilter) && $params->statusFilter !== '' ? (int) $params->statusFilter : 0;

        $repository = new accessPinsRepository();

        $pins = $repository->searchPins([
            'pin' => $pin,
            'facility_id' => $facility_id,
            'status_id' => $status_id
        ]);

        $this->template->assign('results', $pins);

        $send = $this->template->fetch('panel/codigos/tables/tablePins.html');

        return $this->getJSONEncode($send);
    }
    
    private function filterBonos(stdClass $params) {

        $name = trim(strip_tags((string) ($params->nameFilter ?? '')));
        $facility_id = isset($params->facilityFilter) && $params->facilityFilter !== '' ? (int) $params->facilityFilter : 0;
        $status_id = isset($params->statusFilter) && $params->statusFilter !== '' ? (int) $params->statusFilter : 0;

        $repository = new bonusRepository();

        $pins = $repository->searchBonuses([
            'name' => $name,
            'facility_id' => $facility_id,
            'status_id' => $status_id
        ]);

        $this->template->assign('results', $pins);

        $send = $this->template->fetch('panel/bonos/tables/tableBono.html');

        return $this->getJSONEncode($send);
    }
    
    private function filterHistorial(stdClass $params) {

        $date_from = trim((string) ($params->dateFromFilter ?? ''));
        $date_to = trim((string) ($params->dateToFilter ?? ''));
        $facility_id = isset($params->facilityFilter) && $params->facilityFilter !== '' ? (int) $params->facilityFilter : 0;
        $result = trim((string) ($params->resultFilter ?? ''));

        $repository = new ttlockAccessLogsRepository();

        $historical = $repository->searchHistorial([
            'date_from' => $date_from,
            'date_to' => $date_to,
            'facility_id' => $facility_id,
            'access_granted' => $result
                ], true);

        $this->template->assign('results', $historical);

        $send = $this->template->fetch('panel/historical/tables/tableHistorial.html');

        return $this->getJSONEncode($send);
    }

    private function cargarHistorial(&$plantilla_html) {

        $access_logs_repository = new ttlockAccessLogsRepository();
        $facilities_model = new facilitiesModel();

        /*
         * Historial de accesos.
         */
        $results = $access_logs_repository->findAllWithDetails(true);

        /*
         * Instalaciones disponibles para el filtro.
         */
        $facilities = $facilities_model->findAll(true);

        $plantilla_html->assign('results', $results);
        $plantilla_html->assign('facilities', $facilities);
        $plantilla_html->assign('title', $this->chargeTitleHeader('Historial de Accesos'));
    }
    
    private function cargarViewHistorial(&$plantilla_html) {

        $repository = new ttlockAccessLogsRepository();

        $result = $repository->getAccessLogViewData((int) $this->model_id);

        if (empty($result)) {
            throw new RuntimeException('El registro de acceso no existe.');
        }

        $plantilla_html->assign('model', $result);
        $plantilla_html->assign('title', $this->chargeTitleHeader('Registro: ' . $result->id));
    }
    
    private function chargePerfil(&$plantilla_html) {

        $users_model = new usersModel();
        $countries_model = new countriesModel();
        $provinces_model = new provincesModel();

        /*
         * Cargamos los datos del usuario autenticado.
         */
        $model = $users_model->findEditData($this->user_id, true);

        if (empty($model)) {
            throw new RuntimeException('No se ha encontrado el usuario.');
        }

        /*
         * Países disponibles.
         */
        $countries = $countries_model->findAllActive(true);

        /*
         * Provincias del país seleccionado.
         */
        $provinces = [];

        if (!empty($model->country_id)) {
            $provinces = $provinces_model->findByCountryId((int) $model->country_id, true);
        }

        $plantilla_html->assign('title', $this->chargeTitleHeader('Mi perfil'));
        $plantilla_html->assign('model', $model);
        $plantilla_html->assign('countries', $countries);
        $plantilla_html->assign('provinces', $provinces);
    }
    
    private function chargeDocumentacion(&$plantilla_html) {

        $template = '';

        switch ($this->role_user) {

            case 2:
                $template = 'panel/documentation/manager.html';
                break;

            case 3:
                $template = 'panel/documentation/client.html';
                break;

            default:
                $template = 'panel/documentation/manager.html';
                break;
        }
        
        $doc =  new newSmarty();

        $documentation = $doc->fetch($template);

        $plantilla_html->assign('documentation', $documentation);
        $plantilla_html->assign('title', $this->chargeTitleHeader('Documentación'));
    }
    
    private function chargeAudit(&$plantilla_html){
        
        $plantilla_html->assign('bonuses_enabled', defined('_BONUSES_ENABLED') && _BONUSES_ENABLED);
        $plantilla_html->assign('title', $this->chargeTitleHeader('Auditoría'));
    }

    /**
     * Print the js libraries
     * @param type $extra
     */
    protected function printFooterJs(string $extra='') {

        parent::printFooterJs($extra);
        
        $this->template_footer->assign('urlJsCntrl', $extra . _JS . 'panel/panel.js'._ASSET_VERSION);
        
        $footerJs = $this->template_footer->fetch('common/panel/footer_js.html');	
	echo $footerJs;
    }


    protected function generatePasswordUser() {

        $passwordService = new passwordService();
        $password = $passwordService->generatePassword(8);

        return $this->getJSONEncode($password);
    }
    

    protected function chargeDashboard(newSmarty &$plantilla_html) {

        $dashboard = new newSmarty();
        $route = 'dashboard.html';
        $user = new usersModel();
        $dataUser = $user->findById($this->user_id);
        $dashboard->assign('user', $dataUser);

        if ($this->role_user == 1) {

            $route = 'admin/dashboard.html';
            $this->chargeDashboardAdmin($dashboard);
        } else if($this->role_user == 2){
            $route = 'admin/dashboard.html';
            $this->chargeDashboardAdmin($dashboard);
        } else if($this->role_user == 3){
            $route = 'admin/dashboard_client.html';
            $this->chargeDashboardClient($dashboard);
        }

        $dashboard->assign('user_id', $this->user_id);

        $result = $dashboard->fetch('panel/' . $route);

        $plantilla_html->assign('dashboard', $result);
        $plantilla_html->assign('role_id', $this->role_user);
    }

    protected function formFilter($params) {

        $form_id = filter_var(strip_tags($params->form_id));
        $response = '';

        switch ($form_id) {
            case 'filterCerraduras':
                $response = $this->filterCerraduras($params);
                break;

            case 'filterGateway':
                $response = $this->filterGateway($params);
                break;
            
            case 'filterInstalaciones':
                $response = $this->filterInstalaciones($params);
                break;
            
            case 'filterUsuarios':
                $response = $this->filterUsuarios($params);
                break;

            case 'filterClientes':
                $response = $this->filterClientes($params);
                break;
            
            case 'filterReservas':
                $response = $this->filterReservas($params);
                break;
            
            case 'filterPins':
                $response = $this->filterPins($params);
                break;
            
            case 'filterHistorial':
                $response = $this->filterHistorial($params);
                break;
            
            case 'filterBonos':
                $response = $this->filterBonos($params);
                break;

            default:
                $response = $this->getJSONEncode('');
                break;
        }

        return $response;
    }

    protected function confirmPendingReservation(stdClass $params) {

        $reservation_id = isset($params->reservation_id) ? (int) $params->reservation_id : 0;

        $reservations_model = new reservationsModel();
        $reservation_statuses_model = new reservationStatusesModel();
        $reservations_service = new reservationsService();

        if ($reservation_id <= 0) {

            $result = serviceResponse::error(
                    'La reserva seleccionada no es válida.',
                    'RESERVATION_ID_INVALID'
            );
        } else {

            $reservation = $reservations_model->findById(
                    $reservation_id,
                    false
            );

            if (empty($reservation)) {

                $result = serviceResponse::error(
                        'La reserva no existe.',
                        'RESERVATION_NOT_FOUND'
                );
            } else {

                $current_status = $reservation_statuses_model->findById(
                        (int) $reservation['reservation_status_id'],
                        false
                );

                if (empty($current_status) || $current_status['code'] !== 'pending') {

                    $result = serviceResponse::error(
                            'La reserva ya no se encuentra pendiente de confirmación.',
                            'RESERVATION_NOT_PENDING'
                    );
                } else {

                    $confirmed_status = $reservation_statuses_model->findByCode(
                            'confirmed',
                            false
                    );

                    if (empty($confirmed_status)) {

                        $result = serviceResponse::error(
                                'No se ha podido determinar el estado de confirmación.',
                                'CONFIRMED_STATUS_NOT_FOUND'
                        );
                    } else {

                        $result = $reservations_service->changeReservationStatus(
                                $reservation_id,
                                (int) $confirmed_status['id']
                        );
                    }
                }
            }
        }

        $this->type_msg = 'INFO';

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }
    
    protected function cargarDatosAuditoria(stdClass $params) {

        $audit_type = strtoupper(trim((string) ($params->audit_type ?? '')));

        $audit = new auditLogsRepository();

        switch ($audit_type) {

            case 'FACILITY':
                $results = $audit->getFacilitiesAudit();
                break;

            case 'LOCK':
                $results = $audit->getDevicesAudit();
                break;

            case 'GATEWAY':
                $results = $audit->getGatewaysAudit();
                break;

            case 'PIN':
                $results = $audit->getPinsAudit();
                break;

            case 'RESERVATION':
                $results = $audit->getReservationsAudit();
                break;
            
            case 'BONUS':
                if (!defined('_BONUSES_ENABLED') || !_BONUSES_ENABLED) {

                    $this->control_request = 2;
                    $this->type_msg = 'ERROR';
                    $this->msg = 'El módulo de bonos no está habilitado.';

                    return $this->getJSONEncode(false);
                }
                $results = $audit->getBonusesAudit();
                break;

            case 'CLIENT':
                $results = $audit->getClientsAudit();
                break;

            case 'USER':
                $results = $audit->getUsersAudit();
                break;

            default:

                $this->control_request = 2;
                $this->type_msg = 'ERROR';
                $this->msg = 'El tipo de auditoría seleccionado no es válido.';

                return $this->getJSONEncode(false);
        }

        $this->template->assign('results', $results);

        $send = $this->template->fetch(
                'panel/audit_all_template.html'
        );

        $this->extra = $send;

        return $this->getJSONEncode(true);
    }
}
