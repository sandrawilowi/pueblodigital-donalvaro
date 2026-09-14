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
final class pinsController extends controller{

    
    /**
     * Construct
     */
    public function __construct() {

        parent::__construct();
        $this->logs = new logsModel('pins','pin.log');
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
            'addPin' => ['method' => 'addPin', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'revokePin' => ['method' => 'revokePin', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']]

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
            'codigos-pin' => ['method' => 'cargarPins', 'prefix' => 'codigos'],
            'editpin' => ['method' => 'editPin', 'prefix' => 'codigos',
                'breadcrumb' => ['codigos-pin' => 'Códigos Pin', 'title' => 'Ver Pin']],
            'auditpin' => ['method' => 'auditPin', 'prefix' => 'codigos',
                'breadcrumb' => ['codigos-pin' => 'Pins', 'editpin/'.$this->model_id => 'Ver Pin', 'title' => 'Actividad']],

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

    
    private function cargarPins(&$plantilla_html){
        
        $pins_model = new accessPinsModel();
        $facilities = new facilitiesModel();
        $settings_model = new settingsModel();
        $encryption_service = new pinEncryptionService();
        $pin_statuses_model = new pinStatusesModel();

        $pin_statuses = $pin_statuses_model->findAllActive(true);
        $result_facilities = $facilities->findActiveWithDevices(true);
        $results = $pins_model->findAll(true);

        foreach ($results as $result) {
            $result->pin = $encryption_service->decrypt(
                    $result->pin_code_encrypted
            );
        }
        
        $pin_min_length = (int) $settings_model->findValueByKey(
                        'access_pin_min_length'
                );

        $pin_max_length = (int) $settings_model->findValueByKey(
                        'access_pin_max_length'
                );

        $plantilla_html->assign('title',$this->chargeTitleHeader('Códigos Pin',true));
        $plantilla_html->assign('results', $results);
        $plantilla_html->assign('facilities', $result_facilities);
        $plantilla_html->assign('pin_min_length',$pin_min_length);
        $plantilla_html->assign('pin_max_length',$pin_max_length);
        $plantilla_html->assign('pin_statuses', $pin_statuses);
    }
    
    private function editPin(&$plantilla_html){
        
        $repository = new accessPinsRepository();

        $result = $repository->getPinEditData((int) $this->model_id);

        $plantilla_html->assign('title', $this->chargeTitleHeader('Ver Pin: '.$result->pin));
        $plantilla_html->assign('model', $result);
    }
    
    private function auditPin(&$plantilla_html){
        
        $model = new accessPinsModel();
        $service = new pinEncryptionService();
        $pins = $model->findById(intval($this->model_id), true);
        
        $pin = $service->decrypt($pins->pin_code_encrypted);
        
        $audit = new auditLogsRepository();
        $results = $audit->getPinAudit(intval($this->model_id));
        
        $plantilla_audit = new newSmarty();
        $plantilla_audit->assign('results',$results);
        $audit_tmp = $plantilla_audit->fetch('panel/audit_template.html');
        
        $plantilla_html->assign('title',$this->chargeTitleHeader('Actividad: '.$pin));
        $plantilla_html->assign('audit_tmp',$audit_tmp);
    }
    

    /**
     * Print the js libraries
     * @param type $extra
     */
    protected function printFooterJs(string $extra='') {

        parent::printFooterJs($extra);
        
        $this->template_footer->assign('urlJsCntrl', $extra . _JS . 'panel/pins.js'._ASSET_VERSION);
        
        $footerJs = $this->template_footer->fetch('common/panel/footer_js.html');	
	echo $footerJs;
    }
    
    protected function addPin(stdClass $params) {

        $facility_id = isset($params->facility_id) ? (int) $params->facility_id : 0;
        $valid_from = trim($params->valid_from ?? '');
        $valid_until = trim($params->valid_until ?? '');
        $description = trim($params->description ?? '');

        $generate_pin = isset($params->generate_pin);
        $is_permanent = isset($params->is_permanent);
        $is_one_time = isset($params->is_one_time);

        if ($is_permanent && $is_one_time) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = 'El PIN no puede ser permanente y de un solo uso al mismo tiempo.';
            return $this->getJSONEncode(false);
        }

        if ($is_permanent) {
            $access_type = accessPinsModel::ACCESS_TYPE_PERMANENT;
        } elseif ($is_one_time) {
            $access_type = accessPinsModel::ACCESS_TYPE_ONE_TIME;
        } else {
            $access_type = accessPinsModel::ACCESS_TYPE_TEMPORARY;
        }

        $pin = $generate_pin ? null : trim((string) ($params->pin_code ?? ''));

        $service = new accessPinsService();

        $result = $service->createManualPin(
                $facility_id,
                $valid_from,
                $valid_until,
                $description,
                $access_type,
                $pin
        );

        $this->type_msg = 'INFO';

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }

    protected function revokePin(stdClass $params) {

        $pin_id = isset($params->pin_id) ? (int) $params->pin_id : 0;

        $service = new accessPinsService();

        $result = $service->revokeManualPin($pin_id);

        $this->type_msg = 'INFO';

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }
}
