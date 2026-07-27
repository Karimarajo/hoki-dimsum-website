/**
 * toast.js — Hoki POS Global Notification System
 * 
 * CARA PAKAI:
 * Tambah <script src="toast.js"></script> sebelum </body> di setiap halaman
 * 
 * TOAST:
 *   toast.success('Berhasil Disimpan!')
 *   toast.error('Gagal koneksi server!')
 *   toast.warning('Pilih menu dulu!')
 *   toast.info('Info tambahan')
 * 
 * CONFIRM (async):
 *   const ok = await showConfirm('Hapus transaksi ini?')
 *   if (ok) { ... }
 * 
 *   Custom label & warna:
 *   const ok = await showConfirm('Kosongkan semua?', 'Kosongkan', 'Batal', '🗑️', 'red')
 */

const _toastCSS = document.createElement('style');
_toastCSS.textContent = `
/* ── TOAST CONTAINER ── */
#hoki-toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    pointer-events: none;
    max-width: 360px;
}

/* Fallback jarak antar-toast (gap tidak didukung Chrome flex < 84) */
#hoki-toast-container > * + * { margin-top: 10px; }

/* Mobile: muncul di atas cart-bar + bottom-nav */
@media (max-width: 844px) {
    #hoki-toast-container {
        top: auto;
        bottom: 140px;
        right: 12px;
        left: 12px;
        max-width: 100%;
    }
}

.hoki-toast {
    display: flex;
    align-items: center;
    background: #ffffff;
    border: 1px solid #e6e6e0;
    border-radius: 14px;
    padding: 13px 16px;
    min-width: 280px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12), 0 2px 6px rgba(0,0,0,0.06);
    pointer-events: all;
    animation: toastIn 0.3s cubic-bezier(0.22,1,0.36,1) both;
    position: relative;
    overflow: hidden;
}
.hoki-toast > * + * { margin-left: 12px; }
.hoki-toast.hide {
    animation: toastOut 0.25s ease forwards;
}

@media (max-width: 844px) {
    .hoki-toast { min-width: unset; width: 100%; }
}

@keyframes toastIn {
    from { opacity: 0; transform: translateX(40px) scale(0.95); }
    to   { opacity: 1; transform: translateX(0) scale(1); }
}
@keyframes toastOut {
    from { opacity: 1; transform: translateX(0) scale(1); }
    to   { opacity: 0; transform: translateX(40px) scale(0.95); }
}

/* Progress bar bawah */
.hoki-toast::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0;
    height: 3px;
    border-radius: 0 0 14px 14px;
    animation: toastProgress linear forwards;
}
.hoki-toast.success::after { background: #08c611; animation-duration: 3s; }
.hoki-toast.error::after   { background: #d32f2f; animation-duration: 4s; }
.hoki-toast.warning::after { background: #f59e0b; animation-duration: 3.5s; }
.hoki-toast.info::after    { background: #1565c0; animation-duration: 3s; }

@keyframes toastProgress {
    from { width: 100%; }
    to   { width: 0%; }
}

/* Accent bar kiri */
.hoki-toast::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    border-radius: 14px 0 0 14px;
}
.hoki-toast.success::before { background: #08c611; }
.hoki-toast.error::before   { background: #d32f2f; }
.hoki-toast.warning::before { background: #f59e0b; }
.hoki-toast.info::before    { background: #1565c0; }

/* Icon box */
.hoki-toast-icon {
    width: 36px; height: 36px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; flex-shrink: 0;
}
.hoki-toast.success .hoki-toast-icon { background: #f0fdf1; }
.hoki-toast.error   .hoki-toast-icon { background: #fff5f5; }
.hoki-toast.warning .hoki-toast-icon { background: #fffbeb; }
.hoki-toast.info    .hoki-toast-icon { background: #e3f2fd; }

.hoki-toast-body { flex: 1; }
.hoki-toast-title {
    font-size: 13px; font-weight: 700;
    color: #1c1c1a;
    font-family: 'DM Sans', 'Plus Jakarta Sans', sans-serif;
    line-height: 1.3;
}
.hoki-toast-sub {
    font-size: 11px; color: #787870;
    margin-top: 2px;
    font-family: 'DM Sans', 'Plus Jakarta Sans', sans-serif;
}
.hoki-toast-close {
    background: none; border: none;
    color: #b0b0a8; font-size: 18px;
    cursor: pointer; padding: 2px 4px;
    border-radius: 6px; flex-shrink: 0; line-height: 1;
    transition: color 0.15s, background 0.15s;
    pointer-events: all;
}
.hoki-toast-close:hover { color: #1c1c1a; background: #f4f4ef; }

/* ── CONFIRM MODAL ── */
#hoki-confirm-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 99998;
    align-items: center; justify-content: center;
    backdrop-filter: blur(3px);
}
#hoki-confirm-overlay.open { display: flex; }

.hoki-confirm-box {
    background: #ffffff;
    border-radius: 20px;
    padding: 28px 28px 20px;
    width: 90%; max-width: 360px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.18), 0 4px 16px rgba(0,0,0,0.08);
    border: 1px solid #e6e6e0;
    animation: confirmIn 0.3s cubic-bezier(0.22,1,0.36,1) both;
}
@keyframes confirmIn {
    from { opacity: 0; transform: scale(0.9) translateY(20px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}

.hoki-confirm-icon {
    width: 52px; height: 52px;
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; margin-bottom: 16px;
    background: #fff5f5;
}
.hoki-confirm-title {
    font-size: 16px; font-weight: 800;
    color: #1c1c1a;
    font-family: 'DM Sans', 'Plus Jakarta Sans', sans-serif;
    margin-bottom: 8px; line-height: 1.3;
}
.hoki-confirm-msg {
    font-size: 13px; color: #787870;
    font-family: 'DM Sans', 'Plus Jakarta Sans', sans-serif;
    margin-bottom: 22px; line-height: 1.5;
}
.hoki-confirm-actions { display: flex; }
.hoki-confirm-actions > * + * { margin-left: 10px; }
.hoki-confirm-cancel {
    flex: 1; padding: 12px;
    background: #f4f4ef; color: #1c1c1a;
    border: 1.5px solid #e6e6e0; border-radius: 12px;
    font-size: 13px; font-weight: 700; cursor: pointer;
    font-family: 'DM Sans', 'Plus Jakarta Sans', sans-serif;
    transition: background 0.15s;
}
.hoki-confirm-cancel:hover { background: #e8e8e2; }
.hoki-confirm-ok {
    flex: 1; padding: 12px;
    background: #d32f2f; color: white;
    border: none; border-radius: 12px;
    font-size: 13px; font-weight: 700; cursor: pointer;
    font-family: 'DM Sans', 'Plus Jakarta Sans', sans-serif;
    box-shadow: 0 4px 12px rgba(211,47,47,0.3);
    transition: filter 0.15s;
}
.hoki-confirm-ok:hover { filter: brightness(0.9); }
.hoki-confirm-ok.green { background: #08c611; box-shadow: 0 4px 12px rgba(8,198,17,0.3); }
.hoki-confirm-ok.blue  { background: #2e7afc; box-shadow: 0 4px 12px rgba(46,122,252,0.3); }
`;
document.head.appendChild(_toastCSS);

