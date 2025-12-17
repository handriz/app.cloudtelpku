// resources/js/matrix-handler.js


/**
 * ====================================================================
 * 1. VARIABEL GLOBAL & STATE
 * Ditaruh di paling atas (luar) agar tidak ter-reset saat tab berpindah,
 * dan bisa diakses oleh fungsi manapun.
 * ====================================================================
 */

    // Pastikan selectionState ada di window agar bisa diakses global
    window.selectionState = {
        unit: null,     
        items: new Map() 
    };

    // Variabel sementara untuk Modal Upload
    window.tempUploadResults = [];

    // Variabel Peta (Leaflet)
    let rbmMap = null; // Aman, ini cuma wadah kosong (placeholder)
    const areaLayers = {}; // Aman, object kosong
    const areaRawData = {}; // [BARU] Menyimpan data JSON mentah per area
    let sequenceController = null;
    let routeLineLayer = null;   // Garis biru
    let arrowLayer = null;       // Panah arah

/**
 * ====================================================================
 * 2. FUNGSI UI GLOBAL (SINKRONISASI TAMPILAN)
 * Fungsi ini memperbarui checkbox dan tombol grouping berdasarkan State.
 * ====================================================================
 */

    window.syncSelectionUI = function() {
        // 1. Cek Context Unit
        const contextInput = document.getElementById('page-context-unit');
        if (!contextInput) return;
        
        const currentUnit = contextInput.value;
        if (window.selectionState.unit !== currentUnit) {
            window.selectionState.unit = currentUnit;
            window.selectionState.items.clear();
        }

        // 2. Centang Checkbox yang sesuai dengan State
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = window.selectionState.items.has(cb.value);
        });

        // 3. Update Checkbox "Select All"
        const checkAll = document.getElementById('check-all-rows');
        if (checkAll) {
            checkAll.checked = (checkboxes.length > 0 && [...checkboxes].every(c => c.checked));
        }

        // 4. Update Tombol Grouping
        window.toggleGroupButton();
    };

    window.toggleGroupButton = function() {
        const count = window.selectionState.items.size;
        const btn = document.getElementById('btn-group-kddk');
        const countSpan = document.getElementById('count-selected');

        if(btn) {
            if(count > 0) { 
                btn.classList.remove('hidden'); 
                btn.innerHTML = `<i class="fas fa-layer-group mr-2"></i> Bentuk Group (${count})`; 
            } else {
                btn.classList.add('hidden');
            }
        }
        if(countSpan) countSpan.textContent = count;
    };

