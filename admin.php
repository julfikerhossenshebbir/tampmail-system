<?php
/**
 * TempMail Pro - Admin Panel
 */
session_start();
require_once __DIR__ . '/includes/bootstrap.php';
systemGate(); // Admin can still access even in maintenance

$page    = clean($_GET['page'] ?? (isset($_SESSION['ak']) ? 'dashboard' : 'login'));
$msg     = $_SESSION['flash_msg']  ?? '';
$msgType = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

function flash($m, $t = 'success') { $_SESSION['flash_msg'] = $m; $_SESSION['flash_type'] = $t; }

// ─── POST HANDLERS ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean($_POST['action'] ?? '');
    $dbKeys = Database::read(KEY_FILE);

    if ($action === 'login') {
        $loginId  = clean($_POST['login_id'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '';
        try { Auth::checkRateLimit($ip, 'admin_login'); } catch (Exception $e) { flash($e->getMessage(),'error'); redirect('admin.php'); }
        foreach ($dbKeys as $key => $data) {
            if ($data['role'] === 'admin' && ($data['id'] === $loginId || $data['email'] === $loginId) && Auth::verifyPassword($password, $data['password'])) {
                Auth::clearRateLimit($ip, 'admin_login');
                $_SESSION['ak']           = $key;
                $_SESSION['admin_user']   = $data['id'];
                Logger::info("Admin login: " . $data['id']);
                redirect('admin.php');
            }
        }
        flash('Invalid admin credentials.', 'error');
        redirect('admin.php');
    }

    if (!isset($_SESSION['ak'])) { flash('Please login.','error'); redirect('admin.php'); }
    if (!Auth::verifyCsrf($_POST['csrf'] ?? '')) { flash('Session expired.','error'); redirect('admin.php'); }

    // ── SAVE USER ─────────────────────────────────────────────────────────────
    if (in_array($action, ['add_user', 'edit_user'])) {
        $name     = clean($_POST['name']     ?? '');
        $username = clean($_POST['username'] ?? '');
        $email    = clean($_POST['email']    ?? '');
        $phone    = clean($_POST['phone']    ?? '');
        $telegram = clean($_POST['telegram'] ?? '');
        $role     = in_array($_POST['role'] ?? '', ['admin','user']) ? $_POST['role'] : 'user';
        $active   = ($_POST['active'] ?? '0') === '1' ? '1' : '0';
        $domain   = clean($_POST['domain']   ?? '');
        $limit    = clean($_POST['limit']    ?? '15');
        $period   = in_array($_POST['period'] ?? '', ['day','week']) ? $_POST['period'] : 'day';
        $customKey = trim($_POST['api_key'] ?? '');
        if (empty($customKey)) $customKey = Auth::generateApiKey();
        $editKey  = $_POST['edit_key'] ?? '';

        if ($action === 'add_user') {
            if (isset($dbKeys[$customKey])) { flash('API key already exists!','error'); }
            else {
                $prefDomains = clean($_POST['preferred_domains'] ?? '');
                $dbKeys[$customKey] = [
                    'id' => $username, 'name' => $name, 'email' => $email, 'phone' => $phone,
                    'telegram' => $telegram, 'password' => Auth::hashPassword($_POST['password'] ?? bin2hex(random_bytes(8))),
                    'role' => $role, 'active' => $active, 'email_verified' => '1',
                    'allowed_domain' => $domain, 'preferred_domains' => $prefDomains,
                    'limit_count' => $limit, 'limit_period' => $period,
                    'created_at' => time()
                ];
                Database::write(KEY_FILE, $dbKeys);
                flash('User added successfully!');
            }
        } else {
            if (!isset($dbKeys[$editKey])) { flash('User not found.','error'); }
            else {
                if ($customKey !== $editKey) {
                    if (isset($dbKeys[$customKey])) { flash('New key already in use!','error'); goto done; }
                    $dbKeys[$customKey] = $dbKeys[$editKey];
                    unset($dbKeys[$editKey]);
                    $editKey = $customKey;
                }
                $prefDomains = clean($_POST['preferred_domains'] ?? '');
                $dbKeys[$editKey] = array_merge($dbKeys[$editKey], [
                    'id' => $username, 'name' => $name, 'email' => $email, 'phone' => $phone,
                    'telegram' => $telegram, 'role' => $role, 'active' => $active,
                    'allowed_domain' => $domain, 'preferred_domains' => $prefDomains,
                    'limit_count' => $limit, 'limit_period' => $period
                ]);
                if (!empty($_POST['password'])) $dbKeys[$editKey]['password'] = Auth::hashPassword($_POST['password']);
                Database::write(KEY_FILE, $dbKeys);
                flash('User updated!');
            }
        }
        done:
        redirect('admin.php');
    }

    // ── BULK DELETE USERS ─────────────────────────────────────────────────────
    if ($action === 'bulk_delete_users') {
        $keys = $_POST['keys'] ?? [];
        foreach ($keys as $k) { if ($k !== $_SESSION['ak']) unset($dbKeys[$k]); }
        Database::write(KEY_FILE, $dbKeys);
        flash('Selected users deleted.');
        redirect('admin.php');
    }

    // ── BULK DELETE MAILS ─────────────────────────────────────────────────────
    if ($action === 'bulk_delete_mails') {
        $dbMails = Database::read(GLOBAL_INDEX_FILE);
        foreach ($_POST['emails'] ?? [] as $e) unset($dbMails[$e]);
        Database::write(GLOBAL_INDEX_FILE, $dbMails);
        flash('Selected emails deleted.');
        redirect('admin.php?page=mails');
    }

    // ── CREATE TEMP MAIL ──────────────────────────────────────────────────────
    if ($action === 'create_temp_mail') {
        $prefix = preg_replace('/[^a-zA-Z0-9_.]/', '', clean($_POST['mail_prefix'] ?? ''));
        $pass   = clean($_POST['mail_password'] ?? '');
        $domain = clean(isset($_POST['mail_domain']) ? $_POST['mail_domain'] : '');
        if (empty($domain)) { $dfltSrv = MailHandler::getDefaultServer(); $domain = $dfltSrv ? $dfltSrv['domain'] : ''; }
        if (empty($domain)) { flash('No active mail server found. Add a server first.', 'error'); redirect('admin.php?page=mails'); }
        if (empty($prefix)) $prefix = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 8);
        if (empty($pass))   $pass   = bin2hex(random_bytes(4));
        $email   = $prefix . "@" . $domain;
        $dbMails = Database::read(GLOBAL_INDEX_FILE);
        if (isset($dbMails[$email])) { flash("Email already exists: $email", 'error'); }
        else {
            $dbMails[$email] = ['uid' => $_SESSION['admin_user'], 'hash' => password_hash($pass, PASSWORD_DEFAULT), 'time' => time(), 'domain' => $domain];
            Database::write(GLOBAL_INDEX_FILE, $dbMails);
            flash("Temp Mail Created: $email (Pass: $pass)");
        }
        redirect('admin.php?page=mails');
    }

    // ── RESET MAIL PASSWORD ───────────────────────────────────────────────────
    if ($action === 'reset_mail_pass') {
        $email  = clean($_POST['target_email'] ?? '');
        $newPass = clean($_POST['new_password'] ?? '');
        $dbMails = Database::read(GLOBAL_INDEX_FILE);
        if (isset($dbMails[$email]) && !empty($newPass)) {
            $dbMails[$email]['hash'] = password_hash($newPass, PASSWORD_DEFAULT);
            Database::write(GLOBAL_INDEX_FILE, $dbMails);
            flash("Password reset for $email");
        }
        redirect('admin.php?page=mails');
    }

    // ── SAVE MAIL SERVER ──────────────────────────────────────────────────────
    if ($action === 'save_mail_server') {
        $servers  = Database::read(MAIL_SERVERS_FILE);
        $editId   = clean($_POST['edit_id'] ?? '');
        $serverId = !empty($editId) ? $editId : 'srv_' . bin2hex(random_bytes(6));
        $servers[$serverId] = [
            'name'      => clean($_POST['srv_name']      ?? ''),
            'domain'    => strtolower(clean($_POST['srv_domain']  ?? '')),
            'imap_host' => clean($_POST['imap_host'] ?? ''),
            'imap_port' => (int)clean($_POST['imap_port'] ?? 993),
            'imap_ssl'  => ($_POST['imap_ssl'] ?? '1') === '1' ? 1 : 0,
            'imap_user' => clean($_POST['imap_user'] ?? ''),
            'imap_pass' => $_POST['imap_pass'] ?? '',
            'is_public' => ($_POST['is_public'] ?? '1') === '1' ? 1 : 0,
            'owner_id'  => clean($_POST['owner_id'] ?? ''),
            'active'    => ($_POST['srv_active'] ?? '1') === '1' ? 1 : 0,
            'created_at'=> time()
        ];
        Database::write(MAIL_SERVERS_FILE, $servers);
        flash('Mail server saved!');
        redirect('admin.php?page=servers');
    }

    // ── DELETE MAIL SERVER ────────────────────────────────────────────────────
    if ($action === 'delete_mail_server') {
        $servers = Database::read(MAIL_SERVERS_FILE);
        $sid     = clean($_POST['server_id'] ?? '');
        unset($servers[$sid]);
        Database::write(MAIL_SERVERS_FILE, $servers);
        flash('Server deleted.');
        redirect('admin.php?page=servers');
    }

    // ── TEST SMTP ─────────────────────────────────────────────────────────────
    if ($action === 'test_smtp') {
        $result = Mailer::test(SMTP_HOST, SMTP_PORT, SMTP_SECURE, SMTP_USER, SMTP_PASS);
        flash($result['message'], $result['success'] ? 'success' : 'error');
        redirect('admin.php?page=settings');
    }

    if ($action === 'logout') { session_destroy(); redirect('admin.php'); }
}

