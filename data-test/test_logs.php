<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 2 jul 2026
 */

include_once dirname(__FILE__).'/../data-config/config.php';
include_once dirname(__FILE__).'/../donalvaro/autoload.php';

$log = new logsModel('login', 'login.log');

$log->info('Intento de login', [
    'email' => 'sandra@wilowi.com',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
]);

$log->error('Error en login', [
    'exception' => "mensajito",
]);
