<?php

/** * ********************************************************************
 * 	Autoincluding libraries when invoke them
 *
 * @author Wilowi - Sandra Campos
 * @copyright Wilowi
 * @since 12/06/2026
 *
 * ******************************************************************** */
require_once __DIR__ . '/vendor/autoload.php';

spl_autoload_register(function (string $className): void {

    $paths = [
        __DIR__ . '/config/',
        __DIR__ . '/controllers/',
        __DIR__ . '/app/models/',
        __DIR__ . '/app/models/users/',
        __DIR__ . '/app/models/roles/',
        __DIR__ . '/app/models/ttlock/',
        __DIR__ . '/app/models/facilities/',
        __DIR__ . '/app/models/billing/',
        __DIR__ . '/app/models/reservations/',
        __DIR__ . '/app/models/pins/',
        __DIR__ . '/app/models/bonus/',
        __DIR__ . '/app/repository/',
        __DIR__ . '/app/repository/users/',
        __DIR__ . '/app/repository/ttlock/',
        __DIR__ . '/app/repository/pins/',
        __DIR__ . '/app/repository/facility/',
        __DIR__ . '/app/repository/reservations/',
        __DIR__ . '/app/repository/analytics/',
        __DIR__ . '/app/repository/bonus/',
        __DIR__ . '/app/services/',
        __DIR__ . '/app/services/users/',
        __DIR__ . '/app/services/ttlock/',
        __DIR__ . '/app/services/pins/',
        __DIR__ . '/app/services/facility/',
        __DIR__ . '/app/services/reservations/',
        __DIR__ . '/app/services/analytics/',
        __DIR__ . '/app/services/bonus/',
        __DIR__ . '/app/traits/'
    ];

    foreach ($paths as $path) {
        $file = $path . $className . '.php';

        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

