<?php
/**
 * PHPUnit Bootstrap File
 *
 * Sets up the testing environment for Gheop Reader
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define test constants
define('TESTING', true);
define('TEST_ROOT', dirname(__DIR__));

// Mock session for testing
if (!isset($_SESSION)) {
    $_SESSION = [];
}

// Autoload function for test classes
spl_autoload_register(function ($class) {
    $file = TEST_ROOT . '/tests/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Mock database connection for testing
class MockMySQLi {
    public function query($sql) {
        return new MockMySQLiResult();
    }

    public function real_escape_string($str) {
        return addslashes($str);
    }

    public $insert_id = 1;
    public $error = '';
}

class MockMySQLiResult {
    private $data = [];
    public $num_rows = 0;

    public function fetch_assoc() {
        return array_shift($this->data);
    }

    public function fetch_row() {
        return array_values($this->fetch_assoc() ?: []);
    }

    public function fetch_array() {
        return $this->fetch_assoc();
    }
}

echo "Bootstrap loaded successfully\n";
