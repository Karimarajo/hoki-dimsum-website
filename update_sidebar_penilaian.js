const fs = require('fs');

// Tambah menu "Manajemen Penilaian" ke grup VIP Access di semua halaman
// (desktop sidebar + mobile "Lainnya"), posisi setelah "History Login",
// sebelum link eksternal "Menu Order". VIP only.
const files = fs.readdirSync('.').filter(f => f.endsWith('.html') && f !== 'index.html' && f !== 'manajemen_penilaian.html');

function processFile(file) {
    let content = fs.readFileSync(file, 'utf8');
    let original = content;

    // 1. PAGES_VIP array - tambah entry baru (kalau belum ada)
    content = content.replace(
        /const PAGES_VIP\s*=\s*\[([^\]]*)\];/,
        (m, inner) => inner.includes('manajemen_penilaian.html') ? m : `const PAGES_VIP       = [${inner},'manajemen_penilaian.html'];`
    );

    // 2. Sidebar desktop - grup VIP Access, sesudah History Login
    if (!content.includes("navItem('📝','Manajemen Penilaian','manajemen_penilaian.html')")) {
        content = content.replace(
            /([ \t]*)\+ navItem\('🔑','History Login','login_history\.html'\)(\r?\n)/,
            (m, indent, nl) => `${m}${indent}+ navItem('📝','Manajemen Penilaian','manajemen_penilaian.html')${nl}`
        );
    }

    // 3. Mobile "Lainnya" - grup VIP, sesudah Login Log
    if (!content.includes("label:'Manajemen Penilaian'")) {
        content = content.replace(
            /([ \t]*)items\.push\(\{icon:'🔑',label:'Login Log',link:'login_history\.html'\}\);(\r?\n)/,
            (m, indent, nl) => `${m}${indent}items.push({icon:'📝',label:'Manajemen Penilaian',link:'manajemen_penilaian.html'});${nl}`
        );
    }

    if (content !== original) {
        fs.writeFileSync(file, content, 'utf8');
        console.log('Updated: ' + file);
    } else {
        console.log('SKIP (pola tidak ketemu / VIP group tidak ada): ' + file);
    }
}

for (const file of files) {
    processFile(file);
}
console.log('Sidebar Manajemen Penilaian update complete.');
