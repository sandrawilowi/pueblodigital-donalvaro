<?php

/**
 * Control Ajax
 *
 * @author Wilowi - Sandra Campos
 * @since 06/07/2020
 *
 */

// ----- Tipo de salida => JSON
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', FALSE);
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf8');

ini_set('display_errors', 0);

include_once dirname(__FILE__) . '/../config/includes.php';

//if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') { // SSL connection
$json = '';
$result = '';
$files = array();

$server = filter_input(INPUT_SERVER,'REQUEST_METHOD',FILTER_UNSAFE_RAW);

if (($server === 'POST')) {

    $post = filter_input_array(INPUT_POST);

    if (!empty($post) || !empty($_FILES)) {
        $values = (object) $post;
        $files = $_FILES;
    } else {
        $json = file_get_contents('php://input');
        $values = json_decode($json);
    }

    $controller = null;
    try {
        $controllerName = $values->controller ?? 'webController';
        if (class_exists($controllerName)) {
            $controller = new $controllerName();
        } else {
            $controller = new webController();
        }

        if ($controller) {
            echo $controller->doAction($values, $files);
        }
    } catch (Throwable $e) {
        $result = '({"resultado":"","error_code":"' . $e->getCode() . '","error_msg":"' . $e->getMessage() . '","POST":' . json_encode($post) . '})';
        echo $result;
    }
}

//}
