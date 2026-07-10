const fs = require('fs');

const files = fs.readdirSync('.').filter(f => f.endsWith('.html'));

let updatedSidebar = 0;
let updatedMoreMenu = 0;

for (const file of files) {
    if (file === 'customer_hoki.html') continue; // we already wrote it correctly
    
    let content = fs.readFileSync(file, 'utf8');
    let changed = false;

    // 1. Update PAGES_EXEC array
    if (content.includes("'salary_staff.html']") && !content.includes("'customer_hoki.html']")) {
        content = content.replace(
            /const PAGES_EXEC\s*=\s*\[(.*?)'salary_staff\.html'\];/,
            "const PAGES_EXEC      = [$1'salary_staff.html','customer_hoki.html'];"
        );
        changed = true;
    }

    // 2. Update navItem in buildSidebar
    if (content.includes("navItem('💵','Salary Staff','salary_staff.html');") && !content.includes("navItem('👨‍👩‍👦','Customer Hoki','customer_hoki.html');")) {
        content = content.replace(
            /\+\s*navItem\('💵','Salary Staff','salary_staff\.html'\);/,
            "+ navItem('💵','Salary Staff','salary_staff.html')\n            + navItem('👨‍👩‍👦','Customer Hoki','customer_hoki.html');"
        );
        changed = true;
    }

    // 3. Update buildMoreMenu
    if (content.includes("items.push({icon:'📈',label:'Statistik',link:'statistik.html'});") && !content.includes("items.push({icon:'�‍👩‍👦💝',label:'Customer Hoki',link:'customer_hoki.html'});")) {
        content = content.replace(
            /items\.push\(\{icon:'📈',label:'Statistik',link:'statistik\.html'\}\);\s*\}/,
            "items.push({icon:'📈',label:'Statistik',link:'statistik.html'});\n        items.push({icon:'👨‍👩‍👦',label:'Customer Hoki',link:'customer_hoki.html'});\n    }"
        );
        changed = true;
    }

    if (changed) {
        fs.writeFileSync(file, content, 'utf8');
        console.log('Updated ' + file);
        updatedSidebar++;
    }
}

console.log('Done. Files updated: ' + updatedSidebar);
