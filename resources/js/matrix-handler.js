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
    const activeLayers = {}; // Simpan layer aktif: { 'area-RB': LayerGroup, 'route-A1': LayerGroup }
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
        mutations.forEach(m => { if (m.target.id === 'tabs-content' || m.target.closest('#tabs-content')) shouldSync = true; });
        if (shouldSync) setTimeout(syncSelectionUI, 50);
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
            const targetId = areaHeader.dataset.target; // area-RB
            const areaCode = areaHeader.dataset.areaCode;
            const displayCode = areaHeader.dataset.displayCode;
            const icon = areaHeader.querySelector('.icon-chevron');
            
            const content = document.getElementById(targetId);
            if(content) {
                const isHidden = content.classList.contains('hidden'); 
                content.classList.toggle('hidden');
                if(icon) icon.classList.toggle('rotate-180');

                // Update Breadcrumb & Peta
                if (!content.classList.contains('hidden') && displayCode) updateBreadcrumb(displayCode);
                toggleMapLayer(targetId, areaCode, null, isHidden);
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
            const targetId = routeHeader.dataset.target; // route-RB-A1
            const areaCode = routeHeader.dataset.areaCode;
            const routeCode = routeHeader.dataset.routeCode;
            const displayCode = routeHeader.dataset.displayCode;
            const icon = routeHeader.querySelector('.icon-chevron-sub');

            const content = document.getElementById(targetId);
            if(content) {
                const isHidden = content.classList.contains('hidden');
                content.classList.toggle('hidden');
                if(icon) icon.classList.toggle('rotate-180');
                
                if (!content.classList.contains('hidden') && displayCode) updateBreadcrumb(displayCode);
                toggleMapLayer(targetId, areaCode, routeCode, isHidden);
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

    // Custom Submit Generator (Capture Phase: true)
    document.addEventListener('submit', function(e) {
        if (e.target.id === 'kddk-generator-form') {
            e.preventDefault(); 
            e.stopPropagation(); 
            handleGeneratorSubmit(e.target);
        }
    }, true); 


    // ============================================================
    // 4. LOGIKA PETA (MULTI-LAYER)
    // ============================================================
    
    function toggleMapLayer(layerId, areaCode, routeCode, isVisible) {
        const mapContainer = document.getElementById('rbm-map');
        const urlInput = document.getElementById('map-data-url');
        if (!mapContainer || !urlInput) return;

        // Init Peta
        if (!rbmMap) {
            mapContainer.innerHTML = ''; 
            rbmMap = L.map('rbm-map').setView([0.5071, 101.4478], 13);
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles © Esri'
            }).addTo(rbmMap);
            const resizeObserver = new ResizeObserver(() => {
                if (rbmMap) rbmMap.invalidateSize();
            });
            resizeObserver.observe(mapContainer);
            
            // Listener tambahan untuk popup close
            mapContainer.addEventListener('click', function(e) {
                if (e.target.closest('.leaflet-popup-close-button') || e.target.closest('a')) {
                    e.stopPropagation(); // Mencegah reload halaman
                }
            });

            setTimeout(() => rbmMap.invalidateSize(), 200);
            mapContainer.addEventListener('click', function(e) {
                if (e.target.closest('.leaflet-popup-close-button') || e.target.closest('a')) {
                    e.stopPropagation(); // Biarkan Leaflet kerja, tapi jangan lapor ke TabManager
                    // e.preventDefault(); // Opsional, Leaflet biasanya sudah handle ini
                }
            });

        } else {
             if (rbmMap.getContainer() !== mapContainer) {
                rbmMap.remove(); rbmMap = null;
                for (let k in activeLayers) delete activeLayers[k]; 
                toggleMapLayer(layerId, areaCode, routeCode, isVisible); 
                return;
             }
        }

        // Hapus Layer
        if (!isVisible) {
            if (activeLayers[layerId]) {
                rbmMap.removeLayer(activeLayers[layerId]);
                delete activeLayers[layerId];
                updateTotalPoints();
                updateMapTitleWrapper();
            }
            return;
        }

        // Tambah Layer
        if (activeLayers[layerId]) return; 

        const countSpan = document.getElementById('map-count');
        if(countSpan) countSpan.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const urlBase = urlInput.value;
        let fetchUrl = `${urlBase}?area=${areaCode}`;
        if (routeCode) fetchUrl += `&route=${routeCode}`;

        fetch(fetchUrl)
            .then(res => res.json())
            .then(points => {
                const newLayer = L.featureGroup();
                const isRoute = !!routeCode; 

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
                    // Ambil digit ke-7 (Hari) dari KDDK (misal A1A RB AA1 -> A)
                    // Panjang KDDK = 12. Digit 7 ada di index 6 (0-based)
                    const dayCode = pt.seq ? pt.seq.charAt(0) : 'A'; // Fallback logic jika seq beda
                    
                    // Atau ambil dari parameter routeCode jika tersedia (lebih akurat)
                    const dayChar = routeCode ? routeCode.charAt(1) : 'A'; 
                    
                    // Tentukan warna
                    const colorClass = colorMap[dayChar] || 'text-gray-800 border-gray-600';

                    const iconHtml = `<div class="flex items-center justify-center w-5 h-5 bg-white border-2 ${colorClass} rounded-full text-[8px] font-bold shadow-sm" style="opacity: 0.9;">${pt.seq}</div>`;
                    
                    const icon = L.divIcon({ className: 'custom-map-marker', html: iconHtml, iconSize: [20, 20], iconAnchor: [10, 10] });
                    const marker = L.marker([pt.lat, pt.lng], { icon: icon });
                    marker.bindPopup(pt.info);
                    newLayer.addLayer(marker);
                });

                activeLayers[layerId] = newLayer;
                newLayer.addTo(rbmMap);
                
                fitBoundsToAll();
                updateTotalPoints();
                updateMapTitleWrapper();
            })
            .catch(err => console.error("Map Error:", err));
    }

    function fitBoundsToAll() {
        if (!rbmMap) return;
        const group = L.featureGroup();
        Object.values(activeLayers).forEach(layer => layer.eachLayer(m => group.addLayer(m)));
        if (group.getLayers().length > 0) rbmMap.fitBounds(group.getBounds().pad(0.1));
    }

    function updateTotalPoints() {
        const countSpan = document.getElementById('map-count');
        if (!countSpan) return;
        let total = 0;
        Object.values(activeLayers).forEach(layer => total += layer.getLayers().length);
        countSpan.textContent = total + ' Titik';
    }

    function updateMapTitleWrapper() {
        const titleEl = document.getElementById('map-context-title');
        const count = Object.keys(activeLayers).length;
        if (titleEl) {
            if (count === 0) titleEl.textContent = "Pilih Area/Rute";
            else if (count === 1) titleEl.textContent = Object.keys(activeLayers)[0].replace('area-', 'Area ').replace('route-', 'Rute ');
            else titleEl.textContent = `${count} Wilayah Ditampilkan`;
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

    // 3. Refresh Logic
    // 3. REFRESH LOGIC (DENGAN DETEKSI URL OTOMATIS & STATE RESTORE)
    function refreshActiveTab() {
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
        if (selectedIds.length === 0) { alert("Pilih minimal satu pelanggan."); return; }
        const container = document.getElementById('hidden-inputs-container');
        if(container) {
            container.innerHTML = '';
            selectedIds.forEach(id => {
                const i = document.createElement('input'); i.type='hidden'; i.name='selected_idpels[]'; i.value=id; container.appendChild(i);
            });
        }
        const modal = document.getElementById('modal-create-kddk');
        if(modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); if(typeof updateSequenceAndGenerate === 'function') updateSequenceAndGenerate(); }
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
        const term = val.toLowerCase().trim();
        const allRows = document.querySelectorAll('.draggable-idpel'); 
        const allRouteContainers = document.querySelectorAll('.route-container');
        const allAreaContainers = document.querySelectorAll('.area-container');
        const allRouteBodies = document.querySelectorAll('div[id^="route-"]');
        const allAreaBodies = document.querySelectorAll('div[id^="area-"]');
        if (term.length === 0) {
            allRows.forEach(row => row.classList.remove('hidden'));
            allRouteContainers.forEach(el => el.classList.remove('hidden'));
            allAreaContainers.forEach(el => el.classList.remove('hidden'));
            allRouteBodies.forEach(el => el.classList.add('hidden'));
            allAreaBodies.forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.icon-chevron, .icon-chevron-sub').forEach(i => i.classList.remove('rotate-180'));
            return;
        }
        allRouteContainers.forEach(el => el.classList.add('hidden'));
        allAreaContainers.forEach(el => el.classList.add('hidden'));
        allRows.forEach(row => {
            const rowText = row.innerText.toLowerCase();
            if (rowText.includes(term)) {
                row.classList.remove('hidden');
                const routeBody = row.closest('div[id^="route-"]');
                if (routeBody) {
                    const routeContainer = routeBody.closest('.route-container');
                    if (routeContainer) {
                        routeContainer.classList.remove('hidden');
                        routeBody.classList.remove('hidden'); 
                        routeContainer.querySelector('.icon-chevron-sub')?.classList.add('rotate-180');
                    }
                    const digit6Body = routeBody.closest('div[id^="d6-"]');
                    if(digit6Body) {
                         const digit6Container = digit6Body.closest('.digit6-container');
                         if(digit6Container) {
                              digit6Container.classList.remove('hidden');
                              digit6Body.classList.remove('hidden');
                              digit6Container.querySelector('.icon-chevron-d6')?.classList.add('rotate-180');
                         }
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

});