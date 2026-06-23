<?php
namespace App\Libraries;

use Config\Database;

class DatabaseWrapper
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function select(string $sql, array $params = []): array
    {
        $query = $this->db->query($sql, $params);
        return $query ? $query->getResultArray() : [];
    }

    public function row(string $sql, array $params = []): ?array
    {
        $query = $this->db->query($sql, $params);
        if (!$query) return null;
        $row = $query->getRowArray();
        return $row ?: null;
    }

    public function execute(string $sql, array $params = []): bool
    {
        return $this->db->query($sql, $params) !== false;
    }

    public function lastInsertId(): string
    {
        return (string) $this->db->insertID();
    }

    public function count(string $table, string $where = '1=1'): int
    {
        $query = $this->db->query("SELECT COUNT(*) as cnt FROM {$table} WHERE {$where}");
        if (!$query) return 0;
        $row = $query->getRowArray();
        return (int) ($row['cnt'] ?? 0);
    }
}
