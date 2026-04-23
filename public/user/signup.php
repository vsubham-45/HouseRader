<?php
// public/user/signup.php
// Two-step OTP signup UI (step 1: request OTP via email, step 2: verify OTP + create account)

require_once __DIR__ . '/../../src/session.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../src/db.php';

// Simple helper for escaping output
function safe($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// If user already logged in, redirect to seller/dashboard or public index
if (!empty($_SESSION['user']) && !empty($_SESSION['user']['id'])) {
    header('Location: ../index.php');
    exit;
}

if (empty($_SESSION['csrf_signup'])) $_SESSION['csrf_signup'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_signup'];

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Create account — HouseRadar</title>
<script>
(function(){
  try {
    if (localStorage.getItem('hr_theme') === 'dark') {
      document.documentElement.classList.add('dark');
    }
  } catch(e){}
})();
</script>

  <link rel="stylesheet" href="../assets/css/common.css" />
  <style>
    /* Narrow, centered card with modern two-step layout */
    .auth-wrapper { display:flex; justify-content:center; padding:20px 16px 60px; }
    .auth-card { width:420px; max-width:95%; background:var(--card-bg,#fff); border-radius:12px; box-shadow:0 8px 30px rgba(15,30,45,0.06); padding:22px; }
    .lead { color:var(--muted,#6b7b84); margin-top:6px; }
    .form-row { margin:12px 0; }
    label { display:block; font-size:13px; margin-bottom:6px; color:var(--muted,#24303a); }
    input[type="text"], input[type="email"], input[type="password"], input[type="number"] {
      width:100%; padding:10px 12px; border-radius:8px; border:1px solid rgba(0,0,0,0.06); font-size:14px;
    }
    .btn-primary { background:linear-gradient(180deg,#1ea1ff,#2b9cff); border:0; color:#fff; padding:9px 14px; border-radius:8px; cursor:pointer; font-weight:700; }
    .btn-ghost { background:transparent; border:1px solid rgba(0,0,0,0.06); padding:8px 12px; border-radius:8px; cursor:pointer; }
    .muted-small { font-size:13px; color:var(--muted,#6b7b84); margin-top:8px; }
    .small { font-size:13px; color:var(--muted,#9fb1bf); }

    .hidden { display:none !important; }
    .center { text-align:center; }

    .otp-inputs { display:flex; gap:8px; justify-content:center; margin:6px 0; }
    .otp-inputs input { width:52px; text-align:center; font-size:18px; padding:10px 6px; }

    .resend { font-size:13px; color:var(--muted,#6b7b84); cursor:pointer; text-decoration:underline; background:none; border:none; padding:0; }
    .msg-error { color:#b4282d; margin:6px 0; }
    .msg-success { color:#087f5b; margin:6px 0; }
    .btn-google{
  display:flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  border:1px solid #ddd;
  padding:10px;
  border-radius:8px;
  font-weight:600;
  background:#fff;
  text-decoration:none;
  color:#111;
  margin-bottom:14px;
}
.btn-google:hover{ background:#f7f7f7 }

  </style>
</head>
<body>
  <header class="hr-navbar" role="banner" aria-hidden="true">
    <div class="brand"><a href="../index.php" style="text-decoration:none;color:inherit;">🏘️ <span style="color:var(--primary-variant)">HouseRadar</span></a></div>
    <div></div>
    <div aria-hidden="true"></div>
  </header>

  <main>
    <section class="page-hero container" aria-hidden="true">
      <h1 class="hero-title">Find your next home in Mumbai, Thane & Navi Mumbai</h1>
      <p class="hero-sub">Sign up to save favourites, contact sellers and post listings.</p>
    </section>

    <div class="auth-wrapper">
      <div class="auth-card" role="region" aria-labelledby="signupTitle">
        <h2 id="signupTitle">Create your HouseRadar account</h2>
        <p class="lead">Sign up as buyer & seller (you can switch later).</p>

        <div id="messages" role="status" aria-live="polite" class="small"></div>

        <!-- STEP 1: Request OTP -->
        <div id="step1">
          <a class="btn-google" href="../../api/google_login.php">
  <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" width="18">
  Sign up with Google
</a>

<div class="center muted-small" style="margin:8px 0;">or</div>

          <div class="form-row">
            <label for="name">Full name</label>
            <input id="name" type="text" placeholder="Full name" autocomplete="name" />
          </div>

          <div class="form-row">
            <label for="email">Email address</label>
            <input id="email" type="email" placeholder="Email address" autocomplete="email" />
            <div class="muted-small">We will send a verification code to this email.</div>
          </div>

          <div style="display:flex; gap:8px; align-items:center; margin-top:14px;">
            <button id="sendOtpBtn" class="btn-primary" type="button">Send OTP</button>
            <div id="sendingSpinner" class="small hidden">Sending…</div>
            <div style="flex:1"></div>
          </div>
        </div>

        <!-- STEP 2: Verify OTP & Create Account -->
        <div id="step2" class="hidden">
          <div class="form-row center">
            <div class="small">OTP sent to <strong id="sentEmail"></strong></div>
            <div class="muted-small" id="otpHint">Enter the 6-digit code. Expires in 10 minutes.</div>
          </div>

          <div class="form-row center">
            <div class="otp-inputs" id="otpInputs">
              <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]*" class="otp-digit" />
              <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]*" class="otp-digit" />
              <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]*" class="otp-digit" />
              <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]*" class="otp-digit" />
              <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]*" class="otp-digit" />
              <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]*" class="otp-digit" />
            </div>
            <div class="muted-small">Tip: copy/paste the whole code or type digits.</div>
          </div>

          <div class="form-row">
  <label for="password">Create password</label>
  <div class="input-with-toggle">
    <input id="password" type="password" placeholder="Create password" />
    <button type="button"
            class="toggle-visibility"
            aria-pressed="false"
            data-target="password">👁</button>
  </div>
  <div class="muted-small">At least 6 characters</div>
</div>


          <div class="form-row">
  <label for="confirm">Confirm password</label>
  <div class="input-with-toggle">
    <input id="confirm" type="password" placeholder="Confirm password" />
    <button type="button"
            class="toggle-visibility"
            aria-pressed="false"
            data-target="confirm">👁</button>
  </div>
</div>


          <div style="display:flex; gap:8px; align-items:center; margin-top:10px;">
            <button id="verifyBtn" class="btn-primary" type="button">Verify & Create Account</button>
            <button id="backToStep1" class="btn-ghost" type="button">Back</button>
            <div style="flex:1"></div>
            <div id="resendBlock" class="small muted-small">
              <button id="resendBtn" class="resend" type="button">Resend OTP</button>
              <span id="resendTimer" class="small"></span>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>

<script>
(function(){
  const APP_ROOT = (function(){ const p = window.location.pathname.split('/'); return p.length>1?('/'+p[1]):''; })();
  const REQ_URL = APP_ROOT + '/api/request_otp.php';
  const VERIFY_URL = APP_ROOT + '/api/verify_otp.php';

  // DOM
  const step1 = document.getElementById('step1');
  const step2 = document.getElementById('step2');
  const messages = document.getElementById('messages');
  const nameEl = document.getElementById('name');
  const emailEl = document.getElementById('email');
  const sendOtpBtn = document.getElementById('sendOtpBtn');
  const sendingSpinner = document.getElementById('sendingSpinner');

  const sentEmailEl = document.getElementById('sentEmail');
  const otpInputs = Array.from(document.querySelectorAll('.otp-digit'));
  const passwordEl = document.getElementById('password');
  const confirmEl = document.getElementById('confirm');
  const verifyBtn = document.getElementById('verifyBtn');
  const backToStep1 = document.getElementById('backToStep1');
  const resendBtn = document.getElementById('resendBtn');
  const resendTimer = document.getElementById('resendTimer');

  let currentContact = '';
  let resendRemaining = 0;
  let resendInterval = null;
  const RESEND_COOLDOWN = 30; // seconds

  function setMsg(text, isError=false) {
    messages.innerHTML = text ? ('<div class="' + (isError ? 'msg-error':'msg-success') + '">' + text + '</div>') : '';
  }

  function disableSend(v) {
    sendOtpBtn.disabled = !!v;
    sendingSpinner.classList.toggle('hidden', !v);
  }

  function showStep2(email) {
    step1.classList.add('hidden');
    step2.classList.remove('hidden');
    sentEmailEl.textContent = email;
    otpInputs.forEach(i=>i.value='');
    passwordEl.value = '';
    confirmEl.value = '';
    otpInputs[0].focus();
    startResendCountdown(RESEND_COOLDOWN);
  }

  function showStep1() {
    step2.classList.add('hidden');
    step1.classList.remove('hidden');
    setMsg('');
  }

  // helpers for OTP input UX
  otpInputs.forEach((input, idx) => {
    input.addEventListener('input', (e) => {
      const v = input.value.replace(/\D/g,'');
      input.value = v ? v.slice(-1) : '';
      if (v && idx < otpInputs.length - 1) {
        otpInputs[idx+1].focus();
      }
    });
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && input.value === '' && idx > 0) {
        otpInputs[idx-1].focus();
      }
      if (e.key === 'Enter') {
        verifyBtn.click();
      }
    });
  });

  function getOtpValue() {
    return otpInputs.map(i=>i.value.trim()).join('');
  }

  function startResendCountdown(seconds) {
    resendRemaining = seconds;
    resendBtn.disabled = true;
    updateTimerText();
    if (resendInterval) clearInterval(resendInterval);
    resendInterval = setInterval(() => {
      resendRemaining--;
      updateTimerText();
      if (resendRemaining <= 0) {
        clearInterval(resendInterval);
        resendBtn.disabled = false;
        resendTimer.textContent = '';
      }
    }, 1000);
  }

  function updateTimerText() {
    if (resendRemaining > 0) {
      resendTimer.textContent = ' (' + resendRemaining + 's)';
    } else {
      resendTimer.textContent = '';
    }
  }

  // send OTP
  sendOtpBtn.addEventListener('click', async () => {
    const name = nameEl.value.trim();
    const email = emailEl.value.trim();
    if (!name) { setMsg('Please enter your full name', true); nameEl.focus(); return; }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { setMsg('Please enter a valid email', true); emailEl.focus(); return; }

    setMsg('');
    disableSend(true);

    try {
      const fd = new FormData();
      fd.append('contact', email);
      fd.append('channel', 'email');

      const res = await fetch(REQ_URL, { method:'POST', credentials:'include', body: fd });
      const json = await res.json();
      if (!json || !json.ok) {
        const err = json && (json.error || json.message) ? (json.error || json.message) : 'Failed to request OTP';
        setMsg('Error: ' + err, true);
        disableSend(false);
        return;
      }

      // success — server may send warning that it logged OTP for dev
      if (json.warning && json.warning === 'otp_not_sent_logged_for_dev') {
        setMsg('OTP generated and logged on server (dev). Check otp_debug.log if you don\'t get email.', false);
      } else {
        setMsg('OTP sent to email. Check your inbox.', false);
      }
      currentContact = email;
      showStep2(email);
    } catch (e) {
      console.error(e);
      setMsg('Network or server error while requesting OTP', true);
    }
    disableSend(false);
  });

  // resend
  resendBtn.addEventListener('click', async () => {
    if (!currentContact) return;
    resendBtn.disabled = true;
    try {
      const fd = new FormData();
      fd.append('contact', currentContact);
      fd.append('channel', 'email');
      const res = await fetch(REQ_URL, { method:'POST', credentials:'include', body: fd });
      const json = await res.json();
      if (!json || !json.ok) {
        setMsg('Resend failed: ' + (json && json.error ? json.error : 'server error'), true);
        return;
      }
      setMsg('OTP resent. Check your inbox.', false);
      startResendCountdown(RESEND_COOLDOWN);
    } catch (e) {
      console.error(e);
      setMsg('Failed to resend OTP', true);
      resendBtn.disabled = false;
    }
  });

  backToStep1.addEventListener('click', () => {
    showStep1();
  });

  // verify + create account
  verifyBtn.addEventListener('click', async () => {
    const otp = getOtpValue();
    const name = nameEl.value.trim();
    const email = currentContact || emailEl.value.trim();
    const password = passwordEl.value || '';
    const confirm = confirmEl.value || '';

    if (!otp || otp.length !== 6) { setMsg('Enter the 6-digit OTP', true); return; }
    if (!name) { setMsg('Name missing', true); return; }
    if (!email) { setMsg('Email missing', true); return; }
    if (!password || password.length < 6) { setMsg('Password must be at least 6 characters', true); return; }
    if (password !== confirm) { setMsg('Passwords do not match', true); return; }

    setMsg('Verifying…');

    try {
      const fd = new FormData();
      fd.append('contact', email);
      fd.append('channel', 'email');
      fd.append('otp', otp);
      fd.append('name', name);
      fd.append('password', password);

      const res = await fetch(VERIFY_URL, { method:'POST', credentials:'include', body: fd });
      const json = await res.json();
      if (!json || !json.ok) {
        const err = json && (json.error || json.message) ? (json.error || json.message) : 'Verification failed';
        setMsg('Error: ' + err, true);
        return;
      }

      // success: server created user and logged them in (session set). Redirect to dashboard or index.
      setMsg('Account created — redirecting...', false);
      setTimeout(() => {
        // If server provided a redirect path use it, otherwise go to seller dashboard if present
        window.location.href = APP_ROOT + '/public/seller/seller_index.php';
      }, 900);

    } catch (e) {
      console.error(e);
      setMsg('Network or server error during verification', true);
    }
  });

  // allow paste of full code into first input
  otpInputs[0].addEventListener('paste', (e) => {
    e.preventDefault();
    const paste = (e.clipboardData || window.clipboardData).getData('text');
    const digits = paste.replace(/\D/g,'').slice(0,6).split('');
    otpInputs.forEach((i, idx) => i.value = digits[idx] || '');
    if (digits.length) otpInputs[Math.min(digits.length, otpInputs.length)-1].focus();
  });

})();
</script>
<script>
/* Enter -> next input */
(function(){
  var form = document.getElementById('loginForm');
  if (!form) return;
  var inputs = Array.from(
    form.querySelectorAll('input[type=email],input[type=password]')
  );
  inputs.forEach(function(el, idx){
    el.addEventListener('keydown', function(e){
      if (e.key === 'Enter') {
        if (idx === inputs.length - 1) return;
        e.preventDefault();
        inputs[idx+1].focus();
      }
    });
  });
})();

/* Password visibility */
(function(){
  document.querySelectorAll('.toggle-visibility').forEach(btn => {
    var id = btn.dataset.target;
    var input = document.getElementById(id);
    if (!input) return;

    btn.onclick = function(e){
      e.preventDefault();
      var visible = btn.getAttribute('aria-pressed') === 'true';
      input.type = visible ? 'password' : 'text';
      btn.setAttribute('aria-pressed', visible ? 'false' : 'true');
      btn.textContent = visible ? '👁' : '🙈';
      input.focus();
    };
  });
})();

/* Theme sync across tabs */
window.addEventListener('storage', e => {
  if (e.key === 'hr_theme') {
    document.documentElement.classList.toggle(
      'dark',
      e.newValue === 'dark'
    );
  }
});
</script>
</body>
</html>
