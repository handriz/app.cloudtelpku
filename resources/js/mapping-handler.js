/**
 * MAPPING HANDLER (FINAL FIX - INPUT MANUAL BUTTON)
 * Perbaikan: Menghapus logika cloneNode yang menyebabkan tombol macet.
 * Menggunakan direct onclick assignment agar lebih stabil.
 */

document.addEventListener('DOMContentLoaded', function () {

    console.log("✅ Mapping Handler Loaded & DOM Ready");

    // ==========================================
    // 1. INSPECTOR TAB (KWH vs BANGUNAN)
    // ==========================================
    window.switchInspectorTab = function (tab) {
        const btnKwh = document.getElementById('tab-btn-kwh');
        const btnBang = document.getElementById('tab-btn-bangunan');
        const panelKwh = document.getElementById('inspector-kwh');
        const panelBang = document.getElementById('inspector-bangunan');

        if (!btnKwh || !panelKwh) return;

        if (tab === 'kwh') {
            setActiveBtn(btnKwh);
            setInactiveBtn(btnBang);
            panelKwh.classList.remove('hidden');
            panelBang.classList.add('hidden');
        } else {
            setActiveBtn(btnBang);
            setInactiveBtn(btnKwh);
            panelBang.classList.remove('hidden');
            panelKwh.classList.add('hidden');
        }
    };

    function setActiveBtn(btn) {
        btn.classList.add('bg-white', 'dark:bg-gray-600', 'text-indigo-600', 'dark:text-white', 'shadow-sm');
        btn.classList.remove('text-gray-500', 'hover:text-gray-700');
    }

    function setInactiveBtn(btn) {
        btn.classList.remove('bg-white', 'dark:bg-gray-600', 'text-indigo-600', 'dark:text-white', 'shadow-sm');
        btn.classList.add('text-gray-500', 'hover:text-gray-700');
    }

    // ==========================================
    // 2. LOGIKA PILIH BARIS (SELECT ROW) - DIPERBAIKI
    // ==========================================
    window.selectMappingRow = function (row, data) {
        const activePanel = row.closest('.tab-content') || document.body;

        // A. Highlight Baris
        activePanel.querySelectorAll('tbody tr').forEach(tr => {
            tr.classList.remove('bg-indigo-50', 'dark:bg-indigo-900/30');
        });
        if (row) row.classList.add('bg-indigo-50', 'dark:bg-indigo-900/30');

        // B. Update Info Text
        setText('detail-idpel', data.idpel);
        setText('detail-user', data.user_pendataan);
        updatePhotoInspector('kwh', data.foto_kwh);
        updatePhotoInspector('bangunan', data.foto_bangunan);

        // C. Update Peta & Tombol
        const lat = parseFloat(data.latitudey);
        const lng = parseFloat(data.longitudex);

        const mapOverlay = activePanel.querySelector('#map-error-overlay');
        const svBtn = activePanel.querySelector('#google-street-view-link');
        const coordText = activePanel.querySelector('#detail-lat-lon');

        // [FIX] Ambil tombol Input Manual dengan selektor yang pasti
        const btnInputManual = activePanel.querySelector('#btn-input-manual');

        // KONDISI 1: KOORDINAT VALID (Tampilkan Peta)
        if (lat && lng && !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
            if (coordText) coordText.textContent = `${lat}, ${lng}`;
            if (mapOverlay) mapOverlay.classList.add('hidden'); // Sembunyikan overlay

            if (svBtn) {
                svBtn.classList.remove('hidden', 'pointer-events-none', 'opacity-50');
                svBtn.onclick = function (e) {
                    e.preventDefault();
                    window.open(`https://www.google.com/maps?layer=c&cbll=${lat},${lng}`, '_blank');
                };
            }

            window.dispatchEvent(new CustomEvent('map:focus', {
                detail: { lat: lat, lng: lng, idpel: data.idpel }
            }));

        }
        // KONDISI 2: KOORDINAT TIDAK VALID (Tampilkan Overlay & Tombol)
        else {
            if (coordText) coordText.textContent = 'Tidak Ada Data';
            if (mapOverlay) mapOverlay.classList.remove('hidden'); // Tampilkan overlay
            if (svBtn) svBtn.classList.add('hidden', 'pointer-events-none', 'opacity-50');

            if (btnInputManual) {
                // Ambil URL edit dari atribut baris tabel
                const editUrl = row.getAttribute('data-edit-url');
                const isVerified = row.getAttribute('data-verified') === '1';

                // [FIX] Jangan pakai cloneNode! Langsung timpa onclick handler.
                // Ini memastikan elemen tetap sama di DOM dan tidak kehilangan referensi.
                btnInputManual.onclick = function (e) {
                    e.preventDefault();
                    e.stopPropagation(); // Agar tidak menembus ke map di bawahnya

                    console.log("🖱️ Tombol Input Manual Diklik. Verified:", isVerified);

                    if (isVerified) {
                        // Tampilkan Toast jika data terkunci
                        const toast = document.getElementById('simple-toast');
                        if (toast) {
                            toast.style.display = 'block';
                            setTimeout(() => { toast.style.display = 'none'; }, 4000);
                        } else {
                            alert("DATA TERKUNCI! Silakan Revisi dulu.");
                        }
                    }
                    else if (editUrl && editUrl !== 'null') {
                        // Buka Modal Edit
                        console.log("Membuka Modal Edit:", editUrl);
                        if (window.openEditModal) {
                            window.openEditModal(editUrl);
                        } else {
                            window.location.href = editUrl;
                        }
                    } else {
                        alert("URL Edit tidak ditemukan. Coba refresh halaman.");
                    }
                };
            }
        }
    };

    // ==========================================
    // 3. IMAGE VIEWER
    // ==========================================
    let imgState = { scale: 1, rotate: 0, pX: 0, pY: 0, isDragging: false, startX: 0, startY: 0 };
    const viewerModal = document.getElementById('image-viewer-modal');
    const viewerImg = document.getElementById('image-viewer-img');
    const viewerContainer = document.getElementById('image-container');

    window.viewImage = function (type) {
        const source = document.getElementById(`detail-foto-${type}`);
        if (source && source.src && !source.classList.contains('hidden') && viewerModal && viewerImg) {
            viewerImg.src = source.src;
            window.resetImageState();
            viewerModal.classList.remove('hidden', 'pointer-events-none');
            setTimeout(() => viewerModal.classList.remove('opacity-0'), 10);
        }
    };

    window.closeImageViewer = function () {
        if (viewerModal) {
            viewerModal.classList.add('opacity-0');
            setTimeout(() => {
                viewerModal.classList.add('hidden', 'pointer-events-none');
                if (viewerImg) viewerImg.src = '';
            }, 300);
        }
    };

    window.resetImageState = function () {
        if (!viewerImg) return;
        imgState = { scale: 1, rotate: 0, pX: 0, pY: 0, isDragging: false };
        updateTransform(true);
    };

    window.adjustImage = function (action, val) {
        if (!viewerImg) return;
        if (action === 'zoom') {
            const newScale = imgState.scale + val;
            if (newScale >= 0.5 && newScale <= 10) imgState.scale = newScale;
        } else if (action === 'rotate') {
            imgState.rotate += val;
        }
        updateTransform(true);
    };

    function updateTransform(useTransition = false) {
        if (!viewerImg) return;
        viewerImg.style.transition = useTransition ? 'transform 0.2s cubic-bezier(0.1, 0.7, 1.0, 0.1)' : 'none';
        viewerImg.style.transform = `translate(${imgState.pX}px, ${imgState.pY}px) scale(${imgState.scale}) rotate(${imgState.rotate}deg)`;
    }

    if (viewerContainer) {
        viewerContainer.addEventListener('mousedown', (e) => {
            if (e.button !== 0) return;
            e.preventDefault();
            imgState.isDragging = true;
            viewerImg.style.transition = 'none';
            imgState.startX = e.clientX - imgState.pX;
            imgState.startY = e.clientY - imgState.pY;
            viewerContainer.style.cursor = 'grabbing';
        });

        window.addEventListener('mousemove', (e) => {
            if (!imgState.isDragging) return;
            e.preventDefault();
            imgState.pX = e.clientX - imgState.startX;
            imgState.pY = e.clientY - imgState.startY;
            updateTransform(false);
        });

        window.addEventListener('mouseup', () => {
            if (imgState.isDragging) {
                imgState.isDragging = false;
                viewerContainer.style.cursor = 'grab';
            }
        });

        viewerContainer.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -0.1 : 0.1;
            const newScale = imgState.scale + delta;
            if (newScale >= 0.5 && newScale <= 10) {
                imgState.scale = newScale;
                updateTransform(false);
            }
        }, { passive: false });
    }

    document.addEventListener('keydown', (e) => {
        if (viewerModal && !viewerModal.classList.contains('hidden')) {
            if (e.key === 'Escape') closeImageViewer();
            if (e.key === '+' || e.key === '=') adjustImage('zoom', 0.2);
            if (e.key === '-') adjustImage('zoom', -0.2);
        }
    });

    // ==========================================
    // 4. DASHBOARD MAP HANDLER
    // ==========================================
    window.validationMap = null;     // Variabel Global Peta
    window.validationMarker = null;  // Variabel Global Marker

    window.addEventListener('map:focus', function(e) {
        const { lat, lng, idpel } = e.detail;
        
        const mapContainer = document.getElementById('rbm-map');
        if (!mapContainer) return;

        console.log(`📍 Map Focus: ${lat}, ${lng} (ID: ${idpel})`);

        // 1. Inisialisasi Peta jika belum ada
        if (!window.validationMap) {
            window.validationMap = L.map('rbm-map', { 
                zoomControl: false,
                attributionControl: false
            }).setView([lat, lng], 18);
            
            // Layer Satelit
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19,
                attribution: 'Tiles © Esri'
            }).addTo(window.validationMap);
            
            // Layer Label Jalan (Opsional, agar lebih jelas)
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19
            }).addTo(window.validationMap);

            L.control.zoom({ position: 'bottomright' }).addTo(window.validationMap);
        } else {
            // Jika peta sudah ada, langsung terbang ke lokasi
            window.validationMap.flyTo([lat, lng], 18, {
                animate: true,
                duration: 1.5
            });
            // Fix: Invalidate size agar peta tidak abu-abu jika container berubah ukuran
            setTimeout(() => { window.validationMap.invalidateSize(); }, 300);
        }

        // 2. Hapus Marker Lama (jika ada)
        if (window.validationMarker) {
            window.validationMap.removeLayer(window.validationMarker);
        }

        // 3. Buat Icon Marker Keren (CSS Murni)
        const customPin = L.divIcon({
            className: 'custom-map-pin',
            html: `
                <div style="position: relative; width: 14px; height: 14px;">
                    <div style="
                        position: absolute; inset: 0; 
                        background-color: #4f46e5; /* Warna Indigo Utama */
                        border: 2px solid white;   /* Border Putih Tegas */
                        border-radius: 50%; 
                        box-shadow: 0 1px 3px rgba(0,0,0,0.5); /* Bayangan kecil agar kontras */
                        z-index: 20;
                    "></div>
                    
                    <div style="
                        position: absolute; top: 50%; left: 50%; 
                        width: 14px; height: 14px; 
                        background: rgba(79, 70, 229, 0.6); 
                        border-radius: 50%; 
                        transform: translate(-50%, -50%);
                        animation: pingMicro 2s cubic-bezier(0, 0, 0.2, 1) infinite;
                        z-index: 10;
                    "></div>
                </div>
                <style>@keyframes pingMicro { 75%, 100% { transform: translate(-50%, -50%) scale(2.5); opacity: 0; } }</style>
            `,
            // UKURAN DIGANTI JADI KECIL
            iconSize: [14, 14],      // Ukuran kotak elemen (14px)
            iconAnchor: [7, 7],      // Titik jangkar WAJIB setengah dari ukuran (agar pas di tengah koordinat)
            popupAnchor: [0, -10]    // Posisi popup sedikit di atas titik
        })

        // 4. Pasang Marker Baru
        window.validationMarker = L.marker([lat, lng], { icon: customPin, zIndexOffset: 1000 })
            .addTo(window.validationMap)
            .bindPopup(`<div class="font-bold text-center p-1">Idpel: ${idpel}</div>`,{
                closeButton: false,
                autoClose: true,
                closeOnClick: true
            })
            .openPopup();
    });

    // Inisialisasi Peta Kosong saat Load (Opsional, agar tidak blank putih)
    setTimeout(() => {
        if(!window.validationMap && document.getElementById('rbm-map')) {
            window.validationMap = L.map('rbm-map', { zoomControl: false }).setView([0.5071, 101.4478], 10); // Default Pekanbaru
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}').addTo(window.validationMap);
        }
    }, 1000);

    // ==========================================
    // 5. DYNAMIC MODAL (AJAX FORM) - AUTO GENERATE
    // ==========================================
    window.openEditModal = function (url) {
        console.log("🚀 Membuka Modal Edit:", url);

        let modalContainer = document.getElementById('dynamic-form-modal');

        // Jika modal belum ada di DOM, kita BUAT SENDIRI (Bypass dependency #modal-container)
        if (!modalContainer) {
            console.log("⚠️ Container tidak ditemukan, membuat element modal baru di body...");

            modalContainer = document.createElement('div');
            modalContainer.id = 'dynamic-form-modal';
            // Style Inline untuk memastikan Z-Index menang
            modalContainer.style.cssText = 'position: fixed; inset: 0; z-index: 2147483647; display: none;';
            modalContainer.setAttribute('aria-labelledby', 'modal-title');
            modalContainer.setAttribute('role', 'dialog');
            modalContainer.setAttribute('aria-modal', 'true');

            // Struktur HTML Modal
            modalContainer.innerHTML = `
                <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity backdrop-blur-sm" onclick="window.closeDynamicModal()"></div>
                <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                        <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl" id="dynamic-modal-content" onclick="event.stopPropagation()">
                            </div>
                    </div>
                </div>
            `;

            // Tempel langsung ke Body (Aman dari masalah AJAX Partial)
            document.body.appendChild(modalContainer);
        }

        const contentBox = document.getElementById('dynamic-modal-content');
        if (!contentBox) return;

        // Tampilkan Modal (Unhide)
        modalContainer.style.display = 'block';
        modalContainer.classList.remove('hidden'); // Jaga-jaga jika ada class hidden

        // Tampilkan Loading Spinner
        contentBox.innerHTML = `
            <div class="p-12 text-center">
                <svg class="animate-spin h-10 w-10 text-indigo-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-gray-500 font-semibold">Memuat Form...</p>
            </div>
        `;

        // Fetch Data AJAX
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => {
                if (!res.ok) throw new Error("Gagal memuat form.");
                return res.text();
            })
            .then(html => {
                contentBox.innerHTML = html;

                // Re-init listener tombol close (karena elemen baru dibuat)
                const closeBtns = contentBox.querySelectorAll('[data-modal-close]');
                closeBtns.forEach(btn => {
                    btn.onclick = (e) => { e.preventDefault(); window.closeDynamicModal(); };
                });

                // Re-init Peta Preview di dalam modal (jika ada)
                if (window.initPreviewMap) {
                    setTimeout(window.initPreviewMap, 500);
                }
            })
            .catch(err => {
                console.error("Fetch Error:", err);
                contentBox.innerHTML = `
                <div class="p-8 text-center text-red-500">
                    <h3 class="font-bold text-lg mb-2">Error</h3>
                    <p>${err.message}</p>
                    <button onclick="window.closeDynamicModal()" class="mt-4 px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 font-bold">Tutup</button>
                </div>
            `;
            });
    };

    window.closeDynamicModal = function () {
        const modal = document.getElementById('dynamic-form-modal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.add('hidden');
        }
        const content = document.getElementById('dynamic-modal-content');
        if (content) content.innerHTML = '';
    };

    // ==========================================
    // 6. PREVIEW MAP & STREET VIEW
    // ==========================================
    window.previewMap = null;
    window.previewMarker = null;

    window.initPreviewMap = function () {
        const mapContainer = document.getElementById('preview-map');
        if (!mapContainer) return;

        if (window.previewMap) {
            window.previewMap.remove();
            window.previewMap = null;
        }

        const latInput = document.getElementById('latitudey_create') || document.getElementById('latitudey_edit');
        const lngInput = document.getElementById('longitudex_create') || document.getElementById('longitudex_edit');

        let initLat = 0.5071;
        let initLng = 101.4478;
        let initZoom = 13;

        if (latInput && lngInput && latInput.value && lngInput.value) {
            const l = parseFloat(latInput.value);
            const g = parseFloat(lngInput.value);
            if (l && g) { initLat = l; initLng = g; initZoom = 18; }
        }

        window.previewMap = L.map('preview-map').setView([initLat, initLng], initZoom);

        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles © Esri',
            maxZoom: 19
        }).addTo(window.previewMap);
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19
        }).addTo(window.previewMap);

        if (latInput && lngInput && latInput.value) {
            window.updatePreviewMarker(initLat, initLng);
        }

        window.previewMap.on('click', function (e) {
            const lat = e.latlng.lat.toFixed(6);
            const lng = e.latlng.lng.toFixed(6);
            if (latInput) latInput.value = lat;
            if (lngInput) lngInput.value = lng;
            window.updatePreviewMarker(lat, lng);
            if (latInput) latInput.dispatchEvent(new Event('input'));
        });

        setTimeout(() => {
            if (window.previewMap) window.previewMap.invalidateSize();
        }, 500);
    };

    window.updatePreviewMarker = function (lat, lng) {
        if (!window.previewMap) return;
        if (window.previewMarker) window.previewMap.removeLayer(window.previewMarker);

        window.previewMarker = L.marker([lat, lng], { draggable: true }).addTo(window.previewMap);
        window.previewMap.flyTo([lat, lng], 18);

        window.previewMarker.on('dragend', function (e) {
            const pos = e.target.getLatLng();
            const latInput = document.getElementById('latitudey_create') || document.getElementById('latitudey_edit');
            const lngInput = document.getElementById('longitudex_create') || document.getElementById('longitudex_edit');
            if (latInput) latInput.value = pos.lat.toFixed(6);
            if (lngInput) lngInput.value = pos.lng.toFixed(6);
            if (latInput) latInput.dispatchEvent(new Event('input')); // Trigger update link street view
        });
    };

    // ==========================================
    // 7. UPDATE EXTERNAL STREET VIEW
    // ==========================================
    window.updateExternalStreetView = function () {
        const latInput = document.getElementById('latitudey_create') || document.getElementById('latitudey_edit');
        const lngInput = document.getElementById('longitudex_create') || document.getElementById('longitudex_edit');
        const btn = document.getElementById('btn-open-streetview-external');
        const warning = document.getElementById('streetview-warning');

        if (!latInput || !lngInput || !btn) return;

        const lat = parseFloat(latInput.value);
        const lng = parseFloat(lngInput.value);

        if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
            const url = `https://www.google.com/maps?layer=c&cbll=${lat},${lng}`;
            btn.href = url;
            btn.classList.remove('pointer-events-none', 'opacity-50', 'bg-gray-300');
            btn.classList.add('bg-orange-600', 'hover:bg-orange-700', 'text-white');
            if (warning) warning.classList.add('hidden');
        } else {
            btn.href = '#';
            btn.classList.add('pointer-events-none', 'opacity-50', 'bg-gray-300');
            btn.classList.remove('bg-orange-600', 'hover:bg-orange-700', 'text-white');
            if (warning) warning.classList.remove('hidden');
        }
    };

    // ==========================================
    // 8. UTILS & HELPERS
    // ==========================================
    function setText(id, text) { const el = document.getElementById(id); if (el) el.textContent = text || '-'; }

    function updatePhotoInspector(type, path) {
        const imgEl = document.getElementById(`detail-foto-${type}`);
        const phEl = document.getElementById(`placeholder-foto-${type}`);
        const zoomEl = document.getElementById(`zoom-${type}`);
        if (!imgEl || !phEl) return;
        if (path) {
            imgEl.src = `/storage/${path}?t=${new Date().getTime()}`;
            imgEl.classList.remove('hidden'); phEl.classList.add('hidden');
            if (zoomEl) zoomEl.classList.remove('hidden');
        } else {
            imgEl.classList.add('hidden'); phEl.classList.remove('hidden');
            if (zoomEl) zoomEl.classList.add('hidden');
        }
    }

    // ==========================================
    // 9. REQUEST KOORDINAT HANDLER
    // ==========================================

    // Buka Modal
    window.openRequestModal = function () {
        const modal = document.getElementById('request-coord-modal');
        if (modal) {
            modal.style.display = 'flex';
            window.resetRequestModal(); // Reset form setiap kali dibuka
        }
    };

    

    // Reset Modal ke Step 1
    window.resetRequestModal = function () {
        document.getElementById('req-step-1').style.display = 'block';
        document.getElementById('req-step-loading').style.display = 'none';
        document.getElementById('req-step-result').style.display = 'none';
        document.getElementById('form-request-coord').reset();
    };

    // Handle Submit Form AJAX
    window.handleRequestSubmit = function (e) {
        e.preventDefault();

        const form = e.target;
        const formData = new FormData(form);

        // UI Change: Loading
        document.getElementById('req-step-1').style.display = 'none';
        document.getElementById('req-step-loading').style.display = 'block';

        // AJAX Request
        fetch('/team/mapping/request-coordinates', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // UI Change: Show Result
                    document.getElementById('req-step-loading').style.display = 'none';
                    document.getElementById('req-step-result').style.display = 'block';

                    // Isi Data Statistik
                    document.getElementById('res-total').textContent = data.stats.total;
                    document.getElementById('res-found').textContent = data.stats.found;
                    document.getElementById('res-notfound').textContent = data.stats.not_found;

                    // Set Link Download
                    const btnDown = document.getElementById('btn-download-result');
                    btnDown.href = data.download_url;
                    btnDown.setAttribute('target', '_blank');
                } else {
                    throw new Error(data.error || 'Terjadi kesalahan tidak diketahui.');
                }
            })
            .catch(err => {
                // Error Handling
                alert("Gagal memproses: " + (err.message || err));
                window.resetRequestModal();
            });
    };

    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.addedNodes.length) {
                const previewMapEl = document.getElementById('preview-map');
                if (previewMapEl && !previewMapEl.classList.contains('leaflet-container')) {
                    if (window.initPreviewMap) window.initPreviewMap();
                }
            }
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });

    document.addEventListener('input', function (e) {
        if (['latitudey_create', 'longitudex_create', 'latitudey_edit', 'longitudex_edit'].includes(e.target.id)) {
            const suffix = e.target.id.includes('edit') ? '_edit' : '_create';
            const lat = parseFloat(document.getElementById('latitudey' + suffix)?.value);
            const lng = parseFloat(document.getElementById('longitudex' + suffix)?.value);
            if (window.previewMap && window.updatePreviewMarker) window.updatePreviewMarker(lat, lng);
            if (window.updateExternalStreetView) window.updateExternalStreetView();
        }
    });

});