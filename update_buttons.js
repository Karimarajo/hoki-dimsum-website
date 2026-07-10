const fs = require('fs');

const files = fs.readdirSync('.').filter(f => f.endsWith('.html'));

function processFile(file) {
    let content = fs.readFileSync(file, 'utf8');
    let changed = false;

    // We will parse buttons that have onclick.
    // The regex captures the entire button tag and its inner content.
    const newContent = content.replace(/<button([^>]*)>(.*?)<\/button>/gi, (match, attrs, innerText) => {
        // Only target buttons with an onclick handler
        if (!attrs.includes('onclick=')) return match;
        
        let onclickMatch = attrs.match(/onclick="([^"]+)"/);
        if (!onclickMatch) return match;
        let origOnclick = onclickMatch[1];
        let s_onclick = origOnclick.toLowerCase();
        
        let s_text = innerText.toLowerCase();
        let clsMatch = attrs.match(/class="([^"]+)"/);
        let s_cls = clsMatch ? clsMatch[1].toLowerCase() : '';
        let titleMatch = attrs.match(/title="([^"]+)"/);
        let s_title = titleMatch ? titleMatch[1].toLowerCase() : '';

        // Exclude specific functional buttons or global navigation buttons
        if (
            s_cls.includes('btn-logout') || 
            s_cls.includes('btn-simpan') || 
            s_cls.includes('btn-reset') || 
            s_cls.includes('btn-cetak') || 
            s_cls.includes('btn-bayar') ||
            s_cls.includes('btn-add') ||
            s_cls.includes('btn-plus') ||
            s_cls.includes('btn-cari') ||
            s_cls.includes('btn-kosongkan') ||
            s_cls.includes('btn-hari-ini') ||
            s_cls.includes('btn-login') ||
            s_cls.includes('nav-') || 
            s_cls.includes('sidebar-') || 
            s_cls.includes('bn-') ||
            s_onclick.includes('simpan') ||
            s_onclick.includes('logout') ||
            s_onclick.includes('tambah') ||
            s_onclick.includes('proses') ||
            s_onclick.includes('cetak') ||
            s_onclick.includes('reset') ||
            s_onclick.includes('filter') ||
            s_onclick.includes('showmodal') ||
            s_onclick.includes('kosongkan') ||
            s_onclick.includes('location.href')
        ) {
            return match;
        }

        let type = null;
        
        // Priority 1: onclick and explicit text/emojis
        if (s_onclick.includes('edit') || s_title.includes('edit') || s_text.includes('edit') || s_text.includes('✏️') || s_text.includes('✎')) {
            type = 'edit';
        } else if (s_onclick.includes('hapus') || s_onclick.includes('del') || s_title.includes('hapus') || s_text.includes('hapus') || s_text.includes('🗑️') || s_text.includes('✖') || s_text.includes('×')) {
            type = 'hapus';
        } else if (s_onclick.includes('detail') || s_title.includes('detail') || s_text.includes('detail') || s_text.includes('👁️')) {
            type = 'detail';
        } 
        // Priority 2: Fallback to class name if text is obscure (very unlikely needed, but just in case)
        else if (s_cls.includes('edit')) {
            type = 'edit';
        } else if (s_cls.includes('hapus') || s_cls.includes('del')) {
            type = 'hapus';
        } else if (s_cls.includes('detail')) {
            type = 'detail';
        }

        if (type === 'edit') {
            changed = true;
            return `<button class="btn-edit-icon" onclick="${origOnclick}" title="Edit Data">✏️</button>`;
        } else if (type === 'hapus') {
            changed = true;
            return `<button class="btn-hapus-icon" onclick="${origOnclick}" title="Hapus Data">🗑️</button>`;
        } else if (type === 'detail') {
            changed = true;
            return `<button class="btn-detail-icon" onclick="${origOnclick}" title="Detail Data">👁️</button>`;
        }

        return match;
    });

    if (changed && newContent !== content) {
        fs.writeFileSync(file, newContent, 'utf8');
        console.log('Updated buttons in: ' + file);
    }
}

for (const file of files) {
    processFile(file);
}
console.log('Button standardization complete.');
