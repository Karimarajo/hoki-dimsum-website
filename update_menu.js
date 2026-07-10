const fs = require('fs');

const files = fs.readdirSync('.').filter(f => f.endsWith('.html'));

let updatedCount = 0;
for (const file of files) {
    let content = fs.readFileSync(file, 'utf8');
    if (content.includes("function buildMoreMenu()")) {
        const regex = /items\.push\(\{icon:'📝',label:'Belanja',link:'belanja\.html'\}\);\s*items\.push\(\{icon:'📊',label:'Keuangan',link:'laporan\.html'\}\);\s*items\.push\(\{icon:'📈',label:'Statistik',link:'statistik\.html'\}\);\s*\}\s*if\s*\(\s*role\s*===\s*'VIP'\s*\)\s*\{/s;
        
        const replacement = `items.push({icon:'📝',label:'Belanja',link:'belanja.html'});
        items.push({icon:'💵',label:'Salary Staff',link:'salary_staff.html'});
        items.push({icon:'📊',label:'Keuangan',link:'laporan.html'});
        items.push({icon:'📈',label:'Statistik',link:'statistik.html'});
    }
    if (['Owner','VIP','Investor'].includes(role)) {
        items.push({icon:'📈',label:'Omset Cabang',link:'investor_omset.html'});
        items.push({icon:'💰',label:'Profit Sharing',link:'investor_profit.html'});
    }
    if (role === 'VIP') {`;
        
        if (regex.test(content)) {
            content = content.replace(regex, replacement);
            fs.writeFileSync(file, content, 'utf8');
            updatedCount++;
        } else {
            console.log('Regex did not match in ' + file);
        }
    }
}
console.log('Updated ' + updatedCount + ' files.');
