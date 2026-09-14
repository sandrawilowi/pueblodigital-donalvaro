<?php

class CoachValidator
{
    public function validateCreate(array $input): ?array
    {
        $errors = [];

        if (empty($input['name']) || !is_string($input['name']) || trim($input['name']) === '') {
            $errors[] = 'Invalid or missing name.';
        }

        if (empty($input['lastname']) || !is_string($input['lastname']) || trim($input['lastname']) === '') {
            $errors[] = 'Invalid or missing lastname.';
        }

        if (empty($input['email']) || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid or missing email.';
        }

        if (empty($input['password'])) {
            $errors[] = 'Missing password.';
        } else {
            $passwordError = null;
            if (!$this->isValidPassword($input['password'], $passwordError)) {
                $errors[] = $passwordError;
            }
        }

        if (!isset($input['language_id']) || !is_numeric($input['language_id']) || $input['language_id'] <= 0) {
            $errors[] = 'Invalid or missing language_id.';
        }

        if (!isset($input['country_id']) || !is_numeric($input['country_id']) || $input['country_id'] <= 0) {
            $errors[] = 'Invalid or missing country_id.';
        }

        if (!isset($input['time_zone_id']) || !is_numeric($input['time_zone_id']) || $input['time_zone_id'] <= 0) {
            $errors[] = 'Invalid or missing time_zone_id.';
        }

        if (isset($input['phone']) && !$this->isValidPhone($input['phone'])) {
            $errors[] = 'Invalid phone number.';
        }

        if (isset($input['whatsapp']) && !$this->isValidPhone($input['whatsapp'])) {
            $errors[] = 'Invalid whatsapp number.';
        }

        return !empty($errors) ? $errors : null;
    }

    public function validateUpdate(array $input): ?array
    {
        $errors = [];

        if (empty($input)) {
            return ['No input data provided.'];
        }

        if (!isset($input['name'])
            && !isset($input['lastname'])
            && !isset($input['country_id'])
            && !isset($input['country_live_id'])
            && !isset($input['phone'])
            && !isset($input['whatsapp'])) {
            return ['At least one of name, lastname, country_id, country_live_id, phone or whatsapp must be provided.'];
        }

        if (isset($input['name']) && (!is_string($input['name']) || trim($input['name']) === '')) {
            $errors[] = 'Invalid name.';
        }

        if (isset($input['lastname']) && (!is_string($input['lastname']) || trim($input['lastname']) === '')) {
            $errors[] = 'Invalid lastname.';
        }

        if (isset($input['country_id']) && (!is_numeric($input['country_id']) || $input['country_id'] <= 0)) {
            $errors[] = 'Invalid country_id.';
        }

        if (isset($input['country_live_id']) && (!is_numeric($input['country_live_id']) || $input['country_live_id'] <= 0)) {
            $errors[] = 'Invalid country_live_id.';
        }

        if (isset($input['phone']) && !$this->isValidPhone($input['phone'])) {
            $errors[] = 'Invalid phone number.';
        }

        if (isset($input['whatsapp']) && !$this->isValidPhone($input['whatsapp'])) {
            $errors[] = 'Invalid whatsapp number.';
        }

        return !empty($errors) ? $errors : null;
    }

    private function isValidPassword(string $password, ?string &$errorMessage = null): bool
    {
        if (strlen($password) < 8) {
            $errorMessage = 'Password must be at least 8 characters long.';
            return false;
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errorMessage = 'Password must include at least one uppercase letter.';
            return false;
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errorMessage = 'Password must include at least one lowercase letter.';
            return false;
        }

        if (!preg_match('/\d/', $password)) {
            $errorMessage = 'Password must include at least one number.';
            return false;
        }

        if (!preg_match('/[@$!%*?&#]/', $password)) {
            $errorMessage = 'Password must include at least one special character.';
            return false;
        }

        return true;
    }

    private function isValidPhone($phone): bool
    {
        return is_numeric($phone) && strlen((string) $phone) >= 7 && strlen((string) $phone) <= 15;
    }
}
