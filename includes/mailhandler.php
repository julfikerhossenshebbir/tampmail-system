<?php
/**
 * TempMail Pro - IMAP Mail Handler
 */
class MailHandler {
    private $inbox;
    private $serverConfig;

    public function __construct($domain = null) {
        if (!function_exists('imap_open')) {
            throw new Exception("PHP IMAP extension is not installed. Ask your host to enable php-imap.");
        }

        // If no domain given, pick the first active server (default)
        if (empty($domain)) {
            $default = self::getDefaultServer();
            if (!$default) {
                throw new Exception("No active IMAP mail server found. Please add a server in Admin → Mail Servers.");
            }
            $this->serverConfig = $default;
        } else {
            $this->serverConfig = self::getServerByDomain($domain);
            if (!$this->serverConfig) {
                throw new Exception("No IMAP server configured for domain: $domain. Contact administrator.");
            }
            if ((int)(isset($this->serverConfig['active']) ? $this->serverConfig['active'] : 0) !== 1) {
                throw new Exception("Mail server for domain '$domain' is currently disabled.");
            }
        }

        $imapStr = self::buildImapStr($this->serverConfig);
        $this->inbox = @imap_open(
            $imapStr,
            $this->serverConfig['imap_user'],
            $this->serverConfig['imap_pass'],
            OP_READONLY,
            0,
            ['DISABLE_AUTHENTICATOR' => 'GSSAPI']
        );

        if (!$this->inbox) {
            $err = imap_last_error();
            throw new Exception("Cannot connect to mail server for '" . $this->serverConfig['domain'] . "'. Error: " . $err);
        }
    }

    /**
     * Get the first active server (used as default when no domain specified)
     */
    public static function getDefaultServer() {
        $servers = Database::read(MAIL_SERVERS_FILE);
        if (empty($servers)) return null;
        foreach ($servers as $sid => $sv) {
            if ((int)(isset($sv['active']) ? $sv['active'] : 0) === 1) {
                return array_merge(['_id' => $sid], $sv);
            }
        }
        return null;
    }

    /**
     * Get server config by domain name
     */
    public static function getServerByDomain($domain) {
        $servers = Database::read(MAIL_SERVERS_FILE);
        foreach ($servers as $sid => $sv) {
            if (strtolower(isset($sv['domain']) ? $sv['domain'] : '') === strtolower($domain)) {
                return array_merge(['_id' => $sid], $sv);
            }
        }
        return null;
    }

    public static function buildImapStr($config) {
        $ssl  = (isset($config['imap_ssl']) ? (int)$config['imap_ssl'] : 1) ? '/imap/ssl/novalidate-cert' : '/imap/notls';
        $port = isset($config['imap_port']) ? (int)$config['imap_port'] : 993;
        return '{' . $config['imap_host'] . ':' . $port . $ssl . '}INBOX';
    }

    /**
     * Get default domain (first active server's domain)
     */
    public static function getDefaultDomain() {
        $srv = self::getDefaultServer();
        return $srv ? $srv['domain'] : null;
    }

    /**
     * All active domains accessible to a user (public + owned private)
     */
    public static function getDomainsForUser($userId = null) {
        $servers = Database::read(MAIL_SERVERS_FILE);
        $out = [];
        foreach ($servers as $sid => $sv) {
            if ((int)(isset($sv['active']) ? $sv['active'] : 0) !== 1) continue;
            $pub   = (bool)(isset($sv['is_public']) ? $sv['is_public'] : 1);
            $owned = !empty($userId) && (isset($sv['owner_id']) ? $sv['owner_id'] : '') === $userId;
            if ($pub || $owned) {
                $out[] = [
                    'id'     => $sid,
                    'domain' => $sv['domain'],
                    'name'   => isset($sv['name']) ? $sv['name'] : $sv['domain'],
                    'public' => $pub,
                    'owned'  => $owned
                ];
            }
        }
        return $out;
    }

