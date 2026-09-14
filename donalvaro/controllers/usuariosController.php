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
final class usuariosController extends controller{

    
    /**
     * Construct
     */
    public function __construct() {

        parent::__construct();
        $this->logs = new logsModel('usuarios','usuarios.log');
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
            'addUser' => ['method' => 'addUser', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'blockUser' => ['method' => 'blockUser', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'activeUser' => ['method' => 'activeUser', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'actualizarUsuario' => ['method' => 'actualizarUsuario', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'actualizarPassword' => ['method' => 'actualizarPassword', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'actualizarFotoPerfil' => ['method' => 'actualizarFotoPerfil', 'params' => true, 'files' => true, 'roles' => ['system', 'manager']],
            'actualizarDisplayName' => ['method' => 'actualizarDisplayName', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'addBonus' => ['method' => 'addBonus', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'deleteBonus' => ['method' => 'deleteBonus', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'setPassword' => ['method' => 'setPassword', 'params' => true, 'files' => false],
            'cargarProvinciasPais' => ['method' => 'cargarProvinciasPais', 'params' => true, 'files' => false],
            'deleteClient' => ['method' => 'deleteUser', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'deleteUser' => ['method' => 'deleteUser', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'actualizarPerfil' => ['method' => 'actualizarPerfil', 'params' => true, 'files' => false],
            'actualizarPasswordPerfil' => ['method' => 'actualizarPasswordPerfil', 'params' => true, 'files' => false],
            'confirmBonus' => ['method' => 'confirmBonus', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],

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
            'usuarios' => ['method' => 'cargarUsuarios', 'prefix' => 'users'],
            'roles' => ['method' => 'cargarRoles', 'prefix' => 'users'],
            'clientes' => ['method' => 'cargarClientes', 'prefix' => 'users'],
            'editcliente' => ['method' => 'editCliente', 'prefix' => 'users','breadcrumb' => ['clientes' => 'Clientes', 'title' => 'Editar Cliente']],
            'editusuario' => ['method' => 'editUsuario', 'prefix' => 'users','breadcrumb' => ['usuarios' => 'Usuarios', 'title' => 'Editar Usuario']],
            'auditcliente' => ['method' => 'auditUsuario', 'prefix' => 'users',
                'breadcrumb' => ['clientes' => 'Clientes', 'editcliente/'.$this->model_id => 'Editar Cliente', 'title' => 'Actividad']],
            'auditusuario' => ['method' => 'auditUsuario', 'prefix' => 'users',
                'breadcrumb' => ['usuarios' => 'Usuarios', 'editusuario/'.$this->model_id => 'Editar Usuario', 'title' => 'Actividad']]

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
    
        
    private function cargarUsuarios(&$plantilla_html){

        $usuarios = new usersModel();
        $countries_model = new countriesModel();
        $results = $usuarios->findByRole(2, true);
        $countries = $countries_model->findAllActive(true);
        
        $plantilla_html->assign('title',$this->chargeTitleHeader('Usuarios del Sistema',true));
        $plantilla_html->assign('results', $results);
        $plantilla_html->assign('countries', $countries);

    }
    
    private function cargarRoles(&$plantilla_html){
        
        $plantilla_html->assign('title',$this->chargeTitleHeader('Roles',true));
    }
    
    private function cargarClientes(&$plantilla_html){
        
        $usuarios = new usersModel();
        $countries_model = new countriesModel();
        $results = $usuarios->findByRole(3, true);
        $countries = $countries_model->findAllActive(true);
        
        $plantilla_html->assign('title',$this->chargeTitleHeader('Clientes',true));
        $plantilla_html->assign('results', $results);
        $plantilla_html->assign('countries', $countries);
    }
    
    private function editCliente(&$plantilla_html) {

        $provinces_model = new provincesModel();
        $countries_model = new countriesModel();
        $usuarios = new usersModel();
        $reservations_model = new reservationsModel();
        $users_bonuses_model = new usersBonusesModel();
        $bonuses_model = new bonusesModel();
        $tokens_model = new usersTokensModel();

        $results = $usuarios->findEditData((int) $this->model_id, true);
        $provinces = [];

        if (!empty($results->country_id)) {
            $provinces = $provinces_model->findByCountryId((int) $results->country_id, true);
        }

        $countries = $countries_model->findAllActive(true);
        $reservations = $reservations_model->findByUserId((int) $this->model_id, true);
        $bonuses = $users_bonuses_model->findByUserId((int) $this->model_id, true);
        $bonuses_available = $bonuses_model->findAllActive(true);
        $token = $tokens_model->findAccountActivationByUserId((int) $this->model_id, true);

        $plantilla_html->assign(
                'title',
                $this->chargeTitleHeader('Editar Cliente: ' . $results->first_name . ' ' . $results->last_name)
        );

        $plantilla_html->assign('provinces', $provinces);
        $plantilla_html->assign('countries', $countries);
        $plantilla_html->assign('model', $results);
        $plantilla_html->assign('reservations', $reservations);
        $plantilla_html->assign('bonuses', $bonuses);
        $plantilla_html->assign('bonuses_available', $bonuses_available);
        $plantilla_html->assign('token', $token);
    }

    private function editUsuario(&$plantilla_html){
        
        $provinces_model = new provincesModel();
        $countries_model = new countriesModel();
        $usuarios = new usersModel();

        $results = $usuarios->findEditData(intval($this->model_id), true);
        $provinces = [];

        if (!empty($results->country_id)) {
            $provinces = $provinces_model->findByCountryId((int) $results->country_id, true);
        }
        $countries = $countries_model->findAllActive(true);

        $plantilla_html->assign('title',
                $this->chargeTitleHeader('Editar Usuario: ' . $results->first_name . ' ' . $results->last_name)
        );

        $plantilla_html->assign('provinces', $provinces);
        $plantilla_html->assign('countries', $countries);
        $plantilla_html->assign('model', $results);
    }
    
    private function auditUsuario(&$plantilla_html){
        
        $model = new usersModel();
        $user = $model->findById(intval($this->model_id), true);

        $audit = new auditLogsRepository();
        $results = $audit->getUserAudit(intval($this->model_id));
        
        $plantilla_audit = new newSmarty();
        $plantilla_audit->assign('results',$results);
        $audit_tmp = $plantilla_audit->fetch('panel/audit_template.html');
        
        $plantilla_html->assign('title',$this->chargeTitleHeader('Actividad: '.$user->first_name.' '.$user->last_name));
        $plantilla_html->assign('audit_tmp',$audit_tmp);
    }

    
    /**
     * Print the js libraries
     * @param type $extra
     */
    protected function printFooterJs(string $extra='') {

        parent::printFooterJs($extra);
        
        $this->template_footer->assign('urlJsCntrl', $extra . _JS . 'panel/usuarios.js'._ASSET_VERSION);
        
        $footerJs = $this->template_footer->fetch('common/panel/footer_js.html');	
	echo $footerJs;
    }

    protected function blockUser(stdClass $params) {

        $user_id = isset($params->id) ? (int) $params->id : 0;
        $blocked = isset($params->value) ? (int) $params->value : usersModel::STATUS_INACTIVE;

        $service = new usersService();
        $result = $service->changeBlockStatus($user_id, $blocked === 1);

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }

    protected function activeUser(stdClass $params){
        
        $user_id = isset($params->id) ? (int) $params->id : 0;
        $status = isset($params->value) ? (int) $params->value : usersModel::STATUS_INACTIVE;
        
        $service = new usersService();
        $result = $service->changeUserStatus($user_id, $status);
        
        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
        
    }
    
    
    protected function actualizarUsuario(stdClass $params){
        
        $user_id = isset($params->id) ? (int) $params->id : 0;
        
        $service = new usersService();
        
        $result = $service->updateUser($user_id,$params);
        
        $this->type_msg = "INFO";
        if(!$result['success']){
            $this->control_request = 2;
            $this->type_msg = "ERROR";            
        }
        
        $this->msg = $result['message'];        
        
        return $this->getJSONEncode($result['success']);
    }
    
    protected function actualizarPassword(stdClass $params){
        
        $user_id = isset($params->id) ? (int) $params->id : 0;

        $usersService = new usersService();

        $result = $usersService->updatePassword($user_id, $params);
        
        $this->control_request = 2;
        $this->type_msg = "INFO";
        if(!$result['success']){
            
            $this->type_msg = "ERROR";            
        }
        
        $this->msg = $result['message'];        
        
        return $this->getJSONEncode($result['success']);
    }
    
    protected function actualizarFotoPerfil(stdClass $params, array $files = []){
        
        $user_id = isset($params->id) ? (int) $params->id : 0;
        
        $service = new usersService();
        
        $result = $service->updatePhoto($user_id, $params, $files);
        
        $this->type_msg = "INFO";
        if(!$result['success']){
            $this->control_request = 2;
            $this->type_msg = "ERROR";            
        }
        
        $this->msg = $result['message'];        
        
        return $this->getJSONEncode($result['success']);
    }
    
    protected function actualizarDisplayName(stdClass $params){
        
        $user_id = isset($params->id) ? (int) $params->id : 0;
        
        $service = new usersService();
        
        $result = $service->updateDisplayName($user_id, $params);
        
        $this->type_msg = "INFO";
        if(!$result['success']){
            $this->control_request = 2;
            $this->type_msg = "ERROR";            
        }
        
        $this->msg = $result['message'];        
        
        return $this->getJSONEncode($result['success']);
    }


    protected function addBonus(stdClass $params) {

        $user_id = isset($params->id) ? (int) $params->id : 0;

        $service = new usersService();

        $result = $service->addBonus($user_id, $params);

        $this->type_msg = "INFO";

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = "ERROR";
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }
    
    protected function deleteBonus(stdClass $params) {

        $bonus_id = isset($params->id) ? (int) $params->id : 0;

        $service = new usersService();

        $result = $service->deleteBonus($bonus_id);

        $this->type_msg = "INFO";

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = "ERROR";
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }
    
    protected function addUser(stdClass $params) {

        $role = isset($params->role) ? (int) $params->role : 0;

        $usersService = new usersService();

        $response = $usersService->createUserFromAdmin($role, $params);

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
         * Generamos el token de activación.
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
         * Enviamos el correo de activación.
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

        $this->msg = 'Usuario creado correctamente. Se ha enviado un correo para establecer la contraseña y activar la cuenta.';

        return $this->getJSONEncode(true);
    }

    protected function setPassword(stdClass $params) {

        $token = trim((string) ($params->token ?? ''));
        $password = (string) ($params->password ?? '');
        $passwordConfirm = (string) ($params->password_confirm ?? '');

        $usersService = new usersService();

        $result = $usersService->setPassword(
                $token,
                $password,
                $passwordConfirm
        );

        $this->type_msg = 'INFO';

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];
        $this->extra = $result['code'] ?? null;

        return $this->getJSONEncode($result['success']);
    }

    protected function cargarProvinciasPais(stdClass $params) {

        $country_id = isset($params->country_id) ? (int) $params->country_id : 0;

        if (empty($country_id)) {
            return $this->getJSONEncode(
                            '<option value="">Seleccione primero un país</option>'
                    );
        }

        $provinces_model = new provincesModel();

        $provinces = $provinces_model->findByCountryId($country_id, true);

        if (empty($provinces)) {
            return $this->getJSONEncode(
                            '<option value="">Este país no tiene provincias registradas</option>'
                    );
        }

        $html = '<option value="">Seleccione una provincia</option>';

        foreach ($provinces as $province) {

            $html .= '<option value="' . (int) $province->id . '" data-province="1">'
                    . htmlspecialchars($province->name, ENT_QUOTES, 'UTF-8')
                    . '</option>';
        }

        return $this->getJSONEncode($html);
    }
    
    protected function deleteUser(stdClass $params){
        
        $user_id = isset($params->id) ? (int) $params->id : 0;
        
        $service = new usersService();
        $result = $service->deleteUser($user_id);
        
        $this->type_msg = 'INFO';
        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = 'ERROR';
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
        
    }
    
    protected function actualizarPerfil(stdClass $params) {

        $first_name = trim((string) ($params->first_name ?? ''));
        $last_name = trim((string) ($params->last_name ?? ''));
        $email = strtolower(trim((string) ($params->email ?? '')));

        $phone = trim((string) ($params->phone ?? ''));
        $country_id = !empty($params->country_id) ? (int) $params->country_id : null;
        $province_id = !empty($params->province_id) ? (int) $params->province_id : null;
        $city = trim((string) ($params->city ?? ''));
        $postal_code = trim((string) ($params->postal_code ?? ''));
        $address = trim((string) ($params->address ?? ''));

        if ($first_name === '' || $last_name === '' || $email === '') {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = 'Debe completar correctamente los campos obligatorios.';

            return $this->getJSONEncode(false);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = 'El email indicado no es válido.';

            return $this->getJSONEncode(false);
        }

        /*
         * Cargamos el usuario autenticado.
         */
        $users_model = new usersModel();

        $current_user = $users_model->findEditData(
                $this->user_id,
                false
        );

        if (empty($current_user)) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = 'No se ha encontrado el usuario.';

            return $this->getJSONEncode(false);
        }

        /*
         * Comprobamos que el email no pertenezca
         * a otro usuario.
         */
        $email_user = $users_model->findByEmail($email);

        if (
                !empty($email_user) &&
                (int) $email_user['id'] !== $this->user_id
        ) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = 'Ya existe otro usuario con ese email.';

            return $this->getJSONEncode(false);
        }

        /*
         * Si no hay país seleccionado tampoco dejamos
         * una provincia asociada.
         */
        if ($country_id === null) {
            $province_id = null;
        }

        /*
         * Datos principales del usuario.
         */
        $users_model->setId($this->user_id);
        $users_model->setFirstName($first_name);
        $users_model->setLastName($last_name);
        $users_model->setEmail($email);

        if (!$users_model->updateProfileData()) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = 'No se han podido actualizar los datos del usuario.';

            return $this->getJSONEncode(false);
        }

        /*
         * Datos del perfil.
         */
        $profile_model = new usersProfileModel();

        $profile_model->setUserId($this->user_id);
        $profile_model->setPhone($phone !== '' ? $phone : null);
        $profile_model->setCountryId($country_id);
        $profile_model->setProvinceId($province_id);
        $profile_model->setAddress($address !== '' ? $address : null);
        $profile_model->setPostalCode($postal_code !== '' ? $postal_code : null);
        $profile_model->setCity($city !== '' ? $city : null);

        if ($profile_model->existsByUserId($this->user_id)) {

            if (!$profile_model->updateProfileData()) {

                $this->control_request = 2;
                $this->type_msg = 'ERROR';
                $this->msg = 'No se han podido actualizar los datos del perfil.';

                return $this->getJSONEncode(false);
            }
        } else {

            if (!$profile_model->add()) {

                $this->control_request = 2;
                $this->type_msg = 'ERROR';
                $this->msg = 'No se han podido guardar los datos del perfil.';

                return $this->getJSONEncode(false);
            }
        }

        /*
         * Actualizamos también el nombre almacenado
         * en la sesión.
         */
        $_SESSION['name_user'] = trim($first_name . ' ' . $last_name);
        $this->name_user = $_SESSION['name_user'];

        $this->type_msg = 'INFO';
        $this->msg = 'Perfil actualizado correctamente.';

        return $this->getJSONEncode(true);
    }
    
    protected function actualizarPasswordPerfil(stdClass $params) {

        $current_password = (string) ($params->current_password ?? '');

        if ($current_password === '') {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = 'Debe indicar la contraseña actual.';

            return $this->getJSONEncode(false);
        }

        /*
         * Recuperamos el usuario autenticado para comprobar
         * su contraseña actual.
         */
        $users_model = new usersModel();

        $user = $users_model->findById(
                $this->user_id,
                false
        );

        if (empty($user)) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = 'No se ha encontrado el usuario.';

            return $this->getJSONEncode(false);
        }

        /*
         * Comprobamos la contraseña actual.
         */
        if (!password_verify($current_password, $user['password'])) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = 'La contraseña actual no es correcta.';

            return $this->getJSONEncode(false);
        }

        /*
         * Adaptamos los nombres recibidos desde el formulario
         * a los que espera usersService::updatePassword().
         */
        $password_params = new stdClass();

        $password_params->password = (string) ($params->new_password ?? '');
        $password_params->password_confirm = (string) ($params->new_password_confirm ?? '');

        $service = new usersService();

        $result = $service->updatePassword(
                $this->user_id,
                $password_params
        );

        $this->type_msg = 'INFO';

        if (!$result['success']) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = $result['message'];

            return $this->getJSONEncode(false);
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode(true);
    }

    protected function confirmBonus(stdClass $params) {

        $bonus_id = isset($params->id) ? (int) $params->id : 0;

        $service = new usersService();

        $result = $service->confirmBonus($bonus_id);

        $this->type_msg = "INFO";

        if (!$result['success']) {
            $this->control_request = 2;
            $this->type_msg = "ERROR";
        }

        $this->msg = $result['message'];

        return $this->getJSONEncode($result['success']);
    }
}
