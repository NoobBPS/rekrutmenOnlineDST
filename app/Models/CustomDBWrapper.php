<?php
namespace App\Models;

class CustomDBWrapper {
    private $db;

    public function __construct() {
        $this->db = \Config\Database::connect();
    }

    public function select(string $sql, array $params = []): array {
        $query = $this->db->query($sql, $params);
        return $query->getResultArray();
    }

    public function row(string $sql, array $params = []): ?array {
        $query = $this->db->query($sql, $params);
        $result = $query->getRowArray();
        return empty($result) ? null : $result;
    }

    public function execute(string $sql, array $params = []): bool {
        return (bool) $this->db->query($sql, $params);
    }

    public function lastInsertId(): string {
        return (string) $this->db->insertID();
    }

    public function count(string $table, string $where = '1=1'): int {
        $query = $this->db->query("SELECT COUNT(*) as cnt FROM $table WHERE $where");
        return (int) ($query->getRowArray()['cnt'] ?? 0);
    }
}