/* ── TOAST CONTAINER ── */
const _toastContainer = document.createElement('div');
_toastContainer.id = 'hoki-toast-container';
document.body.appendChild(_toastContainer);

/* ── CONFIRM OVERLAY ── */
const _confirmOverlay = document.createElement('div');
_confirmOverlay.id = 'hoki-confirm-overlay';
_confirmOverlay.innerHTML = `
    <div class="hoki-confirm-box" onclick="event.stopPropagation()">
        <div class="hoki-confirm-icon" id="hoki-confirm-icon">⚠️</div>
        <div class="hoki-confirm-title" id="hoki-confirm-title">Konfirmasi</div>
        <div class="hoki-confirm-msg"   id="hoki-confirm-msg"></div>
        <div class="hoki-confirm-actions">
            <button class="hoki-confirm-cancel" id="hoki-confirm-cancel">Batal</button>
            <button class="hoki-confirm-ok"     id="hoki-confirm-ok">Ya, Lanjutkan</button>
        </div>
    </div>`;
document.body.appendChild(_confirmOverlay);

/* ── TOAST FUNCTION ── */
const _toastIcons = {
    success: '✅',
    error:   '❌',
    warning: '⚠️',
    info:    'ℹ️',
};

function showToast(message, type = 'info', subtitle = '', duration = 3000) {
    const toast = document.createElement('div');
    toast.className = `hoki-toast ${type}`;
    toast.innerHTML = `
        <div class="hoki-toast-icon">${_toastIcons[type] || 'ℹ️'}</div>
        <div class="hoki-toast-body">
            <div class="hoki-toast-title">${message}</div>
            ${subtitle ? `<div class="hoki-toast-sub">${subtitle}</div>` : ''}
        </div>
        <button class="hoki-toast-close" onclick="this.closest('.hoki-toast').remove()">×</button>`;
    _toastContainer.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 280);
    }, duration);
}

/* ── CONFIRM FUNCTION ── */
function showConfirm(message, okLabel = 'Ya, Lanjutkan', cancelLabel = 'Batal', icon = '⚠️', okColor = 'red') {
    return new Promise(resolve => {
        document.getElementById('hoki-confirm-msg').innerText   = message;
        document.getElementById('hoki-confirm-icon').innerText  = icon;
        document.getElementById('hoki-confirm-title').innerText = 'Konfirmasi';

        const okBtn     = document.getElementById('hoki-confirm-ok');
        const cancelBtn = document.getElementById('hoki-confirm-cancel');
        okBtn.innerText     = okLabel;
        cancelBtn.innerText = cancelLabel;
        okBtn.className = `hoki-confirm-ok ${okColor === 'red' ? '' : okColor}`;

        _confirmOverlay.classList.add('open');

        const cleanup = (result) => {
            _confirmOverlay.classList.remove('open');
            okBtn.onclick     = null;
            cancelBtn.onclick = null;
            _confirmOverlay.onclick = null;
            resolve(result);
        };

        okBtn.onclick             = () => cleanup(true);
        cancelBtn.onclick         = () => cleanup(false);
        _confirmOverlay.onclick   = (e) => { if (e.target === _confirmOverlay) cleanup(false); };
    });
}

/* ── SHORTHAND ── */
const toast = {
    success: (msg, sub) => showToast(msg, 'success', sub, 3000),
    error:   (msg, sub) => showToast(msg, 'error',   sub, 4000),
    warning: (msg, sub) => showToast(msg, 'warning', sub, 3500),
    info:    (msg, sub) => showToast(msg, 'info',    sub, 3000),
};