<?php

declare(strict_types=1);

abstract class baseModel
{
    use debugTrait;
    
    protected dbConnector $db;

    public function __construct(?dbConnector $db = null)
    {
        $this->db = $db ?? new dbConnector();
    }

    protected function db(): dbConnector
    {
        return $this->db;
    }

    public function findById(int $id, bool $asObject=false): array|object|null
    {
        $sql = 'SELECT * FROM ' . static::TABLE . ' WHERE ' . static::PRIMARY_KEY . ' = ?';
        
        return $this->db->selectOne($sql, [$id], $asObject);
    }

    protected function delete(string $where, array $params = []): bool
    {
        if (trim($where) === '') {
            throw new InvalidArgumentException('No se puede hacer DELETE sin WHERE');
        }

        return $this->db->execute(
            'DELETE FROM ' . static::TABLE . '
              WHERE ' . $where,
            $params
        );
    }

    public function deleteById(int $id): bool
    {
        return $this->delete(
            static::PRIMARY_KEY . ' = ?',
            [$id]
        );
    }

    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    public function commit(): void
    {
        $this->db->commit();
    }

    public function rollBack(): void
    {
        $this->db->rollBack();
    }
    
    public function getDb(): dbConnector
    {
        return $this->db;
    }
}