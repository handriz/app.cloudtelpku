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
window.rbmMap = null;// Aman, ini cuma wadah kosong (placeholder)
//const areaLayers = {}; // Aman, object kosong
const activeRouteLayers = {}; //Menyimpan layer per rute
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

window.syncSelectionUI = function () {
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

window.toggleGroupButton = function () {
    const count = window.selectionState.items.size;
    const btn = document.getElementById('btn-group-kddk');
    const countSpan = document.getElementById('count-selected');

    if (btn) {
        if (count > 0) {
            btn.classList.remove('hidden');
            btn.innerHTML = `<i class="fas fa-layer-group mr-2"></i> Bentuk Group (${count})`;
        } else {
            btn.classList.add('hidden');
        }
    }
    if (countSpan) countSpan.textContent = count;
};

window.showToast = function (message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    // Tentukan Warna & Ikon
    let bgClass, iconClass, title;
    switch (type) {
        case 'success':
            bgClass = 'bg-white border-l-4 border-green-500 text-gray-800';
            iconClass = 'fas fa-check-circle text-green-500';
            title = 'Berhasil';
            break;
        case 'error':
            bgClass = 'bg-white border-l-4 border-red-500 text-gray-800';
            iconClass = 'fas fa-times-circle text-red-500';
            title = 'Error';
            break;
        case 'warning':
            bgClass = 'bg-white border-l-4 border-yellow-500 text-gray-800';
            iconClass = 'fas fa-exclamation-triangle text-yellow-500';
            title = 'Perhatian';
            break;
        default:
            bgClass = 'bg-white border-l-4 border-blue-500 text-gray-800';
            iconClass = 'fas fa-info-circle text-blue-500';
            title = 'Info';
    }

    // Buat Elemen HTML
    const toast = document.createElement('div');
    toast.className = `${bgClass} shadow-lg rounded-md p-4 flex items-start space-x-3 transform transition-all duration-300 translate-x-10 opacity-0 pointer-events-auto min-w-[300px] max-w-sm`;
    toast.innerHTML = `
        <div class="shrink-0 pt-0.5"><i class="${iconClass} text-xl"></i></div>
        <div class="flex-1">
            <h4 class="font-bold text-sm mb-1">${title}</h4>
            <p class="text-xs text-gray-600 leading-relaxed">${message}</p>
        </div>
        <button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
    `;

    // Masukkan ke Container
    container.appendChild(toast);

    // Animasi Masuk
    setTimeout(() => {
        toast.classList.remove('translate-x-10', 'opacity-0');
    }, 10);

    // Hilang Otomatis setelah 4 detik
    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-x-10');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
};

// 2. Tampilkan Konfirmasi Cantik (Pengganti Confirm)
window.showModernConfirm = function (title, message, onYesCallback) {
    const modal = document.getElementById('custom-confirm-modal');
    if (!modal) {
        if (confirm(message.replace(/<[^>]*>?/gm, ''))) onYesCallback(); // Fallback
        return;
    }

    // Isi Konten
    document.getElementById('custom-confirm-title').textContent = title;
    document.getElementById('custom-confirm-message').innerHTML = message;

    // Tampilkan Modal
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => modal.classList.remove('opacity-0'), 10);

    // Setup Tombol
    const btnYes = document.getElementById('custom-confirm-ok');
    const btnNo = document.getElementById('custom-confirm-cancel');
    const btnX = document.getElementById('custom-confirm-cancel-x');

    // Hapus event listener lama (agar tidak menumpuk)
    const newBtnYes = btnYes.cloneNode(true);
    btnYes.parentNode.replaceChild(newBtnYes, btnYes);

    // Event Klik YA
    newBtnYes.addEventListener('click', function () {
        closeModernConfirm();
        if (typeof onYesCallback === 'function') onYesCallback();
    });

    // Event Klik TIDAK / X
    const closeFunc = () => closeModernConfirm();
    btnNo.onclick = closeFunc;
    btnX.onclick = closeFunc;
};

function closeModernConfirm() {
    const modal = document.getElementById('custom-confirm-modal');
    if (!modal) return;

    // Animasi Fade Out
    modal.classList.add('opacity-0');

    // Tunggu animasi selesai baru sembunyikan total
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex'); // <--- PENTING: Hapus flex saat tutup
    }, 300);
}
/**
 * ====================================================================
 * 3. FUNGSI LOGIKA UPLOAD (MODAL & PROSES)
 * ====================================================================
 */

// A. Membuka Modal Upload
window.openUploadModal = function () {
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
    if (fileInput) fileInput.value = '';

    if (dropZone) dropZone.classList.remove('hidden');
    document.getElementById('upload-loading').classList.add('hidden');
    document.getElementById('upload-result-stats').classList.add('hidden');

    const btnApply = document.getElementById('btn-apply-upload');
    if (btnApply) {
        btnApply.disabled = true;
        btnApply.classList.add('opacity-50', 'cursor-not-allowed');
    }

    // Animasi Masuk
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.classList.add('flex', 'opacity-100');
        if (panel) {
            panel.classList.remove('scale-95');
            panel.classList.add('scale-100');
            window.setupDragDropListeners();
        }
    }, 10);
};

