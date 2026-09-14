<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 17 jun 2026
 */

include_once dirname(__FILE__).'/../donalvaro/config/includes.php';

$db = new dbConnector();

echo "<pre>";
echo print_r($db);
echo "</pre>";

$result = $db->select("SHOW DATABASES");

echo "<pre>";
echo print_r($result);
echo "</pre>";

$tables = $db->select("SHOW TABLES");

echo "<pre>";
echo print_r($tables);
echo "</pre>";