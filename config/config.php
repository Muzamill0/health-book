<?php
/**
 * Loads settings from the .env file in the project root.
 * Copy .env.example to .env and edit the values for each environment.
 */
(function (string $file): void {
    if (!is_readable($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (preg_match('/^(["\'])(.*)\1$/', $value, $m)) {
            $value = $m[2];
        }
        $_ENV[$key] = $value;
    }
})(__DIR__ . '/../.env');

function env(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? getenv($key);
    return ($value === false || $value === null) ? $default : (string) $value;
}

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', (int) env('DB_PORT', '3306'));
define('DB_SOCKET', env('DB_SOCKET')); // leave empty to connect with host/port
define('DB_NAME', env('DB_NAME', 'health_book'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS'));

// Public base URL without trailing slash, e.g. https://example.com
define('BASE_URL', rtrim(env('APP_URL', 'http://localhost:8888/health-book'), '/'));

// Paddle Billing (https://vendors.paddle.com → Developer tools → Authentication)
define('PADDLE_ENV', env('PADDLE_ENV', 'sandbox') === 'production' ? 'production' : 'sandbox');
define('PADDLE_API_KEY', env('PADDLE_API_KEY'));
define('PADDLE_CLIENT_TOKEN', env('PADDLE_CLIENT_TOKEN'));
define('PADDLE_WEBHOOK_SECRET', env('PADDLE_WEBHOOK_SECRET'));
define('PADDLE_TAX_CATEGORY', env('PADDLE_TAX_CATEGORY', 'standard'));

define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');
