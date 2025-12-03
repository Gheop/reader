<?php
/**
 * Simple Test Runner for Gheop Reader
 *
 * Usage: php tests/run.php
 *
 * Tests are methods that start with "test" in classes that extend TestCase.
 */

class TestCase {
    protected static int $passed = 0;
    protected static int $failed = 0;
    protected static array $failures = [];

    /**
     * Assert that a condition is true
     */
    protected function assertTrue(bool $condition, string $message = ''): void {
        if ($condition) {
            self::$passed++;
            echo '.';
        } else {
            self::$failed++;
            self::$failures[] = $message ?: 'Expected true, got false';
            echo 'F';
        }
    }

    /**
     * Assert that a condition is false
     */
    protected function assertFalse(bool $condition, string $message = ''): void {
        $this->assertTrue(!$condition, $message ?: 'Expected false, got true');
    }

    /**
     * Assert two values are equal
     */
    protected function assertEquals($expected, $actual, string $message = ''): void {
        if ($expected === $actual) {
            self::$passed++;
            echo '.';
        } else {
            self::$failed++;
            $msg = $message ?: "Expected " . var_export($expected, true) . ", got " . var_export($actual, true);
            self::$failures[] = $msg;
            echo 'F';
        }
    }

    /**
     * Assert value is not null
     */
    protected function assertNotNull($value, string $message = ''): void {
        $this->assertTrue($value !== null, $message ?: 'Expected non-null value');
    }

    /**
     * Assert value is null
     */
    protected function assertNull($value, string $message = ''): void {
        $this->assertTrue($value === null, $message ?: 'Expected null value');
    }

    /**
     * Assert array has key
     */
    protected function assertArrayHasKey(string $key, array $array, string $message = ''): void {
        $this->assertTrue(array_key_exists($key, $array), $message ?: "Array missing key: {$key}");
    }

    /**
     * Assert string contains substring
     */
    protected function assertStringContains(string $needle, string $haystack, string $message = ''): void {
        $this->assertTrue(str_contains($haystack, $needle), $message ?: "String does not contain: {$needle}");
    }

    /**
     * Assert HTTP response code
     */
    protected function assertResponseCode(int $expected, array $response, string $message = ''): void {
        $actual = $response['info']['http_code'] ?? 0;
        $this->assertEquals($expected, $actual, $message ?: "Expected HTTP {$expected}, got {$actual}");
    }

    /**
     * Make HTTP request to local endpoint
     */
    protected function httpGet(string $path, array $cookies = []): array {
        return $this->httpRequest($path, $cookies, true);
    }

    /**
     * Make HTTP request without following redirects
     */
    protected function httpGetNoFollow(string $path, array $cookies = []): array {
        return $this->httpRequest($path, $cookies, false);
    }

    /**
     * Internal HTTP request method
     */
    private function httpRequest(string $path, array $cookies, bool $followRedirects): array {
        $url = 'https://reader.gheop.com' . $path;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,  // Local testing
        ]);

        if (!empty($cookies)) {
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', array_map(
                fn($k, $v) => "{$k}={$v}",
                array_keys($cookies),
                array_values($cookies)
            )));
        }

        $content = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'content' => $content,
            'info' => $info,
            'error' => $error
        ];
    }

    /**
     * Make HTTP POST request
     */
    protected function httpPost(string $path, array $data = [], array $cookies = []): array {
        $url = 'https://reader.gheop.com' . $path;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
        ]);

        if (!empty($cookies)) {
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', array_map(
                fn($k, $v) => "{$k}={$v}",
                array_keys($cookies),
                array_values($cookies)
            )));
        }

        $content = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'content' => $content,
            'info' => $info,
            'error' => $error
        ];
    }

    /**
     * Run all tests in this class
     */
    public function run(): void {
        $class = get_class($this);
        echo "\n{$class}\n";

        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (str_starts_with($method, 'test')) {
                echo "  {$method}: ";
                try {
                    $this->$method();
                } catch (Exception $e) {
                    self::$failed++;
                    self::$failures[] = "{$method}: " . $e->getMessage();
                    echo 'E';
                }
                echo "\n";
            }
        }
    }

    /**
     * Print summary
     */
    public static function printSummary(): void {
        echo "\n" . str_repeat('-', 50) . "\n";
        echo "Tests: " . (self::$passed + self::$failed) . ", ";
        echo "Passed: " . self::$passed . ", ";
        echo "Failed: " . self::$failed . "\n";

        if (!empty(self::$failures)) {
            echo "\nFailures:\n";
            foreach (self::$failures as $i => $failure) {
                echo "  " . ($i + 1) . ") {$failure}\n";
            }
        }

        echo "\n";
    }

    /**
     * Get exit code (0 = success, 1 = failures)
     */
    public static function getExitCode(): int {
        return self::$failed > 0 ? 1 : 0;
    }
}
