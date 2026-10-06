const fs = require('fs');

const files = [
    'bahan_baku.html', 'belanja.html', 'customer_hoki.html', 'dashboard.html',
    'edukasi.html', 'history.html', 'hpp_produk.html', 'investor_omset.html',
    'investor_profit.html', 'laporan.html', 'laporan_staff.html', 'login_history.html',
    'pelamar_kerja.html', 'performa.html', 'produk.html', 'promo.html', 'rekap.html',
    'restock.html', 'salary_staff.html', 'statistik.html', 'stok.html', 'user.html',
    'warehouse.html'
];

const LOGOUT_LINE = "    items.push({icon:'🚪',label:'Logout',link:'#',action:'logout()'});";

const MARAJO_BLOCK = `    if (['kurniarp','hanazaf'].includes((currentUser.user || '').toLowerCase())) {
        items.push({icon:'🏢',label:'PT Marajo Barokah',link:'#',action:'bukaMarajo()'});
    }
${LOGOUT_LINE}`;

function processFile(file) {
    let content = fs.readFileSync(file, 'utf8');
    let original = content;

    if (content.includes(LOGOUT_LINE) && !content.includes("action:'bukaMarajo()'")) {
        content = content.replace(LOGOUT_LINE, MARAJO_BLOCK);
    }

    if (content !== original) {
        fs.writeFileSync(file, content, 'utf8');
        console.log('Updated more menu (Marajo) in: ' + file);
    } else {
        console.log('Skipped (already present or pattern not found): ' + file);
    }
}

for (const file of files) {
    processFile(file);
}
console.log('More menu Marajo update complete.');
