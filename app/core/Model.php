<?php
/**
 * Base Model
 */

class Model {
    /**
     * Database host (IP or hostname)
     * @var string
     */
    private string $host;

    /**
     * Database name
     * @var string
     */
    private string $db_name;

    /**
     * Database username
     * @var string
     */
    private string $username;

    /**
     * Database password
     * @var string
     */
    private string $password;

    /**
     * PDO connection instance
     * @var ?\PDO
     */
    private ?PDO $conn = null;

    /**
     * Database port (default 3306)
     * @var string
     */
    private string $port;

    /**
     * Optional placeholders used by some callers/tools — declared to avoid undefined property inspections
     * @var ?string
     */
    protected ?string $sql = null;

    /**
     * Optional table name placeholder
     * @var ?string
     */
    protected ?string $table = null;
    
    public function __construct() {
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db_name = getenv('DB_NAME') ?: 'dst_recruitment';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') ?: '';
        $this->port = getenv('DB_PORT') ?: '3306';

        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4";
            $this->conn = new PDO(
                $dsn,
                $this->username,
                $this->password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function select(string $sql, array $params = []): array {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function row(string $sql, array $params = []): ?array {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }
    
    public function execute(string $sql, array $params = []): bool {
        $stmt = $this->conn->prepare($sql);
        return (bool) $stmt->execute($params);
    }
    
    public function lastInsertId(): string {
        return $this->conn->lastInsertId();
    }
    
    public function count(string $table, string $where = '1=1'): int {
        $stmt = $this->conn->query("SELECT COUNT(*) as cnt FROM $table WHERE $where");
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
    }
}

function db() {
    static $model = null;
    if ($model === null) {
        $model = new Model();
    }
    return $model;
}
