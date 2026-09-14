<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 14 sept 2026
 */
// Ejecutarlo cada 10 minutos

include_once dirname(__FILE__) . '/../donalvaro/config/includes.php';

$ttlock_service = new ttlockService();
$mail_service = new mailService();
$logs = new logsModel('cron', 'check_gateways.log');

$result = $ttlock_service->checkGatewaysStatus();

$context = [
    'total' => $result['total'] ?? 0,
    'online' => $result['online'] ?? 0,
    'offline' => $result['offline'] ?? 0,
    'changed' => $result['changed'] ?? 0,
    'disconnected' => $result['disconnected'] ?? [],
    'reconnected' => $result['reconnected'] ?? []
];

if ($result['success']) {

    foreach ($result['disconnected'] as $gateway) {

        $mail_result = $mail_service->sendGatewayOffline($gateway);

        if (!$mail_result['success']) {
            $logs->error(
                    'No se ha podido enviar el aviso de gateway desconectado.',
                    [
                        'gateway' => $gateway,
                        'mail_message' => $mail_result['message'] ?? ''
                    ]
            );
        }
    }

    foreach ($result['reconnected'] as $gateway) {

        $mail_result = $mail_service->sendGatewayOnline($gateway);

        if (!$mail_result['success']) {
            $logs->error(
                    'No se ha podido enviar el aviso de gateway reconectado.',
                    [
                        'gateway' => $gateway,
                        'mail_message' => $mail_result['message'] ?? ''
                    ]
            );
        }
    }

    $logs->info($result['message'], $context);

} else {

    $logs->error($result['message'], $context);
}

exit($result['success'] ? 0 : 1);