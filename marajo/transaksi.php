<?php
$pageTitle  = 'Transaksi';
$pageIcon   = '💵';
$activePage = 'transaksi.php';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="section">
    <div class="section-title" id="formTitleTx">➕ Input Transaksi</div>
    <form id="formTransaksi">
        <input type="hidden" id="tId">
        <div class="form-grid">
            <div class="field" style="grid-column: span 2;">
                <label>Keterangan</label>
                <input type="text" id="tKet" placeholder="Contoh: Setoran outlet / Belanja bahan baku" required>
            </div>
            <div class="field">
                <label>Debit / Kredit</label>
                <select id="tTipe">
                    <option value="debit">Debit (uang keluar)</option>
                    <option value="kredit">Kredit (uang masuk)</option>
                </select>
            </div>
            <div class="field">
                <label>Nominal</label>
                <input type="text" id="tNominal" class="fmt-ribuan" inputmode="numeric" placeholder="0" required>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="btnSimpanTx">💾 Simpan</button>
            <button type="button" class="btn btn-secondary" id="btnBatalEditTx" style="display:none;" onclick="batalEditTx()">Batal Edit</button>
        </div>
    </form>
</div>

<div class="section">
    <div class="section-title">📄 History Transaksi <span class="badge-count" id="countTx">0</span></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>No</th><th>Waktu</th><th>Keterangan</th><th>Tipe</th><th class="text-right">Nominal</th><th>Aksi</th></tr>
            </thead>
            <tbody id="tblTransaksi"><tr class="empty-row"><td colspan="6">Memuat...</td></tr></tbody>
        </table>
    </div>
</div>

<?php
$extraScript = <<<'JS'
let txCache = [];

async function loadTransaksi() {
    try {
        const res = await apiGet('transaksi_list');
        txCache = res.data;
        document.getElementById('countTx').textContent = txCache.length;
        const tbody = document.getElementById('tblTransaksi');
        if (!txCache.length) {
            tbody.innerHTML = '<tr class="empty-row"><td colspan="6">Belum ada transaksi.</td></tr>';
            return;
        }
        tbody.innerHTML = txCache.map((t, i) => `
            <tr>
                <td>${i + 1}</td>
                <td>${formatTanggalWaktu(t.waktu)}</td>
                <td>${t.keterangan}</td>
                <td><span class="pill ${t.tipe}">${t.tipe === 'debit' ? 'Debit' : 'Kredit'}</span></td>
                <td class="text-right">${rupiah(t.nominal)}</td>
                <td>
                    <button class="btn-icon" onclick="editTx(${t.id})" title="Edit">✏️</button>
                    <button class="btn-icon danger" onclick="hapusTx(${t.id})" title="Hapus">🗑️</button>
                </td>
            </tr>`).join('');
    } catch (e) {
        toast.error('Gagal memuat transaksi', e.message);
    }
}

function editTx(id) {
    const t = txCache.find(x => x.id === id);
    if (!t) return;
    document.getElementById('tId').value = t.id;
    document.getElementById('tKet').value = t.keterangan;
    document.getElementById('tTipe').value = t.tipe;
    document.getElementById('tNominal').value = angka(t.nominal);
    document.getElementById('formTitleTx').textContent = '✏️ Edit Transaksi #' + t.id;
    document.getElementById('btnBatalEditTx').style.display = '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function batalEditTx() {
    document.getElementById('formTransaksi').reset();
    document.getElementById('tId').value = '';
    document.getElementById('formTitleTx').textContent = '➕ Input Transaksi';
    document.getElementById('btnBatalEditTx').style.display = 'none';
}

async function hapusTx(id) {
    const ok = await showConfirm('Hapus transaksi ini?', 'Ya, Hapus');
    if (!ok) return;
    try {
        await apiPost('transaksi_delete', { id });
        toast.success('Transaksi dihapus.');
        loadTransaksi();
    } catch (e) {
        toast.error('Gagal menghapus', e.message);
    }
}

document.getElementById('formTransaksi').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
        id: document.getElementById('tId').value,
        keterangan: document.getElementById('tKet').value.trim(),
        tipe: document.getElementById('tTipe').value,
        nominal: rawNumber(document.getElementById('tNominal')),
    };
    try {
        await apiPost('transaksi_save', payload);
        toast.success('Transaksi disimpan.');
        batalEditTx();
        loadTransaksi();
    } catch (e) {
        toast.error('Gagal menyimpan', e.message);
    }
});

loadTransaksi();
JS;
require __DIR__ . '/includes/layout_bottom.php';
