<?php
/**
 * TempMail Pro - API Endpoint v2.1
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key");
header("Content-Type: application/json; charset=UTF-8");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

systemGate(true);

$apiKey = trim(isset($_REQUEST['key']) ? $_REQUEST['key'] : (isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : ''));
$action = clean(isset($_REQUEST['action']) ? $_REQUEST['action'] : '');

if ($action === 'check_status') {
    sendJson('success', ['message' => 'System Operational', 'version' => '2.1']);
}

$api_keys = Database::read(KEY_FILE);
if (empty($apiKey) || !isset($api_keys[$apiKey])) {
    sendJson('error', ['message' => 'Unauthorized: Invalid API Key'], 401);
}

$client = $api_keys[$apiKey];

try {
    // Domain lock check
    if (!empty($client['allowed_domain'])) {
        Database::checkDomainLock($client['allowed_domain']);
    }

    // Active check
    if ((isset($client['active']) ? $client['active'] : '0') != '1') {
        throw new Exception("API Key is not active. Contact administrator.");
    }

    // Expiry check
    if (!empty($client['expires_at']) && time() > (int)$client['expires_at']) {
        throw new Exception("API Key has expired.");
    }

    $validActions = ['gen_email', 'get_messages', 'read_message', 'login', 'check_status', 'get_domains'];
    if (!in_array($action, $validActions)) {
        throw new Exception("Invalid action '$action'. Valid actions: " . implode(', ', $validActions));
    }

    // Parse user's preferred domains (set by admin or user in dashboard)
    $prefRaw = isset($client['preferred_domains']) ? $client['preferred_domains'] : '';
    $preferredDomains = [];
    if (!empty($prefRaw)) {
        foreach (explode(',', $prefRaw) as $pd) {
            $pd = trim($pd);
            if ($pd !== '') $preferredDomains[] = $pd;
        }
    }

    switch ($action) {

        case 'get_domains':
            $domains = MailHandler::getDomainsForUser($client['id']);
            $preferred = $preferredDomains;
            // Mark which ones are preferred
            $result = [];
            foreach ($domains as $d) {
                $d['preferred'] = in_array($d['domain'], $preferred);
                $result[] = $d;
            }
            sendJson('success', ['domains' => $result, 'count' => count($result), 'preferred' => $preferred]);
            break;

        case 'gen_email':
            // Get requested domain from param (optional)
            $requestedDomain = clean(isset($_REQUEST['domain']) ? $_REQUEST['domain'] : '');
            if ($requestedDomain === '') $requestedDomain = null;

            // Update usage BEFORE creating (so limit is enforced)
            Database::updateUsage(
                $client['id'],
                isset($client['limit_count'])  ? $client['limit_count']  : 15,
                isset($client['limit_period']) ? $client['limit_period'] : 'day'
            );

            // createTempMailAccount will throw descriptive error if domain invalid/unavailable
            $account = Auth::createTempMailAccount($client['id'], $requestedDomain, $preferredDomains);
            sendJson('success', $account);
            break;

        case 'get_messages':
            $email = clean(isset($_REQUEST['email'])    ? $_REQUEST['email']    : '');
            $pass  = clean(isset($_REQUEST['password']) ? $_REQUEST['password'] : '');
            if (!$email || !$pass) {
                throw new Exception("Parameters 'email' and 'password' are required.");
            }
            if (!Auth::verifyTempMail($email, $pass)) {
                throw new Exception("Authentication failed: Invalid email or password.");
            }
            Database::updateUsage(
                $client['id'],
                isset($client['limit_count'])  ? $client['limit_count']  : 15,
                isset($client['limit_period']) ? $client['limit_period'] : 'day'
            );
            $parts = explode('@', $email);
            $dom   = isset($parts[1]) ? $parts[1] : '';
            $mail  = new MailHandler($dom);
            $msgs  = $mail->fetchMessages($email);
            $mail->close();
            sendJson('success', ['count' => count($msgs), 'messages' => $msgs]);
            break;

        case 'read_message':
            $id    = (int)clean(isset($_REQUEST['id'])       ? $_REQUEST['id']       : '');
            $email = clean(isset($_REQUEST['email'])         ? $_REQUEST['email']    : '');
            $pass  = clean(isset($_REQUEST['password'])      ? $_REQUEST['password'] : '');
            if (!$id || !$email || !$pass) {
                throw new Exception("Parameters 'id', 'email', and 'password' are required.");
            }
            if (!Auth::verifyTempMail($email, $pass)) {
                throw new Exception("Authentication failed: Invalid email or password.");
            }
            Database::updateUsage(
                $client['id'],
                isset($client['limit_count'])  ? $client['limit_count']  : 15,
                isset($client['limit_period']) ? $client['limit_period'] : 'day'
            );
            $parts = explode('@', $email);
            $dom   = isset($parts[1]) ? $parts[1] : '';
            $mail  = new MailHandler($dom);
            $body  = $mail->getMessageContent($id);
            $mail->close();
            sendJson('success', ['content' => $body]);
            break;

        case 'login':
            $email = clean(isset($_REQUEST['email'])    ? $_REQUEST['email']    : '');
            $pass  = clean(isset($_REQUEST['password']) ? $_REQUEST['password'] : '');
            if (!$email || !$pass) {
                throw new Exception("Parameters 'email' and 'password' are required.");
            }
            Database::updateUsage(
                $client['id'],
                isset($client['limit_count'])  ? $client['limit_count']  : 15,
                isset($client['limit_period']) ? $client['limit_period'] : 'day'
            );
            if (Auth::verifyTempMail($email, $pass)) {
                sendJson('success', ['authenticated' => true, 'email' => $email]);
            } else {
                sendJson('error', ['message' => 'Invalid credentials'], 401);
            }
            break;
    }

} catch (Exception $e) {
    $msg  = $e->getMessage();
    // Determine HTTP code from message keywords
    if (strpos($msg, 'limit') !== false || strpos($msg, '429') !== false) {
        $code = 429;
    } elseif (
        strpos($msg, 'Unauthorized') !== false ||
        strpos($msg, 'domain') !== false ||
        strpos($msg, 'Domain') !== false ||
        strpos($msg, 'Invalid credentials') !== false ||
        strpos($msg, 'Authentication failed') !== false ||
        strpos($msg, 'allowed') !== false
    ) {
        $code = 403;
    } elseif (
        strpos($msg, 'not configured') !== false ||
        strpos($msg, 'disabled') !== false ||
        strpos($msg, 'unreachable') !== false
    ) {
        $code = 503;
    } else {
        $code = 400;
    }

    if ($code !== 429) {
        Logger::error("API[$action]: $msg");
    }
    sendJson('error', ['message' => $msg], $code);
}
