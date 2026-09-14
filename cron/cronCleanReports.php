<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 13 sept 2026
 */

// Ejecutarlo una vez a la semana

include_once dirname(__FILE__).'/../donalvaro/config/includes.php';

$logs = new logsModel('cron', 'clean_reports.log');

$reports_path = dirname(__FILE__).'/../donalvaro/assets/reports';

$deleted = 0;
$errors = 0;
$error_details = [];

try {

    if (!is_dir($reports_path)) {
        throw new RuntimeException('No existe el directorio de informes.');
    }

    $files = scandir($reports_path);

    if ($files === false) {
        throw new RuntimeException('No se ha podido leer el directorio de informes.');
    }

    foreach ($files as $file) {

        if ($file === '.' || $file === '..') {
            continue;
        }

        $file_path = $reports_path . DIRECTORY_SEPARATOR . $file;

        /*
         * Solo eliminamos ficheros.
         * Nunca directorios que pudieran existir dentro de reports.
         */
        if (!is_file($file_path)) {
            continue;
        }

        if (unlink($file_path)) {

            $deleted++;

        } else {

            $errors++;

            $error_details[] = [
                'file' => $file
            ];
        }
    }

    $context = [
        'deleted' => $deleted,
        'errors' => $errors
    ];

    if (!empty($error_details)) {
        $context['error_details'] = $error_details;
    }

    if ($errors > 0) {

        $logs->error(
                'Limpieza de informes finalizada con errores.',
                $context
        );

        exit(1);
    }

    $logs->info(
            'Limpieza de informes finalizada correctamente.',
            $context
    );

    exit(0);

} catch (Throwable $e) {

    $logs->error(
            'Error al limpiar los informes.',
            [
                'message' => $e->getMessage()
            ]
    );

    exit(1);
}