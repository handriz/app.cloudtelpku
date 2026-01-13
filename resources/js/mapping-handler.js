/**
 * MAPPING HANDLER
 * Mengurus interaksi UI pada halaman Mapping/Validasi
 */

(function () {
    // 1. Fungsi Ganti Tab (KWH vs Bangunan)
    window.switchInspectorTab = function (tab) {
        const btnKwh = document.getElementById('tab-btn-kwh');
        const btnBang = document.getElementById('tab-btn-bangunan');
        const panelKwh = document.getElementById('inspector-kwh');
        const panelBang = document.getElementById('inspector-bangunan');

        // Safety check jika elemen belum ada (misal tab belum dimuat)
        if (!btnKwh || !panelKwh) return;

        if (tab === 'kwh') {
            // Aktifkan KWH
            setActiveBtn(btnKwh);
            setInactiveBtn(btnBang);
            panelKwh.classList.remove('hidden');
            panelBang.classList.add('hidden');
        } else {
            // Aktifkan Bangunan
            setActiveBtn(btnBang);
            setInactiveBtn(btnKwh);
            panelBang.classList.remove('hidden');
            panelKwh.classList.add('hidden');
        }
    };

    // Helper Styles
    function setActiveBtn(btn) {
        btn.classList.add('bg-white', 'dark:bg-gray-600', 'text-indigo-600', 'dark:text-white', 'shadow-sm');
        btn.classList.remove('text-gray-500', 'hover:text-gray-700');
    }

    function setInactiveBtn(btn) {
        btn.classList.remove('bg-white', 'dark:bg-gray-600', 'text-indigo-600', 'dark:text-white', 'shadow-sm');
        btn.classList.add('text-gray-500', 'hover:text-gray-700');
    }

    // 2. Fungsi Klik Baris Tabel (Master-Detail)
    window.selectMappingRow = function (row, data) {
        // A. Highlight Baris
        document.querySelectorAll('tbody tr').forEach(tr => {
            tr.classList.remove('bg-indigo-50', 'dark:bg-indigo-900/30');
        });
        if (row) row.classList.add('bg-indigo-50', 'dark:bg-indigo-900/30');

        // B. Update Teks Info
        setText('detail-idpel', data.idpel);
        setText('detail-user', data.user_pendataan);

        // C. Update Foto
        updatePhotoInspector('kwh', data.foto_kwh);
        updatePhotoInspector('bangunan', data.foto_bangunan);

        // D. [LOGIKA BARU] Cek Koordinat & Update Peta
        const lat = parseFloat(data.latitudey);
        const lng = parseFloat(data.longitudex);

        const mapOverlay = document.getElementById('map-error-overlay');
        const svBtn = document.getElementById('google-street-view-link');
        const coordText = document.getElementById('detail-lat-lon');
        const btnInputManual = document.getElementById('btn-input-manual');

        // Validasi: Harus angka, tidak NaN, dan tidak 0
        if (lat && lng && !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {

            // 1. Koordinat VALID
            if (coordText) coordText.textContent = `${lat}, ${lng}`;
            if (mapOverlay) mapOverlay.classList.add('hidden'); // Sembunyikan Error

            if (svBtn) {
                svBtn.classList.remove('hidden', 'pointer-events-none', 'opacity-50');
                svBtn.onclick = function () {
                    window.open(`https://www.google.com/maps?q&layer=c&cbll=$${lat},${lng}`, '_blank');
                };
            }

            // KIRIM SINYAL KE PETA
            window.dispatchEvent(new CustomEvent('map:focus', {
                detail: { lat: lat, lng: lng, idpel: data.idpel }
            }));

        } else {
            // 2. Koordinat INVALID / NULL
            if (coordText) coordText.textContent = 'Tidak Ada Data';
            if (mapOverlay) mapOverlay.classList.remove('hidden'); // Tampilkan Error

            if (svBtn) svBtn.classList.add('hidden', 'pointer-events-none', 'opacity-50');

            if (btnInputManual) {
                // Hapus event listener lama (penting agar tidak menumpuk)
                btnInputManual.onclick = null;

                btnInputManual.onclick = function (e) {
                    e.stopPropagation(); // Agar tidak men-trigger klik baris lagi

                    // Ambil URL langsung dari atribut data-edit-url di baris tabel (Tahap 1)
                    const isVerified = row.getAttribute('data-verified') === '1';
                    const editUrl = row.dataset.editUrl;

                    if (isVerified) {
                        window.showToast("Data ini Terkunci (Verified). Silakan 'Tarik Kembali' dulu dari tabel.", "error");
                    } else {
                        const editUrl = row.dataset.editUrl;
                        if (editUrl) {
                            window.openEditModal(editUrl);
                        }
                    }
                };
            }
        }
    };

    // Helper Text
    function setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text || '-';
    }

    // 3. Helper Update Foto Inspector
    function updatePhotoInspector(type, path) {
        const imgEl = document.getElementById(`detail-foto-${type}`);
        const phEl = document.getElementById(`placeholder-foto-${type}`);
        const zoomEl = document.getElementById(`zoom-${type}`);

        if (!imgEl || !phEl) return;

        if (path) {
            // Asumsi storage symlink sudah benar
            imgEl.src = `/storage/${path}?t=${new Date().getTime()}`;
            imgEl.classList.remove('hidden');
            phEl.classList.add('hidden');
            if (zoomEl) zoomEl.classList.remove('hidden');
        } else {
            imgEl.classList.add('hidden');
            phEl.classList.remove('hidden');
            if (zoomEl) zoomEl.classList.add('hidden');
        }
    }

    // 4. IMAGE VIEWER LOGIC (ZOOM, ROTATE, PAN)
    // State Penyimpanan Posisi
    let imgState = {
        scale: 1,
        rotate: 0,
        pX: 0, // Posisi X (Pan)
        pY: 0, // Posisi Y (Pan)
        isDragging: false,
        startX: 0,
        startY: 0
    };

    const modal = document.getElementById('image-viewer-modal');
    const imgEl = document.getElementById('image-viewer-img');
    const container = document.getElementById('image-container');

    // --- FUNGSI UTAMA ---

    window.viewImage = function (type) {
        const source = document.getElementById(`detail-foto-${type}`);
        if (source && source.src && modal && imgEl) {
            imgEl.src = source.src;
            resetImageState(); // Reset posisi tiap buka baru

            modal.classList.remove('hidden', 'pointer-events-none');
            setTimeout(() => modal.classList.remove('opacity-0'), 10);
        }
    };

    window.closeImageViewer = function () {
        if (modal) {
            modal.classList.add('opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden', 'pointer-events-none');
                imgEl.src = '';
            }, 300);
        }
    };

    window.resetImageState = function () {
        imgState = { scale: 1, rotate: 0, pX: 0, pY: 0, isDragging: false };
        updateTransform(true); // true = gunakan animasi halus
    };

    window.adjustImage = function (action, val) {
        if (action === 'zoom') {
            const newScale = imgState.scale + val;
            if (newScale >= 0.5 && newScale <= 10) imgState.scale = newScale;
        } else if (action === 'rotate') {
            imgState.rotate += val;
        }
        updateTransform(true); // Gunakan animasi halus saat klik tombol
    };

    // Fungsi Update CSS
    function updateTransform(useTransition = false) {
        if (!imgEl) return;

        // KUNCI: Hanya gunakan animasi (transition) saat Zoom/Rotate via tombol.
        // Saat Drag, transition harus 'none'.
        imgEl.style.transition = useTransition ? 'transform 0.2s cubic-bezier(0.1, 0.7, 1.0, 0.1)' : 'none';

        imgEl.style.transform = `translate(${imgState.pX}px, ${imgState.pY}px) scale(${imgState.scale}) rotate(${imgState.rotate}deg)`;
    }


    // --- EVENT LISTENERS (MOUSE WHEEL & DRAG) ---

    if (container) {

        // 1. MOUSE WHEEL ZOOM (Tetap sama)
        container.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -0.2 : 0.2;
            const newScale = imgState.scale + delta;
            if (newScale >= 0.5 && newScale <= 10) {
                imgState.scale = newScale;
                // Gunakan true (animasi) untuk zoom agar halus
                updateTransform(true);
            }
        }, { passive: false });


        // 2. DRAG START (Klik Kiri Tahan)
        container.addEventListener('mousedown', (e) => {
            // Pastikan hanya klik kiri dan targetnya gambar/container
            if (e.button !== 0) return;
            e.preventDefault(); // Matikan seleksi teks bawaan
            e.stopPropagation();

            imgState.isDragging = true;

            // TITIK KRUSIAL: Matikan animasi CSS seketika saat mulai geser!
            // Jika tidak dimatikan, ini yang bikin BERGETAR.
            imgEl.style.transition = 'none';

            imgState.startX = e.clientX - imgState.pX;
            imgState.startY = e.clientY - imgState.pY;

            container.style.cursor = 'grabbing';
        });

        // 3. DRAGGING (Gerakkan Mouse)
        window.addEventListener('mousemove', (e) => {
            if (!imgState.isDragging) return;
            e.preventDefault(); // PENTING: Mencegah seleksi browser

            // Hitung posisi baru (Raw calculation)
            // Kita tidak pakai requestAnimationFrame agar instan mengikuti mouse
            imgState.pX = e.clientX - imgState.startX;
            imgState.pY = e.clientY - imgState.startY;

            // Update posisi langsung ke Style Element
            // Kita bypass fungsi updateTransform() agar lebih cepat & tanpa logika lain
            imgEl.style.transform = `translate(${imgState.pX}px, ${imgState.pY}px) scale(${imgState.scale}) rotate(${imgState.rotate}deg)`;
        });

        // 4. DRAG END (Lepas Klik)
        window.addEventListener('mouseup', () => {
            if (imgState.isDragging) {
                imgState.isDragging = false;
                container.style.cursor = 'grab';

                // Opsional: Nyalakan lagi transisi supaya kalau di-zoom/rotate setelah ini jadi halus lagi
                // imgEl.style.transition = 'transform 0.2s ease-out'; 
            }
        });

        // Mencegah "Ghost Image" saat drag
        container.addEventListener('dragstart', (e) => e.preventDefault());
    }

    // Keyboard Shortcuts (Tambahan UX)
    document.addEventListener('keydown', (e) => {
        if (modal && !modal.classList.contains('hidden')) {
            if (e.key === 'Escape') closeImageViewer();
            if (e.key === '+' || e.key === '=') adjustImage('zoom', 0.2);
            if (e.key === '-') adjustImage('zoom', -0.2);
            if (e.key === '0') resetImageState();
        }
    });

    console.log("Mapping Handler Loaded");
    // Init Drag Events saat DOM siap
    setTimeout(() => {
        const cont = document.getElementById('image-container');
        if (cont) {
            cont.onmousedown = (e) => {
                if (e.target.tagName !== 'IMG') return;
                e.preventDefault();
                imgState.panning = true;
                imgState.startX = e.clientX - imgState.pointX;
                imgState.startY = e.clientY - imgState.pointY;
                cont.style.cursor = 'grabbing';
            };

            // ... (Event listener lain sudah di handle global window di atas)
        }
    }, 1000);

    // ==========================================
    // 5. ROBUST MAP HANDLER (FIX KLIK BERULANG)
    // ==========================================

    // Gunakan Window variable agar persisten
    window.validationMap = null;
    window.validationMarker = null; // Marker Utama (Target)
    window.neighborLayer = null;    // Group Marker Tetangga

    // Fungsi Utama: Update Posisi Peta & Fetch Tetangga
    function updateMapPosition(lat, lng, idpel) {
        const mapContainer = document.getElementById('rbm-map');
        if (!mapContainer) return;

        // 1. INIT PETA (Jika Belum Ada)
        const isMapMissing = !window.validationMap || !mapContainer._leaflet_id;

        if (isMapMissing) {
            if (window.validationMap) {
                try { window.validationMap.remove(); } catch (e) { }
                window.validationMap = null;
            }

            window.validationMap = L.map('rbm-map').setView([lat, lng], 18);

            // Layer Satelit
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles © Esri',
                maxZoom: 19
            }).addTo(window.validationMap);

            // Layer Jalan
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19
            }).addTo(window.validationMap);

            // Init Layer Group untuk Tetangga
            window.neighborLayer = L.layerGroup().addTo(window.validationMap);

        } else {
            // Jika peta sudah ada, terbang ke lokasi baru
            window.validationMap.flyTo([lat, lng], 18, { animate: true, duration: 1.5 });
            window.validationMap.invalidateSize();
        }

        // 2. GAMBAR MARKER UTAMA (Target)
        if (window.validationMarker) window.validationMap.removeLayer(window.validationMarker);

        const mainIcon = L.divIcon({
            className: 'custom-pin',
            html: `<div class="w-5 h-5 bg-indigo-600 rounded-full border-2 border-white shadow-[0_0_15px_rgba(79,70,229,0.8)] animate-bounce z-50"></div>`,
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        });

        const popupContent = `
            <div class="pl-3 pr-6 py-2 font-sans relative">
                <div class="flex items-center justify-between mb-1">
                    <span class="mr-2 bg-indigo-50 text-indigo-600 text-[9px] font-bold px-1.5 py-0.5 rounded border border-indigo-100">TARGET</span>
                </div>
                
                <div class="text-sm font-black text-gray-800 mb-2 leading-tight tracking-tight">
                    ${idpel}
                </div>
            </div>
        `;

        window.validationMarker = L.marker([lat, lng], { icon: mainIcon, zIndexOffset: 1000 })
            .addTo(window.validationMap)
            .bindPopup(popupContent, {
                minWidth: 220,  // Lebar minimum (agar tidak sempit)
                maxWidth: 240,  // Lebar maksimum (agar tidak terlalu lebar)
                className: 'pretty-popup' // Class khusus untuk CSS tambahan
            })
            .openPopup();

        // 3. FETCH TETANGGA (AJAX)
        fetchNeighbors(idpel);
    }

    // Fungsi Fetch Data Tetangga dari Controller
    function fetchNeighbors(searchIdpel) {
        const urlInput = document.getElementById('api-map-coordinates');
        if (!urlInput) return;

        // Bersihkan marker tetangga lama
        if (window.neighborLayer) window.neighborLayer.clearLayers();

        // Panggil API (Controller getMapCoordinates)
        // Kita kirim 'search' parameter agar Controller menjalankan logika Bounding Box
        fetch(`${urlInput.value}?search=${searchIdpel}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(res => res.json())
            .then(data => {
                // Data return: { searched: [...], nearby: [...] }

                if (data.nearby && data.nearby.length > 0) {
                    data.nearby.forEach(p => {
                        // Jangan gambar ulang target utama (double marker)
                        if (p.idpel == searchIdpel) return;

                        const lat = parseFloat(p.latitudey);
                        const lng = parseFloat(p.longitudex);

                        if (lat && lng) {
                            // Marker Tetangga (Lebih Kecil & Warna Beda)
                            const neighborIcon = L.divIcon({
                                className: 'neighbor-pin',
                                html: `<div class="w-3 h-3 bg-yellow-400 rounded-full border border-white shadow-sm opacity-90 hover:scale-125 transition-transform"></div>`,
                                iconSize: [12, 12],
                                iconAnchor: [6, 6]
                            });

                            const marker = L.marker([lat, lng], { icon: neighborIcon })
                                .bindPopup(`
                                <div class="text-xs">
                                    <strong class="text-gray-600">Tetangga</strong><br>
                                    ${p.idpel}<br>
                                    <span class="text-[10px] text-gray-400">${Math.round(p.distance * 1000) || '?'} m</span>
                                </div>
                            `);

                            // Masukkan ke Layer Tetangga
                            if (window.neighborLayer) window.neighborLayer.addLayer(marker);
                        }
                    });
                }
            })
            .catch(err => console.error("Gagal load tetangga:", err));
    }

    // LISTENER
    window.addEventListener('map:focus', function (e) {
        const { lat, lng, idpel } = e.detail;
        if (lat && lng) {
            updateMapPosition(lat, lng, idpel);
        }
    });

    // Init Awal
    // Init Awal
    setTimeout(() => {
        const mapContainer = document.getElementById('rbm-map');
        if (mapContainer && !window.validationMap) {
            // 1. Ambil Koordinat Default dari Input Hidden (Kita akan buat input ini di langkah 3)
            const defLat = document.getElementById('setting-default-lat')?.value || 0.5071;
            const defLng = document.getElementById('setting-default-lng')?.value || 101.4478;

            // 2. Gunakan koordinat tersebut
            window.validationMap = L.map('rbm-map').setView([defLat, defLng], 10);

            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles © Esri',
                maxZoom: 19
            }).addTo(window.validationMap);

            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19
            }).addTo(window.validationMap);
        }
    }, 1000);

    // ==========================================
    // 6. DYNAMIC MODAL HANDLER (FIX EDIT LINK)
    // ==========================================

    // Kita pasang listener di body untuk menangkap klik dari elemen yang baru muncul (AJAX)
    window.openEditModal = function (url) {
        console.log("Membuka Modal Edit: " + url);

        // A. Siapkan Wadah Modal
        let modalContainer = document.getElementById('dynamic-form-modal');
        if (!modalContainer) {
            modalContainer = document.createElement('div');
            modalContainer.id = 'dynamic-form-modal';
            modalContainer.className = 'fixed inset-0 z-[9999] hidden bg-gray-900/75 backdrop-blur-sm flex items-center justify-center p-4 transition-opacity duration-300 opacity-0';
            modalContainer.innerHTML = `
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto transform scale-95 transition-transform duration-300 relative" id="dynamic-modal-content"></div>
            `;
            document.body.appendChild(modalContainer);
        }

        const contentBox = document.getElementById('dynamic-modal-content');

        // B. Tampilkan Loading
        modalContainer.classList.remove('hidden');
        setTimeout(() => {
            modalContainer.classList.remove('opacity-0');
            contentBox.classList.remove('scale-95');
            contentBox.classList.add('scale-100');
        }, 10);

        contentBox.innerHTML = `
            <div class="p-12 text-center flex flex-col items-center justify-center h-64">
                <i class="fas fa-spinner fa-spin text-5xl text-indigo-500 mb-4"></i>
                <p class="text-gray-500 font-medium">Sedang memuat form...</p>
            </div>
        `;

        // C. Fetch AJAX
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
            .then(res => {
                if (!res.ok) throw new Error("Gagal memuat data.");
                return res.text();
            })
            .then(html => {
                // D. Masukkan HTML ke Modal
                contentBox.innerHTML = html;

                // Pasang event listener untuk tombol Close yang baru dimuat
                const closeBtns = contentBox.querySelectorAll('[data-modal-close]');
                closeBtns.forEach(btn => btn.onclick = window.closeDynamicModal);

                // Re-init form scripts jika perlu (misal: preview foto)
                // (Opsional)
            })
            .catch(err => {
                contentBox.innerHTML = `
                <div class="p-8 text-center">
                    <div class="w-16 h-16 bg-red-100 text-red-500 rounded-full mx-auto mb-4 flex items-center justify-center"><i class="fas fa-exclamation-triangle text-2xl"></i></div>
                    <p class="text-gray-800 font-bold">Error</p>
                    <p class="text-gray-500 text-sm">${err.message}</p>
                    <button onclick="window.closeDynamicModal()" class="mt-4 px-4 py-2 bg-gray-200 rounded font-bold text-sm">Tutup</button>
                </div>
            `;
            });
    };

    window.closeDynamicModal = function () {
        const modalContainer = document.getElementById('dynamic-form-modal');
        if (modalContainer) {
            modalContainer.classList.add('opacity-0');
            setTimeout(() => {
                modalContainer.classList.add('hidden');
                document.getElementById('dynamic-modal-content').innerHTML = '';
            }, 300);
        }
    };

    // Fungsi Tutup Modal Global
    window.closeDynamicModal = function () {
        const modalContainer = document.getElementById('dynamic-form-modal');
        if (modalContainer) {
            modalContainer.classList.add('opacity-0');
            const contentBox = document.getElementById('dynamic-modal-content');
            if (contentBox) {
                contentBox.classList.remove('scale-100');
                contentBox.classList.add('scale-95');
            }

            setTimeout(() => {
                modalContainer.classList.add('hidden');
                document.getElementById('dynamic-modal-content').innerHTML = ''; // Bersihkan memori
            }, 300);
        }
    };

    // ==========================================
    // 7. TOAST NOTIFICATION SYSTEM (PENGGANTI ALERT)
    // ==========================================
    window.showToast = function (message, type = 'error') {
        // Hapus toast lama jika ada
        const oldToast = document.getElementById('custom-toast');
        if (oldToast) oldToast.remove();

        // Tentukan Warna & Ikon
        let bgColor = type === 'success' ? 'bg-green-600' : 'bg-red-600';
        let icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

        // Buat Elemen Toast
        const toast = document.createElement('div');
        toast.id = 'custom-toast';
        toast.className = `fixed top-6 right-6 z-[10000] flex items-center w-full max-w-xs p-4 space-x-4 text-white ${bgColor} rounded-lg shadow-2xl transform translate-y-[-100%] opacity-0 transition-all duration-300 ease-out`;

        toast.innerHTML = `
            <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 bg-white/20 rounded-lg">
                <i class="fas ${icon}"></i>
            </div>
            <div class="text-sm font-bold">${message}</div>
            <button onclick="this.parentElement.remove()" class="ml-auto -mx-1.5 -my-1.5 bg-white/10 text-white hover:bg-white/20 rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 inline-flex h-8 w-8 items-center justify-center">
                <i class="fas fa-times"></i>
            </button>
        `;

        document.body.appendChild(toast);

        // Animasi Masuk
        requestAnimationFrame(() => {
            toast.classList.remove('translate-y-[-100%]', 'opacity-0');
        });

        // Hilang Otomatis setelah 3 detik
        setTimeout(() => {
            if (document.body.contains(toast)) {
                toast.classList.add('opacity-0', 'translate-y-[-100%]');
                setTimeout(() => toast.remove(), 300);
            }
        }, 3000);
    };

    // ==========================================
    // 8. GLOBAL FIX: LEAFLET POPUP CLOSE BUTTON
    // ==========================================

    // Mencegah tombol close (X) pada popup peta me-refresh halaman
    document.body.addEventListener('click', function (e) {
        // Cek apakah elemen yang diklik adalah tombol close popup Leaflet
        const closeBtn = e.target.closest('.leaflet-popup-close-button');

        if (closeBtn) {
            e.preventDefault();  // Stop aksi default (pindah halaman)
            e.stopPropagation(); // Stop event bubbling ke TabManager

            // Secara manual tutup popup jika perlu (biasanya Leaflet sudah handle ini, 
            // tapi karena stopPropagation kadang kita perlu bantu tutup)
            if (window.validationMap) {
                window.validationMap.closePopup();
            }
        }
    });

    // Tambahan: Fix juga untuk link di dalam popup agar tidak refresh
    // (Misal link IDPEL di dalam popup marker)
    document.body.addEventListener('click', function (e) {
        // Cek apakah klik terjadi DI DALAM container peta
        const mapContainer = e.target.closest('#rbm-map');

        if (mapContainer && e.target.tagName === 'A') {
            // Jika link itu hanyalah anchor kosong atau javascript:void
            const href = e.target.getAttribute('href');
            if (!href || href === '#' || href.startsWith('javascript')) {
                e.preventDefault();
            }
        }
    });

})();