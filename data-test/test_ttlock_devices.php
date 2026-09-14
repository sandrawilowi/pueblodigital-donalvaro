<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 24 jul 2026
 */

require_once __DIR__ . '/../donalvaro/config/includes.php';

$service = new ttlockService();

$model = new ttlockModel();

try {
    $locks = $model->listLocks();

    var_dump($locks);
    
    $locks = $model->listGateways();

    var_dump($locks);
} catch (Throwable $e) {
    echo $e->getMessage();
}