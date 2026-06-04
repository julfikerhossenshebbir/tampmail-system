<?php
require_once __DIR__ . '/includes/bootstrap.php';
systemGate();
$appName = APP_NAME;
$appUrl  = APP_URL;
$defaultSrv = MailHandler::getDefaultServer();
$domain  = $defaultSrv ? $defaultSrv['domain'] : 'yourdomain.com';
$servers = Database::read(MAIL_SERVERS_FILE);
$publicDomains = array_filter($servers, function($s) { return (isset($s['active'])?$s['active']:0)==1 && (isset($s['is_public'])?$s['is_public']:1)==1; });
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $appName ?> - API Documentation</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
pre { background:#1e293b; color:#e2e8f0; padding:1rem; border-radius:0.75rem; overflow-x:auto; font-size:13px; line-height:1.6; }
code.inline { background:#f1f5f9; color:#0f172a; padding:2px 6px; border-radius:4px; font-size:13px; }
.section { scroll-margin-top: 80px; }
</style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">

<!-- NAV -->
<nav class="bg-white border-b sticky top-0 z-40 shadow-sm">
  <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
    <a href="docs.php" class="font-black text-xl text-blue-600"><?= $appName ?></a>
    <div class="flex items-center gap-4 text-sm font-semibold">
      <a href="#overview" class="text-gray-500 hover:text-blue-600">Overview</a>
      <a href="#authentication" class="text-gray-500 hover:text-blue-600">Auth</a>
      <a href="#endpoints" class="text-gray-500 hover:text-blue-600">Endpoints</a>
      <a href="#examples" class="text-gray-500 hover:text-blue-600">Examples</a>
      <a href="tester.php" class="bg-blue-600 text-white px-4 py-1.5 rounded-lg hover:bg-blue-700 transition">API Tester</a>
      <a href="index.php" class="bg-gray-100 text-gray-700 px-4 py-1.5 rounded-lg hover:bg-gray-200 transition">Dashboard</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<div class="bg-gradient-to-br from-blue-700 via-blue-600 to-indigo-600 text-white py-16 px-4">
  <div class="max-w-4xl mx-auto text-center">
    <h1 class="text-4xl md:text-5xl font-black mb-4"><?= $appName ?></h1>
    <p class="text-blue-200 text-lg mb-8">Disposable Email API — generate temp emails, receive & read messages via REST API</p>
    <div class="flex flex-wrap justify-center gap-3">
      <a href="#endpoints" class="bg-white text-blue-700 font-black px-6 py-3 rounded-xl hover:bg-blue-50 transition shadow">View Endpoints</a>
      <a href="tester.php" class="bg-blue-800 text-white font-black px-6 py-3 rounded-xl hover:bg-blue-900 transition">Try API Live</a>
      <a href="index.php?page=register" class="bg-green-500 text-white font-black px-6 py-3 rounded-xl hover:bg-green-600 transition">Get API Key</a>
    </div>
  </div>
</div>

<div class="max-w-6xl mx-auto px-4 py-10 flex flex-col lg:flex-row gap-8">

<!-- SIDEBAR -->
<aside class="lg:w-56 shrink-0">
  <div class="bg-white rounded-xl border shadow-sm p-4 sticky top-20">
    <p class="text-xs font-black text-gray-400 uppercase mb-3">Contents</p>
    <nav class="space-y-1 text-sm">
      <?php foreach ([
        'overview'=>'Overview','authentication'=>'Authentication','rate-limits'=>'Rate Limits',
        'endpoints'=>'Endpoints','gen-email'=>'↳ Generate Email','get-messages'=>'↳ Get Messages',
        'read-message'=>'↳ Read Message','login'=>'↳ Login','get-domains'=>'↳ Get Domains',
        'status'=>'↳ Check Status','examples'=>'Examples','js-example'=>'↳ JavaScript',
        'php-example'=>'↳ PHP','errors'=>'Error Codes','domains-list'=>'Available Domains'
      ] as $id => $label): ?>
      <a href="#<?= $id ?>" class="block px-3 py-1.5 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition font-medium text-gray-600"><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</aside>

<!-- MAIN CONTENT -->
<main class="flex-1 space-y-10">

<!-- OVERVIEW -->
<section id="overview" class="section bg-white rounded-2xl border shadow-sm p-8">
  <h2 class="text-2xl font-black mb-4">Overview</h2>
  <p class="text-gray-600 mb-4"><?= $appName ?> provides a simple REST API to generate disposable email addresses, receive messages, and read email content. Useful for testing, automation, and privacy.</p>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-blue-50 p-4 rounded-xl border border-blue-100">
      <p class="font-black text-blue-700">Base URL</p>
      <code class="text-xs text-blue-600"><?= $appUrl ?>/api/</code>
    </div>
    <div class="bg-green-50 p-4 rounded-xl border border-green-100">
      <p class="font-black text-green-700">Methods</p>
      <p class="text-xs text-green-600">GET & POST</p>
    </div>
    <div class="bg-purple-50 p-4 rounded-xl border border-purple-100">
      <p class="font-black text-purple-700">Response</p>
      <p class="text-xs text-purple-600">JSON (UTF-8)</p>
    </div>
  </div>
</section>

<!-- AUTHENTICATION -->
<section id="authentication" class="section bg-white rounded-2xl border shadow-sm p-8">
  <h2 class="text-2xl font-black mb-4">Authentication</h2>
  <p class="text-gray-600 mb-4">All API calls require your API key passed as a query parameter or HTTP header.</p>
  <div class="space-y-3">
    <div class="bg-gray-50 p-4 rounded-xl border">
      <p class="text-xs font-black text-gray-500 mb-2">Query Parameter (Recommended)</p>
      <pre><?= $appUrl ?>/api/?key=YOUR_API_KEY&action=gen_email</pre>
    </div>
    <div class="bg-gray-50 p-4 rounded-xl border">
      <p class="text-xs font-black text-gray-500 mb-2">HTTP Header</p>
      <pre>X-API-Key: YOUR_API_KEY</pre>
    </div>
  </div>
  <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-sm text-yellow-800">
    <strong>Domain Lock:</strong> You can restrict your API key to specific domains in your dashboard. If set, requests from other origins will be blocked.
  </div>
</section>

<!-- RATE LIMITS -->
<section id="rate-limits" class="section bg-white rounded-2xl border shadow-sm p-8">
  <h2 class="text-2xl font-black mb-4">Rate Limits</h2>
  <p class="text-gray-600 mb-4">Each API key has a configurable request limit (set by admin). When exceeded, a <code class="inline">429</code> response is returned.</p>
  <table class="w-full text-sm border rounded-xl overflow-hidden">
    <thead class="bg-gray-100 font-black text-gray-600 text-xs uppercase">
      <tr><th class="p-3 text-left">Field</th><th class="p-3 text-left">Value</th></tr>
    </thead>
    <tbody>
      <tr class="border-t"><td class="p-3 font-medium">Default Limit</td><td class="p-3"><?= DEFAULT_LIMIT_COUNT ?> requests / <?= DEFAULT_LIMIT_PERIOD ?></td></tr>
      <tr class="border-t bg-gray-50"><td class="p-3 font-medium">Unlimited value</td><td class="p-3"><code class="inline">-1</code></td></tr>
      <tr class="border-t"><td class="p-3 font-medium">Error Code</td><td class="p-3">429 Too Many Requests</td></tr>
    </tbody>
  </table>
</section>

<!-- ENDPOINTS -->
<section id="endpoints" class="section">
  <h2 class="text-2xl font-black mb-4">Endpoints</h2>

  <!-- gen_email -->
  <div id="gen-email" class="section bg-white rounded-2xl border shadow-sm p-6 mb-4">
    <div class="flex items-center gap-3 mb-3">
      <span class="bg-blue-100 text-blue-700 font-black text-xs px-2 py-1 rounded">GET</span>
      <code class="inline text-sm">?action=gen_email</code>
    </div>
    <p class="text-gray-600 text-sm mb-4">Generate a new disposable email address and password.</p>
    <h4 class="font-black text-sm mb-2">Parameters</h4>
    <table class="w-full text-xs border rounded-xl overflow-hidden mb-4">
      <thead class="bg-gray-50 font-black text-gray-500 uppercase"><tr><th class="p-2 text-left">Param</th><th class="p-2 text-left">Required</th><th class="p-2 text-left">Description</th></tr></thead>
      <tbody>
        <tr class="border-t"><td class="p-2 font-mono">key</td><td class="p-2 text-red-500">Yes</td><td class="p-2">Your API key</td></tr>
        <tr class="border-t bg-gray-50"><td class="p-2 font-mono">domain</td><td class="p-2 text-gray-400">No</td><td class="p-2">Mail domain (default: <?= $domain ?>)</td></tr>
      </tbody>
    </table>
    <h4 class="font-black text-sm mb-2">Response</h4>
    <pre>{"status":"success","email":"abc123@<?= $domain ?>","password":"f3a2b1c0"}</pre>
  </div>

  <!-- get_messages -->
  <div id="get-messages" class="section bg-white rounded-2xl border shadow-sm p-6 mb-4">
    <div class="flex items-center gap-3 mb-3">
      <span class="bg-green-100 text-green-700 font-black text-xs px-2 py-1 rounded">GET</span>
      <code class="inline text-sm">?action=get_messages</code>
    </div>
    <p class="text-gray-600 text-sm mb-4">Fetch inbox messages for a temp email address.</p>
    <table class="w-full text-xs border rounded-xl overflow-hidden mb-4">
      <thead class="bg-gray-50 font-black text-gray-500 uppercase"><tr><th class="p-2 text-left">Param</th><th class="p-2 text-left">Required</th><th class="p-2 text-left">Description</th></tr></thead>
      <tbody>
        <tr class="border-t"><td class="p-2 font-mono">key</td><td class="p-2 text-red-500">Yes</td><td class="p-2">API key</td></tr>
        <tr class="border-t bg-gray-50"><td class="p-2 font-mono">email</td><td class="p-2 text-red-500">Yes</td><td class="p-2">Temp email address</td></tr>
        <tr class="border-t"><td class="p-2 font-mono">password</td><td class="p-2 text-red-500">Yes</td><td class="p-2">Temp email password</td></tr>
      </tbody>
    </table>
    <pre>{"status":"success","count":1,"messages":[{"id":42,"sender":"no-reply@site.com","subject":"Verify Email","timestamp":1700000000,"seen":false}]}</pre>
  </div>

  <!-- read_message -->
  <div id="read-message" class="section bg-white rounded-2xl border shadow-sm p-6 mb-4">
    <div class="flex items-center gap-3 mb-3">
      <span class="bg-purple-100 text-purple-700 font-black text-xs px-2 py-1 rounded">GET</span>
      <code class="inline text-sm">?action=read_message</code>
    </div>
    <p class="text-gray-600 text-sm mb-4">Fetch the HTML content of a specific message.</p>
    <table class="w-full text-xs border rounded-xl overflow-hidden mb-4">
      <thead class="bg-gray-50 font-black text-gray-500 uppercase"><tr><th class="p-2 text-left">Param</th><th class="p-2 text-left">Required</th><th class="p-2 text-left">Description</th></tr></thead>
      <tbody>
        <tr class="border-t"><td class="p-2 font-mono">key</td><td class="p-2 text-red-500">Yes</td><td class="p-2">API key</td></tr>
        <tr class="border-t bg-gray-50"><td class="p-2 font-mono">id</td><td class="p-2 text-red-500">Yes</td><td class="p-2">Message ID from get_messages</td></tr>
        <tr class="border-t"><td class="p-2 font-mono">email</td><td class="p-2 text-red-500">Yes</td><td class="p-2">Temp email</td></tr>
        <tr class="border-t bg-gray-50"><td class="p-2 font-mono">password</td><td class="p-2 text-red-500">Yes</td><td class="p-2">Temp email password</td></tr>
      </tbody>
    </table>
    <pre>{"status":"success","content":"&lt;html&gt;...email body...&lt;/html&gt;"}</pre>
  </div>

  <!-- login -->
  <div id="login" class="section bg-white rounded-2xl border shadow-sm p-6 mb-4">
    <div class="flex items-center gap-3 mb-3">
      <span class="bg-yellow-100 text-yellow-700 font-black text-xs px-2 py-1 rounded">GET</span>
      <code class="inline text-sm">?action=login</code>
    </div>
    <p class="text-gray-600 text-sm mb-4">Verify credentials for an existing temp email account.</p>
    <pre>{"status":"success","authenticated":true,"email":"abc123@<?= $domain ?>"}</pre>
  </div>

  <!-- get_domains -->
  <div id="get-domains" class="section bg-white rounded-2xl border shadow-sm p-6 mb-4">
    <div class="flex items-center gap-3 mb-3">
      <span class="bg-indigo-100 text-indigo-700 font-black text-xs px-2 py-1 rounded">GET</span>
      <code class="inline text-sm">?action=get_domains</code>
    </div>
    <p class="text-gray-600 text-sm mb-4">List all mail domains accessible by your API key (public + your private ones).</p>
    <pre>{"status":"success","count":2,"domains":[{"id":"srv_abc","domain":"<?= $domain ?>","name":"Main Domain","public":true,"owned":false},{"id":"srv_xyz","domain":"private.com","public":false,"owned":true}]}</pre>
  </div>

  <!-- check_status -->
  <div id="status" class="section bg-white rounded-2xl border shadow-sm p-6 mb-4">
    <div class="flex items-center gap-3 mb-3">
      <span class="bg-gray-100 text-gray-700 font-black text-xs px-2 py-1 rounded">GET</span>
      <code class="inline text-sm">?action=check_status</code>
    </div>
    <p class="text-gray-600 text-sm mb-2">Check if the API is operational. No API key required.</p>
    <pre>{"status":"success","message":"System Operational","version":"2.0"}</pre>
  </div>
</section>

<!-- EXAMPLES -->
<section id="examples" class="section bg-white rounded-2xl border shadow-sm p-8">
  <h2 class="text-2xl font-black mb-6">Code Examples</h2>

  <div id="js-example" class="section mb-8">
    <h3 class="text-lg font-black mb-3 text-blue-600">JavaScript (Fetch)</h3>
<pre>const API_KEY = 'your_api_key_here';
const BASE    = '<?= $appUrl ?>/api/';

// 1. Generate email
const res   = await fetch(`${BASE}?key=${API_KEY}&action=gen_email`);
const data  = await res.json();
const email = data.email;
const pass  = data.password;

// 2. Check inbox
const inbox = await fetch(`${BASE}?key=${API_KEY}&action=get_messages&email=${email}&password=${pass}`);
const mails = await inbox.json();

// 3. Read a message
if (mails.count > 0) {
  const id  = mails.messages[0].id;
  const msg = await fetch(`${BASE}?key=${API_KEY}&action=read_message&id=${id}&email=${email}&password=${pass}`);
  const body = await msg.json();
  console.log(body.content);
}</pre>
  </div>

  <div id="php-example" class="section">
    <h3 class="text-lg font-black mb-3 text-purple-600">PHP (cURL)</h3>
<pre>$key  = 'your_api_key_here';
$base = '<?= $appUrl ?>/api/';

function apiCall($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// Generate
$account = apiCall("{$base}?key={$key}&action=gen_email");
$email   = $account['email'];
$pass    = $account['password'];

// Messages
$inbox = apiCall("{$base}?key={$key}&action=get_messages&email={$email}&password={$pass}");

// Read first
if ($inbox['count'] > 0) {
    $id  = $inbox['messages'][0]['id'];
    $msg = apiCall("{$base}?key={$key}&action=read_message&id={$id}&email={$email}&password={$pass}");
    echo $msg['content'];
}</pre>
  </div>
</section>

<!-- ERROR CODES -->
<section id="errors" class="section bg-white rounded-2xl border shadow-sm p-8">
  <h2 class="text-2xl font-black mb-4">Error Codes</h2>
  <table class="w-full text-sm border rounded-xl overflow-hidden">
    <thead class="bg-gray-100 font-black text-gray-600 text-xs uppercase">
      <tr><th class="p-3 text-left">HTTP Code</th><th class="p-3 text-left">Meaning</th></tr>
    </thead>
    <tbody>
      <?php foreach ([
        [200,'Success'],
        [400,'Bad Request – Missing required parameters'],
        [401,'Unauthorized – Invalid API key or credentials'],
        [403,'Forbidden – Domain not allowed or key inactive'],
        [429,'Too Many Requests – Rate limit exceeded'],
        [500,'Server Error – Mail server unreachable or internal error'],
        [503,'Maintenance – System temporarily offline'],
      ] as [$code,$msg]): ?>
      <tr class="border-t <?= $code===200?'bg-green-50':($code>=500?'bg-red-50':'') ?>">
        <td class="p-3 font-mono font-black text-<?= $code===200?'green':($code>=500?'red':'orange') ?>-600"><?= $code ?></td>
        <td class="p-3 text-gray-600"><?= $msg ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="mt-4">
    <p class="text-sm font-bold mb-2">Error Response Format:</p>
    <pre>{"status":"error","message":"Descriptive error message here"}</pre>
  </div>
</section>

<!-- AVAILABLE DOMAINS -->
<section id="domains-list" class="section bg-white rounded-2xl border shadow-sm p-8">
  <h2 class="text-2xl font-black mb-4">Available Public Domains</h2>
  <?php if (empty($publicDomains)): ?>
  <p class="text-gray-500 text-sm">No public domains configured. Contact admin or add your own domain.</p>
  <?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
    <?php foreach ($publicDomains as $sv): ?>
    <div class="flex items-center gap-3 p-4 bg-green-50 border border-green-200 rounded-xl">
      <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center text-white font-black text-sm">@</div>
      <div>
        <p class="font-black text-gray-800"><?= htmlspecialchars($sv['domain']) ?></p>
        <p class="text-xs text-gray-500"><?= htmlspecialchars($sv['name'] ?? $sv['domain']) ?></p>
      </div>
      <span class="ml-auto text-xs bg-green-100 text-green-700 font-bold px-2 py-0.5 rounded-full">Public</span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <p class="text-xs text-gray-400 mt-4">Private domains are only visible to their owners. To use a specific domain, pass <code class="inline">domain=yourdomain.com</code> in your <code class="inline">gen_email</code> call.</p>
</section>

</main>
</div>

<!-- FOOTER -->
<footer class="bg-gray-900 text-gray-400 py-8 px-4 mt-12">
  <div class="max-w-6xl mx-auto text-center">
    <p class="font-black text-white text-xl mb-2"><?= $appName ?></p>
    <p class="text-sm">Temporary Email API Service | <a href="tester.php" class="text-blue-400 hover:underline">API Tester</a> | <a href="index.php?page=register" class="text-blue-400 hover:underline">Get API Key</a></p>
  </div>
</footer>

<script>
// Smooth scroll for sidebar links
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    e.preventDefault();
    const el = document.querySelector(a.getAttribute('href'));
    if (el) el.scrollIntoView({behavior:'smooth'});
  });
});
</script>
</body>
</html>
