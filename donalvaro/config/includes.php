<?php

$CONFIG = [];

// ----- Inclusiones básicas
include_once dirname(__FILE__) . '/../../data-config/config.php';
include_once dirname(__FILE__) . '/../autoload.php';

include_once dirname(__FILE__) . '/functions/common.php';

$CONFIG['routes'] = require dirname(__FILE__) . '/../routes.php';
$CONFIG['permissions'] = require dirname(__FILE__) . '/../permissions.php';
