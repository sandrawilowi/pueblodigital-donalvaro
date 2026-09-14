<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 20 jul 2026
 */

require_once __DIR__ . '/../donalvaro/config/includes.php';

$ttlock = new ttlockModel();

$gateways = $ttlock->listGateways();

var_dump($gateways);

$devices = $ttlock->listLocks();

var_dump($devices);

$device_gw = $ttlock->listGatewaysByLock(31341166);

var_dump($device_gw);

