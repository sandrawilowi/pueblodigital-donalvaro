<?php

declare(strict_types=1);

/**
 * auditLogsService
 *
 * Servicio encargado de registrar las acciones de auditoría
 * realizadas sobre los datos de la aplicación.
 *
 * @author Sandra Campos
 * @copyright Wilowi
 * @version 1.0.0
 * @since 7 ago 2026
 */
class auditLogsService {

    public const EVENT_INSERT = 'INSERT';
    public const EVENT_UPDATE = 'UPDATE';
    public const EVENT_DELETE = 'DELETE';

    /**
     * Valor utilizado para ocultar información sensible.
     */
    private const PROTECTED_VALUE = '[PROTECTED]';

    /**
     * Campos cuyo contenido nunca debe almacenarse
     * en los registros de auditoría.
     */
    private const SENSITIVE_FIELDS = [
        'password',
        'password_repeat',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'access_token',
        'refresh_token',
        'api_token',
        'api_key',
        'secret',
        'client_secret',
        'authorization',
    ];


    /**
     * Registra una inserción.
     *
     * @param string $table_name Nombre de la tabla afectada.
     * @param int $target_id ID del registro creado.
     * @param array|null $new_values Valores del nuevo registro.
     * @param string|null $function_name Función desde la que se realiza la acción.
     *
     * @return int
     */
    public function insert(
        string $table_name,
        int $target_id,
        ?array $new_values = null,
        ?string $function_name = null,
        ?string $event_description = null
    ): int {

        return $this->log(
            event: self::EVENT_INSERT,
            table_name: $table_name,
            target_id: $target_id,
            function_name: $function_name,
            event_description: $event_description,
            old_values: null,
            new_values: $new_values
        );
    }


    /**
     * Registra una actualización.
     *
     * Únicamente se almacenan los campos que realmente
     * han cambiado.
     *
     * Los campos incluidos en ignored_fields no se tendrán
     * en cuenta para determinar los cambios.
     *
     * @param string $table_name Nombre de la tabla afectada.
     * @param int $target_id ID del registro actualizado.
     * @param array $old_values Valores anteriores.
     * @param array $new_values Valores nuevos.
     * @param string|null $function_name Función desde la que se realiza la acción.
     * @param array $ignored_fields Campos que no deben auditarse.
     *
     * @return int
     */
    public function update(
        string $table_name,
        int $target_id,
        array $old_values,
        array $new_values,
        ?string $function_name = null,
        array $ignored_fields = [],
        ?string $event_description = null
    ): int {

        $changes = $this->getChanges(
            $old_values,
            $new_values,
            $ignored_fields
        );

        /*
         * Si después de comparar e ignorar los campos técnicos
         * no existe ningún cambio, no generamos auditoría.
         */
        if (
            empty($changes['old'])
            && empty($changes['new'])
        ) {
            return 0;
        }

        return $this->log(
            event: self::EVENT_UPDATE,
            table_name: $table_name,
            target_id: $target_id,
            function_name: $function_name,
            event_description: $event_description,
            old_values: $changes['old'],
            new_values: $changes['new']
        );
    }


    /**
     * Registra una eliminación.
     *
     * @param string $table_name Nombre de la tabla afectada.
     * @param int $target_id ID del registro eliminado.
     * @param array|null $old_values Valores anteriores.
     * @param string|null $function_name Función desde la que se realiza la acción.
     *
     * @return int
     */
    public function delete(
        string $table_name,
        int $target_id,
        ?array $old_values = null,
        ?string $function_name = null,
        ?string $event_description = null
    ): int {

        return $this->log(
            event: self::EVENT_DELETE,
            table_name: $table_name,
            target_id: $target_id,
            function_name: $function_name,
            event_description: $event_description,
            old_values: $old_values,
            new_values: null
        );
    }


    /**
     * Registra la acción en la tabla de auditoría.
     *
     * @param string $event Tipo de evento.
     * @param string $table_name Tabla afectada.
     * @param int|null $target_id ID del registro afectado.
     * @param string|null $function_name Función desde la que se realiza.
     * @param array|null $old_values Valores anteriores.
     * @param array|null $new_values Valores nuevos.
     *
     * @return int
     */
    private function log(
        string $event,
        string $table_name,
        ?int $target_id = null,
        ?string $function_name = null,
        ?string $event_description = null,
        ?array $old_values = null,
        ?array $new_values = null
    ): int {

        /*
         * Como última medida de seguridad saneamos siempre
         * la información antes de almacenarla.
         */
        $old_values = $this->sanitizeValues(
            $old_values
        );

        $new_values = $this->sanitizeValues(
            $new_values
        );

        $model = new auditLogsModel();

        $model->setUserId(
            $this->getUserId()
        );

        $model->setTargetId(
            $target_id
        );

        $model->setTableName(
            $table_name
        );

        $model->setEvent(
            $event
        );

        $model->setFunctionName(
            $function_name
        );

        $model->setEventDescription(
                $event_description
        );

        $model->setOldValues(
                $this->encodeValues(
                $old_values
            )
        );

        $model->setNewValues(
            $this->encodeValues(
                $new_values
            )
        );

        $model->setUrl(
            $this->getCurrentUrl()
        );

        $model->setIpAddress(
            getRealIP()
        );

        $model->setUserAgent(
            getUserAgent()
        );

        return $model->add();
    }


