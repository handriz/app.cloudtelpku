// resources/js/matrix-handler.js

document.addEventListener('DOMContentLoaded', () => {

    // ============================================================
    // 1. GLOBAL STATE & VARIABEL
    // ============================================================
    const selectionState = {
        unit: null,     
        items: new Map() // Key: IDPEL, Value: {jenis: ...}
    };

    // Variabel Peta Multi-Layer
    let rbmMap = null;
    const areaLayers = {}; // Simpan layer aktif: { 'area-RB': LayerGroup, 'route-A1': LayerGroup }
    let sequenceController = null; // Untuk abort fetch sequence

    function updateBreadcrumb(displayCode) {
        const displayEl = document.getElementById('live-kddk-display');
        if (displayEl && displayCode) {
            displayEl.style.transition = 'opacity 0.2s';
            displayEl.style.opacity = 0;
            setTimeout(() => {
                displayEl.textContent = displayCode;
                displayEl.style.opacity = 1;
            }, 200);
        }
    }

    function syncSelectionUI() {
        const contextInput = document.getElementById('page-context-unit');
        if (!contextInput) return;
        const currentUnit = contextInput.value;
        if (selectionState.unit !== currentUnit) {
            selectionState.unit = currentUnit;
            selectionState.items.clear();
        }
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => cb.checked = selectionState.items.has(cb.value));
        const checkAll = document.getElementById('check-all-rows');
        if (checkAll) checkAll.checked = (checkboxes.length > 0 && [...checkboxes].every(c => c.checked));
        toggleGroupButton();
    }

    const observer = new MutationObserver((mutations) => {
        let shouldSync = false;
        let dashboardLoaded = false; // Flag baru

        mutations.forEach(m => { 
            // Cek jika konten tab berubah
            if (m.target.id === 'tabs-content' || m.target.closest('#tabs-content')) {
                shouldSync = true;
                
                // Cek spesifik apakah Data Dashboard masuk ke DOM?
                // Kita cek apakah node baru mengandung ID 'dashboard-analytics-data'
                m.addedNodes.forEach(node => {
                    if (node.nodeType === 1) { // Element Node
                        if (node.id === 'dashboard-analytics-data' || node.querySelector('#dashboard-analytics-data')) {
                            dashboardLoaded = true;
                        }
                    }
                });
            }
        });
        
        if (shouldSync) setTimeout(syncSelectionUI, 50);
        
        // [TAMBAHAN] Trigger Chart jika Dashboard terdeteksi
        if (dashboardLoaded) {
            setTimeout(initDashboardCharts, 100); 
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });


    // ============================================================
    // 2. EVENT LISTENERS (CLICK)
    // ============================================================

    document.addEventListener('click', function(e) {

        // A. TOMBOL KELOLA RBM
        const rbmBtn = e.target.closest('[data-action="manage-rbm"]');
        if (rbmBtn) {
            e.preventDefault(); e.stopPropagation(); 
            const url = rbmBtn.dataset.url;
            const tabName = App.Utils.getActiveTabName();
            if (url && tabName) App.Tabs.loadTabContent(tabName, url);
            return;
        }

        // B. TOGGLE TREE VIEW (Dashboard / General)
        const toggleRow = e.target.closest('[data-action="toggle-tree"]');
        if (toggleRow) {
            e.preventDefault(); e.stopPropagation(); 
            const targetId = toggleRow.dataset.target;
            const icon = document.getElementById('icon-' + targetId) || toggleRow.querySelector('i.fa-chevron-down');
            const displayCode = toggleRow.dataset.displayCode;
            
            const singleRow = document.getElementById('row-' + targetId) || document.getElementById(targetId);
            if (singleRow) {
                singleRow.classList.toggle('hidden');
                if (!singleRow.classList.contains('hidden') && displayCode) updateBreadcrumb(displayCode);
            }
            const multiRows = document.querySelectorAll(`.tree-child-${targetId}`);
            multiRows.forEach(row => row.classList.toggle('hidden'));
            if (icon) icon.classList.toggle('rotate-90');
            return;
        }

        // C. TOGGLE AREA MAP (LEVEL 1)
        const areaHeader = e.target.closest('[data-action="toggle-area-map"]');
        if (areaHeader) {
            e.preventDefault();
            const targetId = areaHeader.dataset.target; // ID Area (misal: area-RB)
            const areaCode = areaHeader.dataset.areaCode; // Kode Area (misal: RB)
            const displayCode = areaHeader.dataset.displayCode;
            const icon = areaHeader.querySelector('.icon-chevron');
            
            const content = document.getElementById(targetId);
            if(content) {
                // Toggle Tampilan HTML Utama
                const isNowHidden = content.classList.toggle('hidden');
                // Putar Icon Panah Utama
                if(icon) icon.classList.toggle('rotate-180');

                if (!isNowHidden) {
                    // BUKA: Update Breadcrumb & Tampilkan Bola Cluster Area
                    if (displayCode) updateBreadcrumb(displayCode);
                    loadAreaMap(areaCode);                    
                } else {
                    // TUTUP: Hapus Layer Area dari Peta (Bersih)
                    removeAreaMap(areaCode);
                    
                    // Reset UI Anak (Tutup semua accordion rute di dalamnya)
                    const childAccordions = content.querySelectorAll('[id^="route-"]');
                    childAccordions.forEach(el => el.classList.add('hidden'));
                    const childIcons = content.querySelectorAll('.icon-chevron-sub, .icon-chevron-d6');
                    childIcons.forEach(el => el.classList.remove('rotate-180'));
                }
            }
            return;
        }

        // D. TOGGLE DIGIT 6 (KELOMPOK RUTE - LEVEL 2)
        const digit6Header = e.target.closest('[data-action="toggle-digit6"]');
        if (digit6Header) {
            e.preventDefault();
            const targetId = digit6Header.dataset.target;
            const displayCode = digit6Header.dataset.displayCode;
            const icon = digit6Header.querySelector('.icon-chevron-d6');
            
            const content = document.getElementById(targetId);
            if (content) {
                content.classList.toggle('hidden');
                if(icon) icon.classList.toggle('rotate-180');
                if (!content.classList.contains('hidden') && displayCode) updateBreadcrumb(displayCode);
            }
            return;
        }

        // E. TOGGLE ROUTE MAP (HARI BACA - LEVEL 3)
        const routeHeader = e.target.closest('[data-action="toggle-route-map"]');
        if (routeHeader) {
            e.preventDefault(); e.stopPropagation(); 
            const targetId = routeHeader.dataset.target; 
            const areaCode = routeHeader.dataset.areaCode;
            const routeCode = routeHeader.dataset.routeCode;
            const displayCode = routeHeader.dataset.displayCode;
            const icon = routeHeader.querySelector('.icon-chevron-sub');

            const content = document.getElementById(targetId);
            if(content) {
                const isHidden = content.classList.toggle('hidden');
                if(icon) icon.classList.toggle('rotate-180');
                
                // Pastikan Layer Induk (Area) ada di peta
                loadAreaMap(areaCode, () => {
                    const btnReorder = document.getElementById('map-visual-controls');

                    if (!isHidden) {
                        // SAAT BUKA RUTE:
                        if (displayCode) updateBreadcrumb(displayCode);
                        
                        // Load Tabel Pelanggan (AJAX)
                        if (content.dataset.loaded === "false") {
                            loadRouteTableData(targetId, areaCode, routeCode);
                        }
                        
                        // (Opsional) Zoom otomatis ke bounds layer area agar terlihat jelas
                        fitBoundsToLayer(areaLayers[areaCode]);

                        if(btnReorder) btnReorder.classList.remove('hidden');

                    } else {
                    // === SAAT RUTE DITUTUP (CLOSE) ===
                        
                        // 1. [BARU] SEMBUNYIKAN TOMBOL REORDER
                        if(btnReorder) {
                            btnReorder.classList.add('hidden');
                            
                            // Opsional: Jika user lupa simpan/batal, paksa keluar mode edit
                            if (window.isReorderMode) cancelVisualReorder();
                        }
                    }
                });
            }
            return;
        }

        // F. DRILL DOWN DETAIL
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
                if (document.documentElement.requestFullscreen) document.documentElement.requestFullscreen().catch(e => console.log(e));
                workspace.classList.add('fullscreen-mode');
                document.body.classList.add('rbm-fullscreen');
                iconExpand.classList.add('hidden');
                iconCompress.classList.remove('hidden');
                textSpan.textContent = "Exit Full";
            } else {
                if (document.exitFullscreen) document.exitFullscreen();
                workspace.classList.remove('fullscreen-mode');
                document.body.classList.remove('rbm-fullscreen');
                iconExpand.classList.remove('hidden');
                iconCompress.classList.add('hidden');
                textSpan.textContent = "Full Screen";
            }
            return;
        }

        // H. TOGGLE MAP LAYOUT (DATA FOCUS MODE)
        const mapToggleBtn = e.target.closest('[data-action="toggle-map-layout"]');
        if (mapToggleBtn) {
            e.preventDefault();
            const panelList = document.getElementById('panel-list');
            const panelMap = document.getElementById('panel-map');
            const textSpan = mapToggleBtn.querySelector('.text-btn');
            const icon = mapToggleBtn.querySelector('i');
            
            const isMapActive = panelMap.classList.contains('md:block');

            if (isMapActive) {
                // Sembunyikan Peta
                panelMap.classList.remove('md:block');
                panelMap.classList.add('hidden');
                panelList.classList.remove('md:w-[450px]');
                panelList.classList.add('w-full');
                textSpan.textContent = "Show Map";
                icon.className = "fas fa-columns mr-1.5 icon-map";
                
                document.querySelectorAll('.routes-grid-container').forEach(el => {
                    el.classList.remove('space-y-0');
                    el.classList.add('grid', 'grid-cols-1', 'md:grid-cols-2', 'xl:grid-cols-3', 'gap-4', 'p-2');
                });
            } else {
                // Tampilkan Peta
                panelMap.classList.add('md:block');
                panelMap.classList.remove('hidden');
                panelList.classList.remove('w-full');
                panelList.classList.add('md:w-[450px]');
                textSpan.textContent = "Hide Map";
                icon.className = "fas fa-map-marked-alt mr-1.5 icon-map";
                
                document.querySelectorAll('.routes-grid-container').forEach(el => {
                    el.classList.remove('grid', 'grid-cols-1', 'md:grid-cols-2', 'xl:grid-cols-3', 'gap-4', 'p-2');
                    el.classList.add('space-y-0');
                });
            }
            
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
                if(typeof rbmMap !== 'undefined' && rbmMap) rbmMap.invalidateSize();
            }, 300);
            return;
        }

        // I. TOMBOL HAPUS PELANGGAN SATUAN
        const btnRemoveCustomer = e.target.closest('.btn-remove-customer');
        if (btnRemoveCustomer) {
            e.preventDefault(); e.stopPropagation();
            const idpel = btnRemoveCustomer.dataset.idpel;
            const onConfirmAction = () => performRemoveIdpel(idpel);
            const message = `
                Anda akan mengeluarkan pelanggan <strong class="text-indigo-600 text-lg">${idpel}</strong>.<br>
                <div class="mt-2 p-2 bg-red-50 text-red-700 text-xs rounded border border-red-100 flex items-start">
                    <i class="fas fa-exclamation-circle mt-0.5 mr-2"></i>
                    <span>Status pelanggan akan kembali menjadi <strong>Belum Dikelompokkan</strong>.</span>
                </div>
            `;

            if (typeof App !== 'undefined' && App.Utils) {
                App.Utils.showCustomConfirm('Keluarkan Pelanggan?', message, onConfirmAction);
            } else {
                if (confirm(`Keluarkan pelanggan ${idpel}?`)) onConfirmAction();
            }
            return;
        }

        
        // J. CONTEXT MENU ACTIONS (KLIK KANAN MENU)
        // 1. Pindah via Menu
        if (e.target.closest('#ctx-btn-move')) {
            const idpel = document.getElementById('ctx-selected-idpel').value;
            const moveModal = document.getElementById('modal-move-route');
            const idpelLabel = document.getElementById('move-modal-idpel');

            if (idpelLabel) idpelLabel.textContent = idpel;
            
            // Reset Dropdown
            const areaSelect = document.getElementById('move-area');
            const routeSelect = document.getElementById('move-route-select');
            if(areaSelect) areaSelect.value = "";
            if(routeSelect) {
                routeSelect.innerHTML = '<option value="">-- Pilih Area Dulu --</option>';
                routeSelect.disabled = true;
            }

            if(moveModal) {
                moveModal.classList.remove('hidden');
                moveModal.classList.add('flex');
            }
            
            // Sembunyikan menu
            document.getElementById('custom-context-menu').classList.add('hidden');
            return;
        }

        // 2. Hapus via Menu
        if (e.target.closest('#ctx-btn-remove')) {
            const idpel = document.getElementById('ctx-selected-idpel').value;
            const onConfirmAction = () => performRemoveIdpel(idpel);
            const message = `
                Anda akan mengeluarkan pelanggan <strong class="text-indigo-600">${idpel}</strong>.<br>
                <span class="text-xs text-gray-500">Status kembali ke belum dikelompokkan.</span>
            `;

            if (typeof App !== 'undefined' && App.Utils) {
                App.Utils.showCustomConfirm('Keluarkan Pelanggan?', message, onConfirmAction);
            } else {
                if(confirm(`Keluarkan ${idpel}?`)) performRemoveIdpel(idpel);
            }
            
            // Sembunyikan menu
            document.getElementById('custom-context-menu').classList.add('hidden');
            return;
        }

        // 3. Tutup Menu jika klik sembarang tempat
        const contextMenu = document.getElementById('custom-context-menu');
        if (contextMenu && !contextMenu.classList.contains('hidden')) {
            // Jangan tutup jika klik di dalam menu itu sendiri (opsional)
            if (!e.target.closest('#custom-context-menu')) {
                contextMenu.classList.add('hidden');
            }
        }

        // 4. Cek apakah yang diklik adalah tombol export (atau anaknya)
        const btn = e.target.closest('#btn-export-trigger');
        const dropdown = document.getElementById('export-dropdown-menu');

        // Skenario A: Klik Tombol Export
        if (btn && dropdown) {
            e.preventDefault();
            e.stopPropagation();

            const isHidden = dropdown.classList.contains('hidden');

            if (isHidden) {
                // 1. Tampilkan
                dropdown.classList.remove('hidden');
                
                // 2. PINDAHKAN KE BODY (Agar Z-Index Menang Mutlak dari Peta)
                document.body.appendChild(dropdown);
                
                // 3. Hitung Posisi Tombol
                const rect = btn.getBoundingClientRect();
                
                // 4. Set Posisi Fixed (Menempel pada layar, bukan container)
                dropdown.style.position = 'fixed';
                dropdown.style.zIndex = '99999'; // Paling Atas
                dropdown.style.top = (rect.bottom + 5) + 'px'; // Di bawah tombol
                dropdown.style.left = (rect.right - dropdown.offsetWidth) + 'px'; // Rata kanan tombol
            } else {
                // Sembunyikan
                dropdown.classList.add('hidden');
            }
            return;
        }

        // Skenario B: Klik Item di dalam Dropdown (Excel/CSV)
        if (e.target.closest('.export-item') && dropdown) {
            // Biarkan link bekerja (download), tapi tutup dropdown
            setTimeout(() => dropdown.classList.add('hidden'), 200);
            return;
        }

        // Skenario C: Klik di Luar (Tutup Dropdown)
        if (dropdown && !dropdown.classList.contains('hidden')) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        }

    });


    // ============================================================
    // 3. EVENT LISTENERS LAIN (INPUT, CHANGE, SUBMIT)
    // ============================================================
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('kddk-part')) updateSequenceAndGenerate();
        if (e.target.id === 'part_sisip') e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 2);
        if (e.target.id === 'kddk-search-input') handleKddkSearch(e.target.value);
    });

    document.addEventListener('change', function(e) {
        // A. Checkbox Baris
        if (e.target.classList.contains('row-checkbox')) {
            const idpel = e.target.value;
            const jenis = e.target.dataset.jenis || 'LAINNYA'; 
            if (e.target.checked) selectionState.items.set(idpel, { jenis: jenis });
            else selectionState.items.delete(idpel);
            
            const checkAll = document.getElementById('check-all-rows');
            if (checkAll && !e.target.checked) checkAll.checked = false;
            toggleGroupButton();
        }

        // B. Checkbox Pilih Semua
        if (e.target.id === 'check-all-rows') {
            const isChecked = e.target.checked;
            document.querySelectorAll('.row-checkbox').forEach(cb => {
                cb.checked = isChecked;
                if (isChecked) selectionState.items.set(cb.value, { jenis: cb.dataset.jenis || 'LAINNYA' });
                else selectionState.items.delete(cb.value);
            });
            toggleGroupButton();
        }

        // C. Generator Dropdown
        if (e.target.classList.contains('kddk-part')) {
            if (e.target.id === 'part_area') updateRouteOptions();
            updateSequenceAndGenerate();
        }

        // D. Select All Route (Di Manage RBM)
        if (e.target.classList.contains('select-all-route')) {
            const table = e.target.closest('table');
            const checkboxes = table.querySelectorAll('.select-item-row');
            checkboxes.forEach(cb => cb.checked = e.target.checked);
            updateBulkUI();
        }
        
        // E. Select Item Row (Di Manage RBM)
        if (e.target.classList.contains('select-item-row')) {
            const table = e.target.closest('table');
            const all = table.querySelectorAll('.select-item-row');
            const checked = table.querySelectorAll('.select-item-row:checked');
            const headerCb = table.querySelector('.select-all-route');
            if (headerCb) headerCb.checked = (all.length === checked.length);
            updateBulkUI();
        }
    });

    document.addEventListener('change', function(e) {
        if (e.target && e.target.id === 'csv-selection-detail') {
            const fileInput = e.target;
            const file = fileInput.files[0];
            
            if (!file) return;

            const reader = new FileReader();
            
            reader.onload = function(event) {
                const text = event.target.result;
                const lines = text.split(/\r\n|\n/);
                
                let totalInCsv = 0;
                let newAdded = 0;
                
                // 1. ITERASI DATA FILE (Bukan Data Tabel)
                lines.forEach(line => {
                    const cleanId = line.trim().replace(/[^0-9]/g, '');
                    
                    // Validasi panjang IDPEL (11-13 digit)
                    if (cleanId.length >= 10) { 
                        totalInCsv++;
                        
                        // 2. MASUKKAN KE MEMORI (STATE) LANGSUNG
                        // Ini intinya! Data tersimpan di RAM browser, tidak peduli halaman berapa.
                        if (!selectionState.items.has(cleanId)) {
                            selectionState.items.set(cleanId, { jenis: 'UPLOAD_CSV' });
                            newAdded++;
                        }
                    }
                });

                // 3. Update Visual Halaman INI Saja (Opsional, agar user tidak bingung)
                // Hanya mencentang yang kebetulan sedang tampil. Sisanya biarkan di memori.
                if (typeof syncSelectionUI === 'function') {
                    syncSelectionUI();
                }
                
                // 4. Update Tombol Grouping (Tampilkan Jumlah Total Memori)
                if (typeof toggleGroupButton === 'function') {
                    toggleGroupButton(); 
                }

                fileInput.value = ''; // Reset input
                
                // 5. Tampilkan Pesan Sukses
                // Pesan ini menegaskan bahwa SEMUA data sudah masuk
                showBeautifulUploadSuccess(totalInCsv, selectionState.items.size);
            };

            reader.readAsText(file);
        }
    });

    // --- FUNGSI PEMBUAT MODAL CANTIK (INJECT KE DOM) ---
    function showBeautifulUploadSuccess(totalCsv, foundDom) {
        // Hapus modal lama jika ada
        const existing = document.getElementById('custom-upload-modal');
        if (existing) existing.remove();

        // Template HTML Modal (Tailwind)
        const modalHtml = `
            <div id="custom-upload-modal" class="fixed inset-0 z-[10000] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4 transition-opacity duration-300 opacity-0">
                <div class="relative w-full max-w-xs sm:max-w-sm transform rounded-2xl bg-white dark:bg-gray-800 shadow-2xl transition-all duration-300 scale-90 opacity-0" id="custom-upload-card">
                    
                    <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-green-400 to-emerald-600 rounded-t-2xl"></div>

                    <div class="p-6 text-center">
                        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-50 dark:bg-green-900/20 ring-8 ring-green-50 dark:ring-green-900/10">
                            <i class="fas fa-file-csv text-3xl text-green-500 animate-pulse"></i>
                        </div>

                        <h3 class="mb-1 text-xl font-extrabold text-gray-900 dark:text-white tracking-tight">Upload Berhasil!</h3>
                        <p class="text-xs text-gray-400 mb-5">Data seleksi telah diproses.</p>
                        
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 mb-6 border border-gray-100 dark:border-gray-600">
                            <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-600 pb-2 mb-2">
                                <span class="text-xs font-bold text-gray-500 uppercase tracking-wide">Total di File</span>
                                <span class="text-base font-mono font-bold text-gray-800 dark:text-gray-200">${totalCsv}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold text-gray-500 uppercase tracking-wide">Dicentang</span>
                                <span class="text-base font-mono font-bold text-green-600">+${foundDom}</span>
                            </div>
                        </div>

                        <p class="text-[10px] text-gray-400 mb-4 italic">
                            *Hanya data yang tampil di halaman ini yang dicentang otomatis.
                        </p>

                        <button id="btn-close-upload-modal" class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-indigo-500/30 transition-all duration-200 hover:bg-indigo-700 hover:-translate-y-0.5 focus:ring-4 focus:ring-indigo-300">
                            Selesai
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Masukkan ke Body
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Elemen
        const modal = document.getElementById('custom-upload-modal');
        const card = document.getElementById('custom-upload-card');
        const btn = document.getElementById('btn-close-upload-modal');

        // Animasi Masuk (Fade In & Scale Up)
        requestAnimationFrame(() => {
            modal.classList.remove('opacity-0');
            card.classList.remove('scale-90', 'opacity-0');
            card.classList.add('scale-100', 'opacity-100');
        });

        // Fungsi Tutup
        const closeModal = () => {
            modal.classList.add('opacity-0');
            card.classList.remove('scale-100');
            card.classList.add('scale-95'); // Efek zoom out dikit saat tutup
            setTimeout(() => modal.remove(), 300);
        };

        btn.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
    }

    // Custom Submit Generator (Capture Phase: true)
    document.addEventListener('submit', function(e) {

        if (e.target.id === 'rbm-form') {
            e.preventDefault(); 
            e.stopPropagation(); 
            handleMainFormSubmit(e.target);
        }

        if (e.target.id === 'kddk-generator-form') {
            e.preventDefault(); 
            e.stopPropagation(); 
            handleGeneratorSubmit(e.target);
        }
    }, true); 


    // ============================================================
    // 4. LOGIKA PETA (MULTI-LAYER)
    // ============================================================
    
    function loadAreaMap(areaCode, callback = null) {
        const mapContainer = document.getElementById('rbm-map');
        const urlInput = document.getElementById('map-data-url');
        
        if (!mapContainer || !urlInput) return;

        // 1. Init Map (Jika belum ada)
        if (!rbmMap) {
            mapContainer.innerHTML = ''; 
            rbmMap = L.map('rbm-map').setView([0.5071, 101.4478], 13);
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles © Esri'
            }).addTo(rbmMap);
            const resizeObserver = new ResizeObserver(() => { if (rbmMap) rbmMap.invalidateSize(); });
            resizeObserver.observe(mapContainer);
            
            // Fix Popup Close (Agar tidak reload halaman)
            mapContainer.addEventListener('click', function(e) {
                if (e.target.closest('.leaflet-popup-close-button') || e.target.closest('a')) {
                    e.stopPropagation();
                }
            });
        }

        // 2. Cek apakah Layer Area sudah ada di memori?
        if (areaLayers[areaCode]) {
            // Jika sudah ada tapi belum tampil di peta, tambahkan
            if (!rbmMap.hasLayer(areaLayers[areaCode])) {
                rbmMap.addLayer(areaLayers[areaCode]);
            }
            // Jalankan callback (misal: lanjut buka tabel rute)
            if (callback) callback();
            updateTotalPoints();
            updateMapTitleWrapper();
            return;
        }

        // 3. Fetch Data Baru (Jika belum ada di memori)
        const countSpan = document.getElementById('map-count');
        if(countSpan) countSpan.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

        // URL meminta data "Area" (Grosiran)
        const fetchUrl = `${urlInput.value}?area=${areaCode}`; 

        fetch(`${urlInput.value}?area=${areaCode}`)
            .then(res => res.json())
            .then(points => {
                
                // A. Setup Cluster
                const newLayer = L.markerClusterGroup({
                    disableClusteringAtZoom: 19,
                    spiderfyOnMaxZoom: true,
                    showCoverageOnHover: false, // Kita ganti dengan Tooltip Custom
                    chunkedLoading: true,
                    maxClusterRadius: 60
                });

                // B. Event Hover pada Cluster (FITUR BARU)
                newLayer.on('clustermouseover', function (a) {
                    const markers = a.layer.getAllChildMarkers();
                    const routeCounts = {};
                    
                    // Hitung jumlah per rute dalam cluster ini
                    markers.forEach(m => {
                        const r = m.kddkData.route || '??';
                        routeCounts[r] = (routeCounts[r] || 0) + 1;
                    });

                    // Susun HTML Tooltip
                    let tooltipContent = '<div class="text-xs font-sans min-w-[100px]">';
                    tooltipContent += '<div class="font-bold border-b border-gray-400 mb-1 pb-1">Isi Cluster Ini:</div>';
                    
                    // Sortir rute agar rapi
                    Object.keys(routeCounts).sort().forEach(r => {
                        tooltipContent += `<div class="flex justify-between items-center">
                            <span class="font-mono text-indigo-200 font-bold mr-2">Rute ${r}</span>
                            <span class="bg-white text-black px-1 rounded text-[9px] font-bold">${routeCounts[r]}</span>
                        </div>`;
                    });
                    tooltipContent += '</div>';

                    // Tampilkan Tooltip
                    L.tooltip({
                        direction: 'top',
                        className: 'bg-gray-900 text-white border border-gray-600 shadow-xl opacity-90 p-2 rounded',
                        offset: [0, -10]
                    })
                    .setContent(tooltipContent)
                    .setLatLng(a.latlng)
                    .addTo(rbmMap);
                });

                // Hapus tooltip saat mouse pergi
                newLayer.on('clustermouseout', function () {
                    rbmMap.eachLayer(function (layer) {
                        if (layer instanceof L.Tooltip) rbmMap.removeLayer(layer);
                    });
                });


                // C. Hitung Pusat & Loop Marker
                let sumLat = 0, sumLng = 0, validCount = 0;
                points.forEach(p => {
                    const lat = parseFloat(p.lat); const lng = parseFloat(p.lng);
                    if (lat && lng) { sumLat += lat; sumLng += lng; validCount++; }
                });
                const centerPoint = validCount > 0 ? L.latLng(sumLat/validCount, sumLng/validCount) : null;
                const ANOMALY_THRESHOLD = 2000;

                const colorMap = {
                    'A': 'text-green-800 border-green-600',
                    'B': 'text-blue-800 border-blue-600',
                    'C': 'text-red-800 border-red-600',
                    'D': 'text-yellow-800 border-yellow-600',
                    'E': 'text-purple-800 border-purple-600',
                    'F': 'text-pink-800 border-pink-600',
                    'G': 'text-indigo-800 border-indigo-600',
                    'H': 'bg-teal-100 text-teal-800 border-teal-200',
                    'I': 'bg-orange-100 text-orange-800 border-orange-200',
                    'J': 'bg-lime-100 text-lime-800 border-lime-200',
                };
                
                points.forEach(pt => {
                    // Parsing Kode Rute (Digit 6 & 7 dari KDDK)
                    // Asumsi KDDK: UP3(1) ULP(1) SUB(1) AREA(2) RUTE(2) ...
                    // Contoh: A1A RB A1 ... -> Index 5 dan 6 adalah "A1"
                    // Pastikan pt.kddk dikirim dari controller (sudah kita cek ada)
                    const routeCodeStr = pt.kddk ? pt.kddk.substring(5, 7) : '??';
                    const dayChar = pt.seq ? pt.seq.charAt(0) : 'A'; 
                    let colorClass = colorMap[dayChar] || 'text-gray-800 border-gray-600';
                    let isAnomaly = false;

                    if (centerPoint) {
                        const dist = centerPoint.distanceTo([pt.lat, pt.lng]);
                        if (dist > ANOMALY_THRESHOLD) {
                            isAnomaly = true;
                            colorClass = 'marker-outlier animate-marker-pulse';
                        }
                    }

                    const iconHtml = `<div class="flex items-center justify-center w-6 h-6 bg-white border-2 ${colorClass} rounded-full text-[9px] font-bold shadow-sm" style="opacity: 0.9;">${pt.seq}</div>`;
                    const icon = L.divIcon({ className: 'custom-map-marker', html: iconHtml, iconSize: [24, 24], iconAnchor: [12, 12] });
                    
                    const marker = L.marker([pt.lat, pt.lng], { icon: icon });
                    
                    marker.kddkData = { 
                        isAnomaly: isAnomaly,
                        route: routeCodeStr,
                        idpel: pt.idpel,
                        fullKddk: pt.kddk 
                    };
                    marker.bindPopup(pt.info);
                    marker.on('click', function(e) {
                        console.log("Marker diklik!", { 
        mode: window.isReorderMode, 
        idpel: this.kddkData.idpel 
    });
                        // Cek apakah sedang dalam Mode Reorder (Variabel Global)
                        if (window.isReorderMode === true) { 
                            // Tutup popup agar tidak menghalangi pandangan
                            marker.closePopup(); 
                            
                            // Panggil fungsi logika reorder
                            handleMarkerClickReorder(marker); 
                        }
                    });
                    newLayer.addLayer(marker);
                });

                // Simpan ke Variabel Global & Tampilkan
                areaLayers[areaCode] = newLayer;
                rbmMap.addLayer(newLayer);

                if (callback) callback();
                else fitBoundsToLayer(newLayer); // Zoom ke area ini
                
                updateTotalPoints();
                updateMapTitleWrapper();
            })
            .catch(err => console.error("Map Error:", err));
    }

    function removeAreaMap(areaCode) {
        if (areaLayers[areaCode] && rbmMap) {
            rbmMap.removeLayer(areaLayers[areaCode]); // Hapus visual
            updateTotalPoints();
            updateMapTitleWrapper();
        }
    }

    function fitBoundsToLayer(layer) {
        if (layer && layer.getBounds().isValid()) {
            rbmMap.fitBounds(layer.getBounds().pad(0.1));
        }
    }

    function updateTotalPoints() {
        const countSpan = document.getElementById('map-count');
        const alertBox = document.getElementById('anomaly-alert');
        const alertCount = document.getElementById('anomaly-count');
        
        if (!countSpan) return;
        
        let total = 0;
        let totalAnomalies = 0;

        // Hitung dari semua layer AREA yang sedang aktif
        Object.values(areaLayers).forEach(layer => {
            if (rbmMap && rbmMap.hasLayer(layer)) {
                total += layer.getLayers().length;
                layer.eachLayer(m => {
                    if (m.kddkData && m.kddkData.isAnomaly) totalAnomalies++;
                });
            }
        });

        countSpan.textContent = total + ' Titik';

        if (alertBox && alertCount) {
            if (totalAnomalies > 0) {
                alertCount.textContent = totalAnomalies;
                alertBox.classList.remove('hidden');
            } else {
                alertBox.classList.add('hidden');
            }
        }
    }

    function updateMapTitleWrapper() {
        const titleEl = document.getElementById('map-context-title');
        let activeCount = 0;
        let lastArea = '';
        
        Object.keys(areaLayers).forEach(code => {
            if (rbmMap && rbmMap.hasLayer(areaLayers[code])) {
                activeCount++;
                lastArea = code;
            }
        });

        if (titleEl) {
            if (activeCount === 0) titleEl.textContent = "Pilih Area/Rute";
            else if (activeCount === 1) titleEl.textContent = `Area ${lastArea}`;
            else titleEl.textContent = `${activeCount} Area Ditampilkan`;
        }
    }


    // ============================================================
    // 5. HELPER AJAX & MODAL SUKSES (PREMIUM)
    // ============================================================

    // 1. Tampilkan Modal
    function showGenericSuccess(message) {
        const modal = document.getElementById('modal-success-generic');
        const msgEl = document.getElementById('generic-success-message');
        if (modal && msgEl) {
            msgEl.textContent = message;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            const okBtn = modal.querySelector('button');
            if(okBtn) setTimeout(() => okBtn.focus(), 100);
        } else {
            alert(message); 
            refreshActiveTab();
        }
    }

    // 2. Tutup Modal & Refresh
    window.closeGenericSuccessModal = function() {
        const modal = document.getElementById('modal-success-generic');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        refreshActiveTab();
    }

    // 3. REFRESH LOGIC (DENGAN DETEKSI URL OTOMATIS & STATE RESTORE)
    function refreshActiveTab(successMessage = null) {
        if (typeof App === 'undefined' || !App.Utils || !App.Tabs) return;
        
        const activeTab = App.Utils.getActiveTabName();
        const activeContent = document.getElementById(`${activeTab}-content`);
        if (!activeContent) return;

        // A. DETEKSI URL YANG BENAR
        // Cek elemen unik untuk menentukan kita sedang di halaman mana
        const rbmForm = activeContent.querySelector('#rbm-form'); // Penanda Halaman Manage RBM
        const unitInput = activeContent.querySelector('input[name="unitup"]'); // Penanda Unit ID
        
        let refreshUrl = null;

        if (rbmForm && unitInput) {
            // KASUS 1: HALAMAN MANAGE RBM
            // Paksa refresh ke URL RBM, bukan URL Tab (Dashboard)
            refreshUrl = `/team/matrix-kddk/manage-rbm/${encodeURIComponent(unitInput.value)}`;
        } 
        else if (unitInput) {
            // KASUS 2: HALAMAN DETAIL GENERATOR
            const searchForm = activeContent.querySelector('form[action*="details"]');
            if (searchForm) {
                refreshUrl = searchForm.action;
                if (window.location.search && !refreshUrl.includes('?')) {
                    refreshUrl += window.location.search;
                }
            } else {
                 refreshUrl = `/team/matrix-kddk/details/${encodeURIComponent(unitInput.value)}`;
            }
        }
        else {
             // KASUS 3: HALAMAN INDEX / LAINNYA
             const tabBtn = document.querySelector(`.tab-button[data-tab-name="${activeTab}"]`);
             refreshUrl = tabBtn ? tabBtn.dataset.url : window.location.href;
        }

        // B. SIMPAN STATE SEBELUM REFRESH
        const state = {
            scroll: 0,
            openedIds: [],
            isMapHidden: false
        };

        // 1. Simpan Posisi Scroll
        const scrollContainer = activeContent.querySelector('.overflow-y-auto');
        if (scrollContainer) state.scroll = scrollContainer.scrollTop;

        // 2. Simpan Accordion Terbuka
        const openElements = activeContent.querySelectorAll('div[id^="area-"]:not(.hidden), div[id^="d6-"]:not(.hidden), div[id^="route-"]:not(.hidden)');
        openElements.forEach(el => state.openedIds.push(el.id));

        // 3. Simpan Status Hide Map (Jika di Manage RBM)
        const panelMap = activeContent.querySelector('#panel-map');
        if (panelMap && !panelMap.classList.contains('md:block')) {
            state.isMapHidden = true;
        }

        // C. EKSEKUSI REFRESH & RESTORE
        if(refreshUrl) {
            let bustUrl = new URL(refreshUrl, window.location.origin);
            bustUrl.searchParams.set('_cb', new Date().getTime());
            
            App.Tabs.loadTabContent(activeTab, bustUrl.toString(), () => {
                
                // RESTORE STATE SETELAH LOAD
                const newContent = document.getElementById(`${activeTab}-content`);
                if (!newContent) return;

                // 1. Buka Kembali Accordion
                state.openedIds.forEach(id => {
                    const el = newContent.querySelector(`#${id}`);
                    if (el) {
                        el.classList.remove('hidden');
                        const toggleBtn = newContent.querySelector(`[data-target="${id}"]`);
                        if (toggleBtn) {
                            const icon = toggleBtn.querySelector('.icon-chevron, .icon-chevron-sub, .icon-chevron-d6');
                            if (icon) icon.classList.add('rotate-180');
                        }
                    }
                });

                // 2. Restore Hide Map (Jika tadi disembunyikan)
                if (state.isMapHidden) {
                    const newPanelList = newContent.querySelector('#panel-list');
                    const newPanelMap = newContent.querySelector('#panel-map');
                    const newToggleBtn = newContent.querySelector('[data-action="toggle-map-layout"]');

                    if (newPanelList && newPanelMap) {
                        newPanelMap.classList.remove('md:block');
                        newPanelMap.classList.add('hidden');
                        
                        newPanelList.classList.remove('md:w-[450px]');
                        newPanelList.classList.add('w-full');
                        
                        // Aktifkan Grid Mode
                        newContent.querySelectorAll('.routes-grid-container').forEach(el => {
                           el.classList.remove('space-y-0');
                           el.classList.add('grid', 'grid-cols-1', 'md:grid-cols-2', 'xl:grid-cols-3', 'gap-4', 'p-2');
                        });

                        if (newToggleBtn) {
                            const txt = newToggleBtn.querySelector('.text-btn');
                            const ico = newToggleBtn.querySelector('i');
                            if(txt) txt.textContent = "Show Map";
                            if(ico) ico.className = "fas fa-columns mr-1.5 icon-map";
                        }
                    }
                }

                // 3. Kembalikan Posisi Scroll (Terakhir)
                const newScroll = newContent.querySelector('.overflow-y-auto');
                if (newScroll) newScroll.scrollTop = state.scroll;

                if (successMessage) {
                    const notifContainer = newContent.querySelector('#kddk-notification-container');
                    if (notifContainer) {
                        notifContainer.innerHTML = `
                            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 flex items-center shadow-sm animate-fade-in-down" role="alert">
                                <i class="fas fa-check-circle mr-2 text-xl"></i>
                                <span class="block sm:inline font-bold">${successMessage}</span>
                                <button onclick="this.parentElement.remove();" class="absolute top-0 bottom-0 right-0 px-4 py-3">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        `;
                        // Auto hide dalam 5 detik
                        setTimeout(() => { if(notifContainer.firstChild) notifContainer.firstChild.remove(); }, 5000);
                    } else {
                        // Fallback jika container hilang
                        alert(successMessage);
                    }
                }
            });
        }
    }

    // 4. Eksekutor AJAX
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
    
    // 5. Wrapper Actions
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

    // [FUNGSI BARU] MENANGANI SUBMIT FORM UTAMA (TOMBOL SIMPAN TOOLBAR)
    function handleMainFormSubmit(form) {
        // 1. Cari tombol submit di DALAM form
        let submitBtn = form.querySelector('button[type="submit"]');

        // 2. Jika tidak ketemu, cari tombol di LUAR form yang terhubung (via attribute form="id")
        if (!submitBtn && form.id) {
            submitBtn = document.querySelector(`button[form="${form.id}"]`);
        }

        // Safety check jika tombol benar-benar tidak ada
        if (!submitBtn) {
            console.error("Tombol Submit tidak ditemukan!");
            return; 
        }

        const originalText = submitBtn.innerHTML;
        
        // Ubah tombol jadi Loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        const formData = new FormData(form);
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            }
        })
        .then(res => res.json())
        .then(data => {
            // Kembalikan tombol
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;

            if (data.success) {
                // PANGGIL REFRESH DENGAN PESAN SUKSES
                refreshActiveTab(data.message);
            } else {
                alert('Gagal: ' + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            alert('Terjadi kesalahan server.');
        });
    }

    // ============================================================
    // 6. GENERATOR KDDK (CUSTOM SUBMIT)
    // ============================================================
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
                const processedCount = selectionState.items.size;
                window.closeKddkModal();
                // Reset State
                selectionState.items.clear();
                toggleGroupButton();
                document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
                const checkAll = document.getElementById('check-all-rows');
                if(checkAll) checkAll.checked = false;
                
                showSuccessModal(data,processedCount); 
            } else {
                alert('Gagal: ' + data.message);
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            if (err.errors) alert('Validasi Gagal.'); 
            else alert('Terjadi kesalahan sistem.');
        });
    }

    function showSuccessModal(data, countOverride = null) {
        const modal = document.getElementById('modal-success-generator');
        if (!modal) { alert(data.message); return; }
        
        document.getElementById('success-modal-message').textContent = data.message;
        const previewCode = document.getElementById('final_kddk_preview').value;
        const totalCount = countOverride !== null ? countOverride : document.getElementById('count-selected').textContent;

        const codeEl = document.getElementById('success-start-code');
        const countEl = document.getElementById('success-total-count');
        if(codeEl) codeEl.textContent = previewCode.substring(0, 12) + '...';
        if(countEl) countEl.textContent = totalCount + ' Pelanggan';

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    
    // Tutup Modal Generator Sukses (Refresh Logic juga)
    window.closeSuccessModal = function() {
        const modal = document.getElementById('modal-success-generator');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        refreshActiveTab();
    }

    // ============================================================
    // 7. DRAG & DROP LOGIC
    // ============================================================
    let draggedIdpel = null;
    let originKddk = null;
    const removeZone = document.getElementById('remove-drop-zone');

    document.addEventListener('dragstart', function(e) {
        const row = e.target.closest('.draggable-idpel');
        if (row) {
            draggedIdpel = row.dataset.idpel;
            originKddk = row.dataset.originPrefix; 
            row.classList.add('opacity-50', 'bg-yellow-100'); 
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', draggedIdpel);
            if(removeZone) {
                removeZone.classList.remove('hidden');
                setTimeout(() => { removeZone.classList.remove('opacity-0', 'translate-y-10'); }, 10);
            }
        }
    });

    document.addEventListener('dragend', function(e) {
        const row = e.target.closest('.draggable-idpel');
        if (row) row.classList.remove('opacity-50', 'bg-yellow-100');
        document.querySelectorAll('.kddk-drop-zone').forEach(zone => {
            zone.classList.remove('bg-green-50', 'border-green-500', 'border-2');
            const indicator = zone.querySelector('.drop-indicator');
            if(indicator) indicator.classList.add('hidden');
        });
        if(removeZone) {
            removeZone.classList.add('opacity-0', 'translate-y-10');
            setTimeout(() => removeZone.classList.add('hidden'), 300);
        }
        // Reset variabel agar bersih
        draggedIdpel = null;
        originKddk = null;
    });

    document.addEventListener('dragover', function(e) {
        e.preventDefault(); 
        const trashTarget = e.target.closest('.kddk-remove-zone');
        if (trashTarget) {
            e.dataTransfer.dropEffect = 'move';
            trashTarget.classList.add('scale-105', 'bg-red-200');
            return;
        }
        const dropZone = e.target.closest('.kddk-drop-zone');
        const targetRow = e.target.closest('.draggable-idpel');

        if (dropZone && draggedIdpel) {
            const targetPrefix = dropZone.dataset.routePrefix;

            // KASUS A: PINDAH RUTE (Different Group)
            if (targetPrefix !== originKddk) {
                e.dataTransfer.dropEffect = 'move';
                dropZone.classList.add('bg-green-50', 'border-green-500');
                const indicator = dropZone.querySelector('.drop-indicator');
                if(indicator) indicator.classList.remove('hidden');
            }
            // KASUS B: REORDER (Same Group)
             else if (targetRow && targetRow.dataset.idpel !== draggedIdpel) {
                 e.dataTransfer.dropEffect = 'move';
                 // Visual Guide: Garis Biru di atas baris target
                 targetRow.style.borderTop = "2px solid #4f46e5"; 
             }
        }
    });

    document.addEventListener('dragleave', function(e) {
        const trashTarget = e.target.closest('.kddk-remove-zone');
        if (trashTarget) trashTarget.classList.remove('scale-105', 'bg-red-200');

        // Hapus Visual Guide Reorder
        const targetRow = e.target.closest('.draggable-idpel');
        if (targetRow) targetRow.style.borderTop = "";

        const dropZone = e.target.closest('.kddk-drop-zone');
        if (dropZone && !dropZone.contains(e.relatedTarget)) {
            dropZone.classList.remove('bg-green-50', 'border-green-500');
            const indicator = dropZone.querySelector('.drop-indicator');
            if(indicator) indicator.classList.add('hidden');
        }
    });

    document.addEventListener('drop', function(e) {
        e.preventDefault();

        // Reset Visuals
        document.querySelectorAll('.draggable-idpel').forEach(r => r.style.borderTop = "");

        // 1. Drop di Trash
        const trash = e.target.closest('.kddk-remove-zone');
        if (trash && draggedIdpel) { 
             const onConfirm = () => performRemoveIdpel(draggedIdpel);
             if(typeof App!=='undefined'&&App.Utils) App.Utils.showCustomConfirm('Hapus?', `Keluarkan pelanggan ${draggedIdpel}?`, onConfirm);
             else if(confirm(`Hapus ${draggedIdpel}?`)) onConfirm();
             return; 
        }

        const dropZone = e.target.closest('.kddk-drop-zone');
        const targetRow = e.target.closest('.draggable-idpel');

        if (dropZone && draggedIdpel) {
            const targetPrefix = dropZone.dataset.routePrefix;
            
            // KASUS A: PINDAH RUTE
            if (targetPrefix !== originKddk) {
                performMoveIdpel(draggedIdpel, targetPrefix);
            } 
            // KASUS B: REORDER (Urutkan Ulang dalam Rute Sama)
            else if (targetRow && targetRow.dataset.idpel !== draggedIdpel) {
                const targetIdpel = targetRow.dataset.idpel;
                performReorderIdpel(draggedIdpel, targetIdpel, targetPrefix);
            }
        }
    });

    // ============================================================
    // 8. LOGIKA BULK ACTION & UTILS LAINNYA
    // ============================================================

    function updateBulkUI() {
        const totalChecked = document.querySelectorAll('.select-item-row:checked').length;
        const bar = document.getElementById('bulk-action-bar');
        const countSpan = document.getElementById('bulk-count');
        if (totalChecked > 0) { if(bar) bar.classList.remove('hidden'); if(countSpan) countSpan.textContent = totalChecked; }
        else if(bar) bar.classList.add('hidden');
    }

    // ============================================================
    // 9. LOGIKA PEMICU KLIK KANAN (CONTEXT MENU)
    // ============================================================
    document.addEventListener('contextmenu', function(e) {
        const row = e.target.closest('.draggable-idpel');
        
        if (row) {
            e.preventDefault(); // Matikan menu bawaan browser
            
            const contextMenu = document.getElementById('custom-context-menu');
            
            if (contextMenu) {
                const idpel = row.dataset.idpel;
                const idDisplay = document.getElementById('ctx-header');
                const hiddenId = document.getElementById('ctx-selected-idpel');

                // Isi data ke menu
                if(idDisplay) idDisplay.textContent = `Pelanggan: ${idpel}`;
                if(hiddenId) hiddenId.value = idpel;

                // Tampilkan Menu
                contextMenu.classList.remove('hidden');
                
                // Atur Posisi agar tidak keluar layar
                let x = e.clientX;
                let y = e.clientY;
                const menuWidth = 224; // w-56
                const menuHeight = 150; 
                
                if (x + menuWidth > window.innerWidth) x -= menuWidth;
                if (y + menuHeight > window.innerHeight) y -= menuHeight;

                contextMenu.style.left = `${x}px`;
                contextMenu.style.top = `${y}px`;
            }
        }
        
    });

    // ============================================================
    // 9. KEYBOARD SHORTCUTS (DEBUG VERSION)
    // ============================================================
    document.addEventListener('keydown', function(e) {
        
        // Cek elemen apa yang sedang fokus
        const activeTag = document.activeElement.tagName;
        const isInput = activeTag === 'INPUT' || activeTag === 'TEXTAREA' || activeTag === 'SELECT';
        
        // 1. ESCAPE (Tutup Modal / Clear Selection)
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.fixed.flex:not(.hidden)');
            if (openModals.length > 0) {
                e.preventDefault();
                openModals.forEach(m => { m.classList.add('hidden'); m.classList.remove('flex'); });
                return;
            }
            if (!isInput) {
                window.clearBulkSelection();
                const searchInput = document.getElementById('kddk-search-input');
                if (searchInput) {
                    searchInput.value = '';
                    if(typeof handleKddkSearch === 'function') handleKddkSearch('');
                    searchInput.blur(); // Lepas fokus
                }
            }
        }

        // 2. CTRL + F (Fokus Pencarian)
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') {
            e.preventDefault();
            const searchInput = document.getElementById('kddk-search-input');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            } else {
                console.warn('Gagal: Input pencarian (kddk-search-input) tidak ditemukan.');
            }
        }

        // 3. CTRL + S (Simpan Form)
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            const saveBtn = document.querySelector('button[form="rbm-form"]');
            if (saveBtn) {
                console.log('Action: Clicking Save Button...');
                saveBtn.click();
            } else {
                console.warn('Gagal: Tombol Simpan (form="rbm-form") tidak ditemukan.');
            }
        }

        // 4. CTRL + P (Cetak Lembar Kerja)
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'p') {
            e.preventDefault();
            const printBtn = document.getElementById('btn-print-worksheet');
            if (printBtn) {
                printBtn.click(); // Klik tombol aslinya biar logic-nya jalan
            } else if (typeof window.printWorksheetCheck === 'function') {
                window.printWorksheetCheck();
            } else {
                console.warn('Gagal: Fungsi Cetak tidak ditemukan.');
            }
        }

        // 5. DELETE (Hapus Massal)
        if (e.key === 'Delete' && !isInput) {
            const checkedCount = document.querySelectorAll('.select-item-row:checked').length;
            if (checkedCount > 0) {
                e.preventDefault();
                window.executeBulkRemove();
            } else {
                console.log('Info: Tidak ada item yang dicentang untuk dihapus.');
            }
        }

    });

    window.clearBulkSelection = function() {
        document.querySelectorAll('.select-item-row').forEach(cb => cb.checked = false);
        document.querySelectorAll('.select-all-route').forEach(cb => cb.checked = false);
        updateBulkUI();
    }
    
    window.toggleGroupButton = function() {
        const count = selectionState.items.size;
        const btn = document.getElementById('btn-group-kddk');
        const countSpan = document.getElementById('count-selected');
        if(btn) {
            if(count>0) { btn.classList.remove('hidden'); btn.innerHTML=`<i class="fas fa-layer-group mr-2"></i> Bentuk Group (${count})`; }
            else btn.classList.add('hidden');
        }
        if(countSpan) countSpan.textContent = count;
    }

    window.openKddkModal = function() {
        const selectedIds = Array.from(selectionState.items.keys());

        if (selectedIds.length === 0) { 
            alert("Pilih atau Upload minimal satu pelanggan."); 
            return; 
        }

        const container = document.getElementById('hidden-inputs-container');
        if(container) {
            container.innerHTML = '';

            selectedIds.forEach(id => {
                const i = document.createElement('input'); 
                i.type='hidden'; 
                i.name='selected_idpels[]'; // Ini yang dikirim ke Controller
                i.value=id; 
                container.appendChild(i);
            });
        }
        const modal = document.getElementById('modal-create-kddk');
        if(modal) { 
            modal.classList.remove('hidden'); 
            modal.classList.add('flex'); 
            
            // Update Label Jumlah di Modal
            const countLabel = document.getElementById('count-selected');
            if(countLabel) countLabel.textContent = selectedIds.length; // Tulis "200"

            if(typeof updateSequenceAndGenerate === 'function') updateSequenceAndGenerate(); 
        }
    }
    
    window.closeKddkModal = function() {
        const modal = document.getElementById('modal-create-kddk');
        if(modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
    }
    
    window.confirmGrouping = function() {
        const total = selectionState.items.size;
        if (total === 0) { alert("Pilih minimal satu pelanggan."); return; }
        const rekap = {};
        selectionState.items.forEach((val, key) => {
            const jenis = val.jenis || 'LAINNYA';
            if (!rekap[jenis]) rekap[jenis] = 0;
            rekap[jenis]++;
        });
        document.getElementById('confirm-total-count').textContent = total;
        const listEl = document.getElementById('confirm-detail-list');
        listEl.innerHTML = '';
        for (const [jenis, count] of Object.entries(rekap)) {
            const li = document.createElement('li');
            li.className = "flex justify-between items-center border-b border-gray-200 dark:border-gray-600 pb-1 last:border-0";
            li.innerHTML = `<span class="font-medium text-gray-700 dark:text-gray-300">${jenis}</span><span class="bg-indigo-100 text-indigo-800 px-2 py-0.5 rounded text-xs font-bold">${count} Plg</span>`;
            listEl.appendChild(li);
        }
        const modal = document.getElementById('modal-confirm-selection');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    window.proceedToGenerator = function() {
        document.getElementById('modal-confirm-selection').classList.add('hidden');
        document.getElementById('modal-confirm-selection').classList.remove('flex');
        window.openKddkModal();
    }

    window.openBulkMoveModal = function() {
        const moveModal = document.getElementById('modal-move-route');
        const count = document.getElementById('bulk-count').textContent;
        const titleEl = moveModal.querySelector('h3');
        const idpelEl = document.getElementById('move-modal-idpel');
        titleEl.textContent = `Pindahkan ${count} Pelanggan`;
        idpelEl.textContent = 'Multi Selection';
        idpelEl.dataset.mode = 'bulk';
        document.getElementById('move-area').value = "";
        const routeSelect = document.getElementById('move-route-select');
        routeSelect.innerHTML = '<option value="">-- Pilih Area Dulu --</option>';
        routeSelect.disabled = true;
        moveModal.classList.remove('hidden');
        moveModal.classList.add('flex');
    }

    window.executeBulkRemove = function() {
        const checked = document.querySelectorAll('.select-item-row:checked');
        const ids = Array.from(checked).map(cb => cb.value);
        if (ids.length === 0) return;
        const count = ids.length;
        const url = document.getElementById('bulk-remove-route').value;
        const onConfirmAction = () => { executeAjax(url, { idpels: ids }); window.clearBulkSelection(); };
        
        const title = 'Keluarkan Pelanggan?';
        const message = `Anda akan mengeluarkan <strong class="text-red-600 text-xl mx-1">${count}</strong> pelanggan terpilih.<br><span class="text-xs mt-2 block bg-red-50 text-red-600 p-2 rounded">Data akan kembali ke antrian detail.</span>`;
        
        if (typeof App !== 'undefined' && App.Utils) App.Utils.showCustomConfirm(title, message, onConfirmAction);
        else if(confirm(`Yakin hapus ${ids.length}?`)) onConfirmAction();
    }

    window.executeMoveRoute = function() {
        const moveModal = document.getElementById('modal-move-route');
        const idpelEl = document.getElementById('move-modal-idpel');
        const isBulk = idpelEl.dataset.mode === 'bulk';
        const area = document.getElementById('move-area').value;
        const route = document.getElementById('move-route-select').value;
        const unitPrefixInput = document.getElementById('ctx-unit-prefix'); 
        const unitPrefix = unitPrefixInput ? unitPrefixInput.value : ''; 
        if (!area || !route) { alert("Harap pilih Area dan Rute tujuan."); return; }
        const sub = 'A'; 
        const targetPrefix = `${unitPrefix}${sub}${area}${route}`;

        if (isBulk) {
            const checked = document.querySelectorAll('.select-item-row:checked');
            const ids = Array.from(checked).map(cb => cb.value);
            const url = document.getElementById('bulk-move-route').value;
            executeAjax(url, { idpels: ids, target_kddk: targetPrefix });
            window.clearBulkSelection();
        } else {
            const idpel = document.getElementById('ctx-selected-idpel').value;
            performMoveIdpel(idpel, targetPrefix);
        }
        moveModal.classList.add('hidden');
        moveModal.classList.remove('flex');
        idpelEl.dataset.mode = '';
    }

    //Tutup Dropdown saat Scroll (Agar tidak melayang aneh)
    window.addEventListener('scroll', function() {
        const dropdown = document.getElementById('export-dropdown-menu');
        if (dropdown && !dropdown.classList.contains('hidden')) {
            dropdown.classList.add('hidden');
        }
    }, true);

    // Generator Internal Helper
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
             const labelText = selectedOption.dataset.label || selectedOption.text;
             areaLabelDisplay.textContent = labelText;
        } else if (areaLabelDisplay) areaLabelDisplay.textContent = '';
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
            } catch(e) {}
        }
    }
    
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
                    routeSelect.disabled = false; 
                    routes.forEach(r => {
                        const opt = document.createElement('option');
                        opt.value = r.code;
                        opt.textContent = `${r.code} (${r.label})`;
                        routeSelect.appendChild(opt);
                    });
                }
            } catch(e) { }
        }
    }

    window.updateLabelDisplay = function() {
        const routeSelect = document.getElementById('part_rute');
        const routeLabelDisplay = document.getElementById('rute-label-display');
        if (routeSelect && routeLabelDisplay) {
            const selectedOption = routeSelect.options[routeSelect.selectedIndex];
            if (selectedOption && selectedOption.value) routeLabelDisplay.textContent = selectedOption.dataset.label || '';
            else routeLabelDisplay.textContent = '';
        }
        if (typeof updateSequenceAndGenerate === 'function') updateSequenceAndGenerate();
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

    function updateSequenceAndGenerate() { 
        generateFinalCode();
        const count = selectionState.items.size;
        const countDisplay = document.getElementById('count-display');
        if(countDisplay) countDisplay.textContent = count;
        
        const prefix7 = getPrefix7();
        const urutInput = document.getElementById('part_urut');
        const form = document.getElementById('kddk-generator-form');
        
        if (prefix7 && urutInput && form) {
            if (!form.dataset || !form.dataset.sequenceUrl) return;
            if (sequenceController) sequenceController.abort();
            sequenceController = new AbortController();
            urutInput.value = '...';
            const url = `${form.dataset.sequenceUrl}/${prefix7}`;
            
            fetch(url, { signal: sequenceController.signal }).then(r=>r.json()).then(d=>{
                if(d.sequence) {
                    urutInput.value = d.sequence;
                    const startSeq = parseInt(d.sequence);
                    const endSeq = startSeq + count - 1;
                    const pStart = document.getElementById('preview-start');
                    const pEnd = document.getElementById('preview-end');
                    const sisip = document.getElementById('part_sisip').value.padStart(2,'0');
                    if(pStart) pStart.textContent = `${prefix7}${d.sequence}${sisip}`;
                    if(pEnd) pEnd.textContent = `${prefix7}${endSeq.toString().padStart(3,'0')}${sisip}`;
                    generateFinalCode();
                }
            }).catch(e=>{});
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
        const hiddenPrefix = document.getElementById('hidden_prefix_code');
        const hiddenSisip = document.getElementById('hidden_sisipan');
        if(!preview || !btn) return;
        const up3 = elUp3 ? (elUp3.value || '_') : '_';
        const ulp = elUlp ? (elUlp.value || '_') : '_';
        const sub = elSub ? (elSub.value || '_') : '_';
        const area = elArea ? (elArea.value || '__') : '__';
        const rute = elRute ? (elRute.value || '__') : '__';
        const urut = elUrut ? (elUrut.value || '___') : '___';
        const sisipVal = (elSisip && elSisip.value ? elSisip.value : '00');
        const sisip = sisipVal.padStart(2,'0');
        const prefix7 = `${up3}${ulp}${sub}${area}${rute}`;
        const fullCode = `${prefix7}${urut}${sisip}`;
        preview.value = fullCode;
        if (hiddenPrefix) hiddenPrefix.value = prefix7;
        if (hiddenSisip) hiddenSisip.value = sisip;
        if (!fullCode.includes('_') && fullCode.length === 12 && !fullCode.includes('...')) {
            preview.classList.replace('border-indigo-100', 'border-green-500');
            preview.classList.replace('text-indigo-600', 'text-green-600');
            if(err) { err.textContent = "Format Valid ✅"; err.className = "text-xs text-center text-green-600 mt-1 h-4"; }
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            preview.classList.replace('border-green-500', 'border-indigo-100');
            preview.classList.replace('text-green-600', 'text-indigo-600');
            if(err) { err.textContent = "Lengkapi data..."; err.className = "text-xs text-center text-red-500 mt-1 h-4"; }
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }
    
    function handleKddkSearch(val) {
    const searchInput = document.getElementById('kddk-search-input');
    const searchResults = document.getElementById('search-results-dropdown');
    const resultsList = document.getElementById('search-results-list');
    const clearBtn = document.getElementById('clear-search-btn');
    let searchTimeout = null;

        if (searchInput) {
            // Event Ketik
            searchInput.addEventListener('input', function(e) {
                const keyword = e.target.value.trim();
                
                // Toggle tombol X
                if(keyword.length > 0) clearBtn.classList.remove('hidden');
                else clearBtn.classList.add('hidden');

                // Reset Timeout (Debounce)
                if (searchTimeout) clearTimeout(searchTimeout);

                if (keyword.length < 3) {
                    searchResults.classList.add('hidden');
                    return;
                }

                // Tunggu user berhenti mengetik 500ms
                searchTimeout = setTimeout(() => {
                    performServerSearch(keyword);
                }, 500);
            });

            // Event Tombol Clear
            if(clearBtn) {
                clearBtn.addEventListener('click', function() {
                    searchInput.value = '';
                    searchResults.classList.add('hidden');
                    clearBtn.classList.add('hidden');
                    // Opsional: Tutup semua accordion yang terbuka
                });
            }
        }

        function performServerSearch(keyword) {
            const url = document.getElementById('api-search-customer').value;
            resultsList.innerHTML = '<li class="p-3 text-center text-gray-500"><i class="fas fa-spinner fa-spin"></i> Mencari...</li>';
            searchResults.classList.remove('hidden');

            fetch(`${url}?keyword=${encodeURIComponent(keyword)}`)
                .then(res => res.json())
                .then(data => {
                    resultsList.innerHTML = '';
                    if (data.length === 0) {
                        resultsList.innerHTML = '<li class="p-3 text-center text-gray-500">Tidak ditemukan.</li>';
                        return;
                    }

                    data.forEach(item => {
                        const li = document.createElement('li');
                        li.className = "p-2 hover:bg-indigo-50 dark:hover:bg-gray-700 cursor-pointer flex justify-between items-center";
                        li.innerHTML = `
                            <div>
                                <div class="font-bold text-indigo-600">${item.idpel}</div>
                                <div class="text-xs text-gray-500 truncate w-48">${item.text.split(' - ')[1]}</div>
                            </div>
                            <span class="text-[10px] bg-gray-100 px-1 rounded border">Rute ${item.route_code}</span>
                        `;
                        
                        // KLIK HASIL -> BUKA RUTE OTOMATIS
                        li.addEventListener('click', () => {
                            navigateToCustomer(item);
                        });
                        
                        resultsList.appendChild(li);
                    });
                })
                .catch(err => {
                    console.error(err);
                    resultsList.innerHTML = '<li class="p-3 text-center text-red-500">Error memuat data.</li>';
                });
        }

        function navigateToCustomer(item) {
            // 1. Tutup Dropdown
            searchResults.classList.add('hidden');
            searchInput.value = item.idpel; // Set input jadi IDPEL terpilih

            // 2. Buka Area (Level 1)
            const areaBody = document.getElementById(item.target_area_id);
            if (areaBody && areaBody.classList.contains('hidden')) {
                const toggleBtn = document.querySelector(`[data-target="${item.target_area_id}"]`);
                if (toggleBtn) toggleBtn.click(); // Simulasi klik agar arrow berputar
            }

            // 3. Buka Digit 6 (Level 2)
            setTimeout(() => {
                const d6Body = document.getElementById(item.target_d6_id);
                if (d6Body && d6Body.classList.contains('hidden')) {
                    const toggleBtn = document.querySelector(`[data-target="${item.target_d6_id}"]`);
                    if (toggleBtn) toggleBtn.click();
                }

                // 4. Buka Rute (Level 3 - AJAX LOAD)
                setTimeout(() => {
                    const routeBody = document.getElementById(item.target_route_id);
                    // Jika rute tertutup, klik untuk buka (ini akan memicu Lazy Load)
                    if (routeBody && routeBody.classList.contains('hidden')) {
                        const toggleBtn = document.querySelector(`[data-target="${item.target_route_id}"]`);
                        if (toggleBtn) toggleBtn.click();
                    } else if (routeBody && !routeBody.classList.contains('hidden')) {
                        // Jika sudah terbuka tapi belum diload (kasus jarang)
                        if (routeBody.dataset.loaded === 'false') {
                            loadRouteTableData(item.target_route_id, item.area_code, item.route_code);
                        }
                    }

                    // 5. Scroll & Highlight (Tunggu AJAX Selesai - Estimasi 1 detik)
                    setTimeout(() => {
                        const row = document.querySelector(`tr[data-idpel="${item.idpel}"]`);
                        if (row) {
                            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            // Efek Kedip Kuning
                            row.classList.add('bg-yellow-200', 'dark:bg-yellow-900');
                            setTimeout(() => row.classList.remove('bg-yellow-200', 'dark:bg-yellow-900'), 3000);
                        } else {
                            // Retry sekali lagi jika loading lambat
                            setTimeout(() => {
                                const rowRetry = document.querySelector(`tr[data-idpel="${item.idpel}"]`);
                                if (rowRetry) {
                                    rowRetry.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    rowRetry.classList.add('bg-yellow-200');
                                }
                            }, 1000);
                        }
                    }, 800);

                }, 300); // Delay D6 -> Rute
            }, 300); // Delay Area -> D6
        }
    }

    function performReorderIdpel(idpel, targetIdpel, prefix) {
        const urlInput = document.getElementById('reorder-route');
        if (!urlInput) return;
        
        // Panggil Execute Ajax
        executeAjax(urlInput.value, { 
            idpel: idpel, 
            target_idpel: targetIdpel,
            route_prefix: prefix
        });
    }

    function loadRouteTableData(targetId, area, route) {
        const tbody = document.getElementById(`tbody-${targetId}`);
        const apiUrl = document.getElementById('api-route-table').value;
        const container = document.getElementById(targetId);

        if (!tbody || !apiUrl) return;

        // Tampilkan Spinner
        tbody.innerHTML = '<tr><td colspan="6" class="p-4 text-center text-indigo-500"><i class="fas fa-spinner fa-spin mr-2"></i> Memuat data...</td></tr>';

        // Fetch
        fetch(`${apiUrl}?area=${area}&route=${route}`)
            .then(res => res.text())
            .then(html => {
                tbody.innerHTML = html;
                container.dataset.loaded = "true"; // Tandai sudah load agar tidak load ulang
            })
            .catch(err => {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center text-red-500 text-xs p-2">Gagal memuat data.</td></tr>`;
                console.error(err);
            });
    }

    // ============================================================
    // 10. DASHBOARD CHARTS (APEXCHARTS HANDLER)
    // ============================================================
    
    // Simpan instance chart agar bisa di-destroy saat refresh
    let chartAreaInstance = null;
    let chartQualityInstance = null;

    function initDashboardCharts() {
        // 1. Cari Elemen Data
        const dataEl = document.getElementById('dashboard-analytics-data');
        if (!dataEl || typeof ApexCharts === 'undefined') return;

        // 2. Ambil Data dari Atribut Data
        // Gunakan try-catch untuk antisipasi JSON error
        let areaLabels = [], areaValues = [];
        try {
            areaLabels = JSON.parse(dataEl.dataset.areaLabels);
            areaValues = JSON.parse(dataEl.dataset.areaValues);
        } catch (e) { console.error("Gagal parsing data chart", e); }

        const validCount = parseInt(dataEl.dataset.qualityValid) || 0;
        const invalidCount = parseInt(dataEl.dataset.qualityInvalid) || 0;

        // 3. Render Chart 1: AREA (Bar)
        const areaChartEl = document.querySelector("#chart-area-bar");
        if (areaChartEl) {
            // Hapus chart lama jika ada (mencegah tumpuk)
            if (chartAreaInstance) chartAreaInstance.destroy();

            const optionsBar = {
                series: [{ name: 'Pelanggan', data: areaValues }],
                chart: { 
                    type: 'bar', 
                    height: 320, 
                    toolbar: { show: false }, 
                    fontFamily: 'Inter, sans-serif',
                    animations: { enabled: true }
                },
                plotOptions: { bar: { borderRadius: 4, horizontal: false, columnWidth: '50%' } },
                dataLabels: { enabled: false },
                stroke: { show: true, width: 2, colors: ['transparent'] },
                xaxis: { categories: areaLabels, title: { text: 'Kode Area' } },
                yaxis: { title: { text: 'Jumlah' } },
                fill: { opacity: 1 },
                colors: ['#6366f1'], // Indigo-500
                tooltip: { y: { formatter: function (val) { return val + " Plg" } } }
            };

            chartAreaInstance = new ApexCharts(areaChartEl, optionsBar);
            chartAreaInstance.render();
        }

        // 4. Render Chart 2: QUALITY (Donut)
        const qualityChartEl = document.querySelector("#chart-quality-pie");
        if (qualityChartEl) {
            if (chartQualityInstance) chartQualityInstance.destroy();

            const optionsPie = {
                series: [validCount, invalidCount],
                chart: { type: 'donut', height: 280, fontFamily: 'Inter, sans-serif' },
                labels: ['Ada Koordinat', 'Tanpa Koordinat'],
                colors: ['#10b981', '#ef4444'], // Emerald-500, Red-500
                dataLabels: { enabled: false },
                legend: { show: false }, // Kita pakai custom legend HTML
                plotOptions: {
                    pie: {
                        donut: {
                            size: '70%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'Total',
                                    formatter: function (w) {
                                        return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                    }
                                }
                            }
                        }
                    }
                }
            };

            chartQualityInstance = new ApexCharts(qualityChartEl, optionsPie);
            chartQualityInstance.render();
        }
    }

    window.openHistoryModal = function() {
        const modal = document.getElementById('modal-history');
        const content = document.getElementById('history-content');
        const url = document.getElementById('history-route').value;
        
        if (!modal || !content || !url) return;

        // Tampilkan Modal
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Reset konten ke loading
        content.innerHTML = '<div class="h-full flex items-center justify-center"><i class="fas fa-spinner fa-spin text-3xl text-indigo-300"></i></div>';

        // Fetch Data
        fetch(url)
            .then(res => res.text())
            .then(html => {
                content.innerHTML = html;
            })
            .catch(err => {
                content.innerHTML = '<div class="text-center text-red-500 mt-10">Gagal memuat riwayat.</div>';
                console.error(err);
            });
    }

    window.printWorksheetCheck = function() {
        // 1. Cari Area yang Terbuka (Header dengan panah berputar)
        const openAreaIcon = document.querySelector('.area-header .icon-chevron.rotate-180');
        
        if (!openAreaIcon) {
            alert("Harap BUKA salah satu AREA terlebih dahulu untuk mencetak.");
            return;
        }

        const areaHeader = openAreaIcon.closest('.area-header');
        const areaCode = areaHeader.dataset.areaCode;
        const areaTargetId = areaHeader.dataset.target; // ID konten area (misal: area-RB)
        const unitUp = document.querySelector('input[name="unitup"]').value;

        // 2. Cari Rute yang Terbuka DI DALAM Area tersebut
        // Kita cari di dalam container Area yang sedang aktif
        const areaContent = document.getElementById(areaTargetId);
        let routeParam = "";
        
        if (areaContent) {
            // Cari header rute yang panahnya berputar (artinya sedang dibuka)
            const openRouteIcon = areaContent.querySelector('.route-header .icon-chevron-sub.rotate-180');
            
            if (openRouteIcon) {
                const routeHeader = openRouteIcon.closest('.route-header');
                const routeCode = routeHeader.dataset.routeCode; // Ambil kode rute (misal: A1)
                
                // Tambahkan parameter route ke URL
                if (routeCode) {
                    routeParam = `&route=${routeCode}`;
                }
            }
        }

        // 3. Buka Halaman Cetak
        if (areaCode && unitUp) {
            const url = `/team/matrix-kddk/print-worksheet/${unitUp}?area=${areaCode}${routeParam}`;
            window.open(url, '_blank');
        }
    }

    // FUNGSI CEK EXPORT (FILTER SESUAI YANG DIBUKA)
    window.exportRbmCheck = function(format) {
        // 1. Ambil Unit ID
        const unitUp = document.querySelector('input[name="unitup"]').value;
        
        // 2. Cari Area yang Terbuka (Header dengan panah berputar)
        const openAreaIcon = document.querySelector('.area-header .icon-chevron.rotate-180');
        
        let urlParams = `?format=${format}`;
        
        if (openAreaIcon) {
            // Jika ada Area terbuka, ambil kodenya
            const areaHeader = openAreaIcon.closest('.area-header');
            const areaCode = areaHeader.dataset.areaCode;
            const areaTargetId = areaHeader.dataset.target;
            
            urlParams += `&area=${areaCode}`;

            // 3. Cek apakah ada Rute yang terbuka di dalam Area itu?
            const areaContent = document.getElementById(areaTargetId);
            if (areaContent) {
                const openRouteIcon = areaContent.querySelector('.route-header .icon-chevron-sub.rotate-180');
                if (openRouteIcon) {
                    const routeHeader = openRouteIcon.closest('.route-header');
                    const routeCode = routeHeader.dataset.routeCode;
                    urlParams += `&route=${routeCode}`;
                }
            }
        } else {
            // Jika tidak ada area terbuka, tanya user apakah mau download SEMUA?
            // Atau bisa diblokir jika data terlalu besar.
            if (!confirm("Anda tidak memilih Area/Rute spesifik. Download SELURUH data unit ini?")) {
                return;
            }
        }

        // 4. Buka URL Export
        // Base URL: /team/matrix-kddk/export-rbm/{unit}
        const baseUrl = `/team/matrix-kddk/export-rbm/${encodeURIComponent(unitUp)}`;
        window.open(baseUrl + urlParams, '_blank');
        
        // Tutup dropdown setelah klik
        const dropdown = document.getElementById('export-dropdown-menu');
        if(dropdown) dropdown.classList.add('hidden');
    }

    // ============================================================
    // 11. VISUAL REORDER LOGIC (FITUR BARU)
    // ============================================================
    
    window.isReorderMode = false;
    window.reorderList = []; // Menyimpan IDPEL yang diklik: ['5123..', '5124..']
    window.polylineLayer = null; // Garis penghubung
    window.currentRoutePrefix = null; // Menyimpan Prefix Rute yang sedang diedit (A1BRBAA)

    // A. Fungsi Memulai Mode Edit
    window.startVisualReorder = function() {
        // Validasi: Harus ada Rute yang terbuka (Header Accordion Aktif)
        const openRouteHeader = document.querySelector('.route-header .icon-chevron-sub.rotate-180');
        
        if (!openRouteHeader) {
            alert("Harap BUKA salah satu RUTE (Accordion) terlebih dahulu untuk mengedit.");
            return;
        }

        // [PERBAIKAN] JANGAN ambil prefix dulu. Kita ambil nanti saat klik pertama.
        window.currentRoutePrefix = null; 

        // UI Updates
        window.isReorderMode = true;
        window.reorderList = [];
        
        document.getElementById('btn-start-reorder').classList.add('hidden');
        document.getElementById('panel-reorder-actions').classList.remove('hidden');
        
        // Init Polyline Kosong
        if (window.polylineLayer) rbmMap.removeLayer(window.polylineLayer);
        window.polylineLayer = L.polyline([], {color: '#4f46e5', weight: 4, dashArray: '10, 10', opacity: 0.7}).addTo(rbmMap);
        
        alert("Mode Edit Aktif! Klik marker pertama untuk mengunci Rute.");
    }

    // B. Fungsi Saat Marker Diklik (Dipasang di listener map)
    function handleMarkerClickReorder(marker) {
        if (!window.isReorderMode) return;

        // 1. Debugging Data
        console.log("DATA MARKER:", marker.kddkData);

        const idpel = marker.kddkData.idpel;
        const fullKddk = marker.kddkData.fullKddk; 

        // 2. Validasi Keras
        if (!idpel) {
            alert("Error: IDPEL tidak ditemukan. Cek Controller 'getMapData'.");
            return;
        }
        if (!fullKddk) {
            alert("Error: KDDK (Prefix) tidak ditemukan. Cek Controller 'getMapData' pastikan return 'kddk'.");
            return;
        }

        // 3. Cek Duplikasi
        if (window.reorderList.includes(idpel)) return;

        // 4. Logika Penguncian Prefix (DIPERBAIKI)
        // Ambil 7 karakter pertama. Contoh: '18111A1'
        const thisMarkerPrefix = fullKddk.substring(0, 7); 

        if (window.currentRoutePrefix === null || window.currentRoutePrefix === undefined) {
            // KLIK PERTAMA: Kunci Prefix
            window.currentRoutePrefix = thisMarkerPrefix;
            console.log("PREFIX TERKUNCI:", window.currentRoutePrefix);
        } 
        else if (window.currentRoutePrefix !== thisMarkerPrefix) {
            // KLIK LANJUTAN: Cek Kesamaan
            alert(`JANGAN CAMPUR RUTE!\n\nPrefix Terkunci: ${window.currentRoutePrefix}\nPrefix Marker: ${thisMarkerPrefix}`);
            return; 
        }

        // 5. Visual Update
        window.reorderList.push(idpel);
        
        const latLng = marker.getLatLng();
        if (window.polylineLayer) window.polylineLayer.addLatLng(latLng);
        
        const seqNum = window.reorderList.length;
        const newIcon = L.divIcon({
            className: 'custom-reorder-marker',
            html: `<div class="flex items-center justify-center w-8 h-8 bg-indigo-600 text-white rounded-full text-sm font-bold border-2 border-white shadow-lg" style="z-index: 9999;">${seqNum}</div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });
        marker.setIcon(newIcon);
        
        document.getElementById('reorder-count').textContent = seqNum + " Item";
    }

    // C. Simpan Perubahan
    window.saveVisualReorder = function() {
        if (window.reorderList.length === 0) {
            alert("Belum ada urutan yang dibuat.");
            return;
        }

        if (!confirm(`Simpan urutan baru untuk ${window.reorderList.length} pelanggan ini?`)) return;

        const url = document.getElementById('api-save-sequence').value;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Tombol Loading
        const btnSave = document.querySelector('#panel-reorder-actions button.bg-green-600');
        const originalText = btnSave.innerHTML;
        btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        btnSave.disabled = true;

        fetch(url, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json', 
                'Accept': 'application/json', 
                'X-CSRF-TOKEN': csrfToken 
            },
            body: JSON.stringify({
                route_prefix: window.currentRoutePrefix, 
                ordered_idpels: window.reorderList
            })
        })
        .then(async res => {
            // 1. Tangkap Error 422 (Validasi Laravel)
            if (res.status === 422) {
                const errData = await res.json();
                let errMsg = "Gagal Validasi:\n";
                // Loop semua pesan error dari Laravel
                for (const [field, messages] of Object.entries(errData.errors)) {
                    errMsg += `- ${messages[0]}\n`;
                }
                throw new Error(errMsg);
            }

            // 2. Tangkap Error Lain (500, 403, dll)
            if (!res.ok) {
                const errorData = await res.json().catch(() => ({})); 
                const errorMessage = errorData.message || res.statusText || "Server Error";
                throw new Error(errorMessage);
            }

            return res.json();
        })
        .then(data => {
            if (data.success) {
                alert("Berhasil! " + data.message);
                cancelVisualReorder(); 
                
               const openRouteHeader = document.querySelector('.route-header .icon-chevron-sub.rotate-180');if (openRouteHeader) {
                    const headerEl = openRouteHeader.closest('.route-header');
                    const targetId = headerEl.dataset.target;     // ID div tabel (route-18111A1-A1)
                    const areaCode = headerEl.dataset.areaCode;
                    const routeCode = headerEl.dataset.routeCode;
                    
                    // Panggil fungsi load tabel yang sudah ada di matrix-handler.js
                    // Ini akan me-request ulang HTML tabel via AJAX tanpa reload halaman
                    if (typeof loadRouteTableData === 'function') {
                        // Set atribut loaded ke false dulu biar dipaksa reload
                        const contentDiv = document.getElementById(targetId);
                        if(contentDiv) contentDiv.dataset.loaded = "false";
                        
                        // Load ulang
                        loadRouteTableData(targetId, areaCode, routeCode);
                    }
                }
                
            } else {
                throw new Error(data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert(err.message); // Tampilkan pesan error yang spesifik
        })
        .finally(() => {
            btnSave.innerHTML = originalText;
            btnSave.disabled = false;
        });
    }

    // D. Batal / Keluar
    window.cancelVisualReorder = function() {
        window.isReorderMode = false;
        window.reorderList = [];
        window.currentRoutePrefix = null;
        
        // Hapus garis
        if (window.polylineLayer) {
            rbmMap.removeLayer(window.polylineLayer);
            window.polylineLayer = null;
        }

        // Reset UI Button
        document.getElementById('btn-start-reorder').classList.remove('hidden');
        document.getElementById('panel-reorder-actions').classList.add('hidden');
        document.getElementById('reorder-count').textContent = "0";

        // Refresh Peta (Reload Layer Area agar icon marker kembali normal)
        // Cari area code yang aktif
        const openRouteHeader = document.querySelector('.route-header .icon-chevron-sub.rotate-180');
        if(openRouteHeader) {
            const areaCode = openRouteHeader.closest('.route-header').dataset.areaCode;
            // Force reload map
            if (areaLayers[areaCode]) {
                rbmMap.removeLayer(areaLayers[areaCode]);
                delete areaLayers[areaCode]; // Hapus cache agar reload fresh
            }
            loadAreaMap(areaCode); 
        }
    }

    window.addEventListener('map:focus', function(e) {
    const { lat, lng } = e.detail;
    
    // Pastikan objek peta (rbmMap atau map) tersedia
    // Sesuaikan nama variabel peta Anda (misal: window.rbmMap atau window.map)
    const targetMap = window.rbmMap || window.map; 
    
    if (targetMap && lat && lng) {
        // Efek Terbang (FlyTo) yang halus
        targetMap.flyTo([lat, lng], 18, {
            animate: true,
            duration: 1.5
        });

        // Opsional: Buka Popup Marker di lokasi tersebut jika ada
        targetMap.eachLayer(layer => {
            if (layer instanceof L.Marker) {
                const lLat = layer.getLatLng().lat;
                const lLng = layer.getLatLng().lng;
                // Cek presisi koordinat (toleransi kecil)
                if (Math.abs(lLat - lat) < 0.00001 && Math.abs(lLng - lng) < 0.00001) {
                    layer.openPopup();
                }
            }
        });
    }
    });
});