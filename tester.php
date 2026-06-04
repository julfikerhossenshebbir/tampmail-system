<?php
require_once __DIR__ . '/includes/bootstrap.php';
systemGate();
$appName = APP_NAME;
$appUrl  = rtrim(APP_URL,'/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $appName ?> - API Tester</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
.loader{border-top-color:#3b82f6;animation:spin 1s linear infinite;}
@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
.blur-f{filter:blur(5px);transition:filter .3s;}
.blur-f.show{filter:blur(0);}
pre{background:#1e293b;color:#e2e8f0;padding:1rem;border-radius:.75rem;overflow-x:auto;font-size:12px;line-height:1.6;max-height:300px;}
</style>
</head>
<body class="bg-gray-100 min-h-screen font-sans">

<nav class="bg-white border-b shadow-sm sticky top-0 z-40">
  <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
    <a href="docs.php" class="font-black text-xl text-blue-600"><?= $appName ?></a>
    <div class="flex gap-3 text-sm font-semibold">
      <a href="docs.php" class="text-gray-500 hover:text-blue-600">Docs</a>
      <a href="index.php" class="text-gray-500 hover:text-blue-600">Dashboard</a>
    </div>
  </div>
</nav>

<div class="max-w-5xl mx-auto p-4 md:p-6 mt-4">
  <div class="mb-6">
    <h1 class="text-2xl font-black text-gray-800">Interactive API Tester</h1>
    <p class="text-gray-500 text-sm mt-1">Test all API endpoints live from your browser.</p>
  </div>

  <!-- Config -->
  <div class="bg-white rounded-2xl border shadow-sm p-6 mb-6">
    <h2 class="font-black text-base mb-4 border-b pb-2">Configuration</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-bold text-gray-500 mb-1">API Base URL</label>
        <input id="apiUrl" type="text" value="<?= $appUrl ?>/api/" class="w-full p-2.5 bg-gray-50 border rounded-lg text-sm font-mono outline-none focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-500 mb-1">Your API Key</label>
        <div class="flex gap-2">
          <input id="apiKey" type="text" placeholder="md_live_xxxxxxxxxxxxxxxx" class="flex-1 p-2.5 bg-white border border-blue-300 rounded-lg text-sm font-mono outline-none focus:ring-2 focus:ring-blue-500">
          <button onclick="clearData()" title="Clear all saved data" class="bg-red-100 text-red-600 px-3 rounded-lg hover:bg-red-200 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Left: Controls -->
    <div class="space-y-4">
      <!-- Actions -->
      <div class="bg-white rounded-2xl border shadow-sm p-6">
        <h2 class="font-black text-base mb-4 border-b pb-2">Actions</h2>
        <div class="grid grid-cols-2 gap-3">
          <button onclick="act('check_status')" class="flex items-center gap-2 p-3 bg-gray-50 border rounded-xl hover:bg-gray-100 transition text-sm font-bold text-gray-700">
            <span class="w-2 h-2 bg-green-400 rounded-full"></span> Check Status
          </button>
          <button onclick="genEmail()" class="flex items-center gap-2 p-3 bg-blue-50 border border-blue-200 rounded-xl hover:bg-blue-100 transition text-sm font-bold text-blue-700">
            <span class="w-2 h-2 bg-blue-500 rounded-full"></span> Generate Email
          </button>
          <button onclick="act('get_domains')" class="flex items-center gap-2 p-3 bg-indigo-50 border border-indigo-200 rounded-xl hover:bg-indigo-100 transition text-sm font-bold text-indigo-700">
            <span class="w-2 h-2 bg-indigo-500 rounded-full"></span> Get Domains
          </button>
          <button onclick="fetchMsgs()" class="flex items-center gap-2 p-3 bg-green-50 border border-green-200 rounded-xl hover:bg-green-100 transition text-sm font-bold text-green-700">
            <span class="w-2 h-2 bg-green-500 rounded-full"></span> Get Messages
          </button>
          <button onclick="loginMail()" class="flex items-center gap-2 p-3 bg-yellow-50 border border-yellow-200 rounded-xl hover:bg-yellow-100 transition text-sm font-bold text-yellow-700 col-span-2 justify-center">
            <span class="w-2 h-2 bg-yellow-500 rounded-full"></span> Login with existing email
          </button>
        </div>
      </div>

      <!-- Active Session -->
      <div class="bg-white rounded-2xl border shadow-sm p-6">
        <h2 class="font-black text-base mb-4 border-b pb-2">Active Session</h2>
        <div class="space-y-3">
          <div>
            <p class="text-xs font-bold text-gray-400 mb-1">Email</p>
            <div class="bg-gray-50 border rounded-xl p-3 cursor-pointer hover:bg-blue-50 transition" onclick="copyField('currEmail')">
              <p id="currEmail" class="font-mono text-sm font-bold text-blue-700 blur-f text-center">Not Generated</p>
              <p class="text-xs text-center text-gray-400 mt-0.5">Click to copy</p>
            </div>
          </div>
          <div>
            <p class="text-xs font-bold text-gray-400 mb-1">Password</p>
            <div class="bg-gray-50 border rounded-xl p-3 cursor-pointer hover:bg-blue-50 transition" onclick="copyField('currPass')">
              <p id="currPass" class="font-mono text-sm font-bold text-gray-800 blur-f text-center">---</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Inbox -->
      <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">
        <div class="p-4 border-b bg-gray-50 flex items-center justify-between">
          <h2 class="font-black text-base">Inbox</h2>
          <span id="msgCount" class="bg-blue-100 text-blue-700 font-black text-xs px-2 py-0.5 rounded-full">0</span>
        </div>
        <div id="inboxList" class="divide-y max-h-64 overflow-y-auto">
          <div class="p-8 text-center text-gray-400 text-sm font-medium">No messages yet</div>
        </div>
      </div>
    </div>

    <!-- Right: Response -->
    <div class="space-y-4">
      <div class="bg-white rounded-2xl border shadow-sm p-6">
        <div class="flex items-center justify-between mb-4 border-b pb-2">
          <h2 class="font-black text-base">API Response</h2>
          <div class="flex items-center gap-2">
            <span id="statusBadge" class="text-xs font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">—</span>
            <button onclick="copyResponse()" class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-lg hover:bg-gray-200 font-bold">Copy</button>
          </div>
        </div>
        <pre id="responseBox">// Response will appear here</pre>
      </div>
      <div class="bg-white rounded-2xl border shadow-sm p-6">
        <h2 class="font-black text-base mb-3 border-b pb-2">Last Request URL</h2>
        <div class="bg-gray-50 border rounded-xl p-3">
          <code id="lastUrl" class="text-xs text-gray-500 break-all">—</code>
        </div>
      </div>
    </div>
  </div>

  <!-- Message Modal -->
  <div id="msgModal" class="fixed inset-0 bg-black/60 hidden items-center justify-center p-4 z-50 flex">
    <div class="bg-white w-full max-w-3xl rounded-2xl shadow-2xl flex flex-col max-h-[90vh]">
      <div class="p-5 border-b flex items-center justify-between">
        <h2 class="font-black text-gray-800">Message Content</h2>
        <button onclick="closeModal()" class="text-gray-400 hover:text-red-500 font-black text-xl">×</button>
      </div>
      <div id="msgBody" class="p-6 overflow-y-auto flex-1 text-sm text-gray-700 leading-relaxed"></div>
    </div>
  </div>
</div>

<script>
const LS = {
  get: k => localStorage.getItem(k),
  set: (k,v) => localStorage.setItem(k,v),
  del: k => localStorage.removeItem(k)
};

window.addEventListener('DOMContentLoaded', () => {
  const k = LS.get('_tm_key'), e = LS.get('_tm_email'), p = LS.get('_tm_pass');
  if (k) document.getElementById('apiKey').value = k;
  if (e) { setSession(e, p || '---'); }
});

function getKey() {
  const k = document.getElementById('apiKey').value.trim();
  if (!k) { alert('Enter API key first!'); return null; }
  LS.set('_tm_key', k);
  return k;
}

function getBase() { return document.getElementById('apiUrl').value.trim(); }

async function callApi(params) {
  const key = getKey();
  if (!key) return null;
  const url = getBase() + '?key=' + key + '&' + params;
  document.getElementById('lastUrl').textContent = url;
  try {
    const r = await fetch(url);
    const d = await r.json();
    showResponse(d, r.status);
    return d;
  } catch(e) {
    showResponse({status:'error',message:'Network error: ' + e.message}, 0);
    return null;
  }
}

function showResponse(data, code) {
  document.getElementById('responseBox').textContent = JSON.stringify(data, null, 2);
  const badge = document.getElementById('statusBadge');
  const ok = data.status === 'success';
  badge.textContent = code || '—';
  badge.className = 'text-xs font-bold px-2 py-0.5 rounded-full ' + (ok ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700');
}

async function act(action) {
  await callApi('action=' + action);
}

async function genEmail() {
  const d = await callApi('action=gen_email');
  if (d && d.status === 'success') {
    setSession(d.email, d.password);
    LS.set('_tm_email', d.email);
    LS.set('_tm_pass', d.password);
    document.getElementById('inboxList').innerHTML = '<div class="p-6 text-center text-blue-600 text-sm font-bold">📧 Email ready! Waiting for messages...</div>';
    document.getElementById('msgCount').textContent = '0';
  }
}

function setSession(email, pass) {
  const ce = document.getElementById('currEmail');
  const cp = document.getElementById('currPass');
  ce.textContent = email;
  cp.textContent = pass;
  ce.classList.add('blur-f');
  cp.classList.add('blur-f');
}

async function fetchMsgs() {
  const e = document.getElementById('currEmail').textContent;
  const p = document.getElementById('currPass').textContent;
  if (e === 'Not Generated') { alert('Generate an email first!'); return; }
  const d = await callApi('action=get_messages&email=' + encodeURIComponent(e) + '&password=' + encodeURIComponent(p));
  if (!d) return;
  const list = document.getElementById('inboxList');
  document.getElementById('msgCount').textContent = d.count || 0;
  if (d.status === 'success' && d.messages && d.messages.length > 0) {
    list.innerHTML = '';
    d.messages.forEach(m => {
      const t = new Date(m.timestamp * 1000).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
      const div = document.createElement('div');
      div.className = 'p-4 hover:bg-blue-50 cursor-pointer transition';
      div.onclick = () => readMsg(m.id);
      div.innerHTML = `<div class="flex justify-between mb-1"><span class="font-bold text-sm truncate text-gray-800 pr-2">${m.sender.replace(/<.*?>/g,'')}</span><span class="text-xs text-gray-400 shrink-0">${t}</span></div><p class="text-xs text-gray-500 truncate flex items-center gap-1">${!m.seen?'<span class="w-1.5 h-1.5 bg-blue-500 rounded-full inline-block"></span>':''} ${m.subject}</p>`;
      list.appendChild(div);
    });
  } else if (d.status === 'success') {
    list.innerHTML = '<div class="p-8 text-center text-gray-400 text-sm font-medium">Inbox empty</div>';
  }
}

async function readMsg(id) {
  const e = document.getElementById('currEmail').textContent;
  const p = document.getElementById('currPass').textContent;
  document.getElementById('msgModal').classList.remove('hidden');
  document.getElementById('msgBody').innerHTML = '<div class="flex items-center justify-center py-16"><div class="loader w-8 h-8 border-4 rounded-full"></div></div>';
  const d = await callApi('action=read_message&id=' + id + '&email=' + encodeURIComponent(e) + '&password=' + encodeURIComponent(p));
  if (d && d.status === 'success') {
    document.getElementById('msgBody').innerHTML = d.content;
  } else {
    document.getElementById('msgBody').innerHTML = '<p class="text-red-500 text-center py-10">Failed to load message.</p>';
  }
}

function loginMail() {
  const e = prompt('Enter temp email address:');
  if (!e) return;
  const p = prompt('Enter password:') || '---';
  setSession(e, p);
  LS.set('_tm_email', e);
  LS.set('_tm_pass', p);
  fetchMsgs();
}

function closeModal() {
  document.getElementById('msgModal').classList.add('hidden');
  document.getElementById('msgBody').innerHTML = '';
}

function copyField(id) {
  const el = document.getElementById(id);
  if (el.textContent === 'Not Generated' || el.textContent === '---') return;
  navigator.clipboard.writeText(el.textContent);
  el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 4000);
  const t = document.createElement('div');
  t.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-xl shadow-lg font-bold text-sm z-50';
  t.textContent = 'Copied!';
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 2000);
}

function copyResponse() {
  const txt = document.getElementById('responseBox').textContent;
  navigator.clipboard.writeText(txt);
}

function clearData() {
  ['_tm_key','_tm_email','_tm_pass'].forEach(k => LS.del(k));
  document.getElementById('apiKey').value = '';
  document.getElementById('currEmail').textContent = 'Not Generated';
  document.getElementById('currPass').textContent = '---';
  document.getElementById('inboxList').innerHTML = '<div class="p-8 text-center text-gray-400 text-sm font-medium">No messages yet</div>';
  document.getElementById('msgCount').textContent = '0';
  document.getElementById('responseBox').textContent = '// Response will appear here';
  document.getElementById('lastUrl').textContent = '—';
}
</script>
</body>
</html>