if (!isset($_SESSION['ak']) && $page !== 'login') redirect('admin.php');
$csrf    = Auth::csrfToken();
$allKeys = Database::read(KEY_FILE);
$allMails= Database::read(GLOBAL_INDEX_FILE);
$servers = Database::read(MAIL_SERVERS_FILE);
$usage   = Database::read(USAGE_FILE);
$appName = APP_NAME;
$adminUser = $_SESSION['admin_user'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $appName ?> Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body { background:#f3f4f6; font-family:'Segoe UI',system-ui,sans-serif; }
.tab-btn { @apply px-4 py-2 rounded-lg text-sm font-bold transition; }
.tab-btn.active { @apply bg-blue-600 text-white; }
.tab-btn:not(.active) { @apply bg-white text-gray-600 border hover:bg-gray-50; }
</style>
</head>
<body class="text-gray-800">

<?php if ($msg): ?>
<div id="toast" class="fixed top-4 right-4 px-5 py-3 rounded-xl shadow-lg text-white font-bold z-50 text-sm flex items-center gap-2 <?= $msgType==='error'?'bg-red-500':'bg-green-500' ?>">
  <?= htmlspecialchars($msg) ?>
</div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t){t.style.opacity='0';t.style.transition='opacity .4s';setTimeout(()=>t.remove(),400);}},4000);</script>
<?php endif; ?>

