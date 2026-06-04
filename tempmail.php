<?php
/**
 * TempMail Pro - Public Temp Mail Generator
 * Works with any API key. Domains from active public IMAP servers.
 */
require_once __DIR__ . '/includes/bootstrap.php';
systemGate();
$appName    = APP_NAME;
$appUrl     = rtrim(APP_URL, '/');
$pubDomains = MailHandler::getPublicDomains();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($appName) ?> — Free Disposable Email</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  :root { --accent: #3b82f6; }
  body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
  .glass { background: rgba(255,255,255,0.05); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.1); }
  .blur-secret { filter: blur(6px); transition: filter .3s ease; user-select: none; }
  .blur-secret.revealed { filter: blur(0); }
  .spin { animation: spin 1s linear infinite; }
  @keyframes spin { to { transform: rotate(360deg); } }
  .msg-item:hover { background: rgba(59,130,246,0.08); }
  .copy-btn:active { transform: scale(0.95); }
  pre, code { font-family: 'Cascadia Code', 'Fira Code', 'Consolas', monospace; }
  ::-webkit-scrollbar { width: 4px; } ::-webkit-scrollbar-track { background: transparent; }
  ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }
</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-950 via-blue-950 to-gray-950 text-white">

<!-- ── NAVBAR ── -->
<nav class="glass border-b border-white/10 sticky top-0 z-50">
  <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between">
    <a href="tempmail.php" class="flex items-center gap-2.5 font-black text-xl">
      <span class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-sm">✉</span>
      <span class="text-white"><?= htmlspecialchars($appName) ?></span>
    </a>
    <div class="flex items-center gap-2 text-sm">
      <a href="docs.php" class="text-white/50 hover:text-white px-3 py-1.5 rounded-lg hover:bg-white/5 transition font-medium">Docs</a>
      <a href="tester.php" class="text-white/50 hover:text-white px-3 py-1.5 rounded-lg hover:bg-white/5 transition font-medium">API Tester</a>
      <a href="index.php" class="text-white/50 hover:text-white px-3 py-1.5 rounded-lg hover:bg-white/5 transition font-medium">Dashboard</a>
      <a href="index.php?page=register" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-1.5 rounded-xl font-bold transition text-xs">Get Free Key →</a>
    </div>
  </div>
</nav>

<!-- ── HERO ── -->
<div class="text-center py-14 px-4">
  <div class="inline-flex items-center gap-2 bg-blue-600/20 border border-blue-500/30 rounded-full px-4 py-1.5 text-blue-300 text-xs font-bold mb-5">
    <span class="w-1.5 h-1.5 bg-green-400 rounded-full inline-block"></span>
    <?= count($pubDomains) ?> domain<?= count($pubDomains) !== 1 ? 's' : '' ?> available
  </div>
  <h1 class="text-4xl md:text-6xl font-black mb-4 bg-gradient-to-br from-white via-blue-100 to-blue-400 bg-clip-text text-transparent leading-tight">
    Disposable Email<br>in Seconds
  </h1>
  <p class="text-white/50 text-lg max-w-xl mx-auto">
    Generate temporary email addresses instantly. No signup needed — just paste your API key and go.
  </p>
</div>

