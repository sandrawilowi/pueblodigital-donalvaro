<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 20 jul 2026
 */

final class ttlockModel
{
    private const BASE_URL = 'https://euapi.ttlock.com';

    private string $clientId;
    private string $clientSecret;
    private string $username;
    private string $password;

    private ?string $accessToken = null;

    private int $connectTimeout = 10;
    private int $timeout = 30;

    public function __construct()
    {
        $this->clientId = _TTLOCK_CLIENTID;
        $this->clientSecret = _TTLOCK_SECRET;
        $this->username = _TTLOCK_USERNAME;
        $this->password = _TTLOCK_PASSWORD;
    }

    /**
     * Lista las cerraduras de la cuenta.
     */
    public function listLocks(
        int $pageNo = 1,
        int $pageSize = 100
    ): array {
        return $this->getJson('/v3/lock/list', [
            'pageNo' => $pageNo,
            'pageSize' => $pageSize,
        ]);
    }

    /**
     * Lista los gateways de la cuenta.
     */
    public function listGateways(
        int $pageNo = 1,
        int $pageSize = 100
    ): array {
        return $this->getJson('/v3/gateway/list', [
            'pageNo' => $pageNo,
            'pageSize' => $pageSize,
        ]);
    }
    
    /**
     * Lista los gateways asociados a una cerradura
     */
    public function listGatewaysByLock(int $lock_id): array {
        return $this->postForm(
                        '/v3/gateway/listByLock',
                        [
                            'lockId' => $lock_id,
                            'date' => $this->milliseconds(),
                        ]
                );
    }
    
    /**
     * Las cerraduras asociadas al gateway
     * @param int $gateway_id
     * @return array
     */
    public function listLocksByGateway(int $gateway_id): array
{
    return $this->postForm(
        '/v3/gateway/listLock',
        [
            'gatewayId' => $gateway_id,
            'date' => $this->milliseconds(),
        ]
    );
}

    /**
     * Abre una cerradura mediante gateway.
     */
    public function unlock(int $lockId): array
    {
        return $this->postForm('/v3/lock/unlock', [
            'lockId' => $lockId,
        ]);
    }

    /**
     * Crea un PIN personalizado mediante gateway.
     *
     * Tipos TTLock:
     * 1 = Un solo uso
     * 2 = Permanente
     * 3 = Periodo
     *
     * Las fechas deben recibirse en milisegundos.
     */
    public function addKeyboardPassword(
            int $lockId,
            string $keyboardPassword,
            string $keyboardPasswordName,
            int $keyboardPwdType,
            int $startDate,
            int $endDate
    ): array {

        if ($lockId <= 0) {
            throw new InvalidArgumentException(
                            'El identificador TTLock no es válido.'
                    );
        }

        if (empty($keyboardPassword)) {
            throw new InvalidArgumentException(
                            'El PIN no puede estar vacío.'
                    );
        }

        if (!in_array($keyboardPwdType, [1, 2, 3], true)) {
            throw new InvalidArgumentException(
                            'El tipo de PIN TTLock no es válido.'
                    );
        }

        if ($endDate <= $startDate) {
            throw new InvalidArgumentException(
                            'La fecha final debe ser posterior a la fecha inicial.'
                    );
        }

        return $this->postForm('/v3/keyboardPwd/add', [
                    'lockId' => $lockId,
                    'keyboardPwdName' => $keyboardPasswordName,
                    'keyboardPwdType' => $keyboardPwdType,
                    'keyboardPwd' => $keyboardPassword,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'addType' => 2,
        ]);
    }

    /**
     * Elimina un PIN mediante gateway.
     */
    public function deleteKeyboardPassword(
        int $lockId,
        int $keyboardPasswordId
    ): array {
        return $this->postForm('/v3/keyboardPwd/delete', [
            'lockId' => $lockId,
            'keyboardPwdId' => $keyboardPasswordId,
            'deleteType' => 2,
        ]);
    }
    
    public function renameLock(
            int $lock_id,
            string $lock_alias
    ): array {
        return $this->postForm(
                        '/v3/lock/rename',
                        [
                            'lockId' => $lock_id,
                            'lockAlias' => trim($lock_alias),
                            'date' => $this->milliseconds(),
                        ]
                );
    }

    public function renameGateway(
            int $gateway_id,
            string $gateway_name
    ): array {
        return $this->postForm(
                        '/v3/gateway/rename',
                        [
                            'gatewayId' => $gateway_id,
                            'gatewayName' => trim($gateway_name),
                            'date' => $this->milliseconds(),
                        ]
                );
    }
    
    

    /**
     * Cierra una cerradura remotamente mediante el gateway.
     *
     * @param int $lock_id Id de la cerradura en TTLock.
     *
     * @return array
     */
    public function lock(int $lock_id): array {
        return $this->postForm('/v3/lock/lock', [
            'lockId' => $lock_id,
        ]);
    }
    
