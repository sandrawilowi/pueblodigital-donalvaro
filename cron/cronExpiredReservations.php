<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 13 sept 2026
 */
// Ejecutarlo cada 2 horas

include_once dirname(__FILE__) . '/../donalvaro/config/includes.php';

$reservations_service = new reservationsService();
$logs = new logsModel('cron', 'expired_reservations.log');

$result = $reservations_service->completeExpiredReservations();

$context = [
    'total' => $result['total'] ?? 0,
    'completed' => $result['completed'] ?? 0,
    'no_show' => $result['no_show'] ?? 0,
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
