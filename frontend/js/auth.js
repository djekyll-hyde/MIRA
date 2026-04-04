const API = '/research-repo/api/auth.php';

// ── Toggle password visibility ─────────────────────────────────────────────
function togglePassword(inputId, btn) {
  const input = document.getElementById(inputId);
  const isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';

  btn.innerHTML = isHidden
    ? `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
        <line x1="1" y1="1" x2="23" y2="23"/>
      </svg>`
    : `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
        <circle cx="12" cy="12" r="3"/>
      </svg>`;
}

// ── Tab switching ─────────────────────────────────────────────────────────
function switchTab(tab, e) {
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  e.target.classList.add('active');
  document.getElementById('loginForm').classList.toggle('hidden', tab !== 'login');
  document.getElementById('registerForm').classList.toggle('hidden', tab !== 'register');
}

// ── Show message ──────────────────────────────────────────────────────────
function showMsg(id, text, type) {
  const el = document.getElementById(id);
  el.textContent = text;
  el.className = `alert alert-${type === 'error' ? 'error' : 'success'}`;
}

// ── Handle Login ──────────────────────────────────────────────────────────
async function handleLogin(e) {
  e.preventDefault();
  const btn = document.getElementById('loginBtn');
  btn.disabled = true;
  btn.textContent = 'Logging in...';

  try {
    const res = await fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action:   'login',
        email:    document.getElementById('loginEmail').value,
        password: document.getElementById('loginPassword').value,
      })
    });

    const data = await res.json();

    if (!res.ok) {
      showMsg('loginMsg', data.error, 'error');
    } else {
      localStorage.setItem('mira_token', data.token);
      localStorage.setItem('mira_user',  JSON.stringify(data.user));
      showMsg('loginMsg', 'Login successful! Redirecting...', 'success');
      setTimeout(() => window.location.href = '/research-repo/frontend/index.html', 1000);
    }

  } catch (err) {
    showMsg('loginMsg', 'Connection error. Is the server running?', 'error');
  }

  btn.disabled = false;
  btn.textContent = 'Login';
}

// ── Handle Register ───────────────────────────────────────────────────────
async function handleRegister(e) {
  e.preventDefault();
  const btn = document.getElementById('registerBtn');
  btn.disabled = true;
  btn.textContent = 'Creating account...';

  try {
    const res = await fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action:   'register',
        name:     document.getElementById('regName').value,
        email:    document.getElementById('regEmail').value,
        password: document.getElementById('regPassword').value,
        role:     document.getElementById('regRole').value,
      })
    });

    const data = await res.json();

    if (!res.ok) {
      showMsg('registerMsg', data.error, 'error');
    } else {
      localStorage.setItem('mira_token', data.token);
      localStorage.setItem('mira_user',  JSON.stringify(data.user));
      showMsg('registerMsg', 'Account created! Redirecting...', 'success');
      setTimeout(() => window.location.href = '/research-repo/frontend/index.html', 1000);
    }

  } catch (err) {
    showMsg('registerMsg', 'Connection error. Is the server running?', 'error');
  }

  btn.disabled = false;
  btn.textContent = 'Create Account';
}

const GOOGLE_CLIENT_ID = '618104625762-kl7cgq7b2tr4tlp42pat584ujbr2srce.apps.googleusercontent.com';
const API_GOOGLE = '/research-repo/api/google_auth.php';

// ── Render Google buttons after page loads ────────────────────────────────
window.addEventListener('load', () => {
  if (typeof google === 'undefined') return;

  // Login button
  google.accounts.id.initialize({
    client_id: GOOGLE_CLIENT_ID,
    callback: (response) => handleGoogleCallback(response, 'login'),
  });
  google.accounts.id.renderButton(
    document.getElementById('google-login-btn'),
    { theme: 'outline', size: 'large', text: 'signin_with', width: 340 }
  );

  // Register button
  google.accounts.id.initialize({
    client_id: GOOGLE_CLIENT_ID,
    callback: (response) => handleGoogleCallback(response, 'register'),
  });
  google.accounts.id.renderButton(
    document.getElementById('google-register-btn'),
    { theme: 'outline', size: 'large', text: 'signup_with', width: 340 }
  );
});

// ── Handle Google callback ────────────────────────────────────────────────
async function handleGoogleCallback(response, action) {
  const msgId = action === 'login' ? 'loginMsg' : 'registerMsg';

  try {
    const res = await fetch(API_GOOGLE, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: response.credential, action })
    });

    const data = await res.json();

    if (!res.ok) {
      showMsg(msgId, data.error, 'error');
    } else {
      localStorage.setItem('mira_token', data.token);
      localStorage.setItem('mira_user',  JSON.stringify(data.user));
      showMsg(msgId, `Welcome, ${data.user.name}! Redirecting...`, 'success');
      setTimeout(() => window.location.href = '/research-repo/frontend/index.html', 1000);
    }

  } catch (err) {
    showMsg(msgId, 'Google sign-in failed. Please try again.', 'error');
  }
}