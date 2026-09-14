<?php

declare(strict_types=1);

class pinEncryptionService
{
    private const CIPHER = 'aes-256-gcm';

    private string $key;

    public function __construct()
    {
        $key = _PIN_ENCRYPTION_KEY ?? '';

        if ($key === '') {
            throw new RuntimeException(
                'No está definida PIN_ENCRYPTION_KEY.'
            );
        }

        /*
         * La clave se guarda en base64 en configuración
         * y aquí la convertimos a binario.
         */
        $decoded_key = base64_decode($key, true);

        if ($decoded_key === false || strlen($decoded_key) !== 32) {
            throw new RuntimeException(
                'PIN_ENCRYPTION_KEY no es una clave válida de 256 bits.'
            );
        }

        $this->key = $decoded_key;
    }

    public function encrypt(string $pin): string
    {
        if ($pin === '') {
            throw new InvalidArgumentException(
                'El PIN no puede estar vacío.'
            );
        }

        $iv_length = openssl_cipher_iv_length(self::CIPHER);

        $iv = random_bytes($iv_length);

        $tag = '';

        $encrypted = openssl_encrypt(
            $pin,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($encrypted === false) {
            throw new RuntimeException(
                'No se ha podido cifrar el PIN.'
            );
        }

        /*
         * Guardamos:
         *
         * IV + TAG + TEXTO CIFRADO
         */
        return $iv . $tag . $encrypted;
    }

    public function decrypt(string $encrypted_data): string
    {
        if ($encrypted_data === '') {
            throw new InvalidArgumentException(
                'El PIN cifrado no puede estar vacío.'
            );
        }

        $iv_length = openssl_cipher_iv_length(self::CIPHER);
        $tag_length = 16;

        if (
            strlen($encrypted_data)
            <= ($iv_length + $tag_length)
        ) {
            throw new RuntimeException(
                'El contenido cifrado no es válido.'
            );
        }

        $iv = substr(
            $encrypted_data,
            0,
            $iv_length
        );

        $tag = substr(
            $encrypted_data,
            $iv_length,
            $tag_length
        );

        $ciphertext = substr(
            $encrypted_data,
            $iv_length + $tag_length
        );

        $pin = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($pin === false) {
            throw new RuntimeException(
                'No se ha podido descifrar el PIN.'
            );
        }

        return $pin;
    }
}