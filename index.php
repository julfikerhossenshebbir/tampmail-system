<?php
/**
 * TempMail Pro - User Dashboard v2
 */
session_start();
require_once __DIR__ . '/includes/bootstrap.php';
systemGate();

$page    = clean($_GET['page'] ?? (isset($_SESSION['uk']) ? 'dashboard' : 'login'));
$msg     = $_SESSION['flash_msg']  ?? '';
$msgType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

function flash($m, $t='success') { $_SESSION['flash_msg']=$m; $_SESSION['flash_type']=$t; }

/* ─── POST HANDLERS ─────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = clean($_POST['action'] ?? '');
    $dbKeys  = Database::read(KEY_FILE);
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    /* REGISTER */
    if ($action === 'register') {
        $name     = clean($_POST['name']     ?? '');
        $username = strtolower(preg_replace('/[^a-zA-Z0-9_]/','',clean($_POST['username']??'')));
        $email    = strtolower(clean($_POST['email']   ?? ''));
        $phone    = clean($_POST['phone']    ?? '');
        $telegram = clean($_POST['telegram'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm']  ?? '';
        if (strlen($password)<8) { flash('Password must be at least 8 characters.','error'); redirect('index.php?page=register'); }
        if ($password!==$confirm) { flash('Passwords do not match.','error'); redirect('index.php?page=register'); }
        if (!Auth::validEmail($email)) { flash('Invalid email address.','error'); redirect('index.php?page=register'); }
        foreach ($dbKeys as $v) {
            if ($v['id']===$username) { flash('Username taken.','error'); redirect('index.php?page=register'); }
            if ($v['email']===$email) { flash('Email already registered.','error'); redirect('index.php?page=register'); }
        }
        $role=$active=''; $role=empty($dbKeys)?'admin':'user'; $active=$role==='admin'?'1':(AUTO_APPROVE_USERS?'1':'0');
        $nk=$newKey=Auth::generateApiKey();
        $dbKeys[$newKey]=['id'=>$username,'name'=>$name,'email'=>$email,'phone'=>$phone,'telegram'=>$telegram,
            'password'=>Auth::hashPassword($password),'role'=>$role,'active'=>$active,'email_verified'=>REQUIRE_EMAIL_VERIFY?'0':'1',
            'allowed_domain'=>'','preferred_domains'=>'','limit_count'=>DEFAULT_LIMIT_COUNT,'limit_period'=>DEFAULT_LIMIT_PERIOD,'created_at'=>time()];
        Database::write(KEY_FILE,$dbKeys);
        if (REQUIRE_EMAIL_VERIFY) {
            try {
                $otp=Auth::generateOTP(); Auth::storeOTP($email,$otp,'verify',['username'=>$username]);
                (new Mailer())->sendOTP($email,$otp,'verify');
                $_SESSION['pending_verify_email']=$email;
                flash('Account created! Check your email for a 6-digit verification code.'); redirect('index.php?page=verify_email');
            } catch(Exception $e) {
                Logger::error("OTP send failed: ".$e->getMessage());
                flash('Account created but OTP email failed: '.$e->getMessage().'. Contact admin.','error'); redirect('index.php?page=login');
            }
        } else {
            $_SESSION['uk']=$newKey; $_SESSION['role']=$role; $_SESSION['username']=$username;
            flash("Welcome, $name!"); redirect('index.php?page=dashboard');
        }
    }

    /* VERIFY EMAIL */
    if ($action==='verify_email') {
        $email=clean($_POST['email']??$_SESSION['pending_verify_email']??'');
        $otp=clean($_POST['otp']??'');
        try {
            Auth::verifyOTP($email,$otp,'verify');
            $dbKeys=Database::read(KEY_FILE);
            foreach ($dbKeys as $k=>$v) if($v['email']===$email){$dbKeys[$k]['email_verified']='1';break;}
            Database::write(KEY_FILE,$dbKeys); unset($_SESSION['pending_verify_email']);
            flash('Email verified! You can now log in.'); redirect('index.php?page=login');
        } catch(Exception $e) { flash($e->getMessage(),'error'); redirect('index.php?page=verify_email'); }
    }

    /* RESEND OTP */
    if ($action==='resend_otp') {
        $email=clean($_POST['email']??$_SESSION['pending_verify_email']??'');
        if($email) try {
            $otp=Auth::generateOTP(); Auth::storeOTP($email,$otp,'verify');
            (new Mailer())->sendOTP($email,$otp,'verify'); flash('New OTP sent.');
        } catch(Exception $e){flash('Failed: '.$e->getMessage(),'error');}
        redirect('index.php?page=verify_email');
    }

    /* LOGIN */
    if ($action==='login') {
        $loginId=clean($_POST['login_id']??''); $password=$_POST['password']??'';
        try { Auth::checkRateLimit($ip,'login'); } catch(Exception $e){flash($e->getMessage(),'error');redirect('index.php?page=login');}
        $found=false;
        foreach ($dbKeys as $key=>$data) {
            if(($data['id']===$loginId||$data['email']===$loginId) && Auth::verifyPassword($password,$data['password'])){
                if(REQUIRE_EMAIL_VERIFY&&($data['email_verified']??'0')!=='1'){
                    $_SESSION['pending_verify_email']=$data['email'];
                    flash('Please verify your email first.','error'); redirect('index.php?page=verify_email');
                }
                Auth::clearRateLimit($ip,'login');
                $_SESSION['uk']=$key; $_SESSION['role']=$data['role']; $_SESSION['username']=$data['id'];
                Logger::info("Login: ".$data['id']); $found=true; redirect('index.php?page=dashboard'); break;
            }
        }
        if(!$found){flash('Invalid credentials.','error');redirect('index.php?page=login');}
    }

    /* FORGOT PASSWORD */
    if ($action==='forgot_password') {
        $email=strtolower(clean($_POST['email']??''));
        $found=false; foreach($dbKeys as $v) if($v['email']===$email){$found=true;break;}
        if($found) try {
            $otp=Auth::generateOTP(); Auth::storeOTP($email,$otp,'reset');
            (new Mailer())->sendOTP($email,$otp,'reset');
        } catch(Exception $e){Logger::error("Reset OTP: ".$e->getMessage());}
        $_SESSION['pending_reset_email']=$email;
        flash('If that email exists, a reset code was sent.'); redirect('index.php?page=reset_password');
    }

    /* RESET PASSWORD */
    if ($action==='reset_password') {
        $email=clean($_POST['email']??$_SESSION['pending_reset_email']??'');
        $otp=clean($_POST['otp']??''); $newPass=$_POST['new_password']??''; $confirm=$_POST['confirm']??'';
        if(strlen($newPass)<8){flash('Min 8 characters.','error');redirect('index.php?page=reset_password');}
        if($newPass!==$confirm){flash('Passwords do not match.','error');redirect('index.php?page=reset_password');}
        try {
            Auth::verifyOTP($email,$otp,'reset');
            $dbKeys=Database::read(KEY_FILE);
            foreach($dbKeys as $k=>$v) if($v['email']===$email){$dbKeys[$k]['password']=Auth::hashPassword($newPass);break;}
            Database::write(KEY_FILE,$dbKeys); unset($_SESSION['pending_reset_email']);
            flash('Password updated! Please login.'); redirect('index.php?page=login');
        } catch(Exception $e){flash($e->getMessage(),'error');redirect('index.php?page=reset_password');}
    }

    /* LOGGED-IN ACTIONS */
    if (!isset($_SESSION['uk'])) { flash('Please login.','error'); redirect('index.php?page=login'); }
    if (!Auth::verifyCsrf($_POST['csrf']??'')) { flash('Session expired.','error'); redirect('index.php?page='.$page); }
    $myKey=$_SESSION['uk']; $dbKeys=Database::read(KEY_FILE);

    if ($action==='update_domain') {
        $dbKeys[$myKey]['allowed_domain']=clean($_POST['domain']??'');
        Database::write(KEY_FILE,$dbKeys); flash('Domain lock updated.'); redirect('index.php?page=settings');
    }
    if ($action==='update_preferred_domains') {
        $raw=clean($_POST['preferred_domains']??'');
        $dbKeys[$myKey]['preferred_domains']=$raw;
        Database::write(KEY_FILE,$dbKeys); flash('Preferred domains updated.'); redirect('index.php?page=settings');
    }
    if ($action==='roll_key') {
        $nk=Auth::generateApiKey(); $dbKeys[$nk]=$dbKeys[$myKey]; unset($dbKeys[$myKey]);
        Database::write(KEY_FILE,$dbKeys); $_SESSION['uk']=$nk; flash('API key regenerated!'); redirect('index.php?page=dashboard');
    }
    if ($action==='change_password') {
        $cur=$_POST['current_password']??''; $np=$_POST['new_password']??''; $cf=$_POST['confirm']??'';
        if(!Auth::verifyPassword($cur,$dbKeys[$myKey]['password'])) flash('Current password incorrect.','error');
        elseif(strlen($np)<8) flash('Min 8 characters.','error');
        elseif($np!==$cf) flash('Passwords do not match.','error');
        else { $dbKeys[$myKey]['password']=Auth::hashPassword($np); Database::write(KEY_FILE,$dbKeys); flash('Password changed.'); }
        redirect('index.php?page=settings');
    }
    if ($action==='logout') { session_destroy(); redirect('index.php?page=login'); }
}

/* ─── SETUP ─────────────────────────────────────────────────────────────── */
$loggedIn = isset($_SESSION['uk']);
$myKey    = $loggedIn ? $_SESSION['uk'] : null;
$allKeys  = Database::read(KEY_FILE);
$myData   = ($loggedIn && isset($allKeys[$myKey])) ? $allKeys[$myKey] : null;
$usage    = Database::read(USAGE_FILE);
if (!$loggedIn && !in_array($page,['login','register','verify_email','forgot_password','reset_password'])) redirect('index.php?page=login');
if ($loggedIn && in_array($page,['login','register'])) redirect('index.php?page=dashboard');
$csrf = Auth::csrfToken();
$appName = APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $appName ?> — <?= ucwords(str_replace('_',' ',$page)) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
:root{--blue:#2563eb;--dark:#0f172a;}
*{box-sizing:border-box;}
body{font-family:'Segoe UI',system-ui,-apple-system,sans-serif;}
.card{@apply bg-white rounded-2xl border border-gray-200 shadow-sm;}
.btn-primary{@apply bg-blue-600 text-white font-bold py-2.5 px-5 rounded-xl hover:bg-blue-700 transition-all active:scale-95 text-sm;}
.btn-gray{@apply bg-gray-100 text-gray-700 font-bold py-2.5 px-5 rounded-xl hover:bg-gray-200 transition-all text-sm;}
.field{@apply w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm outline-none transition-all;}
.field:focus{@apply border-blue-400 ring-2 ring-blue-100 bg-white;}
.label{@apply block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5;}
.blur-text{filter:blur(6px);transition:filter .3s;cursor:pointer;}
.blur-text.shown{filter:blur(0);}
.nav-link{@apply text-gray-500 hover:text-blue-600 font-semibold text-sm transition px-3 py-1.5 rounded-lg hover:bg-blue-50;}
.nav-link.active{@apply text-blue-600 bg-blue-50;}
.badge{@apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold;}
</style>
</head>
<body class="bg-gray-50 min-h-screen text-gray-800">

<?php if($msg): ?>
<div id="toast" class="fixed top-5 right-5 z-50 flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-xl font-semibold text-sm text-white max-w-sm
  <?= $msgType==='error'?'bg-red-500':'bg-emerald-500' ?>">
  <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="<?= $msgType==='error'?'M6 18L18 6M6 6l12 12':'M5 13l4 4L19 7' ?>"/>
  </svg>
  <?= htmlspecialchars($msg) ?>
</div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t){t.style.transition='all .4s';t.style.opacity='0';t.style.transform='translateX(24px)';setTimeout(()=>t.remove(),400);}},4500);</script>
<?php endif; ?>

