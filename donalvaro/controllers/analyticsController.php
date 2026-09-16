<?php
declare(strict_types=1);

/**
 * Admin Panel Controller
 *
 * @author Wilowi - Sandra Campos
 * @since 29/08/2026
 *
 */


/**
 * Class for control the panel
 */
final class analyticsController extends controller{

    
    /**
     * Construct
     */
    public function __construct() {

        parent::__construct();
        $this->logs = new logsModel('analytics','pin.log');
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
            'loadStatistics' => ['method' => 'loadStatistics', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'generateReport' => ['method' => 'generateReport', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']],
            'generateReportPdf' => ['method' => 'generateReportPdf', 'params' => true, 'files' => false, 'roles' => ['system', 'manager']]

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
            'estadisticas' => ['method' => 'cargarEstadisticas', 'prefix' => 'analytics'],
            'informes' => ['method' => 'cargarInformes', 'prefix' => 'analytics']

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

    
    private function cargarEstadisticas(&$plantilla_html) {

        $facilities_model = new facilitiesModel();

        $facilities = $facilities_model->findAll(true);

        $current_year = (int) date('Y');

        /*
         * Mostramos todos los años desde 2026
         * hasta el año actual, del más reciente al más antiguo.
         */
        $years = range($current_year, 2026);

        $plantilla_html->assign('facilities', $facilities);
        $plantilla_html->assign('years', $years);
        $plantilla_html->assign('current_year', $current_year);
        $plantilla_html->assign('bonuses_enabled',defined('_BONUSES_ENABLED') && _BONUSES_ENABLED);
        $plantilla_html->assign('title', $this->chargeTitleHeader('Estadísticas'));
    }

    private function cargarInformes(&$plantilla_html) {
$this->printDebug('entro');
        $facilities_model = new facilitiesModel();
        $reservation_statuses_model = new reservationStatusesModel();

        /*
         * Todas las instalaciones porque los informes
         * también deben permitir consultar datos históricos.
         */
        $facilities = $facilities_model->findAll(true);
$this->printDebug($facilities);
        $reservation_statuses = $reservation_statuses_model->findAllActive(true);
$this->printDebug($reservation_statuses);
        $plantilla_html->assign('facilities', $facilities);
        $plantilla_html->assign('reservation_statuses', $reservation_statuses);
        $plantilla_html->assign('bonuses_enabled',defined('_BONUSES_ENABLED') && _BONUSES_ENABLED);
        $plantilla_html->assign('title', $this->chargeTitleHeader('Informes'));
    }

    private function generateReservationsReport(stdClass $params) {

        $date_from = trim((string) ($params->date_from ?? ''));
        $date_until = trim((string) ($params->date_until ?? ''));
        $facility_id = isset($params->facility_id) && $params->facility_id !== '' ? (int) $params->facility_id : 0;
        $status_id = isset($params->status_id) && $params->status_id !== '' ? (int) $params->status_id : 0;

        if ($date_from !== '' && $date_until !== '' && $date_until < $date_from) {
            throw new InvalidArgumentException('La fecha hasta no puede ser anterior a la fecha desde.');
        }

        $repository = new reportsRepository();

        $results = $repository->getReservationsReport([
            'date_from' => $date_from,
            'date_until' => $date_until,
            'facility_id' => $facility_id,
            'status_id' => $status_id
        ]);

        $this->template->assign('results', $results);

        $html = $this->template->fetch('panel/analytics/tables/tableReservationsReport.html');

        $this->extra = $html;

        return $this->getJSONEncode(true);
    }

    private function generateIncomeReport(stdClass $params) {

        $date_from = trim((string) ($params->date_from ?? ''));
        $date_until = trim((string) ($params->date_until ?? ''));
        $facility_id = isset($params->facility_id) && $params->facility_id !== '' ? (int) $params->facility_id : 0;

        if ($date_from !== '' && $date_until !== '' && $date_until < $date_from) {
            throw new InvalidArgumentException('La fecha hasta no puede ser anterior a la fecha desde.');
        }

        $repository = new reportsRepository();

        $results = $repository->getIncomeReport([
            'date_from' => $date_from,
            'date_until' => $date_until,
            'facility_id' => $facility_id
        ]);

        $total_income = 0;

        foreach ($results as $result) {
            $total_income += (float) $result->total_amount;
        }

        $this->template->assign('results', $results);
        $this->template->assign('total_income', $total_income);

        $html = $this->template->fetch(
                'panel/analytics/tables/tableIncomeReport.html'
        );

        $this->extra = $html;

        return $this->getJSONEncode(true);
    }

    private function generateIncomeReportPdf(stdClass $params) {

        $date_from = trim((string) ($params->date_from ?? ''));
        $date_until = trim((string) ($params->date_until ?? ''));
        $facility_id = isset($params->facility_id) && $params->facility_id !== '' ? (int) $params->facility_id : 0;

        if ($date_from !== '' && $date_until !== '' && $date_until < $date_from) {
            throw new InvalidArgumentException('La fecha hasta no puede ser anterior a la fecha desde.');
        }

        $repository = new reportsRepository();

        $results = $repository->getIncomeReport([
            'date_from' => $date_from,
            'date_until' => $date_until,
            'facility_id' => $facility_id
        ]);

        $facility_name = null;

        if ($facility_id > 0) {

            $facilities_model = new facilitiesModel();

            $facility = $facilities_model->findById(
                    $facility_id,
                    false
            );

            if (!empty($facility)) {
                $facility_name = $facility['name'];
            }
        }

        $service = new reportsPdfService();

        $pdf_result = $service->generateIncomeReport(
                $results,
                [
                    'date_from' => $date_from,
                    'date_until' => $date_until,
                    'facility_id' => $facility_id,
                    'facility_name' => $facility_name
                ]
        );

        if (!$pdf_result['success']) {
            throw new RuntimeException($pdf_result['message']);
        }

        $this->extra = [
            'path' => $pdf_result['path'],
            'filename' => $pdf_result['filename']
        ];

        return $this->getJSONEncode(true);
    }

    private function generateReservationsReportPdf(stdClass $params) {

        $date_from = trim((string) ($params->date_from ?? ''));
        $date_until = trim((string) ($params->date_until ?? ''));
        $facility_id = isset($params->facility_id) && $params->facility_id !== '' ? (int) $params->facility_id : 0;
        $status_id = isset($params->status_id) && $params->status_id !== '' ? (int) $params->status_id : 0;

        try {

            if ($date_from !== '' && $date_until !== '' && $date_until < $date_from) {
                throw new InvalidArgumentException('La fecha hasta no puede ser anterior a la fecha desde.');
            }

            $repository = new reportsRepository();

            $results = $repository->getReservationsReport([
                'date_from' => $date_from,
                'date_until' => $date_until,
                'facility_id' => $facility_id,
                'status_id' => $status_id
            ]);

            $facility_name = null;
            $status_name = null;

            if ($facility_id > 0) {

                $facilities_model = new facilitiesModel();
                $facility = $facilities_model->findById($facility_id, false);

                if (!empty($facility)) {
                    $facility_name = $facility['name'];
                }
            }

            if ($status_id > 0) {

                $reservation_statuses_model = new reservationStatusesModel();
                $status = $reservation_statuses_model->findById($status_id, false);

                if (!empty($status)) {
                    $status_name = $status['name'];
                }
            }

            $service = new reportsPdfService();

            $pdf_result = $service->generateReservationsReport(
                    $results,
                    [
                        'date_from' => $date_from,
                        'date_until' => $date_until,
                        'facility_id' => $facility_id,
                        'facility_name' => $facility_name,
                        'status_id' => $status_id,
                        'status_name' => $status_name
                    ]
            );

            if (!$pdf_result['success']) {
                throw new RuntimeException($pdf_result['message']);
            }

            $this->extra = [
                'path' => $pdf_result['path'],
                'filename' => $pdf_result['filename']
            ];

            return $this->getJSONEncode(true);
        } catch (Throwable $e) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = $e->getMessage();

            return $this->getJSONEncode(false);
        }
    }

    private function generateAccessesReport(stdClass $params) {

        $date_from = trim((string) ($params->date_from ?? ''));
        $date_until = trim((string) ($params->date_until ?? ''));
        $facility_id = isset($params->facility_id) && $params->facility_id !== '' ? (int) $params->facility_id : 0;

        if ($date_from !== '' && $date_until !== '' && $date_until < $date_from) {
            throw new InvalidArgumentException('La fecha hasta no puede ser anterior a la fecha desde.');
        }

        $repository = new reportsRepository();

        $results = $repository->getAccessesReport([
            'date_from' => $date_from,
            'date_until' => $date_until,
            'facility_id' => $facility_id
        ]);

        $this->template->assign('results', $results);

        $html = $this->template->fetch(
                'panel/analytics/tables/tableAccessesReport.html'
        );

        $this->extra = $html;

        return $this->getJSONEncode(true);
    }
    
    private function generateClientsReport(stdClass $params) {

        $date_from = trim((string) ($params->date_from ?? ''));
        $date_until = trim((string) ($params->date_until ?? ''));
        $facility_id = isset($params->facility_id) && $params->facility_id !== '' ? (int) $params->facility_id : 0;

        if ($date_from !== '' && $date_until !== '' && $date_until < $date_from) {
            throw new InvalidArgumentException('La fecha hasta no puede ser anterior a la fecha desde.');
        }

        $repository = new reportsRepository();

        $results = $repository->getClientsReport([
            'date_from' => $date_from,
            'date_until' => $date_until,
            'facility_id' => $facility_id
        ]);

        $this->template->assign('results', $results);

        $html = $this->template->fetch(
                'panel/analytics/tables/tableClientsReport.html'
        );

        $this->extra = $html;

        return $this->getJSONEncode(true);
    }
    
    private function generateClientsReportPdf(stdClass $params) {

        $date_from = trim((string) ($params->date_from ?? ''));
        $date_until = trim((string) ($params->date_until ?? ''));
        $facility_id = isset($params->facility_id) && $params->facility_id !== '' ? (int) $params->facility_id : 0;

        if ($date_from !== '' && $date_until !== '' && $date_until < $date_from) {
            throw new InvalidArgumentException('La fecha hasta no puede ser anterior a la fecha desde.');
        }

        $repository = new reportsRepository();

        $results = $repository->getClientsReport([
            'date_from' => $date_from,
            'date_until' => $date_until,
            'facility_id' => $facility_id
        ]);

        $facility_name = null;

        if ($facility_id > 0) {

            $facilities_model = new facilitiesModel();

            $facility = $facilities_model->findById(
                    $facility_id,
                    false
            );

            if (!empty($facility)) {
                $facility_name = $facility['name'];
            }
        }

        $service = new reportsPdfService();

        $pdf_result = $service->generateClientsReport(
                $results,
                [
                    'date_from' => $date_from,
                    'date_until' => $date_until,
                    'facility_id' => $facility_id,
                    'facility_name' => $facility_name
                ]
        );

        if (!$pdf_result['success']) {
            throw new RuntimeException($pdf_result['message']);
        }

        $this->extra = [
            'path' => $pdf_result['path'],
            'filename' => $pdf_result['filename']
        ];

        return $this->getJSONEncode(true);
    }

    private function generateOriginReport(stdClass $params) {

        $date_from = trim((string) ($params->date_from ?? ''));
        $date_until = trim((string) ($params->date_until ?? ''));
        $facility_id = isset($params->facility_id) && $params->facility_id !== '' ? (int) $params->facility_id : 0;

        if ($date_from !== '' && $date_until !== '' && $date_until < $date_from) {
            throw new InvalidArgumentException('La fecha hasta no puede ser anterior a la fecha desde.');
        }

        $repository = new reportsRepository();

        $results = $repository->getOriginReport([
            'date_from' => $date_from,
            'date_until' => $date_until,
            'facility_id' => $facility_id
        ]);

        $total_clients = 0;

        foreach ($results as $result) {
            $total_clients += (int) $result->clients_count;
        }

        foreach ($results as $result) {

            $result->percentage = $total_clients > 0 ? round(((int) $result->clients_count / $total_clients) * 100, 2) : 0;
        }

        $this->template->assign('results', $results);
        $this->template->assign('total_clients', $total_clients);

        $html = $this->template->fetch(
                'panel/analytics/tables/tableOriginReport.html'
        );

        $this->extra = $html;

        return $this->getJSONEncode(true);
    }

    private function generateOriginReportPdf(stdClass $params) {

        $date_from = trim((string) ($params->date_from ?? ''));
        $date_until = trim((string) ($params->date_until ?? ''));
        $facility_id = isset($params->facility_id) && $params->facility_id !== '' ? (int) $params->facility_id : 0;

        if ($date_from !== '' && $date_until !== '' && $date_until < $date_from) {
            throw new InvalidArgumentException('La fecha hasta no puede ser anterior a la fecha desde.');
        }

        $repository = new reportsRepository();

        $results = $repository->getOriginReport([
            'date_from' => $date_from,
            'date_until' => $date_until,
            'facility_id' => $facility_id
        ]);

        $total_clients = 0;

        foreach ($results as $result) {
            $total_clients += (int) $result->clients_count;
        }

        foreach ($results as $result) {

            $result->percentage = $total_clients > 0 ? round(((int) $result->clients_count / $total_clients) * 100, 2) : 0;
        }

        $facility_name = null;

        if ($facility_id > 0) {

            $facilities_model = new facilitiesModel();
            $facility = $facilities_model->findById($facility_id, false);

            if (!empty($facility)) {
                $facility_name = $facility['name'];
            }
        }

        $service = new reportsPdfService();

        $pdf_result = $service->generateOriginReport(
                $results,
                [
                    'date_from' => $date_from,
                    'date_until' => $date_until,
                    'facility_id' => $facility_id,
                    'facility_name' => $facility_name,
                    'total_clients' => $total_clients
                ]
        );

        if (!$pdf_result['success']) {
            throw new RuntimeException($pdf_result['message']);
        }

        $this->extra = [
            'path' => $pdf_result['path'],
            'filename' => $pdf_result['filename']
        ];

        return $this->getJSONEncode(true);
    }

    private function generateBonusesReport(stdClass $params) {

        $date_from = trim((string) ($params->date_from ?? ''));
        $date_until = trim((string) ($params->date_until ?? ''));
        $facility_id = isset($params->facility_id) && $params->facility_id !== '' ? (int) $params->facility_id : 0;

        if ($date_from !== '' && $date_until !== '' && $date_until < $date_from) {
            throw new InvalidArgumentException('La fecha hasta no puede ser anterior a la fecha desde.');
        }

        $repository = new reportsRepository();

        $results = $repository->getBonusesReport([
            'date_from' => $date_from,
            'date_until' => $date_until,
            'facility_id' => $facility_id
        ]);

        $this->template->assign('results', $results);

        $html = $this->template->fetch(
                'panel/analytics/tables/tableBonusesReport.html'
        );

        $this->extra = $html;

        return $this->getJSONEncode(true);
    }

    private function generateBonusesReportPdf(stdClass $params) {

        $date_from = trim((string) ($params->date_from ?? ''));
        $date_until = trim((string) ($params->date_until ?? ''));
        $facility_id = isset($params->facility_id) && $params->facility_id !== '' ? (int) $params->facility_id : 0;

        if ($date_from !== '' && $date_until !== '' && $date_until < $date_from) {
            throw new InvalidArgumentException('La fecha hasta no puede ser anterior a la fecha desde.');
        }

        $repository = new reportsRepository();

        $results = $repository->getBonusesReport([
            'date_from' => $date_from,
            'date_until' => $date_until,
            'facility_id' => $facility_id
        ]);

        $facility_name = null;

        if ($facility_id > 0) {

            $facilities_model = new facilitiesModel();
            $facility = $facilities_model->findById($facility_id, false);

            if (!empty($facility)) {
                $facility_name = $facility['name'];
            }
        }

        $service = new reportsPdfService();

        $pdf_result = $service->generateBonusesReport(
                $results,
                [
                    'date_from' => $date_from,
                    'date_until' => $date_until,
                    'facility_id' => $facility_id,
                    'facility_name' => $facility_name
                ]
        );

        if (!$pdf_result['success']) {
            throw new RuntimeException($pdf_result['message']);
        }

        $this->extra = [
            'path' => $pdf_result['path'],
            'filename' => $pdf_result['filename']
        ];

        return $this->getJSONEncode(true);
    }

    /**
     * Print the js libraries
     * @param type $extra
     */
    protected function printFooterJs(string $extra='') {

        parent::printFooterJs($extra);
        
        $this->template_footer->assign('urlJsCntrl', $extra . _JS . 'panel/analytics.js'._ASSET_VERSION);
        
        $footerJs = $this->template_footer->fetch('common/panel/footer_js.html');	
	echo $footerJs;
    }
    
    protected function loadStatistics(stdClass $params) {

        $year = isset($params->year) ? (int) $params->year : (int) date('Y');
        $month = isset($params->month) && $params->month !== '' ? (int) $params->month : 0;
        $facility_id = isset($params->facility_id) && $params->facility_id !== '' ? (int) $params->facility_id : 0;

        $this->type_msg = 'INFO';

        try {

            if ($year < 2026 || $year > (int) date('Y')) {
                throw new InvalidArgumentException('El año seleccionado no es válido.');
            }

            if ($month < 0 || $month > 12) {
                throw new InvalidArgumentException('El mes seleccionado no es válido.');
            }

            $repository = new statisticsRepository();

            $result = $repository->getStatistics($year, $month, $facility_id);

            $this->extra = $result;

            return $this->getJSONEncode(true);
        } catch (Throwable $e) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = $e->getMessage();

            return $this->getJSONEncode(false);
        }
    }
    
    protected function generateReport(stdClass $params) {

        $report_type = strtoupper(trim((string) ($params->report_type ?? '')));

        $this->type_msg = 'INFO';

        try {

            switch ($report_type) {

                case 'RESERVATIONS':
                    return $this->generateReservationsReport($params);

                case 'INCOME':
                    return $this->generateIncomeReport($params);
                case 'ACCESSES':
                    return $this->generateAccessesReport($params);
                case 'CLIENTS':
                    return $this->generateClientsReport($params);
                case 'ORIGIN':
                    return $this->generateOriginReport($params);
                case 'BONUSES':

                    if (!defined('_BONUSES_ENABLED') || !_BONUSES_ENABLED) {
                        throw new RuntimeException('El módulo de bonos no está habilitado.');
                    }

                    return $this->generateBonusesReport($params);

                default:
                    throw new InvalidArgumentException('El tipo de informe no es válido.');
            }
        } catch (Throwable $e) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = $e->getMessage();

            return $this->getJSONEncode(false);
        }
    }

    protected function generateReportPdf(stdClass $params) {

        $report_type = strtoupper(trim((string) ($params->report_type ?? '')));

        try {

            switch ($report_type) {

                case 'RESERVATIONS':
                    return $this->generateReservationsReportPdf($params);

                case 'INCOME':
                    return $this->generateIncomeReportPdf($params);

                case 'ACCESSES':
                    return $this->generateAccessesReportPdf($params);

                case 'CLIENTS':
                    return $this->generateClientsReportPdf($params);

                case 'ORIGIN':
                    return $this->generateOriginReportPdf($params);

                case 'BONUSES':

                    if (!defined('_BONUSES_ENABLED') || !_BONUSES_ENABLED) {
                        throw new RuntimeException('El módulo de bonos no está habilitado.');
                    }

                    return $this->generateBonusesReportPdf($params);

                default:
                    throw new InvalidArgumentException('El tipo de informe no es válido.');
            }
        } catch (Throwable $e) {

            $this->control_request = 2;
            $this->type_msg = 'ERROR';
            $this->msg = $e->getMessage();

            return $this->getJSONEncode(false);
        }
    }
}
