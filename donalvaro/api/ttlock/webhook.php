<?php

declare(strict_types=1);

/**
 * Callback de pruebas de TTLock.
 *
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 16 jul 2026
 */

require_once __DIR__ . '/../../config/includes.php';

header('Content-Type: application/json; charset=utf-8');

$raw_body = file_get_contents('php://input');

if ($raw_body === false) {
    $raw_body = '';
}

/*
 * Datos del servidor.
 */
$request_method = (string) filter_input(INPUT_SERVER,'REQUEST_METHOD',FILTER_UNSAFE_RAW);
$remote_addr = (string) filter_input(INPUT_SERVER,'REMOTE_ADDR',FILTER_UNSAFE_RAW);
$request_uri = (string) filter_input(INPUT_SERVER,'REQUEST_URI',FILTER_UNSAFE_RAW);
$content_type = (string) filter_input(INPUT_SERVER,'CONTENT_TYPE',FILTER_UNSAFE_RAW);
$user_agent = (string) filter_input(INPUT_SERVER,'HTTP_USER_AGENT',FILTER_UNSAFE_RAW);

/*
 * Datos enviados por TTLock.
 */
$post = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);

if (!is_array($post)) {
    $post = [];
}

/*
 * Cabeceras.
 */
$headers = [];

if (function_exists('getallheaders')) {
    $headers = getallheaders();
}

$callback_data = [
    'received_at' => date('Y-m-d H:i:s'),
    'request_method' => $request_method,
    'remote_addr' => $remote_addr,
    'request_uri' => $request_uri,
    'content_type' => $content_type,
    'user_agent' => $user_agent,
    'headers' => $headers,
    'post' => $post,
    'raw_body' => $raw_body,
];


    $log = new logsModel(
        'ttlock',
        'callback-webhook.log'
    );

try {
    $message = json_encode($callback_data,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($message === false) {
        throw new RuntimeException(
            'No se ha podido codificar el callback: '
            . json_last_error_msg()
        );
    }


    $log->info($message);

} catch (Throwable $e) {
    error_log(
        'Error procesando callback TTLock: '
        . $e->getMessage()
    );
}

/*
 * Procesamiento webhook
 */

try {

    $service = new ttlockWebhookService();
    $service->process($post);

} catch (Throwable $e) {

     $error = [
        'received_at' => date('Y-m-d H:i:s'),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];

    $message = json_encode(
            $error,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($message !== false) {
        $log->error($message);
    }

    error_log(
            'Error procesando callback TTLock: '
            . $e->getMessage()
    );
}


/*
 * Respondemos correctamente a TTLock.
 */
http_response_code(200);

echo json_encode([
    'success' => true
]);