<?php if(!$loggedIn): /* ═══ AUTH PAGES ═══ */ ?>
<div class="min-h-screen flex">
  <!-- Left panel (decorative) -->
  <div class="hidden lg:flex lg:w-5/12 bg-gradient-to-br from-blue-700 via-blue-600 to-indigo-700 flex-col justify-between p-12 text-white">
    <div>
      <div class="flex items-center gap-3 mb-12">
        <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center font-black text-lg">✉</div>
        <span class="font-black text-2xl"><?= $appName ?></span>
      </div>
      <h2 class="text-4xl font-black leading-tight mb-4">Disposable<br>Email API</h2>
      <p class="text-blue-200 text-lg leading-relaxed">Generate temp emails, receive messages and protect your privacy. Developer-friendly REST API.</p>
    </div>
    <div class="grid grid-cols-2 gap-4 text-sm">
      <?php foreach(['🔒 Secure API Keys','📧 Multiple Domains','⚡ Instant Emails','🌍 IMAP Powered'] as $f): ?>
      <div class="bg-white/10 rounded-xl p-3 font-semibold"><?= $f ?></div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Right panel (form) -->
  <div class="flex-1 flex items-center justify-center p-6">
    <div class="w-full max-w-md">
      <div class="lg:hidden text-center mb-8">
        <span class="font-black text-3xl text-blue-600"><?= $appName ?></span>
      </div>

      <?php if($page==='login'): ?>
      <h1 class="text-2xl font-black text-gray-900 mb-1">Welcome back 👋</h1>
      <p class="text-gray-500 mb-8 text-sm">Sign in to access your API dashboard.</p>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <div><label class="label">Username or Email</label><input class="field" type="text" name="login_id" required placeholder="johndoe or john@example.com" autofocus></div>
        <div>
          <div class="flex justify-between items-center mb-1.5"><label class="label mb-0">Password</label><a href="index.php?page=forgot_password" class="text-xs text-blue-600 font-semibold hover:underline">Forgot?</a></div>
          <input class="field" type="password" name="password" required placeholder="••••••••">
        </div>
        <button type="submit" class="w-full bg-blue-600 text-white font-black py-3 rounded-xl hover:bg-blue-700 transition text-sm mt-2">Sign In</button>
      </form>
      <p class="text-center text-sm mt-6 text-gray-500">No account? <a href="index.php?page=register" class="text-blue-600 font-bold hover:underline">Create one free</a></p>

      <?php elseif($page==='register'): ?>
      <h1 class="text-2xl font-black text-gray-900 mb-1">Create account</h1>
      <p class="text-gray-500 mb-6 text-sm">Get your API key to start generating temp emails.</p>
      <form method="POST" class="space-y-3.5">
        <input type="hidden" name="action" value="register">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <div class="grid grid-cols-2 gap-3">
          <div><label class="label">Full Name</label><input class="field" type="text" name="name" required placeholder="John Doe"></div>
          <div><label class="label">Username</label><input class="field" type="text" name="username" required placeholder="johndoe" pattern="[a-zA-Z0-9_]+"></div>
        </div>
        <div><label class="label">Email Address</label><input class="field" type="email" name="email" required placeholder="john@example.com"></div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="label">Phone</label><input class="field" type="text" name="phone" required placeholder="+1 555 0000"></div>
          <div><label class="label">Telegram</label><input class="field" type="text" name="telegram" required placeholder="@johndoe"></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="label">Password</label><input class="field" type="password" name="password" required placeholder="Min 8 chars"></div>
          <div><label class="label">Confirm</label><input class="field" type="password" name="confirm" required placeholder="Repeat"></div>
        </div>
        <button type="submit" class="w-full bg-emerald-600 text-white font-black py-3 rounded-xl hover:bg-emerald-700 transition text-sm mt-1">Create Account</button>
      </form>
      <p class="text-center text-sm mt-5 text-gray-500">Have account? <a href="index.php?page=login" class="text-blue-600 font-bold hover:underline">Sign in</a></p>

      <?php elseif($page==='verify_email'): ?>
      <?php $pve=$_SESSION['pending_verify_email']??''; ?>
      <div class="text-center mb-8">
        <div class="w-20 h-20 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-5 text-4xl">📬</div>
        <h1 class="text-2xl font-black text-gray-900 mb-2">Check your email</h1>
        <p class="text-gray-500 text-sm">We sent a 6-digit code to<br><strong class="text-gray-800"><?= htmlspecialchars($pve) ?></strong></p>
      </div>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="verify_email">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($pve) ?>">
        <input class="field text-center text-4xl font-black tracking-[0.6em] py-5 border-2 border-blue-300" type="text" name="otp" maxlength="6" placeholder="______" oninput="this.value=this.value.replace(/\D/g,'')" autofocus>
        <button type="submit" class="w-full bg-blue-600 text-white font-black py-3 rounded-xl hover:bg-blue-700 transition">Verify Email</button>
      </form>
      <form method="POST" class="mt-3"><input type="hidden" name="action" value="resend_otp"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="email" value="<?= htmlspecialchars($pve) ?>">
        <button type="submit" class="w-full text-sm text-gray-500 hover:text-blue-600 font-semibold py-2 transition">Didn't get it? Resend code</button></form>

      <?php elseif($page==='forgot_password'): ?>
      <div class="text-center mb-8">
        <div class="w-20 h-20 bg-orange-100 rounded-2xl flex items-center justify-center mx-auto mb-5 text-4xl">🔑</div>
        <h1 class="text-2xl font-black text-gray-900 mb-2">Forgot password?</h1>
        <p class="text-gray-500 text-sm">Enter your email and we'll send a reset code.</p>
      </div>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="forgot_password">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <div><label class="label">Email Address</label><input class="field" type="email" name="email" required placeholder="john@example.com" autofocus></div>
        <button type="submit" class="w-full bg-blue-600 text-white font-black py-3 rounded-xl hover:bg-blue-700 transition">Send Reset Code</button>
      </form>
      <p class="text-center mt-5"><a href="index.php?page=login" class="text-sm text-blue-600 font-bold hover:underline">← Back to login</a></p>

      <?php elseif($page==='reset_password'): ?>
      <?php $pre=$_SESSION['pending_reset_email']??''; ?>
      <div class="text-center mb-8">
        <div class="w-20 h-20 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-5 text-4xl">🔐</div>
        <h1 class="text-2xl font-black text-gray-900 mb-2">Reset Password</h1>
        <p class="text-gray-500 text-sm">Enter the code sent to <strong><?= htmlspecialchars($pre) ?></strong></p>
      </div>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($pre) ?>">
        <div><label class="label">OTP Code</label><input class="field" type="text" name="otp" maxlength="6" required placeholder="6-digit code"></div>
        <div><label class="label">New Password</label><input class="field" type="password" name="new_password" required placeholder="Min 8 characters"></div>
        <div><label class="label">Confirm Password</label><input class="field" type="password" name="confirm" required placeholder="Repeat password"></div>
        <button type="submit" class="w-full bg-blue-600 text-white font-black py-3 rounded-xl hover:bg-blue-700 transition">Set New Password</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php else: /* ═══ LOGGED-IN PAGES ═══ */ ?>