    /**
     * All active public domains only
     */
    public static function getPublicDomains() {
        $servers = Database::read(MAIL_SERVERS_FILE);
        $out = [];
        foreach ($servers as $sid => $sv) {
            if ((int)(isset($sv['active']) ? $sv['active'] : 0) === 1 &&
                (bool)(isset($sv['is_public']) ? $sv['is_public'] : 1)) {
                $out[] = [
                    'id'     => $sid,
                    'domain' => $sv['domain'],
                    'name'   => isset($sv['name']) ? $sv['name'] : $sv['domain']
                ];
            }
        }
        return $out;
    }

    /**
     * Check if any active server exists
     */
    public static function hasActiveServer() {
        $servers = Database::read(MAIL_SERVERS_FILE);
        foreach ($servers as $sv) {
            if ((int)(isset($sv['active']) ? $sv['active'] : 0) === 1) return true;
        }
        return false;
    }

    public function fetchMessages($email, $limit = 15) {
        $msgs = [];
        $res  = @imap_search($this->inbox, 'TO "' . addslashes($email) . '"');
        if (!$res) return $msgs;
        rsort($res);
        foreach (array_slice($res, 0, $limit) as $num) {
            $ov = @imap_fetch_overview($this->inbox, $num, 0);
            if (!$ov) continue;
            $ov = $ov[0];
            $msgs[] = [
                'id'        => (int)$num,
                'sender'    => isset($ov->from)    ? $this->decode($ov->from)    : 'Unknown',
                'subject'   => isset($ov->subject) ? $this->decode($ov->subject) : '(No Subject)',
                'timestamp' => isset($ov->udate)   ? (int)$ov->udate             : time(),
                'seen'      => (bool)(isset($ov->seen) ? $ov->seen : false),
            ];
        }
        return $msgs;
    }

    public function getMessageContent($uid) {
        $struct = @imap_fetchstructure($this->inbox, $uid);
        if (!$struct) return '<p style="color:#888;text-align:center;">[Could not load message]</p>';

        $content = '';
        if (isset($struct->parts) && count($struct->parts)) {
            foreach ($struct->parts as $i => $p) {
                if (strtoupper(isset($p->subtype) ? $p->subtype : '') === 'HTML') {
                    $raw = @imap_fetchbody($this->inbox, $uid, $i + 1);
                    $content = $this->decodePart($raw, isset($p->encoding) ? $p->encoding : 0);
                    break;
                }
            }
            if (empty(trim(strip_tags($content)))) {
                $raw     = @imap_fetchbody($this->inbox, $uid, '1');
                $enc     = isset($struct->parts[0]) && isset($struct->parts[0]->encoding) ? $struct->parts[0]->encoding : 0;
                $content = $this->decodePart($raw, $enc);
                $content = nl2br(htmlspecialchars($content));
            }
        } else {
            $raw     = @imap_fetchbody($this->inbox, $uid, '1');
            $content = $this->decodePart($raw, isset($struct->encoding) ? $struct->encoding : 0);
            if (strtoupper(isset($struct->subtype) ? $struct->subtype : '') !== 'HTML') {
                $content = nl2br(htmlspecialchars($content));
            }
        }
        return $content ?: '<p style="color:#888;text-align:center;">[Empty message]</p>';
    }

    private function decodePart($data, $enc) {
        if (!$data) return '';
        switch ((int)$enc) {
            case 3: return base64_decode($data);
            case 4: return quoted_printable_decode($data);
            default: return $data;
        }
    }

    private function decode($text) {
        if (empty($text)) return '';
        $elems = @imap_mime_header_decode($text);
        if (!$elems) return $text;
        $s = '';
        foreach ($elems as $e) {
            $charset = isset($e->charset) ? $e->charset : 'default';
            $s .= ($charset === 'default') ? $e->text : @mb_convert_encoding($e->text, 'UTF-8', $charset);
        }
        return $s;
    }

    public function close() {
        if ($this->inbox) @imap_close($this->inbox);
    }
}
