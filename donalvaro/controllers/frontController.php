<?php

/**
 * Controller for redirect to web or panel.
 *
 * @author Wilowi - Sandra Campos
 * @since 06/07/2020
 *
 */

header('Cache-Control: post-check=0, pre-check=0', FALSE);
header('Cache-Control: no-store, no-cache, must-revalidate');
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Pragma: no-cache"); 
header("Strict-Transport-Security: max-age=63072000; includeSubDomains; preload");
header("X-Content-Type-Options: nosniff");

// ver si no afecta a lingro
header("X-Frame-Options: SAMEORIGIN"); // Evita ataques de clickjacking
header("X-XSS-Protection: 1; mode=block"); // Protección contra XSS


final class frontController {

    static function main() {
        
        global $CONFIG;
        $controllersMap = $CONFIG['routes'];

        $get = filter_input_array(INPUT_GET);
        //$get_post = filter_input_array(INPUT_POST);
        $type = "";

        if(isset($get['type'])){
            $type = $get['type'];
        }
 
        $control = isset($get['control']) ? $get['control'] : null;
        
        $extra_url = "";
        
        $Controller = new webController(); // Controlador por defecto
        foreach ($controllersMap as $controllerName => $routes) {
            if (in_array($type, $routes)) {
                $controllerClass = $controllerName . 'Controller';

                if (class_exists($controllerClass)) {
                    $Controller = new $controllerClass();
                }
                break;
            }
        }

        if(!empty($type)){
            $Controller->setPage($type);
        }
        
        if (!empty($control)){

            $Controller->setModelId($control);

            $extra_url = '../';
        }
        $Controller->main($extra_url);
        

    }
}
