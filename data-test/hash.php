<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 17 jun 2026
 */

$password_encrypt = 'ds43f8as#jjwDSS$';
$costt = array('cost' => PASSWORD_BCRYPT_DEFAULT_COST);
$password_encrypt_aux = password_hash($password_encrypt, PASSWORD_BCRYPT, $costt);

echo $password_encrypt_aux;