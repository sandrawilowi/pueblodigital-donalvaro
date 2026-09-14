<?php
declare(strict_types=1);

/**
 * Description of controller
 *
 * @author Sandra
 */
class controller {
    
    use debugTrait;
    
        /**
     * Object for a template
     * @var class newSmarty 
     */
    protected $template = null;
    
    /**
     * Parameter to control some options in js
     * @var string 
     */
    protected $control_request = false;
    
    /**
     * Extra information for the js.
     * @var string 
     */
    protected $extra = '';
    
    protected $events = '';
    
    protected $update_container = false;
    
    /**
     * Select the tab.
     * @var string 
     */
    protected $tabs = '';
    
    /**
     * Modal Message
     * @var string 
     */
    protected $msg = '';
    
    /**
     * Modal type message.
     * @var string 
     */
    protected $type_msg = '';
    
    /**
     * Control the session.
     * @var boolean 
     */
    protected $exitSession = false;
    
    /**
     * Object for a log class
     * @var class log 
     */
    protected $logs = null;
    
    /**
     * Url
     * @var string 
     */
    protected $link = '';

    /**
     * Type Alert:
     * - RED: error
     * - GREEN: all ok
     * - YELLOW: warning
     * - BLUE: information
     * @var string
     */
    protected $alert_type = '';

    /**
     * Mensaje para mostrar en la alerta.
     * @var string 
     */
    protected $msg_alert = '';    
    
    
    /**
     * Actual Date
     * @var datetime
     */
    protected $today = null;
    
    /**
     * Debug code
     * @var boolean
     */
    protected $debug = false;
    
    /**
     * Template for header
     * @var smarty
     */
    protected $template_header = null;
    
    /**
     * Template for footer
     * @var smarty
     */
    protected $template_footer = null;
    
    /**
     * Page
     * @var type
     */
    protected $page = '';
    
    /**
     * Id controlador
     * @var type
     */
    protected $model_id = 0;
    
    /**
     * User id from session
     * @var integer
     */
    protected $user_id = 0;
    
    /**
     * User rol from session
     * @var integer
     */
    protected $role_user = 0;
    
    /**
     * Name user from session
     * @var integer
     */
    protected $name_user = 0;
    
    /**
     * Time session
     * @var type
     */
    protected $time_session = 0;
   
    
    protected $data_post = null;
    
    
    /**
     * Contruct
     */
    protected function __construct() {

	$this->template = new newSmarty();
        $this->today = new DateTime('now');
	
        $post = filter_input_array(INPUT_POST);
        $this->data_post = (object) $post;

    }
    
    
    /**
     * Main function
     */
    public function main(){
        
    }
    
    /**
     * Set page to show in document
     * @param string $page
     */
    public function setPage(string $page){
	
	$this->page = $page;
    }

    public function setModelId(mixed $id){
        $this->model_id = $id;
    }

    protected function getSessionData() {

        if (isset($_SESSION['user_id'])) {
            $this->user_id = intval($_SESSION['user_id']);
        }

        if (isset($_SESSION['role_user'])) {
            $this->role_user = intval($_SESSION['role_user']);
        }

        if (isset($_SESSION['name_user'])) {
            $this->name_user = filter_var($_SESSION['name_user']);
        }

        if (isset($_SESSION['time'])) {
            $this->time_session = intval($_SESSION['time']);
        }
    }

    protected function checkSession() {

        if ((time() - $this->time_session) > 3600 || empty($this->user_id) || empty($_SESSION)) {
            $this->destroySession();
            return;
        }

        $users_model = new usersModel();
        $user = $users_model->findByIdWithRole($this->user_id, false);

        if (empty($user) || (int) $user['status'] !== usersModel::STATUS_ACTIVE) {
            $this->destroySession();
            return;
        }

        $this->user_id = (int) $user['id'];
        $this->role_user = (int) $user['role_id'];
        $this->name_user = (string) $user['first_name'];

        $_SESSION['user_id'] = $this->user_id;
        $_SESSION['role_user'] = $this->role_user;
        $_SESSION['name_user'] = $this->name_user;
        $_SESSION['time'] = time();
    }

    protected function accessPage(string $page) {

        global $CONFIG;

        $rolesMap = $CONFIG['permissions'];
        $user_role = $this->getUserRoleGroup();

        if (!isset($rolesMap[$user_role]) || !in_array($page, $rolesMap[$user_role]['allowed'])) {
            return false;
        }

        return true;
    }

    protected function executeAction(array $actionsMap, stdClass $params, array $files = array()) {

        if (empty($params->function) || !isset($actionsMap[$params->function])) {
            return '';
        }

        $action = $actionsMap[$params->function];

        /*
         * Por defecto todas las acciones requieren sesión.
         * Las públicas deberán indicar:
         *
         * 'auth' => false
         */
        $requires_auth = $action['auth'] ?? true;

        if ($requires_auth) {

            if (empty($this->user_id) || $this->exitSession) {
                $this->extra = 'SESSION_FALSE';
                return $this->getJSONEncode($this->link);
            }

            /*
             * Si la acción define roles permitidos,
             * comprobamos que el usuario tenga uno de ellos.
             */
            if (!empty($action['roles'])) {

                $user_role = $this->getUserRoleGroup();

                if (!in_array($user_role, $action['roles'], true)) {
                    return $this->getJSONEncode([
                                'success' => false,
                                'message' => 'No tiene permisos para realizar esta operación.'
                    ]);
                }
            }
        }

        if (!$action['params'] && !$action['files']) {
            return $this->{$action['method']}();
        }

        if ($action['params'] && !$action['files']) {
            return $this->{$action['method']}($params);
        }

        if ($action['params'] && $action['files']) {
            return $this->{$action['method']}($params, $files);
        }

        return '';
    }

