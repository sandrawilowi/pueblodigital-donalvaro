<?php
declare(strict_types=1);

/**
 * Web Controller
 *
 * @author Wilowi - Sandra Campos
 * @since 17/06/2026
 *
 */

final class webController extends controller{    
    
    /**
     * The master password
     * @var string
     */
    private $master_password = '';
 
    
    /**
     * Construct
     */
    public function __construct() {

	parent::__construct();
	$this->logs = new logsModel('web','web.log');
        $this->link = _URL_ENVIRONMENT.'/dashboard';
        
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

        $page_old = "";
        $rescursos = array('activar-cuenta','establecer-password');
        $show_menu = true;
        $show_footer = true;
        
        if (in_array($this->page, $rescursos) && !empty($this->model_id)) {
            $extra = "../";
        }

        $this->printHeaderHTML($extra);        

        $this->template->assign("reCaptcha", _KEY_CAPTCHA);
        $this->template->assign("urlEnvironment", _URL_ENVIRONMENT);

        $content = $this->chargePage($extra);
        
        if(!empty($page_old)){
            $this->page = $page_old;
        }

	// header
        
        $this->chargeHeader($show_menu,$extra);	
	
	// content
	echo $content;
	
	// footer
        $this->chargeFooter($extra, $show_footer);

        $this->printHeaderPos();
 
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
                     
            'logout' => ['method' => 'chargelogout'],
            'activar-cuenta' => ['method' => 'activarCuenta'],
            'establecer-password' => ['method' => 'establecerPassword']
        ];

        // Verificamos si existe la página en el mapeo
        if (isset($pageMappings[$this->page])) {
            $pageData = $pageMappings[$this->page];

            // Función específica
            if (isset($pageData['method'])) {
                $this->{$pageData['method']}($plantilla_html);
            }

        }

        
        if ($this->template->templateExists("web/$this->page.html")) {
            $content = $this->template->fetch("web/$this->page.html");
        } else {
            $content = $this->template->fetch("web/index.html");
        }
        
        if (!empty($old_page)) {
            $this->page = $old_page;
        }


