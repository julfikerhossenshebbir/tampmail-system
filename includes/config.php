<?php
/**
 * TempMail Pro - Core Configuration
 * Reads .env and sets all system constants
 */
if (!defined('TEMPMAIL_LOADED')) define('TEMPMAIL_LOADED', true);

function findEnvFile() {
    $dirs = [__DIR__ . '/../', __DIR__ . '/../../', __DIR__ . '/'];
    foreach ($dirs as $dir) {
        $path = $dir . '.env';
        if (file_exists($path)) return realpath($path);
    }
    return null;
}

function parseEnv($file) {
    $vars  = [];
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        $eqPos = strpos($line, '=');
        $key   = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        $vars[$key] = $value;
    }
    return $vars;
}

$envFile = findEnvFile();
if (!$envFile) {
    $msg = '.env file not found. Please copy .env.example to .env and configure it.';
    if (php_sapi_name() === 'cli') {
        die($msg . "\n");
    }
    header('Content-Type: text/html');
    die('<h2 style="font-family:sans-serif;color:red;padding:20px;">' . $msg . '</h2>');
}

$env = parseEnv($envFile);

function env($key, $default = null) {
    global $env;
    return isset($env[$key]) ? $env[$key] : $default;
}

// System
define('SYSTEM_ONLINE',       env('SYSTEM_ONLINE', 'true') === 'true');
define('MAINTENANCE_MESSAGE', env('MAINTENANCE_MESSAGE', 'System is under maintenance. Please try again later.'));

// App
define('APP_NAME',     env('APP_NAME', 'TempMail Pro'));
define('APP_URL',      rtrim(env('APP_URL', 'http://localhost'), '/'));
define('APP_TIMEZONE', env('APP_TIMEZONE', 'UTC'));
define('APP_DEBUG',    env('APP_DEBUG', 'false') === 'true');

date_default_timezone_set(APP_TIMEZONE);

// Paths
define('BASE_PATH', realpath(__DIR__ . '/..'));
define('DATA_DIR',  BASE_PATH . '/data/');
if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0755, true);

// Data files
define('KEY_FILE',          DATA_DIR . 'api_keys.ini');
define('GLOBAL_INDEX_FILE', DATA_DIR . 'global_email_index.ini');
define('USAGE_FILE',        DATA_DIR . 'api_usage.ini');
define('OTP_FILE',          DATA_DIR . 'otp_store.ini');
define('MAIL_SERVERS_FILE', DATA_DIR . 'mail_servers.ini');
define('RATE_LIMIT_FILE',   DATA_DIR . 'rate_limits.ini');
define('LOG_FILE',          DATA_DIR . 'app.log');

// SMTP (for verification emails — separate from IMAP)
define('SMTP_HOST',       env('SMTP_HOST', ''));
define('SMTP_PORT',       (int)env('SMTP_PORT', 587));
define('SMTP_SECURE',     env('SMTP_SECURE', 'tls'));
define('SMTP_USER',       env('SMTP_USER', ''));
define('SMTP_PASS',       env('SMTP_PASS', ''));
define('SMTP_FROM_NAME',  env('SMTP_FROM_NAME', APP_NAME));
define('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL', env('SMTP_USER', '')));

// Security
define('SECRET_KEY',         env('SECRET_KEY', 'change-this-secret-key'));
define('OTP_EXPIRY',         (int)env('OTP_EXPIRY', 15));
define('MAX_LOGIN_ATTEMPTS', (int)env('MAX_LOGIN_ATTEMPTS', 5));
define('LOCKOUT_DURATION',   (int)env('LOCKOUT_DURATION', 30));
define('SESSION_LIFETIME',   (int)env('SESSION_LIFETIME', 24));

// Registration
define('REQUIRE_EMAIL_VERIFY', env('REQUIRE_EMAIL_VERIFY', 'true') === 'true');
define('AUTO_APPROVE_USERS',   env('AUTO_APPROVE_USERS', 'false') === 'true');
define('DEFAULT_LIMIT_COUNT',  env('DEFAULT_LIMIT_COUNT', '15'));
define('DEFAULT_LIMIT_PERIOD', env('DEFAULT_LIMIT_PERIOD', 'day'));

// Contact
define('ADMIN_TELEGRAM', env('ADMIN_TELEGRAM', '#'));
define('ADMIN_EMAIL',    env('ADMIN_EMAIL', ''));

// NOTE: No DEFAULT_MAIL_DOMAIN constant.
// The default domain is always the first active IMAP server added in Admin Panel.
// If no IMAP server is configured, temp mail creation is blocked with a clear error.

// Error reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
