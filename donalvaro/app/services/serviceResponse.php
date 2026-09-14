<?php

declare(strict_types=1);

/**
 * serviceResponse
 *
 * @author Sandra Campos
 * @copyright Wilowi
 * @version 1.0.0
 * @since 23 jul 2026
 */
class serviceResponse {
    
    public static function success(
        string $message = '',
        string $code = 'SUCCESS',
        array $data = []
    ): array {
        return [
            'success' => true,
            'code' => $code,
            'message' => $message,
            'data' => $data
        ];
    }
    
    public static function warning(
        string $message,
        string $code = 'WARNING',
        array $data = []
    ): array {
        return [
            'success' => false,
            'code' => $code,
            'message' => $message,
            'data' => $data
        ];
    }

    public static function error(
        string $message,
        string $code = 'ERROR',
        array $data = []
    ): array {
        return [
            'success' => false,
            'code' => $code,
            'message' => $message,
            'data' => $data
        ];
    }
    
}
