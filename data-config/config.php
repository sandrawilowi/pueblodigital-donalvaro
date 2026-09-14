<?php

/*
 * Environment
 */
define('_ENVIRONMENT', 'local');

/*
 * Root directories
 */
define('_PROJECT_ROOT', dirname(__DIR__));
define('_PUBLIC_ROOT', _PROJECT_ROOT . '/donalvaro');

/*
 * Environment configuration
 */
switch (_ENVIRONMENT)
{
    case 'local':

        define('_URL_ENVIRONMENT', 'http://localhost/pueblodigital-donalvaro/donalvaro/');
        define('_URL_LOGS', _PROJECT_ROOT . '/data-logs/');
        define('_URL_MAIL', _PUBLIC_ROOT . '/');
        define('_FILE_DATA_DB', _PROJECT_ROOT . '/data-db/db_local.config');
        define('_APP_DEBUG', true);

        break;

    case 'develop':

        define('_URL_ENVIRONMENT', 'https://develop.pueblodigital.es/');
        define('_URL_LOGS', _PROJECT_ROOT . '/data-logs/');
        define('_URL_MAIL', _PUBLIC_ROOT . '/');
        define('_FILE_DATA_DB', _PROJECT_ROOT . '/data-db/db_dev.config');
        define('_APP_DEBUG', true);

        break;

    case 'production':

        define('_URL_ENVIRONMENT', 'https://donalvaro.pueblodigital.es/');
        define('_URL_LOGS', _PROJECT_ROOT . '/data-logs/');
        define('_URL_MAIL', _PUBLIC_ROOT . '/');
        define('_FILE_DATA_DB', _PROJECT_ROOT . '/data-db/db_prod.config');
        define('_APP_DEBUG', false);

        break;

    default:

        die('Entorno no válido');
}

/*
 * Security
 */
define('_KEY_CAPTCHA', '');
define('_KEY_MASTER',_PROJECT_ROOT . '/data-db/master.config');

/*
 * PHPMailer
 */

if (_ENVIRONMENT === 'production') {

    define('_PHPMAILER_HOST', 'smtp.ionos.es');
    define('_PHPMAILER_USERNAME', 'donalvaro@pueblodigital.es');
    define('_PHPMAILER_PASSWORD', '34:Rr4jjUdf$Msp9vjd');
    define('_PHPMAILER_FROM', 'donalvaro@pueblodigital.es');
    define('_PHPMAILER_PORT', 587);
    define('_PHPMAILER_ENCRYPTION', 'tls');
} else {

    define('_PHPMAILER_HOST', 'sandbox.smtp.mailtrap.io');
    define('_PHPMAILER_USERNAME', '538bc609b83df4');
    define('_PHPMAILER_PASSWORD', 'a2ff7fb5e162f9');
    define('_PHPMAILER_PORT', 2525);
    define('_PHPMAILER_ENCRYPTION', 'tls');
    define('_PHPMAILER_FROM', 'donalvaro@pueblodigital.es');
}


/*
 * Directories
 */
define('_CONFIG', 'config/');
define('_CONTROLLERS', 'controllers/');
define('_ASSETS', 'assets/');
define('_CSS', 'assets/css/');
define('_JS', 'assets/js/');
define('_IMAGES', 'assets/images/');
define('_PLUGINS', 'assets/plugins/');
define('_DOCUMENTS', 'documents/');
define('_VENDOR', 'vendor/');
define('_MODELS', 'app/models/');
define('_REPOSITORY', 'app/repository/');
define('_SERVICES', 'app/services/');
define('_VIEWS', 'views/');

/*
 * External data directories
 */
define('_TEST', _PROJECT_ROOT . '/data-test/');
define('_LOG', _PROJECT_ROOT . '/data-logs/');
define('_DB', _PROJECT_ROOT . '/data-db/');

/*
 * General configuration
 */
define('_TITLE', 'Don Álvaro Digital | Gestión municipal inteligente');
define('_ROBOTS_TRUE', 'Index,Follow');
define('_ROBOTS_FALSE', 'NoIndex,NoFollow');

/*
 * Asset version
 */
define('_VERSION', '1.0.0');
define('_ASSET_VERSION', '?v='._VERSION);

/*
 * Timezone
 */
date_default_timezone_set('Europe/Madrid');

/*
 * Módulos
 */
define('_BONUSES_ENABLED', true);

/*
 * Pin 
 */
define ('_PIN_ENCRYPTION_KEY','2alObUHOu547kmNeRqnLnr2mpxkwNX81BvjugkUic+g=');

/*
 * Alertas
 */
define('_SYSTEM_ALERT_EMAIL', 'sandra@wilowi.com');
define('_SYSTEM_ALERT_NAME', 'Pueblo Digital Don Álvaro - Alertas');

/**
 * TTLock
 */
define('_TTLOCK_CLIENTID', '19d3a0ec6f5442faa090c6ab348f0b68');
define('_TTLOCK_SECRET', '08e5c5ae427a3aa60875f30552654ad8');
define('_TTLOCK_CALLBACK_URL', 'api/ttlock/webhook');
define('_TTLOCK_USERNAME', 'donalvaro@pueblodigital.es');
define('_TTLOCK_PASSWORD', 'i7!zbHe-qy5=Y#o');
