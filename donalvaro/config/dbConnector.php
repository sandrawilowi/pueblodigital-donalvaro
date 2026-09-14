<?php

declare(strict_types=1);

class dbConnector
{
    private mysqli $connection;

    public function __construct()
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $config = $this->loadConfig();

        $this->connection = mysqli_init();

        $this->connection->ssl_set(
                $config['ssl_key'],
                $config['ssl_cert'],
                $config['ssl_ca'],
                null,
                null
        );

        $this->connection->real_connect(
                $config['server'], 
                $config['usuario'],
                $config['password'],
                $config['name'],
                intval($config['port'])
        );

        $this->connection->set_charset('utf8mb4');
    }

    public function __destruct()
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    private function loadConfig(): array
    {
        if (!is_file(_FILE_DATA_DB)) {
            throw new RuntimeException('No existe el fichero de configuración de base de datos');
        }

        $content = file_get_contents(_FILE_DATA_DB);

        if ($content === false) {
            throw new RuntimeException('No se pudo leer el fichero de configuración de base de datos');
        }

        $decoded = base64_decode($content, true);

        if ($decoded === false) {
            throw new RuntimeException('El fichero de configuración de base de datos no es base64 válido');
        }

        $config = json_decode($decoded, true);

        if (!is_array($config)) {
            throw new RuntimeException('El fichero de configuración de base de datos no contiene JSON válido');
        }

        foreach (['server', 'usuario', 'password', 'name'] as $key) {
            if (!array_key_exists($key, $config)) {
                throw new RuntimeException("Falta el campo {$key} en la configuración de base de datos");
            }
        }

        return $config;
    }

    public function select(string $sql, array $params = [], bool $asObject = false): array
    {
        $stmt = $this->connection->prepare($sql);

        if (!empty($params)) {
            $this->bindParams($stmt, $params);
        }

        $stmt->execute();

        $result = $stmt->get_result();

        if (!$asObject) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }

        $rows = [];

        while ($row = $result->fetch_object()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function selectOne(string $sql, array $params = [], bool $asObject = false): array|object|null
    {
        $rows = $this->select($sql, $params, $asObject);

        return $rows[0] ?? null;
    }

    public function insert(string $sql, array $params = []): int
    {
        $stmt = $this->connection->prepare($sql);

        if (!empty($params)) {
            $this->bindParams($stmt, $params);
        }

        $stmt->execute();

        return $this->connection->insert_id;
    }

    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->connection->prepare($sql);

        if (!empty($params)) {
            $this->bindParams($stmt, $params);
        }

        $stmt->execute();

        return true;
    }

    public function beginTransaction(): void
    {
        $this->connection->begin_transaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollBack(): void
    {
        $this->connection->rollback();
    }

    public function getConnection(): mysqli
    {
        return $this->connection;
    }

    private function bindParams(mysqli_stmt $stmt, array $params): void
    {
        $types = '';

        foreach ($params as $param) {
            $types .= match (true) {
                is_int($param) => 'i',
                is_float($param) => 'd',
                is_null($param) => 's',
                default => 's',
            };
        }

        $stmt->bind_param($types, ...$params);
    }
}