<?php if (!isset($_SESSION['ak'])): ?>
<!-- LOGIN -->
<div class="min-h-screen flex items-center justify-center bg-gray-900 p-4">
  <div class="w-full max-w-sm bg-white rounded-2xl shadow-2xl p-8">
    <h1 class="text-2xl font-black text-center mb-1"><?= $appName ?></h1>
    <p class="text-center text-gray-500 text-sm mb-6">Admin Portal</p>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="login">
      <div><label class="block text-xs font-bold text-gray-500 mb-1">Admin ID or Email</label>
        <input type="text" name="login_id" required class="w-full p-3 bg-gray-50 border rounded-lg outline-none focus:ring-2 focus:ring-blue-500"></div>
      <div><label class="block text-xs font-bold text-gray-500 mb-1">Password</label>
        <input type="password" name="password" required class="w-full p-3 bg-gray-50 border rounded-lg outline-none focus:ring-2 focus:ring-blue-500"></div>
      <button type="submit" class="w-full bg-blue-600 text-white font-black py-3 rounded-xl hover:bg-blue-700 transition">AUTHORIZE</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ADMIN NAV -->
<nav class="bg-gray-900 text-white sticky top-0 z-40 shadow-xl">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
    <div class="font-black text-lg text-blue-400"><?= $appName ?> <span class="text-white text-sm font-normal">Admin</span></div>
    <div class="flex items-center gap-2 text-sm">
      <?php
      $navItems = ['dashboard'=>'Dashboard','mails'=>'Temp Mails','servers'=>'Mail Servers','settings'=>'Settings'];
      foreach ($navItems as $p => $label):
      ?>
      <a href="admin.php?page=<?= $p ?>" class="px-3 py-1.5 rounded-lg font-bold transition <?= $page===$p?'bg-blue-600 text-white':'text-gray-300 hover:text-white hover:bg-gray-700' ?>"><?= $label ?></a>
      <?php endforeach; ?>
      <form method="POST" class="ml-2"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf" value="<?= $csrf ?>">
        <button class="bg-red-600 px-3 py-1.5 rounded-lg font-bold hover:bg-red-700 transition text-xs">Logout</button></form>
    </div>
  </div>
</nav>

<div class="max-w-7xl mx-auto p-4 md:p-6 mt-4">

<?php if (!SYSTEM_ONLINE): ?>
<div class="bg-yellow-50 border border-yellow-300 rounded-xl p-4 mb-6 flex items-center gap-3">
  <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
  <p class="font-bold text-yellow-800 text-sm">⚠️ System is in <strong>MAINTENANCE MODE</strong>. Public users cannot access the site. Edit <code>.env</code> to change.</p>
</div>
<?php endif; ?>

<?php
// ──────────────────────── DASHBOARD ──────────────────────────────────────────
if ($page === 'dashboard'):
$totalUsers = count($allKeys);
$totalMails = count($allMails);
$activeUsers = count(array_filter($allKeys, function($u) { return (isset($u['active'])?$u['active']:'0')==='1'; }));
$pendingUsers = $totalUsers - $activeUsers;
?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <?php $stats = [['Total Users',$totalUsers,'blue'],['Active',$activeUsers,'green'],['Pending',$pendingUsers,'yellow'],['Temp Mails',$totalMails,'purple']];
  foreach ($stats as [$label,$val,$color]): ?>
  <div class="bg-white p-5 rounded-xl border shadow-sm flex items-center justify-between">
    <div><p class="text-xs text-gray-500 font-bold uppercase"><?= $label ?></p><p class="text-3xl font-black text-<?= $color ?>-600"><?= $val ?></p></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- USERS TABLE -->