/**
 * ====================================================================
 * 3. FUNGSI LOGIKA UPLOAD (MODAL & PROSES)
 * ====================================================================
 */

    // A. Membuka Modal Upload
    window.openUploadModal = function() {
        const modal = document.getElementById('modal-upload-csv-preview');
        const panel = document.getElementById('upload-modal-panel');
        const dropZone = document.getElementById('upload-drop-zone');
        const fileInput = document.getElementById('real-file-input');

        if (!modal) {
            console.error("Modal #modal-upload-csv-preview tidak ditemukan.");
            return;
        }

        // Reset UI & State
        window.tempUploadResults = [];
        if(fileInput) fileInput.value = '';
        
        if(dropZone) dropZone.classList.remove('hidden');
        document.getElementById('upload-loading').classList.add('hidden');
        document.getElementById('upload-result-stats').classList.add('hidden');

        const btnApply = document.getElementById('btn-apply-upload');
        if(btnApply) {
            btnApply.disabled = true;
            btnApply.classList.add('opacity-50', 'cursor-not-allowed');
        }

        // Animasi Masuk
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.classList.add('flex', 'opacity-100');
            if(panel) {
                panel.classList.remove('scale-95');
                panel.classList.add('scale-100');
                window.setupDragDropListeners();
            }
        }, 10);
    };

    // B. Menutup Modal Upload
    window.closeUploadModal = function() {
        const modal = document.getElementById('modal-upload-csv-preview');
        const panel = document.getElementById('upload-modal-panel');

        if (!modal) return;

        if(panel) {
            panel.classList.remove('scale-100');
            panel.classList.add('scale-95');
        }
        modal.classList.remove('opacity-100');
        modal.classList.add('opacity-0');

        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300);
    };

    // C. Proses File (Dipanggil oleh Input Change & Drop)
    window.processUploadedFile = function(file) {
    if (!file) return;
    
    // Setup UI Loading
    const dropZone = document.getElementById('upload-drop-zone');
    const loading = document.getElementById('upload-loading');
    const stats = document.getElementById('upload-result-stats');
    const filenameLabel = document.getElementById('stat-filename');
    const loadingText = loading.querySelector('p');

    if (dropZone) dropZone.classList.add('hidden');
    if (loading) loading.classList.remove('hidden');
    if (stats) stats.classList.add('hidden');
    if (filenameLabel) filenameLabel.textContent = file.name;
    if (loadingText) loadingText.textContent = "Membaca Format CSV (ID, Lat, Lng)...";

    const reader = new FileReader();
    
    reader.onload = function(event) {
        const text = event.target.result;
        const allLines = text.split(/\r\n|\n/);
        
        // [BARU] Parsing Cerdas (IDPEL + KOORDINAT)
        // Format diharapkan: IDPEL, LATITUDE, LONGITUDE
        
        const parsedData = []; // Menyimpan objek {idpel, lat, lng}
        const onlyIds = [];    // Menyimpan string IDPEL saja (untuk validasi server)

        allLines.forEach(line => {
            if (!line.trim()) return; // Skip baris kosong

            // Split berdasarkan Koma, Titik Koma, Pipe, atau Tab
            const parts = line.split(/[,;| \t]+/);
            
            // Ambil IDPEL (Kolom 1 - Bersihkan karakter non-angka)
            const rawId = parts[0].replace(/[^0-9]/g, '');

            // Validasi: Minimal 10 digit agar dianggap IDPEL valid
            if (rawId.length >= 10) {
                let lat = null;
                let lng = null;

                // Cek apakah ada kolom Latitude (2) dan Longitude (3)
                if (parts.length >= 3) {

                    const latStr = parts[1].replace(',', '.'); 
                    const lngStr = parts[2].replace(',', '.');

                    const l1 = parseFloat(parts[1]); // Latitude
                    const l2 = parseFloat(parts[2]); // Longitude
                    
                    // Validasi angka koordinat (Lat -90~90, Lng -180~180)
                    if (!isNaN(l1) && !isNaN(l2) && Math.abs(l1) <= 90 && Math.abs(l2) <= 180) {
                        lat = l1;
                        lng = l2;
                    }
                }

                parsedData.push({ idpel: rawId, lat: lat, lng: lng });
                onlyIds.push(rawId);
            }
        });

        const uniqueIds = [...new Set(onlyIds)];
        
        if (uniqueIds.length === 0) {
            alert("File kosong atau format salah. Pastikan kolom pertama adalah IDPEL.");
            if (dropZone) dropZone.classList.remove('hidden');
            if (loading) loading.classList.add('hidden');
            return;
        }

        // [PENTING] Simpan data lengkap ke Variabel Global Sementara
        // Data ini akan dipakai nanti saat tombol "Simpan" ditekan di Generator
        window.tempUploadFullData = parsedData; 

        // --- VALIDASI KE SERVER (Cuma kirim IDPEL untuk cek status Rute) ---
        if(loadingText) loadingText.textContent = "Memvalidasi Database...";
        
        const unitContext = document.getElementById('page-context-unit').value;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const validateUrl = '/team/matrix-kddk/validate-upload'; 

        fetch(validateUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ idpels: uniqueIds, unitup: unitContext })
        })
        .then(response => response.json())
        .then(data => {

            // Buat lookup table biar cepat
            const serverReadySet = new Set(data.ready_ids);
            // Hanya ambil ID yang dinyatakan 'Valid/Ready' oleh server
            const orderedReadyIds = uniqueIds.filter(id => serverReadySet.has(id));
            // Simpan hasil yang SUDAH URUT ke variabel global
            window.tempUploadResults = orderedReadyIds; 
            
            // Hitung Statistik
            const countReady = orderedReadyIds.length;
            const countMapped = data.mapped_count;
            const countInvalid = uniqueIds.length - (countReady + countMapped);

            // Update Tampilan Statistik
            const elTotal = document.getElementById('stat-total');
            const elValid = document.getElementById('stat-valid');
            const elMapped = document.getElementById('stat-mapped');
            const elInvalid = document.getElementById('stat-invalid');
            
            if (elTotal) elTotal.textContent = uniqueIds.length.toLocaleString();
            if (elValid) elValid.textContent = countReady.toLocaleString();
            if (elMapped) elMapped.textContent = countMapped.toLocaleString();
            if (elInvalid) elInvalid.textContent = countInvalid.toLocaleString();

            document.getElementById('upload-loading').classList.add('hidden');
            document.getElementById('upload-result-stats').classList.remove('hidden');

            const btnApply = document.getElementById('btn-apply-upload');
            if (btnApply) {
                if (countReady > 0) {
                    btnApply.disabled = false;
                    btnApply.classList.remove('opacity-50', 'cursor-not-allowed');
                    
                    // Info tambahan: Berapa banyak yang ada koordinatnya?
                    // Filter parsedData yang ID-nya ada di ready_ids DAN punya lat/lng
                    const readyIdsSet = new Set(data.ready_ids);
                    const hasCoordCount = parsedData.filter(d => readyIdsSet.has(d.idpel) && d.lat).length;
                    
                    const coordMsg = hasCoordCount > 0 ? `<br><span class="text-xs font-normal opacity-80">(Update ${hasCoordCount} Peta)</span>` : '';
                    
                    btnApply.innerHTML = `<span>Gunakan ${countReady} Data</span> ${coordMsg} <i class="fas fa-arrow-right ml-2"></i>`;
                } else {
                    btnApply.disabled = true;
                    btnApply.classList.add('opacity-50', 'cursor-not-allowed');
                    btnApply.innerHTML = `<span>Tidak ada data baru</span>`;
                    
                    if (countMapped > 0) {
                        showGenericWarning(`<strong>${countMapped} Data</strong> sudah memiliki Rute.<br>Upload ditolak untuk mencegah duplikasi.`);
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error Validation:', error);
            alert("Gagal memvalidasi data ke server.");
            if (dropZone) dropZone.classList.remove('hidden');
            if (loading) loading.classList.add('hidden');
        });
    };

    reader.readAsText(file);
};

    // D. Handler Input File (Click)
    window.handleFileFromInput = function(input) {
        if (input.files && input.files[0]) {
            window.processUploadedFile(input.files[0]);
        }
    };

    // E. Setup Drag & Drop Listeners
    window.setupDragDropListeners = function() {
        const dropZone = document.getElementById('upload-drop-zone');
        const visual = document.getElementById('drop-zone-visual');
        if (!dropZone) return;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => {
            dropZone.addEventListener(evt, (e) => { e.preventDefault(); e.stopPropagation(); }, false);
        });

        ['dragenter', 'dragover'].forEach(evt => {
            dropZone.addEventListener(evt, () => visual?.classList.add('border-indigo-500', 'bg-indigo-50'), false);
        });

        ['dragleave', 'drop'].forEach(evt => {
            dropZone.addEventListener(evt, () => visual?.classList.remove('border-indigo-500', 'bg-indigo-50'), false);
        });

        dropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files && files.length > 0) window.processUploadedFile(files[0]);
        }, false);
    };

    // F. APPLY DATA (TOMBOL "GUNAKAN DATA INI") - BAGIAN PENTING
    window.applyUploadSelection = function() {
        console.log("Tombol Apply Ditekan. Jumlah Data:", window.tempUploadResults.length);

        if (window.tempUploadResults.length === 0) {
            alert("Tidak ada data untuk diproses.");
            return;
        }

        // 1. Bersihkan State Lama
        window.selectionState.items.clear();

        // 2. Masukkan Data Baru ke State Utama
        window.tempUploadResults.forEach(idpel => {
            window.selectionState.items.set(idpel, { 
                jenis: 'UPLOAD_CSV', 
                source_order: true 
            });
        });

        console.log("State Updated. Total Item:", window.selectionState.items.size);

        // 3. Update Tampilan Halaman
        window.syncSelectionUI();

        // 4. Tutup Modal Upload
        window.closeUploadModal();

        // 5. Buka Modal Grouping (Generator)
        setTimeout(() => {
            if (typeof window.openKddkModal === 'function') {
                window.openKddkModal();
            } else {
                console.error("Fungsi openKddkModal tidak ditemukan.");
                alert("Data tersimpan di memori (" + window.tempUploadResults.length + " item), tapi gagal membuka Generator otomatis. Silakan klik tombol 'Bentuk Group' manual.");
            }
        }, 400);
    };

    window.toggleManualSequence = function(checkbox) {
    const urutInput = document.getElementById('part_urut');
    if (!urutInput) return;

    if (checkbox.checked) {
        // MODE MANUAL: Buka Kunci
        urutInput.readOnly = false;
        urutInput.value = ''; // Kosongkan biar user isi
        urutInput.placeholder = '000';
        
        // Ubah Tampilan jadi Putih (Aktif)
        urutInput.classList.remove('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
        urutInput.classList.add('bg-white', 'text-indigo-700', 'border-indigo-500', 'ring-2', 'ring-indigo-200');
        urutInput.focus();
    } else {
        // MODE AUTO: Kunci Kembali
        urutInput.readOnly = true;
        urutInput.placeholder = '...';
        
        // Ubah Tampilan jadi Abu-abu (Readonly)
        urutInput.classList.add('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
        urutInput.classList.remove('bg-white', 'text-indigo-700', 'border-indigo-500', 'ring-2', 'ring-indigo-200');
        
        // Panggil server lagi untuk minta nomor otomatis
        window.updateSequenceAndGenerate();
    }
    };

    window.handleManualSequenceInput = function(input) {
        // Validasi: Hanya Angka
        let val = input.value.replace(/[^0-9]/g, '');
        
        // Validasi: Maksimal 3 Digit
        if (val.length > 3) val = val.slice(0, 3);
        
        input.value = val;

        // Update Preview String Langsung
        window.generateFinalCode();
    };

/**
 * ====================================================================
 * 4. FUNGSI GENERATOR & LAINNYA
 * ====================================================================
 */

    window.openKddkModal = function() {
    const selectedIds = Array.from(window.selectionState.items.keys());

    if (selectedIds.length === 0) { 
        alert("Pilih atau Upload minimal satu pelanggan."); 
        return; 
    }

    // 1. Isi Input Hidden
    const container = document.getElementById('hidden-inputs-container');
    if(container) {
        container.innerHTML = '';
        selectedIds.forEach(id => {
            const i = document.createElement('input'); 
            i.type='hidden'; 
            i.name='selected_idpels[]'; 
            i.value=id; 
            container.appendChild(i);
        });
    }

    // 2. Tampilkan Modal dengan Animasi (FIXED)
    const modal = document.getElementById('modal-create-kddk');
    const panel = modal ? modal.querySelector('div') : null; // Ambil container dalam (kartu)

    if(modal) { 
        // Hapus hidden dulu agar elemen dirender
        modal.classList.remove('hidden'); 
        modal.classList.add('flex'); 
        
        // Delay sangat kecil agar transisi CSS opacity berjalan
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.classList.add('opacity-100');
            
            if(panel) {
                panel.classList.remove('scale-95');
                panel.classList.add('scale-100');
            }
        }, 10);

        // Update Label Jumlah
        const countLabel = document.getElementById('count-selected');
        const countDisplay = document.getElementById('count-display');
        if(countLabel) countLabel.textContent = selectedIds.length;
        if(countDisplay) countDisplay.textContent = selectedIds.length;

        // Preview Urutan (3 Item Pertama)
        const previewList = document.getElementById('sequence-preview-list');
        if (previewList) {
            previewList.innerHTML = '';
            selectedIds.slice(0, 3).forEach((id, idx) => {
                previewList.innerHTML += `
                    <div class="flex justify-between text-[10px] text-gray-600 border-b border-gray-100 dark:border-gray-700 py-1 font-mono">
                        <span>Urut #${(idx+1).toString().padStart(3,'0')}</span>
                        <span class="font-bold text-gray-800 dark:text-gray-300">${id}</span>
                    </div>`;
            });
            if (selectedIds.length > 3) {
                previewList.innerHTML += `<div class="text-[10px] text-gray-400 text-center mt-1 font-italic">... dan ${selectedIds.length - 3} lainnya</div>`;
            }
        }
    }
    
    // Trigger update sequence logic
    if(typeof window.updateSequenceAndGenerate === 'function') {
        window.updateSequenceAndGenerate(); 
    }
    };
    
    window.closeKddkModal = function() {
    const modal = document.getElementById('modal-create-kddk');
    const panel = modal ? modal.querySelector('div') : null;

    if(modal) { 
        // Animasi Keluar
        modal.classList.remove('opacity-100');
        modal.classList.add('opacity-0');
        
        if(panel) {
            panel.classList.remove('scale-100');
            panel.classList.add('scale-95');
        }

        // Tunggu animasi selesai (300ms) baru hide element
        setTimeout(() => {
            modal.classList.add('hidden'); 
            modal.classList.remove('flex');
        }, 300);
    }
};

    window.executeAjax = function(url, bodyData) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    document.body.style.cursor = 'wait';

    return fetch(url, {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json', 
            'X-CSRF-TOKEN': csrfToken, 
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
         },
        body: JSON.stringify(bodyData)
    })
    .then(response => response.json())
    .then(data => {
        document.body.style.cursor = 'default';
        if (data.success) {
            // Pastikan showGenericSuccess juga bisa diakses (lihat poin 2)
            if(typeof window.showGenericSuccess === 'function') window.showGenericSuccess(data.message);
            else alert(data.message);
        } else {
            alert('Gagal: ' + data.message);
        }
    })
    .catch(error => {
        document.body.style.cursor = 'default';
        console.error('Error AJAX:', error);
        alert('Terjadi kesalahan server.');
    });
    };
    
    window.performMoveIdpel = function(idpel, targetKddk) {
    const urlInput = document.getElementById('move-route');
    if (!urlInput) return;
    window.executeAjax(urlInput.value, { idpel: idpel, target_kddk: targetKddk });
    };

    window.performRemoveIdpel = function(idpel) {
        // 1. Ambil CSRF Token
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const token = csrfMeta ? csrfMeta.getAttribute('content') : '';

        // 2. Visual Feedback (Baris jadi transparan)
        const rowToDelete = document.querySelector(`.draggable-idpel[data-idpel="${idpel}"]`);
        if (rowToDelete) {
            rowToDelete.style.transition = 'opacity 0.3s';
            rowToDelete.style.opacity = '0.3';
            rowToDelete.style.pointerEvents = 'none';
        }

        let routeContent = null;
        let routeHeaderBadge = null;
        let areaCode = null;
        let routeCode = null;
        let contentId = null;

        if (rowToDelete) {
            routeContent = rowToDelete.closest('.route-content');
            if (routeContent) {
                // Ambil data penting untuk reload
                areaCode = routeContent.dataset.areaCode; // Pastikan di HTML ada data-area-code
                routeCode = routeContent.dataset.routeCode; // Pastikan di HTML ada data-route-code
                contentId = routeContent.id; // ID Kontainer (misal: content-18111A1)

                // Badge jumlah
                const targetIdStr = contentId.replace('content-', '');
                routeHeaderBadge = document.querySelector(`#heading-${targetIdStr} .badge-count`);
            }
        }

        const validateUrl = '/team/matrix-kddk/remove-idpel'; 

        // 3. Kirim Request ke Server
        fetch(validateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ idpel: idpel })
        })
        .then(res => {
            if (!res.ok) throw new Error("Server Error");
            return res.json();
        })
        .then(data => {
            if (data.success) {
                // 1. Hapus Baris Lama
                if (rowToDelete) rowToDelete.remove();

                // 2. Update Badge Jumlah (Visual Cepat)
                if (routeHeaderBadge) {
                    let c = parseInt(routeHeaderBadge.innerText.replace(/\D/g, '')) || 0;
                    if (c > 0) routeHeaderBadge.innerText = (c - 1) + ' Plg';
                }

                // 3. [KUNCI] RELOAD TABEL UNTUK UPDATE NOMOR URUT
                // Panggil fungsi loadRouteTableData yang sudah ada
                if (contentId && areaCode && routeCode && typeof loadRouteTableData === 'function') {
                    // Beri jeda 200ms agar PHP selesai transaksi resequence
                    setTimeout(() => {
                         loadRouteTableData(contentId, areaCode, routeCode);
                    }, 200);
                }

                // 4. Update Peta (Titik Hilang)
                if (typeof updateTotalPoints === 'function') setTimeout(() => updateTotalPoints(), 500);

                // Toast
                window.showGenericSuccess("Data berhasil dihapus dan diurutkan ulang.");

            } else {
                throw new Error(data.message);
            }
        })
        .catch(error => {
            console.error('Error Remove:', error);
            // Jika gagal, kembalikan tampilan baris seperti semula
            if (rowToDelete) {
                rowToDelete.style.opacity = '1';
                rowToDelete.style.pointerEvents = 'auto';
            }
            
            if(typeof App !== 'undefined' && App.Utils) {
                showGenericWarning(`
                    <strong>Gagal menghapus data</strong><br>
                    <span class="text-xs text-gray-500">${error.message}</span>
                `);
            } else {
                alert("Gagal: " + error.message);
            }
        });
    };

    window.refreshActiveTab = function(successMessage = null) {
        if (typeof App === 'undefined' || !App.Utils || !App.Tabs) return;
        
        const activeTab = App.Utils.getActiveTabName();
        const activeContent = document.getElementById(`${activeTab}-content`);
        if (!activeContent) return;

        // A. DETEKSI URL
        const rbmForm = activeContent.querySelector('#rbm-form');
        const unitInput = activeContent.querySelector('input[name="unitup"]');
        let refreshUrl = null;

        if (rbmForm && unitInput) {
            refreshUrl = `/team/matrix-kddk/manage-rbm/${encodeURIComponent(unitInput.value)}`;
        } else if (unitInput) {
             const searchForm = activeContent.querySelector('form[action*="details"]');
             if (searchForm) {
                 refreshUrl = searchForm.action;
                 if (window.location.search && !refreshUrl.includes('?')) refreshUrl += window.location.search;
             } else {
                 refreshUrl = `/team/matrix-kddk/details/${encodeURIComponent(unitInput.value)}`;
             }
        } else {
             const tabBtn = document.querySelector(`.tab-button[data-tab-name="${activeTab}"]`);
             refreshUrl = tabBtn ? tabBtn.dataset.url : window.location.href;
        }

        // B. SIMPAN STATE
        const state = { scroll: 0, openedIds: [], isMapHidden: false };
        const scrollContainer = activeContent.querySelector('.overflow-y-auto');
        if (scrollContainer) state.scroll = scrollContainer.scrollTop;

        const openElements = activeContent.querySelectorAll('div[id^="area-"]:not(.hidden), div[id^="d6-"]:not(.hidden), div[id^="route-"]:not(.hidden)');
        openElements.forEach(el => state.openedIds.push(el.id));

        const panelMap = activeContent.querySelector('#panel-map');
        if (panelMap && !panelMap.classList.contains('md:block')) state.isMapHidden = true;

        // C. EKSEKUSI REFRESH
        if(refreshUrl) {
            let bustUrl = new URL(refreshUrl, window.location.origin);
            bustUrl.searchParams.set('_cb', new Date().getTime());
            
            App.Tabs.loadTabContent(activeTab, bustUrl.toString(), () => {
                const newContent = document.getElementById(`${activeTab}-content`);
                if (!newContent) return;

                // 1. RESTORE ACCORDION
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

                // 2. AUTO LOAD TABEL RUTE
                newContent.querySelectorAll('.route-content').forEach(el => {
                    if (!el.classList.contains('hidden') && el.dataset.loaded === "false") {
                        const areaCode = el.dataset.areaCode;
                        const routeCode = el.dataset.routeCode;
                        if (areaCode && routeCode) loadRouteTableData(el.id, areaCode, routeCode);
                    }
                });

                // 3. [PERBAIKAN PETA & DATA]
                setTimeout(() => {
                    console.log("[MAP REFRESH] Memulai reset peta...");
                    // A. HANCURKAN PETA LAMA SECARA PAKSA
                    // Karena HTML sudah diganti baru, rbmMap yang lama pasti invalid.
                    if (rbmMap) {
                        try { rbmMap.remove(); } catch(e) {}
                        rbmMap = null;
                    }

                    // B. HAPUS CACHE DATA MENTAH (Wajib!)
                    // Agar peta mengambil data baru (tanpa pelanggan yang dihapus) dari server
                    if (typeof areaRawData !== 'undefined') {
                        for (let key in areaRawData) delete areaRawData[key];
                    }
                    if (typeof areaLayers !== 'undefined') {
                        for (let key in areaLayers) delete areaLayers[key];
                    }

                    // C. INIT ULANG PETA
                    const activeAreaHeader = newContent.querySelector('.area-header .icon-chevron.rotate-180');
                    if (activeAreaHeader) {
                        const areaCode = activeAreaHeader.closest('.area-header').dataset.areaCode;
                        console.log("[MAP REFRESH] Memuat ulang area:", areaCode);
                        
                        if (typeof loadAreaMap === 'function') {
                            loadAreaMap(areaCode, () => {
                                // Callback: Dijalankan SETELAH peta selesai digambar
                                // Kita lakukan invalidateSize berulang untuk memastikan peta tidak blank
                                if (rbmMap) rbmMap.invalidateSize();
                                setTimeout(() => { if(rbmMap) rbmMap.invalidateSize(); }, 200);
                                setTimeout(() => { if(rbmMap) rbmMap.invalidateSize(); }, 500);

                                // Update Judul & Titik
                                if (typeof updateMapTitleWrapper === 'function') updateMapTitleWrapper();
                                if (typeof updateTotalPoints === 'function') updateTotalPoints();
                            });
                        }
                    } else {
                        // Jika tidak ada area terbuka, tetap inisialisasi peta kosong agar tidak blank
                        if (typeof loadAreaMap === 'function') {
                            const mapDiv = document.getElementById('rbm-map');
                            if (mapDiv) {
                                mapDiv.innerHTML = '';
                                rbmMap = L.map('rbm-map', { zoomControl: false }).setView([0.5071, 101.4478], 13);
                                L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution: 'Tiles © Esri' }).addTo(rbmMap);
                            }
                        }
                    }
                }, 1000);// Delay agar HTML stabil

                // 4. RESTORE UI LAINNYA
                if (state.isMapHidden) {
                    const newPanelList = newContent.querySelector('#panel-list');
                    const newPanelMap = newContent.querySelector('#panel-map');
                    const newToggleBtn = newContent.querySelector('[data-action="toggle-map-layout"]');
                    if (newPanelList && newPanelMap) {
                        newPanelMap.classList.remove('md:block'); newPanelMap.classList.add('hidden');
                        newPanelList.classList.remove('md:w-[450px]'); newPanelList.classList.add('w-full');
                        newContent.querySelectorAll('.routes-grid-container').forEach(el => {
                           el.classList.remove('space-y-0'); el.classList.add('grid', 'grid-cols-1', 'md:grid-cols-2', 'xl:grid-cols-3', 'gap-4', 'p-2');
                        });
                        if (newToggleBtn) {
                            const txt = newToggleBtn.querySelector('.text-btn');
                            if(txt) txt.textContent = "Show Map";
                        }
                    }
                }

                const newScroll = newContent.querySelector('.overflow-y-auto');
                if (newScroll) newScroll.scrollTop = state.scroll;

                if (successMessage) {
                    const notif = newContent.querySelector('#kddk-notification-container');
                    if (notif) {
                        notif.innerHTML = `<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 animate-fade-in-down"><i class="fas fa-check-circle mr-2"></i><b>${successMessage}</b></div>`;
                        setTimeout(() => { if(notif.firstChild) notif.firstChild.remove(); }, 5000);
                    } else {
                        alert(successMessage);
                    }
                }
            });
        }
    };

    window.performReorderIdpel = function(idpel, targetIdpel, prefix) {
        const urlInput = document.getElementById('reorder-route');
        if (!urlInput) return;
        
        // Panggil Execute Ajax
        executeAjax(urlInput.value, { 
            idpel: idpel, 
            target_idpel: targetIdpel,
            route_prefix: prefix
        });
    }

    function refreshMapAfterReorder(areaCode) {
    console.log('[MAP] Force reload after reorder:', areaCode);

    // 1. Hapus cache data mentah
    if (areaRawData[areaCode]) {
        delete areaRawData[areaCode];
    }

    // 2. Hapus layer lama
    if (areaLayers[areaCode]) {
        if (rbmMap && rbmMap.hasLayer(areaLayers[areaCode])) {
            rbmMap.removeLayer(areaLayers[areaCode]);
        }
        delete areaLayers[areaCode];
    }

    // 3. Hapus garis & panah
    if (routeLineLayer) {
        rbmMap.removeLayer(routeLineLayer);
        routeLineLayer = null;
    }

    if (arrowLayer) {
        rbmMap.removeLayer(arrowLayer);
        arrowLayer = null;
    }

    // 4. Load ulang data map (FETCH BARU)
    loadAreaMap(areaCode, () => {
        updateTotalPoints();
        updateMapTitleWrapper();
    });
    }

