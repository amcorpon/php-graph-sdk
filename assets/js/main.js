/* ============================================================
   WhatsApp AI Bot — Main JS
   ============================================================ */

// ── TOAST NOTIFICATIONS ────────────────────────────────────
function toast(message, type = 'success', duration = 3500) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.textContent = message;
  container.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(20px)'; t.style.transition = '.3s'; setTimeout(() => t.remove(), 300); }, duration);
}

// ── API HELPER ─────────────────────────────────────────────
async function api(url, method = 'GET', data = null) {
  const opts = {
    method,
    headers: { 'Content-Type': 'application/json' },
  };
  if (data) opts.body = JSON.stringify(data);
  const res = await fetch(url, opts);
  const json = await res.json();
  return json;
}

// ── MODAL ──────────────────────────────────────────────────
function openModal(id) {
  document.getElementById(id)?.classList.remove('hidden');
  document.getElementById(id)?.style && (document.getElementById(id).style.display = 'flex');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = 'none';
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-backdrop').forEach(m => {
      m.style.display = 'none';
    });
  }
});

document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-backdrop')) {
    e.target.style.display = 'none';
  }
});

// ── CONFIRM DIALOG ─────────────────────────────────────────
function confirmAction(message, callback) {
  if (window.confirm(message)) callback();
}

// ── TABS ───────────────────────────────────────────────────
function initTabs(containerSelector) {
  const container = document.querySelector(containerSelector);
  if (!container) return;

  const buttons = container.querySelectorAll('.tab-btn');
  const contents = container.querySelectorAll('.tab-content');

  buttons.forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.tab;
      buttons.forEach(b => b.classList.remove('active'));
      contents.forEach(c => c.classList.remove('active'));
      btn.classList.add('active');
      container.querySelector(`[data-tab-content="${target}"]`)?.classList.add('active');
    });
  });

  // Activate first tab
  if (buttons.length) buttons[0].click();
}

// ── BOT STATUS POLLING ─────────────────────────────────────
let statusInterval = null;

function startStatusPolling(intervalMs = 5000) {
  if (statusInterval) clearInterval(statusInterval);
  pollBotStatus();
  statusInterval = setInterval(pollBotStatus, intervalMs);
}

async function pollBotStatus() {
  try {
    const res = await api('/api/bot-control.php?action=status');
    if (!res.success) return;
    const status = res.status;
    updateStatusUI(status);
  } catch {}
}

function updateStatusUI(status) {
  const indicator = document.getElementById('bot-status-indicator');
  const label     = document.getElementById('bot-status-label');
  const pairingBox = document.getElementById('pairing-code-box');

  if (!indicator) return;

  if (status.is_connected == 1) {
    indicator.className = 'bot-status-pill connected';
    indicator.innerHTML = '<span class="status-dot green"></span> Conectado';
    if (label) label.textContent = status.phone_number ? '📱 ' + status.phone_number : 'Conectado';
    if (pairingBox) pairingBox.style.display = 'none';
  } else {
    indicator.className = 'bot-status-pill disconnected';
    indicator.innerHTML = '<span class="status-dot red"></span> Desconectado';
    if (label) label.textContent = status.status_message || 'Desconectado';
    if (pairingBox && status.pairing_code) {
      pairingBox.style.display = 'block';
      const codeEl = document.getElementById('pairing-code-value');
      if (codeEl) codeEl.textContent = status.pairing_code;
    }
  }
}

// ── MOBILE SIDEBAR ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('sidebar-toggle');
  const sidebar = document.querySelector('.sidebar');

  if (toggle && sidebar) {
    toggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
    });
    document.addEventListener('click', e => {
      if (!sidebar.contains(e.target) && e.target !== toggle) {
        sidebar.classList.remove('open');
      }
    });
  }
});

// ── FORMAT CURRENCY ────────────────────────────────────────
function formatCurrency(value) {
  return parseFloat(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

// ── DATE/TIME HELPERS ──────────────────────────────────────
function formatDateBR(dateStr) {
  if (!dateStr) return '';
  const [y, m, d] = dateStr.split('-');
  return `${d}/${m}/${y}`;
}

function formatTimeBR(timeStr) {
  return timeStr ? timeStr.slice(0, 5) : '';
}

// ── DEBOUNCE ───────────────────────────────────────────────
function debounce(fn, ms = 300) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

// ── COPY TO CLIPBOARD ──────────────────────────────────────
function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => {
    toast('Copiado!', 'success', 1500);
  }).catch(() => {
    const el = document.createElement('textarea');
    el.value = text;
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    el.remove();
    toast('Copiado!', 'success', 1500);
  });
}

// ── AUTO RESIZE TEXTAREA ───────────────────────────────────
document.addEventListener('input', e => {
  if (e.target.dataset.autoResize) {
    e.target.style.height = 'auto';
    e.target.style.height = e.target.scrollHeight + 'px';
  }
});
