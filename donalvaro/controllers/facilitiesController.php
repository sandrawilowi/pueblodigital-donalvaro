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
final class facilitiesController extends controller{

    
    /**
     * Construct
     */
    public function __construct() {

        parent::__construct();
        $this->logs = new logsModel('facilities','facility.log');
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
            'addFacility' => ['method' => 'addFacility', 'params' => true, 'files' => true, 'roles' => ['system', 'manager']],
            'activeFacility' => ['method' => 'activeFacility', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'deleteFacility' => ['method' => 'deleteFacility', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'actualizarInstalacion' => ['method' => 'actualizarInstalacion', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'addFacilityPrice' => ['method' => 'addFacilityPrice', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'addFacilityDevice' => ['method' => 'addFacilityDevice', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'addFacilityService' => ['method' => 'addFacilityService', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'addFacilityPaymentMethod' => ['method' => 'addFacilityPaymentMethod', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'editFacilityPrice' => ['method' => 'editFacilityPrice', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'deleteFacilityPrice' => ['method' => 'deleteFacilityPrice', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'activeFacilityPrice' => ['method' => 'activeFacilityPrice', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'saveFacilityImages' => ['method' => 'saveFacilityImages', 'params' => true, 'files' => true, 'roles' => ['system', 'manager']],
            'removeFacilityDevice' => ['method' => 'removeFacilityDevice', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'removeFacilityService' => ['method' => 'removeFacilityService', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'removeFacilityPaymentMethod' => ['method' => 'removeFacilityPaymentMethod', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'addAvailability' => ['method' => 'addAvailability', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'getAvailabilityCalendar' => ['method' => 'getAvailabilityCalendar', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'getAvailabilityReservations' => ['method' => 'getAvailabilityReservations', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'deleteAvailability' => ['method' => 'deleteAvailability', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']]

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
            'instalaciones' => ['method' => 'cargarInstalaciones', 'prefix' => 'facilities'],
            'editinstalacion' => ['method' => 'editInstalacion', 'prefix' => 'facilities',
                'breadcrumb' => ['instalaciones' => 'Instalaciones', 'title' => 'Editar Instalación']],
            'auditinstalacion' => ['method' => 'auditInstalacion', 'prefix' => 'facilities',
                'breadcrumb' => ['instalaciones' => 'Instalaciones', 'editinstalacion/'.$this->model_id => 'Editar Instalación', 'title' => 'Actividad']],
            'disponibilidad-instalacion' => ['method' => 'chargeDisponibilidad', 'prefix' => 'facilities',
                'breadcrumb' => ['instalaciones' => 'Instalaciones', 'editinstalacion/'.$this->model_id => 'Editar Instalación', 'title' => 'Disponibilidad']]

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
    
    private function cargarInstalaciones(&$plantilla_html){

        $instalaciones = new facilitiesModel();
        $provinces_model = new provincesModel();
        
        $results = $instalaciones->findAllNotDeleted(true);
        
        $provinces = $provinces_model->findByCountryId(209, true);

        $booking_types_model = new bookingTypesModel();
        $booking_types = $booking_types_model->findAllActive(true);

        $plantilla_html->assign('booking_types', $booking_types);

        $plantilla_html->assign('title',$this->chargeTitleHeader('Instalaciones',true));
        $plantilla_html->assign('results', $results);
        $plantilla_html->assign('provinces', $provinces);

    }
    
    private function editInstalacion(&$plantilla_html){
        
        $repository = new facilitiesRepository();
        $provinces_model = new provincesModel();
        $billing_unit_types_model = new billingUnitTypesModel();
        $billing_periods_model = new billingPeriodsModel();
        $result_provinces = $provinces_model->findByCountryId(209, true);
        $result = $repository->getFacilityEditData(intval($this->model_id));
        
        $facilities_devices_model = new facilitiesDevicesModel();
        $services_model =  new facilitiesServicesModel();
        $pagos_model = new facilitiesPaymentMethodsModel();
        $available_devices = $facilities_devices_model->findAvailableDevices(true);
        $result_services = $services_model->findAvailableByFacilityId(intval($this->model_id), true);
        $result_payment = $pagos_model->findAvailableByFacilityId(intval($this->model_id), true);
        $billing_unit_types = $billing_unit_types_model->findAllActive(true);
        $billing_periods = $billing_periods_model->findAllActive(true);
        
        $booking_types_model = new bookingTypesModel();
        $booking_types = $booking_types_model->findAllActive(true);

        $plantilla_html->assign('available_devices',$available_devices);
        $plantilla_html->assign('booking_types', $booking_types);

        $plantilla_html->assign('title', $this->chargeTitleHeader('Editar Instalación: ' . $result['facility']->name));
        $plantilla_html->assign('model', $result);
        $plantilla_html->assign('provinces', $result_provinces);
        $plantilla_html->assign('available_payment_methods', $result_payment);
        $plantilla_html->assign('available_services', $result_services);
        $plantilla_html->assign('billing_unit_types', $billing_unit_types);
        $plantilla_html->assign('billing_periods', $billing_periods);
        
    }
    
    private function auditInstalacion(&$plantilla_html){
        
        $model = new facilitiesModel();
        $facility = $model->findById(intval($this->model_id), true);

        $audit = new auditLogsRepository();
        $results = $audit->getFacilityAudit(intval($this->model_id));
        
        $plantilla_audit = new newSmarty();
        $plantilla_audit->assign('results',$results);
        $audit_tmp = $plantilla_audit->fetch('panel/audit_template.html');
        
        $plantilla_html->assign('title',$this->chargeTitleHeader('Actividad: '.$facility->name));
        $plantilla_html->assign('audit_tmp',$audit_tmp);
    }

    private function chargeDisponibilidad(&$plantilla_html) {

        $facility_id = (int) $this->model_id;

        $facilities_model = new facilitiesModel();
        $facility = $facilities_model->findByIdWithBookingType($facility_id, true);

        if (empty($facility)) {
            return;
        }

        $plantilla_html->assign('facility', $facility);
        $plantilla_html->assign('title',$this->chargeTitleHeader('Disponibilidad: '.$facility->name));
    }

    /**
     * Print the js libraries
     * @param type $extra
     */
    protected function printFooterJs(string $extra='') {

        parent::printFooterJs($extra);
        
        $this->template_footer->assign('urlJsCntrl', $extra . _JS . 'panel/facilities.js'._ASSET_VERSION);
        
        $footerJs = $this->template_footer->fetch('common/panel/footer_js.html');	
	echo $footerJs;
    }

    
    protected function addFacility(stdClass $params, array $files = array()){
        
        $service = new facilitiesService();   

        $result = $service->createFacility($params,$files);
        
        $this->msg = $result['message'];
        $this->type_msg = "INFO";
        if(!$result['success']){
            $this->control_request = 2;
            $this->type_msg = "ERROR";            
        }
        
        return $this->getJSONEncode($result['success']);
    }
    
    protected function activeFacility(stdClass $params){
        
        $facility_id = isset($params->id) ? (int) $params->id : 0;
        $status = isset($params->value) ? (int) $params->value : facilitiesModel::STATUS_INACTIVE;
        
        $service = new facilitiesService();
        $result = $service->changeFacilityStatus($facility_id, $status);
        
        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
        
    }
    
    protected function activeFacilityPrice(stdClass $params) {

        $facility_id = isset($params->id) ? (int) $params->id : 0;
        $price_id = isset($params->price_id) ? (int) $params->price_id : 0;
        $status = isset($params->value) ? (int) $params->value : facilitiesPricesModel::STATUS_INACTIVE;

        $service = new facilitiesService();
        $result = $service->changeFacilityPriceStatus($facility_id, $price_id, $status);

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }
    
    protected function deleteFacility(stdClass $params){
        
        $facility_id = isset($params->id) ? (int) $params->id : 0;
        
        $service = new facilitiesService();
        $result = $service->deleteFacility($facility_id);
        
        $this->type_msg = 'INFO';
        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
        
    }
    
    protected function actualizarInstalacion(stdClass $params){        
        
        $facility_id = isset($params->id) ? (int) $params->id : 0;
        
        $service = new facilitiesService();
        
        $result = $service->updateFacility($facility_id,$params);
        
        $this->type_msg = "INFO";
        if(!$result['success']){
            $this->control_request = 2;
            $this->type_msg = "ERROR";            
        }
        
        $this->msg = $result['message'];        
        
        return $this->getJSONEncode($result['success']);
    }
    
    protected function addFacilityPrice(stdClass $params){
        
        $facility_id = isset($params->id) ? (int) $params->id : 0;
        
        $service = new facilitiesService();
        $result = $service->addFacilityPrice($facility_id, $params);
        
        $this->type_msg = "INFO";
        if(!$result['success']){
            $this->control_request = 2;
            $this->type_msg = "ERROR";            
        }
        
        $this->msg = $result['message'];        
        
        return $this->getJSONEncode($result['success']);
        
    }
    
    protected function editFacilityPrice(stdClass $params){
        
        $facility_id = isset($params->id) ? (int) $params->id : 0;
        
        $service = new facilitiesService();
        $result = $service->editFacilityPrice($facility_id, $params);
        
        $this->type_msg = "INFO";
        if(!$result['success']){
            $this->control_request = 2;
            $this->type_msg = "ERROR";            
        }
        
        $this->msg = $result['message'];        
        
        return $this->getJSONEncode($result['success']);
        
    }
    
    protected function deleteFacilityPrice(stdClass $params){
        
        $facility_id = isset($params->id) ? (int) $params->id : 0;
        $price_id = isset($params->price_id) ? (int) $params->price_id : 0;
        
        $service = new facilitiesService();
        $result = $service->deleteFacilityPrice($facility_id, $price_id);
        
        $this->type_msg = 'INFO';
        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }
    
    protected function addFacilityDevice(stdClass $params){
        
        $facility_id = isset($params->id) ? (int) $params->id : 0;
        
        $service = new facilitiesService();
        $result = $service->addFacilityDevice($facility_id, $params);
        
        $this->type_msg = "INFO";
        if(!$result['success']){
            $this->control_request = 2;
            $this->type_msg = "ERROR";            
        }
        
        $this->msg = $result['message'];        
        
        return $this->getJSONEncode($result['success']);
    }
    
    protected function removeFacilityDevice(stdClass $params){
        
        $facility_id = isset($params->id) ? (int) $params->id : 0;
        $device_id = isset($params->device_id) ? (int) $params->device_id : 0;
        
        $service = new facilitiesService();
        $result = $service->removeFacilityDevice($facility_id, $device_id);
        
        $this->type_msg = 'INFO';
        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
        
    }
    
    protected function addFacilityService(stdClass $params){
        
        $facility_id = isset($params->id) ? (int) $params->id : 0;
        
        $service = new facilitiesService();
        $result = $service->addFacilityService($facility_id, $params);
        
        $this->type_msg = "INFO";
        if(!$result['success']){
            $this->control_request = 2;
            $this->type_msg = "ERROR";            
        }
        
        $this->msg = $result['message'];        
        
        return $this->getJSONEncode($result['success']);
    }
    
    protected function removeFacilityService(stdClass $params){
        
        $facility_id = isset($params->id) ? (int) $params->id : 0;
        $service_id = isset($params->service_id) ? (int) $params->service_id : 0;
        
        $service = new facilitiesService();
        $result = $service->removeFacilityService($facility_id, $service_id);
        
        $this->type_msg = 'INFO';
        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }
    
    protected function addFacilityPaymentMethod(stdClass $params){
        
        $facility_id = isset($params->id) ? (int) $params->id : 0;
        
        $service = new facilitiesService();
        $result = $service->addFacilityPaymentMethod($facility_id, $params);
        
        $this->type_msg = "INFO";
        if(!$result['success']){
            $this->control_request = 2;
            $this->type_msg = "ERROR";            
        }
        
        $this->msg = $result['message'];        
        
        return $this->getJSONEncode($result['success']);
    }
    
    protected function removeFacilityPaymentMethod(stdClass $params){
        
        $facility_id = isset($params->id) ? (int) $params->id : 0;
        $method_id = isset($params->method_id) ? (int) $params->method_id : 0;
        
        $service = new facilitiesService();
        $result = $service->removeFacilityPaymentMethod($facility_id, $method_id);
        
        $this->type_msg = 'INFO';
        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
        
    }    


    protected function saveFacilityImages(stdClass $params, array $files = []) {

        $facility_id = isset($params->facility_id) ? (int) $params->facility_id : 0;

        $service = new facilitiesService();
        $result = $service->saveFacilityImages($facility_id, $params, $files);

        $this->type_msg = 'INFO';

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }
    
    protected function addAvailability(stdClass $params){
        
        $facility_id = isset($params->facility_id) ? (int) $params->facility_id : 0;

        $service = new facilitiesService();
        $result = $service->addAvailability($facility_id, $params);
        
        $this->type_msg = 'INFO';

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
        
    }
    
    protected function getAvailabilityCalendar(stdClass $params) {

        $facility_id = isset($params->facility_id) ? (int) $params->facility_id : 0;
        $start = trim((string) ($params->start ?? ''));
        $end = trim((string) ($params->end ?? ''));

        if ($facility_id <= 0 || $start === '' || $end === '') {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = 'Los datos para cargar la disponibilidad no son válidos.';

            return $this->getJSONEncode(false);
        }

        $facilities_model = new facilitiesModel();
        $availability_model = new facilitiesAvailabilityModel();
        $reservations_model = new reservationsModel();        

        $facility = $facilities_model->findByIdWithBookingType($facility_id, false);
        $capacity = max(1, (int) $facility['capacity']);

        if (empty($facility)) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = 'La instalación no existe.';

            return $this->getJSONEncode(false);
        }

        $start_date = (new DateTimeImmutable($start))->format('Y-m-d H:i:s');
        $end_date = (new DateTimeImmutable($end))->format('Y-m-d H:i:s');

        $availability = $availability_model->findActiveByRange(
                $facility_id,
                $start_date,
                $end_date,
                false
        );

        $events = [];

        if ($facility['booking_type_code'] === 'DATE_RANGE') {

            foreach ($availability as $item) {

                $reserved = $reservations_model->getReservedUnitsByPeriod(
                        $facility_id,
                        $item['available_from'],
                        $item['available_until']
                );

                /*
                 * Nunca mostramos más ocupación que capacidad.
                 * Si hubiera alguna inconsistencia histórica,
                 * visualmente quedará como completo.
                 */
                $occupied = min($reserved, $capacity);

                $percentage = ($occupied / $capacity) * 100;

                if ($occupied >= $capacity) {

                    $class_name = 'availability-full';
                } elseif ($percentage >= 50) {

                    $class_name = 'availability-medium';
                } else {

                    $class_name = 'availability-free';
                }

                $events[] = [
                    'id' => (string) $item['id'],
                    'title' => $occupied . '/' . $capacity,
                    'start' => (new DateTimeImmutable($item['available_from']))->format('Y-m-d'),
                    'end' => (new DateTimeImmutable($item['available_until']))->format('Y-m-d'),
                    'allDay' => true,
                    'className' => $class_name,
                    'extendedProps' => [
                        'availability_id' => (int) $item['id'],
                        'occupied_units' => $occupied,
                        'capacity' => $capacity,
                        'available_units' => max(0, $capacity - $occupied)
                    ]
                ];
            }
        }

        if ($facility['booking_type_code'] === 'SPECIFIC_HOUR') {

            $interval = (int) $facility['booking_interval_minutes'];
            $capacity = max(1, (int) $facility['capacity']);

            if ($interval <= 0) {
                $interval = 60;
            }

            foreach ($availability as $item) {

                $period_start = new DateTimeImmutable($item['available_from']);
                $period_end = new DateTimeImmutable($item['available_until']);

                $current = $period_start;

                while ($current < $period_end) {

                    $segment_end = $current->modify('+' . $interval . ' minutes');

                    if ($segment_end > $period_end) {
                        $segment_end = $period_end;
                    }

                    $segment_start_db = $current->format('Y-m-d H:i:s');
                    $segment_end_db = $segment_end->format('Y-m-d H:i:s');

                    $reserved = $reservations_model->getReservedUnitsByPeriod(
                            $facility_id,
                            $segment_start_db,
                            $segment_end_db
                    );

                    $occupied = min($reserved, $capacity);
                    $percentage = ($occupied / $capacity) * 100;

                    if ($occupied >= $capacity) {

                        $class_name = 'availability-full';
                    } elseif ($percentage >= 50) {

                        $class_name = 'availability-medium';
                    } else {

                        $class_name = 'availability-free';
                    }

                    $events[] = [
                        'id' => $item['id'] . '_' . $current->format('YmdHi'),
                        'title' => $occupied . '/' . $capacity,
                        'start' => $current->format('Y-m-d\TH:i:s'),
                        'end' => $segment_end->format('Y-m-d\TH:i:s'),
                        'allDay' => false,
                        'className' => $class_name,
                        'extendedProps' => [
                            'availability_id' => (int) $item['id'],
                            'segment_start' => $segment_start_db,
                            'segment_end' => $segment_end_db,
                            'occupied_units' => $occupied,
                            'capacity' => $capacity,
                            'available_units' => max(0, $capacity - $occupied)
                        ]
                    ];

                    $current = $segment_end;
                }
            }
        }

        $this->extra = $events;
        $this->type_msg = 'INFO';

        return $this->getJSONEncode(true);
    }
    
    protected function getAvailabilityReservations(stdClass $params) {

        $facility_id = isset($params->facility_id) ? (int) $params->facility_id : 0;
        $start_at = trim((string) ($params->start_at ?? ''));
        $end_at = trim((string) ($params->end_at ?? ''));

        if ($facility_id <= 0 || $start_at === '' || $end_at === '') {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = 'No se han podido cargar las reservas.';

            return $this->getJSONEncode(false);
        }

        $reservations_model = new reservationsModel();

        $reservations = $reservations_model->findByFacilityAndPeriod(
                $facility_id,
                $start_at,
                $end_at,
                true
        );

        $this->template->assign('reservations', $reservations);

        $html = $this->template->fetch(
                'panel/facilities/availability_reservations.html'
        );

        $this->extra = $html;
        $this->type_msg = 'INFO';

        return $this->getJSONEncode(true);
    }

    protected function deleteAvailability(stdClass $params) {

        $facility_id = isset($params->facility_id) ? (int) $params->facility_id : 0;
        $availability_id = isset($params->availability_id) ? (int) $params->availability_id : 0;

        $service = new facilitiesService();

        $result = $service->deleteAvailability(
                $facility_id,
                $availability_id,
                $params
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