/**
 * ====================================================================
 * 5. LOGIKA GENERATOR KDDK (SEQUENCE & PREFIX)
 * Dibuat Global agar bisa dipanggil oleh openKddkModal dan Event Listener
 * ====================================================================
 */
    
    // A. Helper: Mengambil 7 Digit Prefix (UP3 + ULP + SUB + AREA + RUTE)
    window.getPrefix7 = function() {
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
        // Harus pas 7 karakter
        return parts.length === 7 ? parts : null;
    }

    // B. Logic Utama: Cek Sequence ke Server & Update UI
    window.updateSequenceAndGenerate = function() {
        const isManual = document.getElementById('mode_insert_sequence')?.checked;
        if (isManual) {
            window.generateFinalCode();
            return; 
        }

        window.generateFinalCode();
       
        const count = window.selectionState.items.size;
        const countDisplay = document.getElementById('count-display');
        if(countDisplay) countDisplay.textContent = count;
        
        const prefix7 = window.getPrefix7();
        const urutInput = document.getElementById('part_urut');
        const form = document.getElementById('kddk-generator-form');
        
        // 2. Jika Prefix Lengkap (7 digit), tanya server nomor urut berikutnya
        if (prefix7 && urutInput && form) {
            if (!form.dataset || !form.dataset.sequenceUrl) return;

            // Batalkan request sebelumnya jika user mengetik cepat
            if (window.sequenceController) window.sequenceController.abort();
            window.sequenceController = new AbortController();

            urutInput.value = '...';// Loading indicator text
            
            const url = `${form.dataset.sequenceUrl}/${prefix7}`;
            
            fetch(url, { signal: window.sequenceController.signal })
                .then(r=>r.json())
                .then(d=>{
                if(d.sequence) {
                    // Server mengembalikan sequence terakhir + 1 (misal: 001)
                    urutInput.value = d.sequence;

                    // Update Preview Range (Start - End)
                    const startSeq = parseInt(d.sequence);
                    const endSeq = startSeq + count - 1;
                    const pStart = document.getElementById('preview-start');
                    const pEnd = document.getElementById('preview-end');
                    const sisipEl = document.getElementById('part_sisip');
                    const sisip = (sisipEl ? sisipEl.value : '00').padStart(2,'0');

                    if(pStart) pStart.textContent = `${prefix7}${d.sequence}${sisip}`;
                    if(pEnd) pEnd.textContent = `${prefix7}${endSeq.toString().padStart(3,'0')}${sisip}`;
                    window.generateFinalCode();
                }
            })
            .catch(e=>{
                if (e.name !== 'AbortError') console.error("Sequence Error:", e);
            });
        }
    };
    
    // C. Logic Visual: Menggabungkan semua input menjadi string KDDK final
    window.generateFinalCode = function() {
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
        const hiddenFullCode = document.getElementById('hidden_full_code_prefix');

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

        // Isi ke Input Hidden / Preview
        preview.value = fullCode;
        if (hiddenPrefix) hiddenPrefix.value = prefix7;
        if (hiddenSisip) hiddenSisip.value = sisip;
        if (hiddenFullCode) hiddenFullCode.value = fullCode;

        // Validasi Akhir: Apakah kode valid (12 digit, tidak ada underscore)
        if (!fullCode.includes('_') && fullCode.length === 12 && !fullCode.includes('...')) {

            // Valid
            if(err) {
                err.textContent = "Format Valid ✅";
                err.className = "text-xs text-center text-green-600 mt-1 h-4";
            }

            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            // Tidak Valid
            if(err) { 
                err.textContent = "Lengkapi data area & rute..."; 
                err.className = "text-xs text-center text-red-500 mt-1 h-4"; 
            }
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    };
    
    // D. Helper Dropdown Area/Rute (Agar Label muncul)
    window.updateRouteOptions = function() {
        const areaSelect = document.getElementById('part_area');
        const routeSelect = document.getElementById('part_rute');
        const areaLabelDisplay = document.getElementById('area-label-display');
        const routeLabelDisplay = document.getElementById('rute-label-display');

        if (!areaSelect || !routeSelect) return;

        routeSelect.innerHTML = '<option value="">--</option>';
        if (routeLabelDisplay) routeLabelDisplay.textContent = '';

        const selectedOption = areaSelect.options[areaSelect.selectedIndex];

        // Tampilkan Nama Area di bawah dropdown
        if (areaLabelDisplay && selectedOption.value) {
             const labelText = selectedOption.dataset.label || selectedOption.text;
             areaLabelDisplay.textContent = labelText;
        } else if (areaLabelDisplay) {
        areaLabelDisplay.textContent = '';
        }

        // Isi Dropdown Rute sesuai Area yang dipilih
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
    };

    window.updateLabelDisplay = function() {
        const routeSelect = document.getElementById('part_rute');
        const routeLabelDisplay = document.getElementById('rute-label-display');
        if (routeSelect && routeLabelDisplay) {
            const selectedOption = routeSelect.options[routeSelect.selectedIndex];
            if (selectedOption && selectedOption.value) routeLabelDisplay.textContent = selectedOption.dataset.label || '';
            else routeLabelDisplay.textContent = '';
        }
        window.updateSequenceAndGenerate();
    }

    window.loadRouteTableData = function(targetId, area, route) {
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
                if(container) container.dataset.loaded = "true"; // Tandai sudah load agar tidak load ulang
            })
            .catch(err => {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center text-red-500 text-xs p-2">Gagal memuat data.</td></tr>`;
                console.error(err);
            });
    };

    window.showGenericSuccess = function(message) {
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
            if(typeof refreshActiveTab === 'function') refreshActiveTab();
        }
    };

    function showGenericWarning(message) {
        const modal = document.getElementById('modal-warning-generic');
        const msgEl = document.getElementById('warning-modal-message');
        
        if (modal && msgEl) {
            msgEl.innerHTML = message; // Gunakan innerHTML agar bisa bold
            modal.classList.remove('hidden');
            modal.classList.remove('opacity-0'); // Pastikan opacity reset
            modal.classList.add('flex');
            modal.classList.add('opacity-100');
        } else {
            // Fallback jika modal HTML belum ada
            alert(message.replace(/<[^>]*>?/gm, '')); 
        }
    }

    window.updateMapControlsPosition = function(isSidebarClosed) {
        const controls = document.getElementById('map-info-controls');
        const anomalyAlert = document.getElementById('anomaly-alert');
        
        // Gunakan MARGIN-LEFT agar tidak bentrok dengan animasi CSS (transform)
        // 170px = Lebar tombol "Buka Menu" + Spasi
        const shiftValue = isSidebarClosed ? '170px' : '0px';

        // 1. Geser Info Titik
        if (controls) {
            controls.style.marginLeft = shiftValue;
        }

        // 2. Geser Alert Anomali
        if (anomalyAlert) {
            anomalyAlert.style.marginLeft = shiftValue;
        }
    };