<?php if(!$myData){redirect('index.php?page=login');} ?>

<!-- NAVBAR -->
<nav class="bg-white border-b border-gray-200 sticky top-0 z-40">
  <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
    <div class="flex items-center gap-6">
      <a href="index.php?page=dashboard" class="font-black text-lg text-blue-600"><?= $appName ?></a>
      <div class="hidden md:flex items-center gap-1">
        <?php foreach(['dashboard'=>'Dashboard','tempmail'=>'Temp Mail','settings'=>'Settings'] as $p=>$l): ?>
        <a href="index.php?page=<?=$p?>" class="nav-link <?=$page===$p?'active':''?>"><?=$l?></a>
        <?php endforeach; ?>
        <a href="docs.php" class="nav-link">Docs</a>
        <a href="tester.php" class="nav-link">API Tester</a>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <div class="hidden md:flex items-center gap-2">
        <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-black text-sm"><?= strtoupper(substr($myData['name'],0,1)) ?></div>
        <div>
          <p class="text-xs font-black text-gray-800 leading-none"><?= htmlspecialchars($myData['name']) ?></p>
          <p class="text-xs text-gray-400">@<?= htmlspecialchars($myData['id']) ?></p>
        </div>
      </div>
      <form method="POST"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf" value="<?=$csrf?>">
        <button class="badge bg-red-100 text-red-600 hover:bg-red-600 hover:text-white transition px-3 py-1.5 text-xs">Logout</button></form>
    </div>
  </div>
  <!-- Mobile nav -->
  <div class="md:hidden border-t border-gray-100 flex overflow-x-auto px-4 py-2 gap-1">
    <?php foreach(['dashboard'=>'Dashboard','tempmail'=>'Temp Mail','settings'=>'Settings'] as $p=>$l): ?>
    <a href="index.php?page=<?=$p?>" class="nav-link whitespace-nowrap <?=$page===$p?'active':''?> text-xs"><?=$l?></a>
    <?php endforeach; ?>
    <a href="docs.php" class="nav-link text-xs whitespace-nowrap">Docs</a>
  </div>