    protected function chargeNotAccessPage(){
        
        $plantilla_html = new newSmarty();
        
        $page = $plantilla_html->fetch("common/notAccess.html");
        
        $this->template->assign('page', $page);
        
    }
    

    /**
     * Format the ajax response in json
     * @param string $HTML ->html
     * @return string
     */
    protected function getJSONEncode(mixed $html): string {
        return json_encode(
                [
                    'result' => $html,
                    'control_request' => $this->control_request,
                    'extra' => $this->extra,
                    'tabs' => $this->tabs,
                    'msg' => $this->msg,
                    'type_msg' => $this->type_msg,
                    'events' => $this->events,
                    'update_container' => $this->update_container
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    /**
     * Print the head for the web. Included all libraries and styles.
     */
    protected function printHeaderHTML(string $extra = "") {
	
	$descripcion = "";
	$keywords = "";
	
	$this->template_header = new newSmarty();
	$this->template_header->assign('title',_TITLE);
	$this->template_header->assign('urlCss',$extra. _CSS);
        $this->template_header->assign('urlCssComun', $extra . _CSS . 'common.css'._ASSET_VERSION);
	$this->template_header->assign('urlLib',$extra. _VENDOR);
        $this->template_header->assign('urlPlugins',$extra. _PLUGINS);
        $this->template_header->assign('urlAssets',$extra. _ASSETS);
	$this->template_header->assign('keywords',$keywords);
	$this->template_header->assign('descripcion',$descripcion);	

    }
    
    /**
     * Print the footer fot web. Included javascript
     * @param string $extra
     */
    protected function printFooterJs(string $extra=''){
        
        $config_template = new newSmarty();
        $config_template->assign('environment',_URL_ENVIRONMENT);
        $config_template->assign('googleCaptcha', _KEY_CAPTCHA);
        
        $config_html = $config_template->fetch("common/config.html");
        
        $this->template_footer = new newSmarty();
        $this->template_footer->assign('configHtml',$config_html);
        $this->template_footer->assign('urlLib',$extra. _VENDOR);
        $this->template_footer->assign('urlPlugins',$extra. _PLUGINS);
	$this->template_footer->assign('urlJs', $extra. _JS);
        $this->template_footer->assign('urlJsCommon', $extra . _JS . 'common.js'._ASSET_VERSION);
        
    }

    /**
     * Get template to redirect the url.
     * @return html
     */
    protected function redirectTemplate() {

        $template_aux = new newSmarty();
        $template_aux->assign('url', $this->link);
        $redirect = $template_aux->fetch("common/redirect.html");
        
        return $redirect;
    }
    
    protected function chargeNavBar(string $extraUrl = ''){
        
        $model = new usersModel();
        $model_p = new usersProfileModel();
        $user = $model->findById($this->user_id);
        $profile = $model_p->findById($this->user_id);
        $user = (object) [...$user,...$profile];

        $plantilla_html = new newSmarty();
        $plantilla_html->assign('year', $this->today->format('Y'));
        $plantilla_html->assign('extraUrl', $extraUrl);
        $plantilla_html->assign('user',$user);

        $html = $plantilla_html->fetch('common/panel/navBar.html');

        return $html;
        
    }

    
    protected function chargeBreadCrumb(string $extraUrl = '', array $page = array()){
         
        $plantilla_html = new newSmarty();
        $plantilla_html->assign('year', $this->today->format('Y'));
        $plantilla_html->assign('extraUrl', $extraUrl);
        $plantilla_html->assign('role_user', $this->role_user);
        $plantilla_html->assign('nameUser', $this->name_user);
        $plantilla_html->assign('pageLink', $page);

        $html = $plantilla_html->fetch('common/panel/breadcrumb.html');

        return $html;
	
    }
    
    protected function chargeLastPageBreadCrumb(array &$array_bread, string $name_aux=''){
        
        $aux = new stdClass();
        $aux->link = $this->page;
        
        if(empty($name_aux)){
            $aux->name = ucfirst($this->page);
        }else{
            $aux->name = ucfirst($name_aux);
        }
        
        $aux->last = true;
        array_push($array_bread, $aux);
    }
    protected function chargePageBreadCrumb(&$array_bread, string $name_aux, string $page){
        
        $aux = new stdClass();
        $aux->link = $page;
        $aux->name = ucfirst($name_aux);        
        $aux->last = false;
        array_push($array_bread, $aux);
    }

    protected function getRolesSystem(){
        
        $model = new rolesModel();
        $result = $model->findBySystem(1);
        $send = array_column($result, 'id');
        
        return $send;
        
    }
    
    protected function getRolesClient(){
        
        return array(3);
    }
    
    protected function getRolesManager(){
        
        return array(2);
    }

    protected function chargeTitleHeader(string $name, bool $modal=false, string $page='') {

        $title = new newSmarty();
        
        $title->assign('title',$name);
        $title->assign('modal',$modal);
        $title->assign('page',$page);
        
        return $title->fetch('common/panel/title_header.html');
        
    }
    
    protected function getUserRoleGroup(): string {

        if (in_array($this->role_user, $this->getRolesSystem())) {
            return 'system';
        }

        if (in_array($this->role_user, $this->getRolesManager())) {
            return 'manager';
        }

        return 'client';
    }

    protected function destroySession(): void {

        $_SESSION = [];
        session_destroy();
        $this->exitSession = true;
    }
}
