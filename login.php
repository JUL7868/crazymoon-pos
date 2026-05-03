<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Crazy Moon POS — Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600&family=Barlow:wght@400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg:       #0f0f0d;
      --panel:    #1a1916;
      --card:     #232220;
      --hover:    #2c2b28;
      --orange:   #e8832a;
      --orange-d: #c46e1f;
      --gold:     #f5c842;
      --text-hi:  #f0ede6;
      --text-mid: #a09a8e;
      --text-lo:  #5a5650;
      --border:   rgba(255,255,255,0.07);
      --border-m: rgba(255,255,255,0.13);
      --red:      #d44a4a;
      --radius:   10px;
      --radius-lg:14px;
      --font-h:   'Oswald', sans-serif;
      --font-b:   'Barlow', sans-serif;
    }

    body {
      font-family: var(--font-b);
      background: var(--bg);
      color: var(--text-hi);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    /* Logo */
    .logo {
      font-family: var(--font-h);
      font-size: 28px;
      font-weight: 600;
      letter-spacing: 0.06em;
      margin-bottom: 6px;
      text-align: center;
    }
    .logo span { color: var(--orange); }
    .logo-sub {
      font-size: 12px;
      color: var(--text-lo);
      text-align: center;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      margin-bottom: 32px;
    }

    /* Screens */
    .screen { width: 100%; max-width: 420px; display: none; }
    .screen.active { display: block; }

    /* Staff selector */
    .section-label {
      font-size: 11px;
      color: var(--text-lo);
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-bottom: 12px;
      text-align: center;
    }

    .staff-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
      gap: 10px;
      margin-bottom: 20px;
    }

    .staff-btn {
      background: var(--card);
      border: 1px solid var(--border-m);
      border-radius: var(--radius-lg);
      padding: 18px 10px;
      cursor: pointer;
      text-align: center;
      transition: all 0.15s;
      -webkit-tap-highlight-color: transparent;
    }
    .staff-btn:active { transform: scale(0.97); }
    .staff-btn:hover  { border-color: var(--orange); }

    .staff-avatar {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: var(--hover);
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: var(--font-h);
      font-size: 18px;
      font-weight: 600;
      color: var(--orange);
      margin: 0 auto 10px;
      border: 1px solid var(--border-m);
    }
    .staff-name {
      font-size: 13px;
      font-weight: 500;
      color: var(--text-hi);
    }
    .staff-role {
      font-size: 10px;
      color: var(--text-lo);
      margin-top: 3px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .staff-btn.manager .staff-avatar { color: var(--gold); border-color: rgba(245,200,66,0.3); }
    .staff-btn.superadmin .staff-avatar { color: var(--orange); border-color: rgba(232,131,42,0.4); }

    /* PIN screen */
    .pin-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 24px;
    }
    .back-btn {
      background: transparent;
      border: 1px solid var(--border-m);
      border-radius: var(--radius);
      padding: 8px 14px;
      color: var(--text-mid);
      font-family: var(--font-b);
      font-size: 13px;
      cursor: pointer;
      -webkit-tap-highlight-color: transparent;
    }
    .back-btn:active { background: var(--hover); }
    .pin-user-name {
      font-family: var(--font-h);
      font-size: 18px;
      font-weight: 600;
      letter-spacing: 0.04em;
    }

    /* PIN dots */
    .pin-dots {
      display: flex;
      justify-content: center;
      gap: 14px;
      margin-bottom: 28px;
    }
    .pin-dot {
      width: 16px;
      height: 16px;
      border-radius: 50%;
      border: 2px solid var(--border-m);
      background: transparent;
      transition: all 0.15s;
    }
    .pin-dot.filled {
      background: var(--orange);
      border-color: var(--orange);
    }
    .pin-dot.error {
      background: var(--red);
      border-color: var(--red);
    }

    /* PIN keypad */
    .keypad {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      max-width: 300px;
      margin: 0 auto;
    }
    .key {
      background: var(--card);
      border: 1px solid var(--border-m);
      border-radius: var(--radius-lg);
      padding: 20px 10px;
      font-family: var(--font-h);
      font-size: 22px;
      font-weight: 500;
      color: var(--text-hi);
      cursor: pointer;
      text-align: center;
      transition: all 0.1s;
      -webkit-tap-highlight-color: transparent;
      user-select: none;
    }
    .key:active { background: var(--hover); transform: scale(0.95); }
    .key.zero   { grid-column: 2; }
    .key.del    {
      background: transparent;
      border-color: transparent;
      color: var(--text-mid);
      font-size: 18px;
    }
    .key.del:active { background: var(--hover); }

    .pin-error {
      text-align: center;
      color: var(--red);
      font-size: 13px;
      margin-top: 16px;
      min-height: 20px;
    }

    /* Loading */
    .loading-overlay {
      position: fixed;
      inset: 0;
      background: rgba(15,15,13,0.85);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 100;
      display: none;
    }
    .loading-overlay.show { display: flex; }
    .spinner {
      width: 36px;
      height: 36px;
      border: 3px solid var(--border-m);
      border-top-color: var(--orange);
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>

  <div class="logo">CRAZY <span>MOON</span></div>
  <div class="logo-sub">Sistema de punto de venta</div>

  <!-- Screen 1: Staff selector -->
  <div class="screen active" id="screen-staff">
    <div class="section-label">Selecciona tu nombre</div>
    <div class="staff-grid" id="staff-grid">
      <div style="text-align:center;color:var(--text-lo);font-size:13px;grid-column:1/-1;padding:20px;">
        Cargando...
      </div>
    </div>
  </div>

  <!-- Screen 2: PIN entry -->
  <div class="screen" id="screen-pin">
    <div class="pin-header">
      <button class="back-btn" onclick="goBack()">Atras</button>
      <div class="pin-user-name" id="pin-user-name"></div>
    </div>

    <div class="pin-dots" id="pin-dots">
      <div class="pin-dot"></div>
      <div class="pin-dot"></div>
      <div class="pin-dot"></div>
      <div class="pin-dot"></div>
      <div class="pin-dot"></div>
      <div class="pin-dot"></div>
    </div>

    <div class="keypad">
      <div class="key" onclick="pressKey('1')">1</div>
      <div class="key" onclick="pressKey('2')">2</div>
      <div class="key" onclick="pressKey('3')">3</div>
      <div class="key" onclick="pressKey('4')">4</div>
      <div class="key" onclick="pressKey('5')">5</div>
      <div class="key" onclick="pressKey('6')">6</div>
      <div class="key" onclick="pressKey('7')">7</div>
      <div class="key" onclick="pressKey('8')">8</div>
      <div class="key" onclick="pressKey('9')">9</div>
      <div class="key zero" onclick="pressKey('0')">0</div>
      <div class="key del" onclick="deleteLast()">&#9003;</div>
    </div>

    <div class="pin-error" id="pin-error"></div>
  </div>

  <!-- Loading overlay -->
  <div class="loading-overlay" id="loading">
    <div class="spinner"></div>
  </div>

<script>
const API = 'https://crazymoon.space/crazymoon_pos/api/auth.php';

let selectedUser = null;
let pin          = '';
const PIN_LENGTH = 6;

// ── Load staff on page load ───────────────────────────────
async function loadStaff() {
  try {
    const res  = await fetch(API + '?action=get_users');
    const data = await res.json();

    if (!data.success || !data.users.length) {
      document.getElementById('staff-grid').innerHTML =
        '<div style="text-align:center;color:var(--text-lo);font-size:13px;grid-column:1/-1;padding:20px;">No hay usuarios activos</div>';
      return;
    }

    document.getElementById('staff-grid').innerHTML = data.users.map(u => {
      const initials = u.name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
      const roleClass = u.role === 'superadmin' ? 'superadmin' : u.role === 'manager' ? 'manager' : '';
      const roleLabel = u.role === 'superadmin' ? 'Super Admin' : u.role === 'manager' ? 'Manager' : 'Staff';
      return `
        <div class="staff-btn ${roleClass}" onclick="selectUser(${u.id}, '${escJs(u.name)}')">
          <div class="staff-avatar">${initials}</div>
          <div class="staff-name">${esc(u.name)}</div>
          <div class="staff-role">${roleLabel}</div>
        </div>`;
    }).join('');
  } catch(e) {
    document.getElementById('staff-grid').innerHTML =
      '<div style="text-align:center;color:var(--text-lo);font-size:13px;grid-column:1/-1;padding:20px;">Error de conexion</div>';
  }
}

// ── Select user → go to PIN screen ───────────────────────
function selectUser(id, name) {
  selectedUser = id;
  pin          = '';
  document.getElementById('pin-user-name').textContent = name;
  document.getElementById('pin-error').textContent     = '';
  updateDots();
  showScreen('screen-pin');
}

// ── PIN keypad ────────────────────────────────────────────
function pressKey(digit) {
  if (pin.length >= PIN_LENGTH) return;
  pin += digit;
  updateDots();
  if (pin.length === PIN_LENGTH) {
    setTimeout(submitPin, 150);
  }
}

function deleteLast() {
  pin = pin.slice(0, -1);
  updateDots();
  document.getElementById('pin-error').textContent = '';
  clearDotError();
}

function updateDots() {
  const dots = document.querySelectorAll('.pin-dot');
  dots.forEach((dot, i) => {
    dot.classList.toggle('filled', i < pin.length);
    dot.classList.remove('error');
  });
}

function clearDotError() {
  document.querySelectorAll('.pin-dot').forEach(d => d.classList.remove('error'));
}

// ── Submit PIN ────────────────────────────────────────────
async function submitPin() {
  showLoading(true);
  try {
    const res  = await fetch(API, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ action: 'login', user_id: selectedUser, pin }),
    });
    const data = await res.json();

    if (data.success) {
      // Redirect based on role
      if (data.role === 'superadmin' || data.role === 'manager') {
        window.location.href = 'dashboard.php';
      } else {
        window.location.href = 'crazymoon-pos.html';
      }
    } else {
      showLoading(false);
      pin = '';
      updateDots();
      document.querySelectorAll('.pin-dot').forEach(d => d.classList.add('error'));
      document.getElementById('pin-error').textContent = 'PIN incorrecto. Intenta de nuevo.';
      setTimeout(clearDotError, 1000);
    }
  } catch(e) {
    showLoading(false);
    pin = '';
    updateDots();
    document.getElementById('pin-error').textContent = 'Error de conexion.';
  }
}

// ── Navigation ────────────────────────────────────────────
function goBack() {
  pin = '';
  selectedUser = null;
  document.getElementById('pin-error').textContent = '';
  showScreen('screen-staff');
}

function showScreen(id) {
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.getElementById(id).classList.add('active');
}

function showLoading(show) {
  document.getElementById('loading').classList.toggle('show', show);
}

// ── Helpers ───────────────────────────────────────────────
function esc(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function escJs(str) {
  return String(str).replace(/'/g, "\\'");
}

// ── Check if already logged in ────────────────────────────
async function checkSession() {
  try {
    const res  = await fetch(API + '?action=check');
    const data = await res.json();
    if (data.success && data.logged_in) {
      if (data.role === 'superadmin' || data.role === 'manager') {
        window.location.href = 'dashboard.php';
      } else {
        window.location.href = 'crazymoon-pos.html';
      }
    }
  } catch(e) {}
}

checkSession();
loadStaff();
</script>
</body>
</html>