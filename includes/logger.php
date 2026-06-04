<?php
class Logger {
    public static function log($message, $level = 'INFO') {
        $ip    = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $entry = "[" . date('Y-m-d H:i:s') . "] [$level] [$ip] $message\n";
        @file_put_contents(LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
    }
    public static function error($msg)   { self::log($msg, 'ERROR'); }
    public static function info($msg)    { self::log($msg, 'INFO'); }
    public static function warning($msg) { self::log($msg, 'WARNING'); }
}
