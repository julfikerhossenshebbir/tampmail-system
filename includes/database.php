<?php
/**
 * TempMail Pro - Database Helper
 * Simple INI-based flat file database with caching
 */
class Database {
    private static $cache = [];

    public static function read($file) {
        if (isset(self::$cache[$file])) return self::$cache[$file];
        if (!file_exists($file)) return [];
        $data = @parse_ini_file($file, true, INI_SCANNER_RAW);
        self::$cache[$file] = $data ?: [];
        return self::$cache[$file];
    }

    public static function write($file, $data) {
        $dir = dirname($file);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $content = "";
        foreach ($data as $section => $values) {
            if (!is_array($values)) continue;
            $sectionKey = str_replace([']', '['], ['', ''], $section);
            $content .= "[" . $sectionKey . "]\n";
            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $k => $v) {
                        $v = str_replace('"', '\"', (string)$v);
                        $content .= "{$key}[{$k}] = \"$v\"\n";
                    }
                } else {
                    $value = str_replace('"', '\"', (string)$value);
                    $content .= "$key = \"$value\"\n";
                }
            }
            $content .= "\n";
        }
        $result = file_put_contents($file, $content, LOCK_EX);
        self::$cache[$file] = $data;
        return $result !== false;
    }

    public static function get($file, $section, $key = null) {
        $data = self::read($file);
        if (!isset($data[$section])) return null;
        if ($key === null) return $data[$section];
        return $data[$section][$key] ?? null;
    }

    public static function set($file, $section, $key, $value) {
        $data = self::read($file);
        if (!isset($data[$section])) $data[$section] = [];
        $data[$section][$key] = $value;
        return self::write($file, $data);
    }

    public static function delete($file, $section) {
        $data = self::read($file);
        unset($data[$section]);
        return self::write($file, $data);
    }

    public static function exists($file, $section) {
        $data = self::read($file);
        return isset($data[$section]);
    }

    public static function clearCache($file = null) {
        if ($file) unset(self::$cache[$file]);
        else self::$cache = [];
    }

    // Check domain lock for API
    public static function checkDomainLock($allowedDomain) {
        if (empty($allowedDomain) || in_array(strtolower(trim($allowedDomain)), ['*', 'any', ''])) {
            return true;
        }
        $referer = $_SERVER['HTTP_REFERER'] ?? $_SERVER['HTTP_ORIGIN'] ?? '';
        if (empty($referer)) throw new Exception("Direct API access blocked. Set domain to 'any' to allow.");
        $host = strtolower(parse_url($referer, PHP_URL_HOST));
        $allowedList = array_map('trim', explode(',', strtolower($allowedDomain)));
        foreach ($allowedList as $d) {
            if ($d === $host || (strpos($d, '*.') === 0 && substr($host, -(strlen($d)-1)) === substr($d, 1))) {
                return true;
            }
        }
        throw new Exception("Unauthorized domain: $host");
    }

    // API usage tracking
    public static function updateUsage($clientId, $limitCount, $period = 'day') {
        $strLimit = strtolower(trim((string)$limitCount));
        if (in_array($strLimit, ['unlimited', '-1', 'pro', 'infinity'])) return;

        $data = self::read(USAGE_FILE);
        $trackingKey = ($period === 'week') ? date('Y-W') : date('Y-m-d');

        if (!isset($data[$clientId]) || ($data[$clientId]['period_key'] ?? '') !== $trackingKey) {
            $data[$clientId] = ['period_key' => $trackingKey, 'count' => 0];
        }

        $currentUsage = (int)($data[$clientId]['count'] ?? 0);
        $maxLimit = (int)$limitCount;
        if ($maxLimit > 0 && $currentUsage >= $maxLimit) {
            throw new Exception("API limit reached. Limit: $maxLimit per $period.");
        }
        $data[$clientId]['count'] = $currentUsage + 1;
        self::write(USAGE_FILE, $data);
    }
}