<div class="bg-white rounded-xl border shadow-sm mb-6">
  <div class="p-5 border-b flex flex-col md:flex-row justify-between items-center gap-3">
    <h2 class="font-black text-lg">User Management</h2>
    <div class="flex gap-3 w-full md:w-auto">
      <input type="text" id="searchUser" oninput="filterTable('searchUser','userTbl')" placeholder="Search users..." class="p-2 border rounded-lg text-sm flex-1 md:w-64 outline-none focus:ring-2 focus:ring-blue-400">
      <a href="admin.php?page=add_user" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-bold text-sm hover:bg-blue-700 transition whitespace-nowrap">+ Add User</a>
    </div>
  </div>
  <form method="POST" onsubmit="return confirm('Delete selected users?')">
    <input type="hidden" name="action" value="bulk_delete_users">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <div class="overflow-x-auto">
      <table id="userTbl" class="w-full text-sm whitespace-nowrap">
        <thead class="bg-gray-50 text-gray-500 text-xs font-black uppercase">
          <tr>
            <th class="p-4 w-8"><input type="checkbox" onchange="toggleAll(this,'uc','delUBtn')"></th>
            <th class="p-4">Identity</th>
            <th class="p-4">API Key</th>
            <th class="p-4">Limits / Usage</th>
            <th class="p-4">Role & Status</th>
            <th class="p-4 text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_reverse($allKeys, true) as $k => $v):
            $todayUsage = (int)($usage[$v['id']]['count'] ?? 0);
          ?>
          <tr class="border-b hover:bg-gray-50 transition">
            <td class="p-4"><input type="checkbox" class="uc" name="keys[]" value="<?= $k ?>" <?= $k===$_SESSION['ak']?'disabled':'' ?> onchange="toggleDelete('uc','delUBtn')"></td>
            <td class="p-4">
              <p class="font-bold text-gray-800"><?= htmlspecialchars($v['name']) ?> <span class="text-blue-600 text-xs">@<?= htmlspecialchars($v['id']) ?></span></p>
              <p class="text-xs text-gray-400"><?= htmlspecialchars($v['email']) ?> | <?= htmlspecialchars($v['phone']) ?></p>
            </td>
            <td class="p-4 font-mono text-xs text-indigo-600 max-w-[140px] truncate"><?= $k ?></td>
            <td class="p-4 text-xs">
              <p class="font-medium">Limit: <strong><?= $v['limit_count'] ?>/<?= $v['limit_period'] ?></strong></p>
              <p class="text-gray-400">Used today: <?= $todayUsage ?></p>
            </td>
            <td class="p-4">
              <span class="inline-block px-2 py-0.5 rounded text-xs font-bold <?= $v['role']==='admin'?'bg-purple-100 text-purple-700':'bg-gray-200 text-gray-700' ?> mb-1"><?= $v['role'] ?></span><br>
              <?= ($v['active']??'0')==='1'?'<span class="text-green-700 font-bold text-xs bg-green-100 px-2 py-0.5 rounded">Active</span>':'<span class="text-red-700 font-bold text-xs bg-red-100 px-2 py-0.5 rounded">Pending</span>' ?>
              <?= ($v['email_verified']??'0')==='1'?'':'<span class="text-yellow-700 font-bold text-xs bg-yellow-100 px-2 py-0.5 rounded ml-1">Unverified</span>' ?>
            </td>
            <td class="p-4 text-center"><a href="admin.php?page=edit_user&key=<?= urlencode($k) ?>" class="bg-gray-100 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-gray-200 transition">Edit</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="p-4 bg-gray-50 border-t"><button type="submit" id="delUBtn" class="bg-red-600 text-white px-5 py-2 rounded-lg text-sm font-bold hover:bg-red-700 hidden">Delete Selected</button></div>
  </form>
</div>

