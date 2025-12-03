<?php
/**
 * HTTP Client for Gheop Reader
 *
 * Provides a simple interface for making HTTP requests with:
 * - Consistent user agent
 * - SSL verification
 * - Timeout handling
 * - Multi-curl support for parallel requests
 */

class HttpClient {
    private string $userAgent = 'Mozilla/5.0 (compatible; GheopReader/1.0; +https://reader.gheop.com/)';
    private int $timeout = 30;
    private int $connectTimeout = 10;
    private bool $sslVerify = true;
    private int $maxRedirects = 3;

    /**
     * Set custom user agent
     */
    public function setUserAgent(string $userAgent): self {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Set timeout in seconds
     */
    public function setTimeout(int $seconds): self {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Set connection timeout in seconds
     */
    public function setConnectTimeout(int $seconds): self {
        $this->connectTimeout = $seconds;
        return $this;
    }

    /**
     * Enable/disable SSL verification
     */
    public function setSslVerify(bool $verify): self {
        $this->sslVerify = $verify;
        return $this;
    }

    /**
     * Fetch URL content
     *
     * @param string $url URL to fetch
     * @param array $headers Optional headers
     * @return string|false Content or false on failure
     */
    public function get(string $url, array $headers = []): string|false {
        $ch = $this->createHandle($url, $headers);
        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("HttpClient error for {$url}: {$error}");
            return false;
        }

        return $result ? trim($result) : false;
    }

    /**
     * Fetch URL and return response info
     *
     * @param string $url URL to fetch
     * @param array $headers Optional headers
     * @return array{content: string|false, info: array, error: string}
     */
    public function getWithInfo(string $url, array $headers = []): array {
        $ch = $this->createHandle($url, $headers);
        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'content' => $result ? trim($result) : false,
            'info' => $info,
            'error' => $error
        ];
    }

    /**
     * Fetch multiple URLs in parallel
     *
     * @param array $urls Array of URLs or array of ['url' => ..., 'headers' => ...]
     * @param int $maxConcurrent Maximum concurrent connections
     * @return array Results indexed by URL
     */
    public function getMultiple(array $urls, int $maxConcurrent = 10): array {
        $mh = curl_multi_init();
        $handles = [];
        $results = [];

        // Normalize URLs to array format
        $requests = [];
        foreach ($urls as $key => $url) {
            if (is_array($url)) {
                $requests[$key] = $url;
            } else {
                $requests[$url] = ['url' => $url, 'headers' => []];
            }
        }

        $urlKeys = array_keys($requests);
        $running = 0;
        $processed = 0;

        while ($processed < count($requests)) {
            // Add handles up to max concurrent
            while ($running < $maxConcurrent && ($processed + $running) < count($requests)) {
                $key = $urlKeys[$processed + $running];
                $req = $requests[$key];
                $ch = $this->createHandle($req['url'], $req['headers'] ?? []);
                curl_multi_add_handle($mh, $ch);
                $handles[(int)$ch] = ['key' => $key, 'handle' => $ch];
                $running++;
            }

            // Execute and wait for activity
            do {
                $status = curl_multi_exec($mh, $active);
            } while ($status === CURLM_CALL_MULTI_PERFORM);

            // Process completed handles
            while ($info = curl_multi_info_read($mh)) {
                if ($info['msg'] === CURLMSG_DONE) {
                    $ch = $info['handle'];
                    $handleInfo = $handles[(int)$ch];
                    $key = $handleInfo['key'];

                    $results[$key] = [
                        'content' => curl_multi_getcontent($ch),
                        'info' => curl_getinfo($ch),
                        'error' => curl_error($ch),
                        'errno' => $info['result']
                    ];

                    curl_multi_remove_handle($mh, $ch);
                    curl_close($ch);
                    unset($handles[(int)$ch]);
                    $processed++;
                    $running--;
                }
            }

            // Wait for activity (avoid busy loop)
            if ($active && $status === CURLM_OK) {
                curl_multi_select($mh, 0.1);
            }
        }

        curl_multi_close($mh);

        return $results;
    }

    /**
     * Create a curl handle with standard options
     */
    private function createHandle(string $url, array $headers = []): CurlHandle {
        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',  // Accept all encodings
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => $this->maxRedirects,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0
        ];

        if (!empty($headers)) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $options);

        return $ch;
    }
}
