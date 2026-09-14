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
final class bonusController extends controller{

    
    /**
     * Construct
     */
    public function __construct() {

        parent::__construct();
        $this->logs = new logsModel('bonus','bonus.log');
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
        
        if (!_BONUSES_ENABLED) {
            header('Location: ' . $this->link);
            echo $this->redirectTemplate();
            die();
        }

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

        if (!_BONUSES_ENABLED) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = 'El módulo de bonos no está habilitado.';

            return $this->getJSONEncode(false);
        }

        $actionsMap = [
            'addBonus' => ['method' => 'addBonus', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'activeBonus' => ['method' => 'activeBonus', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'editBonus' => ['method' => 'editBonus', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'deleteBonus' => ['method' => 'deleteBonus', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'addBonusPaymentMethod' => ['method' => 'addBonusPaymentMethod', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'removeBonusPaymentMethod' => ['method' => 'removeBonusPaymentMethod', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']]

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
            'bonos' => ['method' => 'cargarBonos', 'prefix' => 'bonos'],
            'editbono' => ['method' => 'editBono', 'prefix' => 'bonos',
                'breadcrumb' => ['bonos' => 'Bonos', 'title' => 'Editar Bono']],
            'auditbono' => ['method' => 'auditBono', 'prefix' => 'bonos',
                'breadcrumb' => ['bonos' => 'Bonos', 'editbono/'.$this->model_id => 'Editar Bono', 'title' => 'Actividad']]

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
    
    private function cargarBonos(&$plantilla_html){

        $bonos = new bonusesModel();
        $facilities_model = new facilitiesModel();
        
        $results = $bonos->findAllNotDeleted(true);
        $facilities_result = $facilities_model->findAllNotDeleted(true);

        $plantilla_html->assign('title',$this->chargeTitleHeader('Bonos',true));
        $plantilla_html->assign('results', $results);
        $plantilla_html->assign('facilities', $facilities_result);

    }
    
    private function editBono(&$plantilla_html) {

        $bonus_model = new bonusesModel();
        $facilities_model = new facilitiesModel();
        $bonus_facilities_model = new bonusesFacilitiesModel();
        $pagos_model = new bonusesPaymentMethodsModel();

        $bonus = $bonus_model->findById((int) $this->model_id, true);
        $facilities = $facilities_model->findAllNotDeleted(true);
        $bonus_facilities = $bonus_facilities_model->findByBonusId((int) $this->model_id, false);
        $assigned_facilities = $bonus_facilities_model->findFacilitiesByBonusId((int) $this->model_id, true);
        $available_payment_methods = $pagos_model->findAvailableByBonusId(intval($this->model_id), true);
        $bonus_payment = $pagos_model->findByBonusId(intval($this->model_id), true);

        $facility_ids = [];

        foreach ($bonus_facilities as $facility) {
            $facility_ids[] = (int) $facility['facility_id'];
        }

        $plantilla_html->assign('title', $this->chargeTitleHeader('Editar Bono: ' . $bonus->name));
        $plantilla_html->assign('model', $bonus);
        $plantilla_html->assign('facilities', $facilities);
        $plantilla_html->assign('facility_ids', $facility_ids);
        $plantilla_html->assign('assigned_facilities', $assigned_facilities);
        $plantilla_html->assign('available_payment_methods', $available_payment_methods);
        $plantilla_html->assign('payment_methods', $bonus_payment);
    }

    private function auditBono(&$plantilla_html) {

        $model = new bonusesModel();
        $bono = $model->findById(intval($this->model_id), true);

        $audit = new auditLogsRepository();
        $results = $audit->getBonusAudit(intval($this->model_id));
        
        $plantilla_audit = new newSmarty();
        $plantilla_audit->assign('results',$results);
        $audit_tmp = $plantilla_audit->fetch('panel/audit_template.html');
        
        $plantilla_html->assign('title',$this->chargeTitleHeader('Actividad: '.$bono->name));
        $plantilla_html->assign('audit_tmp',$audit_tmp);
    }


    /**
     * Print the js libraries
     * @param type $extra
     */
    protected function printFooterJs(string $extra='') {

        parent::printFooterJs($extra);
        
        $this->template_footer->assign('urlJsCntrl', $extra . _JS . 'panel/bonus.js'._ASSET_VERSION);
        
        $footerJs = $this->template_footer->fetch('common/panel/footer_js.html');	
	echo $footerJs;
    }

    
    protected function addBonus(stdClass $params){

        $service = new bonusesService();
        $result = $service->addBonus($params);
        
        $this->type_msg = "INFO";
        if(!$result['success']){
            $this->control_request = 2;
            $this->type_msg = "ERROR";            
        }
        
        $this->msg = $result['message'];        
        
        return $this->getJSONEncode($result['success']);
    }
    
    protected function activeBonus(stdClass $params) {

        $bonus_id = isset($params->bonus_id) ? (int) $params->bonus_id : 0;
        $status = isset($params->value) ? (int) $params->value : bonusesModel::STATUS_INACTIVE;

        $service = new bonusesService();
        $result = $service->changeBonusStatus($bonus_id, $status);

        $this->type_msg = 'INFO';

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }
    
    protected function editBonus(stdClass $params) {

        $service = new bonusesService();
        $result = $service->editBonus($params);

        $this->type_msg = 'INFO';

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }
    
    protected function deleteBonus(stdClass $params) {

        $bonus_id = isset($params->bonus_id) ? (int) $params->bonus_id : 0;

        $service = new bonusesService();
        $result = $service->deleteBonus($bonus_id);

        $this->type_msg = 'INFO';

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }
    
    protected function addBonusPaymentMethod(stdClass $params){
        
        $bonus_id = isset($params->id) ? (int) $params->id : 0;
        
        $service = new bonusesService();
        $result = $service->addBonusPaymentMethod($bonus_id, $params);
        
        $this->type_msg = "INFO";
        if(!$result['success']){
            $this->control_request = 2;
            $this->type_msg = "ERROR";            
        }
        
        $this->msg = $result['message'];        
        
        return $this->getJSONEncode($result['success']);
    }
    
    protected function removeBonusPaymentMethod(stdClass $params){
        
        $bonus_id = isset($params->id) ? (int) $params->id : 0;
        $method_id = isset($params->method_id) ? (int) $params->method_id : 0;
        
        $service = new bonusesService();
        $result = $service->removeBonusPaymentMethod($bonus_id, $method_id);
        
        $this->type_msg = 'INFO';
        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
        
    }  


}