<?php
// ──────────────────────── ADD / EDIT USER ────────────────────────────────────
elseif (in_array($page, ['add_user','edit_user'])):
$editKey = $_GET['key'] ?? '';
$u = isset($allKeys[$editKey]) ? $allKeys[$editKey] : ['name'=>'','id'=>'','email'=>'','phone'=>'','telegram'=>'','role'=>'user','active'=>'1','allowed_domain'=>'','limit_count'=>'15','limit_period'=>'day'];
?>
<div class="bg-white rounded-xl border shadow-sm p-8 max-w-4xl mx-auto">
  <h2 class="text-2xl font-black mb-6 border-b pb-3"><?= $editKey?'Edit User':'Add New User' ?></h2>
  <form method="POST" class="space-y-6">
    <input type="hidden" name="action" value="<?= $editKey?'edit_user':'add_user' ?>">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <?php if ($editKey): ?><input type="hidden" name="edit_key" value="<?= htmlspecialchars($editKey) ?>"><?php endif; ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div><label class="block text-xs font-bold text-gray-500 mb-1">API Key</label>
        <div class="flex gap-2"><input type="text" id="apiKeyField" name="api_key" value="<?= htmlspecialchars($editKey) ?>" class="flex-1 p-2.5 bg-gray-50 border rounded-lg font-mono text-sm outline-none focus:ring-2 focus:ring-blue-500" placeholder="Auto-generated">
        <button type="button" onclick="genKey()" class="bg-gray-800 text-white px-3 rounded-lg text-sm font-bold hover:bg-gray-900">Gen</button></div>
      </div>
      <div><label class="block text-xs font-bold text-gray-500 mb-1">Password <?= $editKey?'(blank = no change)':'' ?></label>
        <input type="text" name="password" <?= $editKey?'':'required' ?> class="w-full p-2.5 bg-gray-50 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500"></div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <?php foreach (['name'=>'Full Name','username'=>'Username','email'=>'Email','phone'=>'Phone','telegram'=>'Telegram','domain'=>'Origin Domain Lock (blank=any)'] as $field => $label):
        $val = $field==='username' ? $u['id'] : ($field==='domain' ? ($u['allowed_domain']??'') : ($u[$field]??''));
      ?>
      <div><label class="block text-xs font-bold text-gray-500 mb-1"><?= $label ?></label>
        <input type="<?= $field==='email'?'email':'text' ?>" name="<?= $field ?>" value="<?= htmlspecialchars($val) ?>" <?= in_array($field,['name','username','email'])?'required':'' ?> class="w-full p-2.5 bg-gray-50 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500"></div>
      <?php endforeach; ?>
      <!-- Preferred mail domains -->
      <?php
      $allSrvDomains = [];
      foreach(Database::read(MAIL_SERVERS_FILE) as $s) if(($s['active']??0)==1) $allSrvDomains[]=$s['domain'];
      $userPrefArr = array_filter(array_map('trim', explode(',', $u['preferred_domains']??'')));
      ?>
      <div class="md:col-span-2">
        <label class="block text-xs font-bold text-gray-500 mb-2">PREFERRED MAIL DOMAINS (blank = all public, comma-separated)</label>
        <div class="flex flex-wrap gap-2 mb-2">
          <?php foreach($allSrvDomains as $sd): $chk=in_array($sd,$userPrefArr); ?>
          <label class="flex items-center gap-1.5 cursor-pointer bg-gray-50 border rounded-xl px-3 py-1.5 hover:bg-blue-50 hover:border-blue-300 transition text-sm <?=$chk?'border-blue-400 bg-blue-50':''?>">
            <input type="checkbox" class="adm-pref-chk accent-blue-600" value="<?= htmlspecialchars($sd) ?>" <?=$chk?'checked':''?> onchange="syncAdminPref()">
            @<?= htmlspecialchars($sd) ?>
          </label>
          <?php endforeach; ?>
          <?php if(empty($allSrvDomains)): ?><p class="text-xs text-gray-400">No active mail servers configured yet.</p><?php endif; ?>
        </div>
        <input type="text" id="adminPrefInput" name="preferred_domains" value="<?= htmlspecialchars($u['preferred_domains']??'') ?>" class="w-full p-2.5 bg-gray-50 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500" placeholder="domain1.com, domain2.com" oninput="syncAdminPrefText(this.value)">
      </div>
    </div>
    <div class="bg-gray-50 p-5 rounded-xl border">
      <h3 class="font-black text-sm mb-4 uppercase text-gray-700">Permissions</h3>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div><label class="block text-xs font-bold text-gray-500 mb-1">Role</label>
          <select name="role" class="w-full p-2 bg-white border rounded-lg text-sm outline-none">
            <option value="user" <?= ($u['role']??'')==='user'?'selected':'' ?>>User</option>
            <option value="admin" <?= ($u['role']??'')==='admin'?'selected':'' ?>>Admin</option>
          </select></div>
        <div><label class="block text-xs font-bold text-gray-500 mb-1">Status</label>
          <select name="active" class="w-full p-2 bg-white border rounded-lg text-sm outline-none">
            <option value="1" <?= ($u['active']??'')==='1'?'selected':'' ?>>Active</option>
            <option value="0" <?= ($u['active']??'')==='0'?'selected':'' ?>>Pending</option>
          </select></div>
        <div><label class="block text-xs font-bold text-gray-500 mb-1">Limit Count</label>
          <input type="text" name="limit" value="<?= htmlspecialchars($u['limit_count']??'15') ?>" class="w-full p-2 bg-white border rounded-lg text-sm outline-none" placeholder="-1 unlimited"></div>
        <div><label class="block text-xs font-bold text-gray-500 mb-1">Period</label>
          <select name="period" class="w-full p-2 bg-white border rounded-lg text-sm outline-none">
            <option value="day" <?= ($u['limit_period']??'')==='day'?'selected':'' ?>>Daily</option>
            <option value="week" <?= ($u['limit_period']??'')==='week'?'selected':'' ?>>Weekly</option>
          </select></div>
      </div>
    </div>
    <div class="flex gap-3">
      <button type="submit" class="bg-blue-600 text-white font-bold py-2.5 px-8 rounded-xl hover:bg-blue-700 transition">Save User</button>
      <a href="admin.php" class="bg-gray-200 text-gray-700 font-bold py-2.5 px-8 rounded-xl hover:bg-gray-300 transition">Cancel</a>
    </div>
  </form>
</div>

<?php
// ──────────────────────── MAILS PAGE ─────────────────────────────────────────
elseif ($page === 'mails'):
$allDomains = array_column(array_values($servers), 'domain');
?>
<div class="flex justify-between items-center mb-4">
  <h2 class="text-xl font-black">Temp Mails <span class="text-gray-400 font-normal text-base">(<?= count($allMails) ?>)</span></h2>
  <button onclick="document.getElementById('mailModal').classList.remove('hidden')" class="bg-green-600 text-white px-4 py-2 rounded-xl font-bold text-sm hover:bg-green-700 transition">+ Create Mail</button>
</div>
<div class="bg-white rounded-xl border shadow-sm">
  <div class="p-4 border-b"><input type="text" id="searchMail" oninput="filterTable('searchMail','mailTbl')" placeholder="Search emails..." class="p-2 border rounded-lg text-sm w-full md:w-72 outline-none focus:ring-2 focus:ring-blue-400"></div>
  <form method="POST" onsubmit="return confirm('Delete selected?')">
    <input type="hidden" name="action" value="bulk_delete_mails">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <div class="overflow-x-auto max-h-[600px]">
      <table id="mailTbl" class="w-full text-sm whitespace-nowrap">
        <thead class="bg-gray-50 text-xs font-black text-gray-500 uppercase sticky top-0">
          <tr>
            <th class="p-4 w-8"><input type="checkbox" onchange="toggleAll(this,'mc','delMBtn')"></th>
            <th class="p-4">Email</th><th class="p-4">Creator</th><th class="p-4">Domain</th><th class="p-4">Created</th><th class="p-4 text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_reverse($allMails,true) as $em => $dt): ?>
          <tr class="border-b hover:bg-gray-50">
            <td class="p-4"><input type="checkbox" class="mc" name="emails[]" value="<?= $em ?>" onchange="toggleDelete('mc','delMBtn')"></td>
            <td class="p-4 font-mono font-bold text-gray-800"><?= htmlspecialchars($em) ?></td>
            <td class="p-4 text-blue-600 font-bold">@<?= htmlspecialchars($dt['uid']) ?></td>
            <td class="p-4 text-gray-500 text-xs"><?= htmlspecialchars($dt['domain'] ?? explode('@',$em)[1] ?? '-') ?></td>
            <td class="p-4 text-gray-400 text-xs"><?= date('Y-m-d H:i',(int)($dt['time']??time())) ?></td>
            <td class="p-4 text-center"><button type="button" onclick="openReset('<?= htmlspecialchars($em) ?>')" class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-lg text-xs font-bold hover:bg-yellow-200">Reset Pass</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="p-4 bg-gray-50 border-t"><button type="submit" id="delMBtn" class="bg-red-600 text-white px-5 py-2 rounded-lg text-sm font-bold hover:bg-red-700 hidden">Delete Selected</button></div>
  </form>
