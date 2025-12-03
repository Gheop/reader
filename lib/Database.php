<?php
/**
 * Database Abstraction Layer for Gheop Reader
 *
 * Provides:
 * - Singleton connection management
 * - Safe prepared statements with auto-type detection
 * - Transaction support
 * - Batch operations
 * - Slow query logging
 */

class Database {
    private static ?Database $instance = null;
    private mysqli $connection;
    private bool $debugMode = false;
    private int $slowQueryThreshold = 100; // ms

    /**
     * Private constructor - use getInstance()
     */
    private function __construct() {
        $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($this->connection->connect_error) {
            error_log("Database connection failed: " . $this->connection->connect_error);
            throw new Exception("Database connection failed");
        }

        $this->connection->set_charset("utf8mb4");
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /**
     * Get the raw mysqli connection (for legacy code compatibility)
     */
    public function getConnection(): mysqli {
        return $this->connection;
    }

    /**
     * Execute a prepared statement and return results
     *
     * @param string $sql SQL query with ? placeholders
     * @param array $params Parameters to bind
     * @return mysqli_result|bool Result set or boolean for INSERT/UPDATE/DELETE
     */
    public function query(string $sql, array $params = []): mysqli_result|bool {
        $start = microtime(true);

        if (empty($params)) {
            $result = $this->connection->query($sql);
        } else {
            $stmt = $this->connection->prepare($sql);

            if (!$stmt) {
                error_log("SQL prepare error: " . $this->connection->error . " | Query: " . $sql);
                throw new Exception("Query preparation failed: " . $this->connection->error);
            }

            $types = $this->detectTypes($params);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            if ($stmt->error) {
                error_log("SQL execute error: " . $stmt->error . " | Query: " . $sql);
                throw new Exception("Query execution failed: " . $stmt->error);
            }

            $result = $stmt->get_result();

            // For INSERT/UPDATE/DELETE, return true on success
            if ($result === false && $stmt->affected_rows >= 0) {
                $result = true;
            }

            $stmt->close();
        }

        $this->logSlowQuery($sql, $start);

        return $result;
    }

    /**
     * Execute query and fetch single row as associative array
     */
    public function fetchOne(string $sql, array $params = []): ?array {
        $result = $this->query($sql, $params);
        if ($result instanceof mysqli_result) {
            $row = $result->fetch_assoc();
            $result->free();
            return $row;
        }
        return null;
    }

    /**
     * Execute query and fetch all rows as associative array
     */
    public function fetchAll(string $sql, array $params = []): array {
        $result = $this->query($sql, $params);
        if ($result instanceof mysqli_result) {
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
            return $rows;
        }
        return [];
    }

    /**
     * Execute query and fetch single column value
     */
    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed {
        $result = $this->query($sql, $params);
        if ($result instanceof mysqli_result) {
            $row = $result->fetch_row();
            $result->free();
            return $row[$column] ?? null;
        }
        return null;
    }

    /**
     * Execute INSERT and return last insert ID
     */
    public function insert(string $sql, array $params = []): int {
        $this->query($sql, $params);
        return $this->connection->insert_id;
    }

    /**
     * Execute UPDATE/DELETE and return affected rows
     */
    public function execute(string $sql, array $params = []): int {
        $this->query($sql, $params);
        return $this->connection->affected_rows;
    }

    /**
     * Batch insert multiple rows efficiently
     *
     * @param string $table Table name
     * @param array $columns Column names
     * @param array $rows Array of value arrays
     * @param bool $ignore Use INSERT IGNORE
     * @return int Number of affected rows
     */
    public function batchInsert(string $table, array $columns, array $rows, bool $ignore = false): int {
        if (empty($rows)) {
            return 0;
        }

        $table = $this->escapeIdentifier($table);
        $cols = implode(', ', array_map([$this, 'escapeIdentifier'], $columns));
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $allPlaceholders = implode(', ', array_fill(0, count($rows), $placeholders));

        $ignoreKeyword = $ignore ? 'IGNORE ' : '';
        $sql = "INSERT {$ignoreKeyword}INTO {$table} ({$cols}) VALUES {$allPlaceholders}";

        // Flatten params array
        $params = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                $params[] = $value;
            }
        }

        return $this->execute($sql, $params);
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool {
        return $this->connection->begin_transaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool {
        return $this->connection->rollback();
    }

    /**
     * Execute callback within a transaction
     * Auto-commits on success, rolls back on exception
     *
     * @param callable $callback Function to execute
     * @return mixed Return value of callback
     */
    public function transaction(callable $callback): mixed {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Escape identifier (table/column name)
     */
    public function escapeIdentifier(string $identifier): string {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Escape string value (use prepared statements instead when possible)
     */
    public function escape(string $value): string {
        return $this->connection->real_escape_string($value);
    }

    /**
     * Get last error message
     */
    public function getError(): string {
        return $this->connection->error;
    }

    /**
     * Get last insert ID
     */
    public function getLastInsertId(): int {
        return $this->connection->insert_id;
    }

    /**
     * Get affected rows from last query
     */
    public function getAffectedRows(): int {
        return $this->connection->affected_rows;
    }

    /**
     * Set debug mode
     */
    public function setDebugMode(bool $enabled): void {
        $this->debugMode = $enabled;
    }

    /**
     * Set slow query threshold in milliseconds
     */
    public function setSlowQueryThreshold(int $ms): void {
        $this->slowQueryThreshold = $ms;
    }

    /**
     * Auto-detect parameter types for bind_param
     */
    private function detectTypes(array $params): string {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_null($param)) {
                $types .= 's'; // NULL as string
            } else {
                $types .= 's';
            }
        }
        return $types;
    }

    /**
     * Log slow queries
     */
    private function logSlowQuery(string $sql, float $start): void {
        $elapsed = (microtime(true) - $start) * 1000;

        if ($this->debugMode) {
            error_log(sprintf("[DB Debug] %.2fms | %s", $elapsed, substr($sql, 0, 200)));
        }

        if ($elapsed > $this->slowQueryThreshold) {
            error_log(sprintf("[Slow Query] %.2fms | %s", $elapsed, substr($sql, 0, 500)));
        }
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Close connection on destruct
     */
    public function __destruct() {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }
}

/**
 * Helper function to get Database instance
 */
function db(): Database {
    return Database::getInstance();
}
