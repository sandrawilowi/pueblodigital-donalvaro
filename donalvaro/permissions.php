<?php

global $CONFIG;
$routes = $CONFIG['routes'];

return [
    'system' => [
        'allowed' => array_merge(
            $routes['panel'],
            $routes['reservas'],
            $routes['facilities'],
            $routes['bonus'],
            $routes['pins'],
            $routes['admin'],
            $routes['dispositivos'],
            $routes['usuarios'],
            $routes['analytics']
        )
    ],
    'manager' => [
        'allowed' => array_merge(            
            ['dashboard','perfil','historial-accesos','viewhistorial','documentacion'],
            $routes['reservas'],
            ['usuarios','clientes','editusuario','editcliente','auditusuario','auditcliente'],
            $routes['facilities'],
            $routes['bonus'],
            $routes['pins'],
            $routes['analytics']
        )
    ],
    'client' => [
        'allowed' => array_merge(
            ['dashboard','perfil','documentacion'],
            $routes['client']
        )
    ]
    
];
