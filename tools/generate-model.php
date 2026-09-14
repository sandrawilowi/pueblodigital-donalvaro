<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 2 jul 2026
 */

require_once __DIR__ . '/../data-config/config.php';
require_once __DIR__ . '/../donalvaro/autoload.php';

if ($argc < 2) {
    die("Uso: php tools/generate-model.php nombre_tabla\n");
}

$table = $argv[1];

$db = new dbConnector();

$database = $db->selectOne('SELECT DATABASE() AS db_name')['db_name'];

$columns = $db->select(
    'SELECT 
        COLUMN_NAME,
        DATA_TYPE,
        IS_NULLABLE,
        COLUMN_KEY,
        EXTRA,
        COLUMN_DEFAULT
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = ?
       AND TABLE_NAME = ?
     ORDER BY ORDINAL_POSITION',
    [$database, $table]
);

if (empty($columns)) {
    die("No se encontró la tabla {$table}\n");
}

$className = tableToClassName($table);
$primaryKey = getPrimaryKey($columns);

$outputDir = __DIR__ . '/../donalvaro/app/generated/models/';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0775, true);
}

$outputFile = $outputDir . $className . '.php';

$code = generateModelCode($className, $table, $primaryKey, $columns);

file_put_contents($outputFile, $code);

echo "Modelo generado correctamente:\n{$outputFile}\n";


function tableToClassName(string $table): string
{
    $table = preg_replace('/^wi_/', '', $table);

    $parts = explode('_', $table);

    $class = array_shift($parts);

    foreach ($parts as $part) {
        $class .= ucfirst($part);
    }

    return $class . 'Model';
}

function getPrimaryKey(array $columns): string
{
    foreach ($columns as $column) {
        if ($column['COLUMN_KEY'] === 'PRI') {
            return $column['COLUMN_NAME'];
        }
    }

    return 'id';
}

function dbTypeToPhpType(string $type, bool $nullable): string
{
    $phpType = match ($type) {
        'tinyint', 'smallint', 'mediumint', 'int', 'bigint' => 'int',
        'decimal', 'float', 'double' => 'float',
        default => 'string',
    };

    return $nullable ? '?' . $phpType : $phpType;
}

function defaultValue(array $column): string
{
    $nullable = $column['IS_NULLABLE'] === 'YES';
    $type = dbTypeToPhpType($column['DATA_TYPE'], false);
    $default = $column['COLUMN_DEFAULT'];

    if ($nullable) {
        return 'null';
    }

    if ($default !== null && strtoupper((string)$default) !== 'CURRENT_TIMESTAMP') {
        if ($type === 'int' || $type === 'float') {
            return (string)$default;
        }

        return "'" . addslashes((string)$default) . "'";
    }

    return match ($type) {
        'int' => '0',
        'float' => '0.0',
        default => "''",
    };
}

function columnToMethod(string $column): string
{
    $parts = explode('_', $column);
    $name = '';

    foreach ($parts as $part) {
        $name .= ucfirst($part);
    }

    return $name;
}

