<?php
/**
 * Base Model
 */

class Model {
    /**
     * Database host (IP or hostname)
     * @var string
     */
    private $host;

    /**
     * Database name
     * @var string
     */
    private $db_name;

    /**
     * Database username
     * @var string
     */
    private $username;

    /**
     * Database password
     * @var string
     */
    private $password;

    /**
     * PDO connection instance
     * @var ?\PDO
     */
    private $conn = null;

    /**
     * Database port (default 3306)
     * @var string
     */
    private $port;

    /**
     * Optional placeholders used by some callers/tools - declared to avoid undefined property inspections
     * @var ?string
     */
    protected $sql = null;

    /**
     * Optional table name placeholder
     * @var ?string
     */
    protected $table = null;
    
    public function __construct() {
    $this->host = getenv('DB_HOST') ?: '127.0.0.1';
        $this->db_name = getenv('DB_NAME') ?: 'dst_recruitment';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') ?: '';
        $this->port = getenv('DB_PORT') ?: '3306';

        // If DB_HOST looks like a unix socket path (contains a slash) or is empty, prefer TCP localhost
        if ($this->host === '' || strpos($this->host, '/') !== false) {
            $this->host = '127.0.0.1';
        }

        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4";

        // Try to connect with a couple of retries for transient network issues on shared hosting
        $attempts = 0;
        $maxAttempts = 3;
        while ($attempts < $maxAttempts) {
            try {
                $this->conn = new PDO(
                    $dsn,
                    $this->username,
                    $this->password,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                break;
            } catch (PDOException $e) {
                $attempts++;
                if ($attempts >= $maxAttempts) {
                    // Provide a helpful message while still exposing the underlying error for admin debugging
                    die("Database connection failed after {$attempts} attempts: " . $e->getMessage());
                }
                // small backoff
                usleep(200000);
            }
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
