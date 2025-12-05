document.addEventListener('DOMContentLoaded', () => {
    
    // ... (Bagian 1: LOGIKA TREE VIEW biarkan tetap sama) ...
    // ... (Copy dari kode sebelumnya untuk bagian Expand/Collapse) ...
    document.addEventListener('click', function(e) {
        const toggleBtn = e.target.closest('.tree-toggle-btn');
        if (toggleBtn) {
            e.preventDefault(); e.stopPropagation();
            const targetId = toggleBtn.dataset.target;
            const currentState = toggleBtn.dataset.state;
            const icon = toggleBtn.querySelector('i');
            if (currentState === 'closed') {
                if(icon) { icon.classList.remove('fa-plus-square'); icon.classList.add('fa-minus-square'); }
                document.querySelectorAll(`.tree-row[data-parent="${targetId}"]`).forEach(row => row.classList.remove('hidden'));
                toggleBtn.dataset.state = 'open';
            } else {
                if(icon) { icon.classList.remove('fa-minus-square'); icon.classList.add('fa-plus-square'); }
                hideChildrenRecursive(targetId);
                toggleBtn.dataset.state = 'closed';
            }
        }
    });
    function hideChildrenRecursive(parentId) {
        const children = document.querySelectorAll(`.tree-row[data-parent="${parentId}"]`);
        children.forEach(row => {
            row.classList.add('hidden');
            const btn = row.querySelector('.tree-toggle-btn');
            if (btn) {
                btn.dataset.state = 'closed';
                const icon = btn.querySelector('i');
                if(icon) { icon.classList.remove('fa-minus-square'); icon.classList.add('fa-plus-square'); }
                hideChildrenRecursive(row.dataset.id);
            }
        });
    }

    // ============================================================
    // 2. LOGIKA FILTER & VALIDASI (REVISI)
    // ============================================================

    function toggleKddkInputState(mode) {
        const typeSelect = document.getElementById(mode + '_unit_type');
        const kddkInput = document.getElementById(mode + '_kddk_code');

        if (!typeSelect || !kddkInput) return;

        const type = typeSelect.value;

        if (type === 'UID') {
            // KASUS 1: UID (Tidak Punya Kode)
            kddkInput.value = '';
            kddkInput.disabled = true;
            kddkInput.required = false; 
            kddkInput.placeholder = '-';
            kddkInput.classList.add('bg-gray-100', 'cursor-not-allowed');
            kddkInput.classList.remove('bg-yellow-50', 'text-yellow-700'); // Hapus style auto
        
        } else if (type === 'SUB_ULP') {
            // KASUS 2: SUB ULP (Auto Increment) - Hanya di Mode Create
            if (mode === 'create') {
                kddkInput.value = ''; // Kosongkan, biarkan backend mengisi
                kddkInput.readOnly = true; // Readonly tapi tetap terkirim (tidak disabled)
                kddkInput.disabled = false; 
                kddkInput.required = false; // Validasi backend yang handle
                kddkInput.placeholder = 'AUTO';
                // Beri visualisasi beda (misal kuning)
                kddkInput.classList.add('bg-yellow-50', 'cursor-not-allowed', 'text-yellow-700', 'font-bold');
                kddkInput.classList.remove('bg-gray-100');
            } else {
                // Mode Edit: Biarkan manual/readonly sesuai kebijakan (disini kita buka manual)
                kddkInput.readOnly = false;
                kddkInput.disabled = false;
                kddkInput.classList.remove('bg-gray-100', 'bg-yellow-50', 'text-yellow-700', 'font-bold');
            }

        } else {
            // KASUS 3: UP3 & ULP (Manual Wajib)
            kddkInput.value = kddkInput.value || ''; // Pertahankan value jika ada
            kddkInput.readOnly = false;
            kddkInput.disabled = false;
            kddkInput.required = true; 
            kddkInput.placeholder = 'A';
            kddkInput.classList.remove('bg-gray-100', 'bg-yellow-50', 'cursor-not-allowed', 'text-yellow-700', 'font-bold');
        }
    }

    window.filterParentOptions = function(mode) {
        const typeSelect = document.getElementById(mode + '_unit_type');
        const parentSelect = document.getElementById(mode + '_parent_code');
        
        if (!typeSelect || !parentSelect) return;

        const selectedType = typeSelect.value; 
        let allowedParentType = '';
        
        // --- LOGIKA FILTER KETAT ---
        switch (selectedType) {
            case 'UID': 
                allowedParentType = 'ROOT'; 
                break;
            case 'UP3': 
                allowedParentType = 'UID'; 
                break;
            case 'ULP': 
                allowedParentType = 'UP3'; 
                break;
            case 'SUB_ULP': 
                allowedParentType = 'ULP'; 
                break;
            default:
                // JIKA TIPE BELUM DIPILIH (Data Kosong/UNK), JANGAN TAMPILKAN SEMUA.
                // Paksa user memilih tipe dulu agar parent yang muncul relevan.
                allowedParentType = 'NONE'; 
        }

        console.log(`Filtering ${mode}: Type=${selectedType}, AllowedParent=${allowedParentType}`);

        let hasSelected = false;
        Array.from(parentSelect.options).forEach(option => {
            const optionType = option.dataset.type; // Ambil data-type dari HTML
            const optionVal = option.value;

            // Logika Tampil:
            // 1. Tipe opsi cocok dengan yang diizinkan.
            // 2. ATAU jika target ROOT, opsi value kosong ("-- Tidak Ada --") yang boleh tampil.
            // 3. ATAU opsi tersebut sedang terpilih (agar data lama tidak hilang visualnya).
            
            let shouldShow = false;

            if (allowedParentType === 'ROOT') {
                if (optionVal === '') shouldShow = true;
            } else if (allowedParentType === 'NONE') {
                shouldShow = false; // Sembunyikan semua jika tipe unit belum jelas
            } else {
                if (optionType === allowedParentType) shouldShow = true;
            }

            // Selalu tampilkan opsi default "Pilih Parent" jika bukan mode ROOT
            if (optionVal === '' && allowedParentType !== 'ROOT') {
                shouldShow = true; 
            }

            // Selalu tampilkan opsi yang sedang terpilih saat ini (agar tidak error visual)
            if (option.selected) shouldShow = true;

            // Eksekusi Tampil/Sembunyi
            if (shouldShow) {
                option.style.display = ''; 
                option.disabled = false;
            } else {
                option.style.display = 'none'; 
                option.disabled = true; 
            }
            
            // Validasi seleksi
            if (option.selected && option.style.display === 'none') {
                option.selected = false;
            }
            if (option.selected) hasSelected = true;
        });

        // Reset jika seleksi tidak valid
        if (!hasSelected) parentSelect.value = "";
    };

    // --- OBSERVER (AUTO DETECT MODAL) ---
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            // Cek jika ada node baru ditambahkan (Modal muncul)
            if (mutation.addedNodes.length) {
                // Cek Create Form
                const createSelect = document.getElementById('create_unit_type');
                if (createSelect) {
                    // Attach listener manual untuk jaga-jaga
                    createSelect.onchange = function() { filterParentOptions('create'); toggleKddkInputState('create'); };
                    // Jalankan awal
                    filterParentOptions('create');
                    toggleKddkInputState('create');
                }

                // Cek Edit Form
                const editSelect = document.getElementById('edit_unit_type');
                if (editSelect) {
                    // Attach listener manual
                    editSelect.onchange = function() { filterParentOptions('edit'); toggleKddkInputState('edit'); };
                    // Jalankan awal
                    filterParentOptions('edit');
                    toggleKddkInputState('edit');
                }
            }
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });

});