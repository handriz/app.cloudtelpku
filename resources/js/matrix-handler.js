// resources/js/matrix-handler.js

document.addEventListener('DOMContentLoaded', () => {
    
    console.log('Matrix KDDK Handler Loaded (Integrated: Map + Confirmation + Generator)');

    // ============================================================
    // 1. GLOBAL STATE (CHECKBOX & METADATA PRESERVATION)
    // ============================================================
    const selectionState = {
        unit: null,     
        items: new Map() // MENGGUNAKAN MAP (Key: IDPEL, Value: {jenis: ...})
    };

    function syncSelectionUI() {
        const contextInput = document.getElementById('page-context-unit');
        if (!contextInput) return;

        const currentUnit = contextInput.value;

        if (selectionState.unit !== currentUnit) {
            selectionState.unit = currentUnit;
            selectionState.items.clear(); // Reset Map
        }

        const checkboxes = document.querySelectorAll('.row-checkbox');
        let allChecked = true;
        let hasCheckboxes = checkboxes.length > 0;

        checkboxes.forEach(cb => {
            // Cek keberadaan key di Map
            if (selectionState.items.has(cb.value)) {
                cb.checked = true;
            } else {
                cb.checked = false;
                allChecked = false;
            }
        });

        const checkAll = document.getElementById('check-all-rows');
        if (checkAll && hasCheckboxes) {
            checkAll.checked = allChecked;
        }
        toggleGroupButton();
    }

    const observer = new MutationObserver((mutations) => {
        let shouldSync = false;
        mutations.forEach(m => {
            if (m.target.id === 'tabs-content' || m.target.closest('#tabs-content')) {
                shouldSync = true;
            }
        });
        if (shouldSync) {
            setTimeout(syncSelectionUI, 50);
        }
    });
    
    observer.observe(document.body, { childList: true, subtree: true });

    function updateBreadcrumb(displayCode) {
        const displayEl = document.getElementById('live-kddk-display');
        if (displayEl && displayCode) {
            // Animasi kecil agar user sadar berubah
            displayEl.style.opacity = 0;
            setTimeout(() => {
                displayEl.textContent = displayCode;
                displayEl.style.opacity = 1;
            }, 100);
        }
    }

    // ============================================================
    // 2. EVENT LISTENERS (DELEGATION UTAMA)
    // ============================================================

    document.addEventListener('click', function(e) {
        // A. LOGIKA TOMBOL KELOLA RBM
        const rbmBtn = e.target.closest('[data-action="manage-rbm"]');
        if (rbmBtn) {
            e.preventDefault(); e.stopPropagation(); 
            const url = rbmBtn.dataset.url;
            const tabName = App.Utils.getActiveTabName();
            if (url && tabName) App.Tabs.loadTabContent(tabName, url);
            return;
        }

        // B. Toggle Tree View
        const toggleRow = e.target.closest('[data-action="toggle-tree"]');
        if (toggleRow) {
            e.preventDefault(); e.stopPropagation(); 
            const targetId = toggleRow.dataset.target;
            const icon = document.getElementById('icon-' + targetId);
            
            const singleRow = document.getElementById('row-' + targetId);
            if (singleRow) singleRow.classList.toggle('hidden');

            const multiRows = document.querySelectorAll(`.tree-child-${targetId}`);
            multiRows.forEach(row => row.classList.toggle('hidden'));

            if (icon) icon.classList.toggle('rotate-90');
            return;
        }

        // C. TOGGLE AREA MAP
        const areaHeader = e.target.closest('[data-action="toggle-area-map"]');
        if (areaHeader) {
            e.preventDefault();
            const targetId = areaHeader.dataset.target;
            const areaCode = areaHeader.dataset.areaCode;
            const icon = areaHeader.querySelector('.icon-chevron');
            
            const content = document.getElementById(targetId);
            if(content) content.classList.toggle('hidden');
            if(icon) icon.classList.toggle('rotate-180');

            if (content && !content.classList.contains('hidden')) {
                loadMapContext(areaCode, null);
                updateMapTitle(`Peta Area Baca ${areaCode}`);
            }
            return;
            
            // function updateMapTitle(title) {
            //     const titleEl = document.getElementById('map-context-title');
            //     if (titleEl) {
            //         titleEl.textContent = title;
            //     }
            // }
        }

        // D. TOGGLE ROUTE MAP
        const routeHeader = e.target.closest('[data-action="toggle-route-map"]');
        if (routeHeader) {
            e.preventDefault(); e.stopPropagation(); 
            const targetId = routeHeader.dataset.target;
            const areaCode = routeHeader.dataset.areaCode;
            const routeCode = routeHeader.dataset.routeCode;
            const icon = routeHeader.querySelector('.icon-chevron-sub');

            // 1. Toggle Accordion UI
            const content = document.getElementById(targetId);
            if(content) content.classList.toggle('hidden');
            if(icon) icon.classList.toggle('rotate-180');

            // 2. Load Map & Update Judul
            if (content && !content.classList.contains('hidden')) {
                loadMapContext(areaCode, routeCode);
                updateMapTitle(`Peta Area Baca ${areaCode} Rute ${routeCode}`); 
            }
            return;
        }

        // E. Drill Down Detail
        const row = e.target.closest('[data-action="drill-down"]');
        if (row) {
            e.preventDefault();
            const url = row.dataset.url;
            const tabName = App.Utils.getActiveTabName();
            if (url && tabName) App.Tabs.loadTabContent(tabName, url);
            return;
        }

        // F. FULL SCREEN
        const fsBtn = e.target.closest('[data-action="toggle-fullscreen"]');
        if (fsBtn) {
            e.preventDefault();
            const workspace = document.getElementById('rbm-workspace');
            const iconExpand = fsBtn.querySelector('.icon-expand');
            const iconCompress = fsBtn.querySelector('.icon-compress');
            const textSpan = fsBtn.querySelector('.text-btn');

            if (!document.fullscreenElement) {
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen().catch(e => console.log(e));
                }
                workspace.classList.add('fullscreen-mode');
                document.body.classList.add('rbm-fullscreen');
                iconExpand.classList.add('hidden');
                iconCompress.classList.remove('hidden');
                textSpan.textContent = "Exit Full";
            } else {
                if (document.exitFullscreen) { document.exitFullscreen(); }
                workspace.classList.remove('fullscreen-mode');
                document.body.classList.remove('rbm-fullscreen');
                iconExpand.classList.remove('hidden');
                iconCompress.classList.add('hidden');
                textSpan.textContent = "Full Screen";
            }
            return;
        }
    });

    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('kddk-part')) {
            updateSequenceAndGenerate();
        }
        if (e.target.id === 'part_sisip') {
             e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 2);
        }
        if (e.target.id === 'kddk-search-input') {
            handleKddkSearch(e.target.value);
        }
    });

    document.addEventListener('change', function(e) {
        // A. Checkbox Baris (Simpan ID + Jenis)
        if (e.target.classList.contains('row-checkbox')) {
            const idpel = e.target.value;
            const jenis = e.target.dataset.jenis || 'LAINNYA';

            if (e.target.checked) {
                selectionState.items.set(idpel, { jenis: jenis });
            } else {
                selectionState.items.delete(idpel);
            }
            
            const checkAll = document.getElementById('check-all-rows');
            if (checkAll && !e.target.checked) checkAll.checked = false;
            
            toggleGroupButton();
        }

        // B. Checkbox Pilih Semua
        if (e.target.id === 'check-all-rows') {
            const isChecked = e.target.checked;
            document.querySelectorAll('.row-checkbox').forEach(cb => {
                cb.checked = isChecked;
                const idpel = cb.value;
                const jenis = cb.dataset.jenis || 'LAINNYA';

                if (isChecked) {
                    selectionState.items.set(idpel, { jenis: jenis });
                } else {
                    selectionState.items.delete(idpel);
                }
            });
            toggleGroupButton();
        }

        // C. Generator Dropdown
        if (e.target.classList.contains('kddk-part')) {
            if (e.target.id === 'part_area') updateRouteOptions();
            updateSequenceAndGenerate();
        }
    });

    // ============================================================
    // 3. LOGIKA DRAG & DROP (MOVE, REMOVE, REORDER)
    // ============================================================
    let draggedIdpel = null;
    let originKddk = null;
    const removeZone = document.getElementById('remove-drop-zone');

    // DRAG START
    document.addEventListener('dragstart', function(e) {
        const row = e.target.closest('.draggable-idpel');
        if (row) {
            draggedIdpel = row.dataset.idpel;
            originKddk = row.dataset.originKddk;
            
            row.classList.add('opacity-50', 'bg-yellow-100'); 
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', draggedIdpel);

            // Tampilkan Zona Sampah
            if(removeZone) {
                removeZone.classList.remove('hidden');
                // Animasi masuk
                setTimeout(() => {
                    removeZone.classList.remove('opacity-0', 'translate-y-10');
                    removeZone.classList.add('opacity-100', 'translate-y-0');
                }, 10);
            }
        }
    });

    // DRAG END
    document.addEventListener('dragend', function(e) {
        const row = e.target.closest('.draggable-idpel');
        if (row) row.classList.remove('opacity-50', 'bg-yellow-100');
        
        // Reset Style Drop Zone Normal
        document.querySelectorAll('.kddk-drop-zone').forEach(zone => {
            zone.classList.remove('bg-green-50', 'border-green-500', 'border-2');
            const indicator = zone.querySelector('.drop-indicator');
            if(indicator) indicator.classList.add('hidden');
        });

        // Sembunyikan Zona Sampah
        if(removeZone) {
            removeZone.classList.remove('bg-red-200', 'scale-105'); // Reset hover effect
            removeZone.classList.add('opacity-0', 'translate-y-10'); // Animasi keluar
            setTimeout(() => {
                removeZone.classList.add('hidden');
            }, 300);
        }
    });

    // DRAG OVER (Update: Izinkan drop di tempat yang sama untuk Reorder)
    document.addEventListener('dragover', function(e) {
        e.preventDefault(); 
        const trashTarget = e.target.closest('.kddk-remove-zone');
        if (trashTarget) {
            e.dataTransfer.dropEffect = 'move';
            trashTarget.classList.add('scale-105', 'bg-red-200');
            return;
        }
        
        // 1. Cek Drop Zone (Container Rute)
        const dropZone = e.target.closest('.kddk-drop-zone');
        
        // 2. Cek Target Baris (Untuk Reorder)
        const targetRow = e.target.closest('.draggable-idpel');

        if (dropZone && draggedIdpel) {
            e.preventDefault(); // Wajib agar bisa drop
            
            const targetPrefix = dropZone.dataset.routePrefix;
            const originPrefix = document.querySelector(`.draggable-idpel[data-idpel="${draggedIdpel}"]`)?.dataset.originPrefix;

            // KASUS A: Pindah ke Rute Lain (MOVE)
            if (targetPrefix !== originPrefix) {
                e.dataTransfer.dropEffect = 'move';
                dropZone.classList.add('bg-green-50', 'border-green-500');
                const indicator = dropZone.querySelector('.drop-indicator');
                if(indicator) indicator.classList.remove('hidden');
            }
            // KASUS B: Pindah di Rute Sama (REORDER)
            else if (targetRow && targetRow.dataset.idpel !== draggedIdpel) {
                e.dataTransfer.dropEffect = 'move';
                // Visual feedback: Garis di atas baris target
                targetRow.style.borderTop = "2px solid #4f46e5"; // Indigo
            }
        }
    });

    // DRAG LEAVE (Update: Bersihkan style borderTop)
    document.addEventListener('dragleave', function(e) {
        const targetRow = e.target.closest('.draggable-idpel');
        if (targetRow) {
            targetRow.style.borderTop = ""; // Hapus garis
        }
        // ... (kode lama untuk dropZone leave) ...
        const dropZone = e.target.closest('.kddk-drop-zone');
        if (dropZone && !dropZone.contains(e.relatedTarget)) {
            dropZone.classList.remove('bg-green-50', 'border-green-500');
            const indicator = dropZone.querySelector('.drop-indicator');
            if(indicator) indicator.classList.add('hidden');
        }
    });

    // DROP (Update: Deteksi Reorder vs Move)
    document.addEventListener('drop', function(e) {
        e.preventDefault();

        // Bersihkan visual
        document.querySelectorAll('.draggable-idpel').forEach(r => r.style.borderTop = "");

        // Cek Trash
        const trashTarget = e.target.closest('.kddk-remove-zone');
        if (trashTarget && draggedIdpel) {
             if(confirm(`Keluarkan pelanggan ${draggedIdpel}?`)) performRemoveIdpel(draggedIdpel);
             return;
        }

        const dropZone = e.target.closest('.kddk-drop-zone');
        const targetRow = e.target.closest('.draggable-idpel');

        if (dropZone && draggedIdpel) {
            const targetPrefix = dropZone.dataset.routePrefix;
            // Ambil prefix asal dari elemen yang sedang didrag (atau variabel global jika masih valid)
            // Kita gunakan selector DOM untuk aman
            const draggedEl = document.querySelector(`.draggable-idpel[data-idpel="${draggedIdpel}"]`);
            const originPrefix = draggedEl ? draggedEl.dataset.originPrefix : null;

            // KASUS 1: PINDAH GRUP
            if (targetPrefix !== originPrefix) {
                performMoveIdpel(draggedIdpel, targetPrefix); // Pindah ke akhir grup baru
            } 
            // KASUS 2: REORDER (Grup Sama)
            else if (targetRow && targetRow.dataset.idpel !== draggedIdpel) {
                // Pindah posisi di grup yang sama
                const targetIdpel = targetRow.dataset.idpel;
                performReorderIdpel(draggedIdpel, targetIdpel, targetPrefix);
            }
        }
    });

    // --- FUNGSI BARU: REORDER AJAX ---
    function performReorderIdpel(idpel, targetIdpel, prefix) {
        const urlInput = document.getElementById('reorder-route');
        if (!urlInput) return;

        const url = urlInput.value;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        document.body.style.cursor = 'wait';

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ 
                idpel: idpel, 
                target_idpel: targetIdpel,
                route_prefix: prefix
            })
        })
        .then(response => response.json())
        .then(data => {
            document.body.style.cursor = 'default';
            if (data.success) {
                if (typeof App !== 'undefined' && App.Utils) App.Utils.displayNotification('success', data.message);
                
                // Refresh Tab
                const activeTab = App.Utils.getActiveTabName();
                const unitInput = document.querySelector('form.ajax-form input[name="unitup"]');
                if (unitInput) {
                    const refreshUrl = `/team/matrix-kddk/manage-rbm/${encodeURIComponent(unitInput.value)}`;
                    let bustUrl = new URL(refreshUrl, window.location.origin);
                    bustUrl.searchParams.set('_cb', new Date().getTime());
                    App.Tabs.loadTabContent(activeTab, bustUrl.toString());
                }
            } else {
                alert('Gagal Reorder: ' + data.message);
            }
        })
        .catch(error => {
            document.body.style.cursor = 'default';
            console.error('Error:', error);
            alert('Terjadi kesalahan server.');
        });
    }

    // AJAX: PINDAH GRUP
    function performMoveIdpel(idpel, targetKddk) {
        const urlInput = document.getElementById('move-route');
        if (!urlInput) return;
        
        executeDragDropAjax(urlInput.value, { idpel: idpel, target_kddk: targetKddk });
    }

    // AJAX: HAPUS DARI GRUP (BARU)
    function performRemoveIdpel(idpel) {
        const urlInput = document.getElementById('remove-route');
        if (!urlInput) return;

        executeDragDropAjax(urlInput.value, { idpel: idpel });
    }

    // Helper AJAX Generik
    function executeDragDropAjax(url, bodyData) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        document.body.style.cursor = 'wait';

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(bodyData)
        })
        .then(response => response.json())
        .then(data => {
            document.body.style.cursor = 'default';
            if (data.success) {
                if (typeof App !== 'undefined' && App.Utils) App.Utils.displayNotification('success', data.message);
                
                // Refresh Halaman
                const activeTab = App.Utils.getActiveTabName();
                const unitInput = document.querySelector('form.ajax-form input[name="unitup"]');
                if (unitInput) {
                    const refreshUrl = `/team/matrix-kddk/manage-rbm/${encodeURIComponent(unitInput.value)}`;
                    let bustUrl = new URL(refreshUrl, window.location.origin);
                    bustUrl.searchParams.set('_cb', new Date().getTime());
                    App.Tabs.loadTabContent(activeTab, bustUrl.toString());
                }
            } else {
                alert('Gagal: ' + data.message);
            }
        })
        .catch(error => {
            document.body.style.cursor = 'default';
            console.error('Error:', error);
            alert('Terjadi kesalahan server.');
        });
    }


    // ============================================================
    // 4. LOGIKA GENERATOR KDDK (MODAL)
    // ============================================================

    let sequenceController = null; 

    // --- FUNGSI GLOBAL (Window Scope) ---

    window.toggleGroupButton = function() {
        const count = selectionState.items.size; // PERBAIKAN: Gunakan .items.size
        const btn = document.getElementById('btn-group-kddk');
        const countSpan = document.getElementById('count-selected');
        
        if(btn) {
            if (count > 0) {
                btn.classList.remove('hidden');
                btn.innerHTML = `<i class="fas fa-layer-group mr-2"></i> Bentuk Group (${count})`;
            } else {
                btn.classList.add('hidden');
            }
        }
        // Update counter di modal jika sedang terbuka
        if (countSpan) countSpan.textContent = count;
    }

    window.confirmGrouping = function() {
        const total = selectionState.items.size;
        if (total === 0) {
            alert("Pilih minimal satu pelanggan.");
            return;
        }

        // Hitung Rekap Jenis Layanan dari Map
        const rekap = {};
        selectionState.items.forEach((val, key) => {
            const jenis = val.jenis || 'LAINNYA';
            if (!rekap[jenis]) rekap[jenis] = 0;
            rekap[jenis]++;
        });

        // Update UI Modal Konfirmasi
        document.getElementById('confirm-total-count').textContent = total;
        const listEl = document.getElementById('confirm-detail-list');
        listEl.innerHTML = '';

        for (const [jenis, count] of Object.entries(rekap)) {
            const li = document.createElement('li');
            li.className = "flex justify-between items-center border-b border-gray-200 dark:border-gray-600 pb-1 last:border-0";
            li.innerHTML = `
                <span class="font-medium text-gray-700 dark:text-gray-300">${jenis}</span>
                <span class="bg-indigo-100 text-indigo-800 px-2 py-0.5 rounded text-xs font-bold">${count} Plg</span>
            `;
            listEl.appendChild(li);
        }

        // Tampilkan Modal Konfirmasi
        const modal = document.getElementById('modal-confirm-selection');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    window.proceedToGenerator = function() {
        // Tutup Modal Konfirmasi
        document.getElementById('modal-confirm-selection').classList.add('hidden');
        document.getElementById('modal-confirm-selection').classList.remove('flex');
        
        // Buka Modal Generator (Fungsi lama)
        window.openKddkModal();
    }

    window.openKddkModal = function() {
        const selectedIds = Array.from(selectionState.items.keys());
        if (selectedIds.length === 0) {
            alert("Pilih minimal satu pelanggan.");
            return;
        }
        const container = document.getElementById('hidden-inputs-container');
        const counter = document.getElementById('count-selected');
        
        if(container) {
            container.innerHTML = '';
            selectedIds.forEach(idpel => {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = 'selected_idpels[]'; input.value = idpel;
                container.appendChild(input);
            });
        }
        if(counter) counter.textContent = selectedIds.length;

        const modal = document.getElementById('modal-create-kddk');
        if(modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            if (typeof updateSequenceAndGenerate === 'function') updateSequenceAndGenerate();
        }
    }

    window.closeKddkModal = function() {
        const modal = document.getElementById('modal-create-kddk');
        if(modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    // --- LOGIKA INTERNAL GENERATOR ---

    function updateRouteOptions() {
        const areaSelect = document.getElementById('part_area');
        const routeSelect = document.getElementById('part_rute');
        if (!areaSelect || !routeSelect) return;

        routeSelect.innerHTML = '<option value="">--</option>';
        
        const selectedOption = areaSelect.options[areaSelect.selectedIndex];
        if (selectedOption && selectedOption.dataset.routes) {
            try {
                const routes = JSON.parse(selectedOption.dataset.routes);
                routes.forEach(r => {
                    const opt = document.createElement('option');
                    opt.value = r.code;
                    opt.textContent = `${r.code} (${r.label})`;
                    routeSelect.appendChild(opt);
                });
            } catch(e) { console.error("JSON Route Error", e); }
        }
    }

    function getPrefix7() {
        const ids = ['part_up3', 'part_ulp', 'part_sub', 'part_area', 'part_rute'];
        const parts = ids.map(id => {
            const el = document.getElementById(id);
            if(!el) return '';
            let val = el.value.toUpperCase();
            if(id === 'part_area' && val.length !== 2) return '';
            if(id === 'part_rute' && val.length !== 2) return '';
            if(id === 'part_sub' && val.length !== 1) return '';
            return val;
        }).join('');
        return parts.length === 7 ? parts : null;
    }

    // PERBAIKAN UTAMA: Tambahkan Pengecekan Elemen (Safe Check)
    function updateSequenceAndGenerate() {
        generateFinalCode(); 

        // PERBAIKAN: Gunakan .items.size
        const count = selectionState.items.size; 
        const countDisplay = document.getElementById('count-display');
        if (countDisplay) countDisplay.textContent = count;

        const prefix7 = getPrefix7();
        const urutInput = document.getElementById('part_urut');
        const form = document.getElementById('kddk-generator-form');

        if (prefix7 && urutInput && form) {
            if (sequenceController) sequenceController.abort();
            sequenceController = new AbortController();

            urutInput.value = '...';
            const baseUrl = form.dataset.sequenceUrl; 
            const url = `${baseUrl}/${prefix7}`;

            fetch(url, { signal: sequenceController.signal })
                .then(res => res.json())
                .then(data => {
                    if(data.sequence) {
                        urutInput.value = data.sequence;
                        
                        // Hitung range sequence
                        const startSeq = parseInt(data.sequence);
                        const endSeq = startSeq + count - 1;
                        
                        const previewStart = document.getElementById('preview-start');
                        const previewEnd = document.getElementById('preview-end');
                        const sisipInput = document.getElementById('part_sisip');
                        const sisip = sisipInput ? sisipInput.value.padStart(2,'0') : '00';
                        
                        if(previewStart) previewStart.textContent = `${prefix7}${data.sequence}${sisip}`;
                        if(previewEnd) previewEnd.textContent = `${prefix7}${endSeq.toString().padStart(3,'0')}${sisip}`;
                        
                        generateFinalCode();
                    }
                })
                .catch(e => { 
                    if(e.name !== 'AbortError') {
                        urutInput.value = '001'; 
                        generateFinalCode();
                    }
                });
        }
    }

    function generateFinalCode() {
        const elUp3 = document.getElementById('part_up3');
        const elUlp = document.getElementById('part_ulp');
        const elSub = document.getElementById('part_sub');
        const elArea = document.getElementById('part_area');
        const elRute = document.getElementById('part_rute');
        const elUrut = document.getElementById('part_urut');
        const elSisip = document.getElementById('part_sisip');
        const preview = document.getElementById('final_kddk_preview');
        const btn = document.getElementById('btn-save-kddk');
        const err = document.getElementById('kddk_error_msg');

        // Input Hidden (Target Data)
        const hiddenPrefix = document.getElementById('hidden_prefix_code');
        const hiddenSisip = document.getElementById('hidden_sisipan');

        if(!preview || !btn) return;

        const up3 = elUp3 ? (elUp3.value || '_') : '_';
        const ulp = elUlp ? (elUlp.value || '_') : '_';
        const sub = elSub ? (elSub.value || '_') : '_';
        const area = elArea ? (elArea.value || '__') : '__';
        const rute = elRute ? (elRute.value || '__') : '__';
        const urut = elUrut ? (elUrut.value || '___') : '___';
        
        // Format Sisipan (Pastikan 2 digit)
        const rawSisip = elSisip ? elSisip.value : '00';
        const sisip = (rawSisip || '00').padStart(2,'0');

        // Gabungkan Prefix 7 Digit
        const prefix7 = `${up3}${ulp}${sub}${area}${rute}`;
        
        // Gabungkan Full Code untuk Preview
        const fullCode = `${prefix7}${urut}${sisip}`;
        preview.value = fullCode;
        
        // --- UPDATE HIDDEN INPUTS (PENTING UNTUK SUBMIT) ---
        if (hiddenPrefix) hiddenPrefix.value = prefix7;
        if (hiddenSisip) hiddenSisip.value = sisip;
        // ----------------------------------------------------

        // Validasi Akhir
        if (!fullCode.includes('_') && fullCode.length === 12 && !fullCode.includes('...')) {
            preview.classList.replace('border-indigo-100', 'border-green-500');
            preview.classList.replace('text-indigo-600', 'text-green-600');
            if(err) {
                err.textContent = "Format Valid ✅";
                err.className = "text-xs text-center text-green-600 mt-1 h-4";
            }
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            preview.classList.replace('border-green-500', 'border-indigo-100');
            preview.classList.replace('text-green-600', 'text-indigo-600');
            if(err) {
                err.textContent = "Lengkapi semua data...";
                err.className = "text-xs text-center text-red-500 mt-1 h-4";
            }
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }

    // ============================================================
    // 5. LOGIKA PENCARIAN MANAGE RBM (LEVEL PELANGGAN)
    // ============================================================
    function handleKddkSearch(value) {
        const term = value.toLowerCase().trim();
        
        // Selektor
        const allRows = document.querySelectorAll('.draggable-idpel');
        const allDigit6Bodies = document.querySelectorAll('div[id^="d6-"]'); // Baru
        const allRouteBodies = document.querySelectorAll('div[id^="route-"]');
        const allAreaBodies = document.querySelectorAll('div[id^="area-"]');

        // 1. RESET
        if (term.length === 0) {
            // Tampilkan semua
            allRows.forEach(el => el.classList.remove('hidden'));
            document.querySelectorAll('.route-container, .digit6-container, .area-container').forEach(el => el.classList.remove('hidden'));
            // Tutup accordion
            allRouteBodies.forEach(el => el.classList.add('hidden'));
            allDigit6Bodies.forEach(el => el.classList.add('hidden')); // Baru
            allAreaBodies.forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.icon-chevron, .icon-chevron-d6, .icon-chevron-sub').forEach(i => i.classList.remove('rotate-180'));
            return;
        }

        // 2. FILTER
        // Sembunyikan container induk dulu
        document.querySelectorAll('.route-container').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.digit6-container').forEach(el => el.classList.add('hidden')); // Baru
        document.querySelectorAll('.area-container').forEach(el => el.classList.add('hidden'));

        allRows.forEach(row => {
            const rowText = row.innerText.toLowerCase();
            const isMatch = rowText.includes(term);

            if (isMatch) {
                row.classList.remove('hidden');

                // Buka Rute (Level 3)
                const routeBody = row.closest('div[id^="route-"]');
                if (routeBody) {
                    const routeContainer = routeBody.closest('.route-container');
                    if (routeContainer) {
                        routeContainer.classList.remove('hidden');
                        routeBody.classList.remove('hidden');
                        routeContainer.querySelector('.icon-chevron-sub')?.classList.add('rotate-180');
                    }

                    // Buka Digit 6 (Level 2)
                    const digit6Body = routeBody.closest('div[id^="d6-"]');
                    if (digit6Body) {
                        const digit6Container = digit6Body.closest('.digit6-container');
                        if (digit6Container) {
                            digit6Container.classList.remove('hidden');
                            digit6Body.classList.remove('hidden');
                            digit6Container.querySelector('.icon-chevron-d6')?.classList.add('rotate-180');
                        }

                        // Buka Area (Level 1)
                        const areaBody = digit6Body.closest('div[id^="area-"]');
                        if (areaBody) {
                            const areaContainer = areaBody.closest('.area-container');
                            if (areaContainer) {
                                areaContainer.classList.remove('hidden');
                                areaBody.classList.remove('hidden');
                                areaContainer.querySelector('.icon-chevron')?.classList.add('rotate-180');
                            }
                        }
                    }
                }
            } else {
                row.classList.add('hidden');
            }
        });
    }

    // ============================================================
    // 6. LOGIKA CONTEXT MENU (KLIK KANAN) - REVISI AJAX SUPPORT
    // ============================================================

    // A. Mencegat Klik Kanan (Context Menu)
    document.addEventListener('contextmenu', function(e) {
        const row = e.target.closest('.draggable-idpel');
        
        if (row) {
            e.preventDefault(); // Matikan menu klik kanan bawaan browser
            
            // Cari elemen menu SECARA DINAMIS (karena baru muncul setelah AJAX load)
            const contextMenu = document.getElementById('custom-context-menu');
            
            if (contextMenu) {
                const idpel = row.dataset.idpel;
                const idDisplay = document.getElementById('ctx-header');
                const hiddenId = document.getElementById('ctx-selected-idpel');

                // Isi data ke menu
                if(idDisplay) idDisplay.textContent = `IDPEL: ${idpel}`;
                if(hiddenId) hiddenId.value = idpel;

                // Tampilkan Menu
                contextMenu.classList.remove('hidden');
                
                // Atur Posisi (Cegah keluar layar)
                let x = e.clientX;
                let y = e.clientY;
                const menuWidth = 224; // sesuaikan dengan w-56
                const menuHeight = 120; // estimasi tinggi menu
                
                if (x + menuWidth > window.innerWidth) x -= menuWidth;
                if (y + menuHeight > window.innerHeight) y -= menuHeight;

                contextMenu.style.left = `${x}px`;
                contextMenu.style.top = `${y}px`;
            }
        } else {
            // Jika klik kanan di luar baris, sembunyikan menu custom
            const contextMenu = document.getElementById('custom-context-menu');
            if (contextMenu && !contextMenu.classList.contains('hidden')) {
                contextMenu.classList.add('hidden');
            }
        }
    });

    // ============================================================
    // 6. LOGIKA PETA KONTEKSTUAL (Baru)
    // ============================================================
    let rbmMap = null;
    let markersLayer = null;

    function updateMapTitle(title) {
        const titleEl = document.getElementById('map-context-title');
        if (titleEl) titleEl.textContent = title;
    }

    function loadMapContext(area, route) {
        const mapContainer = document.getElementById('rbm-map');
        const urlInput = document.getElementById('map-data-url');
        const countSpan = document.getElementById('map-count');
        
        if (!mapContainer || !urlInput) return;
        const urlBase = urlInput.value;

        // Tampilkan Loading
        if(countSpan) countSpan.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        // Construct URL
        let fetchUrl = `${urlBase}?area=${area}`;
        if (route) fetchUrl += `&route=${route}`;

        fetch(fetchUrl)
            .then(res => res.json())
            .then(points => {
                if(countSpan) countSpan.textContent = points.length + ' Titik';
                renderMap(points);
            })
            .catch(err => {
                console.error("Map Error:", err);
                if(countSpan) countSpan.textContent = 'Error';
            });
    }

    function renderMap(points) {
        const mapContainer = document.getElementById('rbm-map');
        
        // Init Map jika belum ada
        if (!rbmMap) {
            mapContainer.innerHTML = ''; // Bersihkan placeholder
            rbmMap = L.map('rbm-map').setView([0.5071, 101.4478], 13);
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles © Esri'
            }).addTo(rbmMap);
            markersLayer = L.featureGroup().addTo(rbmMap);
        } else {
            markersLayer.clearLayers(); // Bersihkan marker lama
        }

        if (points.length === 0) {
             // Bisa tambahkan notifikasi "Tidak ada koordinat"
             return;
        }

        points.forEach(pt => {
            const iconHtml = `<div class="flex items-center justify-center w-5 h-5 bg-white border border-green-600 rounded-full text-[9px] font-bold text-green-800 shadow-sm" style="opacity: 0.9;">${pt.seq}</div>`;
            
            const icon = L.divIcon({
                className: 'custom-map-marker',
                html: iconHtml,
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });

            const marker = L.marker([pt.lat, pt.lng], { icon: icon });
            marker.bindPopup(pt.info);
            markersLayer.addLayer(marker);
        });

        try {
            rbmMap.fitBounds(markersLayer.getBounds().pad(0.1));
        } catch(e){}
    }

    // B. Handler Tombol di dalam Context Menu (Menggunakan Event Delegation)
    document.addEventListener('click', function(e) {
        const contextMenu = document.getElementById('custom-context-menu');
        
        // 1. Klik Tombol "Pindah ke Rute Lain"
        if (e.target.closest('#ctx-btn-move')) {
            const idpel = document.getElementById('ctx-selected-idpel').value;
            const moveModal = document.getElementById('modal-move-route');
            const idpelLabel = document.getElementById('move-modal-idpel');

            if (idpelLabel) idpelLabel.textContent = idpel;
            
            // Reset Dropdown
            const areaSelect = document.getElementById('move-area');
            const routeSelect = document.getElementById('move-route-select');
            if(areaSelect) areaSelect.value = "";
            if(routeSelect) routeSelect.innerHTML = '<option value="">-- Pilih Area Dulu --</option>';

            if(moveModal) {
                moveModal.classList.remove('hidden');
                moveModal.classList.add('flex');
            }
            
            // Sembunyikan menu setelah klik
            if(contextMenu) contextMenu.classList.add('hidden');
            return;
        }

        // 2. Klik Tombol "Keluarkan"
        if (e.target.closest('#ctx-btn-remove')) {
            const idpel = document.getElementById('ctx-selected-idpel').value;
            if(confirm(`Yakin ingin mengeluarkan IDPEL ${idpel} dari grup?`)) {
                performRemoveIdpel(idpel);
            }
            
            // Sembunyikan menu setelah klik
            if(contextMenu) contextMenu.classList.add('hidden');
            return;
        }

        // 3. Klik di mana saja (Tutup Menu)
        if (contextMenu && !contextMenu.classList.contains('hidden')) {
            // Pastikan tidak menutup jika yang diklik adalah bagian dalam menu itu sendiri (opsional)
            // Tapi biasanya klik menu = aksi = tutup.
            contextMenu.classList.add('hidden');
        }
    });

    // C. Helper & Eksekusi Pindah (Global)
    window.updateMoveRouteOptions = function() {
        const areaSelect = document.getElementById('move-area');
        const routeSelect = document.getElementById('move-route-select');
        if(!areaSelect || !routeSelect) return;
        
        routeSelect.innerHTML = '<option value="">-- Pilih Rute --</option>';
        
        const selectedOption = areaSelect.options[areaSelect.selectedIndex];
        if (selectedOption && selectedOption.dataset.routes) {
            try {
                const routes = JSON.parse(selectedOption.dataset.routes);
                routes.forEach(r => {
                    const opt = document.createElement('option');
                    opt.value = r.code;
                    opt.textContent = `${r.code} (${r.label})`;
                    routeSelect.appendChild(opt);
                });
            } catch(e) { console.error(e); }
        }
    }

    window.executeMoveRoute = function() {
        const idpel = document.getElementById('ctx-selected-idpel').value;
        const area = document.getElementById('move-area').value;
        const route = document.getElementById('move-route-select').value;
        const unitPrefixInput = document.getElementById('ctx-unit-prefix'); 
        const unitPrefix = unitPrefixInput ? unitPrefixInput.value : ''; 
        
        if (!area || !route) {
            alert("Harap pilih Area dan Rute tujuan.");
            return;
        }

        // Asumsi Sub Unit Default 'A' (sesuaikan jika perlu dropdown sub)
        const sub = 'A'; 
        const targetPrefix = `${unitPrefix}${sub}${area}${route}`;

        const moveModal = document.getElementById('modal-move-route');
        if(moveModal) moveModal.classList.add('hidden');

        performMoveIdpel(idpel, targetPrefix); // Fungsi AJAX Move yang sudah ada (Smart Move)
    }

    // --- FUNGSI AJAX HAPUS (Helper untuk Remove) ---
    function performRemoveIdpel(idpel) {
        const urlInput = document.getElementById('remove-route');
        if (!urlInput) return;

        const url = urlInput.value;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        document.body.style.cursor = 'wait';

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ idpel: idpel })
        })
        .then(response => response.json())
        .then(data => {
            document.body.style.cursor = 'default';
            if (data.success) {
                if (typeof App !== 'undefined' && App.Utils) App.Utils.displayNotification('success', data.message);
                
                // Refresh Tab
                const activeTab = App.Utils.getActiveTabName();
                const unitInput = document.querySelector('form.ajax-form input[name="unitup"]');
                if (unitInput) {
                    const refreshUrl = `/team/matrix-kddk/manage-rbm/${encodeURIComponent(unitInput.value)}`;
                    let bustUrl = new URL(refreshUrl, window.location.origin);
                    bustUrl.searchParams.set('_cb', new Date().getTime());
                    App.Tabs.loadTabContent(activeTab, bustUrl.toString());
                }
            } else {
                alert('Gagal: ' + data.message);
            }
        })
        .catch(error => {
            document.body.style.cursor = 'default';
            console.error('Error:', error);
            alert('Terjadi kesalahan server.');
        });
    }

});