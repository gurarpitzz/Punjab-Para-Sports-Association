<?php
// config/app.php - Central Application Configurations for Punjab Para Sports Association

// 1. Lightweight .env loader (reads .env.local then .env if present)
function loadEnvFile($filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) return;
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, '"\''); // strip quotes
        if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Load .env files (precedence: system env > .env.local > .env > config/local.php)
loadEnvFile(dirname(__DIR__) . '/.env.local');
loadEnvFile(dirname(__DIR__) . '/.env');

// Optional legacy local override (strictly git-ignored)
if (file_exists(__DIR__ . '/local.php')) {
    include_once __DIR__ . '/local.php';
}

// Helper function to fetch env or fallback
function ppsa_config($key, $default = '') {
    if (defined($key)) return constant($key);
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    return $default;
}

// 2. Define Core Constants from Environment
if (!defined('DB_HOST')) define('DB_HOST', ppsa_config('DB_HOST', 'localhost'));
if (!defined('DB_NAME')) define('DB_NAME', ppsa_config('DB_NAME', ''));
if (!defined('DB_USER')) define('DB_USER', ppsa_config('DB_USER', ''));

// Support both DB_PASSWORD and DB_PASS interchangeably
$resolvedPassword = '';
if (defined('DB_PASSWORD') && DB_PASSWORD !== '') {
    $resolvedPassword = DB_PASSWORD;
} elseif (defined('DB_PASS') && DB_PASS !== '') {
    $resolvedPassword = DB_PASS;
} else {
    $resolvedPassword = ppsa_config('DB_PASSWORD', ppsa_config('DB_PASS', ''));
}
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', $resolvedPassword);
if (!defined('DB_PASS')) define('DB_PASS', $resolvedPassword);

if (!defined('RESEND_API_KEY')) define('RESEND_API_KEY', ppsa_config('RESEND_API_KEY', ''));
if (!defined('MAILER_FROM')) define('MAILER_FROM', ppsa_config('MAILER_FROM', 'Punjab Para Sports Association <noreply@punjabparasports.ajeetgraphics.com>'));
if (!defined('PPSA_ADMIN_EMAIL')) define('PPSA_ADMIN_EMAIL', ppsa_config('PPSA_ADMIN_EMAIL', 'admin@punjabparasports.org'));

if (!defined('OTP_SECRET')) define('OTP_SECRET', ppsa_config('OTP_SECRET', ''));
if (!defined('APP_ENV')) define('APP_ENV', ppsa_config('APP_ENV', 'production'));
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');

