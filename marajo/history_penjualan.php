<?php
$pageTitle  = 'History Penjualan';
$pageIcon   = '🧾';
$activePage = 'history_penjualan.php';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="section">
    <div class="section-title">🧾 History Penjualan <span class="badge-count" id="countPenjualan">0</span></div>
    <p class="text-muted" style="font-size:12px;margin-bottom:14px;">Otomatis dari data "keluar" di Riwayat Stock (Stock Gudang Warehouse) — bukan input manual.</p>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Tanggal</th><th>Nama Barang</th><th class="text-right">Qty</th><th class="text-right">Harga</th><th class="text-right">Profit</th><th>Keterangan</th></tr>
            </thead>
            <tbody id="tblPenjualan"><tr class="empty-row"><td colspan="6">Memuat...</td></tr></tbody>
        </table>
    </div>
</div>

<?php
$extraScript = <<<'JS'
function formatTanggal(tgl) {
    const d = new Date(tgl + 'T00:00:00');
    if (isNaN(d)) return tgl;
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

async function loadPenjualan() {
    try {
        const res = await apiGet('penjualan_list');
        document.getElementById('countPenjualan').textContent = res.data.length;
        const tbody = document.getElementById('tblPenjualan');
        if (!res.data.length) {
            tbody.innerHTML = '<tr class="empty-row"><td colspan="6">Belum ada penjualan tercatat.</td></tr>';
            return;
        }
        tbody.innerHTML = res.data.map(p => `
            <tr>
                <td>${formatTanggal(p.waktu)}</td>
                <td>${p.nama_barang}</td>
                <td class="text-right">${angka(p.qty)}</td>
                <td class="text-right">${rupiah(p.omset)}</td>
                <td class="text-right">${rupiah(p.profit)}</td>
                <td>${p.keterangan || '-'}</td>
            </tr>`).join('');
    } catch (e) {
        toast.error('Gagal memuat history penjualan', e.message);
    }
}
loadPenjualan();
JS;
require __DIR__ . '/includes/layout_bottom.php';
