<?php
/**
 * TempMail Pro - Authentication & Security
 */
class Auth {

    public static function generateOTP() {
        return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function storeOTP($identifier, $otp, $type = 'verify', $extra = []) {
        $data = Database::read(OTP_FILE);
        $key  = md5($identifier . $type);
        $data[$key] = array_merge([
            'otp'        => password_hash($otp, PASSWORD_DEFAULT),
            'identifier' => $identifier,
            'type'       => $type,
            'expires'    => time() + (OTP_EXPIRY * 60),
            'attempts'   => 0,
            'created_at' => time()
        ], $extra);
        Database::write(OTP_FILE, $data);
        return $otp;
    }

    public static function verifyOTP($identifier, $otp, $type = 'verify') {
        $data = Database::read(OTP_FILE);
        $key  = md5($identifier . $type);
        if (!isset($data[$key])) throw new Exception("OTP not found or already used.");
        $record = $data[$key];
        if (time() > (int)$record['expires']) {
            unset($data[$key]); Database::write(OTP_FILE, $data);
            throw new Exception("OTP expired. Please request a new one.");
        }
        if ((int)$record['attempts'] >= 5) {
            unset($data[$key]); Database::write(OTP_FILE, $data);
            throw new Exception("Too many wrong attempts. Please request a new OTP.");
        }
        if (!password_verify($otp, $record['otp'])) {
            $data[$key]['attempts'] = (int)$record['attempts'] + 1;
            Database::write(OTP_FILE, $data);
            $left = 5 - (int)$data[$key]['attempts'];
            throw new Exception("Invalid OTP. $left attempts remaining.");
        }
        unset($data[$key]);
        Database::write(OTP_FILE, $data);
        return $record;
    }

    public static function checkRateLimit($ip, $action = 'login') {
        $data        = Database::read(RATE_LIMIT_FILE);
        $key         = md5($ip . $action);
        $now         = time();
        $lockoutSecs = LOCKOUT_DURATION * 60;
        $windowSecs  = 15 * 60;

        if (!isset($data[$key])) $data[$key] = ['attempts' => 0, 'first_attempt' => $now, 'locked_until' => 0];
        $record = $data[$key];

        if ((int)$record['locked_until'] > $now) {
            $rem = ceil(((int)$record['locked_until'] - $now) / 60);
            throw new Exception("Too many attempts. Try again in $rem minutes.");
        }
        if ($now - (int)$record['first_attempt'] > $windowSecs) {
            $data[$key] = ['attempts' => 0, 'first_attempt' => $now, 'locked_until' => 0];
        }
        $data[$key]['attempts'] = (int)$data[$key]['attempts'] + 1;
        if ((int)$data[$key]['attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $data[$key]['locked_until'] = $now + $lockoutSecs;
            Database::write(RATE_LIMIT_FILE, $data);
            throw new Exception("Account locked for " . LOCKOUT_DURATION . " minutes due to too many failed attempts.");
        }
        Database::write(RATE_LIMIT_FILE, $data);
    }

    public static function clearRateLimit($ip, $action = 'login') {
        $data = Database::read(RATE_LIMIT_FILE);
        $key  = md5($ip . $action);
        if (isset($data[$key])) {
            unset($data[$key]);
            Database::write(RATE_LIMIT_FILE, $data);
        }
    }

    /**
     * Pick a domain for temp mail creation.
     *
     * Rules (strict, no DEFAULT_MAIL_DOMAIN fallback):
     * 1. No servers at all → error
     * 2. All servers disabled → error
     * 3. User has preferred_domains on API key:
     *    - requestedDomain given → must be in preferred AND accessible → or clear error
     *    - no requestedDomain   → random from preferred (accessible ones only)
     * 4. User has NO preferred_domains:
     *    - requestedDomain given → must be accessible → or clear error with available list
     *    - no requestedDomain   → random from all accessible
     */
    public static function pickDomain($clientId, $preferredDomains = [], $requestedDomain = null) {
        $servers = Database::read(MAIL_SERVERS_FILE);

        if (empty($servers)) {
            throw new Exception("No IMAP mail servers configured. Please contact the administrator.");
        }

        // Build accessible (active + public or owned)
        $accessible = [];
        foreach ($servers as $sid => $sv) {
            if ((int)(isset($sv['active']) ? $sv['active'] : 0) !== 1) continue;
            $isPublic = (bool)(isset($sv['is_public']) ? $sv['is_public'] : 1);
            $isOwner  = (isset($sv['owner_id']) ? $sv['owner_id'] : '') === $clientId;
            if ($isPublic || $isOwner) {
                $accessible[$sv['domain']] = $sv;
            }
        }

        if (empty($accessible)) {
            $total = count($servers);
            if ($total > 0) {
                throw new Exception("All mail servers are currently disabled. Please contact the administrator.");
            }
            throw new Exception("No accessible mail servers found. Please contact the administrator.");
        }

        // Clean preferred list
        $preferred = [];
        foreach ($preferredDomains as $pd) {
            $pd = trim($pd);
            if ($pd !== '') $preferred[] = $pd;
        }

        // ── User HAS preferred domains ────────────────────────────────────────
        if (!empty($preferred)) {
            $validPreferred = [];
            foreach ($preferred as $pd) {
                if (isset($accessible[$pd])) $validPreferred[] = $pd;
            }

            if (empty($validPreferred)) {
                throw new Exception(
                    "None of your configured preferred domains are currently accessible. " .
                    "Configured: " . implode(', ', $preferred) . ". Contact admin."
                );
            }

            if (!empty($requestedDomain)) {
                if (in_array($requestedDomain, $validPreferred)) {
                    return $requestedDomain;
                }
                if (isset($accessible[$requestedDomain])) {
                    throw new Exception(
                        "Domain '$requestedDomain' exists but is NOT in your preferred domains. " .
                        "Your allowed domains: " . implode(', ', $validPreferred)
                    );
                }
                throw new Exception(
                    "Domain '$requestedDomain' is not accessible for your API key. " .
                    "Your allowed domains: " . implode(', ', $validPreferred)
                );
            }

            // Random from valid preferred
            return $validPreferred[array_rand($validPreferred)];
        }

        // ── User has NO preferred domains → all accessible allowed ────────────

        if (!empty($requestedDomain)) {
            if (isset($accessible[$requestedDomain])) {
                return $requestedDomain;
            }
            $availableList = implode(', ', array_keys($accessible));
            throw new Exception(
                "Domain '$requestedDomain' is not available or not active. " .
                "Available domains: " . $availableList
            );
        }

        // Random from all accessible
        $allDomains = array_keys($accessible);
        return $allDomains[array_rand($allDomains)];
    }

    public static function createTempMailAccount($clientId, $requestedDomain = null, $preferredDomains = []) {
        $domain = self::pickDomain($clientId, $preferredDomains, $requestedDomain);

        $index = Database::read(GLOBAL_INDEX_FILE);
        $email = '';
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $clen  = strlen($chars);
        for ($i = 0; $i < 10; $i++) {
            $prefix = '';
            for ($j = 0; $j < 8; $j++) {
                $prefix .= $chars[random_int(0, $clen - 1)];
            }
            $tmp = $prefix . '@' . $domain;
            if (!isset($index[$tmp])) { $email = $tmp; break; }
        }
        if (empty($email)) {
            $email = bin2hex(random_bytes(4)) . '@' . $domain;
        }

        $password      = bin2hex(random_bytes(4));
        $index[$email] = [
            'uid'    => $clientId,
            'hash'   => password_hash($password, PASSWORD_DEFAULT),
            'time'   => time(),
            'domain' => $domain
        ];
        Database::write(GLOBAL_INDEX_FILE, $index);
        return ['email' => $email, 'password' => $password];
    }

    public static function verifyTempMail($email, $password) {
        $index = Database::read(GLOBAL_INDEX_FILE);
        if (!isset($index[$email])) return false;
        return password_verify($password, $index[$email]['hash']);
    }

    public static function generateApiKey()      { return 'md_live_' . bin2hex(random_bytes(16)); }
    public static function hashPassword($p)      { return password_hash($p, PASSWORD_BCRYPT, ['cost' => 12]); }
    public static function verifyPassword($p,$h) { return password_verify($p, $h); }
    public static function clean($v)             { return htmlspecialchars(strip_tags(trim($v ?? ''))); }
    public static function validEmail($e)        { return filter_var($e, FILTER_VALIDATE_EMAIL) !== false; }

    public static function csrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf($t) {
        return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $t);
    }
}
