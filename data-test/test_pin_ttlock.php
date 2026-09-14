<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 16 ago 2026
 */


require_once __DIR__ . '/../donalvaro/config/includes.php';

$ttlock_model = new ttlockModel();


//$response = $ttlock_model->listKeyboardPwd(31341166);

//echo "<pre>"; echo print_r($response); echo "</pre>";

$lock_id = 31341166;

/*$start_date = strtotime('2026-08-18 10:00:00') * 1000;
$end_date = strtotime('2026-08-23 10:00:00') * 1000;

$result = $ttlock_model->testAddKeyboardPassword(
        $lock_id,
        '735291',
        'TEST TEMPORAL',
        3,
        $start_date,
        $end_date
);

echo "<pre>"; echo print_r($result); echo "</pre>";*/

$response = $ttlock_model->listKeyboardPwd($lock_id);
echo "<pre>"; echo print_r($response); echo "</pre>";