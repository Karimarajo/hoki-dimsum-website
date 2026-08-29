const fs = require('fs');

// Tambah query version ke <script src="config.js"> di semua file HTML, supaya
// browser & CDN Hostinger (cache 7 hari utk .js, lihat .htaccess) wajib ambil
// versi baru begitu config.js berubah - persis pola yang sudah dipakai
// style.css (style.css?v=20260501). Dipicu oleh bug: fungsi bukaLinkWA() sempat
// ketinggalan ter-upload ke production config.js, dan tanpa versioning ini,
// browser yang sudah kadung cache config.js lama tidak akan ambil fix-nya
// sampai 7 hari walau file di server sudah dibetulkan.
const VERSION = '20260829';

const files = fs.readdirSync('.').filter(f => f.endsWith('.html'));

function processFile(file) {
    let content = fs.readFileSync(file, 'utf8');
    let original = content;

    content = content.replace(
        /<script src="config\.js"><\/script>/g,
        `<script src="config.js?v=${VERSION}"></script>`
    );

    if (content !== original) {
        fs.writeFileSync(file, content, 'utf8');
        console.log('Updated: ' + file);
    }
}

for (const file of files) {
    processFile(file);
}
console.log('config.js cache-bust update complete.');