</nav>

<div class="max-w-6xl mx-auto px-4 py-6">

<?php if($myData['active']=='0'): ?>
<!-- PENDING ACTIVATION -->
<div class="flex items-center justify-center min-h-64">
  <div class="card p-10 text-center max-w-md w-full">
    <div class="text-5xl mb-4">⏳</div>
    <h2 class="text-xl font-black mb-2 text-gray-900">Pending Approval</h2>
    <p class="text-gray-500 text-sm mb-6">Your account is awaiting admin activation. Please contact admin to get started.</p>
    <a href="<?= ADMIN_TELEGRAM ?>" target="_blank" class="btn-primary inline-block">Contact Admin on Telegram</a>
  </div>
</div>

<?php elseif($page==='settings'): /* ─── SETTINGS ─── */ ?>
<div class="mb-6"><h1 class="text-2xl font-black">Settings</h1><p class="text-gray-500 text-sm mt-1">Manage your account and API configuration.</p></div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

  <!-- Change Password -->
  <div class="card p-6">
    <h2 class="font-black text-base mb-1">Change Password</h2>
    <p class="text-xs text-gray-400 mb-5">Use a strong password with at least 8 characters.</p>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="change_password"><input type="hidden" name="csrf" value="<?=$csrf?>">
      <div><label class="label">Current Password</label><input class="field" type="password" name="current_password" required placeholder="••••••••"></div>
      <div><label class="label">New Password</label><input class="field" type="password" name="new_password" required placeholder="Min 8 characters"></div>
      <div><label class="label">Confirm New</label><input class="field" type="password" name="confirm" required placeholder="Repeat"></div>
      <button type="submit" class="btn-primary">Update Password</button>
    </form>
  </div>

  <!-- Domain Lock -->
  <div class="card p-6">
    <h2 class="font-black text-base mb-1">Domain Lock</h2>
    <p class="text-xs text-gray-400 mb-5">Restrict API key usage to specific origins. Use comma for multiple.</p>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="update_domain"><input type="hidden" name="csrf" value="<?=$csrf?>">
      <div><label class="label">Allowed Domains</label>
        <input class="field" type="text" name="domain" value="<?= htmlspecialchars($myData['allowed_domain']??'') ?>" placeholder="e.g. myapp.com, app.myapp.com (blank = any)"></div>
      <button type="submit" class="btn-primary">Save Domain Lock</button>
    </form>
  </div>

  <!-- Preferred Mail Domains -->
  <?php
  $accessibleDomains = MailHandler::getDomainsForUser($myData['id']);
  $prefDomains = $myData['preferred_domains'] ?? '';
  ?>
  <div class="card p-6">
    <h2 class="font-black text-base mb-1">Preferred Mail Domains</h2>
    <p class="text-xs text-gray-400 mb-4">When your API generates emails, it will use only these domains (randomly). Leave blank to use any public domain.</p>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="update_preferred_domains"><input type="hidden" name="csrf" value="<?=$csrf?>">
      <?php if(!empty($accessibleDomains)): ?>
      <div class="flex flex-wrap gap-2 mb-2">
        <?php foreach($accessibleDomains as $d): ?>
        <label class="flex items-center gap-1.5 bg-gray-50 border rounded-xl px-3 py-1.5 cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition text-sm has-[:checked]:bg-blue-100 has-[:checked]:border-blue-400">
          <input type="checkbox" name="pref_domain_toggle" value="<?= htmlspecialchars($d['domain']) ?>"
            class="pref-chk accent-blue-600"
            <?= in_array($d['domain'], array_map('trim',explode(',',$prefDomains))) ? 'checked' : '' ?>
            onchange="syncPrefDomains()">
          <span class="font-semibold text-gray-700">@<?= htmlspecialchars($d['domain']) ?></span>
          <span class="badge <?= $d['public']?'bg-green-100 text-green-700':'bg-purple-100 text-purple-700' ?>"><?= $d['public']?'Public':'Private' ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <input type="hidden" id="preferredDomainsInput" name="preferred_domains" value="<?= htmlspecialchars($prefDomains) ?>">
      <div><label class="label">Or type manually (comma-separated)</label>
        <input class="field" type="text" id="preferredDomainsText" value="<?= htmlspecialchars($prefDomains) ?>" placeholder="domain1.com, domain2.com" oninput="document.getElementById('preferredDomainsInput').value=this.value"></div>
      <button type="submit" class="btn-primary">Save Preferences</button>
    </form>
  </div>

  <!-- Account Info -->
  <div class="card p-6">
    <h2 class="font-black text-base mb-4">Account Info</h2>
    <div class="space-y-3 text-sm">
      <?php foreach([['Email',$myData['email']],['Phone',$myData['phone']??'—'],['Telegram',$myData['telegram']??'—'],['Role',ucfirst($myData['role'])],['Member Since',date('M d, Y',(int)($myData['created_at']??time()))]] as [$k,$v]): ?>
      <div class="flex justify-between py-2 border-b border-gray-50 last:border-0">
        <span class="text-gray-400 font-bold text-xs uppercase"><?=$k?></span>
        <span class="font-semibold text-gray-800"><?= htmlspecialchars($v) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Danger Zone -->
