document.addEventListener('DOMContentLoaded', () => {

    // --- LOGIKA TAB SWITCHER (Yang sudah ada) ---
    document.addEventListener('click', function(e) {
        // ... (kode tab switcher biarkan tetap ada) ...
        const tabBtn = e.target.closest('.tab-toggle-btn');
        if (tabBtn) {
            e.preventDefault();
            const targetName = tabBtn.dataset.target;
            document.querySelectorAll('.local-tab-content').forEach(el => el.classList.add('hidden'));
            const targetContent = document.getElementById('content-tab-' + targetName);
            if (targetContent) targetContent.classList.remove('hidden');
            
            // Reset & Set Active State
            document.querySelectorAll('.tab-toggle-btn').forEach(btn => {
                btn.classList.remove('text-indigo-600', 'border-indigo-600', 'dark:text-indigo-500', 'dark:border-indigo-500');
                btn.classList.add('border-transparent', 'hover:text-gray-600', 'hover:border-gray-300', 'dark:hover:text-gray-300');
            });
            tabBtn.classList.add('text-indigo-600', 'border-indigo-600', 'dark:text-indigo-500', 'dark:border-indigo-500');
            tabBtn.classList.remove('border-transparent', 'hover:text-gray-600', 'hover:border-gray-300', 'dark:hover:text-gray-300');
        }
    });

    // --- LOGIKA CHECKBOX ---
    document.addEventListener('change', function(e) {
        
        // A. LOGIKA UMUM: Group Select All (Untuk Permission & Menu)
        if (e.target.classList.contains('group-select-all')) {
            const groupSlug = e.target.dataset.group;
            const isChecked = e.target.checked;
            const form = e.target.closest('form'); 
            if (form) {
                // Cari item dengan grup yang sama
                const items = form.querySelectorAll(`.permission-item.group-${groupSlug}`);
                items.forEach(cb => {
                    if (!cb.disabled) cb.checked = isChecked;
                });
            }
        }

        // B. LOGIKA KHUSUS MENU: Child Checkbox
        // Jika Sub-Menu (Anak) dicentang -> Menu Induk (Bapak) WAJIB dicentang otomatis
        if (e.target.classList.contains('child-menu-cb')) {
            if (e.target.checked) {
                // Cari class yang formatnya child-of-{id}
                const classes = Array.from(e.target.classList);
                const parentClass = classes.find(c => c.startsWith('child-of-'));
                
                if (parentClass) {
                    const parentId = parentClass.replace('child-of-', '');
                    // Cari checkbox bapaknya
                    const parentCb = document.querySelector(`.parent-menu-cb[data-parent-id="${parentId}"]`);
                    // Centang bapaknya
                    if (parentCb) parentCb.checked = true;
                }
            }
        }

        // C. LOGIKA KHUSUS MENU: Parent Checkbox
        // (Opsional, karena sudah tercover oleh Logic A 'group-select-all', 
        // tapi kita tambahkan spesifik untuk keamanan jika class group berbeda)
        if (e.target.classList.contains('parent-menu-cb')) {
            const parentId = e.target.dataset.parentId;
            const children = document.querySelectorAll(`.child-of-${parentId}`);
            children.forEach(child => {
                if (!child.disabled) child.checked = e.target.checked;
            });
        }
    });
    
});