// B. Menutup Modal Upload
window.closeUploadModal = function () {
    const modal = document.getElementById('modal-upload-csv-preview');
    const panel = document.getElementById('upload-modal-panel');

    if (!modal) return;

    if (panel) {
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
window.processUploadedFile = function (file) {
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

    reader.onload = function (event) {
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
        if (loadingText) loadingText.textContent = "Memvalidasi Database...";

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
window.handleFileFromInput = function (input) {
    if (input.files && input.files[0]) {
        window.processUploadedFile(input.files[0]);
    }
};

// E. Setup Drag & Drop Listeners
window.setupDragDropListeners = function () {
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
window.applyUploadSelection = function () {
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

window.toggleManualSequence = function (checkbox) {
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

window.handleManualSequenceInput = function (input) {
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

window.openKddkModal = function () {
    const selectedIds = Array.from(window.selectionState.items.keys());

    if (selectedIds.length === 0) {
        alert("Pilih atau Upload minimal satu pelanggan.");
        return;
    }

    // 1. Isi Input Hidden
    const container = document.getElementById('hidden-inputs-container');
    if (container) {
        container.innerHTML = '';
        selectedIds.forEach(id => {
            const i = document.createElement('input');
            i.type = 'hidden';
            i.name = 'selected_idpels[]';
            i.value = id;
            container.appendChild(i);
        });
    }

    // 2. Tampilkan Modal dengan Animasi (FIXED)
    const modal = document.getElementById('modal-create-kddk');
    const panel = modal ? modal.querySelector('div') : null; // Ambil container dalam (kartu)

    if (modal) {
        // Hapus hidden dulu agar elemen dirender
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        // Delay sangat kecil agar transisi CSS opacity berjalan
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.classList.add('opacity-100');

            if (panel) {
                panel.classList.remove('scale-95');
                panel.classList.add('scale-100');
            }
        }, 10);

        // Update Label Jumlah
        const countLabel = document.getElementById('count-selected');
        const countDisplay = document.getElementById('count-display');
        if (countLabel) countLabel.textContent = selectedIds.length;
        if (countDisplay) countDisplay.textContent = selectedIds.length;

        // Preview Urutan (3 Item Pertama)
        const previewList = document.getElementById('sequence-preview-list');
        if (previewList) {
            previewList.innerHTML = '';
            selectedIds.slice(0, 3).forEach((id, idx) => {
                previewList.innerHTML += `
                    <div class="flex justify-between text-[10px] text-gray-600 border-b border-gray-100 dark:border-gray-700 py-1 font-mono">
                        <span>Urut #${(idx + 1).toString().padStart(3, '0')}</span>
                        <span class="font-bold text-gray-800 dark:text-gray-300">${id}</span>
                    </div>`;
            });
            if (selectedIds.length > 3) {
                previewList.innerHTML += `<div class="text-[10px] text-gray-400 text-center mt-1 font-italic">... dan ${selectedIds.length - 3} lainnya</div>`;
            }
        }
    }

    // Trigger update sequence logic
    if (typeof window.updateSequenceAndGenerate === 'function') {
        window.updateSequenceAndGenerate();
    }
};

window.closeKddkModal = function () {
    const modal = document.getElementById('modal-create-kddk');
    const panel = modal ? modal.querySelector('div') : null;

    if (modal) {
        // Animasi Keluar
        modal.classList.remove('opacity-100');
        modal.classList.add('opacity-0');

        if (panel) {
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

window.executeAjax = function (url, bodyData) {
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
                if (typeof window.showGenericSuccess === 'function') window.showGenericSuccess(data.message);
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

window.performMoveIdpel = function (idpel, targetKddk) {
    const urlInput = document.getElementById('move-route');
    if (!urlInput) return;
    window.executeAjax(urlInput.value, { idpel: idpel, target_kddk: targetKddk });
};

window.performRemoveIdpel = function (idpel) {
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

            if (typeof App !== 'undefined' && App.Utils) {
                showGenericWarning(`
                    <strong>Gagal menghapus data</strong><br>
                    <span class="text-xs text-gray-500">${error.message}</span>
                `);
            } else {
                alert("Gagal: " + error.message);
            }
        });
};

window.refreshActiveTab = function (successMessage = null) {
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
    if (refreshUrl) {
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

            // 3. [PERBAIKAN PETA & DATA - VERSI MULTI LAYER]
            setTimeout(() => {
                console.log("[MAP REFRESH] Memulai reset peta...");

                // A. Reset Variabel Peta Global
                if (window.rbmMap) {
                    try { window.rbmMap.remove(); } catch (e) { }
                    window.rbmMap = null;
                }
                // Reset Layer Cache
                for (const key in activeRouteLayers) delete activeRouteLayers[key];

                // B. Init Peta Baru (Cukup panggil sekali lewat toggleRouteLayer nanti)
                // Cari semua Rute yang sedang TERBUKA (Accordion aktif)
                const openRoutes = newContent.querySelectorAll('.route-header .icon-chevron-sub.rotate-180');

                if (openRoutes.length > 0) {
                    console.log(`[MAP REFRESH] Menemukan ${openRoutes.length} rute terbuka.`);

                    openRoutes.forEach(header => {
                        const areaCode = header.dataset.areaCode;
                        const routeCode = header.dataset.routeCode;

                        // Load Layer untuk setiap rute yang terbuka
                        if (typeof window.toggleRouteLayer === 'function') {
                            window.toggleRouteLayer(areaCode, routeCode, true);
                        }
                    });
                } else {
                    // Jika tidak ada rute terbuka, inisialisasi peta kosong agar tidak blank
                    const mapContainer = document.getElementById('rbm-map');
                    if (mapContainer && !window.rbmMap) {
                        mapContainer.innerHTML = '';
                        window.rbmMap = L.map('rbm-map', { zoomControl: false }).setView([0.5071, 101.4478], 13);
                        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                            attribution: 'Tiles © Esri'
                        }).addTo(window.rbmMap);
                    }
                }

                // C. Update Judul & Titik
                setTimeout(() => {
                    if (typeof updateMapTitleWrapper === 'function') updateMapTitleWrapper();
                    if (typeof updateTotalPoints === 'function') updateTotalPoints();
                }, 1000);

            }, 1000); // Delay agar HTML stabil

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
                        if (txt) txt.textContent = "Show Map";
                    }
                }
            }

            const newScroll = newContent.querySelector('.overflow-y-auto');
            if (newScroll) newScroll.scrollTop = state.scroll;

            if (successMessage) {
                const notif = newContent.querySelector('#kddk-notification-container');
                if (notif) {
                    notif.innerHTML = `<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 animate-fade-in-down"><i class="fas fa-check-circle mr-2"></i><b>${successMessage}</b></div>`;
                    setTimeout(() => { if (notif.firstChild) notif.firstChild.remove(); }, 5000);
                } else {
                    alert(successMessage);
                }
            }
        });
    }
};

window.performReorderIdpel = function (idpel, targetIdpel, prefix) {
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
    console.log('[MAP] Refreshing Route Layer:', areaCode);

    // Ambil kode rute yang sedang aktif (disimpan saat saveVisualReorder)
    const routeCode = window.currentOpenRouteCode;

    if (areaCode && routeCode) {
        const layerKey = `${areaCode}-${routeCode}`;

        // 1. Hapus Layer Lama jika ada
        if (activeRouteLayers[layerKey] && window.rbmMap) {
            window.rbmMap.removeLayer(activeRouteLayers[layerKey]);
            delete activeRouteLayers[layerKey];
        }

        // 2. Load Ulang Layer Baru (Pakai Fungsi Baru)
        // Parameter true artinya "Sedang Membuka/Refresh"
        if (typeof window.toggleRouteLayer === 'function') {
            window.toggleRouteLayer(areaCode, routeCode, true);
        }
    }
}

/**
 * ====================================================================
 * 5. LOGIKA GENERATOR KDDK (SEQUENCE & PREFIX)
 * Dibuat Global agar bisa dipanggil oleh openKddkModal dan Event Listener
 * ====================================================================
 */

// A. Helper: Mengambil 7 Digit Prefix (UP3 + ULP + SUB + AREA + RUTE)
window.getPrefix7 = function () {
    const ids = ['part_up3', 'part_ulp', 'part_sub', 'part_area', 'part_rute'];
    const parts = ids.map(id => {
        const el = document.getElementById(id);
        if (!el) return '';
        let val = el.value.toUpperCase();
        if (id === 'part_area' && val.length !== 2) return '';
        if (id === 'part_rute' && val.length !== 2) return '';
        if (id === 'part_sub' && val.length !== 1) return '';
        return val;
    }).join('');
    // Harus pas 7 karakter
    return parts.length === 7 ? parts : null;
}

// B. Logic Utama: Cek Sequence ke Server & Update UI
window.updateSequenceAndGenerate = function () {
    const isManual = document.getElementById('mode_insert_sequence')?.checked;
    if (isManual) {
        window.generateFinalCode();
        return;
    }

    window.generateFinalCode();

    const count = window.selectionState.items.size;
    const countDisplay = document.getElementById('count-display');
    if (countDisplay) countDisplay.textContent = count;

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
            .then(r => r.json())
            .then(d => {
                if (d.sequence) {
                    // Server mengembalikan sequence terakhir + 1 (misal: 001)
                    urutInput.value = d.sequence;

                    // Update Preview Range (Start - End)
                    const startSeq = parseInt(d.sequence);
                    const endSeq = startSeq + count - 1;
                    const pStart = document.getElementById('preview-start');
                    const pEnd = document.getElementById('preview-end');
                    const sisipEl = document.getElementById('part_sisip');
                    const sisip = (sisipEl ? sisipEl.value : '00').padStart(2, '0');

                    if (pStart) pStart.textContent = `${prefix7}${d.sequence}${sisip}`;
                    if (pEnd) pEnd.textContent = `${prefix7}${endSeq.toString().padStart(3, '0')}${sisip}`;
                    window.generateFinalCode();
                }
            })
            .catch(e => {
                if (e.name !== 'AbortError') console.error("Sequence Error:", e);
            });
    }
};

// C. Logic Visual: Menggabungkan semua input menjadi string KDDK final
window.generateFinalCode = function () {
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

    if (!preview || !btn) return;
    const up3 = elUp3 ? (elUp3.value || '_') : '_';
    const ulp = elUlp ? (elUlp.value || '_') : '_';
    const sub = elSub ? (elSub.value || '_') : '_';
    const area = elArea ? (elArea.value || '__') : '__';
    const rute = elRute ? (elRute.value || '__') : '__';
    const urut = elUrut ? (elUrut.value || '___') : '___';
    const sisipVal = (elSisip && elSisip.value ? elSisip.value : '00');
    const sisip = sisipVal.padStart(2, '0');

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
        if (err) {
            err.textContent = "Format Valid ✅";
            err.className = "text-xs text-center text-green-600 mt-1 h-4";
        }

        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
        // Tidak Valid
        if (err) {
            err.textContent = "Lengkapi data area & rute...";
            err.className = "text-xs text-center text-red-500 mt-1 h-4";
        }
        btn.disabled = true;
        btn.classList.add('opacity-50', 'cursor-not-allowed');
    }
};

// D. Helper Dropdown Area/Rute (Agar Label muncul)
window.updateRouteOptions = function () {
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
        } catch (e) { }
    }
};

window.updateLabelDisplay = function () {
    const routeSelect = document.getElementById('part_rute');
    const routeLabelDisplay = document.getElementById('rute-label-display');
    if (routeSelect && routeLabelDisplay) {
        const selectedOption = routeSelect.options[routeSelect.selectedIndex];
        if (selectedOption && selectedOption.value) routeLabelDisplay.textContent = selectedOption.dataset.label || '';
        else routeLabelDisplay.textContent = '';
    }
    window.updateSequenceAndGenerate();
}

