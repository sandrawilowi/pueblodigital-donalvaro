<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 13 sept 2026
 */

// Ejecutarlo una vez al día 00:10 / 00:15

include_once dirname(__FILE__).'/../donalvaro/config/includes.php';

$access_pins_service = new accessPinsService();
$logs = new logsModel('cron', 'expired_bonus_pins.log');

$result = $access_pins_service->revokeExpiredBonusPins();

$context = [
    'total' => $result['total'] ?? 0,
    'revoked' => $result['revoked'] ?? 0,
    'errors' => $result['errors'] ?? 0
];

if (!empty($result['error_details'])) {
    $context['error_details'] = $result['error_details'];
}

if ($result['success']) {
    $logs->info($result['message'], $context);
} else {
    $logs->error($result['message'], $context);
}

exit($result['success'] ? 0 : 1);