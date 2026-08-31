const fs = require('fs');

// Tambah 2 menu baru ke sidebar (desktop) + "Lainnya" (mobile) di semua halaman:
//   - Data Pelamar Kerja -> grup Executive
//   - Cek Lokasi -> grup VIP Access
const files = fs.readdirSync('.').filter(f => f.endsWith('.html') && f !== 'index.html');

function processFile(file) {
    let content = fs.readFileSync(file, 'utf8');
    let original = content;

    // 1. PAGES_EXEC / PAGES_VIP array - tambah entry baru
    content = content.replace(
        /const PAGES_EXEC\s*=\s*\[([^\]]+)\];/,
        (m, inner) => inner.includes('pelamar_kerja.html') ? m : `const PAGES_EXEC      = [${inner},'pelamar_kerja.html'];`
    );
    content = content.replace(
        /const PAGES_VIP\s*=\s*\[([^\]]+)\];/,
        (m, inner) => inner.includes('cek_lokasi.html') ? m : `const PAGES_VIP       = [${inner},'cek_lokasi.html'];`
    );

    // 2. Sidebar desktop - grup Executive: tambah navItem sebelum blok marajo (atau
    //    sebelum h += navGroup('executive'...) kalau blok marajo tidak ada di file ini)
    if (!content.includes("navItem('📋','Data Pelamar Kerja','pelamar_kerja.html')")) {
        if (content.includes("+ navItem('👨‍👩‍👦','Customer Hoki','customer_hoki.html');")) {
            content = content.replace(
                "+ navItem('👨‍👩‍👦','Customer Hoki','customer_hoki.html');",
                "+ navItem('👨‍👩‍👦','Customer Hoki','customer_hoki.html')\n            + navItem('📋','Data Pelamar Kerja','pelamar_kerja.html');"
            );
        }
    }

    // 3. Sidebar desktop - grup VIP Access: tambah navItem setelah History Login
    if (!content.includes("navItem('📍','Cek Lokasi','cek_lokasi.html')")) {
        if (content.includes("+ navItem('🔑','History Login','login_history.html')")) {
            content = content.replace(
                "+ navItem('🔑','History Login','login_history.html')",
                "+ navItem('🔑','History Login','login_history.html')\n            + navItem('📍','Cek Lokasi','cek_lokasi.html')"
            );
        }
    }

    // 4. Mobile "Lainnya" - grup Executive
    if (!content.includes("label:'Data Pelamar Kerja'")) {
        if (content.includes("items.push({icon:'👨‍👩‍👦',label:'Customer Hoki',link:'customer_hoki.html'});")) {
            content = content.replace(
                "items.push({icon:'👨‍👩‍👦',label:'Customer Hoki',link:'customer_hoki.html'});",
                "items.push({icon:'👨‍👩‍👦',label:'Customer Hoki',link:'customer_hoki.html'});\n        items.push({icon:'📋',label:'Data Pelamar Kerja',link:'pelamar_kerja.html'});"
            );
        }
    }

    // 5. Mobile "Lainnya" - grup VIP
    if (!content.includes("label:'Cek Lokasi'")) {
        if (content.includes("items.push({icon:'🔑',label:'Login Log',link:'login_history.html'});")) {
            content = content.replace(
                "items.push({icon:'🔑',label:'Login Log',link:'login_history.html'});",
                "items.push({icon:'🔑',label:'Login Log',link:'login_history.html'});\n        items.push({icon:'📍',label:'Cek Lokasi',link:'cek_lokasi.html'});"
            );
        }
    }

    if (content !== original) {
        fs.writeFileSync(file, content, 'utf8');
        console.log('Updated: ' + file);
    }
}

for (const file of files) {
    processFile(file);
}
console.log('Sidebar new-features update complete.');