window.loadRouteTableData = function (targetId, area, route) {
    const tbody = document.getElementById(`tbody-${targetId}`);
    const apiUrl = document.getElementById('api-route-table').value;
    const container = document.getElementById(targetId);

    if (!tbody || !apiUrl) return;

    let skeletonHtml = '';
    for (let i = 0; i < 3; i++) {
        skeletonHtml += `
                <tr class="animate-pulse border-b border-gray-100 dark:border-gray-700">
                    <td class="p-2 text-center"><div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-4 mx-auto"></div></td> <td class="p-2 text-center"><div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-3 mx-auto"></div></td> <td class="p-2"><div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-8"></div></td>        <td class="p-2"><div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-24"></div></td>       <td class="p-2"><div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-16"></div></td>       <td class="p-2"><div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-12"></div></td>       </tr>
            `;
    }

    // Tampilkan Spinner
    tbody.innerHTML = skeletonHtml;

    const minTime = new Promise(resolve => setTimeout(resolve, 500));
    const fetchData = fetch(`${apiUrl}?area=${area}&route=${route}`).then(res => res.text());
    // Fetch
    Promise.all([fetchData, minTime])
        .then(([html]) => {
            // Baru update UI dengan data asli setelah minimal 500ms berlalu
            tbody.innerHTML = html;

            // Tandai container sudah ter-load agar tidak request ulang saat ditutup-buka
            if (container) container.dataset.loaded = "true";
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = `<tr><td colspan="6" class="text-center text-red-500 text-xs p-4">Gagal memuat data.</td></tr>`;
        });
};

