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
final class dispositivosController extends controller{

    
    /**
     * Construct
     */
    public function __construct() {

        parent::__construct();
        $this->logs = new logsModel('dispositivos','dispositivos.log');
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
            
            'sincronizarCerraduras' => ['method' => 'sincronizarCerraduras', 'params' => false, 'files' => false, 'roles' => ['system']],
            'actualizarCerradura' => ['method' => 'actualizarCerradura', 'params' => true, 'files' => false, 'roles' => ['system']],
            'activeDevice' => ['method' => 'activeDevice', 'params' => true, 'files' => false, 'roles' => ['system']],
            'sincronizarGateways' => ['method' => 'sincronizarGateways', 'params' => false, 'files' => false, 'roles' => ['system']],
            'actualizarGateway' => ['method' => 'actualizarGateway', 'params' => true, 'files' => false, 'roles' => ['system']],
            'abrirCerradura' => ['method' => 'abrirCerradura', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'cerrarCerradura' => ['method' => 'cerrarCerradura', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']]

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
            'cerraduras' => ['method' => 'cargarCerraduras', 'prefix' => 'devices'],
            'editdevice' => ['method' => 'editarCerradura', 'prefix' => 'devices','breadcrumb' => ['cerraduras' => 'Cerraduras', 'title' => 'Editar Cerradura']],
            'gateways' => ['method' => 'cargarGateways', 'prefix' => 'devices'],
            'editgateway' => ['method' => 'editarGateway', 'prefix' => 'devices','breadcrumb' => ['gateways' => 'Gateways', 'title' => 'Editar Gateway']],
            'auditcerradura' => ['method' => 'auditCerradura', 'prefix' => 'devices',
                'breadcrumb' => ['cerraduras' => 'Cerraduras', 'editdevice/'.$this->model_id => 'Editar Cerradura', 'title' => 'Actividad']],
            'auditgateway' => ['method' => 'auditGateway', 'prefix' => 'devices',
                'breadcrumb' => ['gateways' => 'Gateways', 'editgateway/'.$this->model_id => 'Editar Gateway', 'title' => 'Actividad']]

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

    
    /**
     * Print the js libraries
     * @param type $extra
     */
    protected function printFooterJs(string $extra='') {

        parent::printFooterJs($extra);
        
        $this->template_footer->assign('urlJsCntrl', $extra . _JS . 'panel/dispositivos.js'._ASSET_VERSION);
        
        $footerJs = $this->template_footer->fetch('common/panel/footer_js.html');	
	echo $footerJs;
    }

    private function cargarCerraduras(&$plantilla_html){
        
        $model = new ttlockDevicesModel();
        $results = $model->findAllActive(true);

        $plantilla_html->assign('title',$this->chargeTitleHeader('Cerraduras',true));
        $plantilla_html->assign('results', $results);

    }
    
    private function editarCerradura(&$plantilla_html){
        
        $model = new ttlockDevicesModel();
        $result = $model->findByIdWithGateway(intval($this->model_id), true);

        $plantilla_html->assign('title',$this->chargeTitleHeader('Editar Cerradura: '.$result->lock_alias));
        $plantilla_html->assign('model', $result);
    }
    
    private function cargarGateways(&$plantilla_html){
        
        $model = new ttlockGatewaysModel();
        $results = $model->findAllActive(true);

        $plantilla_html->assign('title',$this->chargeTitleHeader('Gateways',true));
        $plantilla_html->assign('results', $results);
    }
    
    private function editarGateway(&$plantilla_html){
        
        $model = new ttlockGatewaysModel();
        $result = $model->findById(intval($this->model_id), true);
        $locks = $model->findLocksByGatewayId(intval($this->model_id), true);

        $plantilla_html->assign('title',$this->chargeTitleHeader('Editar Gateway: '.$result->gateway_name));
        $plantilla_html->assign('model', $result);
        $plantilla_html->assign('locks', $locks);
    }
    
    private function auditCerradura(&$plantilla_html){
        
        $model = new ttlockDevicesModel();
        $device = $model->findById(intval($this->model_id), true);

        $audit = new auditLogsRepository();
        $results = $audit->getDeviceAudit(intval($this->model_id));
        
        $plantilla_audit = new newSmarty();
        $plantilla_audit->assign('results',$results);
        $audit_tmp = $plantilla_audit->fetch('panel/audit_template.html');
        
        $plantilla_html->assign('title',$this->chargeTitleHeader('Actividad: '.$device->lock_alias));
        $plantilla_html->assign('audit_tmp',$audit_tmp);
    }
    
    private function auditGateway(&$plantilla_html){
        
        $model = new ttlockGatewaysModel();
        $gateway = $model->findById(intval($this->model_id), true);
        
        $audit = new auditLogsRepository();
        $results = $audit->getGatewayAudit(intval($this->model_id));
        
        $plantilla_audit = new newSmarty();
        $plantilla_audit->assign('results',$results);
        $audit_tmp = $plantilla_audit->fetch('panel/audit_template.html');
        
        $plantilla_html->assign('title',$this->chargeTitleHeader('Actividad: '.$gateway->gateway_name));
        $plantilla_html->assign('audit_tmp',$audit_tmp);
    }
    
    /* ACCIONES */
    
    protected function sincronizarCerraduras(){
        
        //$this->printDebug("dentro",true);
        $service_ttlock = new ttlockService();
        
        $result = $service_ttlock->syncLocks();
        
        $this->msg = $result['message'];
        $this->type_msg = "INFO";
        if(!$result['success']){
            $this->control_request = 2;
            $this->type_msg = "ERROR";            
        }
        
        return $this->getJSONEncode($result['success']);
        
    }
    
    protected function sincronizarGateways(){
        
        $service_ttlock = new ttlockService();
        $result = $service_ttlock->syncGateways();
        
        $this->msg = $result['message'];
        $this->type_msg = "INFO";
        if(!$result['success']){
            $this->control_request = 2;
            $this->type_msg = "ERROR";            
        }
        
        return $this->getJSONEncode($result['success']);
        
    }
    
    protected function actualizarCerradura(stdClass $params): string{
        
        $this->control_request = 2;
        $device_id = isset($params->id) ? (int) $params->id : 0;
        $lock_alias = isset($params->alias) ? trim(strip_tags((string) $params->alias)) : '';
        
        $service = new ttlockService();
        $this->type_msg = "INFO";

        $result = $service->renameLock($device_id, $lock_alias);
        
        if(!$result['success']){
            $this->type_msg = "ERROR";            
        }
        
        $this->msg = $result['message'];        
        
        return $this->getJSONEncode($result['success']);
        
    }
    
    protected function actualizarGateway(stdClass $params): string{
        
        $this->control_request = 2;
        $device_id = isset($params->id) ? (int) $params->id : 0;
        $nombre = isset($params->nombre) ? trim(strip_tags((string) $params->nombre)) : '';
        
        $service = new ttlockService();
        $this->type_msg = "INFO";

        $result = $service->renameGateway($device_id, $nombre);
        
        if(!$result['success']){
            $this->type_msg = "ERROR";            
        }
        
        $this->msg = $result['message'];        
        
        return $this->getJSONEncode($result['success']);
        
    }
    
    protected function activeDevice(stdClass $params): string {

        $device_id = isset($params->id) ? (int) $params->id : 0;
        $status = isset($params->value) ? (int) $params->value : ttlockDevicesModel::STATUS_INACTIVE;
        $type_device = isset($params->type_device) ? trim(strip_tags((string) $params->type_device)) : '';

        $service = new ttlockService();

        $this->type_msg = 'INFO';
        switch ($type_device) {

            case 'cerraduras':
                $result = $service->changeDeviceStatus($device_id,$status);
                break;
            
            case 'gateway':
                $result = $service->changeGatewayStatus($device_id, $status);
                break;

            default:
                $result = [
                    'success' => false,
                    'message' => 'Petición incorrecta.'
                ];

                break;
        }

        

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }

    protected function abrirCerradura(stdClass $params): string {
        
        $this->control_request = 2;
        $id = (int) ($params->id ?? 0);

        $service = new ttlockService();

        $response = $service->unlockLock($id);

        $this->msg = $response['message'];

        if ($response['success']) {
            $this->type_msg = 'INFO';
        } else {
            $this->type_msg = 'ERROR';
        }

        return $this->getJSONEncode($response['success']);
    }

    protected function cerrarCerradura(stdClass $params): string {
        
        $this->control_request = 2;
        $id = (int) ($params->id ?? 0);

        $service = new ttlockService();

        $response = $service->lockLock($id);

        $this->msg = $response['message'];

        if ($response['success']) {
            $this->type_msg = 'INFO';
        } else {
            $this->type_msg = 'ERROR';
        }

        return $this->getJSONEncode($response['success']);
    }
}
