<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/mailhandler.php';

// System maintenance gate
function systemGate($returnJson = false) {
    if (!SYSTEM_ONLINE) {
        if ($returnJson) {
            header('Content-Type: application/json');
            http_response_code(503);
            echo json_encode(['status' => 'error', 'message' => MAINTENANCE_MESSAGE, 'maintenance' => true]);
            exit;
        }
        // HTML maintenance page
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= APP_NAME ?> - Maintenance</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 min-h-screen flex items-center justify-center p-4">
<div class="text-center max-w-md">
  <div class="w-20 h-20 bg-yellow-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
    <svg class="w-10 h-10 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
    </svg>
  </div>
  <h1 class="text-3xl font-black text-white mb-3"><?= APP_NAME ?></h1>
  <p class="text-yellow-400 text-lg font-bold mb-2">🔧 Under Maintenance</p>
  <p class="text-gray-400"><?= htmlspecialchars(MAINTENANCE_MESSAGE) ?></p>
</div>
</body>
</html>
        <?php
        exit;
    }
}

function sendJson($status, $data, $code = 200) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code($code);
    }
    echo json_encode(array_merge(['status' => $status], (array)$data), JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect($url) {
    if (!headers_sent()) header("Location: $url");
    else echo "<script>window.location.href=" . json_encode($url) . ";</script>";
    exit;
}

function clean($v) { return Auth::clean($v); }