<!-- ── MAIN CONTENT ── -->
<div class="max-w-6xl mx-auto px-4 pb-16 space-y-5">

  <!-- API Key + Domain Row -->
  <div class="glass rounded-2xl p-5">
    <div class="flex flex-col md:flex-row gap-4">
      <!-- API Key -->
      <div class="flex-1">
        <label class="block text-xs font-bold text-white/40 uppercase tracking-wider mb-2">API Key <span class="text-blue-400">*</span></label>
        <div class="flex gap-2">
          <input id="apiKeyInput" type="password"
            placeholder="md_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
            class="flex-1 px-4 py-3 bg-black/30 border border-white/15 rounded-xl text-sm font-mono text-white placeholder-white/20 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 transition"
            oninput="saveKey(this.value)">
          <button onclick="toggleKeyVisibility()" class="px-3 py-3 glass rounded-xl hover:bg-white/10 transition text-white/50 hover:text-white" title="Show/hide key">
            <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
          </button>
        </div>
        <p class="text-xs text-white/25 mt-1.5">Don't have a key? <a href="index.php?page=register" class="text-blue-400 hover:underline">Register free →</a></p>
      </div>
      <!-- Domain Selector -->
      <div class="md:w-56">
        <label class="block text-xs font-bold text-white/40 uppercase tracking-wider mb-2">Mail Domain</label>
        <?php if (empty($pubDomains)): ?>
        <div class="px-4 py-3 bg-red-900/30 border border-red-500/30 rounded-xl text-sm text-red-400">No domains configured</div>
        <?php else: ?>
        <select id="domainSelect" class="w-full px-3 py-3 bg-black/30 border border-white/15 rounded-xl text-sm text-white outline-none focus:border-blue-500 transition appearance-none cursor-pointer">
          <option value="">🎲 Random domain</option>
          <?php foreach ($pubDomains as $d): ?>
          <option value="<?= htmlspecialchars($d['domain']) ?>">@<?= htmlspecialchars($d['domain']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>
      </div>
      <!-- Generate Button -->
      <div class="flex items-end">
        <button id="genBtn" onclick="generateEmail()"
          class="w-full md:w-auto px-7 py-3 bg-blue-600 hover:bg-blue-500 active:scale-95 transition rounded-xl font-black text-sm flex items-center justify-center gap-2 shadow-lg shadow-blue-600/20 disabled:opacity-50 disabled:cursor-not-allowed">
          <span id="genBtnContent" class="flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Generate Email
          </span>
        </button>
      </div>
    </div>
  </div>

  <!-- Main Grid -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    <!-- Left Column: Session + Domains -->
    <div class="space-y-4">

      <!-- Active Email Card -->
      <div class="glass rounded-2xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-white/8 flex items-center justify-between">
          <span class="text-xs font-black text-white/40 uppercase tracking-wider">Active Session</span>
          <span id="sessionDot" class="w-2 h-2 bg-gray-600 rounded-full" title="No active session"></span>
        </div>
        <div class="p-5 space-y-4">
          <!-- Email -->
          <div>
            <p class="text-xs text-white/30 font-bold uppercase mb-2">Email Address</p>
            <button onclick="copyField('emailDisplay')" class="copy-btn w-full bg-blue-600/15 border border-blue-500/25 hover:bg-blue-600/25 hover:border-blue-500/50 transition rounded-xl p-3.5 text-left group">
              <p id="emailDisplay" class="font-mono font-bold text-blue-300 text-sm break-all blur-secret">No email generated</p>
              <p class="text-xs text-blue-400/40 mt-1.5 group-hover:text-blue-400/70 transition flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                Click to copy
              </p>
            </button>
          </div>
          <!-- Password -->
          <div>
            <p class="text-xs text-white/30 font-bold uppercase mb-2">Password</p>
            <button onclick="copyField('passDisplay')" class="copy-btn w-full bg-white/5 border border-white/10 hover:bg-white/10 transition rounded-xl p-3 text-left">
              <p id="passDisplay" class="font-mono text-white/60 text-sm blur-secret">—</p>
            </button>
          </div>
          <!-- Login existing -->
          <button onclick="loginExisting()" class="w-full text-xs text-white/30 hover:text-white/60 transition py-1 flex items-center justify-center gap-1.5 border border-white/10 rounded-xl hover:border-white/20">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
            Login with existing email
          </button>
        </div>
      </div>

      <!-- Refresh + Count -->
      <button onclick="fetchInbox()" id="refreshBtn"
        class="w-full glass hover:bg-white/10 transition rounded-2xl py-3 px-5 font-bold text-sm flex items-center justify-center gap-2.5 text-indigo-300 hover:text-indigo-200">
        <svg id="refreshIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        <span id="refreshText">Refresh Inbox</span>
        <span id="msgCountBadge" class="ml-auto bg-indigo-600/30 border border-indigo-500/30 text-indigo-300 text-xs font-black px-2.5 py-0.5 rounded-full">0</span>
      </button>

      <!-- Available Domains -->
      <?php if (!empty($pubDomains)): ?>
      <div class="glass rounded-2xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-white/8">
          <span class="text-xs font-black text-white/40 uppercase tracking-wider">Public Domains</span>
        </div>
        <div class="divide-y divide-white/5">
          <?php foreach ($pubDomains as $d): ?>
          <div class="flex items-center justify-between px-5 py-3 hover:bg-white/3 transition cursor-pointer" onclick="document.getElementById('domainSelect').value='<?= htmlspecialchars($d['domain']) ?>'">
            <div class="flex items-center gap-2.5">
              <div class="w-7 h-7 bg-blue-600/20 rounded-lg flex items-center justify-center text-blue-400 text-xs font-black">@</div>
              <span class="font-mono text-sm text-white/80"><?= htmlspecialchars($d['domain']) ?></span>
            </div>
            <span class="text-xs bg-green-600/20 border border-green-500/30 text-green-400 px-2 py-0.5 rounded-full font-bold">Active</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- How it works -->
      <div class="glass rounded-2xl p-5">
        <p class="text-xs font-black text-white/40 uppercase tracking-wider mb-4">How It Works</p>
        <div class="space-y-3.5">
          <?php foreach ([
            ['1', 'blue', 'Get API Key', 'Register free or use existing'],
            ['2', 'purple', 'Generate Email', 'Click generate, get instant address'],
            ['3', 'green', 'Receive Mail', 'Use address, refresh inbox'],
          ] as [$n, $clr, $t, $d]): ?>
          <div class="flex items-start gap-3">
            <div class="w-6 h-6 bg-<?= $clr ?>-600/30 border border-<?= $clr ?>-500/30 rounded-lg flex items-center justify-center text-<?= $clr ?>-400 text-xs font-black shrink-0 mt-0.5"><?= $n ?></div>
            <div><p class="font-bold text-sm text-white/80"><?= $t ?></p><p class="text-xs text-white/35"><?= $d ?></p></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Right Column: Inbox -->
    <div class="lg:col-span-2 glass rounded-2xl overflow-hidden flex flex-col" style="min-height: 600px;">
      <!-- Inbox Header -->
      <div class="px-5 py-4 border-b border-white/8 flex items-center justify-between bg-white/2">
        <div class="flex items-center gap-3">
          <svg class="w-5 h-5 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          <span class="font-black text-white/80">Inbox</span>
        </div>
        <div class="flex items-center gap-2">
          <span id="lastRefreshed" class="text-xs text-white/25 hidden">Just now</span>
          <span id="inboxBadgeLarge" class="text-xs bg-blue-600/20 border border-blue-500/25 text-blue-300 px-3 py-1 rounded-full font-black">0 messages</span>
        </div>
      </div>

      <!-- Inbox List -->
      <div id="inboxList" class="flex-1 overflow-y-auto">
        <div id="inboxPlaceholder" class="flex flex-col items-center justify-center h-full py-24 text-white/20">
          <div class="w-20 h-20 rounded-2xl bg-white/5 flex items-center justify-center text-4xl mb-5">📭</div>
          <p class="font-black text-base text-white/30">No messages yet</p>
          <p class="text-sm mt-2 text-white/20">Generate an email address to get started</p>
        </div>
      </div>

      <!-- Auto-refresh -->
      <div class="px-5 py-3 border-t border-white/8 bg-white/2 flex items-center gap-3">
        <label class="flex items-center gap-2 cursor-pointer text-xs text-white/40 hover:text-white/60 transition">
          <div class="relative">
            <input type="checkbox" id="autoRefreshToggle" class="sr-only" onchange="toggleAutoRefresh(this.checked)">
            <div id="autoToggleTrack" class="w-8 h-4 bg-white/10 rounded-full transition-colors"></div>
            <div id="autoToggleThumb" class="absolute top-0.5 left-0.5 w-3 h-3 bg-white/40 rounded-full transition-transform"></div>
          </div>
          Auto-refresh (10s)
        </label>
        <span id="nextRefreshText" class="text-xs text-white/20 hidden"></span>
      </div>
    </div>
  </div>
</div>

<!-- ── MESSAGE MODAL ── -->
<div id="msgModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4" style="background:rgba(0,0,0,0.85);backdrop-filter:blur(8px);">
  <div class="bg-gray-900 border border-white/10 rounded-2xl w-full max-w-3xl shadow-2xl flex flex-col" style="max-height:90vh;">
    <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
      <div>
        <h3 id="msgModalSubject" class="font-black text-white">Message</h3>
        <p id="msgModalMeta" class="text-xs text-white/40 mt-0.5"></p>
      </div>
      <button onclick="closeMsgModal()" class="w-9 h-9 flex items-center justify-center rounded-xl hover:bg-white/10 transition text-white/40 hover:text-red-400 text-xl font-black">×</button>
    </div>
    <div id="msgBody" class="flex-1 overflow-y-auto bg-white rounded-b-2xl text-gray-800 text-sm leading-relaxed p-6"></div>
  </div>
</div>

<!-- ── TOAST CONTAINER ── -->
<div id="toastBox" class="fixed bottom-6 right-6 z-50 flex flex-col gap-2 items-end"></div>

<script>
const API_BASE = '<?= $appUrl ?>/api/';
let curEmail = '', curPass = '', autoTimer = null;

/* ── Init ── */
(function init() {
  var k = ls('_k'), e = ls('_e'), p = ls('_p');
  if (k) { document.getElementById('apiKeyInput').value = k; }
  if (e && p) { curEmail = e; curPass = p; setSession(e, p); fetchInbox(); }
})();

/* ── LocalStorage helpers ── */
function ls(k) { try { return localStorage.getItem(k) || ''; } catch(e) { return ''; } }
function lsSet(k, v) { try { localStorage.setItem(k, v); } catch(e) {} }
function lsDel(k) { try { localStorage.removeItem(k); } catch(e) {} }

/* ── Key visibility ── */
function toggleKeyVisibility() {
  var inp = document.getElementById('apiKeyInput');
  inp.type = inp.type === 'password' ? 'text' : 'password';
}
function saveKey(v) { if (v) lsSet('_k', v); }

/* ── API call ── */
async function apiCall(params) {
  var key = document.getElementById('apiKeyInput').value.trim();
  if (!key) { toast('Enter your API key first.', 'error'); return null; }
  lsSet('_k', key);
  try {
    var r = await fetch(API_BASE + '?key=' + encodeURIComponent(key) + '&' + params);
    return await r.json();
  } catch (e) { toast('Network error: ' + e.message, 'error'); return null; }
}

/* ── Generate email ── */
async function generateEmail() {
  var btn = document.getElementById('genBtn');
  var content = document.getElementById('genBtnContent');
  btn.disabled = true;
  content.innerHTML = '<div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full spin"></div> Generating...';

  var domain = document.getElementById('domainSelect') ? document.getElementById('domainSelect').value : '';
  var params = 'action=gen_email' + (domain ? '&domain=' + encodeURIComponent(domain) : '');
  var d = await apiCall(params);

  btn.disabled = false;
  content.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg> Generate Email';

  if (d && d.status === 'success') {
    curEmail = d.email; curPass = d.password;
    lsSet('_e', d.email); lsSet('_p', d.password);
    setSession(d.email, d.password);
    copyToClipboard(d.email);
    toast('✓ Email generated & copied!', 'success');
    showInboxLoading();
    document.getElementById('inboxBadgeLarge').textContent = '0 messages';
    document.getElementById('msgCountBadge').textContent = '0';
    // Auto fetch after short delay
    setTimeout(fetchInbox, 1500);
  } else {
    toast('Error: ' + (d ? d.message : 'Request failed'), 'error');
  }
}

/* ── Set session display ── */
function setSession(email, pass) {
  document.getElementById('emailDisplay').textContent = email;
  document.getElementById('passDisplay').textContent = pass;
  document.getElementById('sessionDot').className = 'w-2 h-2 bg-green-400 rounded-full';
  document.getElementById('sessionDot').title = 'Active session';
}

/* ── Fetch inbox ── */
async function fetchInbox() {
  if (!curEmail) { toast('Generate an email first.', 'error'); return; }
  var btn = document.getElementById('refreshBtn');
  var icon = document.getElementById('refreshIcon');
  var txt = document.getElementById('refreshText');
  icon.classList.add('spin');
  txt.textContent = 'Loading...';

  var d = await apiCall('action=get_messages&email=' + encodeURIComponent(curEmail) + '&password=' + encodeURIComponent(curPass));
  icon.classList.remove('spin');
  txt.textContent = 'Refresh Inbox';

  if (!d) return;
  var cnt = d.count || 0;
  document.getElementById('msgCountBadge').textContent = cnt;
  document.getElementById('inboxBadgeLarge').textContent = cnt + ' message' + (cnt !== 1 ? 's' : '');
  document.getElementById('lastRefreshed').textContent = 'Updated ' + new Date().toLocaleTimeString();
  document.getElementById('lastRefreshed').classList.remove('hidden');

  if (d.status === 'success' && d.messages && d.messages.length > 0) {
    renderInbox(d.messages);
  } else if (d.status === 'success') {
    document.getElementById('inboxList').innerHTML = '<div class="flex flex-col items-center justify-center py-20 text-white/20"><div class="w-16 h-16 rounded-2xl bg-white/5 flex items-center justify-center text-3xl mb-4">📭</div><p class="font-bold">Inbox empty</p><p class="text-xs mt-1 text-white/15">No messages received yet</p></div>';
  } else {
    toast('Fetch error: ' + d.message, 'error');
  }
}

function renderInbox(messages) {
  var html = messages.map(function(m) {
    var sender = (m.sender || '').replace(/<[^>]*>/g, '').substring(0, 50);
    var initial = (sender[0] || '?').toUpperCase();
    var time = new Date(m.timestamp * 1000).toLocaleString([], {month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'});
    var unseenDot = !m.seen ? '<span class="w-2 h-2 bg-blue-400 rounded-full shrink-0"></span>' : '';
    return '<div class="msg-item flex items-center gap-4 px-5 py-4 cursor-pointer border-b border-white/5 last:border-0 transition" onclick="readMessage(' + m.id + ', ' + JSON.stringify(m.subject||'') + ', ' + JSON.stringify(sender) + ')">'
      + '<div class="w-10 h-10 bg-gradient-to-br from-blue-600/40 to-indigo-600/40 border border-blue-500/25 rounded-xl flex items-center justify-center font-black text-blue-300 text-sm shrink-0">' + esc(initial) + '</div>'
      + '<div class="flex-1 min-w-0">'
      + '<div class="flex items-center justify-between gap-2 mb-0.5"><span class="font-bold text-sm text-white/90 truncate">' + esc(sender) + '</span><span class="text-xs text-white/30 shrink-0">' + time + '</span></div>'
      + '<div class="flex items-center gap-2 text-xs text-white/40 truncate">' + unseenDot + esc(m.subject || '(No Subject)') + '</div>'
      + '</div>'
      + '<svg class="w-4 h-4 text-white/20 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>'
      + '</div>';
  }).join('');
  document.getElementById('inboxList').innerHTML = html;
}

function showInboxLoading() {
  document.getElementById('inboxList').innerHTML = '<div class="flex flex-col items-center justify-center py-20 text-blue-400/50"><div class="w-10 h-10 border-4 border-blue-400/30 border-t-blue-400 rounded-full spin mb-4"></div><p class="font-bold text-sm">Waiting for messages...</p></div>';
}

/* ── Read message ── */
async function readMessage(id, subject, sender) {
  var modal = document.getElementById('msgModal');
  modal.style.display = 'flex'; modal.classList.remove('hidden');
  document.getElementById('msgModalSubject').textContent = subject || '(No Subject)';
  document.getElementById('msgModalMeta').textContent = 'From: ' + sender;
  document.getElementById('msgBody').innerHTML = '<div class="flex justify-center py-16"><div class="w-10 h-10 border-4 border-blue-200 border-t-blue-600 rounded-full spin"></div></div>';

  var d = await apiCall('action=read_message&id=' + id + '&email=' + encodeURIComponent(curEmail) + '&password=' + encodeURIComponent(curPass));
  if (d && d.status === 'success') {
    document.getElementById('msgBody').innerHTML = d.content;
  } else {
    document.getElementById('msgBody').innerHTML = '<p class="text-red-500 text-center py-10 font-bold">Failed to load message content.</p>';
  }
}

function closeMsgModal() {
  var modal = document.getElementById('msgModal');
  modal.classList.add('hidden'); modal.style.display = '';
  document.getElementById('msgBody').innerHTML = '';
  fetchInbox();
}
document.getElementById('msgModal').addEventListener('click', function(e) { if (e.target === this) closeMsgModal(); });

/* ── Login existing ── */
function loginExisting() {
  var e = prompt('Enter your temp email address:');
  if (!e) return;
  var p = prompt('Enter the password:');
  if (!p) return;
  curEmail = e; curPass = p;
  lsSet('_e', e); lsSet('_p', p);
  setSession(e, p); fetchInbox();
}

/* ── Copy helpers ── */
function copyField(id) {
  var el = document.getElementById(id);
  var v = el.textContent.trim();
  if (!v || v === 'No email generated' || v === '—') return;
  copyToClipboard(v);
  el.classList.add('revealed');
  setTimeout(function() { el.classList.remove('revealed'); }, 5000);
}

function copyToClipboard(text) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).catch(function() { fallbackCopy(text); });
  } else { fallbackCopy(text); }
}