</div>

<?php
// ──────────────────────── MAIL SERVERS ───────────────────────────────────────
elseif ($page === 'servers'):
$editSrv = $_GET['sid'] ?? '';
$srv = isset($servers[$editSrv]) ? $servers[$editSrv] : ['name'=>'','domain'=>'','imap_host'=>'','imap_port'=>993,'imap_ssl'=>1,'imap_user'=>'','imap_pass'=>'','is_public'=>1,'owner_id'=>'','active'=>1];
$userList = array_values(array_map(function($u){ return $u['id']; },$allKeys));
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <!-- Server Form -->
  <div class="lg:col-span-1">
    <div class="bg-white rounded-xl border shadow-sm p-6">
      <h2 class="font-black text-base mb-4 border-b pb-2"><?= $editSrv?'Edit Server':'Add Mail Server' ?></h2>
      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="save_mail_server">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <?php if ($editSrv): ?><input type="hidden" name="edit_id" value="<?= htmlspecialchars($editSrv) ?>"><?php endif; ?>
        <?php foreach (['srv_name'=>['Server Name','text','My Mail Server'],'srv_domain'=>['Domain','text','mail.example.com'],'imap_host'=>['IMAP Host','text','imap.example.com'],'imap_port'=>['IMAP Port','number','993'],'imap_user'=>['IMAP User Email','email','admin@example.com']] as $fn => [$lbl,$tp,$ph]):
          $fkey = str_replace('srv_','',$fn); $fkey = in_array($fkey,['name','domain'])?$fkey:$fkey;
          $fval = $srv[$fn] ?? $srv[$fkey] ?? '';
        ?>
        <div><label class="block text-xs font-bold text-gray-500 mb-1"><?= $lbl ?></label>
          <input type="<?= $tp ?>" name="<?= $fn ?>" value="<?= htmlspecialchars((string)$fval) ?>" class="w-full p-2 bg-gray-50 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500" placeholder="<?= $ph ?>"></div>
        <?php endforeach; ?>
        <div><label class="block text-xs font-bold text-gray-500 mb-1">IMAP Password</label>
          <input type="password" name="imap_pass" value="<?= htmlspecialchars($srv['imap_pass']??'') ?>" class="w-full p-2 bg-gray-50 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500" placeholder="IMAP password"></div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="block text-xs font-bold text-gray-500 mb-1">SSL</label>
            <select name="imap_ssl" class="w-full p-2 bg-white border rounded-lg text-sm">
              <option value="1" <?= ($srv['imap_ssl']??1)==1?'selected':'' ?>>Yes (993)</option>
              <option value="0" <?= ($srv['imap_ssl']??1)==0?'selected':'' ?>>No (143)</option>
            </select></div>
          <div><label class="block text-xs font-bold text-gray-500 mb-1">Visibility</label>
            <select name="is_public" class="w-full p-2 bg-white border rounded-lg text-sm">
              <option value="1" <?= ($srv['is_public']??1)==1?'selected':'' ?>>🌐 Public</option>
              <option value="0" <?= ($srv['is_public']??1)==0?'selected':'' ?>>🔒 Private</option>
            </select></div>
        </div>
        <div><label class="block text-xs font-bold text-gray-500 mb-1">Owner (for private)</label>
          <input type="text" name="owner_id" value="<?= htmlspecialchars($srv['owner_id']??'') ?>" list="userList" class="w-full p-2 bg-gray-50 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500" placeholder="Username">
          <datalist id="userList"><?php foreach ($userList as $uid): ?><option value="<?= htmlspecialchars($uid) ?>"><?php endforeach; ?></datalist>
        </div>
        <div><label class="block text-xs font-bold text-gray-500 mb-1">Status</label>
          <select name="srv_active" class="w-full p-2 bg-white border rounded-lg text-sm">
            <option value="1" <?= ($srv['active']??1)==1?'selected':'' ?>>Active</option>
            <option value="0" <?= ($srv['active']??1)==0?'selected':'' ?>>Disabled</option>
          </select></div>
        <div class="flex gap-2 pt-2">
          <button type="submit" class="flex-1 bg-blue-600 text-white font-bold py-2 rounded-xl hover:bg-blue-700 transition text-sm">Save Server</button>
          <?php if ($editSrv): ?><a href="admin.php?page=servers" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl font-bold text-sm hover:bg-gray-300 transition">Cancel</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Servers List -->
  <div class="lg:col-span-2">
    <div class="bg-white rounded-xl border shadow-sm">
      <div class="p-5 border-b"><h2 class="font-black text-base">Configured Mail Servers (<?= count($servers) ?>)</h2></div>
      <div class="divide-y">
        <?php if (empty($servers)): ?>
        <div class="p-10 text-center text-gray-400"><p class="font-bold">No mail servers configured yet.</p><p class="text-sm mt-1">Add your first IMAP server using the form.</p></div>
        <?php else: foreach ($servers as $sid => $sv): ?>
        <div class="p-5 flex items-start justify-between gap-4">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
              <span class="font-black text-gray-800"><?= htmlspecialchars($sv['name']??'') ?></span>
              <span class="text-xs px-2 py-0.5 rounded-full font-bold <?= ($sv['is_public']??1)?'bg-green-100 text-green-700':'bg-purple-100 text-purple-700' ?>"><?= ($sv['is_public']??1)?'Public':'Private' ?></span>
              <span class="text-xs px-2 py-0.5 rounded-full font-bold <?= ($sv['active']??1)?'bg-blue-100 text-blue-700':'bg-red-100 text-red-700' ?>"><?= ($sv['active']??1)?'Active':'Disabled' ?></span>
            </div>
            <p class="text-sm font-bold text-blue-600">@<?= htmlspecialchars($sv['domain']??'') ?></p>
            <p class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($sv['imap_host']??'') ?>:<?= $sv['imap_port']??993 ?> | <?= htmlspecialchars($sv['imap_user']??'') ?></p>
            <?php if (!($sv['is_public']??1) && !empty($sv['owner_id'])): ?>
            <p class="text-xs text-purple-600 mt-0.5">Owner: @<?= htmlspecialchars($sv['owner_id']) ?></p>
            <?php endif; ?>
          </div>
          <div class="flex gap-2">
            <a href="admin.php?page=servers&sid=<?= urlencode($sid) ?>" class="bg-gray-100 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-gray-200 transition">Edit</a>
            <form method="POST" onsubmit="return confirm('Delete this server?')" class="inline">
              <input type="hidden" name="action" value="delete_mail_server">
              <input type="hidden" name="server_id" value="<?= htmlspecialchars($sid) ?>">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <button type="submit" class="bg-red-100 text-red-700 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-red-200 transition">Delete</button>
            </form>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <div class="mt-4 bg-blue-50 border border-blue-200 rounded-xl p-4 text-xs text-blue-700">
      <p class="font-black mb-1">ℹ️ Mail Server Tips:</p>
      <ul class="space-y-1 list-disc list-inside text-blue-600">
        <li><strong>Public servers:</strong> All API users can generate emails on this domain.</li>
        <li><strong>Private servers:</strong> Only the owner (and admin) can use this domain via API.</li>
        <li>The IMAP user must be the master inbox that receives all emails for the domain (catch-all).</li>
      </ul>
    </div>
  </div>