function generateModelCode(string $className, string $table, string $primaryKey, array $columns): string
{
    $properties = '';
    $getters = '';
    $setters = '';

    foreach ($columns as $column) {
        $name = $column['COLUMN_NAME'];
        $nullable = $column['IS_NULLABLE'] === 'YES';
        $type = dbTypeToPhpType($column['DATA_TYPE'], $nullable);
        $default = defaultValue($column);
        $method = columnToMethod($name);

        $properties .= "    private {$type} \${$name} = {$default};\n";

        $getters .= <<<PHP

    public function get{$method}(): {$type}
    {
        return \$this->{$name};
    }

PHP;

        $setters .= <<<PHP

    public function set{$method}({$type} \${$name}): void
    {
        \$this->{$name} = \${$name};
    }

PHP;
    }

    $insertColumns = array_filter($columns, function ($column) {
        return !str_contains($column['EXTRA'], 'auto_increment')
            && !in_array($column['COLUMN_NAME'], ['created_at', 'updated_at', 'deleted_at'], true);
    });

    $updateColumns = array_filter($columns, function ($column) use ($primaryKey) {
        return $column['COLUMN_NAME'] !== $primaryKey
            && !in_array($column['COLUMN_NAME'], ['created_at', 'updated_at', 'deleted_at'], true);
    });

    $insertFields = implode(",\n                ", array_column($insertColumns, 'COLUMN_NAME'));
    $insertPlaceholders = implode(',', array_fill(0, count($insertColumns), '?'));
    $insertParams = implode(",\n                ", array_map(
        fn($column) => '$this->' . $column['COLUMN_NAME'],
        $insertColumns
    ));

    $updateSet = implode(",\n                    ", array_map(
        fn($column) => $column['COLUMN_NAME'] . ' = ?',
        $updateColumns
    ));

    if (hasColumn($columns, 'updated_at')) {
        $updateSet .= ",\n                    updated_at = NOW()";
    }

    $updateParams = implode(",\n                ", array_map(
        fn($column) => '$this->' . $column['COLUMN_NAME'],
        $updateColumns
    ));

    if ($updateParams !== '') {
        $updateParams .= ",\n                ";
    }

    $updateParams .= '$this->' . $primaryKey;

    $findByEmail = '';

    if (hasColumn($columns, 'email')) {
        $findByEmail = <<<PHP

    public function findByEmail(string \$email): ?array
    {
        return \$this->db()->selectOne(
            'SELECT *
               FROM ' . self::TABLE . '
              WHERE email = ?
              LIMIT 1',
            [strtolower(trim(\$email))]
        );
    }

PHP;
    }

$softDelete = '';

if (hasColumn($columns, 'deleted_at')) {

    $statusLine = '';
    $statusValue = '';

    if (hasColumn($columns, 'status')) {
        $statusLine = "                    status = ?,\n";
        $statusValue = "                self::STATUS_DELETED,\n";
    }

    $updatedLine = hasColumn($columns, 'updated_at')
        ? "                    updated_at = NOW(),\n"
        : '';

    $softDelete = <<<PHP

    public function softDelete(): bool
    {
        if (empty(\$this->{$primaryKey})) {
            throw new RuntimeException('No se puede eliminar sin {$primaryKey}.');
        }

        return \$this->db()->execute(
            'UPDATE ' . self::TABLE . '
                SET
{$statusLine}{$updatedLine}                    deleted_at = NOW()
              WHERE {$primaryKey} = ?',
            [
{$statusValue}                \$this->{$primaryKey}
            ]
        );
    }

PHP;
    }

    $statusConstants = '';

    if (hasColumn($columns, 'status')) {
        $statusConstants = <<<PHP

    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_BLOCKED = 2;
    public const STATUS_DELETED = 3;

PHP;
    }

    return <<<PHP
<?php

declare(strict_types=1);

class {$className} extends baseModel
{
    protected const TABLE = '{$table}';
    protected const PRIMARY_KEY = '{$primaryKey}';
{$statusConstants}
{$properties}
{$findByEmail}
    public function add(): int
    {
        return \$this->db()->insert(
            'INSERT INTO ' . self::TABLE . '
            (
                {$insertFields}
            )
            VALUES
            (
                {$insertPlaceholders}
            )',
            [
                {$insertParams}
            ]
        );
    }

    public function update(): bool
    {
        if (empty(\$this->{$primaryKey})) {
            throw new RuntimeException('No se puede actualizar sin {$primaryKey}.');
        }

        return \$this->db()->execute(
            'UPDATE ' . self::TABLE . '
                SET
                    {$updateSet}
              WHERE {$primaryKey} = ?',
            [
                {$updateParams}
            ]
        );
    }
{$softDelete}
{$getters}
{$setters}
}

PHP;
}

function hasColumn(array $columns, string $name): bool
{
    foreach ($columns as $column) {
        if ($column['COLUMN_NAME'] === $name) {
            return true;
        }
    }

    return false;
}