function fallbackCopy(text) {
  var ta = document.createElement('textarea');
  ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
  document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); } catch(e){}
  document.body.removeChild(ta);
}

/* ── Auto-refresh ── */
function toggleAutoRefresh(on) {
  var track = document.getElementById('autoToggleTrack');
  var thumb = document.getElementById('autoToggleThumb');
  var txt   = document.getElementById('nextRefreshText');
  if (on) {
    track.classList.add('bg-blue-600'); track.classList.remove('bg-white/10');
    thumb.style.transform = 'translateX(16px)'; thumb.classList.add('bg-white'); thumb.classList.remove('bg-white/40');
    txt.classList.remove('hidden');
    startAutoRefresh();
  } else {
    track.classList.remove('bg-blue-600'); track.classList.add('bg-white/10');
    thumb.style.transform = ''; thumb.classList.remove('bg-white'); thumb.classList.add('bg-white/40');
    txt.classList.add('hidden');
    stopAutoRefresh();
  }
}

function startAutoRefresh() {
  stopAutoRefresh();
  var sec = 10;
  document.getElementById('nextRefreshText').textContent = 'Next in ' + sec + 's';
  autoTimer = setInterval(function() {
    sec--;
    document.getElementById('nextRefreshText').textContent = 'Next in ' + sec + 's';
    if (sec <= 0) { sec = 10; if (curEmail) fetchInbox(); }
  }, 1000);
}

function stopAutoRefresh() { if (autoTimer) { clearInterval(autoTimer); autoTimer = null; } }

/* ── Toast ── */
function toast(msg, type) {
  var d = document.createElement('div');
  var colors = type === 'success' ? 'bg-emerald-600 border-emerald-500/50' : (type === 'error' ? 'bg-red-600 border-red-500/50' : 'bg-blue-600 border-blue-500/50');
  d.className = 'flex items-center gap-2.5 px-4 py-2.5 rounded-xl border shadow-2xl text-white text-sm font-semibold ' + colors;
  d.innerHTML = '<span>' + (type==='success'?'✓':type==='error'?'✗':'ℹ') + '</span>' + esc(msg);
  document.getElementById('toastBox').appendChild(d);
  setTimeout(function() { d.style.transition = 'all .4s'; d.style.opacity = '0'; d.style.transform = 'translateX(16px)'; setTimeout(function(){d.remove();},400); }, 3500);
}

function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>