</div>

<?php
// ──────────────────────── SETTINGS ───────────────────────────────────────────
elseif ($page === 'settings'): ?>
<h2 class="text-xl font-black mb-6">System Settings</h2>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
  <!-- SMTP Info -->
  <div class="bg-white rounded-xl border shadow-sm p-6">
    <h3 class="font-black text-base mb-4 border-b pb-2">SMTP Configuration</h3>
    <p class="text-sm text-gray-500 mb-4">SMTP settings are managed in your <code class="bg-gray-100 px-1 rounded">.env</code> file in your project root.</p>
    <div class="space-y-2 text-sm">
      <?php foreach ([['Host', SMTP_HOST],['Port',SMTP_PORT],['Secure',SMTP_SECURE],['User',SMTP_USER],['From Name',SMTP_FROM_NAME],['From Email',SMTP_FROM_EMAIL]] as [$k,$v]): ?>
      <div class="flex justify-between py-1 border-b border-gray-50">
        <span class="font-bold text-gray-500"><?= $k ?></span>
        <span class="text-gray-700 font-mono text-xs"><?= $k==='Password'?'••••••':htmlspecialchars((string)$v) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <form method="POST" class="mt-4">
      <input type="hidden" name="action" value="test_smtp">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg font-bold text-sm hover:bg-blue-700 transition">Test SMTP Connection</button>
    </form>
  </div>
  <!-- Env Settings Info -->
  <div class="bg-white rounded-xl border shadow-sm p-6">
    <h3 class="font-black text-base mb-4 border-b pb-2">Environment Settings</h3>
    <div class="space-y-2 text-sm">
      <?php foreach ([
        ['App Name', APP_NAME],
        ['App URL', APP_URL],
        ['System Online', SYSTEM_ONLINE?'✅ Yes':'🔴 Offline (Maintenance)'],
        ['Email Verify', REQUIRE_EMAIL_VERIFY?'Required':'Optional'],
        ['Auto Approve', AUTO_APPROVE_USERS?'Yes':'No (Admin must approve)'],
        ['Default Limit', DEFAULT_LIMIT_COUNT . '/' . DEFAULT_LIMIT_PERIOD],
        ['OTP Expiry', OTP_EXPIRY . ' minutes'],
        ['Max Login Attempts', MAX_LOGIN_ATTEMPTS],
        ['Lockout Duration', LOCKOUT_DURATION . ' minutes'],
      ] as [$k,$v]): ?>
      <div class="flex justify-between py-1 border-b border-gray-50">
        <span class="font-bold text-gray-500"><?= $k ?></span>
        <span class="text-gray-700 text-xs"><?= htmlspecialchars((string)$v) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="text-xs text-gray-400 mt-3">Edit <code class="bg-gray-100 px-1 rounded">.env</code> file to change these settings.</p>
  </div>
