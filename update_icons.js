const fs = require('fs');

const files = fs.readdirSync('.').filter(f => f.endsWith('.html'));

const replacements = [
    { label: /Customer Hoki/i, icon: '👨‍👩‍👦' },
    { label: /HPP Produk/i, icon: '🗃️' },
    { label: /Rekapan Penjualan/i, icon: '🗓️' },
    { label: /Laporan Penjualan/i, icon: '🚀' },
    { label: /Belanja/i, icon: '📝' },
    { label: /Omset Cabang/i, icon: '💹' },
    { label: /Manajemen User/i, icon: '⚙️' }
];

function processFile(file) {
    let content = fs.readFileSync(file, 'utf8');
    let original = content;

    for (let r of replacements) {
        // Regex for navItem('ICON', 'LABEL', ...)
        // We need to match navItem( 'ANY_ICON' , 'LABEL' 
        // Example: navItem('📊','Laporan Penjualan','laporan_staff.html')
        let regexNavItem = new RegExp(`(navItem\\s*\\(\\s*['"])[^'"]+(['"]\\s*,\\s*['"])(.*?)(['"])`, 'g');
        content = content.replace(regexNavItem, (match, p1, p2, p3, p4) => {
            if (r.label.test(p3)) {
                return `${p1}${r.icon}${p2}${p3}${p4}`;
            }
            return match;
        });

        // Regex for items.push({icon:'ICON', label:'LABEL'})
        let regexPush = new RegExp(`(icon\\s*:\\s*['"])[^'"]+(['"]\\s*,\\s*label\\s*:\\s*['"])(.*?)(['"])`, 'g');
        content = content.replace(regexPush, (match, p1, p2, p3, p4) => {
            if (r.label.test(p3)) {
                return `${p1}${r.icon}${p2}${p3}${p4}`;
            }
            return match;
        });
    }

    if (content !== original) {
        fs.writeFileSync(file, content, 'utf8');
        console.log('Updated icons in: ' + file);
    }
}

for (const file of files) {
    processFile(file);
}
console.log('Icon update complete.');