<div class="card border-red-200 p-6 mt-6">
  <h2 class="font-black text-base text-red-600 mb-3 flex items-center gap-2"><span class="text-lg">⚠️</span> Danger Zone</h2>
  <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
    <div><p class="font-semibold text-sm">Regenerate API Key</p><p class="text-xs text-gray-500 mt-0.5">Your current key will stop working immediately.</p></div>
    <form method="POST" onsubmit="return confirm('Regenerate your API key? This cannot be undone.')">
      <input type="hidden" name="action" value="roll_key"><input type="hidden" name="csrf" value="<?=$csrf?>">
      <button class="badge bg-red-100 text-red-600 hover:bg-red-600 hover:text-white transition px-4 py-2 text-sm font-bold">Regenerate Key</button>
    </form>
  </div>
</div>

<?php elseif($page==='tempmail'): /* ─── TEMP MAIL PAGE ─── */
$domains = MailHandler::getDomainsForUser($myData['id']);
?>
<div class="mb-6">
  <h1 class="text-2xl font-black">Temp Mail</h1>
  <p class="text-gray-500 text-sm mt-1">Generate and use disposable email addresses.</p>
</div>

<?php if(empty($domains)): ?>
<div class="card p-10 text-center">
  <div class="text-5xl mb-4">📭</div>
  <h2 class="font-black text-lg mb-2">No Mail Servers Available</h2>
  <p class="text-gray-500 text-sm">No active IMAP servers are configured. Contact admin to add mail servers.</p>