document.addEventListener('DOMContentLoaded', () => {

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
        
        if (shouldSync) setTimeout(window.syncSelectionUI, 50);
        
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
                
                // [UPDATE 1] Update Judul Langsung (Visual Feedback Cepat)
                updateMapTitleWrapper();

                // Pastikan Layer Induk (Area) ada di peta
                loadAreaMap(areaCode, () => {
                    const btnReorder = document.getElementById('map-visual-controls');

                    // [UPDATE 2] Update Jumlah Titik SETELAH data map dipastikan ada
                    updateTotalPoints();

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
                        
                        // 1. Sembunyikan tombol reorder
                        if(btnReorder) btnReorder.classList.add('hidden');
                        
                        // 2. Batalkan mode edit jika lupa disimpan
                        if (window.isReorderMode) cancelVisualReorder();

                        // 3. PENTING: Hitung ulang poin agar kembali ke Total Area
                        // Gunakan timeout agar UI sempat refresh (menutup accordion) dulu
                        setTimeout(updateTotalPoints, 50); 
                        
                        // 4. Update Judul juga agar kembali ke nama Area
                        setTimeout(updateMapTitleWrapper, 50);
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
                
                // 2. PINDAHKAN KE WORKSPACE (BUKAN BODY)
                // Agar saat Fullscreen (Z-Index Max), dropdown tetap terlihat karena berada dalam container yang sama
               const workspace = document.getElementById('rbm-workspace');
                if (workspace) {
                    workspace.appendChild(dropdown);
                } else {
                    document.body.appendChild(dropdown);
                }
                
                // 3. Hitung Posisi Tombol
                const rect = btn.getBoundingClientRect();
                
                // 4. Set Posisi Fixed (Menempel pada layar, bukan container)
                dropdown.style.position = 'fixed';
                dropdown.style.zIndex = '2147483647'; // Paling Atas
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

        // K. TOGGLE SIDEBAR (EXPAND/COLLAPSE LIST)
        const sidebarBtn = e.target.closest('[data-action="toggle-sidebar"]');
        if (sidebarBtn) {
            e.preventDefault();
            
            const panelList = document.getElementById('panel-list');
            const openBtn = document.getElementById('btn-open-sidebar');
            const mapControls = document.getElementById('map-info-controls'); // Controls lama
            
            // Cek apakah sidebar sedang tersembunyi?
            const isHidden = panelList.classList.contains('hidden');

            if (!isHidden) {
                // TUTUP SIDEBAR -> MODE FULLSCREEN
                panelList.classList.add('hidden');
                if(openBtn) openBtn.classList.remove('hidden');
                updateMapControlsPosition(true); // Geser Kanan
            } else {
                // BUKA SIDEBAR -> MODE NORMAL
                panelList.classList.remove('hidden');
                if(openBtn) openBtn.classList.add('hidden');
                updateMapControlsPosition(false); // Reset Kiri
            }

            // Refresh Ukuran Peta
            setTimeout(() => {
                if (window.rbmMap) window.rbmMap.invalidateSize();
            }, 300); // Delay sesuaikan dengan durasi transition CSS (jika ada)

            return;
        }

    });


    // ============================================================
    // 3. EVENT LISTENERS LAIN (INPUT, CHANGE, SUBMIT)
    // ============================================================
    function handleKddkPartChange(e) {
        if (e.target.classList.contains('kddk-part')) {
            console.log("Input Changed:", e.target.id); // Debugging

            // Jika AREA berubah, update RUTE
            if (e.target.id === 'part_area') {
                window.updateRouteOptions(); 
            }
            
            // Selalu update sequence & preview string
            window.updateSequenceAndGenerate();
        }
    }
    
    document.addEventListener('input', function(e) {
        handleKddkPartChange(e);

        if (e.target.id === 'part_sisip') {
            e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 2);
        }

        if (e.target.id === 'kddk-search-input') handleKddkSearch(e.target.value);
    });

    document.addEventListener('change', function(e) {
        handleKddkPartChange(e);

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
            if (e.target.id === 'part_area') window.updateRouteOptions();
            window.updateSequenceAndGenerate();
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

        if (e.target.id === 'part_rute') {
            window.updateLabelDisplay();
        }

    });

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
    // FUNGSI LOAD MAP (FILTER VISUAL + ANOMALI + CACHE RAW)
    // ============================================================
    window.loadAreaMap = function(areaCode, callback = null) {
        const mapContainer = document.getElementById('rbm-map');
        const urlInput = document.getElementById('map-data-url');
        
        if (!mapContainer || !urlInput) return;

        // 1. CEK STALE MAP (Hapus Peta Lama jika Container Berubah)
        if (rbmMap && rbmMap.getContainer() !== mapContainer) {
            rbmMap.remove(); 
            rbmMap = null;
            // Bersihkan semua cache agar fresh
            for (const key in areaLayers) delete areaLayers[key];
            if (typeof areaRawData !== 'undefined') {
                for (const key in areaRawData) delete areaRawData[key];
            }
        }

        // 2. INIT MAP (Jika Belum Ada)
        if (!rbmMap) {
            mapContainer.innerHTML = ''; 
            rbmMap = L.map('rbm-map', { zoomControl: false }).setView([0.5071, 101.4478], 13);
            L.control.zoom({ position: 'bottomright' }).addTo(rbmMap);
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles © Esri'
            }).addTo(rbmMap);
            new ResizeObserver(() => { if (rbmMap) rbmMap.invalidateSize(); }).observe(mapContainer);
            mapContainer.addEventListener('click', e => { if (e.target.closest('a')) e.stopPropagation(); });
        }

        // ---------------------------------------------------------
        // FUNGSI RENDER INTERNAL (Dipanggil oleh Fetch atau Cache)
        // ---------------------------------------------------------
        window.renderPointsToMap = function(points) {
            // A. Cek Filter Rute (Accordion Terbuka)
            let targetRouteCode = window.currentOpenRouteCode || null;

            if (!targetRouteCode) {
                const openRouteIcon = document.querySelector('.route-header .icon-chevron-sub.rotate-180');
                if (openRouteIcon) {
                    const header = openRouteIcon.closest('.route-header');
                    if (header?.dataset?.routeCode) {
                        targetRouteCode = header.dataset.routeCode.trim();
                    }
                }
            }

            // B. Bersihkan Layer Lama (Visual)
            if (areaLayers[areaCode]) {
                if (rbmMap.hasLayer(areaLayers[areaCode])) rbmMap.removeLayer(areaLayers[areaCode]);
            }
            if (routeLineLayer) {
                rbmMap.removeLayer(routeLineLayer);
                routeLineLayer = null;
            }
            if (arrowLayer) {
                rbmMap.removeLayer(arrowLayer);
                arrowLayer = null;
            }
            // C. Setup Cluster Baru
            const newLayer = L.markerClusterGroup({
                disableClusteringAtZoom: 19, spiderfyOnMaxZoom: true, showCoverageOnHover: false, chunkedLoading: true, maxClusterRadius: 60
            });

            // Tooltip Cluster
            newLayer.on('clustermouseover', function (a) {
                const markers = a.layer.getAllChildMarkers();
                const routeCounts = {};
                markers.forEach(m => { const r = m.kddkData.route || '??'; routeCounts[r] = (routeCounts[r] || 0) + 1; });
                let content = '<div class="text-xs font-sans min-w-[100px]"><div class="font-bold border-b border-gray-400 mb-1 pb-1">Isi Cluster Ini:</div>';
                Object.keys(routeCounts).sort().forEach(r => {
                    content += `<div class="flex justify-between"><span>Rute ${r}</span><span class="bg-white text-black px-1 rounded ml-2 font-bold">${routeCounts[r]}</span></div>`;
                });
                content += '</div>';
                L.tooltip({ direction: 'top', className: 'bg-gray-900 text-white p-2 rounded', offset: [0, -10] }).setContent(content).setLatLng(a.latlng).addTo(rbmMap);
            });
            newLayer.on('clustermouseout', () => rbmMap.eachLayer(l => { if (l instanceof L.Tooltip) rbmMap.removeLayer(l); }));

            // D. Hitung Pusat Per Rute (Untuk Deteksi Anomali)
            const routeCenters = {}; 
            points.forEach(p => {
                const lat = parseFloat(p.lat); const lng = parseFloat(p.lng);
                const rCode = p.kddk ? String(p.kddk).substring(5, 7).trim() : '??';
                if (lat && lng) {
                    if (!routeCenters[rCode]) routeCenters[rCode] = { sumLat: 0, sumLng: 0, count: 0 };
                    routeCenters[rCode].sumLat += lat; routeCenters[rCode].sumLng += lng; routeCenters[rCode].count++;
                }
            });
            const finalCenters = {};
            for (const [r, d] of Object.entries(routeCenters)) {
                if (d.count > 0) finalCenters[r] = L.latLng(d.sumLat/d.count, d.sumLng/d.count);
            }

            // E. Loop Marker (Filter Visual & Cek Anomali)
            const colorMap = { 'A':'text-green-800 border-green-600', 'B':'text-blue-800 border-blue-600', 'C':'text-red-800 border-red-600', 'D':'text-yellow-800 border-yellow-600', 'E':'text-purple-800 border-purple-600', 'F':'text-pink-800 border-pink-600', 'G':'text-indigo-800 border-indigo-600' };
            const ANOMALY_THRESHOLD = 1000; // 1 KM
            const searchableData = []; // Data ringan untuk hitungan cepat
            let anomalyDebug = 0;

            // Urutkan points berdasarkan Sequence (seq) agar garisnya nyambung urut 001->002->003
            // Kita copy array dulu biar aman
            const sortedPoints = [...points].sort((a, b) => {
                return (parseInt(a.seq) || 0) - (parseInt(b.seq) || 0);
            });

            let lineCoordinates = [];

            sortedPoints.forEach(pt => {
                const routeCodeStr = pt.kddk ? String(pt.kddk).substring(5, 7).trim() : '??';

                // [FILTER VISUAL] Skip jika tidak sesuai filter rute
                if (targetRouteCode && targetRouteCode !== routeCodeStr) {
                    return; // Lewati iterasi ini
                }

                // if (pt.lat && pt.lng) {
                //     lineCoordinates.push([pt.lat, pt.lng]);
                // }

                const dayChar = pt.seq ? pt.seq.charAt(0) : 'A'; 
                let cls = colorMap[dayChar] || 'text-gray-800 border-gray-600';
                let isAnomaly = false;

                // Cek Anomali (Jarak ke pusat rutenya sendiri)
                const myCenter = finalCenters[routeCodeStr];
                if (myCenter && pt.lat && pt.lng) {
                    if (myCenter.distanceTo([pt.lat, pt.lng]) > ANOMALY_THRESHOLD) {
                        isAnomaly = true;
                        cls = 'marker-outlier animate-marker-pulse';
                        anomalyDebug++;
                    }
                }

                // Simpan ke Data Ringan (Hanya yg ditampilkan)
                searchableData.push({ route: routeCodeStr, isAnomaly: isAnomaly });

                // Buat Marker
                const icon = L.divIcon({ 
                    className: 'custom-map-marker', 
                    html: `<div class="flex items-center justify-center w-6 h-6 bg-white border-2 ${cls} rounded-full text-[9px] font-bold shadow-sm opacity-90">${pt.seq}</div>`,
                    iconSize: [24, 24] 
                });
                
                const marker = L.marker([pt.lat, pt.lng], { icon: icon });
                marker.kddkData = { route: routeCodeStr, idpel: pt.idpel, fullKddk: pt.kddk, isAnomaly: isAnomaly };
                marker.bindPopup(pt.info);
                marker.on('click', () => { if(window.isReorderMode){ marker.closePopup(); handleMarkerClickReorder(marker); } });
                newLayer.addLayer(marker);

                if (targetRouteCode && pt.lat && pt.lng) {
                    lineCoordinates.push([pt.lat, pt.lng]);
                }

            });

            // F. Simpan & Tampilkan Layer
            newLayer._searchableData = searchableData; // Simpan data ringan ke layer
            areaLayers[areaCode] = newLayer;
            rbmMap.addLayer(newLayer);

            if (targetRouteCode && lineCoordinates.length > 1) {
                
                // 1. Gambar Garis Kuning SOLID (Lebih Rapi untuk Jarak Dekat)
                routeLineLayer = L.polyline(lineCoordinates, {
                    color: '#FFD700',
                    weight: 4,          // LEBIH TEBAL → tidak putus saat dekat
                    opacity: 1,
                    lineCap: 'round',
                    lineJoin: 'round',
                    smoothFactor: 1.5
                }).addTo(rbmMap);

                // 2. Gambar Panah Arah
                if (typeof L.polylineDecorator === 'function' && routeLineLayer) {
                    try {
                        arrowLayer = L.polylineDecorator(routeLineLayer, {
                            patterns: [
                                {
                                    // Mulai dari 0 (titik pertama)
                                    offset: 0,
                                    
                                    // Jarak antar panah dilonggarkan sedikit (50px - 70px)
                                    // Agar saat di-zoom out tidak terlihat menumpuk semrawut
                                    repeat: '70px', 
                                    
                                    symbol: L.Symbol.arrowHead({
                                        pixelSize: 12,    // Ukuran panah proporsional
                                        polygon: true,
                                        pathOptions: { 
                                            stroke: true, 
                                            color: '#FF0000', // Merah
                                            fillColor: '#FF0000', 
                                            fillOpacity: 1,
                                            weight: 1 
                                        }
                                    })
                                }
                            ]
                        }).addTo(rbmMap);
                        
                        console.log("✅ Panah berhasil digambar untuk rute:", targetRouteCode);
                    } catch (e) {
                        console.error("❌ Error Gambar Panah:", e);
                    }
                } else {
                    console.warn("⚠️ Plugin Panah (L.polylineDecorator) atau L.Symbol belum dimuat.");
                }
            }

            if (!window.isReorderMode) {
                if (callback) callback();
                else fitBoundsToLayer(newLayer);
            }

            // Update UI Info
            updateMapTitleWrapper();
            updateTotalPoints(); 

            // Cek Posisi Sidebar
            const panelList = document.getElementById('panel-list');
            if(panelList && typeof updateMapControlsPosition === 'function') {
                updateMapControlsPosition(panelList.classList.contains('hidden'));
            }
        };

        // 3. LOGIKA UTAMA (FETCH ATAU AMBIL CACHE)
        // Cek apakah data mentah JSON sudah ada di Cache Global?
        if (typeof areaRawData !== 'undefined' && areaRawData[areaCode]) {
            // Data ada, langsung render ulang (Cepat)
            renderPointsToMap(areaRawData[areaCode]);
            return;
        }

        // 4. FETCH DATA BARU (AJAX)
        const countSpan = document.getElementById('map-count');
        if(countSpan) countSpan.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

        fetch(`${urlInput.value}?area=${areaCode}`)
            .then(res => res.json())
            .then(points => {
                // Simpan ke Cache Global (Jika variabel ada)
                if (typeof areaRawData !== 'undefined') {
                    areaRawData[areaCode] = points;
                }
                // Render Peta
                renderPointsToMap(points);
            })
            .catch(err => console.error("Map Error:", err));
    }

    function removeAreaMap(areaCode) {
        if (areaLayers[areaCode] && rbmMap) {
            rbmMap.removeLayer(areaLayers[areaCode]); 
        }
        // [BARU] Hapus Garis & Panah
        if (routeLineLayer && rbmMap) {
            rbmMap.removeLayer(routeLineLayer);
            routeLineLayer = null;
        }
        if (arrowLayer && rbmMap) {
            rbmMap.removeLayer(arrowLayer);
            arrowLayer = null;
        }

        updateTotalPoints();
        updateMapTitleWrapper();
    }

    function fitBoundsToLayer(layer) {
        if (layer && layer.getBounds().isValid()) {
            rbmMap.fitBounds(layer.getBounds().pad(0.1));
        }
    }

    window.updateTotalPoints = function() {
        const countSpan = document.getElementById('map-count');
        const alertBox = document.getElementById('anomaly-alert');
        const alertCount = document.getElementById('anomaly-count');
        
        if (!countSpan) return;
        
        let total = 0;
        let totalAnomalies = 0;
        
        // 1. CEK FILTER RUTE (Tanpa Error Variable Scope)
        let targetRouteCode = null;
        
        // Cari elemen header rute yang icon-nya sedang berputar (artinya terbuka)
        const openRouteIcon = document.querySelector('.route-header .icon-chevron-sub.rotate-180');

        if (openRouteIcon) {
            // Kita definisikan variabel header HANYA di dalam blok ini untuk ambil datanya
            const headerElement = openRouteIcon.closest('.route-header');
            if(headerElement && headerElement.dataset.routeCode) {
                targetRouteCode = String(headerElement.dataset.routeCode).trim();
            }
        }

        // Debugging aman (Pakai targetRouteCode, jangan pakai variabel header)
        // console.log("[MAP COUNT] Target Route:", targetRouteCode || "ALL AREA");

        // 2. LOOP SEMUA LAYER AKTIF
        Object.values(areaLayers).forEach(layer => {
            if (rbmMap && rbmMap.hasLayer(layer)) {
                
                // [FIX 0 MARKERS] 
                // Gunakan .getLayers() bukan getAllChildMarkers().
                // getLayers() mengambil data marker mentah yang sudah ada di memori (Synchronous),
                // sehingga tidak terpengaruh loading animasi cluster.
                const allMarkers = layer.getLayers();
                
                // console.log(`[MAP COUNT] Layer has ${allMarkers.length} markers.`);

                if (targetRouteCode) {
                    // === MODE FILTER RUTE ===
                    allMarkers.forEach(m => {
                        // Pastikan data marker ada
                        if (m.kddkData && m.kddkData.route) {
                            // Bandingkan String vs String agar aman
                            if (String(m.kddkData.route) === targetRouteCode) {
                                total++;
                                if (m.kddkData.isAnomaly) totalAnomalies++;
                            }
                        }
                    });
                } else {
                    // === MODE SEMUA (AREA) ===
                    total += allMarkers.length;
                    
                    // Cek anomali (tetap butuh loop)
                    allMarkers.forEach(m => {
                        if (m.kddkData && m.kddkData.isAnomaly) totalAnomalies++;
                    });
                }
            }
        });

        // console.log("[MAP COUNT] Final Total:", total);

        // 3. UPDATE UI TEKS
        countSpan.textContent = total + ' Titik';

        // 4. ALERT ANOMALI
        if (alertBox && alertCount) {
            if (totalAnomalies > 0) {
                alertCount.textContent = totalAnomalies;
                alertBox.classList.remove('hidden');
            } else {
                alertBox.classList.add('hidden');
            }
        }
    }

    window. updateMapTitleWrapper = function() {
        const titleEl = document.getElementById('map-context-title');
        if (!titleEl) return;

        // 1. Cek Prioritas TERTINGGI: Apakah ada RUTE yang sedang terbuka?
        // Kita cari icon chevron rute yang sedang berputar (rotate-180) di sidebar
        const openRouteIcon = document.querySelector('.route-header .icon-chevron-sub.rotate-180');
        
        if (openRouteIcon) {
            const header = openRouteIcon.closest('.route-header');
            // Ambil nama dari data-display-code (Misal: "RB A1") yang sudah ada di Blade
            const displayName = header.dataset.displayCode; 
            
            titleEl.textContent = displayName || 'Rute Terpilih';
            titleEl.classList.add('text-indigo-600'); // Kasih warna biru biar terlihat fokus
            return;
        }

        // 2. Prioritas KEDUA: Jika Rute tutup, cek apakah ada AREA yang terbuka?
        const openAreaIcon = document.querySelector('.area-header .icon-chevron.rotate-180');
        
        if (openAreaIcon) {
            const header = openAreaIcon.closest('.area-header');
            // Ambil nama dari data-display-code (Misal: "Area RB")
            const displayName = header.dataset.displayCode; 
            
            titleEl.textContent = displayName || 'Area Terpilih';
            titleEl.classList.remove('text-indigo-600'); // Warna standar
            return;
        }

        // 3. Default: Tidak ada yang terbuka (Posisi Awal / Semua Tertutup)
        titleEl.textContent = "Pilih Area/Rute di kiri";
        titleEl.classList.remove('text-indigo-600');
    }


    // ============================================================
    // 5. HELPER AJAX & MODAL SUKSES (PREMIUM)
    // ============================================================

    // 2. Tutup Modal & Refresh
    window.closeGenericSuccessModal = function() {
        const modal = document.getElementById('modal-success-generic');
        if (!modal) return;
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        if (typeof refreshActiveTab === 'function') {
            refreshActiveTab();
        }
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

    function handleGeneratorSubmit(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

        // Buat FormData standar
        const formData = new FormData(form);
        
        // [BARU] Cek apakah ada data koordinat 'titipan' dari proses upload tadi?
        if (window.tempUploadFullData && window.tempUploadFullData.length > 0) {
            
            // Ambil IDPEL yang sedang dipilih user saat ini
            const selectedIds = Array.from(window.selectionState.items.keys());
            
            // Mapping cepat IDPEL -> Koordinat
            const coordMap = {};
            window.tempUploadFullData.forEach(item => {
                if (item.lat && item.lng) {
                    coordMap[item.idpel] = { lat: item.lat, lng: item.lng };
                }
            });

            // Filter: Hanya kirim koordinat milik IDPEL yang sedang diproses
            const coordsToSend = {};
            let coordCount = 0;
            
            selectedIds.forEach(id => {
                if (coordMap[id]) {
                    coordsToSend[id] = coordMap[id];
                    coordCount++;
                }
            });
            
            // Tempelkan ke FormData sebagai JSON String
            if (coordCount > 0) {
                console.log(`Menyisipkan ${coordCount} koordinat ke payload.`);
                formData.append('coord_updates', JSON.stringify(coordsToSend));
            }
        }

        fetch(form.action, {
            method: 'POST',
            headers: { 
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            
            if (data.success) {
                const processedCount = selectionState.items.size;
                window.closeKddkModal();
                
                // Bersihkan State
                selectionState.items.clear();
                toggleGroupButton();
                document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
                const checkAll = document.getElementById('check-all-rows');
                if(checkAll) checkAll.checked = false;
                
                // Bersihkan memori temp upload
                window.tempUploadFullData = [];
                
                showSuccessModal(data, processedCount); 
            } else {
                alert('Gagal: ' + data.message);
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            if (err.status === 422) alert('Validasi Gagal. Cek kembali isian form.');
            else alert('Terjadi kesalahan sistem: ' + err);
            console.error(err);
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
    // 7. DRAG & DROP LOGIC (REVISI LAZY LOAD ELEMENT)
    // ============================================================
    let draggedIdpel = null;
    let originKddk = null;

    document.addEventListener('dragstart', function(e) {
        const row = e.target.closest('.draggable-idpel');
        if (row) {

            const id = row.dataset.idpel;

            draggedIdpel = id;
            originKddk = row.dataset.originPrefix; 

            row.classList.add('opacity-50', 'bg-yellow-100'); 
            e.dataTransfer.effectAllowed = 'move';

            e.dataTransfer.setData('text/plain', id);

            const removeZone = document.getElementById('remove-drop-zone');
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

        const removeZone = document.getElementById('remove-drop-zone');     
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
        if (!(e.target instanceof Element)) return;

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
        if (!(e.target instanceof Element)) return;
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
    if (!(e.target instanceof Element)) return;

    // Reset Visual Garis Biru
    document.querySelectorAll('.draggable-idpel').forEach(r => r.style.borderTop = "");

    // 1. AMBIL IDPEL YANG SEDANG DITARIK (HANDLE UTAMA)
    const transferId = e.dataTransfer.getData('text/plain');
    const finalIdpel = draggedIdpel || transferId; // ID item yang dipegang mouse

    // 2. IDENTIFIKASI DROP ZONE (TRASH)
    const trash = e.target.closest('.kddk-remove-zone');

    // === LOGIKA HAPUS (DROP DI TRASH) ===
    if (trash && finalIdpel) { 
        
        // A. CEK APAKAH INI BULK ACTION?
        // Syarat Bulk: 
        // 1. Ada item yang dicentang (.select-item-row:checked)
        // 2. Item yang sedang ditarik (finalIdpel) ADALAH SALAH SATU yang dicentang
        
        const allChecked = document.querySelectorAll('.select-item-row:checked');
        const bulkIds = Array.from(allChecked).map(cb => cb.value);
        
        // Cek apakah item yang ditarik ada di dalam daftar yang dicentang
        const isDragItemChecked = bulkIds.includes(finalIdpel);

        // --- SKENARIO 1: HAPUS BANYAK (BULK) ---
        if (bulkIds.length > 1 && isDragItemChecked) {
            const count = bulkIds.length;
            const url = document.getElementById('bulk-remove-route').value;
            
            const onConfirmBulk = () => {
                // Panggil API Bulk Remove
                executeAjax(url, { idpels: bulkIds });
                window.clearBulkSelection(); // Bersihkan centang setelah aksi
            };

            if(typeof App !== 'undefined' && App.Utils) {
                App.Utils.showCustomConfirm(
                    'Hapus Massal?', 
                    `Keluarkan <strong>${count}</strong> pelanggan terpilih dari grup?`, 
                    onConfirmBulk
                );
            } else if(confirm(`Hapus ${count} pelanggan terpilih?`)) {
                onConfirmBulk();
            }
            return; // Selesai, jangan lanjut ke single remove
        }

        // --- SKENARIO 2: HAPUS SATU (SINGLE) ---
        // Jalan jika tidak ada centang, ATAU yang ditarik bukan item yang dicentang
        const onConfirmSingle = () => performRemoveIdpel(finalIdpel);
        
        if(typeof App !== 'undefined' && App.Utils) {
            App.Utils.showCustomConfirm('Hapus?', `Keluarkan pelanggan ${finalIdpel}?`, onConfirmSingle);
        } else if(confirm(`Hapus ${finalIdpel}?`)) {
            onConfirmSingle();
        }
        return; 
    }

    // === LOGIKA PINDAH / REORDER (DROP DI TABEL LAIN) ===
    const dropZone = e.target.closest('.kddk-drop-zone');
    const targetRow = e.target.closest('.draggable-idpel');

    if (dropZone && finalIdpel) {
        const targetPrefix = dropZone.dataset.routePrefix;
        
        // KASUS A: PINDAH RUTE
        if (targetPrefix !== originKddk) {
            // Note: Jika ingin support Bulk Move via Drag, logika serupa bisa dipasang di sini
            performMoveIdpel(finalIdpel, targetPrefix);
        } 
        // KASUS B: REORDER (Urutkan Ulang dalam Rute Sama)
        else if (targetRow && targetRow.dataset.idpel !== finalIdpel) {
            const targetIdpel = targetRow.dataset.idpel;
            performReorderIdpel(finalIdpel, targetIdpel, targetPrefix);
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

        // Cek apakah mode Bulk (Banyak) atau Single (Satu)
        const isBulk = idpelEl.dataset.mode === 'bulk';

        const area = document.getElementById('move-area').value;
        const route = document.getElementById('move-route-select').value;

        // Ambil Prefix Unit dari input hidden yang baru kita tambahkan
        const unitPrefixInput = document.getElementById('ctx-unit-prefix'); 
        const unitPrefix = unitPrefixInput ? unitPrefixInput.value : ''; 

        // Validasi
        if (!area || !route) { 
            alert("Harap pilih Area dan Rute tujuan."); 
            return; 
        }

        // Susun Kode Tujuan
        // Pastikan Sub Unit default 'A' selalu ada
        const targetPrefix = `${unitPrefix}${area}${route}`;

        // Validasi Panjang Kode (Harus 7 Karakter: 3 Prefix + 1 Sub + 2 Area + 1 Rute/Hari)
        // Note: Sesuaikan validasi ini dengan format KDDK Unit Anda
        if (targetPrefix.length < 5) {
            alert("Gagal menyusun kode rute. Silakan refresh halaman.");
            return;
        }

        if (isBulk) {
            // === LOGIKA PINDAH BANYAK (BULK) ===
            const checked = document.querySelectorAll('.select-item-row:checked');
            const ids = Array.from(checked).map(cb => cb.value);

            if (ids.length === 0) {
                alert("Tidak ada pelanggan yang dicentang.");
                return;
            }

            const url = document.getElementById('bulk-move-route').value;

            // Eksekusi AJAX
            executeAjax(url, { idpels: ids, target_kddk: targetPrefix });

            // Bersihkan centang setelah sukses
            window.clearBulkSelection();
        } else {
            // === LOGIKA PINDAH SATU (SINGLE) ===
            const idpel = document.getElementById('ctx-selected-idpel').value;
            performMoveIdpel(idpel, targetPrefix);
        }
        // Tutup Modal
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
                        window.currentOpenRouteCode = routeCode;
                        setTimeout(() => {
                            refreshMapAfterReorder(areaCode);
                        }, 300);
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