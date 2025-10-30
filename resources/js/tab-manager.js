const GOOGLE_API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

// VARIABEL UNTUK KDDK MAPPING (Peta Sebaran)
let mappingFeatureGroup = null; 
let mappingClickedMarker = null; 

// VARIABEL UNTUK VALIDASI
let validationMapInstance = null;
let validationMarker = null; 

// VARIABEL LAIN YANG HARUS DI GLOBAL
let currentValidationId = null; 
window.currentValidationDetails = null;

// DEFINISI IKON (Wajib Global agar bisa diakses initializeValidationMap dan Event Listener)
const redIcon = new L.Icon({
    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
    iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
});
const blueIcon = new L.Icon({
    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
    iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
});
   
   document.addEventListener('DOMContentLoaded', function () {
    // --- Inisialisasi Variabel Global ---
    const dashboardUrl = document.body.dataset.dashboardUrl;
    const sidebar = document.getElementById('sidebarMenu');
    const toggleBtn = document.getElementById('sidebarToggle');
    const tabsHeader = document.getElementById('tabs-header');
    const tabsContent = document.getElementById('tabs-content');
    const scrollLeftBtn = document.getElementById('tab-scroll-left');
    const scrollRightBtn = document.getElementById('tab-scroll-right');
    const mainModal = document.getElementById('main-modal');
    const modalContent = document.getElementById('modal-content');
    let searchDebounceTimer;
    const imageModal = document.getElementById('image-viewer-modal');
    const imageModalImg = document.getElementById('image-viewer-img');
    const imageModalClose = document.getElementById('image-viewer-close');
    const imageModalOverlay = document.getElementById('image-viewer-overlay');
    const modalMeterInputContainer = document.getElementById('modal-meter-input-container');
    const modalMeterInput = document.getElementById('modal-meter-input');

    // --- Toggle Sidebar ---
    if (sidebar && toggleBtn) {
        toggleBtn.addEventListener('click', () => sidebar.classList.toggle('sidebar-collapsed'));
    }

    // --- FUNGSI UTILITY: Tampilkan Notifikasi ---
    function displayNotification(type, message) {
        let container = null;

        // 1. Coba cari container di tab yang sedang aktif
        const activeTabContent = document.querySelector('.tab-content:not(.hidden)');
        if (activeTabContent) {
            // Cek container validasi (jika kita di tab itu)
            container = activeTabContent.querySelector('#interactive-validation-container');
            if (!container) {
                // Cek container KDDK (jika kita di tab itu)
                container = activeTabContent.querySelector('#kddk-notification-container');
            }
        }

        // 2. Jika masih tidak ketemu (fallback), gunakan alert
        if (!container) {
            console.warn("displayNotification: Tidak ada container di tab aktif. Fallback ke alert.");
            alert(message);
            return;
        }

        // 3. Hapus notifikasi sebelumnya (Success atau Error)
        container.querySelectorAll('.bg-green-100, .bg-red-100').forEach(el => el.remove());

        // 4. Tentukan style
        let alertClass = type ;
        let strongText = type ;

        if (type === 'success' || type === 'validate') { 
            alertClass = 'bg-green-100 border-green-400 text-green-700';
            strongText = 'Berhasil!';
        } else if (type === 'reject') { 
            alertClass = 'bg-red-100 border-red-400 text-red-700';
            strongText = 'Penolakan Berhasil!'; 
        } else { 
            alertClass = 'bg-red-100 border-red-400 text-red-700';
            strongText = 'Error!';
        }            
        
        // 5. Buat HTML Notifikasi
        const notificationHtml = `
                <div id="action-notification-alert" class="mt-4 ${alertClass} border px-4 py-3 rounded relative" role="alert" style="margin-top: 0.5rem !important;">
                    <strong class="font-bold">${strongText}</strong>
                    <span class="block sm:inline"> ${message}</span>

                    <button type="button" class="absolute top-0 right-0 p-4 text-xl" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
            `;
        
        // 6. Sisipkan notifikasi
        container.insertAdjacentHTML('afterbegin', notificationHtml); 

        const newAlert = container.querySelector('#action-notification-alert');

        // 7. Logika Auto-hide
        if (newAlert) {
            newAlert.scrollIntoView({ behavior: 'smooth', block: 'start' });

            const autoHideTimer = setTimeout(() => {
                if (newAlert) {
                    newAlert.style.opacity = 0; 
                    setTimeout(() => newAlert.remove(), 500);
                }
            }, 15000); // Waktu 15 detik

            const closeButton = newAlert.querySelector('[data-dismiss="alert"]');
            if (closeButton) {
                closeButton.addEventListener('click', () => {
                    newAlert.remove();
                    clearTimeout(autoHideTimer); 
                });
            }
        }
    }

    function showCustomConfirm(title, message, onConfirm) {
        const modal = document.getElementById('custom-confirm-modal');
        const titleEl = document.getElementById('custom-confirm-title');
        const messageEl = document.getElementById('custom-confirm-message');
        const okButton = document.getElementById('custom-confirm-ok');
        const cancelButton = document.getElementById('custom-confirm-cancel');
        const overlay = modal; // Modal itu sendiri adalah overlay

        if (!modal || !titleEl || !messageEl || !okButton || !cancelButton) {
            console.error('Elemen modal konfirmasi kustom tidak ditemukan!');
            // Fallback ke konfirmasi bawaan jika modal tidak ada
            if (confirm(message)) {
                onConfirm();
            }
            return;
        }

        // 1. Set konten
        titleEl.textContent = title || 'Konfirmasi Tindakan';
        messageEl.textContent = message;

        // 2. Tampilkan modal
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        // 3. Buat fungsi handler (agar bisa di-remove nanti)
        const handleConfirm = () => {
            cleanup();
            onConfirm(); // Jalankan callback (logika fetch)
        };

        const handleCancel = () => {
            cleanup();
        };

        const handleOverlayClick = (e) => {
            if (e.target === overlay) {
                cleanup();
            }
        };

        // 4. Buat fungsi cleanup untuk menyembunyikan modal & hapus listener
        const cleanup = () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            okButton.removeEventListener('click', handleConfirm);
            cancelButton.removeEventListener('click', handleCancel);
            overlay.removeEventListener('click', handleOverlayClick);
        };

        // 5. Hapus listener lama (jika ada sisa) dan tambahkan yang baru
        okButton.removeEventListener('click', handleConfirm);
        cancelButton.removeEventListener('click', handleCancel);
        overlay.removeEventListener('click', handleOverlayClick);

        okButton.addEventListener('click', handleConfirm);
        cancelButton.addEventListener('click', handleCancel);
        overlay.addEventListener('click', handleOverlayClick);
    }

    // ===================================================================
    // ===== SATU EVENT LISTENER UNTUK SEMUA AKSI KLIK =====
    // ===================================================================
    document.addEventListener('click', function(e) {
        const modalLink = e.target.closest('[data-modal-link]');
        const deleteButton = e.target.closest('[data-delete-url]');
        const modalCloseButton = e.target.closest('[data-modal-close]');
        const clearButton = e.target.closest('#clear-search-button');
        const targetLink = e.target.closest('a');
        const isActionOrForm = e.target.closest('form[data-custom-handler="invalidate-action"]') ||
                            e.target.closest('form[data-custom-handler="promote-action"]') || 
                               e.target.closest('[data-delete-url]');

        if (isActionOrForm) {
            return; 
        }
        const dataRow = e.target.closest('.data-row-clickable');
        if (dataRow) {
            e.preventDefault();

            const data = dataRow.dataset;
            const activePanel = dataRow.closest('.tab-content');
            if (!activePanel) return;

            const titleSpanEl = activePanel.querySelector('#detail-title-span');
            const stampEl = activePanel.querySelector('#detail-status-stamp');
            // Variabel untuk elemen foto (Link Zoom dan Gambar IMG)
            const kwhLinkEl = activePanel.querySelector('#detail-foto-kwh-link');
            const kwhImgEl = activePanel.querySelector('#detail-foto-kwh');
            const kwhPlaceholderEl = activePanel.querySelector('#placeholder-foto-kwh');
            
            const bangunanLinkEl = activePanel.querySelector('#detail-foto-bangunan-link');
            const bangunanImgEl = activePanel.querySelector('#detail-foto-bangunan');
            const bangunanPlaceholderEl = activePanel.querySelector('#placeholder-foto-bangunan');
            const latLonEl = activePanel.querySelector('#detail-lat-lon');
            const streetViewLinkEl = activePanel.querySelector('#google-street-view-link');

            if (latLonEl && streetViewLinkEl) {
                // Cek apakah data lat/lon valid (bukan 0 atau string kosong)
                if (data.lat && data.lon && parseFloat(data.lat) !== 0 && parseFloat(data.lon) !== 0) {
                    const lat = parseFloat(data.lat);
                    const lon = parseFloat(data.lon);
                    
                    // 1. Update Teks Koordinat
                    latLonEl.textContent = `${lat.toFixed(6)}, ${lon.toFixed(6)}`;
                    
                    // 2. Buat URL Street View
                    const streetViewUrl = `https://www.google.com/maps?q&layer=c&cbll=${lat},${lon}`;
                    
                    // 3. Update Link Icon
                    streetViewLinkEl.href = streetViewUrl;
                    
                    // 4. Tampilkan Icon
                    streetViewLinkEl.classList.remove('hidden');
                    
                } else {
                    // Jika data tidak valid (misal 0,0 atau null)
                    latLonEl.textContent = 'Koordinat tidak valid';
                    streetViewLinkEl.classList.add('hidden'); // Sembunyikan icon
                }
            }

            const streetViewModal = activePanel.querySelector('#street-view-modal');
            const streetViewIframe = activePanel.querySelector('#street-view-iframe');
            const streetViewCloseButton = activePanel.querySelector('#street-view-close-button');
            const streetViewHeader = activePanel.querySelector('#street-view-header');

            if (streetViewModal && streetViewIframe && streetViewCloseButton && streetViewHeader) {

                // Fungsi handler untuk membuka modal
                const handleStreetViewClick = (e) => {
                    e.preventDefault(); // Mencegah link href="#" melompat ke atas halaman
                    e.stopPropagation(); // Mencegah event klik baris (dataRow) terpicu lagi

                    if (data.lat && data.lon && parseFloat(data.lat) !== 0 && parseFloat(data.lon) !== 0) {
                        const lat = parseFloat(data.lat);
                        const lon = parseFloat(data.lon);
                        
                        // URL Google Maps Embed API untuk Iframe
                        const streetViewUrl = `https://www.google.com/maps/embed/v1/streetview?location=${lat},${lon}&key=${GOOGLE_API_KEY}`;
                        
                        streetViewIframe.src = streetViewUrl; // Atur src iframe
                        streetViewModal.classList.remove('hidden'); // Tampilkan modal
                        // streetViewModal.classList.add('flex'); // Gunakan flex untuk centering
                        // document.body.style.overflow = 'hidden'; // Nonaktifkan scroll body
                    } else {
                        alert('Koordinat tidak valid untuk Street View.');
                    }
                };

                // Fungsi handler untuk menutup modal
                const closeStreetViewModal = () => {
                    streetViewModal.classList.add('hidden');
                    streetViewIframe.src = ""; // Kosongkan src iframe untuk menghentikan proses
                    // streetViewModal.classList.remove('flex');
                    // document.body.style.overflow = ''; // Aktifkan kembali scroll body
                };

                streetViewModal.style.left = '';
                streetViewModal.style.top = '';
                streetViewModal.style.right = '';

                let isDragging = false;
                let offsetX, offsetY;

                // Fungsi saat mousedown di header
                const onMouseDown = (e) => {
                    // Hanya geser jika targetnya adalah header (bukan tombol close di header)
                    if (e.target.id !== 'street-view-header' && !e.target.closest('#street-view-header')) return;

                    isDragging = true;
                    
                    // Hitung posisi mouse relatif terhadap pojok kiri atas modal
                    offsetX = e.clientX - streetViewModal.getBoundingClientRect().left;
                    offsetY = e.clientY - streetViewModal.getBoundingClientRect().top;
                    
                    streetViewHeader.style.cursor = 'grabbing'; // Ubah cursor saat digeser
                    document.body.style.userSelect = 'none'; // Matikan seleksi teks saat digeser

                    // Tambahkan listener ke seluruh dokumen
                    document.addEventListener('mousemove', onMouseMove);
                    document.addEventListener('mouseup', onMouseUp);
                };

                // Fungsi saat mouse digerakkan
                const onMouseMove = (e) => {
                    if (!isDragging) return;
                    
                    // Hitung posisi baru modal
                    let newX = e.clientX - offsetX;
                    let newY = e.clientY - offsetY;

                    // Terapkan posisi baru via inline style
                    streetViewModal.style.left = `${newX}px`;
                    streetViewModal.style.top = `${newY}px`;
                    
                    // Hapus style 'right' agar 'left' bisa bekerja
                    streetViewModal.style.right = 'auto'; 
                };

                // Fungsi saat mouse dilepas
                const onMouseUp = () => {
                    isDragging = false;
                    
                    streetViewHeader.style.cursor = 'move'; // Kembalikan cursor
                    document.body.style.userSelect = ''; // Nyalakan lagi seleksi teks

                    // Hapus listener dari dokumen
                    document.removeEventListener('mousemove', onMouseMove);
                    document.removeEventListener('mouseup', onMouseUp);
                };
                
                // --- 4. ATUR EVENT LISTENERS ---
                
                // PENTING: Hapus listener lama sebelum menambah yang baru
                streetViewLinkEl.removeEventListener('click', streetViewLinkEl.__handler);
                streetViewCloseButton.removeEventListener('click', streetViewCloseButton.__handler);
                streetViewHeader.removeEventListener('mousedown', streetViewHeader.__dragHandler);
                // Hapus listener modal (karena overlay dihilangkan)
                streetViewModal.removeEventListener('click', streetViewModal.__handler); 

                // Simpan referensi handler baru
                streetViewLinkEl.__handler = handleStreetViewClick;
                streetViewCloseButton.__handler = closeStreetViewModal;
                streetViewHeader.__handler = onMouseDown;

                // Tambahkan event listener baru
                streetViewLinkEl.addEventListener('click', handleStreetViewClick);
                streetViewCloseButton.addEventListener('click', closeStreetViewModal);
                streetViewHeader.addEventListener('mousedown', onMouseDown); // Tambahkan listener drag

            } else {
                console.error("Elemen modal Street View (street-view-modal, iframe, atau close-button) tidak ditemukan.");
            }

            if (titleSpanEl) {
                // Perbarui teks di dalam span
                titleSpanEl.textContent = `Detail Peta - ${data.idpel} (Object ID: ${data.objectid})`;
            }

            if (stampEl) {
                // Asumsi path asset Anda
                const verifiedStamp = '/images/verified_stamp.png'; 
                const unverifiedStamp = '/images/unverified_stamp.png';

                if (data.enabled === 'true') {
                    stampEl.src = verifiedStamp;
                    stampEl.alt = 'Valid (Aktif)';
                    stampEl.title = 'Status: Valid (Aktif)';
                } else {
                    stampEl.src = unverifiedStamp;
                    stampEl.alt = 'Tidak Aktif';
                    // Tampilkan status spesifik jika tidak aktif
                    let statusTitle = 'Status: Data Tidak Aktif';
                    if (data.status === 'verified') statusTitle = 'Status: Terverifikasi (Belum Aktif)';
                    if (data.status === 'superseded') statusTitle = 'Status: Digantikan';
                    if (data.status === 'recalled_1') statusTitle = 'Status: Ditarik';
                    stampEl.title = statusTitle;
                }
                stampEl.classList.remove('hidden'); // Pastikan stamp terlihat
            }

            // Update Foto KWH
            if (kwhImgEl && kwhPlaceholderEl && kwhLinkEl) {
                if (data.fotoKwhUrl) {
                    kwhImgEl.src = data.fotoKwhUrl;
                    kwhLinkEl.classList.remove('hidden');
                    kwhImgEl.classList.remove('hidden');
                    kwhPlaceholderEl.classList.add('hidden');
                } else {
                    kwhImgEl.src = '';
                    kwhLinkEl.classList.add('hidden');
                    kwhImgEl.classList.add('hidden');
                    kwhPlaceholderEl.classList.remove('hidden');
                }
            }
            
            // Update Foto Bangunan
            if (bangunanImgEl && bangunanPlaceholderEl && bangunanLinkEl) {
                if (data.fotoBangunanUrl) {
                    bangunanImgEl.src = data.fotoBangunanUrl;
                    bangunanLinkEl.classList.remove('hidden');
                    bangunanImgEl.classList.remove('hidden');
                    bangunanPlaceholderEl.classList.add('hidden');
                } else {
                    bangunanImgEl.src = '';
                    bangunanLinkEl.classList.add('hidden');
                    bangunanImgEl.classList.add('hidden');
                    bangunanPlaceholderEl.classList.remove('hidden');
                }
            }
            
            // 2. Logika Peta: Panggil ulang data koordinat
            if (window.mapInstance && data.lat && data.lon) {
                const lat = parseFloat(data.lat);
                const lon = parseFloat(data.lon);

                if (!isNaN(lat) && !isNaN(lon)) {
                    // 1. Pindahkan view ke lokasi yang diklik
                    window.mapInstance.setView([lat, lon], 18);

                    //Panggil fungsi helper baru untuk merender ulang marker
                    renderClickedMapMarkers(data.idpel, data.objectid, lat, lon);
                }
            }
            
           // 3. Beri highlight pada baris
            const table = dataRow.closest('table');
            if (table) {
                table.querySelectorAll('.data-row-clickable').forEach(row => {
                    row.classList.remove('bg-blue-100', 'dark:bg-blue-900');
                });
            }
            dataRow.classList.add('bg-blue-100', 'dark:bg-blue-900');

            return; // Hentikan listener
        }

        const imageZoomButton = e.target.closest('.image-zoom-trigger');
        if (imageZoomButton && imageModal) {
            e.preventDefault();
            const imgElement = imageZoomButton.querySelector('img');
            const zoomType = imageZoomButton.dataset.zoomType; // Ambil tipe zoom

            if (imgElement && imgElement.src) {
                imageModalImg.src = imgElement.src; // Set gambar modal

                // Logika Input Meter Sementara
                if (zoomType === 'kwh' && modalMeterInputContainer) {
                    // 1. Cari input meter asli di form
                    const mainMeterInput = imageZoomButton.closest('#validation-content')?.querySelector('#eval_meter_input');
                    // 2. Salin nilainya ke input modal
                    modalMeterInput.value = mainMeterInput ? mainMeterInput.value : ''; 
                    // 3. Tampilkan kontainer input di modal
                    modalMeterInputContainer.classList.remove('hidden');
                    // 4. (Opsional) Fokuskan ke input modal
                    setTimeout(() => modalMeterInput.focus(), 50); 
                } else if (modalMeterInputContainer) {
                    // Sembunyikan jika bukan KWH
                    modalMeterInputContainer.classList.add('hidden');
                }

                imageModal.classList.remove('hidden'); // Tampilkan modal
            }
            return;
        }
        
        const validationQueueButton = e.target.closest('[data-validation-queue-id]');
        if (validationQueueButton) {
            e.preventDefault();
            const id = validationQueueButton.dataset.validationQueueId;
            lockAndLoadDetails(id, validationQueueButton);
            return;
        }
        
        // === LISTENER BARU UNTUK REFRESH ===
        const refreshButton = e.target.closest('#refresh-queue-list');
        if (refreshButton) {
            e.preventDefault();
            refreshValidationQueue(true); // Panggil fungsi refresh (akan kita buat)
            return;
        }

        // Prioritas 1: Buka Modal
        if (modalLink) {
            e.preventDefault();
            openModal(modalLink.href);
            return;
        }
        
        // Prioritas 2: Tombol Hapus
        if (deleteButton) {
            const userName = deleteButton.dataset.userName || 'item ini';
            const deleteUrl = deleteButton.dataset.deleteUrl;

            if (confirm(`Apakah Anda yakin ingin menghapus ${userName}?`)) {
                const formData = new FormData();
                formData.append('_method', 'DELETE');

                fetch(deleteUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: formData
                })
                .then(response => {
                    if (response.status === 204) return { message: 'Data berhasil dihapus!' };
                    if (!response.ok) return response.json().then(err => { throw err; });
                    return response.json();
                })
                .then(data => {
                    if (data.message) {
                        alert(data.message);
                        const tabNameToRefresh = getActiveTabName();
                        const tabContent = document.getElementById(`${tabNameToRefresh}-content`);
                        if (tabContent) {
                            const searchForm = tabContent.querySelector('form[id*="-search-form"]');
                            let refreshUrl;
                            if (searchForm) {
                                const params = new URLSearchParams(new FormData(searchForm)).toString();
                                refreshUrl = `${searchForm.action}?${params}`;
                            } else {
                                const tabButton = document.querySelector(`#tabs-header .tab-button[data-tab-name="${tabNameToRefresh}"]`);
                                if (tabButton) refreshUrl = tabButton.dataset.url || tabButton.href;
                            }
                            if (refreshUrl) loadTabContent(tabNameToRefresh, refreshUrl);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert(error.message || 'Terjadi kesalahan saat menghapus data.');
                });
            }
            return;
        }

        // Prioritas 3: Tombol "Batal" atau Tombol Close di Modal
        if (modalCloseButton) {
            closeModal();
            return;
        }
        
        // Prioritas 4: Tombol Clear 'X' di Search Box
        if (clearButton) {
            const searchForm = clearButton.closest('form');
            const searchInput = searchForm.querySelector('input[name="search"]');
            searchInput.value = '';
            clearButton.classList.add('hidden');
            const activeTabName = getActiveTabName();
            if (activeTabName) {
                loadTabContent(activeTabName, searchForm.action);
            }
            return;
        }

        // Prioritas 5: Tutup modal jika overlay diklik
        if (e.target === mainModal) {
            closeModal();
            return;
        }

        if (!targetLink) return;

        // ===================================================================
        // ===== MODIFIKASI UTAMA: Menangani SEMUA Link AJAX dalam Tab =====
        // ===================================================================
        // Cek apakah link berada di dalam konten tab (#tabs-content)
        // dan BUKAN link untuk membuka tab baru.
        if (targetLink.closest('#tabs-content') &&
            !targetLink.hasAttribute('data-tab-link') && 
            targetLink.getAttribute('target') !== '_blank' &&
            targetLink.id !== 'google-street-view-link' &&
            targetLink.id !== 'validation-street-view-link')
            {
            e.preventDefault(); // Mencegah refresh
            const activeTabName = getActiveTabName();
            if (activeTabName) {
                // Gunakan fungsi loadTabContent yang sudah ada!
                loadTabContent(activeTabName, targetLink.href);
            }
            return;
        }
        // ===================================================================


        // B. Link Menu Sidebar (Buka Tab)
        if (targetLink.hasAttribute('data-tab-link')) {
            e.preventDefault();
            const url = targetLink.href;
            const tabName = targetLink.dataset.tabLink;
            const existingTab = tabsHeader.querySelector(`[data-tab-name="${tabName}"]`);
            if (existingTab) {
                activateTab(tabName, url, true);
            } else {
                const isClosable = targetLink.dataset.closable !== 'false';
                createTab(tabName, url, isClosable, true);
            }
            return;
        }
    });

    // ===================================================================
    // == 1. TAMBAHKAN LISTENER BARU UNTUK FORM EVALUASI RADIO ==
    // ===================================================================

    // Dengarkan semua perubahan pada input atau radio dengan class .eval-input / .eval-radio
    const MIN_REJECTION_CHARS = 5;

    document.addEventListener('input', handleEvaluationChange);
    document.addEventListener('change', handleEvaluationChange);
    
    function handleEvaluationChange(e) {
        // Cek apakah yang berubah adalah elemen form evaluasi
        const evalElement = e.target.closest('.eval-input, .eval-radio, #eval_rejection_reason, #eval_peta_reason, #eval_persil_reason');
        if (!evalElement) return;
        if (evalElement) {

            // Cari div '#validation-content' terdekat sebagai panel
            const panel = evalElement.closest('#validation-content') || document.querySelector('#validation-content');
            if (!panel) return;

            // Ambil data detail global
            const currentDetails = window.currentValidationDetails;
            if (!currentDetails) {
                console.warn("### Data detail global (window.currentValidationDetails) kosong, tidak bisa cek form.");
                return;
            }
        
                // Jalankan pengecekan utama
            checkEvaluationForm(panel, currentDetails);
        }
    }


    // ===================================================================
    // ===== FUNGSI HELPER UNTUK LOGIKA EVALUASI (DENGAN ALASAN PETA & PERSIL) =====
    // ===================================================================

    /**
     * Mengecek status formulir evaluasi dan mengaktifkan/menonaktifkan tombol Validasi/Tolak.
     * @param {HTMLElement} panel - Elemen div '#validation-content'.
     * @param {object|null} details - Objek data detail yang berisi 'full_meter_number'.
     */
    function checkEvaluationForm(panel, details) {
    if (!panel || !details) return;

    // === Ambil elemen-elemen utama ===
    const meterInput = panel.querySelector('#eval_meter_input');
    const meterStatus = panel.querySelector('#eval_meter_status');
    const petaValue = panel.querySelector('input[name="eval_peta"]:checked')?.value;
    const persilValue = panel.querySelector('input[name="eval_persil"]:checked')?.value;

    const petaReasonContainer = panel.querySelector('#eval_peta_reason_container');
    const petaReasonSelect = panel.querySelector('#eval_peta_reason');
    const persilReasonContainer = panel.querySelector('#eval_persil_reason_container');
    const persilReasonSelect = panel.querySelector('#eval_persil_reason');

    const rejectionContainer = panel.querySelector('#rejection_reason_container');
    const rejectionReason = panel.querySelector('#eval_rejection_reason');
    const rejectionPlaceholder = panel.querySelector('#rejection_reason_placeholder');

    const validateButton = panel.querySelector('#detail-button-validate');
    const rejectButton = panel.querySelector('#detail-button-reject');

    if (!validateButton || !rejectButton) return;

    // === Reset tombol dan tampilan awal ===
    validateButton.disabled = true;
    rejectButton.disabled = true;
    validateButton.classList.add('opacity-50', 'cursor-not-allowed');
    rejectButton.classList.add('opacity-50', 'cursor-not-allowed');
    
    if (meterStatus) {
        meterStatus.textContent = '';
        meterStatus.classList.remove('text-green-500', 'text-red-500');
    }
    if (rejectionContainer) rejectionContainer.classList.add('hidden');
    if (rejectionPlaceholder) rejectionPlaceholder.classList.remove('hidden');
        // ============ LOGIKA METER ============
    const answerKey = details.full_meter_number || details.no_meter || details.meter_number || details.nomor_meter || '';
    let meterMatch = false;
    let meterNotMatch = false;
    const currentMeter = meterInput ? meterInput.value.trim() : '';

    if (answerKey && currentMeter.length > 0) {
        if (currentMeter === String(answerKey)) {
            meterMatch = true;
            if (meterStatus) { meterStatus.textContent = 'Nomor meter cocok!'; meterStatus.classList.add('text-green-500'); }
        } else {
            // yakin user sudah mengetik penuh jika panjang >= panjang kunci (atau panjang input > 0 boleh dianggap mismatch juga)
            if (currentMeter.length >= String(answerKey).length) {
                meterNotMatch = true;
                if (meterStatus) { meterStatus.textContent = 'Nomor meter tidak cocok!'; meterStatus.classList.add('text-red-500'); }
            } else {
                if (meterStatus) { meterStatus.textContent = 'Mengetik...'; }
            }
        }
    }

    // ============ TAMPILKAN / RESET DROPDOWN PETA & PERSIL ============
    const petaTidakSesuai = petaValue === 'tidak';
    const persilTidakSesuai = persilValue === 'tidak';

    if (petaReasonContainer) {
        if (petaTidakSesuai) petaReasonContainer.classList.remove('hidden');
        else { petaReasonContainer.classList.add('hidden'); if (petaReasonSelect) petaReasonSelect.value = ''; }
    }
    if (persilReasonContainer) {
        if (persilTidakSesuai) persilReasonContainer.classList.remove('hidden');
        else { persilReasonContainer.classList.add('hidden'); if (persilReasonSelect) persilReasonSelect.value = ''; }
    }

    // ============ ADA PENOLAKAN APA TIDAK ============
    const hasAnyRejection = meterNotMatch || petaTidakSesuai || persilTidakSesuai;

    if (hasAnyRejection) {
        if (rejectionContainer) rejectionContainer.classList.remove('hidden');
        if (rejectionPlaceholder) rejectionPlaceholder.classList.add('hidden');
    } else {
        if (rejectionContainer) rejectionContainer.classList.add('hidden');
        if (rejectionPlaceholder) rejectionPlaceholder.classList.remove('hidden');
    }

    // ============ CEK KEWAJIBAN ALASAN ============
    const isPetaSelected = typeof petaValue !== 'undefined';
    const isPersilSelected = typeof persilValue !== 'undefined';

    const isPetaReasonSelected = !petaTidakSesuai || (petaTidakSesuai && petaReasonSelect && petaReasonSelect.value.trim() !== '');
    const isPersilReasonSelected = !persilTidakSesuai || (persilTidakSesuai && persilReasonSelect && persilReasonSelect.value.trim() !== '');
    const isRejectionReasonFilled = !hasAnyRejection || (rejectionReason && rejectionReason.value.trim().length >= MIN_REJECTION_CHARS);

    // ============ AKTIFKAN TOMBOL SESUAI SKEMA ============
    // 1) SETUJU: semua 3 harus terpenuhi (meterMatch + peta sesuai + persil sesuai)
    if (meterMatch && petaValue === 'sesuai' && persilValue === 'sesuai') {
        validateButton.disabled = false;
        validateButton.classList.remove('opacity-50', 'cursor-not-allowed');
    }

    // 2) TOLAK: aktif jika salah satu kondisi gagal + alasan terisi
    if (hasAnyRejection) {
        // pastikan semua alasan spesifik untuk yang 'tidak' sudah dipilih, dan alasan umum terpenuhi
        if (isPetaSelected && isPersilSelected && isPetaReasonSelected && isPersilReasonSelected && isRejectionReasonFilled) {
            rejectButton.disabled = false;
            rejectButton.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    // DEBUG LOG (opsional)
    console.log('checkEvaluationForm', {
        answerKey, currentMeter, meterMatch, meterNotMatch,
        petaValue, persilValue,
        petaTidakSesuai, persilTidakSesuai,
        isPetaReasonSelected, isPersilReasonSelected,
        isRejectionReasonFilled, hasAnyRejection
    });
}



    // ===================================================================
    // ===== SATU EVENT LISTENER UNTUK SEMUA SUBMIT FORM =====
    // ===================================================================
    document.addEventListener('submit', function(e) {
        console.log('SUBMIT EVENT TERDETEKSI', e.target);

        const searchForm = e.target.closest('form[id*="-search-form"]');
        const validationForm = e.target.closest('#detail-form-validate, #detail-form-reject');
        const invalidateForm = e.target.closest('form[data-custom-handler="invalidate-action"]');
        const formInModal = e.target.closest('#modal-content form');
        const promoteForm = e.target.closest('form[data-custom-handler="promote-action"]');

        console.log('Mencari promoteForm:', promoteForm);
        // A. Submit Form Pencarian
        if (searchForm) {
            e.preventDefault();
            clearTimeout(searchDebounceTimer);
            const params = new URLSearchParams(new FormData(searchForm)).toString();
            const url = `${searchForm.action}?${params}`;
            loadTabContent(getActiveTabName(), url);
            return;
        }

        // B. Submit Form Invalidate
        if (invalidateForm) {
            e.preventDefault();
            
            // 1. Definisikan aksi yang akan dijalankan JIKA user menekan "OK"
            const onConfirmAction = () => {
            const originalButton = invalidateForm.querySelector('button[type="submit"]');
            const originalText = originalButton.textContent;
            
            // Tampilkan loading spinner sementara tombol dinonaktifkan
            originalButton.textContent = 'Memproses...';
            originalButton.disabled = true;
            originalButton.classList.add('opacity-50', 'cursor-not-allowed');

            fetch(invalidateForm.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: new FormData(invalidateForm)
            })
            .then(response => {
                const contentType = response.headers.get("content-type");

                if (response.ok) { // Server melaporkan SUKSES (status 2xx)
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        return response.json();
                    } else {
                        // Kasus Aneh: Sukses tapi BUKAN JSON (misal redirect setelah aksi)
                        console.warn("Server sukses (2xx) tapi mengirim non-JSON. Anggap aksi berhasil.");
                        // Buat objek sukses generik agar .then() berikutnya tetap jalan
                        return { message: 'Aksi invalidate berhasil diproses.' }; 
                    }
                } else { // Server melaporkan ERROR (status non-2xx)
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        // Kasus Error Terstruktur: Gagal + pesan error JSON
                        return response.json().then(err => { throw err; }); // Lempar error dari JSON
                    } else {
                        // Kasus Error Tidak Terstruktur: Gagal + HTML (Ini kemungkinan besar Sesi Habis atau Error PHP Fatal)
                        console.error("Server error (non-2xx) dan mengirim non-JSON. Kemungkinan sesi habis.");
                        throw new Error('Sesi Anda mungkin telah habis atau terjadi error server. Halaman akan dimuat ulang.'); // Lempar error sesi habis
                    }
                }
            })
            .then(data => {
                // Reset tombol
                originalButton.textContent = originalText;
                originalButton.disabled = false;
                originalButton.classList.remove('opacity-50', 'cursor-not-allowed');
                
                // Refresh tab (dengan cache busting)
                const tabNameToRefresh = getActiveTabName();
                if (tabNameToRefresh) {
                    const tabContent = document.getElementById(`${tabNameToRefresh}-content`);
                    if (tabContent) {
                        const searchForm = tabContent.querySelector('form[id*="-search-form"]');
                        let refreshUrl;
                        
                        if (searchForm) {
                            const params = new URLSearchParams(new FormData(searchForm)).toString();
                            refreshUrl = `${searchForm.action}?${params}`;
                        } else {
                            const tabButton = document.querySelector(`#tabs-header .tab-button[data-tab-name="${tabNameToRefresh}"]`);
                            if (tabButton) refreshUrl = tabButton.dataset.url || tabButton.href;
                        }

                        if (refreshUrl) {
                            let bustUrl = new URL(refreshUrl, window.location.origin);
                            bustUrl.searchParams.set('_cb', new Date().getTime()); 
                            loadTabContent(tabNameToRefresh, bustUrl.toString());
                            setTimeout(() => {
                                displayNotification('success', data.message || 'Aksi berhasil diproses.');
                            }, 1000); // Jeda 1 detik (1000ms) 
                        } else {
                            console.error("Gagal menentukan URL refresh tab.");
                        }
                    }
                }
            })
            .catch(error => {
                // Reset tombol
                originalButton.textContent = originalText;
                originalButton.disabled = false;
                originalButton.classList.remove('opacity-50', 'cursor-not-allowed');

                console.error('Error Invalidate:', error);
                
                // Cek apakah ini error "Sesi Habis" yang kita buat
                if (error.message.includes('Sesi Anda mungkin telah habis')) {
                    alert(error.message); // Beri tahu pengguna
                    window.location.reload(); // Muat ulang halaman
                } else {
                    // Tampilkan error lain (misal dari JSON error server atau network error)
                    const errorMessage = error.message || 'Terjadi kesalahan saat memproses invalidate.';
                    displayNotification('error', errorMessage);
                }
            });
            };

            // 2. Panggil modal kustom baru kita
            showCustomConfirm(
                'Konfirmasi Invalidate', // Judul
                'Anda yakin ingin mengembalikan data ini ke antrian validasi?', // Pesan
                onConfirmAction // Aksi yang dijalankan jika "OK"
            );

            return; // Penting agar listener submit berhenti di sini
        }

        if (promoteForm) {
            e.preventDefault(); // Cegah submit

            // 1. Definisikan aksi jika "OK"
            const onConfirmAction = () => {
                // Tampilkan loading di tombol
                const originalButton = promoteForm.querySelector('button[type="submit"]');
                const originalInnerHTML = originalButton.innerHTML; // Simpan ikon aslinya
                
                originalButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; // Ganti ikon jadi spinner
                originalButton.disabled = true;
                
                // --- LOGIKA FETCH BARU ---
                fetch(promoteForm.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json', // Minta JSON, bukan HTML redirect
                    },
                    body: new FormData(promoteForm)
                })
                .then(response => {
                    // 1. Jika respons BUKAN OK (misalnya 401, 419, 500)
                    if (!response.ok) {
                        // Jika status 419 (CSRF token expired), kita harus reload
                        if (response.status === 419) {
                            throw new Error('Sesi telah kedaluwarsa (CSRF Token Hilang). Halaman akan dimuat ulang.');
                        }
                        // Coba ambil pesan error dari JSON
                        return response.json().then(err => {
                            throw new Error(err.message || `Gagal: ${response.status} ${response.statusText}`);
                        }).catch(() => {
                            // Jika gagal parse JSON (kemungkinan HTML error page), lempar error umum
                            throw new Error(`Aksi Gagal. Status: ${response.status}. Periksa log server.`);
                        });
                    }
                    
                    // 2. Jika respons OK (200), coba parse JSON untuk sukses
                    return response.json(); 
                })
                .then(data => {
                    // Reset tombol
                    originalButton.innerHTML = originalInnerHTML;
                    originalButton.disabled = false;
                    
                    // Muat ulang tab + tunda notifikasi
                    const tabNameToRefresh = getActiveTabName();
                    if (tabNameToRefresh) {
                        const tabContent = document.getElementById(`${tabNameToRefresh}-content`);
                        if (tabContent) {
                            // Ambil URL refresh (gunakan URL yang sedang dicari/aktif)
                            const searchForm = tabContent.querySelector('form[id*="-search-form"]');
                            let refreshUrl;
                            if (searchForm) {
                                const params = new URLSearchParams(new FormData(searchForm)).toString();
                                refreshUrl = `${searchForm.action}?${params}`;
                            } else {
                                const tabButton = document.querySelector(`#tabs-header .tab-button[data-tab-name="${tabNameToRefresh}"]`);
                                if (tabButton) refreshUrl = tabButton.dataset.url || tabButton.href;
                            }

                            if (refreshUrl) {
                                let bustUrl = new URL(refreshUrl, window.location.origin);
                                bustUrl.searchParams.set('_cb', new Date().getTime()); 
                                
                                loadTabContent(tabNameToRefresh, bustUrl.toString()); 
                                
                                // TUNDA notifikasi
                                setTimeout(() => {
                                    displayNotification('success', data.message || 'Data berhasil dipromosikan.');
                                }, 1000); 
                            }
                        }
                    }
                })
                .catch(error => {
                    // Reset tombol
                    originalButton.innerHTML = originalInnerHTML;
                    originalButton.disabled = false;

                    console.error('Error Promote:', error);
                    if (error.message.includes('Sesi habis')) {
                        alert(error.message);
                        window.location.reload();
                    } else {
                        displayNotification('error', error.message || 'Gagal mempromosikan data.');
                    }
                });
            };

            // 2. Panggil modal kustom
            showCustomConfirm(
                'Konfirmasi Promosi Data', // Judul
                'Jadikan data ini sebagai data VALID (AKTIF)? Data valid yang lama (jika ada) akan digantikan.', // Pesan
                onConfirmAction // Aksi
            );

            return; // Hentikan listener
        }

        // C. Submit Form di Dalam Modal (Edit User, dll)
        if (formInModal) {
            if (formInModal.hasAttribute('data-custom-handler')) {
                return; // Abaikan jika sudah ditangani (seperti upload chunk)
            }
            e.preventDefault();
            const submitButton = formInModal.querySelector('button[type="submit"]');
            const originalButtonText = submitButton ? submitButton.innerHTML : 'Simpan';
            if(submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            }

            fetch(formInModal.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: new FormData(formInModal)
            })
            .then(response => {
                if (!response.ok) return response.json().then(err => { throw err; });
                return response.json();
            })
            .then(data => {
                if (data.message) {
                    closeModal();
                    displayNotification('success', data.message);

                    // Logika refresh tab yang dinamis
                    let tabNameToRefresh = 'Dashboard'; // default

                    if (formInModal.id === 'create-mapping-form') {
                        tabNameToRefresh = 'Data Mapping Pelanggan';
                    } else if (formInModal.id === 'edit-user-form') { // Asumsi ID form edit user
                        tabNameToRefresh = 'Daftar Pengguna';
                    }

                    // Panggil fungsi refresh
                    const tabButton = document.querySelector(`#tabs-header .tab-button[data-tab-name="${tabNameToRefresh}"]`);
                    if(tabButton) {
                         loadTabContent(tabNameToRefresh, tabButton.dataset.url || tabButton.href);
                    }
                }
            })
            .catch(error => {
                // --- PENANGANAN ERROR (422) ---
                console.error('Error:', error);
                if(submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }

                if (error.errors) {
                    let errorDiv = null;
                    // Cari div error yang tepat berdasarkan ID form
                    if (formInModal.id === 'create-mapping-form') {
                        errorDiv = formInModal.querySelector('#create-mapping-errors');
                    } else if (formInModal.id === 'edit-user-form') { // Ganti jika ID form edit user Anda beda
                        errorDiv = formInModal.querySelector('#edit-user-errors');
                    }

                    if (errorDiv) {
                        let errorList = '<ul>';
                        for (const key in error.errors) {
                            // error.errors[key] adalah array, ambil pesan pertama [0]
                            errorList += `<li class="text-sm">- ${error.errors[key][0]}</li>`;
                        }
                        errorList += '</ul>';
                        errorDiv.innerHTML = errorList;
                        errorDiv.classList.remove('hidden');
                        
                        // Scroll ke atas modal agar error terlihat
                        modalContent.scrollTop = 0;
                    } else {
                        // Fallback jika div error tidak ditemukan
                        alert('Validasi gagal. Cek console untuk detail.');
                    }
                } else {
                    alert(error.message || 'Terjadi kesalahan saat memproses data.');
                }
            });
        }

        // C. Submit Form Validasi / Reject
        if (validationForm) {
            e.preventDefault();

            // --- 1. Konfirmasi (Sudah dipindahkan dari HTML) ---
            const isReject = validationForm.id === 'detail-form-reject';
            if (isReject) {
                if (!confirm('Anda yakin ingin MENOLAK data ini? Data dan foto TIDAK akan dihapus, tetapi statusnya akan diubah menjadi "Rejected" dan dilepaskan dari antrian Anda.')) {
                    return; // Batalkan proses jika pengguna menekan "Cancel"
                }
            }

            // --- 2. Setup Loading ---
            const url = validationForm.action;
            const originalButton = validationForm.querySelector('button[type="submit"]');
            const originalText = originalButton.textContent;

            // Tampilkan loading spinner sementara tombol dinonaktifkan
            originalButton.textContent = 'Memproses...';
            originalButton.disabled = true;
            originalButton.classList.add('opacity-50', 'cursor-not-allowed');

            // Tentukan panel utama
            const panel = validationForm.closest('#validation-content');

            const form = new FormData(validationForm);

            if (panel) {
                // 1. Kumpulkan Data Evaluasi Terstruktur (Radio, Dropdown, Meter Input)
                const evalData = {
                    // Gunakan || null untuk memastikan key terkirim, meskipun nilainya null
                    eval_peta: panel.querySelector('input[name="eval_peta"]:checked')?.value || null,
                    eval_peta_reason: panel.querySelector('#eval_peta_reason')?.value || null,
                    eval_persil: panel.querySelector('input[name="eval_persil"]:checked')?.value || null,
                    eval_persil_reason: panel.querySelector('#eval_persil_reason')?.value || null,
                    eval_meter_input: panel.querySelector('#eval_meter_input')?.value || null, // Nomor Meter Input
                };
                
                // 2. Ambil Alasan Penolakan Umum (Textarea)
                const rejectionReason = panel.querySelector('#eval_rejection_reason')?.value || ''; 

                // 3. Tambahkan ke FormData
                
                // Wajib: Stringify data terstruktur
                form.append('validation_data', JSON.stringify(evalData));
                
                // Tambahkan alasan penolakan umum (string)
                form.append('validation_notes', rejectionReason);
            }

            // --- 3. Kirim AJAX ---
            fetch(url, {
                method: 'POST', // Kirim selalu sebagai POST, Laravel akan mengurus DELETE/PUT melalui _method
                headers: {
                    // Wajib kirim CSRF dan minta JSON
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: form
            })

            // --- BLOK 1: MEMBACA RESPONS MENTAH DAN MENENTUKAN STATUS ---
            .then(async response => {
                const status = response.status;
                let data = null;
                
                // Coba dapatkan teks respons untuk debug/fallback
                const responseText = await response.text(); 
                
                // 1. Coba parse JSON
                try {
                     if (responseText) {
                        data = JSON.parse(responseText);
                     }
                } catch(e) {
                    // Jika parsing JSON gagal, cek apakah respons seharusnya JSON
                    if (contentType && contentType.indexOf("application/json") !== -1) {
                        console.error("Gagal parsing JSON meskipun Content-Type: application/json. Response text:", responseText);
                        // Lempar error untuk masuk ke catch, tapi gunakan Status code yang valid
                        throw new Error(`Respons JSON tidak valid. Status: ${response.status}`);
                    }
                    // Jika bukan JSON, biarkan data tetap null, dan lanjutkan ke pengecekan status
                }
                
                // 2. Pengecekan Status HTTP
                if (!response.ok) { // Jika status code BUKAN 2xx
                    // Jika ada data JSON yang berhasil di-parse, ambil pesan errornya
                    if (data && (data.error || data.message)) {
                         throw new Error(data.error || data.message); 
                    } else if (responseText) {
                         // Jika ada teks respons non-JSON (misal: error page HTML)
                         throw new Error(`Gagal memproses. Status HTTP: ${response.status}. Respons server: ${responseText.substring(0, 100)}...`);
                    } else {
                         throw new Error(`Gagal memproses. Status HTTP: ${response.status}.`);
                    }
                }
                
                // 3. Jika Status OK (2xx)
                if (data) return data;
                
                // Jika OK, tapi tidak ada data yang valid (respons kosong atau non-JSON yang sukses)
                return { action_type: 'validate', status_message: 'Operasi berhasil. Detail item berikutnya dimuat.' };
            })

            // --- 4. PENANGANAN SUKSES (STATUS 2xx DARI SERVER) ---
            .then(data => {
                // Reset tombol (akan dinonaktifkan lagi oleh checkEvaluationForm jika diperlukan)
                originalButton.textContent = originalText;
                originalButton.disabled = false;
                originalButton.classList.remove('opacity-50', 'cursor-not-allowed');
                
                // Muat ulang form validation untuk mengaplikasikan status disabled yang benar
                checkEvaluationForm(validationForm.closest('#validation-content'), window.currentValidationDetails); 
                const isRejectForm = validationForm.id === 'detail-form-reject';

                let notificationType = data.action_type || (isRejectForm ? 'reject' : 'success');

                // Gunakan pesan dari server atau pesan fallback jika tidak ada
                const successMessage = data.status_message || 
                                       (notificationType === 'reject' ? 'Penolakan berhasil. Item berikutnya dimuat.' : 'Validasi berhasil. Item berikutnya dimuat.');

                // 1. Tampilkan Notifikasi (dengan warna yang benar)
                displayNotification(notificationType, successMessage);
                
                // 2. Muat Item Baru atau Tampilkan Placeholder
                if (data.queue_empty) {
                    const container = validationForm.closest('#interactive-validation-container');
                    container.querySelector('#validation-content').classList.add('hidden');
                    container.querySelector('#validation-placeholder').classList.remove('hidden');
                    currentValidationId = null;
                    window.currentValidationDetails = null;
                } else {
                    // Item baru ditemukan: Panggil updateValidationUI
                    currentValidationId = data.currentItemId;
                    window.currentValidationDetails = data.details;
                    const container = validationForm.closest('#interactive-validation-container');
                    updateValidationUI(container, data.details);
                    
                    // Hapus highlight dari item lama di daftar antrian
                    container.querySelectorAll('.validation-queue-item.bg-indigo-100').forEach(btn => {
                        btn.classList.remove('bg-indigo-100', 'dark:bg-indigo-900');
                    });
                }
                
                // 3. Refresh List Antrian
                refreshValidationQueue(false);
            })

            // --- 5. PENANGANAN ERROR (STATUS NON-2xx ATAU JARINGAN) ---
            .catch(error => {
                // Reset tombol dan tampilkan pesan error
                originalButton.textContent = originalText;
                originalButton.disabled = false;
                originalButton.classList.add('opacity-50', 'cursor-not-allowed');
                
                // Tampilkan pesan error yang lebih spesifik
                console.error('Validation/Rejection Error:', error);
                displayNotification('error', error.message || 'Terjadi kesalahan saat memproses data.');
            });
            
            return;
        }
        
    });

    // ===================================================================
    // ===== EVENT LISTENER UNTUK LIVE SEARCH (SAAT MENGETIK) =====
    // ===================================================================
    document.addEventListener('input', function(e) {
        const searchInput = e.target.closest('form[id*="-search-form"] input[name="search"]');
        if (searchInput) {
            const searchForm = searchInput.closest('form');
            const clearButton = searchForm.querySelector('#clear-search-button');
            if (clearButton) {
                clearButton.classList.toggle('hidden', searchInput.value.length === 0);
            }
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                const params = new URLSearchParams(new FormData(searchForm)).toString();
                const url = `${searchForm.action}?${params}`;
                loadTabContent(getActiveTabName(), url);
            }, 900);
        }
    });
    
    // ===================================================================
    // ===== Mencegah Aksi Drag-Drop Default di Seluruh Halaman =====
    // ===================================================================
    window.addEventListener("dragover", e => e.preventDefault(), false);
    window.addEventListener("drop", e => e.preventDefault(), false);

    // ===================================================================
    // ===== SEMUA FUNGSI HELPER (TAB, MODAL, DLL) =====
    // ===================================================================
    
    function getActiveTabName() {
        const activeTab = tabsHeader.querySelector('.tab-button.active');
        return activeTab ? activeTab.dataset.tabName : null; 
    }

    function createTab(tabName, url, isClosable = true, pushHistory = true) {
        if (tabName === 'Dashboard') isClosable = false;
        
        const tabButton = document.createElement('a');
        tabButton.href = url;
        tabButton.dataset.url = url;
        tabButton.textContent = tabName;
        
        tabButton.className = 'tab-button flex items-center';
        tabButton.dataset.tabName = tabName;
        tabButton.onclick = (e) => {
            e.preventDefault();
            activateTab(tabName, url, true);
        };

        if (isClosable) {
            const closeButton = document.createElement('i');
            closeButton.className = 'tab-close-button fas fa-times text-gray-400 hover:text-gray-900 ml-2';
            closeButton.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                closeTab(tabName);
            };
            tabButton.appendChild(closeButton);
        }

        const tabContent = document.createElement('div');
        tabContent.id = `${tabName}-content`;
        tabContent.className = 'tab-content hidden';
        tabsHeader.appendChild(tabButton);
        tabsContent.appendChild(tabContent);

        loadTabContent(tabName, url); 
        activateTab(tabName, url, pushHistory);
    }

    function loadTabContent(tabName, url) {
        const tabContent = document.getElementById(`${tabName}-content`);
        if (!tabContent) return;

        // 1. Bersihkan DOM dan tampilkan Spinner
        // Hancurkan peta KDDK Mapping jika ada
        if (window.mapInstance) {
            window.mapInstance.remove();
            window.mapInstance = null;
        }

        tabContent.innerHTML = `<div class="flex justify-center items-center p-10"><i class="fas fa-spinner fa-spin fa-3x text-gray-400"></i></div>`;
        
        let fetchUrl = new URL(url, window.location.origin);
        fetchUrl.searchParams.set('is_ajax', '1');

        fetch(fetchUrl.toString())
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok.');
            return response.text();
        })
        .then(html => {
            // [PERBAIKAN KRUSIAL] Hapus konten lama dan ganti dengan konten baru
            
            // 1. Buat kontainer sementara dari HTML yang baru diterima
            const newContentContainer = document.createElement('div');
            newContentContainer.innerHTML = html;
            
            // 2. Kosongkan tab konten sepenuhnya
            tabContent.innerHTML = ''; 
            
            // 3. Tambahkan kontainer konten baru
            tabContent.appendChild(newContentContainer);
            
            // 4. Jeda untuk memastikan browser selesai merender dan memproses semua elemen
            setTimeout(() => {
                
                // 5. Inisialisasi Ulang Semua Script yang Dibutuhkan
                
                // Cek dan atur tampilan tombol clear search
                const searchInput = tabContent.querySelector('form[id*="-search-form"] input[name="search"]');
                if (searchInput && searchInput.value.length > 0) {
                    const clearButton = tabContent.querySelector('#clear-search-button');
                    if (clearButton) clearButton.classList.remove('hidden');
                }
    
                // Inisialisasi Peta (KDDK Mapping)
                const mapContainer = tabContent.querySelector('#map');
                if (mapContainer) {
                    initializeMap(mapContainer);
                } 
    
                // Inisialisasi Tab Validasi
                const validationContainer = tabContent.querySelector('#interactive-validation-container');
                if (validationContainer) {
                    initializeValidationTab(validationContainer); 
                }
                
                // Update Scroll Tabs
                updateScrollButtons();
                
                // Update History
                const cleanUrl = new URL(url, window.location.origin);
                cleanUrl.searchParams.delete('is_ajax');
                history.pushState({ tab: tabName }, '', cleanUrl.toString());

            }, 100); // Jeda 100ms
        })
        .catch(error => {
            tabContent.innerHTML = `<div class="p-4 text-red-500">Gagal memuat konten.</div>`;
            console.error('Error loading tab content:', error);
        });
    }

    function initializeMap(mapContainer) {
        if (!mapContainer) return;

        // 1. Hancurkan instance peta lama jika ada
        if (window.mapInstance) {
            window.mapInstance.remove();
            window.mapInstance = null;
        }

        // 2. Buat instance peta baru dan simpan di global
        const map = L.map(mapContainer).setView([0.5071, 101.4478], 12);
        window.mapInstance = map;

        // 3. Tambahkan tile layer
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri'
        }).addTo(map);
       
        // 5. Ambil nilai pencarian
        const activeTabContent = mapContainer.closest('.tab-content');
        const searchInput = activeTabContent.querySelector('#mapping-search-form input[name="search"]');
        const searchValue = searchInput ? searchInput.value : '';

        // 6. [MODIFIKASI] Bersihkan semua marker lama
        // (Baik dari pencarian sebelumnya ATAU dari klik tabel)
        if (mappingFeatureGroup) {
            mappingFeatureGroup.clearLayers();
        }
        if (mappingClickedMarker) {
            mappingClickedMarker.remove();
            mappingClickedMarker = null;
        }

        // 7. [MODIFIKASI] Hentikan jika tidak ada pencarian (load awal)
        // Ini akan membuat peta tetap kosong, sesuai permintaan Anda
        if (!searchValue) {
            console.log("initializeMap: Tidak ada search value, peta akan kosong.");
            return;
        }

        // 8. Bangun URL dan ambil data koordinat (HANYA JIKA ADA PENCARIAN)
        let coordinatesUrl = new URL('/team/mapping-coordinates', window.location.origin);
        if (searchValue) {
            coordinatesUrl.searchParams.set('search', searchValue);
        }
        
        fetch(coordinatesUrl.toString())
            .then(response => response.json())
            .then(data => {
                // [MODIFIKASI] Gunakan variabel global, BUKAN 'const allMarkers'
                mappingFeatureGroup = L.featureGroup();
                let markerToOpen = null;

                // KONDISI 1: Menampilkan semua marker (jika controller mengirim 'all')
                if (data.all && data.all.length > 0) {
                    data.all.forEach(point => {
                        const marker = L.marker([point.latitudey, point.longitudex], { icon: blueIcon });
                        marker.bindPopup(`<b>Idpel:</b> ${point.idpel}`);
                        mappingFeatureGroup.addLayer(marker); 
                    });
                }

                // KONDISI 2: Menampilkan hasil pencarian (merah) dan terdekat (biru)
                if (data.searched && data.searched.length > 0) {
                    data.searched.forEach(point => {
                        const marker = L.marker([point.latitudey, point.longitudex], { icon: redIcon });
                        marker.bindPopup(`<b>Idpel (dicari):</b> ${point.idpel}`);
                        mappingFeatureGroup.addLayer(marker); 

                        if (!markerToOpen) {
                            markerToOpen = marker;
                         }
                    });
                }

                // KONDISI 3: Menampilkan 'nearby' (biru)
                if (data.nearby && data.nearby.length > 0) {
                    data.nearby.forEach(point => {
                        const marker = L.marker([point.latitudey, point.longitudex], { icon: blueIcon });
                        marker.bindPopup(`<b>Idpel Terdekat:</b> ${point.idpel}`);
                        mappingFeatureGroup.addLayer(marker); 
                    });
                }
                
                // [MODIFIKASI] Tambahkan grup marker ke peta
                if (mappingFeatureGroup.getLayers().length > 0) {
                    mappingFeatureGroup.addTo(map);
                    
                    // [MODIFIKASI] Gunakan mappingFeatureGroup.getBounds()
                    if (searchValue && data.searched && data.searched.length === 1 && (!data.nearby || data.nearby.length === 0)) {
                        map.setView(markerToOpen.getLatLng(), 18);
                        markerToOpen.openPopup();
                    } 
                    else if (markerToOpen) {
                        map.fitBounds(mappingFeatureGroup.getBounds().pad(0.1)); // [MODIFIKASI]
                        setTimeout(() => {
                            markerToOpen.openPopup();
                        }, 500);
                    }
                    else {
                        map.fitBounds(mappingFeatureGroup.getBounds().pad(0.1)); // [MODIFIKASI]
                    }
                }
            })
            .catch(error => console.error('Error fetching map data:', error));
    }

    async function renderClickedMapMarkers(idpel, objectid, lat, lon) {
        // Hapus marker lama (dari pencarian) dan marker klik sebelumnya
        if (mappingFeatureGroup) {
            mappingFeatureGroup.clearLayers();
        }
        if (mappingClickedMarker) {
            mappingClickedMarker.remove();
            mappingClickedMarker = null;
        }

        let coordinatesUrl = new URL('/team/mapping-coordinates', window.location.origin);
        // Panggil controller menggunakan IDPEL yang diklik
        coordinatesUrl.searchParams.set('search', idpel);

        try {
            const response = await fetch(coordinatesUrl.toString());
            const data = await response.json();

            // Kumpulkan marker di grup baru
            mappingFeatureGroup = L.featureGroup();
            let clickedMarkerRef = null;

            // 1. Tambahkan marker nearby (biru)
            if (data.nearby && data.nearby.length > 0) {
                data.nearby.forEach(point => {
                    // Gunakan ikon biru untuk nearby
                    const marker = L.marker([point.latitudey, point.longitudex], { icon: blueIcon });
                    marker.bindPopup(`<b>Idpel (terdekat):</b> ${point.idpel}`).openPopup();
                    mappingFeatureGroup.addLayer(marker);
                });
            }

            // 2. Tambahkan marker item yang diklik (merah, highlight)
            // (Kita harus buat ulang secara manual agar selalu muncul dan menimpa yang lain)
            clickedMarkerRef = L.marker([lat, lon], { 
                icon: redIcon,
                zIndexOffset: 1000 // Pastikan ini di atas yang biru
            });
            clickedMarkerRef.on('popupopen', function() {
                // Cari elemen pop-up yang baru saja dibuka
                const popupElement = clickedMarkerRef.getPopup().getElement();
                if (popupElement) {
                    // Cari semua tombol close atau elemen interaktif di dalamnya
                    const closeButton = popupElement.querySelector('.leaflet-popup-close-button');
                    
                    // Tambahkan event listener ke tombol close
                    if (closeButton) {
                        closeButton.addEventListener('click', function(e) {
                            // Kunci: Hentikan event agar tidak menyebar ke event listener global (tab-manager.js:91)
                            e.stopPropagation();
                        });
                    }
                    
                    // Jika Anda punya tombol/tautan lain di dalam pop-up:
                    // Misalnya: popupElement.querySelectorAll('a').forEach(link => { link.addEventListener('click', e => e.stopPropagation()); });
                }
            });
            clickedMarkerRef.bindPopup(`<b>Idpel:</b> ${idpel}<br><b>Object ID:</b> ${objectid}`,{
                maxWidth: 250,
                className: 'leaflet-popup-small'
            });
            
            // Simpan marker ini di global agar bisa dihapus di klik berikutnya
            mappingClickedMarker = clickedMarkerRef; 

            // 3. Tambahkan marker yang diklik (merah) dan grup nearby (biru) ke peta
            if (mappingFeatureGroup.getLayers().length > 0) {
                 mappingFeatureGroup.addTo(window.mapInstance);
            }
            if (mappingClickedMarker) {
                 mappingClickedMarker.addTo(window.mapInstance);
            }
            if (clickedMarkerRef) {
                // Gunakan setTimeout 0 untuk menunda eksekusi ke akhir event loop saat ini
                setTimeout(() => {
                    clickedMarkerRef.openPopup();
                }, 50); // Jeda 50ms
            }

        } catch (error) {
            console.error('Error fetching coordinates for clicked item:', error);
        }
    }

    function initializeValidationMap(mapContainer, lat, lon, idpel) {
        if (!mapContainer) return;
        
        // 1. Bersihkan instance peta lama jika ada (validationMapInstance)
        if (window.validationMapInstance) {
            window.validationMapInstance.remove();
            window.validationMapInstance = null;
        }
        
        // 2. Buat instance peta baru
        const map = L.map(mapContainer).setView([lat, lon], 18); // Set view ke koordinat item
        window.validationMapInstance = map;

        // 3. Tambahkan tile layer
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri'
        }).addTo(map);

        // 4. Hapus marker lama (jika ada)
        if (window.validationMarker) {
            window.validationMarker.remove();
            window.validationMarker = null;
        }

        // 5. Tambahkan marker (menggunakan redIcon global)
        // Catatan: Pastikan variabel 'redIcon' sudah didefinisikan secara global di Langkah 1 sebelumnya.
        window.validationMarker = L.marker([lat, lon], { icon: redIcon }) 
                               .addTo(map);
        
        window.validationMarker.bindPopup(`<b>Idpel:</b> ${idpel}`,{
            maxWidth: 150,
            className: 'leaflet-popup-small'
        }).openPopup();
    }

    function activateTab(tabName, url, pushHistory = true) {
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));

        const activeTabButton = tabsHeader.querySelector(`[data-tab-name="${tabName}"]`);
        const activeTabContent = document.getElementById(`${tabName}-content`);
        
        if (activeTabButton) {
            activeTabButton.classList.add('active');
            activeTabButton.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
        if (activeTabContent) {
            activeTabContent.classList.remove('hidden');

            const mapContainer = activeTabContent.querySelector('#map');
            if (mapContainer) {
                setTimeout(function() {
                  initializeMap(mapContainer);
            }, 150); // Diberi sedikit jeda agar transisi tab selesai
        }
        }
        
        if (pushHistory) {
            const newUrl = new URL(url, window.location.origin);
            newUrl.searchParams.delete('is_ajax');
            history.replaceState({ tab: tabName }, '', newUrl.toString());
        }
        updateScrollButtons();
    }
    
    function closeTab(tabName) {
        const tabToClose = tabsHeader.querySelector(`[data-tab-name="${tabName}"]`);
        const contentToClose = document.getElementById(`${tabName}-content`);
        if (!tabToClose) return;

        const wasActive = tabToClose.classList.contains('active');
        const nextTab = tabToClose.nextElementSibling || tabToClose.previousElementSibling;

        tabToClose.remove();
        if(contentToClose) contentToClose.remove();

        if (wasActive && nextTab) {
            activateTab(nextTab.dataset.tabName, nextTab.dataset.url, true);
        } else if (tabsHeader.children.length > 0) {
            const firstTab = tabsHeader.children[0];
            activateTab(firstTab.dataset.tabName, firstTab.dataset.url, true);
        } else {
            createTab('Dashboard', dashboardUrl, false, true);
        }
        updateScrollButtons();
    }
    
    function openModal(url) {
        const mainModal = document.getElementById('main-modal');
        const modalContent = document.getElementById('modal-content');

        if (!mainModal || !modalContent) return;
        modalContent.innerHTML = '<div class="text-center p-8"><i class="fas fa-spinner fa-spin fa-2x text-gray-400"></i></div>';
        mainModal.classList.remove('hidden');

        let fetchUrl = new URL(url, window.location.origin);
        fetchUrl.searchParams.set('is_ajax', '1');

        fetch(fetchUrl)
            .then(response => response.text())
            .then(html => {
                modalContent.innerHTML = html;
                setTimeout(function() {
                    const previewMapContainer = modalContent.querySelector('#preview-map');
                    if (previewMapContainer) {
                        initializePreviewMap(modalContent); // Kirim seluruh konten modal
                    }
                    const photoUploadInputs = modalContent.querySelectorAll('.photo-upload-input');
                    if (photoUploadInputs.length > 0) {
                        initializePhotoUpload(modalContent);
                    }
                    const createForm = modalContent.querySelector('#create-mapping-form');
                    if (createForm) {
                        initializeCreateFormValidation(createForm); // Panggil fungsi helper baru
                    }
                }, 150);

                if (window.UploadInitializers && typeof window.UploadInitializers.initializeUploadForm === 'function') {
                    if (modalContent.querySelector('#upload-form')) {
                        window.UploadInitializers.initializeUploadForm();
                    }   
                }
                
                if (window.UploadInitializers && typeof window.UploadInitializers.initializeBatchPhotoUploadForm === 'function') {
                    if (modalContent.querySelector('#batch-photo-upload-form')) { // Cek ID form FOTO
                        window.UploadInitializers.initializeBatchPhotoUploadForm();
                        
                    }
                }
            })
            .catch(error => {
                modalContent.innerHTML = '<div class="text-center p-8 text-red-500">Gagal memuat konten.</div>';
                console.error("Fetch Error:", error);
            });
    }

    function initializePreviewMap(modalContent) {
        const mapContainer = modalContent.querySelector('#preview-map');
        const latInput = modalContent.querySelector('#latitudey_create');
        const lonInput = modalContent.querySelector('#longitudex_create');

        // Elemen Tab Peta
        const mapTabButton = modalContent.querySelector('#tab-btn-map');
        const mapTabPanel = modalContent.querySelector('#tab-panel-map');
        
        // Elemen Tab Street View
        const streetViewTabButton = modalContent.querySelector('#tab-btn-streetview');
        const streetViewTabPanel = modalContent.querySelector('#tab-panel-streetview');
        const streetViewIframe = modalContent.querySelector('#create-street-view-iframe');
        const streetViewPlaceholder = modalContent.querySelector('#create-street-view-placeholder');

        if (!mapContainer || !latInput || !lonInput || !streetViewIframe || !mapTabButton) return;

        // Hancurkan map lama jika ada
        if (mapContainer._leaflet_id) { mapContainer._leaflet_id = null; }

        // --- 2. Inisialisasi Peta Leaflet ---
        const previewMap = L.map(mapContainer).setView([0.5071, 101.4478], 12);
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri'
        }).addTo(previewMap);

        let previewMarker = null;

        // --- 3. Fungsi untuk Update SEMUA Pratinjau ---
        // --- 3. Fungsi untuk Update SEMUA Pratinjau ---
        function updatePreviews() {
            const lat = parseFloat(latInput.value);
            const lon = parseFloat(lonInput.value);
            
            if (!isNaN(lat) && !isNaN(lon)) {
                // A. Update Peta Satelit
                if (previewMarker) {
                    previewMarker.remove();
                }
                previewMarker = L.marker([lat, lon]).addTo(previewMap);
                previewMap.setView([lat, lon], 17);
                
                // B. Update Street View
                const streetViewUrl = `https://www.google.com/maps/embed/v1/streetview?location=${lat},${lon}&key=${GOOGLE_API_KEY}`;
                streetViewIframe.src = streetViewUrl;
                streetViewIframe.classList.remove('hidden');
                streetViewPlaceholder.classList.add('hidden');

            } else {
                // Reset jika Lat/Lon tidak valid
                if (previewMarker) {
                    previewMarker.remove();
                    previewMarker = null;
                }
                streetViewIframe.src = '';
                streetViewIframe.classList.add('hidden');
                streetViewPlaceholder.classList.remove('hidden');
            }
        }

        // --- 4. Pasang Event Listener ---
        
        // Panggil update saat mengetik Lat/Lon
        latInput.addEventListener('input', updatePreviews);
        lonInput.addEventListener('input', updatePreviews);

        // Listener untuk ganti Tab
        streetViewTabButton.addEventListener('click', () => {
            // Aktifkan tombol Street View
            streetViewTabButton.classList.add('border-indigo-500', 'text-indigo-600', 'dark:text-indigo-400');
            streetViewTabButton.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300');
            // Nonaktifkan tombol Peta
            mapTabButton.classList.remove('border-indigo-500', 'text-indigo-600', 'dark:text-indigo-400');
            mapTabButton.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300');
            
            // Tampilkan panel Street View
            streetViewTabPanel.classList.remove('hidden');
            mapTabPanel.classList.add('hidden');
        });

        mapTabButton.addEventListener('click', () => {
            // Aktifkan tombol Peta
            mapTabButton.classList.add('border-indigo-500', 'text-indigo-600', 'dark:text-indigo-400');
            mapTabButton.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300');
            // Nonaktifkan tombol Street View
            streetViewTabButton.classList.remove('border-indigo-500', 'text-indigo-600', 'dark:text-indigo-400');
            streetViewTabButton.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300');

            // Tampilkan panel Peta
            mapTabPanel.classList.remove('hidden');
            streetViewTabPanel.classList.add('hidden');
            
            // PENTING: Peta Leaflet perlu "refresh" ukuran saat panelnya terlihat
            setTimeout(() => previewMap.invalidateSize(), 50);
        });

        // Panggil sekali saat inisialisasi
        updatePreviews();
    }

    function initializePhotoUpload(modalContent) {
        modalContent.querySelectorAll('.photo-upload-input').forEach(input => {
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                const inputId = e.target.id;
                const statusDiv = document.getElementById(inputId.replace('_create', '_status'));
                const filenameInput = document.getElementById(inputId.replace('_create', '_filename'));
                const progressContainer = document.getElementById(inputId.replace('_create', '_progress_container'));
                const progressBar = document.getElementById(inputId.replace('_create', '_progress_bar'));
                const form = input.closest('form');
                const uploadUrl = form.dataset.uploadPhotoUrl;

                // 1. Hapus file lama jika ada
                const oldFilename = filenameInput.value;
                if (oldFilename) {
                    fetch('/team/mapping-delete-photo', {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: JSON.stringify({ filename: oldFilename })
                    });
                }

                // Reset UI
                statusDiv.innerHTML = '';
                filenameInput.value = '';
                if(progressContainer) progressContainer.classList.add('hidden');
                if(progressBar) progressBar.style.width = '0%';

                // Jika pengguna batal memilih file, hentikan proses
                if (!file) {
                    return;
                }
                
                // 2. Mulai proses upload file baru
                progressContainer.classList.remove('hidden');
                
                const formData = new FormData();
                formData.append('photo', file);

                const xhr = new XMLHttpRequest();
                xhr.open('POST', uploadUrl, true);
                xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                xhr.setRequestHeader('Accept', 'application/json');

                // Event listener untuk melacak progress upload
                xhr.upload.onprogress = function(event) {
                    if (event.lengthComputable) {
                        const percentComplete = Math.round((event.loaded / event.total) * 100);
                        progressBar.style.width = percentComplete + '%';
                        statusDiv.textContent = `Mengunggah... ${percentComplete}%`;
                    }
                };

                // Event listener saat upload selesai
                xhr.onload = function() {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        const data = JSON.parse(xhr.responseText);
                        statusDiv.innerHTML = `<i class="fas fa-check-circle text-green-500"></i> Berhasil diunggah`;
                        filenameInput.value = data.filename;
                    } else {
                        const error = JSON.parse(xhr.responseText);
                        const message = error.errors?.photo?.[0] || 'Upload gagal.';
                        statusDiv.innerHTML = `<i class="fas fa-times-circle text-red-500"></i> ${message}`;
                        e.target.value = '';
                        progressContainer.classList.add('hidden');
                    }
                };

                // Event listener untuk error jaringan
                xhr.onerror = function() {
                    statusDiv.innerHTML = `<i class="fas fa-times-circle text-red-500"></i> Terjadi error jaringan.`;
                    e.target.value = '';
                    progressContainer.classList.add('hidden');
                };

                xhr.send(formData);
            });
        });
    }
    
    function initializeCreateFormValidation(formElement) {
        const idpelInput = formElement.querySelector('#idpel_create');
        const statusIconDiv = formElement.querySelector('#idpel-status-icon');
        const statusMessageEl = formElement.querySelector('#idpel-status-message');
        const submitButton = formElement.querySelector('#create-mapping-submit-button');
        const ketSurveyTextarea = formElement.querySelector('#ket_survey_create');
        let debounceTimer;
        const checkUrlBase = 'master-pelanggan/check/'; // Sesuaikan jika route Anda berbeda

        if (!idpelInput || !statusIconDiv || !statusMessageEl || !submitButton || !ketSurveyTextarea) {
            console.error("Elemen form create tidak lengkap untuk validasi live IDPEL.");
            return;
        }

        const updateIdpelStatusUI = (isLoading, exists, isActive, message) => {
            statusMessageEl.textContent = message || '';
            statusIconDiv.classList.toggle('hidden', !isLoading && !exists && message === ''); // Sembunyi jika tidak ada status

            // Reset warna pesan
            statusMessageEl.classList.remove('text-green-600', 'text-red-600', 'text-yellow-600', 'text-gray-500');

            if (isLoading) {
                statusIconDiv.innerHTML = '<i class="fas fa-spinner fa-spin text-gray-400"></i>';
                statusIconDiv.classList.remove('hidden');
                statusMessageEl.classList.add('text-gray-500');
                submitButton.disabled = true;
                ketSurveyTextarea.readOnly = true; // Kunci textarea saat loading
                ketSurveyTextarea.value = '';      // Kosongkan textarea saat loading

            } else if (exists && isActive) { // Ditemukan & AKTIF
                statusIconDiv.innerHTML = '<i class="fas fa-check-circle text-green-500"></i>';
                statusIconDiv.classList.remove('hidden');
                statusMessageEl.classList.add('text-green-600');
                submitButton.disabled = false; // <<< ENABLE Simpan
                ketSurveyTextarea.readOnly = false; // <<< BUKA Kunci textarea
                ketSurveyTextarea.value = '';       // Kosongkan (biarkan user isi)
                ketSurveyTextarea.placeholder = ''; // Hapus placeholder

            } else if (exists && !isActive) { // Ditemukan & NON AKTIF
                statusIconDiv.innerHTML = '<i class="fas fa-exclamation-triangle text-yellow-500"></i>'; // Ikon warning
                statusIconDiv.classList.remove('hidden');
                statusMessageEl.classList.add('text-yellow-600'); // Warna warning
                statusMessageEl.textContent = message || 'Pelanggan ditemukan tapi status tidak aktif.'; // Pastikan pesan warning
                submitButton.disabled = false; // <<< TETAP ENABLE Simpan
                ketSurveyTextarea.readOnly = true;  // <<< KUNCI textarea
                ketSurveyTextarea.value = 'Pelanggan Non Aktif'; // Isi otomatis
                ketSurveyTextarea.placeholder = ''; // Hapus placeholder

            } else if (!exists && message) { // Tidak ditemukan (Error)
                statusIconDiv.innerHTML = '<i class="fas fa-times-circle text-red-500"></i>';
                statusIconDiv.classList.remove('hidden');
                statusMessageEl.classList.add('text-red-600');
                submitButton.disabled = true;
                ketSurveyTextarea.readOnly = true; // Kunci textarea
                ketSurveyTextarea.value = '';      // Kosongkan
                ketSurveyTextarea.placeholder = 'ID Pelanggan tidak valid';

            } else { // Status netral (input kosong atau belum 12 digit)
                statusIconDiv.classList.add('hidden');
                statusMessageEl.classList.add('text-gray-500');
                submitButton.disabled = true;
                ketSurveyTextarea.readOnly = true; // Kunci textarea
                ketSurveyTextarea.value = '';      // Kosongkan
                ketSurveyTextarea.placeholder = 'Masukkan ID Pelanggan untuk mengecek status';
            }

             // Atur style textarea saat dikunci
             if (ketSurveyTextarea.readOnly) {
                ketSurveyTextarea.classList.add('bg-gray-100', 'dark:bg-gray-800', 'cursor-not-allowed');
             } else {
                ketSurveyTextarea.classList.remove('bg-gray-100', 'dark:bg-gray-800', 'cursor-not-allowed');
             }
        };

        idpelInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            const idpelValue = idpelInput.value.trim();

            // Reset status jika input kosong atau belum 12 digit
            if (idpelValue.length === 0) {
                updateIdpelStatusUI(false, null, null, '');
                return;
            }
            if (idpelValue.length < 12) {
                updateIdpelStatusUI(false, null, null, 'ID Pelanggan harus 12 digit.');
                return;
            }
             if (idpelValue.length > 12) {
                updateIdpelStatusUI(false, false, null, 'ID Pelanggan tidak boleh lebih 12 digit.');
                return;
            }

            // Mulai debounce
            debounceTimer = setTimeout(() => {
                updateIdpelStatusUI(true, null, null, 'Mengecek...'); // Loading

                fetch(`${checkUrlBase}${idpelValue}`)
                    .then(response => {
                        // Selalu coba parse JSON, baik OK maupun error
                        return response.json().then(data => ({ ok: response.ok, status: response.status, data }));
                    })
                    .then(({ ok, status, data }) => {
                        if (!ok) {
                            // Jika server kirim error (4xx, 5xx), gunakan pesan dari JSON
                            throw new Error(data.message || `Error ${status}`);
                        }
                        // Jika OK (200), update UI berdasarkan data
                        updateIdpelStatusUI(false, data.exists, data.is_active, data.message);
                    })
                    .catch(error => {
                        console.error("Error cek IDPEL:", error);
                        updateIdpelStatusUI(false, false, null, `Gagal mengecek: ${error.message}`);
                    });
            }, 800);
        });

        // Set status awal saat modal dibuka
        updateIdpelStatusUI(false, null, null, '');
    }

    function closeModal() {
        if (!mainModal) return;
        mainModal.classList.add('hidden');
        modalContent.innerHTML = '';
    }

    function updateScrollButtons() {
        if (!tabsHeader || !scrollLeftBtn || !scrollRightBtn) return;
        const shouldShow = tabsHeader.scrollWidth > tabsHeader.clientWidth;
        scrollLeftBtn.classList.toggle('hidden', !shouldShow);
        scrollRightBtn.classList.toggle('hidden', !shouldShow);
    }
    
    scrollLeftBtn.addEventListener('click', () => tabsHeader.scrollBy({ left: -200, behavior: 'smooth' }));
    scrollRightBtn.addEventListener('click', () => tabsHeader.scrollBy({ left: 200, behavior: 'smooth' }));
    window.addEventListener('resize', updateScrollButtons);

    window.addEventListener('popstate', function(event) {
        const state = event.state;
        if (state && state.tab) {
            const tabName = state.tab;
            const url = window.location.href;
            const existingTab = tabsHeader.querySelector(`[data-tab-name="${tabName}"]`);
            if (existingTab) {
                activateTab(tabName, url, false);
            } else {
                createTab(tabName, url, true, false);
            }
        } else if (tabsHeader.children.length > 0) {
            const dashboardTab = tabsHeader.querySelector('[data-tab-name="Dashboard"]');
            if (dashboardTab) {
                activateTab('Dashboard', dashboardUrl, false);
            }
        } else {
            initializeDashboardTab();
        }
    });

    function initializeDashboardTab() {
        const currentPath = window.location.pathname;
        const dashboardPath = new URL(dashboardUrl).pathname;
        
        const dashboardExists = tabsHeader.querySelector('[data-tab-name="Dashboard"]');
        if (!dashboardExists) {
            createTab('Dashboard', dashboardUrl, false, false);
        }

        let activeTabName = 'Dashboard';
        let activeUrl = dashboardUrl;
        
        if (currentPath !== dashboardPath && currentPath !== '/') {
            const sidebarLink = document.querySelector(`a[href*="${currentPath}"]`);
            if (sidebarLink && sidebarLink.dataset.tabLink) {
                activeTabName = sidebarLink.dataset.tabLink;
                activeUrl = sidebarLink.href;
                
                const activeTabExists = tabsHeader.querySelector(`[data-tab-name="${activeTabName}"]`);
                if (!activeTabExists) {
                    createTab(activeTabName, activeUrl, true, false);
                }
            }
        }
        activateTab(activeTabName, activeUrl, false);
    }

    function initializeValidationMap(mapContainer) {
        if (validationMapInstance) {
            validationMapInstance.remove();
            validationMapInstance = null;
        }
        
        // Buat Peta
        validationMapInstance = L.map(mapContainer).setView([0.5071, 101.4478], 12); // Center Pekanbaru
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri'
        }).addTo(validationMapInstance);

        // Buat Marker (akan dipindah-pindah)
        validationMarker = L.marker([0, 0]).addTo(validationMapInstance);
    }

 // ===================================================================
 // ===== FUNGSI BARU UNTUK IMPLEMENTASI LOCK & REFRESH =====
 // ===================================================================

 async function lockAndLoadDetails(id, buttonElement) {

        // Coba cari container relatif terhadap tombol DULU
        let container = buttonElement.closest('#interactive-validation-container');

        // Jika tidak ketemu via closest, coba cari dari tab aktif
        if (!container) {
            console.warn("### Gagal via closest(). Mencoba via tab aktif...");
            const activeTabName = getActiveTabName();
            const activeTabContent = activeTabName ? document.getElementById(`${activeTabName}-content`) : null;
            
            if (activeTabContent) {
                container = activeTabContent.querySelector('#interactive-validation-container');
            } else {
                 console.error("### Tidak bisa menemukan elemen konten tab aktif!");
            }
        }

        // Cek final apakah container ditemukan
        if (!container) {
            console.error("### KRITIS: Container #interactive-validation-container tetap tidak ditemukan. Proses dibatalkan.");
            alert("Kesalahan internal: Tidak dapat menemukan kontainer validasi utama. Coba muat ulang tab.");
            return; // Hentikan fungsi di sini
        }
        
        // --- Lanjutkan dengan logika asli jika container ditemukan ---
        const loading = container.querySelector('#validation-loading');
        const content = container.querySelector('#validation-content');
        const placeholder = container.querySelector('#validation-placeholder');

        if(!loading || !content || !placeholder) {
            console.error("### Error: Elemen UI (loading/content/placeholder) tidak ditemukan di dalam container.");
            return;
        }

        placeholder.classList.add('hidden');
        content.classList.add('hidden');
        loading.classList.remove('hidden');

        const oldAlert = container.querySelector('#action-notification-alert');
        if (oldAlert) {
            oldAlert.remove();
        }

        container.querySelectorAll('.validation-queue-item.bg-indigo-100').forEach(btn => {
            btn.classList.remove('bg-indigo-100', 'dark:bg-indigo-900');
        });

        try {
            const fetchUrl = `/team/mapping-validation/item/${id}/lock`;
            const csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : null;
            if (!csrfToken) throw new Error('Token CSRF tidak ditemukan.');

            const fetchOptions = {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };

            const response = await fetch(fetchUrl, fetchOptions);

            let data;
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                data = await response.json();
            } else {
                 const responseText = await response.text();
                 console.error("### Respons server BUKAN JSON:", responseText.substring(0, 500) + '...');
                 throw new Error(`Server mengembalikan respons non-JSON (Status: ${response.status}).`);
            }

            if (!response.ok) {
                throw new Error(data.error || `Gagal mengunci item (Status: ${response.status})`);
            }

            // Lock berhasil
            currentValidationId = data.currentItemId;
            window.currentValidationDetails = data.details;
            buttonElement.classList.add('bg-indigo-100', 'dark:bg-indigo-900');
            
            updateValidationUI(container, data.details);

        } catch (error) {
            console.error('### Error dalam lockAndLoadDetails:', error);
            alert(`Terjadi kesalahan: ${error.message}. Coba refresh halaman atau login ulang.`);
            loading.classList.add('hidden');
            placeholder.classList.remove('hidden');
            content.classList.add('hidden');
            currentValidationId = null;
            window.currentValidationDetails = null;
        }
    }

        // === FUNGSI BARU: Refresh Queue ===
        async function refreshValidationQueue() {
            const queueListDiv = document.getElementById('validation-queue-list');
            try {
                // Panggil API untuk lock (Method POST)
                const response = await fetch(`/team/mapping-validation/item/${id}/lock`, {
                    method: 'POST', // Pastikan POST
                    headers: {
                        // PASTIKAN BARIS INI ADA DAN BENAR
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json', // Beri tahu server kita mau JSON
                        'X-Requested-With': 'XMLHttpRequest' // Penting agar Laravel tahu ini AJAX
                    }
                })
                // (Opsional: Update counter total/displayed)
            } catch (error) { /* ... handle error ... */ }
        }


        // === FUNGSI BARU: Update Validation UI ===
    /**
     * Memperbarui panel detail validasi dengan data yang diterima dari server.
     * Mengisi info, foto, mereset form evaluasi, dan merender peta.
     * @param {HTMLElement} container - Elemen div '#interactive-validation-container'.
     * @param {object|null} details - Objek data detail dari controller.
     */
    function updateValidationUI(container, details) {
        // Cari elemen utama di dalam container
        const loading = container.querySelector('#validation-loading');
        const content = container.querySelector('#validation-content');
        const placeholder = container.querySelector('#validation-placeholder'); 

        // Pastikan elemen dasar ada
        if (!loading || !content || !placeholder) {
            console.error("Elemen UI dasar (loading/content/placeholder) tidak ditemukan!");
            return;
        }

        // Handle jika tidak ada data detail (misal antrian habis)
        if (!details) {
            console.warn("updateValidationUI dipanggil tanpa data 'details'. Menampilkan placeholder.");
            loading.classList.add('hidden'); 
            placeholder.classList.remove('hidden'); 
            content.classList.add('hidden');
             // Hancurkan peta lama jika user kembali ke state tanpa data
             if (validationMapInstance) { validationMapInstance.remove(); validationMapInstance = null; validationMarker = null;}
             return;
        }

        // --- Mulai mengisi data ---

        // 1. Update Header (IDPEL & User)
        const idpelEl = content.querySelector('#detail-idpel');
        if (idpelEl) idpelEl.textContent = details.idpel || 'IDPEL Tidak Tersedia'; // Tampilkan fallback
        const userEl = content.querySelector('#detail-user');
        if (userEl) userEl.textContent = details.user_pendataan || 'User Tidak Diketahui'; // Tampilkan fallback

        // 2. Isi Keterangan
        const ketEl = content.querySelector('#detail-keterangan');
        if (ketEl) ketEl.textContent = details.keterangan || 'Tidak ada keterangan.';

        // 3. Isi Foto KWH
        const kwhLink = content.querySelector('#detail-foto-kwh-link');
        const kwhImg = content.querySelector('#detail-foto-kwh');
        const kwhNone = content.querySelector('#detail-foto-kwh-none');
        if (kwhLink && kwhImg && kwhNone) { // Cek semua elemen ada
            if (details.foto_kwh_url) {
                kwhImg.src = details.foto_kwh_url;
                kwhLink.classList.remove('hidden');
                kwhNone.classList.add('hidden');
            } else {
                kwhLink.classList.add('hidden');
                kwhNone.classList.remove('hidden');
            }
        } else { console.error("Elemen Foto KWH tidak lengkap."); }

        // 4. Isi Foto Bangunan
        const bangunanLink = content.querySelector('#detail-foto-bangunan-link');
        const bangunanImg = content.querySelector('#detail-foto-bangunan');
        const bangunanNone = content.querySelector('#detail-foto-bangunan-none');
         if (bangunanLink && bangunanImg && bangunanNone) { // Cek semua elemen ada
            if (details.foto_bangunan_url) {
                bangunanImg.src = details.foto_bangunan_url;
                bangunanLink.classList.remove('hidden');
                bangunanNone.classList.add('hidden');
            } else {
                bangunanLink.classList.add('hidden');
                bangunanNone.classList.remove('hidden');
            }
        } else { console.error("Elemen Foto Bangunan tidak lengkap."); }

        // 5. Update Action URL Tombol Form
        const rejectForm = content.querySelector('#detail-form-reject');
        const validateForm = content.querySelector('#detail-form-validate');
        // Pastikan currentValidationId sudah diupdate sebelum memanggil fungsi ini
        if(rejectForm) rejectForm.action = `/team/mapping-validation/${currentValidationId}/reject`; 
        if(validateForm) validateForm.action = `/team/mapping-validation/${currentValidationId}/approve`; 

        // 6. Reset Form Evaluasi (Panggil fungsi helper terpisah)
        resetEvaluationForm(content); 
        // Panggil check setelah reset untuk memastikan tombol disabled sesuai kondisi awal
        checkEvaluationForm(content, details); 

        const historyAlert = content.querySelector('#rejection-history-alert');
        const historyStatus = content.querySelector('#rejection-status');
        const historyList = content.querySelector('#rejection-list-items');

        if (historyAlert && historyStatus && historyList) {
            // Cek data 'rejection_history' yang kita buat di controller
            if (details.rejection_history && details.rejection_history.length > 0) {
                
                // 1. Isi status (cth: rejected_1_kali)
                historyStatus.textContent = details.status_validasi || 'Ditolak';
                
                // 2. Kosongkan daftar riwayat sebelumnya
                historyList.innerHTML = ''; 
                
                // 3. Isi daftar dengan data baru
                details.rejection_history.forEach(item => {
                    const li = document.createElement('li');
                    // Format: <strong>Label:</strong> Value
                    li.innerHTML = `<strong class="font-semibold">${item.label}:</strong> ${item.value}`;
                    historyList.appendChild(li);
                });

                // 4. Tampilkan kotak alert
                historyAlert.classList.remove('hidden');

            } else {
                // Jika tidak ada riwayat (item baru), SEMBUNYIKAN kotak alert
                historyAlert.classList.add('hidden');
                historyList.innerHTML = ''; // Kosongkan list untuk item berikutnya
            }
        } else {
            console.warn("Elemen UI Riwayat Penolakan (#rejection-history-alert) tidak ditemukan.");
        }

        // --- LOGIKA BARU UNTUK STREET VIEW VALIDASI ---
        // 1. Temukan elemen-elemen baru di panel validasi
        const latLonEl = content.querySelector('#validation-lat-lon');
        const streetViewLinkEl = content.querySelector('#validation-street-view-link');

        // 2. Temukan elemen modal RELATIF TERHADAP 'container' TAB VALIDASI
        const validationTabPanel = container.closest('.tab-content'); 

        const streetViewModal = validationTabPanel ? validationTabPanel.querySelector('#street-view-modal') : null;
        const streetViewIframe = validationTabPanel ? validationTabPanel.querySelector('#street-view-iframe') : null;
        const streetViewHeader = validationTabPanel ? validationTabPanel.querySelector('#street-view-header') : null;
        const streetViewCloseButton = validationTabPanel ? validationTabPanel.querySelector('#street-view-close-button') : null;

        // 3. Update Teks Koordinat dan Tampilkan/Sembunyikan Ikon
        if (latLonEl && streetViewLinkEl) {
            if (details.lat && details.lon && parseFloat(details.lat) !== 0) {
                latLonEl.textContent = `${details.lat.toFixed(6)}, ${details.lon.toFixed(6)}`;
                streetViewLinkEl.classList.remove('hidden');
            } else {
                latLonEl.textContent = 'Koordinat tidak valid';
                streetViewLinkEl.classList.add('hidden');
            }
        }
        
        // 4. Pasang Event Listener 'click' HANYA ke ikon baru
        if (streetViewLinkEl && streetViewModal && streetViewIframe && streetViewHeader && streetViewCloseButton) {
            
            // Definisikan handler 'click'
            const handleValidationStreetViewClick = (e) => {
                e.preventDefault();
                e.stopPropagation(); // Hentikan event lain

                // Ambil data 'details' yang relevan saat ini
                if (details.lat && details.lon && parseFloat(details.lat) !== 0) {
                    const lat = details.lat;
                    const lon = details.lon;
                    
                    // Pastikan YOUR_API_KEY sudah diganti di sini juga
                    const streetViewUrl = `https://www.google.com/maps/embed/v1/streetview?location=${lat},${lon}&key=${GOOGLE_API_KEY}`;
                    streetViewIframe.src = streetViewUrl; 
                    
                    // Tampilkan modal dan RESET posisinya ke default (pojok kanan atas)
                    streetViewModal.classList.remove('hidden'); 
                    streetViewModal.style.left = '';
                    streetViewModal.style.top = '';
                    streetViewModal.style.right = ''; 
                    
                } else {
                    alert('Koordinat tidak valid untuk Street View.');
                }
            };
            // B. Handler untuk TUTUP modal
            const closeValidationStreetView = () => {
                streetViewModal.classList.add('hidden');
                streetViewIframe.src = ""; 
                streetViewModal.style.left = '';
                streetViewModal.style.top = '';
                streetViewModal.style.right = '';
            }
        
        // C. Handler untuk DRAG (Logika disalin dari KDDK)
            let isDragging = false;
            let offsetX, offsetY;
            
            const onMouseDown = (e) => {
                if (e.target.id !== 'street-view-header' && !e.target.closest('#street-view-header')) return;
                isDragging = true;
                offsetX = e.clientX - streetViewModal.getBoundingClientRect().left;
                offsetY = e.clientY - streetViewModal.getBoundingClientRect().top;
                streetViewHeader.style.cursor = 'grabbing';
                document.body.style.userSelect = 'none';
                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            };

            const onMouseMove = (e) => {
                if (!isDragging) return;
                let newX = e.clientX - offsetX;
                let newY = e.clientY - offsetY;
                streetViewModal.style.left = `${newX}px`;
                streetViewModal.style.top = `${newY}px`;
                streetViewModal.style.right = 'auto'; 
            };
            
            const onMouseUp = () => {
                isDragging = false;
                streetViewHeader.style.cursor = 'move';
                document.body.style.userSelect = '';
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
            };

            // Hapus listener lama (PENTING saat item diganti)
            streetViewLinkEl.removeEventListener('click', streetViewLinkEl.__handler);
            streetViewCloseButton.removeEventListener('click', streetViewCloseButton.__handler);
            streetViewHeader.removeEventListener('mousedown', streetViewHeader.__handler);

            // Simpan referensi handler baru
            streetViewLinkEl.__handler = handleValidationStreetViewClick;
            streetViewCloseButton.__handler = closeValidationStreetView;
            streetViewHeader.__handler = onMouseDown;
            
            // Pasang listener baru
            streetViewLinkEl.addEventListener('click', handleValidationStreetViewClick);
            streetViewCloseButton.addEventListener('click', closeValidationStreetView);
            streetViewHeader.addEventListener('mousedown', onMouseDown);

        }

        // 7. Tampilkan Konten Utama, Sembunyikan Loading & Placeholder
        content.classList.remove('hidden');
        loading.classList.add('hidden');
        placeholder.classList.add('hidden');

        // 8. Update Peta (Gunakan setTimeout - versi stabil untuk render)
        setTimeout(() => {
            const mapContainer = content.querySelector('#validation-map');
            if (mapContainer) {
                // Selalu hancurkan peta lama untuk memastikan state bersih
                if (validationMapInstance) { 
                    try { validationMapInstance.remove(); } catch(e){ console.warn("Gagal remove map lama:", e); }
                    validationMapInstance = null; 
                    validationMarker = null; 
                }
                
                // Cek tinggi (debug) - pastikan style inline atau safelist bekerja
                if (mapContainer.offsetHeight === 0) {
                     console.warn('Tinggi #validation-map masih 0 saat setTimeout. Peta mungkin gagal render. Periksa CSS (style inline/safelist).');
                }

                // Buat peta baru
                try {
                    // Pastikan lat/lon valid sebelum membuat peta
                    if (typeof details.lat === 'number' && typeof details.lon === 'number') {
                        const newLatLng = [details.lat, details.lon];
                        validationMapInstance = L.map(mapContainer).setView(newLatLng, 18);
                         L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                             attribution: 'Tiles &copy; Esri'
                         }).addTo(validationMapInstance);
                         validationMarker = L.marker(newLatLng).addTo(validationMapInstance);
                         
                         // Panggil invalidateSize lagi setelah jeda singkat
                         setTimeout(() => {
                             if(validationMapInstance) validationMapInstance.invalidateSize();
                         }, 50); 
                    } else {
                         console.error("Data Latitude/Longitude tidak valid:", details.lat, details.lon);
                         // Tampilkan pesan error di area peta jika koordinat tidak valid
                         mapContainer.innerHTML = '<div class="flex items-center justify-center h-full text-red-500">Data Koordinat Tidak Valid</div>';
                    }
                } catch(e) { 
                    console.error("Gagal total membuat peta Leaflet:", e); 
                    mapContainer.innerHTML = '<div class="flex items-center justify-center h-full text-red-500">Gagal Memuat Peta</div>';
                }
            } else {
                 console.error("Div #validation-map tidak ditemukan di dalam content.");
            }
        }, 200); // Jeda 200ms untuk browser menggambar div content
    }
        
    // === FUNGSI BARU: Refresh Queue ===
    /**
     * Memuat ulang daftar item antrian validasi secara acak via AJAX.
     * Juga mereset panel detail ke placeholder.
     */
    async function refreshValidationQueue(resetPanel = false) {
        // Cari elemen daftar antrian dan kontainer utama
        const queueListDiv = document.getElementById('validation-queue-list');
        const container = queueListDiv?.closest('#interactive-validation-container');
        
        // Hentikan jika elemen tidak ditemukan
        if (!queueListDiv || !container) {
             console.error("Tidak dapat menemukan elemen #validation-queue-list atau #interactive-validation-container saat refresh.");
             return;
        }
        if (resetPanel) {
            // 1. Reset panel detail ke kondisi awal (placeholder)
            const placeholder = container.querySelector('#validation-placeholder');
            const content = container.querySelector('#validation-content');
            const loading = container.querySelector('#validation-loading');
            if(placeholder) placeholder.classList.remove('hidden');
            if(content) content.classList.add('hidden');
            if(loading) loading.classList.add('hidden');
            
            // 2. Reset state global
            currentValidationId = null;
            window.currentValidationDetails = null;
            
            // 3. Hancurkan peta lama jika ada
            if (validationMapInstance) { 
                try { validationMapInstance.remove(); } catch(e){}
                validationMapInstance = null; 
                validationMarker = null;
            }
        }
        // 4. Tampilkan loading di area antrian
        queueListDiv.innerHTML = '<div class="col-span-full text-center p-4"><i class="fas fa-spinner fa-spin text-gray-400"></i> Memuat ulang daftar...</div>'; 

        try {
            // 5. Panggil controller index dengan flag is_ajax_list=1
            const response = await fetch('/team/mapping-validation?is_ajax_list=1'); 
            if (!response.ok) {
                 // Coba baca pesan error jika ada
                 let errorMsg = 'Gagal memuat ulang daftar.';
                 try {
                     const errorData = await response.json();
                     errorMsg = errorData.error || errorMsg;
                 } catch(e){}
                 throw new Error(errorMsg + ` (Status: ${response.status})`);
            }
            
            // 6. Dapatkan HTML partial untuk daftar antrian
            const html = await response.text();

            // 7. Ganti konten daftar antrian dengan HTML baru
            queueListDiv.innerHTML = html; 

            // 8. (Opsional) Update counter total/displayed jika controller mengirimnya
            //    Anda perlu cara untuk mengirim data ini dari controller (misal via header)
            //    atau mengambilnya dari elemen di HTML baru jika ada.
            const totalAvailableEl = document.getElementById('total-available-count'); // Cari elemen counter
            const displayedEl = document.getElementById('displayed-count');
            // Contoh sederhana (perlu disesuaikan):
            // const totalFromServer = response.headers.get('X-Total-Available') || '?'; 
            // if(totalAvailableEl) totalAvailableEl.textContent = totalFromServer;
            if(displayedEl) displayedEl.textContent = queueListDiv.querySelectorAll('.validation-queue-item').length; // Hitung item yg tampil


            console.log(">>> Validation queue refreshed successfully."); // Log debug

        } catch (error) {
            console.error("Gagal refresh:", error);
            queueListDiv.innerHTML = `<div class="col-span-full text-center text-red-500 p-4">Gagal memuat ulang daftar: ${error.message}. Coba lagi nanti.</div>`;
        }
    }
    // === FUNGSI BARU: Reset Evaluation Form ===
        // (Tambahkan jika belum ada)
        function resetEvaluationForm(panel) {
            if (!panel) return;
            panel.querySelectorAll('.eval-radio').forEach(radio => { radio.checked = false; });
            const meterInput = panel.querySelector('#eval_meter_input');
            if (meterInput) meterInput.value = '';
            const meterStatus = panel.querySelector('#eval_meter_status');
            if (meterStatus) meterStatus.textContent = '';
            const petaReasonContainer = panel.querySelector('#eval_peta_reason_container');
            const petaReasonSelect = panel.querySelector('#eval_peta_reason');
            if (petaReasonContainer) petaReasonContainer.classList.add('hidden');
            if (petaReasonSelect) petaReasonSelect.value = ''; // Reset ke "-- Pilih Alasan --"
            const persilReasonContainer = panel.querySelector('#eval_persil_reason_container');
            const persilReasonSelect = panel.querySelector('#eval_persil_reason');
            if (persilReasonContainer) persilReasonContainer.classList.add('hidden');
            if (persilReasonSelect) persilReasonSelect.value = ''; // Reset ke "-- Pilih Alasan --"
            const rejectionContainer = panel.querySelector('#rejection_reason_container');
            if (rejectionContainer) rejectionContainer.classList.add('hidden');
            const rejectionReason = panel.querySelector('#eval_rejection_reason');
            if (rejectionReason) rejectionReason.value = '';
            const rejectionPlaceholder = panel.querySelector('#rejection_reason_placeholder');
            if (rejectionPlaceholder) rejectionPlaceholder.classList.remove('hidden');

            // Reset Tombol
            const validateButton = panel.querySelector('#detail-button-validate');
            const rejectButton = panel.querySelector('#detail-button-reject');
            if (validateButton) { validateButton.disabled = true; validateButton.classList.add('opacity-50', 'cursor-not-allowed'); }
            if (rejectButton) { rejectButton.disabled = true; rejectButton.classList.add('opacity-50', 'cursor-not-allowed'); }
        }    

        // ===================================================================
        // ===== FUNGSI BARU UNTUK INISIALISASI TAB VALIDASI (PERBAIKAN) =====
        // ===================================================================
        function initializeValidationTab(container) {
            if (!container) return;

            console.log("Initializing validation tab..."); // Tambahkan log untuk debug

            // Ambil data awal dari data-attributes PHP (jika ada)
            // Kita simpan ID saja, detail akan diambil via AJAX saat diklik
            currentValidationId = container.dataset.currentId || null; 
            
            // Kosongkan detail global saat tab baru dibuka
            window.currentValidationDetails = null; 

            // Tampilkan placeholder, sembunyikan loading & content
            const placeholder = container.querySelector('#validation-placeholder');
            const content = container.querySelector('#validation-content');
            const loading = container.querySelector('#validation-loading');
            if(placeholder) placeholder.classList.remove('hidden');
            if(content) content.classList.add('hidden');
            if(loading) loading.classList.add('hidden');

            // Hancurkan peta lama jika ada (penting saat tab dibuka ulang)
            if (validationMapInstance) { 
                validationMapInstance.remove(); 
                validationMapInstance = null; 
                validationMarker = null;
                console.log("Destroyed old validation map instance."); // Log debug
            }
            
            console.log("Validation tab initialized. Ready for item selection."); // Log debug
        }

    if (imageModal) {
        const closeImageModal = () => {
            // == LOGIKA BARU UNTUK MENYALIN INPUT ==
            // Cek apakah input meter di modal sedang terlihat
            if (modalMeterInputContainer && !modalMeterInputContainer.classList.contains('hidden')) {
                // 1. Ambil nilai dari input modal
                const modalValue = modalMeterInput.value;
                
                // 2. Cari input meter asli di tab aktif
                const activeTabContent = document.querySelector('.tab-content:not(.hidden)');
                const mainMeterInput = activeTabContent?.querySelector('#eval_meter_input');
                
                // 3. Jika ketemu, update nilainya
                if (mainMeterInput) {
                    mainMeterInput.value = modalValue;
                    // 4. PENTING: Trigger event 'input' agar checkEvaluationForm berjalan
                    mainMeterInput.dispatchEvent(new Event('input', { bubbles: true })); 
                }
                
                // 5. Sembunyikan lagi input di modal
                modalMeterInputContainer.classList.add('hidden');
                modalMeterInput.value = ''; // Kosongkan
            }
            // =====================================

            // Logika lama untuk menutup modal
            imageModal.classList.add('hidden');
            imageModalImg.src = ''; 
        };
        
        // Tutup jika tombol close (X) diklik
        imageModalClose.addEventListener('click', closeImageModal);
        
        // Tutup jika overlay (latar belakang) diklik
        imageModalOverlay.addEventListener('click', closeImageModal);
    }
    // --- Panggil Inisialisasi di Akhir ---
    initializeDashboardTab();
});