</div>
<?php else: ?>
<div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
  <!-- Generator + Session -->
  <div class="lg:col-span-2 space-y-4">
    <!-- Generate Card -->
    <div class="card p-5">
      <h2 class="font-black text-sm uppercase tracking-wide text-gray-500 mb-4">Generate Email</h2>
      <div class="mb-4">
        <label class="label">Mail Domain</label>
        <select id="genDomain" class="field">
          <option value="">🎲 Random (any public)</option>
          <?php foreach($domains as $d): ?>
          <option value="<?= htmlspecialchars($d['domain']) ?>"><?= $d['owned']?'🔒':'🌐' ?> @<?= htmlspecialchars($d['domain']) ?> (<?= $d['public']?'Public':'Private' ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <button onclick="generateEmail()" id="genBtn" class="w-full btn-primary flex items-center justify-center gap-2 py-3">
        <span id="genBtnIcon">✉</span> Generate New Email
      </button>
    </div>

    <!-- Session Card -->
    <div class="card p-5" id="sessionCard">
      <h2 class="font-black text-sm uppercase tracking-wide text-gray-500 mb-4">Active Session</h2>
      <div class="space-y-3">
        <div>
          <label class="label">Email Address</label>
          <div onclick="copyField('sessEmail')" class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-3 cursor-pointer hover:from-blue-100 transition group">
            <p id="sessEmail" class="font-mono font-bold text-blue-700 text-sm text-center blur-text">—</p>
            <p class="text-center text-xs text-blue-400 mt-1 group-hover:text-blue-600">👆 Click to copy</p>
          </div>
        </div>
        <div>
          <label class="label">Password</label>
          <div onclick="copyField('sessPass')" class="bg-gray-50 border rounded-xl p-3 cursor-pointer hover:bg-gray-100 transition">
            <p id="sessPass" class="font-mono text-gray-700 text-sm text-center blur-text">—</p>
          </div>
        </div>
        <button onclick="loginExisting()" class="w-full btn-gray text-xs py-2">Or login with existing email</button>
      </div>
    </div>

    <!-- Refresh -->
    <button onclick="fetchMessages()" id="refreshBtn" class="w-full bg-indigo-50 text-indigo-700 border border-indigo-200 font-bold py-2.5 px-4 rounded-xl hover:bg-indigo-100 transition text-sm flex items-center justify-center gap-2">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
      Refresh Inbox
    </button>

    <!-- API Key display for this page -->
    <div class="card p-4 text-xs">
      <p class="font-black text-gray-500 uppercase mb-2">Your API Key</p>
      <p id="apiKeyDisplay" class="font-mono text-blue-700 truncate blur-text cursor-pointer" onclick="this.classList.toggle('shown')"><?= htmlspecialchars($myKey) ?></p>
    </div>
  </div>

  <!-- Inbox -->
  <div class="lg:col-span-3 card flex flex-col" style="min-height:500px;">
    <div class="p-4 border-b bg-gray-50 rounded-t-2xl flex items-center justify-between">
      <h2 class="font-black text-sm uppercase tracking-wide text-gray-500">Inbox</h2>
      <span id="inboxCount" class="badge bg-blue-100 text-blue-700">0 messages</span>
    </div>
    <div id="inboxList" class="flex-1 overflow-y-auto divide-y divide-gray-50">
      <div id="inboxEmpty" class="flex flex-col items-center justify-center h-64 text-gray-400">
        <div class="text-5xl mb-3">📭</div>
        <p class="font-bold text-sm">No messages yet</p>
        <p class="text-xs mt-1">Generate an email and wait for messages</p>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Message Modal -->
<div id="msgModal" class="fixed inset-0 bg-black/60 hidden z-50 flex items-center justify-center p-4">
  <div class="bg-white w-full max-w-3xl rounded-2xl shadow-2xl flex flex-col" style="max-height:90vh;">
    <div class="p-5 border-b flex items-center justify-between">
      <h2 class="font-black text-gray-900">📧 Message</h2>
      <button onclick="closeMsgModal()" class="text-gray-400 hover:text-red-500 text-2xl font-black transition leading-none">×</button>
    </div>
    <div id="msgModalBody" class="flex-1 overflow-y-auto p-6 text-sm text-gray-700 leading-relaxed"></div>
  </div>
</div>

<?php else: /* ─── MAIN DASHBOARD ─── */
$usageCount = (int)($usage[$myData['id']]['count'] ?? 0);
$limitCount = (int)($myData['limit_count'] ?? 15);
$pct = $limitCount>0 ? min(100,round($usageCount/$limitCount*100)) : 0;
$pctColor = $pct>80?'bg-red-500':($pct>50?'bg-amber-400':'bg-blue-500');
$domains = MailHandler::getDomainsForUser($myData['id']);
$prefDomains = array_filter(array_map('trim', explode(',', $myData['preferred_domains']??'')));
?>

<!-- Welcome Banner -->
<div class="bg-gradient-to-r from-blue-600 via-blue-500 to-indigo-600 rounded-2xl p-6 mb-6 text-white relative overflow-hidden">
  <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(circle at 80% 50%, white 0%, transparent 60%);"></div>
  <div class="relative flex items-center justify-between">
    <div class="flex items-center gap-4">
      <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-2xl font-black">
        <?= strtoupper(substr($myData['name'],0,1)) ?>
      </div>
      <div>
        <h1 class="text-xl font-black">Hello, <?= htmlspecialchars(explode(' ',$myData['name'])[0]) ?>! 👋</h1>
        <p class="text-blue-200 text-sm">@<?= htmlspecialchars($myData['id']) ?> · <span class="capitalize"><?= $myData['role'] ?></span></p>
      </div>
    </div>
    <a href="index.php?page=tempmail" class="hidden md:flex items-center gap-2 bg-white/20 hover:bg-white/30 transition px-4 py-2 rounded-xl font-bold text-sm">
      ✉ Open Temp Mail
    </a>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
  <!-- API Key Card -->
  <div class="card p-6 lg:col-span-2">
    <div class="flex items-center justify-between mb-4">
      <h2 class="font-black text-base">API Key</h2>
      <span class="badge bg-green-100 text-green-700">● Active</span>
    </div>
    <div onclick="toggleKey()" class="bg-gray-900 rounded-xl p-4 cursor-pointer group relative overflow-hidden mb-4 hover:bg-gray-800 transition">
      <code id="apiKeyCode" class="text-green-400 font-mono text-sm break-all blur-text block"><?= htmlspecialchars($myKey) ?></code>
      <div id="keyOverlay" class="absolute inset-0 flex items-center justify-center bg-gray-900/80 group-hover:bg-gray-800/80 transition">
        <span class="text-white text-xs font-bold flex items-center gap-1.5">👁 Click to reveal & copy</span>
      </div>
    </div>
    <div class="grid grid-cols-3 gap-3 text-sm">
      <div class="bg-blue-50 rounded-xl p-3 border border-blue-100">
        <p class="text-xs text-gray-500 font-bold uppercase mb-1">Limit</p>
        <p class="font-black text-blue-700 text-lg"><?= $limitCount ?></p>
        <p class="text-xs text-gray-400">per <?= $myData['limit_period']??'day' ?></p>
      </div>
      <div class="bg-purple-50 rounded-xl p-3 border border-purple-100">
        <p class="text-xs text-gray-500 font-bold uppercase mb-1">Used Today</p>
        <p class="font-black text-purple-700 text-lg"><?= $usageCount ?></p>
        <p class="text-xs text-gray-400"><?= $pct ?>% used</p>
      </div>
      <div class="bg-gray-50 rounded-xl p-3 border">
        <p class="text-xs text-gray-500 font-bold uppercase mb-1">Role</p>
        <p class="font-black text-gray-800 capitalize text-lg"><?= $myData['role'] ?></p>
        <p class="text-xs text-gray-400">access level</p>
      </div>
    </div>
    <?php if($limitCount>0): ?>
    <div class="mt-4">
      <div class="flex justify-between text-xs text-gray-500 mb-1.5"><span>Daily Usage</span><span><?= $usageCount ?>/<?= $limitCount ?></span></div>
      <div class="h-2 bg-gray-100 rounded-full overflow-hidden"><div class="h-full rounded-full transition-all duration-500 <?= $pctColor ?>" style="width:<?=$pct?>%"></div></div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Quick Stats -->
  <div class="card p-6 flex flex-col justify-between">
    <div>
      <h2 class="font-black text-base mb-4">Account</h2>
      <div class="space-y-2.5 text-sm">
        <div class="flex justify-between"><span class="text-gray-400">Email</span><span class="font-semibold text-xs truncate ml-2"><?= htmlspecialchars($myData['email']) ?></span></div>
        <div class="flex justify-between"><span class="text-gray-400">Domain Lock</span><span class="font-semibold text-xs"><?= empty($myData['allowed_domain'])?'<span class="badge bg-green-100 text-green-700">Any</span>':htmlspecialchars($myData['allowed_domain']) ?></span></div>
        <div class="flex justify-between"><span class="text-gray-400">Verified</span><span class="font-semibold">
          <?= ($myData['email_verified']??'0')==='1'?'<span class="badge bg-green-100 text-green-700">✓ Yes</span>':'<span class="badge bg-yellow-100 text-yellow-700">Pending</span>' ?>
        </span></div>
        <div class="flex justify-between"><span class="text-gray-400">Since</span><span class="font-semibold text-xs"><?= date('M d, Y',(int)($myData['created_at']??time())) ?></span></div>
      </div>
    </div>
    <a href="index.php?page=tempmail" class="mt-5 w-full bg-blue-600 text-white font-bold py-2.5 rounded-xl hover:bg-blue-700 transition text-sm text-center block">
      Open Temp Mail →
    </a>
  </div>
</div>

<!-- Available Domains -->
<?php if(!empty($domains)): ?>
<div class="card p-6 mb-6">
  <div class="flex items-center justify-between mb-4">
    <h2 class="font-black text-base">Available Mail Domains</h2>
    <span class="badge bg-gray-100 text-gray-600"><?= count($domains) ?> domain<?= count($domains)!==1?'s':'' ?></span>
  </div>
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
    <?php foreach($domains as $d):
      $isPref = in_array($d['domain'],$prefDomains); ?>
    <div class="flex items-center gap-2.5 p-3 rounded-xl border <?= $isPref?'border-blue-300 bg-blue-50':($d['owned']?'border-purple-200 bg-purple-50':'border-gray-200 bg-gray-50') ?> transition">
      <div class="w-8 h-8 rounded-lg flex items-center justify-center text-sm shrink-0
        <?= $d['public']?'bg-green-100 text-green-600':'bg-purple-100 text-purple-600' ?>">@</div>
      <div class="min-w-0">
        <p class="font-black text-xs text-gray-800 truncate"><?= htmlspecialchars($d['domain']) ?></p>
        <div class="flex gap-1 mt-0.5">
          <span class="badge text-[10px] px-1.5 py-0 <?= $d['public']?'bg-green-100 text-green-700':'bg-purple-100 text-purple-700' ?>"><?= $d['public']?'Public':'Private' ?></span>
          <?php if($d['owned']): ?><span class="badge text-[10px] px-1.5 py-0 bg-indigo-100 text-indigo-700">Owned</span><?php endif; ?>
          <?php if($isPref): ?><span class="badge text-[10px] px-1.5 py-0 bg-blue-100 text-blue-700">Preferred</span><?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Quick Start -->
<div class="card p-6">
  <h2 class="font-black text-base mb-4">Quick Start</h2>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs mb-5">
    <div class="bg-blue-50 p-4 rounded-xl border border-blue-100"><p class="font-black text-blue-700 mb-2 text-sm">1️⃣ Generate Email</p><code class="text-gray-600 leading-relaxed">/api/?key=YOUR_KEY&amp;action=gen_email</code></div>
    <div class="bg-green-50 p-4 rounded-xl border border-green-100"><p class="font-black text-green-700 mb-2 text-sm">2️⃣ Get Messages</p><code class="text-gray-600 leading-relaxed">/api/?...&amp;action=get_messages &amp;email=&amp;password=</code></div>
    <div class="bg-purple-50 p-4 rounded-xl border border-purple-100"><p class="font-black text-purple-700 mb-2 text-sm">3️⃣ Read Message</p><code class="text-gray-600 leading-relaxed">/api/?...&amp;action=read_message &amp;id=&amp;email=&amp;password=</code></div>
  </div>
  <div class="flex flex-wrap gap-3">
    <a href="docs.php" class="btn-primary text-xs">📄 Full Documentation</a>
    <a href="tester.php" class="btn-gray text-xs">🧪 API Tester</a>
    <a href="index.php?page=settings" class="btn-gray text-xs">⚙ Settings</a>
  </div>
</div>

<?php endif; /* end page switch */ ?>
</div><!-- /container -->

<?php if($page==='tempmail'&&$myData['active']=='1'): ?>
<script>
const API_KEY = '<?= htmlspecialchars($myKey) ?>';
const API_URL = '<?= rtrim(APP_URL,"/") ?>/api/';
let currentEmail='', currentPass='';

window.addEventListener('DOMContentLoaded',()=>{
  const e=localStorage.getItem('_tm_email'), p=localStorage.getItem('_tm_pass');
  if(e&&p){currentEmail=e;currentPass=p;setSession(e,p);fetchMessages();}
});

async function callApi(params){
  const url=API_URL+'?key='+API_KEY+'&'+params;
  const r=await fetch(url); return r.json();
}

async function generateEmail(){
  const btn=document.getElementById('genBtn');
  const icon=document.getElementById('genBtnIcon');
  btn.disabled=true; icon.innerHTML='<span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>';
  const domain=document.getElementById('genDomain').value;
  const params='action=gen_email'+(domain?'&domain='+encodeURIComponent(domain):'');
  const d=await callApi(params).catch(e=>({status:'error',message:e.message}));
  btn.disabled=false; icon.innerHTML='✉';
  if(d.status==='success'){
    currentEmail=d.email; currentPass=d.password;
    localStorage.setItem('_tm_email',d.email); localStorage.setItem('_tm_pass',d.password);
    setSession(d.email,d.password);
    document.getElementById('inboxEmpty').style.display='';
    document.getElementById('inboxList').innerHTML='<div id="inboxEmpty" class="flex flex-col items-center justify-center h-64 text-blue-400"><div class="text-4xl mb-3 animate-bounce">📬</div><p class="font-bold text-sm">Inbox ready!</p><p class="text-xs mt-1">Waiting for incoming messages...</p></div>';
    document.getElementById('inboxCount').textContent='0 messages';
    showToast('Email generated & copied!','success');
    navigator.clipboard.writeText(d.email).catch(()=>{});
  } else {
    showToast('Error: '+(d.message||'Failed'),'error');
  }
}

function setSession(e,p){
  document.getElementById('sessEmail').textContent=e;
  document.getElementById('sessPass').textContent=p;
}

async function fetchMessages(){
  if(!currentEmail){showToast('Generate an email first.','error');return;}
  const btn=document.getElementById('refreshBtn');
  const orig=btn.innerHTML; btn.innerHTML='<span class="inline-block w-4 h-4 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin mr-2"></span>Loading...';
  const d=await callApi('action=get_messages&email='+encodeURIComponent(currentEmail)+'&password='+encodeURIComponent(currentPass)).catch(e=>null);
  btn.innerHTML=orig;
  if(!d){showToast('Request failed','error');return;}
  const list=document.getElementById('inboxList');
  document.getElementById('inboxCount').textContent=(d.count||0)+' message'+(d.count!==1?'s':'');
  if(d.status==='success'&&d.messages&&d.messages.length>0){
    list.innerHTML=d.messages.map(m=>{
      const t=new Date(m.timestamp*1000).toLocaleString([],{month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'});
      const sender=m.sender.replace(/<.*?>/g,'').substring(0,40);
      return `<div class="p-4 hover:bg-blue-50 cursor-pointer transition flex items-start gap-3" onclick="readMessage(${m.id})">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-100 to-indigo-100 flex items-center justify-center text-blue-600 font-black text-sm shrink-0">${sender[0]?.toUpperCase()||'?'}</div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between gap-2">
            <span class="font-bold text-sm text-gray-900 truncate">${escHtml(sender)}</span>
            <span class="text-xs text-gray-400 shrink-0">${t}</span>
          </div>
          <p class="text-xs text-gray-500 truncate mt-0.5 flex items-center gap-1.5">
            ${!m.seen?'<span class="w-1.5 h-1.5 bg-blue-500 rounded-full inline-block"></span>':''}
            ${escHtml(m.subject)}
          </p>
        </div>
      </div>`;
    }).join('');
  } else if(d.status==='success'){
    list.innerHTML='<div class="flex flex-col items-center justify-center h-64 text-gray-400"><div class="text-4xl mb-3">📭</div><p class="font-bold text-sm">Inbox empty</p><p class="text-xs mt-1">No messages yet</p></div>';
  } else {
    showToast('Error: '+(d.message||'Unknown error'),'error');
  }
}

async function readMessage(id){
  document.getElementById('msgModal').classList.remove('hidden');
  document.getElementById('msgModalBody').innerHTML='<div class="flex justify-center py-16"><div class="w-10 h-10 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div></div>';
  const d=await callApi('action=read_message&id='+id+'&email='+encodeURIComponent(currentEmail)+'&password='+encodeURIComponent(currentPass)).catch(e=>null);
  if(d&&d.status==='success') document.getElementById('msgModalBody').innerHTML=d.content;
  else document.getElementById('msgModalBody').innerHTML='<p class="text-center text-red-500 py-10 font-bold">Failed to load message.</p>';
}

function closeMsgModal(){
  document.getElementById('msgModal').classList.add('hidden');
  document.getElementById('msgModalBody').innerHTML='';
  fetchMessages();
}

function loginExisting(){
  const e=prompt('Enter temp email address:');
  if(!e) return;
  const p=prompt('Enter password:');
  if(!p) return;
  currentEmail=e; currentPass=p;
  localStorage.setItem('_tm_email',e); localStorage.setItem('_tm_pass',p);
  setSession(e,p); fetchMessages();
}

function copyField(id){
  const el=document.getElementById(id);
  const v=el.textContent; if(v==='—') return;
  navigator.clipboard.writeText(v).catch(()=>{});
  el.classList.add('shown'); setTimeout(()=>el.classList.remove('shown'),4000);
  showToast('Copied!','success');
}

function showToast(msg,type='success'){
  const d=document.createElement('div');
  d.className='fixed bottom-5 right-5 z-50 px-4 py-2.5 rounded-xl shadow-xl font-bold text-sm text-white '+(type==='success'?'bg-emerald-500':'bg-red-500');
  d.textContent=msg; document.body.appendChild(d);
  setTimeout(()=>{d.style.transition='all .4s';d.style.opacity='0';setTimeout(()=>d.remove(),400);},2500);
}

function escHtml(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

// Close modal on backdrop click
document.getElementById('msgModal').addEventListener('click',function(e){if(e.target===this)closeMsgModal();});
</script>
<?php endif; ?>

<script>
// API Key toggle
function toggleKey(){
  const code=document.getElementById('apiKeyCode');
  const overlay=document.getElementById('keyOverlay');
  if(!code) return;
  code.classList.toggle('shown');
  if(code.classList.contains('shown')){
    if(overlay) overlay.style.display='none';
    navigator.clipboard.writeText(code.textContent.trim()).catch(()=>{});
    setTimeout(()=>{code.classList.remove('shown');if(overlay)overlay.style.display='';},6000);
  }
}

// Preferred domain checkboxes sync
function syncPrefDomains(){
  const checked=Array.from(document.querySelectorAll('.pref-chk:checked')).map(c=>c.value);
  document.getElementById('preferredDomainsInput').value=checked.join(', ');
  document.getElementById('preferredDomainsText').value=checked.join(', ');
}
</script>
<?php endif; /* end loggedIn */ ?>
</body>
</html>