    /**
     * Obtiene únicamente los campos modificados.
     *
     * Solo se comparan los campos presentes en new_values,
     * ya que son los campos que forman parte de la operación
     * de actualización.
     *
     * Para los campos sensibles se compara el valor real,
     * pero únicamente se almacena [PROTECTED].
     *
     * @param array $old_values Valores anteriores.
     * @param array $new_values Nuevos valores a actualizar.
     * @param array $ignored_fields Campos que no deben auditarse.
     *
     * @return array
     */
    private function getChanges(
            array $old_values,
            array $new_values,
            array $ignored_fields = []
    ): array {

        $old = [];
        $new = [];

        /*
         * Solo recorremos los nuevos valores.
         *
         * Estos representan los campos que se están
         * intentando modificar.
         */
        foreach ($new_values as $key => $new_value) {

            /*
             * Campos que expresamente no queremos auditar.
             */
            if (
                    is_string($key) && in_array(
                            $key,
                            $ignored_fields,
                            true
                    )
            ) {
                continue;
            }

            /*
             * Obtenemos el valor anterior del campo.
             *
             * Si por algún motivo no existe en old_values
             * se considera null.
             */
            $old_value = array_key_exists(
                            $key,
                            $old_values
                    ) ? $old_values[$key] : null;

            /*
             * Si no ha cambiado, no se registra.
             */
            if ($old_value === $new_value) {
                continue;
            }

            /*
             * Para campos sensibles dejamos constancia
             * del cambio sin almacenar el contenido.
             */
            if ($this->isSensitiveField($key)) {

                $old[$key] = self::PROTECTED_VALUE;
                $new[$key] = self::PROTECTED_VALUE;

                continue;
            }

            $old[$key] = is_array($old_value) ? $this->sanitizeValues($old_value) : $old_value;

            $new[$key] = is_array($new_value) ? $this->sanitizeValues($new_value) : $new_value;
        }

        return [
            'old' => $old,
            'new' => $new
        ];
    }

    /**
     * Sustituye el contenido de todos los campos sensibles
     * por [PROTECTED].
     *
     * La comprobación es recursiva.
     *
     * @param array|null $values Valores a sanear.
     *
     * @return array|null
     */
    private function sanitizeValues(
        ?array $values
    ): ?array {

        if ($values === null) {
            return null;
        }

        foreach ($values as $key => $value) {

            if ($this->isSensitiveField($key)) {

                $values[$key] = self::PROTECTED_VALUE;

                continue;
            }

            if (is_array($value)) {

                $values[$key] = $this->sanitizeValues(
                    $value
                );
            }
        }

        return $values;
    }


    /**
     * Comprueba si un campo contiene información sensible.
     *
     * @param string|int $field Nombre del campo.
     *
     * @return bool
     */
    private function isSensitiveField(
        string|int $field
    ): bool {

        if (!is_string($field)) {
            return false;
        }

        return in_array(
            strtolower($field),
            self::SENSITIVE_FIELDS,
            true
        );
    }


    /**
     * Convierte los valores a JSON.
     *
     * @param array|null $values Valores a convertir.
     *
     * @return string|null
     */
    private function encodeValues(
        ?array $values
    ): ?string {

        if ($values === null) {
            return null;
        }

        return json_encode(
            $values,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );
    }


    /**
     * Obtiene el usuario autenticado desde la sesión.
     *
     * En procesos automáticos sin usuario devolverá null.
     *
     * @return int|null
     */
    private function getUserId(): ?int
    {
        if (
            isset($_SESSION['user_id'])
            && !empty($_SESSION['user_id'])
        ) {
            return intval(
                $_SESSION['user_id']
            );
        }

        return null;
    }

    /**
     * Obtiene la URL de la página desde la que
     * se ha realizado la acción.
     *
     * En peticiones AJAX se utiliza HTTP_REFERER,
     * ya que REQUEST_URI corresponde al controlador
     * que procesa la petición.
     *
     * @return string|null
     */
    private function getCurrentUrl(): ?string {
        $server = filter_input_array(INPUT_SERVER);

        /*
         * Página desde la que el usuario ha lanzado
         * la petición.
         */
        if (!empty($server['HTTP_REFERER'])) {
            return $server['HTTP_REFERER'];
        }

        /*
         * Si no existe Referer, utilizamos como fallback
         * la URL de la petición actual.
         */
        if (empty($server['HTTP_HOST'])) {
            return null;
        }

        $scheme = (
                !empty($server['HTTPS']) && $server['HTTPS'] !== 'off'
                ) ? 'https' : 'http';

        $uri = $server['REQUEST_URI'] ?? '';

        return $scheme
                . '://'
                . $server['HTTP_HOST']
                . $uri;
    }
}
