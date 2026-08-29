<?php
$pageTitle  = 'Stock Barang';
$pageIcon   = '🏬';
$activePage = 'stock_barang.php';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="section">
    <div class="section-title">📊 Data Stock Barang <span class="badge-count" id="countStock">0</span></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>No</th><th>Nama Barang</th><th class="text-right">Stock Qty</th><th class="text-right">Estimasi Harga</th><th class="text-right">Estimasi Profit</th><th>Aksi</th></tr>
            </thead>
            <tbody id="tblStock"><tr class="empty-row"><td colspan="6">Memuat...</td></tr></tbody>
        </table>
    </div>
</div>

<!-- ── MODAL RIWAYAT STOCK ── -->
<div class="modal-overlay" id="modalRiwayat">
    <div class="modal-box">
        <div class="modal-head">
            <h3>📜 Riwayat Stock — <span id="riwayatNama"></span></h3>
            <button class="modal-close" onclick="closeModal('modalRiwayat')">×</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="font-size:12px;margin-bottom:14px;">Data langsung dari Stock Gudang (Warehouse) Hoki Dimsum — bukan input manual di Marajo.</p>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Tanggal</th><th>Keterangan</th><th class="text-right">Masuk</th><th class="text-right">Keluar</th><th class="text-right">Sisa</th></tr></thead>
                    <tbody id="tblRiwayat"><tr class="empty-row"><td colspan="5">Memuat...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$extraScript = <<<'JS'
let stockCache = [];
let riwayatNamaAktif = '';

async function loadStock() {
    try {
        const res = await apiGet('stock_list');
        stockCache = res.data;
        document.getElementById('countStock').textContent = stockCache.length;
        const tbody = document.getElementById('tblStock');
        if (!stockCache.length) {
            tbody.innerHTML = '<tr class="empty-row"><td colspan="6">Belum ada data stock. Input barang dulu di menu Data Barang.</td></tr>';
            return;
        }
        tbody.innerHTML = stockCache.map((s, i) => `
            <tr>
                <td>${i + 1}</td>
                <td>${s.nama_barang}</td>
                <td class="text-right"><span class="pill ${s.stock_qty <= 0 ? 'danger' : (s.stock_qty < 10 ? 'warn' : 'ok')}">${angka(s.stock_qty)}</span></td>
                <td class="text-right">${rupiah(s.estimasi_harga)}</td>
                <td class="text-right">${rupiah(s.estimasi_profit)}</td>
                <td><button class="btn btn-secondary btn-sm" onclick="viewRiwayat('${s.nama_barang.replace(/'/g,"\\'")}')">📜 Riwayat</button></td>
            </tr>`).join('');
    } catch (e) {
        toast.error('Gagal memuat stock', e.message);
    }
}

function formatTanggal(tgl) {
    const d = new Date(tgl + 'T00:00:00');
    if (isNaN(d)) return tgl;
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

async function viewRiwayat(nama) {
    riwayatNamaAktif = nama;
    document.getElementById('riwayatNama').textContent = nama;
    openModal('modalRiwayat');
    await loadRiwayat();
}

async function loadRiwayat() {
    const tbody = document.getElementById('tblRiwayat');
    tbody.innerHTML = '<tr class="empty-row"><td colspan="5">Memuat...</td></tr>';
    try {
        const res = await apiGet('stock_riwayat', { nama_barang: riwayatNamaAktif });
        if (!res.data.length) {
            tbody.innerHTML = '<tr class="empty-row"><td colspan="5">Belum ada riwayat di Warehouse untuk item ini.</td></tr>';
            return;
        }
        tbody.innerHTML = res.data.map(r => `
            <tr>
                <td>${formatTanggal(r.waktu)}</td>
                <td>${r.keterangan}</td>
                <td class="text-right">${r.masuk > 0 ? angka(r.masuk) : '-'}</td>
                <td class="text-right">${r.keluar > 0 ? angka(r.keluar) : '-'}</td>
                <td class="text-right">${angka(r.sisa)}</td>
            </tr>`).join('');
    } catch (e) {
        toast.error('Gagal memuat riwayat', e.message);
    }
}

loadStock();
JS;
require __DIR__ . '/includes/layout_bottom.php';
