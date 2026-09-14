<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 13 sept 2026
 */

// Ejecutarlo cada 15 minutos

include_once dirname(__FILE__).'/../donalvaro/config/includes.php';

$users_service = new usersService();
$logs = new logsModel('cron', 'unlock_users.log');

$result = $users_service->unlockBlockedUsers();

$context = [
    'total' => $result['total'] ?? 0,
    'unlocked' => $result['unlocked'] ?? 0,
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