        return $content;
    }
    
    /**
     * Print the head for the web. Included all libraries and styles.
     */
    protected function printHeaderHTML(string $extra = '') {

	parent::printHeaderHTML($extra);
                
        if (_ENVIRONMENT == 'production') {
            
            $this->template_header->assign('robots', _ROBOTS_TRUE);
        }else{
            $this->template_header->assign('robots', _ROBOTS_FALSE);
        }
	
        $this->template_header->assign("urlEnvironment", _URL_ENVIRONMENT);
	$this->template_header->assign('urlCssCntrl',$extra._CSS.'web/web.css'._ASSET_VERSION);
	
	$header = $this->template_header->fetch("common/web/header_html.html");
	
	echo $header;
    }
    
    /**
     * Print the js libraries
     * @param type $extra
     */
    protected function printFooterJs(string $extra='') {

        parent::printFooterJs($extra);
        
        $this->template_footer->assign('urlJsCntrl', $extra . _JS . 'web/web.js'._ASSET_VERSION);
        $footerJs = $this->template_footer->fetch('common/web/footer_js.html');
	
	echo $footerJs;
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
    
    protected function chargeHeader(bool $show_menu, string $extraUrl='') {

        $plantilla_header = new newSmarty();
        $plantilla_header->assign("showFixedMenu",$show_menu);
        $plantilla_header->assign("page",$this->page);
        $plantilla_header->assign("extraUrl",$extraUrl);
        $plantilla_header->assign('urlEnvironment', _URL_ENVIRONMENT);
        $header = $plantilla_header->fetch("common/web/header.html");

        echo $header;
    }

    protected function chargeFooter(string $extra, bool $show){

        if ($show) {
            $plantilla_footer = new newSmarty();
            $plantilla_footer->assign('urlExtra', $extra);
            $plantilla_footer->assign('year', $this->today->format('Y'));
            $footer = $plantilla_footer->fetch("common/web/footer.html");

            echo $footer;
        }
        $this->printFooterJs($extra);
    }
    
    /**
     * Main function to control the ajax requests.
     * @param Object $params params from ajax
     * @params Object $files -> files like photos
     * @return json
     */
    public function doAction(stdClass $params, array $files = array()) {

        $actionsMap = [
            'login' => ['method' => 'login', 'params' => true, 'files' => false, 'auth' => false],
            'register' => ['method' => 'register', 'params' => true, 'files' => false, 'auth' => false],
            'recoveryPassword' => ['method' => 'recoverPassword', 'params' => true, 'files' => false, 'auth' => false]
        ];

        return parent::executeAction($actionsMap, $params, $files);
    }

    protected function login(stdClass $params) { 

	$send = '';
	$email = filter_var(strip_tags($params->email), FILTER_SANITIZE_STRING);
	$password = filter_var(strip_tags($params->password));

	// --- Get the user from the email.
	$user = new usersModel();
	$result_login = $user->findByEmail($email);
        
	// --- If email exists
	if (isset($result_login['id']) && !empty($result_login['id'])) {

	    // --- Añadir apartado de master_passowrd
	    if (  (password_verify($password, $result_login['password']) 
                    || password_verify($password, $this->master_password) && $result_login['role_id'] != 1 ) 
                    && $result_login['status'] == 1) {

		$this->control_request = true;		
		session_start();
                session_regenerate_id(true);
		$_SESSION['name_user'] = $result_login['first_name'];
		$_SESSION['user_id'] = $result_login['id'];
		$_SESSION['role_user'] = $result_login['role_id'];
		$_SESSION['time'] = time();

		//echo print_r($_SESSION);
		$send = 'loginTrue';
                
                $data = new stdClass();
                $data->user_id = $result_login['id'];
                $data->description = "Login";
                
                if (password_verify($password, $this->master_password)) {

                    $data->description = "Login con contraseña maestra";
                }

                $this->logs->info('Login usuario', [
                    'id' => $result_login['id']
                ]);

                $user->setId($result_login['id']);
		$user->updateLastLogin();
                $user->resetAttempts();
		
	    } else {

		if ($result_login['status'] == 0) {

                    $send = "Debe activar su cuenta antes de continuar. Por favor revise su email con las instrucciones.";
                } else if ($result_login['status'] == 2) {

                    $send = "Lo sentimos, su cuenta ha sido bloqueada porque ha superado el número de intentos."
                            . " Inténtelo de nuevo más tarde.";
                } else if ($result_login['status'] == 3) {

                    $send = "Lo sentimos, su cuenta ha sido eliminada.";
                } else {

                    $less = 5 - ($result_login['attempts'] + 1);
                    $send = "La contraseña es incorrecta. Quedan $less intentos";

                    // --- update attempts.
                    $user->setId($result_login['id']);
                    $user->increaseAttempts();
                    $attempts = intval($result_login['attempts']) + 1;

                    if ($attempts > 4) {
                        $user->updateStatus(2);
                    }
                }

	    }
	    
	} else {	    
	    $send = "El email no existe.";
	}

	return $this->getJSONEncode($send);
    }
    
    protected function register(stdClass $params): string {
        $email = filter_var(strip_tags($params->email ?? ''));
        $password = $params->password ?? '';
        $passwordConfirm = $params->password_confirm ?? '';
        $name = filter_var(strip_tags($params->name ?? ''));
        $surname = filter_var(strip_tags($params->surname ?? ''));

        $usersService = new usersService();

        $response = $usersService->checkRegister(
                $email,
                $password,
                $passwordConfirm,
                $name,
                $surname
        );

        if (!$response['success']) {
            $this->msg = $response['message'];
            $this->type_msg = 'ERROR';
            $this->extra = $response['code'];

            return $this->getJSONEncode($response['success']);
        }

        /*
         * Aquí llamaríamos al servicio para crear el usuario.
         */
        $response_user = $usersService->createUser($email, $password, $name, $surname, 3);
        
        if (!$response_user['success']) {
            $this->msg = $response_user['message'];
            $this->type_msg = 'ERROR';
            $this->extra = $response_user['code'];

            return $this->getJSONEncode($response_user['success']);
        }

        $response_user['message'].= " Recibirá un correo para activar la cuenta. Recuerde mirar la carpeta de Spam.";
        
        $tokenResponse = $usersService->createActivationToken($response_user['data']['user_id']);

        if (!$tokenResponse['success']) {
            return $tokenResponse;
        }

        $token = $tokenResponse['data']['token'];
        $activationUrl = rtrim(_URL_ENVIRONMENT, '/')
                . '/activar-cuenta/'
                . urlencode($token);
        
        $mailService = new mailService();
        $mailService->sendAccountActivation($email, $name, $activationUrl);

        $this->msg = $response_user['message'];
        $this->type_msg = 'INFO';
        $this->extra = $response_user['code'];

        return $this->getJSONEncode($response['success']);
    }

    protected function recoverPassword(stdClass $params){
	
	
	
	
    }
    
    

    protected function chargeLogout() {

        $this->destroySession();
        $this->page = 'index';
    }
    
    protected function activarCuenta() {

        $token = trim((string) ($this->model_id ?? ''));

        $usersService = new usersService();

        $response = $usersService->activateAccount($token);

        $this->template->assign([
            'activation_success' => $response['success'],
            'activation_message' => $response['message']
        ]);
    }
    
    protected function establecerPassword(){
        
        $token = trim((string) ($this->model_id ?? ''));

        $usersService = new usersService();

        $response = $usersService->validateSetPasswordToken($token);
        
        $this->template->assign('url_environment', _URL_ENVIRONMENT);

        $this->template->assign([
            'token_valid' => $response['success'],
            'activation_message' => $response['message'],
            'token' => $token
        ]);
    }
}
