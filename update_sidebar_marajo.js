const fs = require('fs');

const files = fs.readdirSync('.').filter(f => f.endsWith('.html') && f !== 'index.html');

const MARAJO_BLOCK = `        if (['kurniarp','hanazaf'].includes((currentUser.user || '').toLowerCase())) {
            exec += navItemExternal('🏢','PT Marajo Barokah', \`https://marajo.pos-hokidimsum.com/sso.php?u=\${encodeURIComponent(currentUser.user)}&t=\${encodeURIComponent(localStorage.getItem('sessionToken') || '')}\`);
        }
`;

function processFile(file) {
    let content = fs.readFileSync(file, 'utf8');
    let original = content;

    if (content.includes("h += navGroup('executive', 'Executive', exec, PAGES_EXEC);") && !content.includes('PT Marajo Barokah')) {
        content = content.replace(
            "        h += navGroup('executive', 'Executive', exec, PAGES_EXEC);",
            MARAJO_BLOCK + "        h += navGroup('executive', 'Executive', exec, PAGES_EXEC);"
        );
    }

    if (content !== original) {
        fs.writeFileSync(file, content, 'utf8');
        console.log('Updated sidebar (Marajo) in: ' + file);
    }
}

for (const file of files) {
    processFile(file);
}
console.log('Sidebar Marajo update complete.');
