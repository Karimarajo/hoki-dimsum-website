<?php
$pageTitle  = 'Dashboard';
$pageIcon   = '📊';
$activePage = 'dashboard.php';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="panel-grid">
    <div class="card">
        <div class="card-title">💰 Saldo Total</div>
        <div class="card-value" id="cardSaldo">Rp 0</div>
        <div class="card-hint">Profit + Transaksi (Kredit − Debit)</div>
    </div>
    <div class="card">
        <div class="card-title">📈 Profit</div>
        <div class="card-value green" id="cardProfit">Rp 0</div>
        <div class="card-hint">Total dari History Penjualan</div>
    </div>
    <div class="card">
        <div class="card-title">🧮 Estimasi Saldo Keseluruhan</div>
        <div class="card-value blue" id="cardEstSaldo">Rp 0</div>
        <div class="card-hint">Total estimasi nilai jual seluruh stock barang</div>
    </div>
    <div class="card">
        <div class="card-title">💎 Estimasi Profit</div>
        <div class="card-value blue" id="cardEstProfit">Rp 0</div>
        <div class="card-hint">Total profit kalau seluruh stock terjual</div>
    </div>
</div>

<div class="two-col">
    <div class="section">
        <div class="section-title">📦 Diagram Stock Barang <span class="text-muted" style="font-weight:400;font-size:11px;">(top 10 stock terbanyak)</span></div>
        <div class="chart-box" id="chartStok"><div class="text-muted">Memuat...</div></div>
    </div>
    <div class="section">
        <div class="section-title">🚀 Grafik Penjualan <span class="text-muted" style="font-weight:400;font-size:11px;" id="grafikPenjualanSub">(total penjualan per hari, bulan ini)</span></div>
        <div class="chart-box" id="chartPenjualan"><div class="text-muted">Memuat...</div></div>
    </div>
</div>

<?php
$extraScript = <<<'JS'
async function loadDashboard() {
    try {
        const res = await apiGet('dashboard_summary');
        const d = res.data;
        document.getElementById('cardSaldo').textContent     = rupiah(d.saldo);
        document.getElementById('cardProfit').textContent    = rupiah(d.profit_realized);
        document.getElementById('cardEstSaldo').textContent  = rupiah(d.estimasi_saldo_keseluruhan);
        document.getElementById('cardEstProfit').textContent = rupiah(d.estimasi_profit_keseluruhan);

        const stokItems = d.diagram_stok_barang.map(x => ({ label: x.nama_barang, value: x.stock_qty }));
        document.getElementById('chartStok').innerHTML = svgDonutChart(stokItems, { valueFmt: angka });

        if (d.bulan_label) {
            const [y, m] = d.bulan_label.split('-');
            const namaBulan = new Date(`${y}-${m}-01T00:00:00`).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
            document.getElementById('grafikPenjualanSub').textContent = `(total penjualan per hari, ${namaBulan})`;
        }
        const penjualanItems = d.grafik_penjualan.map(x => ({
            label: String(new Date(x.tgl + 'T00:00:00').getDate()),
            value: Number(x.omset),
        }));
        document.getElementById('chartPenjualan').innerHTML = svgLineChart(penjualanItems, { color: '#d32f2f', valueFmt: rupiah });
    } catch (e) {
        toast.error('Gagal memuat dashboard', e.message);
    }
}
loadDashboard();
JS;
require __DIR__ . '/includes/layout_bottom.php';