window.showGenericSuccess = function (message) {
    const modal = document.getElementById('modal-success-generic');
    const msgEl = document.getElementById('generic-success-message');
    if (modal && msgEl) {
        msgEl.textContent = message;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        const okBtn = modal.querySelector('button');
        if (okBtn) setTimeout(() => okBtn.focus(), 100);
    } else {
        alert(message);
        if (typeof refreshActiveTab === 'function') refreshActiveTab();
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

window.updateMapControlsPosition = function (isSidebarClosed) {
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

    window.updateBreadcrumb = function (displayCode) {
        const displayEl = document.getElementById('live-kddk-display');

        // [SAFETY CHECK 1] Pastikan elemen ada sebelum lanjut
        if (displayEl && displayCode) {

            // Mulai Animasi Fade Out
            displayEl.style.transition = 'opacity 0.2s';
            displayEl.style.opacity = 0;

            setTimeout(() => {
                // [SAFETY CHECK 2] Cek lagi apakah elemen MASIH ada?
                // (Penting saat user melakukan refresh/navigasi cepat)
                if (displayEl) {
                    displayEl.textContent = displayCode;
                    displayEl.style.opacity = 1;
                }
            }, 200);
        }
    };

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

    document.addEventListener('click', function (e) {

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
            const targetId = areaHeader.dataset.target;
            const content = document.getElementById(targetId);
            const icon = areaHeader.querySelector('.icon-chevron');

            if (content) {
                // 1. Lakukan Perubahan Visual (Toggle Class)
                const isNowHidden = content.classList.toggle('hidden');

                // 2. Putar Icon (Ini penting karena fungsi Title mendeteksi class ini!)
                if (icon) icon.classList.toggle('rotate-180');

                if (!isNowHidden) {
                    // === KASUS: MEMBUKA AREA ===
                    const displayCode = areaHeader.dataset.displayCode;
                    if (displayCode) updateBreadcrumb(displayCode);

                    // Panggil update judul (Delay 10ms cukup agar DOM terupdate)
                    setTimeout(() => window.updateMapTitleWrapper(), 10);

                } else {
                    // === KASUS: MENUTUP AREA ===
                    // 1. Bersihkan Layer Peta (Memori)
                    const areaCode = areaHeader.dataset.areaCode;
                    if (typeof activeRouteLayers !== 'undefined') {
                        Object.keys(activeRouteLayers).forEach(key => {
                            if (key.startsWith(areaCode + '-')) {
                                if (window.rbmMap) window.rbmMap.removeLayer(activeRouteLayers[key]);
                                delete activeRouteLayers[key];
                            }
                        });
                    }

                    // 2. Reset UI Anak (Tutup rute di dalamnya)
                    content.querySelectorAll('[id^="route-"]').forEach(el => el.classList.add('hidden'));
                    content.querySelectorAll('.icon-chevron-sub').forEach(el => el.classList.remove('rotate-180'));

                    // 3. Update Judul & Poin
                    updateTotalPoints();
                    setTimeout(() => window.updateMapTitleWrapper(), 10);
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
                if (icon) icon.classList.toggle('rotate-180');
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


            const content = document.getElementById(targetId);
            if (content) {
                // 1. Toggle Tampilan Accordion (HTML)
                const isHidden = content.classList.toggle('hidden');
                const icon = routeHeader.querySelector('.icon-chevron-sub');
                if (icon) icon.classList.toggle('rotate-180');

                // 3. Panggil Fungsi Peta Multi-Layer (Ganti loadAreaMap)
                const isOpening = !isHidden;

                // 3. Panggil Fungsi Peta Multi-Layer (Ganti loadAreaMap)
                if (typeof window.toggleRouteLayer === 'function') {
                    window.toggleRouteLayer(areaCode, routeCode, isOpening);
                }

                // 4. Logika UI Tambahan
                const btnReorder = document.getElementById('map-visual-controls');

                if (isOpening) {
                    // === SAAT DIBUKA ===

                    // A. Update Breadcrumb
                    if (displayCode && typeof updateBreadcrumb === 'function') {
                        updateBreadcrumb(displayCode);
                    }

                    // B. Load Tabel Pelanggan (AJAX Lazy Load)
                    if (content.dataset.loaded === "false" && typeof loadRouteTableData === 'function') {
                        loadRouteTableData(targetId, areaCode, routeCode);
                    }

                    // C. Tampilkan Tombol Visual Reorder
                    if (btnReorder) btnReorder.classList.remove('hidden');

                } else {
                    // === SAAT DITUTUP ===

                    // A. Cek apakah masih ada rute lain yang terbuka?
                    // Jika tidak ada rute lain yg terbuka, sembunyikan tombol reorder
                    const anyRouteOpen = document.querySelector('.route-header .icon-chevron-sub.rotate-180');
                    if (!anyRouteOpen && btnReorder) {
                        btnReorder.classList.add('hidden');
                    }

                    // B. Matikan Mode Reorder jika user lupa mematikannya
                    if (window.isReorderMode && typeof cancelVisualReorder === 'function') {
                        cancelVisualReorder();
                    }
                }
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
                if (typeof rbmMap !== 'undefined' && rbmMap) rbmMap.invalidateSize();
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
            if (areaSelect) areaSelect.value = "";
            if (routeSelect) {
                routeSelect.innerHTML = '<option value="">-- Pilih Area Dulu --</option>';
                routeSelect.disabled = true;
            }

            if (moveModal) {
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
                if (confirm(`Keluarkan ${idpel}?`)) performRemoveIdpel(idpel);
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
                if (openBtn) openBtn.classList.remove('hidden');
                updateMapControlsPosition(true); // Geser Kanan
            } else {
                // BUKA SIDEBAR -> MODE NORMAL
                panelList.classList.remove('hidden');
                if (openBtn) openBtn.classList.add('hidden');
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

    document.addEventListener('input', function (e) {
        handleKddkPartChange(e);

        if (e.target.id === 'part_sisip') {
            e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 2);
        }

        if (e.target.id === 'kddk-search-input') handleKddkSearch(e.target.value);
    });

    document.addEventListener('change', function (e) {
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
    document.addEventListener('submit', function (e) {

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

    window.toggleRouteLayer = function (areaCode, routeCode, isOpening) {
        const mapContainer = document.getElementById('rbm-map');
        const urlInput = document.getElementById('map-data-url');
        const layerKey = `${areaCode}-${routeCode}`;

        // 0. INIT MAP (Jika belum ada)
        if (!window.rbmMap) {
            mapContainer.innerHTML = '';

            // 1. Definisi Base Layers
            const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles © Esri',
                maxZoom: 19
            });

            const street = L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
                attribution: 'Map data © Google',
                maxZoom: 20
            });

            // 2. Buat Peta (Default Satelit)
            window.rbmMap = L.map('rbm-map', {
                zoomControl: false,
                fadeAnimation: true,
                layers: [satellite] // Default layer
            }).setView([0.5071, 101.4478], 13);

            // 3. Posisi Zoom Control di Kanan Bawah
            L.control.zoom({ position: 'bottomright' }).addTo(window.rbmMap);

            // 4. Tambah Tombol Ganti Layer (PINDAH KE KANAN BAWAH)
            const baseMaps = {
                "Satelit (Esri)": satellite,
                "Jalan (Google)": street
            };
            // Kita taruh di bottomright, dia akan otomatis antri dengan tombol Zoom
            L.control.layers(baseMaps, null, { position: 'bottomright' }).addTo(window.rbmMap);

            // 5. Tambah Legenda (Kiri Bawah)
            const legend = L.control({ position: 'bottomleft' });
            legend.onAdd = function (map) {
                const div = L.DomUtil.create('div', 'info legend bg-white p-2 rounded shadow text-xs border border-gray-300 opacity-90');
                div.innerHTML = `
                    <div class="font-bold mb-1 border-b pb-1">Keterangan Marker</div>
                    <div class="flex items-center mb-1">
                        <span class="w-3 h-3 rounded-full border border-blue-600 bg-white mr-2 shadow-sm"></span>
                        <span>Normal</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-3 h-3 rounded-full border border-white bg-orange-500 mr-2 shadow-sm relative">
                            <span class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 bg-red-600 rounded-full border border-white"></span>
                        </span>
                        <span>Duplikat (Anomali)</span>
                    </div>
                `;
                return div;
            };
            legend.addTo(window.rbmMap);

            new ResizeObserver(() => { if (window.rbmMap) window.rbmMap.invalidateSize(); }).observe(mapContainer);
        }

        // === SKENARIO 1: MENUTUP RUTE (CLEANUP TOTAL) ===
        if (!isOpening) {
            // Cek apakah layer ada di memori?
            if (activeRouteLayers[layerKey]) {
                // 1. Hapus dari Peta Visual
                window.rbmMap.removeLayer(activeRouteLayers[layerKey]);
                // 2. HAPUS DARI MEMORI (PENTING AGAR TIDAK TERHITUNG LAGI)
                delete activeRouteLayers[layerKey];

                console.log(`[MAP] Rute ${layerKey} ditutup & dihapus dari memori.`);
            }

            // 3. Update UI Segera
            updateTotalPoints();      // Hitung ulang (sekarang pasti berkurang)
            updateMapTitleWrapper();  // Cek judul lagi
            return;
        }

        // === SKENARIO 2: MEMBUKA RUTE YANG SUDAH ADA (CACHE) ===
        if (activeRouteLayers[layerKey]) {
            const existingLayer = activeRouteLayers[layerKey];
            setTimeout(() => {
                window.rbmMap.invalidateSize();
                if (existingLayer.getBounds().isValid()) window.rbmMap.fitBounds(existingLayer.getBounds().pad(0.1));
            }, 300);

            // Update UI (Penting jika user pindah tab lalu balik lagi)
            updateTotalPoints();
            updateMapTitleWrapper();
            return;
        }

        // === SKENARIO 3: FETCH DATA BARU ===
        const fetchUrl = `${urlInput.value}?area=${areaCode}&route=${routeCode}`;

        fetch(fetchUrl)
            .then(res => res.json())
            .then(points => {
                // A. Wadah Layer
                const clusterGroup = L.markerClusterGroup({ disableClusteringAtZoom: 19, spiderfyOnMaxZoom: true, chunkedLoading: true });
                const mainLayer = L.featureGroup();

                // B. Proses Data
                const style = getRouteStyle(routeCode);
                const lineCoords = [];

                points.sort((a, b) => (parseInt(a.seq) || 0) - (parseInt(b.seq) || 0)).forEach(pt => {
                    if (!pt.lat || !pt.lng) return;

                    let bgClass = "bg-white";          // Default: Putih
                    let textClass = "text-gray-700";   // Default: Teks Abu
                    let borderClass = "border-blue-600"; // Default: Border Biru
                    let extraHtml = "";

                    // JIKA DUPLIKAT TERDETEKSI
                    if (pt.is_duplicate) {
                        bgClass = "bg-orange-500";     // Ubah jadi ORANYE
                        textClass = "text-white";      // Teks Putih
                        borderClass = "border-white";  // Border Putih

                        // Tambahkan titik merah kecil di pojok kanan atas marker
                        extraHtml = `<div class="absolute -top-1 -right-1 w-2 h-2 bg-red-600 rounded-full border border-white"></div>`;
                    }

                    // Render Icon Leaflet
                    const icon = L.divIcon({
                        className: 'custom-map-marker',
                        html: `
                            <div class="relative flex items-center justify-center w-6 h-6 ${bgClass} ${textClass} border-2 ${borderClass} rounded-full text-[9px] font-bold shadow-sm opacity-90 transition transform hover:scale-110">
                                ${pt.seq}
                                ${extraHtml}
                            </div>`,
                        iconSize: [24, 24]
                    });

                    const marker = L.marker([pt.lat, pt.lng], { icon: icon });

                    const popupContent = `
                        <div class="text-xs min-w-[200px]">
                            
                            <div class="mb-2">
                                ${pt.info} 
                            </div>
                            
                            <div class="mt-2 pt-2 border-t border-gray-200">
                                <button onclick="window.enableMarkerDrag('${pt.idpel}')" 
                                    class="w-full inline-flex items-center justify-center bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 px-2 py-1.5 rounded transition text-[10px] font-bold">
                                    <i class="fas fa-arrows-alt mr-1.5"></i> Geser Posisi
                                </button>
                            </div>

                        </div>
                    `;

                    marker.bindPopup(popupContent);

                    if (!window.markerRegistry) window.markerRegistry = {};
                    window.markerRegistry[pt.idpel] = marker;

                    marker.kddkData = {
                        route: routeCode,
                        area: areaCode,
                        idpel: pt.idpel, // Wajib ada
                        fullKddk: pt.kddk // Wajib ada
                    };

                    if (pt.is_duplicate) marker.kddkData.isAnomaly = true;// Asumsi data backend kirim is_anomaly

                    marker.on('click', (e) => {
                        // Jika mode reorder aktif, matikan popup dan jalankan logika reorder
                        if (window.isReorderMode) {
                            marker.closePopup(); // Tutup popup info pelanggan
                            e.originalEvent.preventDefault(); // Cegah aksi default
                            handleMarkerClickReorder(marker); // Panggil fungsi reorder
                        }
                    });
                    clusterGroup.addLayer(marker);
                    lineCoords.push([pt.lat, pt.lng]);
                });

                // C. Garis & Panah
                if (lineCoords.length > 1) {
                    const routeLine = L.polyline(lineCoords, { color: style.line, weight: 3, opacity: 0.8, dashArray: '10, 10' });
                    mainLayer.addLayer(routeLine);
                    try {
                        const arrowLayer = L.polylineDecorator(routeLine, {
                            patterns: [{ offset: 25, repeat: 100, symbol: L.Symbol.arrowHead({ pixelSize: 14, polygon: true, pathOptions: { stroke: true, color: style.line, weight: 1, fill: true, fillColor: '#ecf409ff', fillOpacity: 1 } }) }]
                        });
                        mainLayer.addLayer(arrowLayer);
                    } catch (e) { }
                }

                mainLayer.addLayer(clusterGroup);

                // D. SIMPAN KE MEMORI & TAMPILKAN
                activeRouteLayers[layerKey] = mainLayer;
                window.rbmMap.addLayer(mainLayer);

                // E. UPDATE UI SETELAH DATA MASUK (WAJIB)
                updateTotalPoints();
                updateMapTitleWrapper();

                // F. Zoom
                setTimeout(() => {
                    window.rbmMap.invalidateSize();
                    if (mainLayer.getBounds().isValid()) window.rbmMap.fitBounds(mainLayer.getBounds().pad(0.1), { animate: true, duration: 1 });
                }, 400);
            })
            .catch(err => console.error("Load Route Error:", err));
    };

    function removeAreaMap(areaCode) {
        // Hapus semua layer yang ada di peta saat ini
        if (window.rbmMap) {
            rbmMap.eachLayer((layer) => {
                if (layer instanceof L.MarkerClusterGroup || layer instanceof L.Polyline) {
                    rbmMap.removeLayer(layer);
                }
            });
        }
        // Reset variabel global
        routeLineLayer = null;
        arrowLayer = null;
        updateTotalPoints();
        updateMapTitleWrapper();
    }

    function fitBoundsToLayer(layer) {
        if (layer && layer.getBounds().isValid()) {
            rbmMap.fitBounds(layer.getBounds().pad(0.1));
        }
    }

    // ============================================================
    // UPDATE TOTAL POIN (FIX STRUKTUR BARU)
    // ============================================================
    window.updateTotalPoints = function () {
        const countSpan = document.getElementById('map-count');

        if (!countSpan) return;

        let total = 0;
        let totalAnomalies = 0;

        // Hitung Total dari Layer Aktif
        if (typeof activeRouteLayers !== 'undefined') {
            Object.values(activeRouteLayers).forEach(mainLayer => {
                mainLayer.eachLayer(subLayer => {
                    if (subLayer instanceof L.MarkerClusterGroup) {
                        const markers = subLayer.getLayers();
                        total += markers.length;
                        // Hitung anomali sekalian
                        markers.forEach(m => {
                            if (m.kddkData && m.kddkData.isAnomaly) totalAnomalies++;
                        });
                    }
                });
            });
        }

        // UPDATE UI
        // Jika 0, tulis "0 Titik" saja (JANGAN "Pilih Rute" lagi)
        countSpan.textContent = total + ' Titik';

        // Update Alert Anomali (Opsional jika Anda pakai)
        const alertBox = document.getElementById('anomaly-alert');
        const alertCount = document.getElementById('anomaly-count');

        if (alertBox && alertCount) {
            if (totalAnomalies > 0) {
                alertCount.textContent = totalAnomalies;
                alertBox.classList.remove('hidden');
            } else {
                alertBox.classList.add('hidden');
            }
        }
    };

    // ============================================================
    // UPDATE JUDUL PETA
    // ============================================================
    window.updateMapTitleWrapper = function () {
        const titleEl = document.getElementById('map-title-text');
        const subTitleEl = document.getElementById('map-subtitle-text');

        if (!titleEl) return;

        // 1. CEK RUTE YANG AKTIF
        const activeKeys = (typeof activeRouteLayers !== 'undefined') ? Object.keys(activeRouteLayers) : [];

        if (activeKeys.length > 0) {
            if (activeKeys.length === 1) {
                // Split Key "18111-A1"
                const parts = activeKeys[0].split('-');
                const areaCode = parts[0];
                const routeCode = parts.length > 1 ? parts[1] : parts[0];

                // Cari Nama Area Cantik (Opsional)
                let areaDisplayName = areaCode;
                const areaBtn = document.querySelector(`.area-header[data-area-code="${areaCode}"]`);
                if (areaBtn) areaDisplayName = areaBtn.dataset.displayCode || areaCode;

                // === PERUBAHAN DISINI: PISAH BARIS ===
                // Gunakan innerHTML untuk membuat baris baru
                titleEl.innerHTML = `
                    <div class="text-gray-600">Area: ${areaDisplayName}</div>
                    <div class="text-indigo-600 text-[12px] mt-0.5">Rute: ${routeCode}</div>
                `;

                if (subTitleEl) subTitleEl.textContent = "Detail Pelanggan";

            } else {
                // Banyak Rute
                titleEl.innerHTML = `<div class="text-indigo-600">Gabungan ${activeKeys.length} Rute</div>`;
                if (subTitleEl) subTitleEl.textContent = "Mode Multi-Layer";
            }
            return;
        }

        // 2. CEK AREA YANG TERBUKA
        const activeAreaIcon = document.querySelector('[data-action="toggle-area-map"] .icon-chevron.rotate-180');

        if (activeAreaIcon) {
            const btnArea = activeAreaIcon.closest('[data-action="toggle-area-map"]');
            if (btnArea) {
                let areaName = btnArea.dataset.displayCode || btnArea.dataset.areaCode;
                if (!areaName) {
                    areaName = btnArea.innerText.replace(/\s+/g, ' ').trim().replace('Area ', '').substring(0, 15);
                }

                // Tampilan Area Saja
                titleEl.innerHTML = `<div class="text-gray-800">Area: ${areaName}</div>`;
                if (subTitleEl) subTitleEl.textContent = "Klik rute untuk detail";
                return;
            }
        }

        // 3. DEFAULT
        titleEl.innerHTML = "Peta Wilayah";
        if (subTitleEl) subTitleEl.textContent = "Pilih menu di kiri";
    };


    // ============================================================
    // 5. HELPER AJAX & MODAL SUKSES (PREMIUM)
    // ============================================================

    // 2. Tutup Modal & Refresh
    window.closeGenericSuccessModal = function () {
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
                    if (checkAll) checkAll.checked = false;

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
        if (codeEl) codeEl.textContent = previewCode.substring(0, 12) + '...';
        if (countEl) countEl.textContent = totalCount + ' Pelanggan';

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    // Tutup Modal Generator Sukses (Refresh Logic juga)
    window.closeSuccessModal = function () {
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

    document.addEventListener('dragstart', function (e) {
        const row = e.target.closest('.draggable-idpel');
        if (row) {

            const id = row.dataset.idpel;

            draggedIdpel = id;
            originKddk = row.dataset.originPrefix;

            row.classList.add('opacity-50', 'bg-yellow-100');
            e.dataTransfer.effectAllowed = 'move';

            e.dataTransfer.setData('text/plain', id);

            const removeZone = document.getElementById('remove-drop-zone');
            if (removeZone) {
                removeZone.classList.remove('hidden');
                setTimeout(() => { removeZone.classList.remove('opacity-0', 'translate-y-10'); }, 10);
            }
        }
    });

    document.addEventListener('dragend', function (e) {
        const row = e.target.closest('.draggable-idpel');
        if (row) row.classList.remove('opacity-50', 'bg-yellow-100');
        document.querySelectorAll('.kddk-drop-zone').forEach(zone => {
            zone.classList.remove('bg-green-50', 'border-green-500', 'border-2');
            const indicator = zone.querySelector('.drop-indicator');
            if (indicator) indicator.classList.add('hidden');
        });

        const removeZone = document.getElementById('remove-drop-zone');
        if (removeZone) {
            removeZone.classList.add('opacity-0', 'translate-y-10');
            setTimeout(() => removeZone.classList.add('hidden'), 300);
        }
        // Reset variabel agar bersih
        draggedIdpel = null;
        originKddk = null;
    });

    document.addEventListener('dragover', function (e) {
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
                if (indicator) indicator.classList.remove('hidden');
            }
            // KASUS B: REORDER (Same Group)
            else if (targetRow && targetRow.dataset.idpel !== draggedIdpel) {
                e.dataTransfer.dropEffect = 'move';
                // Visual Guide: Garis Biru di atas baris target
                targetRow.style.borderTop = "2px solid #4f46e5";
            }
        }
    });

    document.addEventListener('dragleave', function (e) {
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
            if (indicator) indicator.classList.add('hidden');
        }
    });

    document.addEventListener('drop', function (e) {
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

                if (typeof App !== 'undefined' && App.Utils) {
                    App.Utils.showCustomConfirm(
                        'Hapus Massal?',
                        `Keluarkan <strong>${count}</strong> pelanggan terpilih dari grup?`,
                        onConfirmBulk
                    );
                } else if (confirm(`Hapus ${count} pelanggan terpilih?`)) {
                    onConfirmBulk();
                }
                return; // Selesai, jangan lanjut ke single remove
            }

            // --- SKENARIO 2: HAPUS SATU (SINGLE) ---
            // Jalan jika tidak ada centang, ATAU yang ditarik bukan item yang dicentang
            const onConfirmSingle = () => performRemoveIdpel(finalIdpel);

            if (typeof App !== 'undefined' && App.Utils) {
                App.Utils.showCustomConfirm('Hapus?', `Keluarkan pelanggan ${finalIdpel}?`, onConfirmSingle);
            } else if (confirm(`Hapus ${finalIdpel}?`)) {
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
        if (totalChecked > 0) { if (bar) bar.classList.remove('hidden'); if (countSpan) countSpan.textContent = totalChecked; }
        else if (bar) bar.classList.add('hidden');
    }

    // ============================================================
    // 9. LOGIKA PEMICU KLIK KANAN (CONTEXT MENU)
    // ============================================================
    document.addEventListener('contextmenu', function (e) {
        const row = e.target.closest('.draggable-idpel');

        if (row) {
            e.preventDefault(); // Matikan menu bawaan browser

            const contextMenu = document.getElementById('custom-context-menu');

            if (contextMenu) {
                const idpel = row.dataset.idpel;
                const idDisplay = document.getElementById('ctx-header');
                const hiddenId = document.getElementById('ctx-selected-idpel');

                // Isi data ke menu
                if (idDisplay) idDisplay.textContent = `Pelanggan: ${idpel}`;
                if (hiddenId) hiddenId.value = idpel;

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
    document.addEventListener('keydown', function (e) {

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
                    if (typeof handleKddkSearch === 'function') handleKddkSearch('');
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

    window.clearBulkSelection = function () {
        document.querySelectorAll('.select-item-row').forEach(cb => cb.checked = false);
        document.querySelectorAll('.select-all-route').forEach(cb => cb.checked = false);
        updateBulkUI();
    }

    window.confirmGrouping = function () {
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

    window.proceedToGenerator = function () {
        document.getElementById('modal-confirm-selection').classList.add('hidden');
        document.getElementById('modal-confirm-selection').classList.remove('flex');
        window.openKddkModal();
    }

    window.openBulkMoveModal = function () {
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

    window.executeBulkRemove = function () {
        const checked = document.querySelectorAll('.select-item-row:checked');
        const ids = Array.from(checked).map(cb => cb.value);
        if (ids.length === 0) return;
        const count = ids.length;
        const url = document.getElementById('bulk-remove-route').value;
        const onConfirmAction = () => { executeAjax(url, { idpels: ids }); window.clearBulkSelection(); };

        const title = 'Keluarkan Pelanggan?';
        const message = `Anda akan mengeluarkan <strong class="text-red-600 text-xl mx-1">${count}</strong> pelanggan terpilih.<br><span class="text-xs mt-2 block bg-red-50 text-red-600 p-2 rounded">Data akan kembali ke antrian detail.</span>`;

        if (typeof App !== 'undefined' && App.Utils) App.Utils.showCustomConfirm(title, message, onConfirmAction);
        else if (confirm(`Yakin hapus ${ids.length}?`)) onConfirmAction();
    }

    window.executeMoveRoute = function () {
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
    window.addEventListener('scroll', function () {
        const dropdown = document.getElementById('export-dropdown-menu');
        if (dropdown && !dropdown.classList.contains('hidden')) {
            dropdown.classList.add('hidden');
        }
    }, true);


    window.updateMoveRouteOptions = function () {
        const areaSelect = document.getElementById('move-area');
        const routeSelect = document.getElementById('move-route-select');
        if (!areaSelect || !routeSelect) return;
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
            } catch (e) { }
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
            searchInput.addEventListener('input', function (e) {
                const keyword = e.target.value.trim();

                // Toggle tombol X
                if (keyword.length > 0) clearBtn.classList.remove('hidden');
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
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
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

            if (item.lat && item.lng && parseFloat(item.lat) !== 0) {
                // Delay sedikit menunggu accordion terbuka & peta meresize
                setTimeout(() => {
                    window.dispatchEvent(new CustomEvent('rbm:focus', {
                        detail: { lat: parseFloat(item.lat), lng: parseFloat(item.lng) }
                    }));
                }, 1000); // 1 detik agar animasi accordion selesai dulu
            }
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

    window.openHistoryModal = function () {
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

    // ============================================================
    // PERBAIKAN FINAL: PRINT WORKSHEET (MULTI AREA & RUTE)
    // ============================================================
    window.printWorksheetCheck = function () {
        const unitInput = document.querySelector('input[name="unitup"]');
        if (!unitInput) return;
        const unitUp = unitInput.value;

        // 1. SCAN GLOBAL: Cari SEMUA Rute yang Terbuka (di Area manapun)
        const openRouteIcons = document.querySelectorAll('.route-header .icon-chevron-sub.rotate-180');
        let params = "";

        if (openRouteIcons.length > 0) {
            // KASUS A: Ada Rute yang Terbuka
            // Kumpulkan pasangan Area-Rute. Contoh: ["18111-A1", "18222-B2"]
            const selections = Array.from(openRouteIcons).map(icon => {
                const header = icon.closest('.route-header');
                return `${header.dataset.areaCode}-${header.dataset.routeCode}`;
            });

            // Gabungkan jadi string: "18111-A1,18222-B2"
            params = `?multis=${selections.join(',')}`;

        } else {
            // KASUS B: Tidak ada Rute, Cek apakah ada Area Terbuka?
            const openAreaIcons = document.querySelectorAll('.area-header .icon-chevron.rotate-180');

            if (openAreaIcons.length > 0) {
                const areas = Array.from(openAreaIcons).map(icon => {
                    return icon.closest('.area-header').dataset.areaCode;
                });
                params = `?areas=${areas.join(',')}`;
            } else {
                alert("Harap BUKA setidaknya satu Area atau Rute untuk mencetak.");
                return;
            }
        }

        // 2. Buka Halaman Cetak
        const url = `/team/matrix-kddk/print-worksheet/${unitUp}${params}`;
        window.open(url, '_blank');
    }

    // ============================================================
    // PERBAIKAN FINAL: EXPORT RBM (MULTI AREA & RUTE)
    // ============================================================
    window.exportRbmCheck = function (format) {
        const unitInput = document.querySelector('input[name="unitup"]');
        if (!unitInput) return;
        const unitUp = unitInput.value;

        let params = `?format=${format}`;
        let mode = ""; // Untuk konfirmasi user

        // 1. SCAN GLOBAL: Cari Rute Terbuka
        const openRouteIcons = document.querySelectorAll('.route-header .icon-chevron-sub.rotate-180');

        if (openRouteIcons.length > 0) {
            const selections = Array.from(openRouteIcons).map(icon => {
                const header = icon.closest('.route-header');
                return `${header.dataset.areaCode}-${header.dataset.routeCode}`;
            });
            params += `&multis=${selections.join(',')}`;
            mode = "ROUTES";

        } else {
            // 2. Cek Area Terbuka
            const openAreaIcons = document.querySelectorAll('.area-header .icon-chevron.rotate-180');

            if (openAreaIcons.length > 0) {
                const areas = Array.from(openAreaIcons).map(icon => {
                    return icon.closest('.area-header').dataset.areaCode;
                });
                params += `&areas=${areas.join(',')}`;
                mode = "AREAS";
            }
        }

        // Fungsi Download
        const doDownload = () => {
            const baseUrl = `/team/matrix-kddk/export-rbm/${encodeURIComponent(unitUp)}`;
            window.open(baseUrl + params, '_blank');

            // UI Feedback
            window.showToast('Download sedang diproses...', 'success');
            const dropdown = document.getElementById('export-dropdown-menu');
            if (dropdown) dropdown.classList.add('hidden');
        };

        // Konfirmasi
        if (mode === "ROUTES") {
            // User spesifik memilih rute -> Langsung download
            doDownload();
        } else if (mode === "AREAS") {
            // User memilih area -> Langsung download
            doDownload();
        } else {
            // Tidak ada yang dipilih -> Download SEMUA (Bahaya, perlu konfirmasi)
            window.showModernConfirm(
                "Download Seluruh Data?",
                "Anda tidak membuka Area/Rute spesifik.<br>Sistem akan mendownload <strong>SEMUA DATA</strong> unit ini.",
                doDownload
            );
        }
    }

    // ============================================================
    // 11. VISUAL REORDER LOGIC (FITUR BARU)
    // ============================================================

    window.isReorderMode = false;
    window.reorderList = []; // Menyimpan IDPEL yang diklik: ['5123..', '5124..']
    window.polylineLayer = null; // Garis penghubung
    window.currentRoutePrefix = null; // Menyimpan Prefix Rute yang sedang diedit (A1BRBAA)

    // A. Fungsi Memulai Mode Edit
    window.startVisualReorder = function () {
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
        window.polylineLayer = L.polyline([], { color: '#4f46e5', weight: 4, dashArray: '10, 10', opacity: 0.7 }).addTo(rbmMap);

        alert("Mode Edit Aktif! Klik marker pertama untuk mengunci Rute.");
    }

    // B. Fungsi Saat Marker Diklik (Reorder Mode)
    function handleMarkerClickReorder(marker) {
        if (!window.isReorderMode) return;

        const data = marker.kddkData;

        // 1. Validasi Data
        if (!data || !data.idpel) {
            alert("Error: Data marker tidak lengkap.");
            return;
        }

        // 2. Cek Duplikasi
        if (window.reorderList.includes(data.idpel)) return;

        // 3. [FIX] LOGIKA PREFIX 7 KARAKTER (SESUAI REQUEST SERVER)
        // Kita butuh format "18111A1" (5 digit Area + 2 digit Rute)
        let targetPrefix7 = "";

        if (data.fullKddk && data.fullKddk.length >= 7) {
            // Ambil dari KDDK asli (paling aman)
            targetPrefix7 = data.fullKddk.substring(0, 7);
        } else {
            // Fallback: Gabung manual
            targetPrefix7 = String(data.area).trim() + String(data.route).trim();
        }

        // Safety Check: Pastikan panjangnya 7
        if (targetPrefix7.length !== 7) {
            alert(`Error Data: Prefix rute tidak valid (${targetPrefix7}). Harus 7 karakter.`);
            return;
        }

        // 4. Logika Penguncian
        if (window.currentRoutePrefix === null) {
            // KLIK PERTAMA: Kunci Prefix Ini
            window.currentRoutePrefix = targetPrefix7;

            // Update UI Text
            const panelMsg = document.querySelector('#panel-reorder-actions p');
            if (panelMsg) panelMsg.innerHTML = `Mengurutkan Prefix: <b>${targetPrefix7}</b>`;

        } else if (window.currentRoutePrefix !== targetPrefix7) {
            // KLIK SALAH: Beda Rute
            alert(`JANGAN CAMPUR RUTE!\n\nPrefix Terkunci: ${window.currentRoutePrefix}\nMarker ini: ${targetPrefix7}`);
            return;
        }

        // 5. Tambahkan ke List
        window.reorderList.push(data.idpel);

        // 6. Visual Garis & Icon
        const latLng = marker.getLatLng();
        if (window.polylineLayer) window.polylineLayer.addLatLng(latLng);

        const seqNum = window.reorderList.length;
        const newIcon = L.divIcon({
            className: 'custom-reorder-marker',
            html: `<div class="flex items-center justify-center w-8 h-8 bg-indigo-600 text-white rounded-full text-sm font-bold border-2 border-white shadow-lg z-[9999]">${seqNum}</div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });

        marker.setIcon(newIcon);
        document.getElementById('reorder-count').textContent = seqNum + " Item";
    }

    // C. Simpan Perubahan
    window.saveVisualReorder = function () {
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

                    const openRouteHeader = document.querySelector('.route-header .icon-chevron-sub.rotate-180'); if (openRouteHeader) {
                        const headerEl = openRouteHeader.closest('.route-header');
                        const targetId = headerEl.dataset.target;     // ID div tabel (route-18111A1-A1)
                        const areaCode = headerEl.dataset.areaCode;
                        const routeCode = headerEl.dataset.routeCode;

                        // Panggil fungsi load tabel yang sudah ada di matrix-handler.js
                        // Ini akan me-request ulang HTML tabel via AJAX tanpa reload halaman
                        if (typeof loadRouteTableData === 'function') {
                            // Set atribut loaded ke false dulu biar dipaksa reload
                            const contentDiv = document.getElementById(targetId);
                            if (contentDiv) contentDiv.dataset.loaded = "false";

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
    window.cancelVisualReorder = function () {
        // Simpan key sebelum di-reset
        const savedPrefix = window.currentRoutePrefix;

        window.isReorderMode = false;
        window.reorderList = [];
        window.currentRoutePrefix = null;

        // Hapus garis
        if (window.polylineLayer) {
            window.rbmMap.removeLayer(window.polylineLayer);
            window.polylineLayer = null;
        }

        // Reset UI
        document.getElementById('btn-start-reorder').classList.remove('hidden');
        document.getElementById('panel-reorder-actions').classList.add('hidden');
        document.getElementById('reorder-count').textContent = "0";
        const panelMsg = document.querySelector('#panel-reorder-actions p');
        if (panelMsg) panelMsg.textContent = "Klik marker satu per satu sesuai urutan yang diinginkan.";

        // REFRESH PETA (Agar marker angka 1,2,3 kembali jadi titik biasa)
        if (savedPrefix && savedPrefix.length === 7) {
            // Parse "18111A1" -> Area "18111", Rute "A1"
            const areaCode = savedPrefix.substring(0, 5);
            const routeCode = savedPrefix.substring(5, 7);
            const layerKey = `${areaCode}-${routeCode}`; // Format Key Memori (Pake Strip)

            console.log(`[Reorder Cancel] Refreshing: Area ${areaCode}, Rute ${routeCode}`);

            // Hapus dari memori & Load Ulang
            if (activeRouteLayers[layerKey]) {
                window.rbmMap.removeLayer(activeRouteLayers[layerKey]);
                delete activeRouteLayers[layerKey];
            }

            if (typeof window.toggleRouteLayer === 'function') {
                window.toggleRouteLayer(areaCode, routeCode, true);
            }
        }
    }

    // ============================================================
    // LISTENER GLOBAL: FOKUS PETA (VERSI FINAL - CLEAN NO SPAM)
    // ============================================================
    window.addEventListener('rbm:focus', function (e) {
        const rawLat = e.detail.lat;
        const rawLng = e.detail.lng;
        const targetIdpel = e.detail.idpel;

        // 1. Validasi Input
        if (typeof rawLat !== 'number' || typeof rawLng !== 'number' ||
            !Number.isFinite(rawLat) || !Number.isFinite(rawLng)) return;

        const targetMap = window.rbmMap;
        if (!targetMap || !targetMap.getContainer()) return;

        // =========================================================
        // FUNGSI INTI PERPINDAHAN (Internal Helper)
        // Kita bungkus logika pindah agar bisa dipanggil tunda
        // =========================================================
        const executeMove = () => {
            targetMap.invalidateSize(); // Wajib: Baca ukuran layar terbaru

            // Cek lagi, kalau masih 0px, menyerah (jangan spam console)
            const checkSize = targetMap.getSize();
            if (checkSize.x === 0 || checkSize.y === 0) return;

            let targetLatLng;
            try { targetLatLng = L.latLng(rawLat, rawLng); } catch (e) { return; }

            // LOGIKA CARI MARKER DALAM CLUSTER (Penting utk Multi-Layer)
            let foundMarker = null;
            let parentLayer = null;

            if (typeof activeRouteLayers !== 'undefined') {
                for (const key in activeRouteLayers) {
                    const group = activeRouteLayers[key];
                    const layers = group.getLayers();

                    // CARI 1: Berdasarkan IDPEL (Paling Akurat untuk Data Ganda)
                    if (targetIdpel) {
                        foundMarker = layers.find(l => l.kddkData && String(l.kddkData.idpel) === String(targetIdpel));
                    }

                    // CARI 2: Fallback ke Koordinat (Jika IDPEL tidak dikirim/tidak ketemu)
                    if (!foundMarker) {
                        foundMarker = layers.find(l => l instanceof L.Marker && l.getLatLng().distanceTo(targetLatLng) < 1);
                    }

                    if (foundMarker) { parentLayer = group; break; }
                }
            }

            // EKSEKUSI
            try {
                targetMap.stop(); // Stop animasi lama

                if (foundMarker && parentLayer) {
                    // Zoom ke Cluster
                    parentLayer.zoomToShowLayer(foundMarker, () => foundMarker.openPopup());
                } else {
                    // Terbang Manual (Fallback)
                    // Cek pusat peta valid?
                    const curCenter = targetMap.getCenter();
                    if (!curCenter || isNaN(curCenter.lat)) {
                        targetMap.setView(targetLatLng, 19, { animate: false });
                    } else {
                        targetMap.flyTo(targetLatLng, 19, { animate: true, duration: 1.2 });
                    }

                    // Buka Popup Manual (jika marker ada tapi bukan di cluster)
                    setTimeout(() => {
                        targetMap.eachLayer(l => {
                            // Coba buka popup jika IDPEL cocok, atau koordinat cocok
                            if (l instanceof L.Marker) {
                                const isIdMatch = targetIdpel && l.kddkData && String(l.kddkData.idpel) === String(targetIdpel);
                                const isLocMatch = l.getLatLng().distanceTo(targetLatLng) < 1;
                                
                                if (isIdMatch || (!targetIdpel && isLocMatch)) {
                                    l.openPopup();
                                }
                            }
                        });
                    }, 1300);
                }
            } catch (err) {
                targetMap.setView(targetLatLng, 19, { animate: false });
            }
        };

        // =========================================================
        // 2. CEK UKURAN & EKSEKUSI
        // =========================================================
        targetMap.invalidateSize();
        const mapSize = targetMap.getSize();

        // JIKA PETA TERTUTUP (0px)
        if (mapSize.x === 0 || mapSize.y === 0) {
            // Jangan pakai console.warn (Biar console bersih)
            // console.log("[RBM MAP] Menunggu peta terbuka...");

            // Buka Wrapper Paksa
            const container = document.getElementById('rbm-map');
            const hiddenParent = container.closest('.hidden');
            if (hiddenParent) hiddenParent.classList.remove('hidden');
            else container.style.display = 'block';

            // TUNDA EKSEKUSI (JANGAN KIRIM EVENT LAGI)
            // Cukup panggil fungsi executeMove() setelah jeda
            setTimeout(() => {
                executeMove();
            }, 300); // 300ms cukup untuk animasi accordion

            return; // Stop disini, biarkan setTimeout yang lanjut nanti
        }

        // JIKA PETA SUDAH TERBUKA -> LANGSUNG EKSEKUSI
        executeMove();
    });

    // 2. DISPATCHER: Menangani Klik Baris Tabel (Tetap Sama)
    document.body.addEventListener('click', function (e) {
        const row = e.target.closest('tr.draggable-idpel');

        if (row && !e.target.closest('input') && !e.target.closest('button') && !e.target.closest('a')) {
            e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();

            const rawLat = row.getAttribute('data-lat');
            const rawLng = row.getAttribute('data-lng');
            const idpel = row.getAttribute('data-idpel');

            if (!rawLat || !rawLng) return;

            const lat = parseFloat(String(rawLat).replace(',', '.').trim());
            const lng = parseFloat(String(rawLng).replace(',', '.').trim());

            if (Number.isFinite(lat) && Number.isFinite(lng) && lat !== 0 && lng !== 0) {
                document.querySelectorAll('tr.bg-indigo-50').forEach(el => el.classList.remove('bg-indigo-50'));
                row.classList.add('bg-indigo-50');

                requestAnimationFrame(() => {
                    window.dispatchEvent(new CustomEvent('rbm:focus', { detail: { lat: lat, lng: lng, idpel: idpel } }));
                });
            }
        }
    }, true);

});


/**
 * ============================================================
 * HELPER: GENERATOR WARNA KONSISTEN
 * Menghasilkan warna yang sama untuk kode rute yang sama.
 * ============================================================
 */
const ROUTE_PALETTE = [
    { name: 'Red', border: 'border-red-600', bg: 'bg-red-50', line: '#dc2626' },
    { name: 'Blue', border: 'border-blue-600', bg: 'bg-blue-50', line: '#2563eb' },
    { name: 'Green', border: 'border-green-600', bg: 'bg-green-50', line: '#16a34a' },
    { name: 'Orange', border: 'border-orange-600', bg: 'bg-orange-50', line: '#ea580c' },
    { name: 'Purple', border: 'border-purple-600', bg: 'bg-purple-50', line: '#9333ea' },
    { name: 'Pink', border: 'border-pink-600', bg: 'bg-pink-50', line: '#db2777' },
    { name: 'Teal', border: 'border-teal-600', bg: 'bg-teal-50', line: '#0d9488' },
    { name: 'Cyan', border: 'border-cyan-600', bg: 'bg-cyan-50', line: '#0891b2' },
    { name: 'Indigo', border: 'border-indigo-600', bg: 'bg-indigo-50', line: '#4f46e5' },
    { name: 'Lime', border: 'border-lime-600', bg: 'bg-lime-50', line: '#65a30d' },
];

function getRouteStyle(routeCode) {
    if (!routeCode) return ROUTE_PALETTE[1]; // Default Blue

    // Ubah string "A1" menjadi angka unik
    let hash = 0;
    for (let i = 0; i < routeCode.length; i++) {
        hash = routeCode.charCodeAt(i) + ((hash << 5) - hash);
    }

    // Pilih warna berdasarkan angka tersebut (Modulo)
    const index = Math.abs(hash) % ROUTE_PALETTE.length;
    return ROUTE_PALETTE[index];
}

// =================================================================
// VARIABEL GLOBAL
// =================================================================
window.currentDragMarker = null;
window.originalMarkerRef = null;
window.dragConnectionLine = null;

/**
 * [SOLUSI FINAL FIX] Mode Drag Tanpa Animasi CSS (Agar Tidak Konflik)
 */
window.enableMarkerDrag = function (idpel) {
    if (!window.markerRegistry || !window.markerRegistry[idpel]) {
        showToast("Marker tidak ditemukan.", "error");
        return;
    }

    const originalMarker = window.markerRegistry[idpel];

    // 1. Tutup Popup
    originalMarker.closePopup();

    // 2. Ambil Posisi & Icon
    const startLatLng = originalMarker.getLatLng();
    const icon = originalMarker.options.icon;

    // 3. OFFSET KECIL SAJA (Supaya tidak lompat jauh)
    // Cukup geser 0.0001 derajat (sekitar 10 meter)
    // Ini cukup untuk memisahkan diri dari tumpukan, tapi tetap dekat
    const offsetLatLng = {
        lat: startLatLng.lat + 0.0001,
        lng: startLatLng.lng + 0.00005
    };

    // 4. Sembunyikan Marker Asli
    originalMarker.setOpacity(0);
    window.originalMarkerRef = originalMarker;

    // 5. Bersihkan sampah lama
    cleanUpDragElements();

    // 6. GARIS PENGHUBUNG (Merah Putus-putus)
    window.dragConnectionLine = L.polyline([startLatLng, offsetLatLng], {
        color: '#ef4444', // Merah Tailwind
        weight: 2,
        opacity: 0.8,
        dashArray: '5, 5'
    }).addTo(window.rbmMap);

    // 7. MARKER BAYANGAN (CLONE)
    window.currentDragMarker = L.marker(offsetLatLng, {
        icon: icon,
        draggable: true,
        zIndexOffset: 20000 // Paling Atas
    }).addTo(window.rbmMap);

    // 8. EFEK VISUAL (PENTING: JANGAN PAKAI ANIMATE-BOUNCE)
    // Kita pakai CSS Filter saja agar tidak merusak fungsi Drag Leaflet
    const el = window.currentDragMarker.getElement();
    if (el) {
        // Beri efek glow merah & sedikit transparan
        el.style.filter = "drop-shadow(0 0 8px rgba(255, 0, 0, 0.8))";
        el.style.opacity = "0.9";
        el.style.cursor = "move"; // Ubah kursor jadi ikon geser
    }

    showToast("Mode Geser Aktif. Tarik marker ke lokasi rumah.", "info");

    // 9. UPDATE GARIS SAAT DIGESER
    window.currentDragMarker.on('drag', function (e) {
        const curPos = e.target.getLatLng();
        window.dragConnectionLine.setLatLngs([startLatLng, curPos]);
    });

    // 10. EVENT SAAT LEPAS (DROP)
    window.currentDragMarker.on('dragend', function (e) {
        const newPos = e.target.getLatLng();
        const lat = newPos.lat.toFixed(7);
        const lng = newPos.lng.toFixed(7);

        // Beri jeda sedikit
        setTimeout(() => {
            // Tanya user
            if (confirm(`Simpan lokasi baru?\n\nLat: ${lat}\nLng: ${lng}`)) {
                saveCoordinate(idpel, lat, lng);
            } else {
                cancelDrag();
            }
        }, 100);
    });
};

/**
 * Bersihkan Elemen
 */
function cleanUpDragElements() {
    if (window.currentDragMarker) {
        window.rbmMap.removeLayer(window.currentDragMarker);
        window.currentDragMarker = null;
    }
    if (window.dragConnectionLine) {
        window.rbmMap.removeLayer(window.dragConnectionLine);
        window.dragConnectionLine = null;
    }
}

/**
 * Batalkan
 */
function cancelDrag() {
    cleanUpDragElements();
    // Munculkan kembali marker asli
    if (window.originalMarkerRef) {
        window.originalMarkerRef.setOpacity(1);
        window.originalMarkerRef = null;
    }
    showToast("Edit dibatalkan.", "info");
}

/**
 * Simpan
 */
function saveCoordinate(idpel, lat, lng) {
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    if (window.currentDragMarker) window.currentDragMarker.dragging.disable();

    showToast("Menyimpan...", "info");

    fetch('/team/matrix-kddk/update-coordinate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify({ idpel, lat, lng })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast("Berhasil!", "success");
                cleanUpDragElements();

                // REFRESH PETA
                if (window.originalMarkerRef && window.originalMarkerRef.kddkData) {
                    const d = window.originalMarkerRef.kddkData;
                    const layerKey = `${d.area}-${d.route}`;

                    if (activeRouteLayers[layerKey]) {
                        window.rbmMap.removeLayer(activeRouteLayers[layerKey]);
                        delete activeRouteLayers[layerKey];
                    }

                    setTimeout(() => {
                        window.toggleRouteLayer(d.area, d.route, true);
                    }, 300);
                }
            } else {
                showToast("Gagal: " + data.message, "error");
                cancelDrag();
            }
        })
        .catch(err => {
            console.error(err);
            showToast("Error koneksi.", "error");
            cancelDrag();
        });
}