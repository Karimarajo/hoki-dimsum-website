<?php
$pageTitle  = 'Data Barang';
$pageIcon   = '📦';
$activePage = 'data_barang.php';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="section">
    <div class="section-title" id="formTitle">➕ Input Barang</div>
    <form id="formBarang">
        <input type="hidden" id="fId">
        <div class="form-grid">
            <div class="field">
                <label>Nama Barang</label>
                <select id="fNama" required>
                    <option value="">-- Pilih dari Stock Gudang --</option>
                </select>
            </div>
            <div class="field">
                <label>Harga Barang (total batch)</label>
                <input type="text" id="fHargaBarang" class="fmt-ribuan" inputmode="numeric" placeholder="0">
            </div>
            <div class="field">
                <label>Qty</label>
                <input type="number" id="fQty" min="1" step="1" required placeholder="0">
            </div>
            <div class="field">
                <label>Harga Modal Satuan <span class="text-muted" style="font-weight:400;">(otomatis)</span></label>
                <input type="text" id="fHargaModal" inputmode="numeric" placeholder="0" readonly>
            </div>
            <div class="field">
                <label>Harga Jual Satuan</label>
                <input type="text" id="fHargaJual" class="fmt-ribuan" inputmode="numeric" placeholder="0">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="btnSimpanBarang">💾 Simpan</button>
            <button type="button" class="btn btn-secondary" id="btnBatalEditBarang" style="display:none;" onclick="batalEditBarang()">Batal Edit</button>
        </div>
    </form>
</div>

<div class="section">
    <div class="section-title">📋 List Track Barang <span class="badge-count" id="countBarang">0</span></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No</th><th>Nama Barang</th><th class="text-right">Harga Barang</th><th class="text-right">Qty</th>
                    <th class="text-right">Modal Satuan</th><th class="text-right">Jual Satuan</th>
                    <th class="text-right">Profit</th><th class="text-right">Margin</th><th>Aksi</th>
                </tr>
            </thead>
            <tbody id="tblBarang"><tr class="empty-row"><td colspan="9">Memuat...</td></tr></tbody>
        </table>
    </div>
</div>

<?php
$extraScript = <<<'JS'
let barangCache = [];

// Harga Modal Satuan = Harga Barang ÷ Qty, dihitung otomatis (field readonly).
function hitungModalOtomatis() {
    const hargaBarang = Number(rawNumber(document.getElementById('fHargaBarang'))) || 0;
    const qty = Number(document.getElementById('fQty').value) || 0;
    const modal = qty > 0 ? Math.round(hargaBarang / qty) : 0;
    document.getElementById('fHargaModal').value = modal > 0 ? angka(modal) : '';
}
document.getElementById('fHargaBarang').addEventListener('input', hitungModalOtomatis);
document.getElementById('fQty').addEventListener('input', hitungModalOtomatis);

async function loadNamaBarang() {
    const res = await apiGet('barang_nama_list');
    const sel = document.getElementById('fNama');
    const current = sel.value;
    sel.innerHTML = '<option value="">-- Pilih dari Stock Gudang --</option>' +
        res.data.map(n => `<option value="${n.replace(/"/g,'&quot;')}">${n}</option>`).join('');
    if (current) sel.value = current;
}

async function loadBarang() {
    try {
        const res = await apiGet('barang_masuk_list');
        barangCache = res.data;
        document.getElementById('countBarang').textContent = barangCache.length;
        const tbody = document.getElementById('tblBarang');
        if (!barangCache.length) {
            tbody.innerHTML = '<tr class="empty-row"><td colspan="9">Belum ada data barang.</td></tr>';
            return;
        }
        tbody.innerHTML = barangCache.map((b, i) => `
            <tr>
                <td>${i + 1}</td>
                <td>${b.nama_barang}</td>
                <td class="text-right">${rupiah(b.harga_barang)}</td>
                <td class="text-right">${angka(b.qty)}</td>
                <td class="text-right">${rupiah(b.harga_modal_satuan)}</td>
                <td class="text-right">${rupiah(b.harga_jual_satuan)}</td>
                <td class="text-right">${rupiah(b.profit)}</td>
                <td class="text-right">${Number(b.margin).toFixed(1)}%</td>
                <td>
                    <button class="btn-icon" onclick="editBarang(${b.id})" title="Edit">✏️</button>
                    <button class="btn-icon danger" onclick="hapusBarang(${b.id})" title="Hapus">🗑️</button>
                </td>
            </tr>`).join('');
    } catch (e) {
        toast.error('Gagal memuat data barang', e.message);
    }
}

function editBarang(id) {
    const b = barangCache.find(x => x.id === id);
    if (!b) return;
    document.getElementById('fId').value = b.id;
    document.getElementById('fNama').value = b.nama_barang;
    document.getElementById('fHargaBarang').value = angka(b.harga_barang);
    document.getElementById('fQty').value = b.qty;
    document.getElementById('fHargaModal').value = angka(b.harga_modal_satuan);
    document.getElementById('fHargaJual').value = angka(b.harga_jual_satuan);
    document.getElementById('formTitle').textContent = '✏️ Edit Barang #' + b.id;
    document.getElementById('btnBatalEditBarang').style.display = '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function batalEditBarang() {
    document.getElementById('formBarang').reset();
    document.getElementById('fId').value = '';
    document.getElementById('formTitle').textContent = '➕ Input Barang';
    document.getElementById('btnBatalEditBarang').style.display = 'none';
}

async function hapusBarang(id) {
    const ok = await showConfirm('Hapus data barang ini? Stock terkait akan otomatis dikoreksi.', 'Ya, Hapus');
    if (!ok) return;
    try {
        await apiPost('barang_masuk_delete', { id });
        toast.success('Data barang dihapus.');
        loadBarang();
    } catch (e) {
        toast.error('Gagal menghapus', e.message);
    }
}

document.getElementById('formBarang').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
        id: document.getElementById('fId').value,
        nama_barang: document.getElementById('fNama').value.trim(),
        harga_barang: rawNumber(document.getElementById('fHargaBarang')),
        qty: document.getElementById('fQty').value,
        harga_jual_satuan: rawNumber(document.getElementById('fHargaJual')),
    };
    try {
        await apiPost('barang_masuk_save', payload);
        toast.success('Berhasil disimpan.');
        batalEditBarang();
        loadNamaBarang();
        loadBarang();
    } catch (e) {
        toast.error('Gagal menyimpan', e.message);
    }
});

loadNamaBarang();
loadBarang();
JS;
require __DIR__ . '/includes/layout_bottom.php';