</div>

<?php endif; // end pages ?>
</div><!-- /container -->

<!-- MAIL MODAL -->
<div id="mailModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50 flex">
  <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl p-6">
    <div class="flex justify-between items-center mb-4 border-b pb-2">
      <h2 class="text-lg font-black">Create Temp Mail</h2>
      <button onclick="document.getElementById('mailModal').classList.add('hidden')" class="text-gray-400 hover:text-red-500 font-black text-xl">×</button>
    </div>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="create_temp_mail">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <div><label class="block text-xs font-bold text-gray-500 mb-1">Domain</label>
        <select name="mail_domain" class="w-full p-2.5 bg-gray-50 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
          <?php foreach ($servers as $sv): if (($sv['active']??0)==1): ?>
          <option value="<?= htmlspecialchars($sv['domain']) ?>"><?= htmlspecialchars($sv['domain']) ?></option>
          <?php endif; endforeach;
          <?php if (empty($servers)): ?><option value="">No servers configured</option><?php endif; ?>
        </select></div>
      <div><label class="block text-xs font-bold text-gray-500 mb-1">Prefix (leave blank = random)</label>
        <div class="flex gap-2">
          <input type="text" id="mprefix" name="mail_prefix" class="flex-1 p-2.5 bg-gray-50 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g. john_doe">
          <button type="button" onclick="genR('mprefix',8,true)" class="bg-gray-800 text-white px-3 rounded-lg text-sm font-bold">Gen</button>
        </div></div>
      <div><label class="block text-xs font-bold text-gray-500 mb-1">Password (blank = random)</label>
        <div class="flex gap-2">
          <input type="text" id="mpass" name="mail_password" class="flex-1 p-2.5 bg-gray-50 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500" placeholder="Custom password">
          <button type="button" onclick="genR('mpass',10,false)" class="bg-gray-800 text-white px-3 rounded-lg text-sm font-bold">Gen</button>
        </div></div>
      <button type="submit" class="w-full bg-green-600 text-white font-bold py-3 rounded-xl hover:bg-green-700 transition">Create</button>
    </form>
  </div>
</div>

<!-- RESET PASS MODAL -->
<div id="resetModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50 flex">
  <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl p-6">
    <div class="flex justify-between items-center mb-4 border-b pb-2">
      <h2 class="text-lg font-black">Reset Mail Password</h2>
      <button onclick="document.getElementById('resetModal').classList.add('hidden')" class="text-gray-400 hover:text-red-500 font-black text-xl">×</button>
    </div>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="reset_mail_pass">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" id="resetTarget" name="target_email">
      <p class="text-sm font-bold text-gray-600">Email: <span id="resetDisplay" class="text-blue-600 font-mono"></span></p>
      <div><label class="block text-xs font-bold text-gray-500 mb-1">New Password</label>
        <input type="text" name="new_password" required class="w-full p-2.5 bg-gray-50 border rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500"></div>
      <button type="submit" class="w-full bg-yellow-500 text-white font-bold py-2.5 rounded-xl hover:bg-yellow-600 transition">Update Password</button>
    </form>
  </div>
</div>

<?php endif; // logged in ?>
<script>
function filterTable(inId,tblId) {
  const t = document.getElementById(inId).value.toLowerCase();
  document.querySelectorAll(`#${tblId} tbody tr`).forEach(r => r.style.display = r.innerText.toLowerCase().includes(t)?'':'none');
}
function toggleAll(m,cls,btnId) {
  document.querySelectorAll('.'+cls+':not([disabled])').forEach(c=>{ if(c.closest('tr').style.display!=='none') c.checked=m.checked; });
  toggleDelete(cls,btnId);
}
function toggleDelete(cls,btnId) {
  const b = document.getElementById(btnId);
  const n = document.querySelectorAll('.'+cls+':checked').length;
  if(b) { b.classList.toggle('hidden', n===0); }
}
function genKey() {
  const c='0123456789abcdef'; let h='';
  for(let i=0;i<32;i++) h+=c[Math.floor(Math.random()*c.length)];
  document.getElementById('apiKeyField').value='md_live_'+h;
}
function openReset(email) {
  document.getElementById('resetTarget').value=email;
  document.getElementById('resetDisplay').innerText=email;
  document.getElementById('resetModal').classList.remove('hidden');
}
function genR(id,len,prefix) {
  const c = prefix ? 'abcdefghijklmnopqrstuvwxyz0123456789' : 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let r=''; for(let i=0;i<len;i++) r+=c[Math.floor(Math.random()*c.length)];
  document.getElementById(id).value=r;
}
function syncAdminPref(){
  const checked=Array.from(document.querySelectorAll('.adm-pref-chk:checked')).map(x=>x.value);
  const inp=document.getElementById('adminPrefInput');
  if(inp){inp.value=checked.join(', ');}
}
function syncAdminPrefText(val){
  // just keep input in sync, checkboxes don't need update
}
</script>
</body>
</html>
