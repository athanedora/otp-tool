<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>OTP Pool Generator</title>
<style>
body{font-family:Arial,sans-serif;max-width:760px;margin:24px auto;padding:0 16px;background:#f8fafc}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:18px}
label{display:block;margin:12px 0 6px;font-weight:700}
input{width:100%;padding:12px;border:1px solid #d1d5db;border-radius:10px;box-sizing:border-box}
button{padding:12px 16px;border:0;border-radius:10px;background:#111827;color:#fff;font-weight:700;cursor:pointer;margin-top:12px}
.msg{margin-top:10px;font-size:13px;white-space:pre-wrap}
.ok{color:#166534;font-weight:700}.err{color:#b91c1c;font-weight:700}
</style>
</head>
<body>
<div class="card">
  <h2>OTP Pool Generator</h2>
  <label>Secret Key</label>
  <input id="secret" type="password" placeholder="Enter secret key">
  <label>Count</label>
  <input id="count" type="number" value="1000" min="1">
  <label>Length</label>
  <input id="len" type="number" value="18" min="6">
  <button id="btn">Generate & Download password_store.json</button>
  <div id="msg" class="msg"></div>
</div>
<script>
function setMsg(t, type=''){ const el=document.getElementById('msg'); el.textContent=t; el.className='msg '+(type||''); }
function randInt(max){ const b=new Uint32Array(1); crypto.getRandomValues(b); return b[0]%max; }
function genPassword(length){ const chars='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*()-_=+'; let out=''; for(let i=0;i<length;i++) out+=chars[randInt(chars.length)]; return out; }
function b64FromBytes(bytes){ let bin=''; for(let i=0;i<bytes.length;i++) bin+=String.fromCharCode(bytes[i]); return btoa(bin); }
async function sha256Raw(str){ return await crypto.subtle.digest('SHA-256', new TextEncoder().encode(str)); }
async function encryptStore(plain, secret){ const keyRaw=await sha256Raw(secret); const key=await crypto.subtle.importKey('raw', keyRaw, {name:'AES-CBC'}, false, ['encrypt']); const iv=crypto.getRandomValues(new Uint8Array(16)); const data=new TextEncoder().encode(plain); const cipherBuf=await crypto.subtle.encrypt({name:'AES-CBC', iv}, key, data); const cipherBytes=new Uint8Array(cipherBuf); const combined=new Uint8Array(iv.length+cipherBytes.length); combined.set(iv,0); combined.set(cipherBytes, iv.length); return b64FromBytes(combined); }
function download(name, text){ const blob=new Blob([text], {type:'application/json'}); const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=name; a.click(); setTimeout(()=>URL.revokeObjectURL(a.href), 1000); }

document.getElementById('btn').addEventListener('click', async ()=>{
  try{
    const secret=document.getElementById('secret').value.trim();
    const count=parseInt(document.getElementById('count').value,10);
    const len=parseInt(document.getElementById('len').value,10);
    if(secret.length<8) return setMsg('Secret key must be at least 8 characters.','err');
    if(!count||count<1) return setMsg('Count must be at least 1.','err');
    if(!len||len<6) return setMsg('Length must be at least 6.','err');
    setMsg('Generating passwords...');
    const seen=new Set(); const arr=[];
    while(arr.length<count){ const p=genPassword(len); if(!seen.has(p)){ seen.add(p); arr.push(p); } }
    const obj={version:1, updated_at:new Date().toISOString(), passwords:arr};
    setMsg('Encrypting store...');
    const enc=await encryptStore(JSON.stringify(obj), secret);
    download('password_store.json', enc);
    setMsg('Downloaded encrypted password_store.json','ok');
  }catch(e){ setMsg('Error:\n'+e.message,'err'); }
});
</script>
</body>
</html>
