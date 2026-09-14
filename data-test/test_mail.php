<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 23 jul 2026
 */

include_once dirname(__FILE__).'/../data-config/config.php';
include_once dirname(__FILE__).'/../donalvaro/autoload.php';

try {
    $mailer = new customMailer();

    /*
     * Activar solo durante las pruebas.
     */
    //$mailer->enableDebug();

    $mailer->setAddress([
        [
            'email' => 'sandra@wilowi.com',
            'name' => 'Sandra'
        ]
    ]);

    $sent = $mailer->sendStandardMail(
        'Prueba de correo - Pueblo Digital',
        '
            <div style="
                max-width:600px;
                margin:0 auto;
                padding:24px;
                font-family:Arial,sans-serif;
                color:#0f172a;
            ">
                <h2 style="color:#2563eb;">
                    Prueba de correo
                </h2>

                <p>
                    Este mensaje confirma que la configuración SMTP
                    de Pueblo Digital funciona correctamente.
                </p>

                <p>
                    Fecha de envío:
                    <strong>' . date('d/m/Y H:i:s') . '</strong>
                </p>
            </div>
        ',
        'Prueba de correo de Pueblo Digital realizada correctamente.'
    );

    echo '<pre>';

    if ($sent) {
        echo "Correo enviado correctamente.\n";
    } else {
        echo "No se ha podido enviar el correo.\n";
        echo "Consulta el log de PHPMailer y customMail.log.\n";
    }

    echo '</pre>';

} catch (Throwable $e) {
    echo '<pre>';
    echo "EXCEPCIÓN\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo '</pre>';
}