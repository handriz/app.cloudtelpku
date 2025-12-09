// resources/js/matrix-handler.js

document.addEventListener('DOMContentLoaded', () => {
    
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
            displayEl.style.transition = 'opacity 0.2s';
            displayEl.style.opacity = 0;
            setTimeout(() => {
                displayEl.textContent = displayCode;
                displayEl.style.opacity = 1;
            }, 200);
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
            const icon = document.getElementById('icon-' + targetId) || toggleRow.querySelector('i.fa-chevron-down'); // Support icon di dalam tombol
            const displayCode = toggleRow.dataset.displayCode;
            
            // Handle Single Row (Manage RBM - Level 2 Digit 6)
            const singleRow = document.getElementById(targetId) || document.getElementById('row-' + targetId);
            if (singleRow) {
                singleRow.classList.toggle('hidden');
                if (!singleRow.classList.contains('hidden') && displayCode) {
                    updateBreadcrumb(displayCode); // UPDATE DISPLAY
                }
            }

            // Handle Multi Rows (Index)
            const multiRows = document.querySelectorAll(`.tree-child-${targetId}`);
            if (multiRows.length > 0) {
                multiRows.forEach(row => row.classList.toggle('hidden'));
            }

            if (icon) icon.classList.toggle('rotate-90');
            return;
        }

        // C. TOGGLE AREA MAP
        const areaHeader = e.target.closest('[data-action="toggle-area-map"]');
        if (areaHeader) {
            e.preventDefault();
            const targetId = areaHeader.dataset.target;
            const areaCode = areaHeader.dataset.areaCode;
            const displayCode = areaHeader.dataset.displayCode; // AMBIL DATA
            const icon = areaHeader.querySelector('.icon-chevron');
            
            const content = document.getElementById(targetId);
            if(content) content.classList.toggle('hidden');
            if(icon) icon.classList.toggle('rotate-180');

            if (content && !content.classList.contains('hidden')) {
                loadMapContext(areaCode, null);
                updateMapTitle(`Peta Area Baca ${areaCode}`);
                if (displayCode) updateBreadcrumb(displayCode); // UPDATE DISPLAY
            }
            return;
        }

        // D. TOGGLE DIGIT 6 (KELOMPOK RUTE) ---
        const digit6Header = e.target.closest('[data-action="toggle-digit6"]');
        if (digit6Header) {
            e.preventDefault();
            const targetId = digit6Header.dataset.target;
            const displayCode = digit6Header.dataset.displayCode; // Ambil data breadcrumb
            const icon = digit6Header.querySelector('.icon-chevron-d6');
            
            const content = document.getElementById(targetId);
            if (content) {
                content.classList.toggle('hidden');
                if(icon) icon.classList.toggle('rotate-180');

                // Update Breadcrumb di atas jika dibuka
                if (!content.classList.contains('hidden') && displayCode) {
                    updateBreadcrumb(displayCode);
                }
            }
            return;
        }

        // E. TOGGLE ROUTE MAP (HARI BACA)
        const routeHeader = e.target.closest('[data-action="toggle-route-map"]');
        if (routeHeader) {
            e.preventDefault(); e.stopPropagation(); 
            const targetId = routeHeader.dataset.target;
            const areaCode = routeHeader.dataset.areaCode;
            const routeCode = routeHeader.dataset.routeCode;
            const displayCode = routeHeader.dataset.displayCode; // AMBIL DATA
            const icon = routeHeader.querySelector('.icon-chevron-sub');

            const content = document.getElementById(targetId);
            if(content) content.classList.toggle('hidden');
            if(icon) icon.classList.toggle('rotate-180');

            if (content && !content.classList.contains('hidden')) {
                loadMapContext(areaCode, routeCode);
                updateMapTitle(`Peta Area ${areaCode} Rute ${routeCode}`);
                if (displayCode) updateBreadcrumb(displayCode); // UPDATE DISPLAY
            }
            return;
        }

        // F. Drill Down Detail
        const row = e.target.closest('[data-action="drill-down"]');
        if (row) {
            e.preventDefault();
            const url = row.dataset.url;
            const tabName = App.Utils.getActiveTabName();
            if (url && tabName) App.Tabs.loadTabContent(tabName, url);
            return;
        }

        // G. FULL SCREEN
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

        // H. TOGGLE MAP LAYOUT (DATA FOCUS MODE) - FIXED
        const mapToggleBtn = e.target.closest('[data-action="toggle-map-layout"]');
        if (mapToggleBtn) {
            e.preventDefault();
            
            const panelList = document.getElementById('panel-list');
            const panelMap = document.getElementById('panel-map');
            const textSpan = mapToggleBtn.querySelector('.text-btn');
            const icon = mapToggleBtn.querySelector('i');
            
            // PERBAIKAN LOGIKA:
            // Cek apakah peta sedang aktif di desktop? (Punya class 'md:block')
            const isMapActive = panelMap.classList.contains('md:block');

            if (isMapActive) {
                // --- AKSI: SEMBUNYIKAN PETA (Mode Fokus Data) ---
                
                // 1. Matikan Peta
                panelMap.classList.remove('md:block'); // Matikan mode desktop
                panelMap.classList.add('hidden');      // Paksa sembunyi
                
                // 2. Lebarkan List
                panelList.classList.remove('md:w-[450px]'); // Hapus lebar tetap
                panelList.classList.add('w-full');           // Pakai lebar penuh
                
                // 3. Update Tombol
                textSpan.textContent = "Show Map";
                icon.className = "fas fa-columns mr-1.5 icon-map";
                
                // 4. Ubah Rute jadi Grid (Supaya muat banyak)
                document.querySelectorAll('.routes-grid-container').forEach(el => {
                    el.classList.remove('space-y-2');
                    el.classList.add('grid', 'grid-cols-1', 'md:grid-cols-2', 'xl:grid-cols-3', 'gap-4');
                });

            } else {
                // --- AKSI: TAMPILKAN PETA (Mode Normal) ---
                
                // 1. Hidupkan Peta
                panelMap.classList.add('md:block');    // Hidupkan mode desktop
                panelMap.classList.remove('hidden');   // Hapus hidden
                
                // 2. Kecilkan List
                panelList.classList.remove('w-full');
                panelList.classList.add('md:w-[450px]'); // Kembali ke lebar asal
                
                // 3. Update Tombol
                textSpan.textContent = "Hide Map";
                icon.className = "fas fa-map-marked-alt mr-1.5 icon-map";
                
                // 4. Ubah Rute jadi List Vertikal
                document.querySelectorAll('.routes-grid-container').forEach(el => {
                    el.classList.remove('grid', 'grid-cols-1', 'md:grid-cols-2', 'xl:grid-cols-3', 'gap-4');
                    el.classList.add('space-y-2');
                });
            }
            
            // Refresh ukuran peta agar tidak error abu-abu
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
                if(typeof rbmMap !== 'undefined' && rbmMap) rbmMap.invalidateSize();
            }, 300);
            
            return;
        }

        // ==========================================================
        // I. TOMBOL HAPUS PELANGGAN SATUAN (BARU)
        // ==========================================================
        const btnRemoveCustomer = e.target.closest('.btn-remove-customer');
        if (btnRemoveCustomer) {
            e.preventDefault();
            e.stopPropagation(); // Agar tidak men-trigger accordion atau drag
            
            const idpel = btnRemoveCustomer.dataset.idpel;
            
            // Fungsi aksi hapus
            const onConfirmAction = () => {
                performRemoveIdpel(idpel);
            };
            
            // Pesan HTML Cantik
            const message = `
                Anda akan mengeluarkan pelanggan <strong class="text-indigo-600 text-lg">${idpel}</strong>.<br>
                <div class="mt-2 p-2 bg-red-50 text-red-700 text-xs rounded border border-red-100 flex items-start">
                    <i class="fas fa-exclamation-circle mt-0.5 mr-2"></i>
                    <span>Status pelanggan akan kembali menjadi <strong>Belum Dikelompokkan</strong>.</span>
                </div>
            `;

            // Panggil Modal Kustom
            if (typeof App !== 'undefined' && App.Utils) {
                App.Utils.showCustomConfirm('Keluarkan Pelanggan?', message, onConfirmAction);
            } else {
                // Fallback aman
                if (confirm(`Keluarkan pelanggan ${idpel}?`)) onConfirmAction();
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

    // ============================================================
    // 4. FUNGSI HELPER AJAX & MODAL SUKSES (FINAL INTEGRATED)
    // ============================================================

    // 1. TAMPILKAN MODAL SUKSES
    function showGenericSuccess(message) {
        const modal = document.getElementById('modal-success-generic');
        const msgEl = document.getElementById('generic-success-message');
        
        if (modal && msgEl) {
            msgEl.textContent = message;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            // Fokus ke tombol OK
            const okBtn = modal.querySelector('button');
            if(okBtn) setTimeout(() => okBtn.focus(), 100);
        } else {
            // Fallback
            alert(message);
            // Jika fallback alert, refresh manual disini
            refreshActiveTab();
        }
    }

    // 2. FUNGSI BARU: TUTUP MODAL & REFRESH (Dipanggil oleh Tombol OK)
    window.closeGenericSuccessModal = function() {
        const modal = document.getElementById('modal-success-generic');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        
        // REFRESH HALAMAN SETELAH TUTUP
        refreshActiveTab();
    }

    // 3. HELPER REFRESH TAB
    function refreshActiveTab() {
        if (typeof App === 'undefined' || !App.Utils || !App.Tabs) return;

        const activeTab = App.Utils.getActiveTabName();
        const unitInput = document.querySelector('form.ajax-form input[name="unitup"]');
        
        if (unitInput && activeTab) {
            const refreshUrl = `/team/matrix-kddk/manage-rbm/${encodeURIComponent(unitInput.value)}`;
            let bustUrl = new URL(refreshUrl, window.location.origin);
            bustUrl.searchParams.set('_cb', new Date().getTime());
            
            // Load ulang tab
            App.Tabs.loadTabContent(activeTab, bustUrl.toString());
        }
    }

    // 4. EKSEKUTOR AJAX
    function executeAjax(url, bodyData) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        document.body.style.cursor = 'wait';

        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(bodyData)
        })
        .then(response => response.json())
        .then(data => {
            document.body.style.cursor = 'default';
            if (data.success) {
                
                // HANYA TAMPILKAN MODAL (JANGAN REFRESH DISINI)
                showGenericSuccess(data.message);
                
            } else {
                alert('Gagal: ' + data.message);
            }
        })
        .catch(error => {
            document.body.style.cursor = 'default';
            console.error('Error AJAX:', error);
            alert('Terjadi kesalahan server.');
        });
    }

    // 4. WRAPPER ACTIONS (AGAR KONSISTEN)
    
    function performMoveIdpel(idpel, targetKddk) {
        const urlInput = document.getElementById('move-route');
        if (!urlInput) return;
        executeAjax(urlInput.value, { idpel: idpel, target_kddk: targetKddk });
    }

    function performRemoveIdpel(idpel) {
        const urlInput = document.getElementById('remove-route');
        if (!urlInput) return;
        executeAjax(urlInput.value, { idpel: idpel });
    }

    // ============================================================
    // 5. LOGIKA GENERATOR KDDK (MODAL)
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
        const areaLabelDisplay = document.getElementById('area-label-display');
        const routeLabelDisplay = document.getElementById('rute-label-display');

        if (!areaSelect || !routeSelect) return;

        routeSelect.innerHTML = '<option value="">--</option>';
        if (routeLabelDisplay) routeLabelDisplay.textContent = '';
        
        const selectedOption = areaSelect.options[areaSelect.selectedIndex];
        if (areaLabelDisplay && selectedOption.value) {
             // Ambil text setelah kode (misal: "RB - RBM Paskabayar" -> ambil "RBM Paskabayar")
             // Atau gunakan data-label jika kita tambahkan di blade
             const labelText = selectedOption.dataset.label || selectedOption.text;
             areaLabelDisplay.textContent = labelText;
        } else if (areaLabelDisplay) {
             areaLabelDisplay.textContent = '';
        }

        if (selectedOption && selectedOption.dataset.routes) {
            try {
                const routes = JSON.parse(selectedOption.dataset.routes);
                routes.forEach(r => {
                    const opt = document.createElement('option');
                    opt.value = r.code;
                    opt.textContent = `${r.code} (${r.label})`;
                    opt.dataset.label = r.label;
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
        const count = (typeof selectionState !== 'undefined') ? selectionState.items.size : 0;
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
    // 7. LOGIKA PETA KONTEKSTUAL (Baru)
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
        if (!mapContainer) return;

        if (rbmMap) {
            const attachedContainer = rbmMap.getContainer();
            
            // Jika container berbeda (berarti halaman baru di-load via AJAX), 
            // maka peta lama harus dihancurkan.
            if (attachedContainer !== mapContainer) {
                console.log("Map container changed. Destroying old instance.");
                rbmMap.remove();
                rbmMap = null;
                markersLayer = null;
            }
        }

        // Init Map jika belum ada (atau baru saja di-reset)
        if (!rbmMap) {
            mapContainer.innerHTML = ''; // Bersihkan placeholder
            rbmMap = L.map('rbm-map').setView([0.5071, 101.4478], 13);
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles © Esri'
            }).addTo(rbmMap);
            markersLayer = L.featureGroup().addTo(rbmMap);
            setTimeout(() => {
                rbmMap.invalidateSize();
            }, 200);
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
           setTimeout(() => {
                if (markersLayer.getLayers().length > 0) {
                    rbmMap.fitBounds(markersLayer.getBounds().pad(0.1)); 
                }
            }, 100);
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

    // ============================================================
    // 8. CUSTOM SUBMIT GENERATOR (UNTUK MODAL SUKSES)
    // ============================================================
    
    // PERBAIKAN 1: Gunakan 'true' (Capture Phase) agar menang dari tab-manager.js
    document.addEventListener('submit', function(e) {
        if (e.target.id === 'kddk-generator-form') {
            e.preventDefault(); 
            e.stopPropagation(); // Hentikan event bubbling ke tab-manager
            handleGeneratorSubmit(e.target);
        }
    }, true); 

    function handleGeneratorSubmit(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

        fetch(form.action, {
            method: 'POST',
            headers: { 
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new FormData(form)
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;

            if (data.success) {
                // 1. Tutup Modal Generator
                window.closeKddkModal();
                selectionState.items.clear();
                toggleGroupButton();

                document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
                const checkAll = document.getElementById('check-all-rows');
                if(checkAll) checkAll.checked = false;
                
                // 2. Tampilkan Modal Sukses Kustom
                showSuccessModal(data);
                
                // PERBAIKAN 2: JANGAN REFRESH TAB DISINI!
                // Refresh akan dilakukan saat user klik tombol "Selesai" di modal sukses.
                // Jika refresh disini, modal sukses akan langsung hilang tertimpa konten baru.
                
            } else {
                alert('Gagal: ' + data.message);
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            console.error(err);
            
            if (err.errors) {
                alert('Validasi Gagal. Cek inputan.'); 
            } else {
                alert('Terjadi kesalahan sistem.');
            }
        });
    }

    function showSuccessModal(data) {
        const modal = document.getElementById('modal-success-generator');
        if (!modal) {
            alert(data.message);
            return;
        }
        document.getElementById('success-modal-message').textContent = data.message;
        
        const previewCode = document.getElementById('final_kddk_preview').value;
        const totalCount = document.getElementById('count-selected').textContent;

        const codeEl = document.getElementById('success-start-code');
        const countEl = document.getElementById('success-total-count');
        
        if(codeEl) codeEl.textContent = previewCode.substring(0, 12) + '...';
        if(countEl) countEl.textContent = totalCount + ' Pelanggan';

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    // ============================================================
    // 8. LOGIKA BULK ACTION (MULTI SELECT)
    // ============================================================
    
    // A. Handle Checkbox Change
    document.addEventListener('change', function(e) {
        // 1. Select All per Table
        if (e.target.classList.contains('select-all-route')) {
            const table = e.target.closest('table');
            const checkboxes = table.querySelectorAll('.select-item-row');
            checkboxes.forEach(cb => cb.checked = e.target.checked);
            updateBulkUI();
        }

        // 2. Select Individual Item
        if (e.target.classList.contains('select-item-row')) {
            // Cek apakah semua dicentang untuk update header checkbox
            const table = e.target.closest('table');
            const all = table.querySelectorAll('.select-item-row');
            const checked = table.querySelectorAll('.select-item-row:checked');
            const headerCb = table.querySelector('.select-all-route');
            if (headerCb) headerCb.checked = (all.length === checked.length);
            
            updateBulkUI();
        }
    });

    // B. Update UI Floating Bar
    function updateBulkUI() {
        const totalChecked = document.querySelectorAll('.select-item-row:checked').length;
        const bar = document.getElementById('bulk-action-bar');
        const countSpan = document.getElementById('bulk-count');

        if (totalChecked > 0) {
            if(bar) bar.classList.remove('hidden');
            if(countSpan) countSpan.textContent = totalChecked;
        } else {
            if(bar) bar.classList.add('hidden');
        }
    }

    // C. Clear Selection
    window.clearBulkSelection = function() {
        document.querySelectorAll('.select-item-row').forEach(cb => cb.checked = false);
        document.querySelectorAll('.select-all-route').forEach(cb => cb.checked = false);
        updateBulkUI();
    }

    // D. Bulk Actions Implementation
    window.openBulkMoveModal = function() {
        // Gunakan modal pindah yang sudah ada, tapi set flag "BULK"
        const moveModal = document.getElementById('modal-move-route');
        const count = document.getElementById('bulk-count').textContent;
        
        // Ubah judul modal agar user tahu ini massal
        const titleEl = moveModal.querySelector('h3');
        const idpelEl = document.getElementById('move-modal-idpel');
        
        titleEl.textContent = `Pindahkan ${count} Pelanggan`;
        idpelEl.textContent = 'Multi Selection';
        idpelEl.dataset.mode = 'bulk'; // Flag Mode

        // Reset Dropdown
        const areaSelect = document.getElementById('move-area');
        const routeSelect = document.getElementById('move-route-select');
        if(areaSelect) areaSelect.value = "";
        if(routeSelect) {
            routeSelect.innerHTML = '<option value="">-- Pilih Area Dulu --</option>';
            routeSelect.disabled = true;
        }

        moveModal.classList.remove('hidden');
        moveModal.classList.add('flex');
    }

    window.executeBulkRemove = function() {
        const checked = document.querySelectorAll('.select-item-row:checked');
        const ids = Array.from(checked).map(cb => cb.value);
        
        if (ids.length === 0) return;

        const count = ids.length;
        const url = document.getElementById('bulk-remove-route').value;

        // Aksi Konfirmasi
        const onConfirmAction = () => {
            executeAjax(url, { idpels: ids });
            window.clearBulkSelection();
        };

        // Pesan HTML Cantik
        const title = 'Keluarkan Pelanggan?';
        const message = `
            Anda akan mengeluarkan <strong class="text-red-600 text-xl font-bold mx-1">${count}</strong> pelanggan terpilih dari grup ini.<br>
            <span class="text-xs mt-2 block bg-red-50 text-red-600 p-2 rounded border border-red-100">
                <i class="fas fa-info-circle mr-1"></i> Data akan kembali ke antrian detail (Belum Dikelompokkan).
            </span>
        `;

        // Panggil Modal Kustom
        if (typeof App !== 'undefined' && App.Utils) {
            App.Utils.showCustomConfirm(title, message, onConfirmAction);
        } else {
            if(confirm(`Yakin ingin mengeluarkan ${count} pelanggan?`)) onConfirmAction();
        }
    }

    // PERBAIKAN 3: REFRESH SAAT TUTUP MODAL
    window.closeSuccessModal = function() {
        const modal = document.getElementById('modal-success-generator');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            
            // BARU DISINI KITA REFRESH HALAMAN
            const activeTab = App.Utils.getActiveTabName();
            if (activeTab) {
                // Ambil URL refresh yang benar (pertahankan search filter jika ada)
                const tabContent = document.getElementById(`${activeTab}-content`);
                const searchForm = tabContent ? tabContent.querySelector('form[action*="details"]') : null;
                
                // Gunakan URL search form (jika ada) atau URL tab default
                let refreshUrl = window.location.href;
                if (searchForm) {
                     refreshUrl = searchForm.action + window.location.search;
                } else {
                     // Fallback ke data-url tab button
                     const tabBtn = document.querySelector(`.tab-button[data-tab-name="${activeTab}"]`);
                     if(tabBtn) refreshUrl = tabBtn.dataset.url;
                }
                
                // Tambahkan cache buster
                let bustUrl = new URL(refreshUrl, window.location.origin);
                bustUrl.searchParams.set('_cb', new Date().getTime());

                App.Tabs.loadTabContent(activeTab, bustUrl.toString());
            }
        }
    }

    // C. Helper & Eksekusi Pindah (Global)
    window.updateMoveRouteOptions = function() {
        const areaSelect = document.getElementById('move-area');
        const routeSelect = document.getElementById('move-route-select');
        if(!areaSelect || !routeSelect) return;
        
        routeSelect.innerHTML = '<option value="">-- Pilih Rute --</option>';
        routeSelect.disabled = true;
        
        const selectedOption = areaSelect.options[areaSelect.selectedIndex];

        if (selectedOption && selectedOption.dataset.routes) {
            try {
                const routes = JSON.parse(selectedOption.dataset.routes);
                if (routes.length > 0) {
                    routeSelect.disabled = false; // <--- INI KUNCINYA
                    
                    routes.forEach(r => {
                        const opt = document.createElement('option');
                        opt.value = r.code;
                        opt.textContent = `${r.code} (${r.label})`;
                        routeSelect.appendChild(opt);
                    });
                }
            } catch(e) { console.error(e); }
        }
    }

    window.executeMoveRoute = function() {
        const moveModal = document.getElementById('modal-move-route');
        const idpelEl = document.getElementById('move-modal-idpel');
        const isBulk = idpelEl.dataset.mode === 'bulk';
        
        const area = document.getElementById('move-area').value;
        const route = document.getElementById('move-route-select').value;
        const unitPrefixInput = document.getElementById('ctx-unit-prefix'); 
        const unitPrefix = unitPrefixInput ? unitPrefixInput.value : ''; 
        
        if (!area || !route) {
            alert("Harap pilih Area dan Rute tujuan.");
            return;
        }

        const sub = 'A'; 
        const targetPrefix = `${unitPrefix}${sub}${area}${route}`;

        if (isBulk) {
            // Logic Bulk
            const checked = document.querySelectorAll('.select-item-row:checked');
            const ids = Array.from(checked).map(cb => cb.value);
            const url = document.getElementById('bulk-move-route').value;
            
            executeAjax(url, { idpels: ids, target_kddk: targetPrefix });
            window.clearBulkSelection();
        } else {
            // Logic Single (Lama)
            const idpel = document.getElementById('ctx-selected-idpel').value;
            performMoveIdpel(idpel, targetPrefix);
        }

        moveModal.classList.add('hidden');
        moveModal.classList.remove('flex');
        // Reset flag
        idpelEl.dataset.mode = '';
        const titleEl = moveModal.querySelector('h3');
        titleEl.textContent = 'Pindahkan Pelanggan'; // Balikin judul
    }

    window.updateLabelDisplay = function() {
        const routeSelect = document.getElementById('part_rute');
        const routeLabelDisplay = document.getElementById('rute-label-display');
        
        if (routeSelect && routeLabelDisplay) {
            const selectedOption = routeSelect.options[routeSelect.selectedIndex];
            if (selectedOption && selectedOption.value) {
                routeLabelDisplay.textContent = selectedOption.dataset.label || '';
            } else {
                routeLabelDisplay.textContent = '';
            }
        }
        // Panggil generator update juga
        if (typeof updateSequenceAndGenerate === 'function') updateSequenceAndGenerate();
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