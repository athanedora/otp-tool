<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>OTP Tool</title>
<style>
  body{font-family:Arial,sans-serif;max-width:820px;margin:24px auto;padding:0 16px;background:#f8fafc;color:#111827}
  h1{font-size:28px;margin:0 0 14px}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:18px;margin:16px 0;box-shadow:0 2px 10px rgba(0,0,0,.03)}
  label{display:block;margin:12px 0 6px;font-weight:700}
  input{width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:10px;font-size:15px;box-sizing:border-box}
  button{padding:12px 16px;border:0;border-radius:10px;background:#111827;color:#fff;font-weight:700;cursor:pointer}
  button:hover{background:#000}
  button.secondary{background:#374151}
  .row{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
  .msg{white-space:pre-wrap;font-size:13px;margin-top:10px;color:#4b5563}
  .ok{color:#166534;font-weight:700}
  .err{color:#b91c1c;font-weight:700}
  .muted{color:#6b7280;font-size:12px}
</style>
</head>
<body>
<h1>🔐 OTP Tool</h1>

<div class="card">
  <strong>Generate & Consume OTP</strong>
  <label for="secret">Secret Key</label>
  <input id="secret" type="password" placeholder="Enter secret key">

  <div class="row">
    <button id="btnGenerate" type="button">Generate OTP</button>
    <button id="btnCopy" type="button" class="secondary">Copy</button>
    <button id="btnCount" type="button" class="secondary">Check Remaining</button>
  </div>

  <label for="pwdBox">Password</label>
  <input id="pwdBox" type="text" readonly placeholder="Click Generate OTP">
  <div id="msg" class="msg"></div>
</div>

<div class="card">
  <strong>Generate New Encrypted Pool</strong>
  <div class="muted">Creates a fresh encrypted <code>password_store.json</code> using the same encryption format as the API.</div>

  <div class="row">
    <div style="flex:1;min-width:160px">
      <label for="count">Count</label>
      <input id="count" type="number" value="1000" min="1">
    </div>
    <div style="flex:1;min-width:160px">
      <label for="len">Length</label>
      <input id="len" type="number" value="18" min="6">
    </div>
  </div>

  <div class="row">
    <button id="btnPool" type="button">Generate & Download Pool</button>
  </div>
  <div id="poolMsg" class="msg"></div>
</div>

<script>
function setMsg(text, type = '') {
  const el = document.getElementById('msg');
  el.textContent = text;
  el.className = 'msg ' + (type === 'ok' ? 'ok' : type === 'err' ? 'err' : '');
}

function setPoolMsg(text, type = '') {
  const el = document.getElementById('poolMsg');
  el.textContent = text;
  el.className = 'msg ' + (type === 'ok' ? 'ok' : type === 'err' ? 'err' : '');
}

let currentPassword = '';

async function apiPost(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });

  const text = await res.text();
  let json;
  try {
    json = JSON.parse(text);
  } catch (e) {
    throw new Error('Invalid JSON response:\n' + text);
  }

  if (!res.ok) {
    throw new Error(json.message || ('HTTP ' + res.status));
  }

  return json;
}

function randInt(max) {
  const arr = new Uint32Array(1);
  crypto.getRandomValues(arr);
  return arr[0] % max;
}

function genPassword(length) {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*()-_=+';
  let out = '';
  for (let i = 0; i < length; i++) out += chars[randInt(chars.length)];
  return out;
}

function b64FromBytes(bytes) {
  let bin = '';
  for (let i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
  return btoa(bin);
}

async function sha256Raw(str) {
  return await crypto.subtle.digest('SHA-256', new TextEncoder().encode(str));
}

async function encryptStore(plain, secret) {
  const keyRaw = await sha256Raw(secret);
  const key = await crypto.subtle.importKey('raw', keyRaw, { name: 'AES-CBC' }, false, ['encrypt']);
  const iv = crypto.getRandomValues(new Uint8Array(16));
  const data = new TextEncoder().encode(plain);
  const cipherBuf = await crypto.subtle.encrypt({ name: 'AES-CBC', iv }, key, data);
  const cipherBytes = new Uint8Array(cipherBuf);
  const combined = new Uint8Array(iv.length + cipherBytes.length);
  combined.set(iv, 0);
  combined.set(cipherBytes, iv.length);
  return b64FromBytes(combined);
}

function download(name, text) {
  const blob = new Blob([text], { type: 'application/json' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = name;
  a.click();
  setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

document.getElementById('btnGenerate').addEventListener('click', async () => {
  try {
    const secret = document.getElementById('secret').value.trim();
    if (!secret) return setMsg('Enter secret key first.', 'err');

    setMsg('Generating OTP...');
    const result = await apiPost('otp_api.php?action=consume', { secret });

    if (!result.success) {
      currentPassword = '';
      document.getElementById('pwdBox').value = '';
      return setMsg(result.message || 'Failed to generate OTP.', 'err');
    }

    currentPassword = result.password;
    document.getElementById('pwdBox').value = currentPassword;
    setMsg('OTP generated and consumed.\nRemaining: ' + result.remaining, 'ok');
  } catch (e) {
    setMsg('Generate error:\n' + e.message, 'err');
  }
});

document.getElementById('btnCopy').addEventListener('click', async () => {
  try {
    if (!currentPassword) return setMsg('Generate OTP first.', 'err');
    await navigator.clipboard.writeText(currentPassword);
    setMsg('Copied to clipboard.', 'ok');
  } catch (e) {
    try {
      const box = document.getElementById('pwdBox');
      box.removeAttribute('readonly');
      box.select();
      document.execCommand('copy');
      box.setAttribute('readonly', 'readonly');
      setMsg('Copied (fallback).', 'ok');
    } catch {
      setMsg('Copy failed.', 'err');
    }
  }
});

document.getElementById('btnCount').addEventListener('click', async () => {
  try {
    const secret = document.getElementById('secret').value.trim();
    if (!secret) return setMsg('Enter secret key first.', 'err');

    setMsg('Checking remaining...');
    const result = await apiPost('otp_api.php?action=count', { secret });
    if (!result.success) return setMsg(result.message || 'Failed to check count.', 'err');

    setMsg('Remaining passwords: ' + result.count, 'ok');
  } catch (e) {
    setMsg('Count error:\n' + e.message, 'err');
  }
});

document.getElementById('btnPool').addEventListener('click', async () => {
  try {
    const secret = document.getElementById('secret').value.trim();
    const count = parseInt(document.getElementById('count').value, 10);
    const len = parseInt(document.getElementById('len').value, 10);

    if (secret.length < 8) return setPoolMsg('Secret key must be at least 8 characters.', 'err');
    if (!count || count < 1) return setPoolMsg('Count must be at least 1.', 'err');
    if (!len || len < 6) return setPoolMsg('Length must be at least 6.', 'err');

    setPoolMsg('Generating passwords...');
    const seen = new Set();
    const arr = [];

    while (arr.length < count) {
      const p = genPassword(len);
      if (!seen.has(p)) {
        seen.add(p);
        arr.push(p);
      }
    }

    const obj = {
      version: 1,
      updated_at: new Date().toISOString(),
      passwords: arr
    };

    setPoolMsg('Encrypting store...');
    const enc = await encryptStore(JSON.stringify(obj), secret);
    download('password_store.json', enc);
    setPoolMsg('Downloaded fresh encrypted password_store.json', 'ok');
  } catch (e) {
    setPoolMsg('Pool generation error:\n' + e.message, 'err');
  }
});
</script>
</body>
</html>
