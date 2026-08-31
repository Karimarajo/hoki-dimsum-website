/**
 * app.js — Helper bersama semua halaman Marajo.
 */

const API = 'api.php';

function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    const mc = document.getElementById('mainContent');
    if (window.innerWidth <= 900) {
        sb.classList.toggle('mobile-open');
        return;
    }
    sb.classList.toggle('collapsed');
    mc.classList.toggle('expanded');
    localStorage.setItem('marajoSidebarState', sb.classList.contains('collapsed') ? 'small' : 'large');
}

document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('marajoSidebarState') === 'small') {
        const sb = document.getElementById('sidebar');
        const mc = document.getElementById('mainContent');
        if (sb && mc) { sb.classList.add('collapsed'); mc.classList.add('expanded'); }
    }
});

/** Format angka jadi "Rp 1.234.567". */
function rupiah(n) {
    n = Number(n) || 0;
    return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

/** Format angka biasa dengan pemisah ribuan (tanpa "Rp"). */
function angka(n) {
    n = Number(n) || 0;
    return Math.round(n).toLocaleString('id-ID');
}

function formatTanggalWaktu(str) {
    if (!str) return '-';
    const d = new Date(str.replace(' ', 'T'));
    if (isNaN(d)) return str;
    return d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

/** POST JSON ke api.php?action=... */
async function apiPost(action, payload = {}) {
    const res = await fetch(`${API}?action=${encodeURIComponent(action)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
    const data = await res.json().catch(() => null);
    if (res.status === 401) {
        toast.error('Sesi habis, silakan login ulang.');
        setTimeout(() => location.href = 'login.php', 1500);
        throw new Error('unauthorized');
    }
    if (!data || data.status === 'error') {
        throw new Error((data && data.message) || 'Terjadi kesalahan.');
    }
    return data;
}

/** GET ke api.php?action=...&(query params) */
async function apiGet(action, params = {}) {
    const qs = new URLSearchParams(params).toString();
    const res = await fetch(`${API}?action=${encodeURIComponent(action)}${qs ? '&' + qs : ''}`);
    const data = await res.json().catch(() => null);
    if (res.status === 401) {
        toast.error('Sesi habis, silakan login ulang.');
        setTimeout(() => location.href = 'login.php', 1500);
        throw new Error('unauthorized');
    }
    if (!data || data.status === 'error') {
        throw new Error((data && data.message) || 'Terjadi kesalahan.');
    }
    return data;
}

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

/** Ambil nilai angka murni (tanpa titik ribuan) dari input class="fmt-ribuan". */
function rawNumber(input) {
    return (input.value || '').replace(/\D/g, '');
}

/** Aktifkan format ribuan otomatis untuk semua input[class~="fmt-ribuan"] di halaman ini. */
function initFmtRibuan() {
    document.querySelectorAll('input.fmt-ribuan').forEach(el => {
        if (el.dataset.fmtBound) return;
        el.dataset.fmtBound = '1';
        el.addEventListener('input', () => {
            const digits = el.value.replace(/\D/g, '');
            el.value = digits === '' ? '' : Number(digits).toLocaleString('id-ID');
        });
    });
}
document.addEventListener('DOMContentLoaded', initFmtRibuan);

/** Palet warna donut/pie — diulang kalau item lebih banyak dari palet. */
const CHART_COLORS = ['#1565c0', '#d32f2f', '#2e7d32', '#f59e0b', '#7c3aed', '#0891b2', '#db2777', '#65a30d', '#ea580c', '#4338ca'];

/**
 * Donut chart SVG + legend keterangan warna, tanpa dependency eksternal.
 * items: [{label, value}]. Return HTML string (svg + legend).
 */
function svgDonutChart(items, { size = 200, valueFmt = angka } = {}) {
    if (!items.length) return '<div class="text-muted" style="padding:30px 0;text-align:center;">Belum ada data.</div>';
    const total = items.reduce((s, i) => s + i.value, 0) || 1;
    const r = size / 2 - 16, cx = size / 2, cy = size / 2;
    const circumference = 2 * Math.PI * r;

    let offset = 0;
    let circles = '';
    items.forEach((it, idx) => {
        const color = CHART_COLORS[idx % CHART_COLORS.length];
        const frac = it.value / total;
        const dash = frac * circumference;
        circles += `<circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="${color}" stroke-width="26"
            stroke-dasharray="${dash} ${circumference - dash}" stroke-dashoffset="${-offset}"
            transform="rotate(-90 ${cx} ${cy})">
            <title>${it.label}: ${valueFmt(it.value)} (${(frac * 100).toFixed(1)}%)</title>
        </circle>`;
        offset += dash;
    });

    const svg = `<svg viewBox="0 0 ${size} ${size}" width="${size}" height="${size}" xmlns="http://www.w3.org/2000/svg">${circles}</svg>`;
    const legend = '<div class="chart-legend">' + items.map((it, idx) =>
        `<span><span class="dot" style="background:${CHART_COLORS[idx % CHART_COLORS.length]}"></span>${it.label}: ${valueFmt(it.value)}</span>`
    ).join('') + '</div>';

    return `<div style="display:flex;justify-content:center;">${svg}</div>${legend}`;
}

/**
 * Line chart SVG sederhana tanpa dependency eksternal.
 * items: [{label, value}].
 */
function svgLineChart(items, { height = 200, color = '#d32f2f', valueFmt = rupiah } = {}) {
    if (!items.length) return '<div class="text-muted" style="padding:30px 0;text-align:center;">Belum ada data penjualan bulan ini.</div>';
    const padL = 16, padR = 16, padTop = 20, padBottom = 34, stepX = 40;
    const w = Math.max(items.length * stepX + padL + padR, 240);
    const h = height;
    const max = Math.max(...items.map(i => i.value), 1);
    const chartH = h - padTop - padBottom;

    const points = items.map((it, idx) => ({
        x: padL + idx * stepX + stepX / 2,
        y: padTop + (chartH - (it.value / max) * chartH),
        ...it,
    }));
    const path = points.map((p, idx) => (idx === 0 ? 'M' : 'L') + p.x.toFixed(1) + ' ' + p.y.toFixed(1)).join(' ');

    let dots = '';
    points.forEach(p => {
        dots += `<circle cx="${p.x}" cy="${p.y}" r="3.5" fill="${color}"><title>${p.label}: ${valueFmt(p.value)}</title></circle>`;
        dots += `<text x="${p.x}" y="${h - padBottom + 18}" font-size="10" text-anchor="middle" fill="#787870" font-family="DM Mono, monospace">${p.label}</text>`;
    });

    return `<svg viewBox="0 0 ${w} ${h}" width="${w}" height="${h}" xmlns="http://www.w3.org/2000/svg">
        <path d="${path}" fill="none" stroke="${color}" stroke-width="2.5"/>
        ${dots}
    </svg>`;
}
