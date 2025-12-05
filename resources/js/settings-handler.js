document.addEventListener('DOMContentLoaded', () => {
    
    let globalIndex = new Date().getTime();

    // --- A. EVENT LISTENERS ---
    document.addEventListener('click', function(e) {
        
        // 1. Tab Switcher
        const tabBtn = e.target.closest('.tab-toggle-btn');
        if (tabBtn) {
            e.preventDefault();
            const target = tabBtn.dataset.target;
            document.querySelectorAll('.setting-tab-content').forEach(el => el.classList.add('hidden'));
            document.getElementById('content-tab-' + target).classList.remove('hidden');
            
            // Reset Style
            document.querySelectorAll('.tab-toggle-btn').forEach(b => {
                b.classList.remove('text-indigo-600', 'border-b-2', 'border-indigo-600', 'dark:text-indigo-500', 'dark:border-indigo-500');
                b.classList.add('border-transparent', 'hover:text-gray-600', 'hover:border-gray-300');
            });
            tabBtn.classList.add('text-indigo-600', 'border-b-2', 'border-indigo-600');
            tabBtn.classList.remove('border-transparent', 'hover:text-gray-600', 'hover:border-gray-300');
            return;
        }

        // 2. Tombol "Tambah Kode Area Baru" (di Index)
        if (e.target.closest('#add-area-row-btn')) {
             e.preventDefault();
             window.addAreaRow();
             return;
        }
        
        // 3. Tombol "Tambah Rute Baru" (di Halaman Detail Rute)
        if (e.target.closest('#add-route-manager-btn')) {
             e.preventDefault();
             const areaCode = e.target.closest('#add-route-manager-btn').dataset.areaCode;
             window.addRouteRowManager(areaCode);
             return;
        }
        
        // 4. Tombol Hapus Baris
        if (e.target.closest('.remove-row-btn')) {
            e.preventDefault();
            if(confirm('Hapus baris ini?')) {
                e.target.closest('tr').remove();
            }
            return;
        }

        // 5. LOGIKA PENCARIAN TABEL RUTE (Baru) ---
        const searchInput = document.getElementById('route-search-input');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const term = e.target.value.toLowerCase();
                const rows = document.querySelectorAll('.route-item');
                let visibleCount = 0;

                rows.forEach(row => {
                    const codeInput = row.querySelector('.route-code-input');
                    const labelInput = row.querySelector('.route-label-input');
                    
                    const codeText = codeInput ? codeInput.value.toLowerCase() : '';
                    const labelText = labelInput ? labelInput.value.toLowerCase() : '';

                    if (codeText.includes(term) || labelText.includes(term)) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Tampilkan pesan kosong jika tidak ada hasil
                const noResultDiv = document.getElementById('no-routes-found');
                if (noResultDiv) {
                    noResultDiv.classList.toggle('hidden', visibleCount > 0);
                }
            });
        }
    });

    // --- B. FUNGSI HELPER (GLOBAL) ---

    // --- UPDATE HELPER: HITUNG TOTAL --- 
    window.updateRouteCount = function() {
        const countSpan = document.getElementById('total-routes-count');
        const rows = document.querySelectorAll('#route-rows-container tr');
        if(countSpan) countSpan.textContent = rows.length;
    };

    // Navigasi ke Halaman Detail Rute
    window.manageAreaRoutes = function(areaCode) {
        // Gunakan App.Tabs jika tersedia untuk membuka di tab baru atau load content
        // Asumsi route name: admin.settings.manage_routes
        const url = `/settings/manage-routes/${areaCode}`; 
        // Buka sebagai Tab Baru agar user bisa multitasking
        App.Tabs.createTab(`Rute [${areaCode}]`, url, true, true);
    };

    // Tambah Baris Area (Master)
    window.addAreaRow = function() {
        const container = document.getElementById('area-rows-container');
        globalIndex++;
        const row = `
            <tr>
                <td class="p-2">
                    <input type="text" name="settings[kddk_config_data][areas][${globalIndex}][code]" maxlength="2" placeholder="XX" class="w-full text-center font-bold uppercase rounded border-gray-300 dark:bg-gray-800 dark:text-white" required oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '')">
                </td>
                <td class="p-2">
                    <input type="text" name="settings[kddk_config_data][areas][${globalIndex}][label]" placeholder="Keterangan Area" class="w-full rounded border-gray-300 dark:bg-gray-800 dark:text-white" required>
                </td>
                <td class="p-2 text-center">
                    <span class="text-xs text-gray-400 italic">Simpan dulu</span>
                </td>
                <td class="p-2 text-center">
                    <button type="button" class="text-red-600 hover:text-red-800 remove-row-btn"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;
        container.insertAdjacentHTML('beforeend', row);
    }

    // Tambah Baris Rute (Detail Manager)
    window.addRouteRowManager = function(areaCode) {
        const container = document.getElementById('route-rows-container');
        globalIndex++;
        const row = `
            <tr>
                <td class="p-2 w-32">
                    <input type="text" name="settings[kddk_config_data][routes_manage][${areaCode}][${globalIndex}][code]" maxlength="2" placeholder="XX" class="w-full text-center font-bold uppercase rounded border-gray-300 dark:bg-gray-800 dark:text-white" required oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '')">
                </td>
                <td class="p-2">
                    <input type="text" name="settings[kddk_config_data][routes_manage][${areaCode}][${globalIndex}][label]" placeholder="Keterangan Rute" class="w-full rounded border-gray-300 dark:bg-gray-800 dark:text-white" required>
                </td>
                <td class="p-2 text-center w-10">
                    <button type="button" class="text-red-500 hover:text-red-700 remove-row-btn"><i class="fas fa-times"></i></button>
                </td>
            </tr>
        `;
        container.insertAdjacentHTML('beforeend', row);

        container.insertAdjacentHTML('beforeend', row);
        if (typeof updateRouteCount === 'function') updateRouteCount();
        container.parentElement.scrollTop = container.parentElement.scrollHeight;
    };

    console.log('Settings Handler Loaded (Master-Detail)');
});