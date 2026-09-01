<?php
require_once __DIR__ . '/../config.php';

class AuthMiddleware {
    public static function verify() {
        self::checkRateLimit();
        self::checkToken();
    }

    private static function checkToken() {
        $enabled = filter_var($_ENV['AUTH_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return;
        }

        $expectedToken = $_ENV['AUTH_TOKEN'] ?? '';
        if (empty($expectedToken)) {
            self::abort(500, 'Auth token is not configured on the server.');
        }

        $providedToken = '';
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        
        // Nginx/FastCGI fallback for headers
        if (empty($headers)) {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        }

        // lowercase keys for easier matching
        $headers = array_change_key_case($headers, CASE_LOWER);

        if (isset($headers['x-auth-token'])) {
            $providedToken = $headers['x-auth-token'];
        } elseif (isset($headers['authorization']) && preg_match('/Bearer\s+(.*)$/i', $headers['authorization'], $matches)) {
            $providedToken = $matches[1];
        }

        if (empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
            self::abort(401, 'Unauthorized');
        }
    }

    private static function checkRateLimit() {
        $limit = (int)($_ENV['RATE_LIMIT_PER_MINUTE'] ?? 10);
        if ($limit <= 0) {
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if ($ip === 'unknown') return;

        $dir = __DIR__ . '/../logs/rate_limit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $hash = md5($ip);
        $file = $dir . '/' . $hash . '.json';
        $now = time();
        $requests = [];

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data)) {
                $requests = array_filter($data, function($timestamp) use ($now) {
                    return ($now - $timestamp) < 60;
                });
            }
        }

        if (count($requests) >= $limit) {
            self::abort(429, 'Too Many Requests - Rate limit exceeded');
        }

        $requests[] = $now;
        file_put_contents($file, json_encode(array_values($requests)));
    }

    private static function abort($code, $message) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }
}
