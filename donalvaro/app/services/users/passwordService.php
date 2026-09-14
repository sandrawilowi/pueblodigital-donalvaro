<?php

declare(strict_types=1);

class passwordService
{

    public const MIN_SIZE = 8;
    public const SPECIAL_CHAR = '@#$%&+!=?';


    public function __construct()
    {

    }


    public function checkPassword(
        string $password,
        string $confirmPassword
    ): array {

        if ($password === '' || $confirmPassword === '') {
            return serviceResponse::error(
                'Debes introducir y confirmar la contraseña.',
                'PASSWORD_REQUIRED'
            );
        }

        if (!$this->isEqual($password, $confirmPassword)) {
            return serviceResponse::error(
                'Las contraseñas no coinciden.',
                'PASSWORDS_DO_NOT_MATCH'
            );
        }

        if (!$this->isLongEnough($password)) {
            return serviceResponse::error(
                'La contraseña debe tener al menos '
                . self::MIN_SIZE
                . ' caracteres.',
                'PASSWORD_TOO_SHORT'
            );
        }

        if (!$this->hasAtLeastOneLowercase($password)) {
            return serviceResponse::error(
                'La contraseña debe contener al menos una letra minúscula.',
                'PASSWORD_LOWERCASE_REQUIRED'
            );
        }

        if (!$this->hasAtLeastOneUppercase($password)) {
            return serviceResponse::error(
                'La contraseña debe contener al menos una letra mayúscula.',
                'PASSWORD_UPPERCASE_REQUIRED'
            );
        }

        if (!$this->hasAtLeastOneNumber($password)) {
            return serviceResponse::error(
                'La contraseña debe contener al menos un número.',
                'PASSWORD_NUMBER_REQUIRED'
            );
        }

        if (!$this->hasAtLeastOneSpecialChar($password)) {
            return serviceResponse::error(
                'La contraseña debe contener al menos un carácter especial de entre estos: '
                . self::SPECIAL_CHAR,
                'PASSWORD_SPECIAL_CHARACTER_REQUIRED'
            );
        }

        return serviceResponse::success(
            'La contraseña es válida.',
            'PASSWORD_VALID'
        );
    }


    public function generatePassword(
        int $passwordSize = self::MIN_SIZE
    ): string {

        if ($passwordSize < self::MIN_SIZE) {
            $passwordSize = self::MIN_SIZE;
        }

        /*
         * Añadimos un carácter obligatorio de cada grupo.
         */
        $passwordCharacters = [
            $this->getRandomCharacter('abcdefghijklmnopqrstuvwxyz'),
            $this->getRandomCharacter('ABCDEFGHIJKLMNOPQRSTUVWXYZ'),
            $this->getRandomCharacter('0123456789'),
            $this->getRandomCharacter(self::SPECIAL_CHAR)
        ];

        $allCharacters =
            'abcdefghijklmnopqrstuvwxyz'
            . 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
            . '0123456789'
            . self::SPECIAL_CHAR;

        while (count($passwordCharacters) < $passwordSize) {
            $passwordCharacters[] = $this->getRandomCharacter(
                $allCharacters
            );
        }

        /*
         * Mezclamos los caracteres generados.
         */
        for (
            $position = count($passwordCharacters) - 1;
            $position > 0;
            $position--
        ) {

            $randomPosition = random_int(0, $position);

            [
                $passwordCharacters[$position],
                $passwordCharacters[$randomPosition]
            ] = [
                $passwordCharacters[$randomPosition],
                $passwordCharacters[$position]
            ];
        }

        return implode('', $passwordCharacters);
    }


    public function encryptPassword(string $password): string
    {

        $passwordHash = password_hash(
            $password,
            PASSWORD_BCRYPT,
            [
                'cost' => PASSWORD_BCRYPT_DEFAULT_COST
            ]
        );

        if ($passwordHash === false) {
            throw new RuntimeException(
                'No se ha podido cifrar la contraseña.'
            );
        }

        return $passwordHash;
    }


    public function isEqual(
        string $passwordOne,
        string $passwordTwo
    ): bool {

        return $passwordOne === $passwordTwo;
    }


    public function isPasswordStrong(
        string $password
    ): bool {

        return $this->isLongEnough($password)
            && $this->hasAtLeastOneLowercase($password)
            && $this->hasAtLeastOneUppercase($password)
            && $this->hasAtLeastOneNumber($password)
            && $this->hasAtLeastOneSpecialChar($password);
    }


    public function isLongEnough(
        string $password,
        int $minSize = self::MIN_SIZE
    ): bool {

        return strlen($password) >= $minSize;
    }


    public function hasAtLeastOneLowercase(
        string $password
    ): bool {

        return $this->checkPattern('/[a-z]/', $password);
    }


    public function hasAtLeastOneUppercase(
        string $password
    ): bool {

        return $this->checkPattern('/[A-Z]/', $password);
    }


    public function hasAtLeastOneNumber(
        string $password
    ): bool {

        return $this->checkPattern('/[0-9]/', $password);
    }


    public function hasAtLeastOneSpecialChar(
        string $password
    ): bool {

        $specialCharacters = preg_quote(
            self::SPECIAL_CHAR,
            '/'
        );

        return $this->checkPattern(
            '/[' . $specialCharacters . ']/',
            $password
        );
    }


    public function getConsumerSecret(): string
    {

        return bin2hex(random_bytes(32));
    }


    public function getConsumerKey(): string
    {

        $clientId = $this->generateCode(20);

        return bin2hex($clientId);
    }


    private function checkPattern(
        string $pattern,
        string $password
    ): bool {

        return preg_match($pattern, $password) === 1;
    }


    private function getRandomCharacter(
        string $characters
    ): string {

        $position = random_int(
            0,
            strlen($characters) - 1
        );

        return $characters[$position];
    }


    private function generateCode(
        int $length
    ): string {

        $code = '';

        $characters =
            '1234567890'
            . 'abcdefghijklmnopqrstuvwxyz'
            . 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $maxPosition = strlen($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[
                random_int(0, $maxPosition)
            ];
        }

        return $code;
    }

}