    public function listKeyboardPwd(int $lock_id): array {

        return $this->postForm(
                        '/v3/lock/listKeyboardPwd',
                        [
                            'lockId' => $lock_id,
                            'pageNo' => 1,
                            'pageSize' => 100,
                            'date' => $this->milliseconds()
                        ]
                );
    }
    
    public function testAddKeyboardPassword(
            int $lockId,
            string $keyboardPassword,
            string $keyboardPasswordName,
            int $keyboardPwdType,
            int $startDate,
            int $endDate
    ): array {

        return $this->postForm('/v3/keyboardPwd/add', [
                    'lockId' => $lockId,
                    'keyboardPwdName' => $keyboardPasswordName,
                    'keyboardPwdType' => $keyboardPwdType,
                    'keyboardPwd' => $keyboardPassword,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'addType' => 2
        ]);
    }

    /**
     * Genera y guarda el access token en la instancia actual.
     */
    private function createToken(): void {
        $requestData = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'username' => $this->username,
            'password' => md5($this->password),
        ];

        $curl = curl_init(self::BASE_URL . '/oauth2/token');

        if ($curl === false) {
            throw new RuntimeException(
                'No se ha podido inicializar cURL.'
            );
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($requestData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->isProduction(),
            CURLOPT_SSL_VERIFYHOST => $this->isProduction() ? 2 : false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $response = $this->executeCurl($curl);

        if (
            empty($response['access_token'])
            || !is_string($response['access_token'])
        ) {
            throw new RuntimeException(
                'TTLock no ha devuelto un access token válido.'
            );
        }

        $this->accessToken = $response['access_token'];
    }

    /**
     * Comprueba que exista un access token.
     */
    private function ensureAccessToken(): void
    {
        if (empty($this->accessToken)) {
            $this->createToken();
        }
    }

    /**
     * Realiza una petición POST autenticada.
     */
    private function postForm(
        string $endpoint,
        array $data
    ): array {
        $this->ensureAccessToken();

        $requestData = $data;

        $requestData['clientId'] = $this->clientId;
        $requestData['accessToken'] = $this->accessToken;
        $requestData['date'] = $this->milliseconds();

        $curl = curl_init(self::BASE_URL . $endpoint);

        if ($curl === false) {
            throw new RuntimeException(
                'No se ha podido inicializar cURL.'
            );
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($requestData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->isProduction(),
            CURLOPT_SSL_VERIFYHOST => $this->isProduction() ? 2 : false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        return $this->executeCurl($curl);
    }

    /**
     * Realiza una petición GET autenticada.
     */
    private function getJson(
        string $endpoint,
        array $parameters
    ): array {
        $this->ensureAccessToken();

        $requestParameters = $parameters;

        $requestParameters['clientId'] = $this->clientId;
        $requestParameters['accessToken'] = $this->accessToken;
        $requestParameters['date'] = $this->milliseconds();

        $url = self::BASE_URL
            . $endpoint
            . '?'
            . http_build_query($requestParameters);

        $curl = curl_init($url);

        if ($curl === false) {
            throw new RuntimeException(
                'No se ha podido inicializar cURL.'
            );
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->isProduction(),
            CURLOPT_SSL_VERIFYHOST => $this->isProduction() ? 2 : false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
        ]);

        return $this->executeCurl($curl);
    }

    /**
     * Ejecuta la petición cURL y devuelve la respuesta decodificada.
     */
    private function executeCurl(CurlHandle $curl): array
    {
        $response = curl_exec($curl);

        if ($response === false) {
            $errorNumber = curl_errno($curl);
            $errorMessage = curl_error($curl);

            curl_close($curl);

            throw new RuntimeException(
                sprintf(
                    'Error cURL TTLock [%d]: %s',
                    $errorNumber,
                    $errorMessage
                )
            );
        }

        $httpCode = (int) curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

        curl_close($curl);

        $decodedResponse = json_decode($response, true);

        if (!is_array($decodedResponse)) {
            throw new RuntimeException(
                'TTLock ha devuelto una respuesta JSON no válida: '
                . $response
            );
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(
                sprintf(
                    'TTLock ha respondido con HTTP %d: %s',
                    $httpCode,
                    $response
                )
            );
        }

        $this->validateResponse($decodedResponse);

        return $decodedResponse;
    }

    /**
     * Comprueba los códigos de error propios de TTLock.
     */
    private function validateResponse(array $response): void
    {
        $errorCode = $response['errcode']
            ?? $response['errCode']
            ?? null;

        if ($errorCode === null || (int) $errorCode === 0) {
            return;
        }

        $errorMessage = $response['errmsg']
            ?? $response['errMsg']
            ?? 'Error desconocido';

        throw new RuntimeException(
            sprintf(
                'Error TTLock [%s]: %s',
                (string) $errorCode,
                (string) $errorMessage
            )
        );
    }

    /**
     * Devuelve la fecha actual en milisegundos.
     */
    private function milliseconds(): int
    {
        return (int) round(microtime(true) * 1000);
    }

    /**
     * Comprueba si la aplicación está en producción.
     */
    private function isProduction(): bool {
        return _ENVIRONMENT === 'production';
    }
    
    
}
