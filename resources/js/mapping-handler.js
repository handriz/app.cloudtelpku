/**
 * MAPPING HANDLER (FULL VERSION)
 * Mengurus interaksi UI pada halaman Mapping/Validasi
 * Termasuk: Dashboard Map, Preview Map (Modal), Street View, Image Viewer, & Toast
 */

(function () {
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
    // 2. INTERAKSI BARIS TABEL (MASTER-DETAIL)
    // ==========================================
    window.selectMappingRow = function (row, data) {
        // A. Highlight Baris
        document.querySelectorAll('tbody tr').forEach(tr => {
            tr.classList.remove('bg-indigo-50', 'dark:bg-indigo-900/30');
        });
        if (row) row.classList.add('bg-indigo-50', 'dark:bg-indigo-900/30');

        // B. Update Teks Info
        setText('detail-idpel', data.idpel);
        setText('detail-user', data.user_pendataan);

        // C. Update Foto Inspector
        updatePhotoInspector('kwh', data.foto_kwh);
        updatePhotoInspector('bangunan', data.foto_bangunan);

        // D. Update Peta Dashboard
        const lat = parseFloat(data.latitudey);
        const lng = parseFloat(data.longitudex);

        const mapOverlay = document.getElementById('map-error-overlay');
        const svBtn = document.getElementById('google-street-view-link');
        const coordText = document.getElementById('detail-lat-lon');
        const btnInputManual = document.getElementById('btn-input-manual');

        if (lat && lng && !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
            // Koordinat Valid
            if (coordText) coordText.textContent = `${lat}, ${lng}`;
            if (mapOverlay) mapOverlay.classList.add('hidden');

            if (svBtn) {
                svBtn.classList.remove('hidden', 'pointer-events-none', 'opacity-50');
                svBtn.onclick = function () {
                    // Buka Street View External
                    window.open(`http://googleusercontent.com/maps.google.com/layer=c&cbll=${lat},${lng}`, '_blank');
                };
            }

            // Trigger event untuk update peta
            window.dispatchEvent(new CustomEvent('map:focus', {
                detail: { lat: lat, lng: lng, idpel: data.idpel }
            }));

        } else {
            // Koordinat Invalid
            if (coordText) coordText.textContent = 'Tidak Ada Data';
            if (mapOverlay) mapOverlay.classList.remove('hidden');

            if (svBtn) svBtn.classList.add('hidden', 'pointer-events-none', 'opacity-50');

            if (btnInputManual) {
                btnInputManual.onclick = null; // Reset listener
                btnInputManual.onclick = function (e) {
                    e.stopPropagation();
                    const isVerified = row.getAttribute('data-verified') === '1';
                    const editUrl = row.dataset.editUrl;

                    if (isVerified) {
                        window.showToast("Data Verified terkunci. Tarik kembali dulu.", "error");
                    } else if (editUrl) {
                        window.openEditModal(editUrl);
                    }
                };
            }
        }
    };

    function setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text || '-';
    }

    function updatePhotoInspector(type, path) {
        const imgEl = document.getElementById(`detail-foto-${type}`);
        const phEl = document.getElementById(`placeholder-foto-${type}`);
        const zoomEl = document.getElementById(`zoom-${type}`);

        if (!imgEl || !phEl) return;

        if (path) {
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

    // ==========================================
    // 3. IMAGE VIEWER (ZOOM/PAN/ROTATE)
    // ==========================================
    let imgState = { scale: 1, rotate: 0, pX: 0, pY: 0, isDragging: false, startX: 0, startY: 0 };
    const modal = document.getElementById('image-viewer-modal');
    const imgEl = document.getElementById('image-viewer-img');
    const container = document.getElementById('image-container');

    window.viewImage = function (type) {
        const source = document.getElementById(`detail-foto-${type}`);
        if (source && source.src && modal && imgEl) {
            imgEl.src = source.src;
            resetImageState();
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
        updateTransform(true);
    };

    window.adjustImage = function (action, val) {
        if (action === 'zoom') {
            const newScale = imgState.scale + val;
            if (newScale >= 0.5 && newScale <= 10) imgState.scale = newScale;
        } else if (action === 'rotate') {
            imgState.rotate += val;
        }
        updateTransform(true);
    };

    function updateTransform(useTransition = false) {
        if (!imgEl) return;
        imgEl.style.transition = useTransition ? 'transform 0.2s cubic-bezier(0.1, 0.7, 1.0, 0.1)' : 'none';
        imgEl.style.transform = `translate(${imgState.pX}px, ${imgState.pY}px) scale(${imgState.scale}) rotate(${imgState.rotate}deg)`;
    }

    if (container) {
        container.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -0.2 : 0.2;
            const newScale = imgState.scale + delta;
            if (newScale >= 0.5 && newScale <= 10) {
                imgState.scale = newScale;
                updateTransform(true);
            }
        }, { passive: false });

        container.addEventListener('mousedown', (e) => {
            if (e.button !== 0) return;
            e.preventDefault();
            e.stopPropagation();
            imgState.isDragging = true;
            imgEl.style.transition = 'none';
            imgState.startX = e.clientX - imgState.pX;
            imgState.startY = e.clientY - imgState.pY;
            container.style.cursor = 'grabbing';
        });

        window.addEventListener('mousemove', (e) => {
            if (!imgState.isDragging) return;
            e.preventDefault();
            imgState.pX = e.clientX - imgState.startX;
            imgState.pY = e.clientY - imgState.startY;
            imgEl.style.transform = `translate(${imgState.pX}px, ${imgState.pY}px) scale(${imgState.scale}) rotate(${imgState.rotate}deg)`;
        });

        window.addEventListener('mouseup', () => {
            if (imgState.isDragging) {
                imgState.isDragging = false;
                container.style.cursor = 'grab';
            }
        });

        container.addEventListener('dragstart', (e) => e.preventDefault());
    }

    document.addEventListener('keydown', (e) => {
        if (modal && !modal.classList.contains('hidden')) {
            if (e.key === 'Escape') closeImageViewer();
            if (e.key === '+' || e.key === '=') adjustImage('zoom', 0.2);
            if (e.key === '-') adjustImage('zoom', -0.2);
            if (e.key === '0') resetImageState();
        }
    });

    // ==========================================
    // 4. DASHBOARD MAP HANDLER (LEAFLET)
    // ==========================================
    window.validationMap = null;
    window.validationMarker = null;
    window.neighborLayer = null;

    function updateMapPosition(lat, lng, idpel) {
        const mapContainer = document.getElementById('rbm-map');
        if (!mapContainer) return;

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

            // Layer Jalan/Label
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19
            }).addTo(window.validationMap);

            window.neighborLayer = L.layerGroup().addTo(window.validationMap);
        } else {
            window.validationMap.flyTo([lat, lng], 18, { animate: true, duration: 1.5 });
            window.validationMap.invalidateSize();
        }

        // Marker Utama
        if (window.validationMarker) window.validationMap.removeLayer(window.validationMarker);

        const mainIcon = L.divIcon({
            className: 'custom-pin',
            html: `<div class="w-5 h-5 bg-indigo-600 rounded-full border-2 border-white shadow-[0_0_15px_rgba(79,70,229,0.8)] animate-bounce z-50"></div>`,
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        });

        window.validationMarker = L.marker([lat, lng], { icon: mainIcon, zIndexOffset: 1000 })
            .addTo(window.validationMap)
            .bindPopup(`<div class="font-bold text-gray-800 text-sm p-1">${idpel}</div>`)
            .openPopup();

        fetchNeighbors(idpel);
    }

    function fetchNeighbors(searchIdpel) {
        const urlInput = document.getElementById('api-map-coordinates');
        if (!urlInput) return;

        if (window.neighborLayer) window.neighborLayer.clearLayers();

        fetch(`${urlInput.value}?search=${searchIdpel}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(res => res.json())
            .then(data => {
                if (data.nearby && data.nearby.length > 0) {
                    data.nearby.forEach(p => {
                        if (p.idpel == searchIdpel) return;
                        const lat = parseFloat(p.latitudey);
                        const lng = parseFloat(p.longitudex);

                        if (lat && lng) {
                            const neighborIcon = L.divIcon({
                                className: 'neighbor-pin',
                                html: `<div class="w-3 h-3 bg-yellow-400 rounded-full border border-white shadow-sm opacity-90 hover:scale-125 transition-transform"></div>`,
                                iconSize: [12, 12],
                                iconAnchor: [6, 6]
                            });

                            const marker = L.marker([lat, lng], { icon: neighborIcon })
                                .bindPopup(`<div class="text-xs"><strong>Tetangga</strong><br>${p.idpel}</div>`);

                            if (window.neighborLayer) window.neighborLayer.addLayer(marker);
                        }
                    });
                }
            });
    }

    window.addEventListener('map:focus', function (e) {
        const { lat, lng, idpel } = e.detail;
        if (lat && lng) updateMapPosition(lat, lng, idpel);
    });

    // Init Map Awal (Default)
    setTimeout(() => {
        const mapContainer = document.getElementById('rbm-map');
        if (mapContainer && !window.validationMap) {
            const defLat = 0.5071;
            const defLng = 101.4478;
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
    // 5. DYNAMIC MODAL HANDLER (STATIC BACKDROP)
    // ==========================================
    window.openEditModal = function (url) {
        let modalContainer = document.getElementById('dynamic-form-modal');

        // 1. Buat Container jika belum ada
        if (!modalContainer) {
            modalContainer = document.createElement('div');
            modalContainer.id = 'dynamic-form-modal';
            // Perhatikan z-index tinggi dan background gelap
            modalContainer.className = 'fixed inset-0 z-[9999] hidden bg-gray-900/75 backdrop-blur-sm flex items-center justify-center p-4 transition-opacity duration-300 opacity-0';

            // [LOGIKA BARU] STATIC BACKDROP
            // Mencegah modal tertutup saat background diklik
            modalContainer.onclick = function (e) {
                if (e.target === modalContainer) {
                    // Opsional: Efek "Bounce" agar user tahu modal terkunci
                    const contentBox = document.getElementById('dynamic-modal-content');
                    if (contentBox) {
                        contentBox.classList.remove('scale-100');
                        contentBox.classList.add('scale-105'); // Membesar sedikit
                        setTimeout(() => {
                            contentBox.classList.remove('scale-105');
                            contentBox.classList.add('scale-100'); // Kembali normal
                        }, 150);
                    }
                    // Jangan tutup modal!
                    return;
                }
            };

            modalContainer.innerHTML = `
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto transform scale-95 transition-transform duration-300 relative"
                id="dynamic-modal-content"
                onclick="event.stopPropagation()">
            </div>
            `;


            document.body.appendChild(modalContainer);
        }

        const contentBox = document.getElementById('dynamic-modal-content');
        modalContainer.classList.remove('hidden');

        // Animasi Masuk
        setTimeout(() => {
            modalContainer.classList.remove('opacity-0');
            contentBox.classList.remove('scale-95');
            contentBox.classList.add('scale-100');
        }, 10);

        // Loading State
        contentBox.innerHTML = `< div class="p-12 text-center" ><i class="fas fa-spinner fa-spin text-5xl text-indigo-500 mb-4"></i><p>Memuat form...</p></div > `;

        // Fetch Konten
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
        })
            .then(res => { if (!res.ok) throw new Error("Gagal memuat."); return res.text(); })
            .then(html => {
                contentBox.innerHTML = html;

                // Pasang event listener KHUSUS untuk tombol close (X) dan tombol Batal
                const closeBtns = contentBox.querySelectorAll('[data-modal-close]');
                closeBtns.forEach(btn => {
                    btn.onclick = function (e) {
                        e.preventDefault();
                        window.closeDynamicModal(); // Hanya tutup jika tombol ini ditekan
                    };
                });
            })
            .catch(err => {
                contentBox.innerHTML = `< div class="p-8 text-center text-red-500" > Error: ${err.message} <br><button onclick="window.closeDynamicModal()" class="mt-4 px-4 py-2 bg-gray-200 rounded text-black">Tutup</button></div>`;
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

    // ==========================================
    // 6. TOAST NOTIFICATION
    // ==========================================
    window.showToast = function (message, type = 'error') {
        const oldToast = document.getElementById('custom-toast');
        if (oldToast) oldToast.remove();

        let bgColor = type === 'success' ? 'bg-green-600' : 'bg-red-600';
        let icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

        const toast = document.createElement('div');
        toast.id = 'custom-toast';
        toast.className = `fixed top - 6 right - 6 z - [10000] flex items - center w - full max - w - xs p - 4 space - x - 4 text - white ${bgColor} rounded - lg shadow - 2xl transform translate - y - [-100 %] opacity - 0 transition - all duration - 300 ease - out`;
        toast.innerHTML = `< div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 bg-white/20 rounded-lg" > <i class="fas ${icon}"></i></div > <div class="text-sm font-bold">${message}</div>`;

        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.remove('translate-y-[-100%]', 'opacity-0'));
        setTimeout(() => { if (toast) toast.remove(); }, 3000);
    };

    // ==========================================
    // 7. GLOBAL FIXES
    // ==========================================
    document.body.addEventListener('click', function (e) {
        if (e.target.closest('.leaflet-popup-close-button')) {
            e.preventDefault();
            e.stopPropagation();
            if (window.validationMap) window.validationMap.closePopup();
        }
    });

    // ==========================================
    // 8. UPDATE LINK STREET VIEW (HEMAT BIAYA)
    // ==========================================
    window.updateExternalStreetView = function () {
        // Cek input dari Create atau Edit
        const latInput = document.getElementById('latitudey_create') || document.getElementById('latitudey_edit');
        const lngInput = document.getElementById('longitudex_create') || document.getElementById('longitudex_edit');
        const btn = document.getElementById('btn-open-streetview-external');
        const warning = document.getElementById('streetview-warning');

        if (!latInput || !lngInput || !btn) return;

        const lat = parseFloat(latInput.value);
        const lng = parseFloat(lngInput.value);

        if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
            // URL Street View Google Maps
            const url = `https://www.google.com/maps?layer=c&cbll=${lat},${lng}`;
            btn.href = url;
            btn.classList.remove('pointer-events-none', 'opacity-50');
            if (warning) warning.classList.add('hidden');
        } else {
            btn.href = '#';
            btn.classList.add('pointer-events-none', 'opacity-50');
            if (warning) warning.classList.remove('hidden');
        }
    };

    // ==========================================
    // 9. PREVIEW MAP HANDLER (MODAL CREATE/EDIT)
    // ==========================================
    window.previewMap = null;
    window.previewMarker = null;

    window.initPreviewMap = function () {
        const mapContainer = document.getElementById('preview-map');
        if (!mapContainer) return; // Keluar jika elemen tidak ada

        // Reset Peta Lama
        if (window.previewMap) {
            window.previewMap.remove();
            window.previewMap = null;
        }

        const latInput = document.getElementById('latitudey_create') || document.getElementById('latitudey_edit');
        const lngInput = document.getElementById('longitudex_create') || document.getElementById('longitudex_edit');

        // Default Pekanbaru
        let initLat = 0.5071;
        let initLng = 101.4478;
        let initZoom = 13;

        // Jika ada nilai awal (Edit Mode), gunakan itu
        if (latInput && lngInput && latInput.value && lngInput.value) {
            initLat = parseFloat(latInput.value);
            initLng = parseFloat(lngInput.value);
            initZoom = 18;
        }

        // Render Peta Preview
        window.previewMap = L.map('preview-map').setView([initLat, initLng], initZoom);

        // Layer Satelit
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles © Esri',
            maxZoom: 19
        }).addTo(window.previewMap);

        // Layer Label
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19
        }).addTo(window.previewMap);

        // Pasang Marker Awal
        if (latInput && lngInput && latInput.value && lngInput.value) {
            window.updatePreviewMarker(initLat, initLng);
        }

        // FIX PETA BLANK: Refresh ukuran setelah render
        setTimeout(() => {
            if (window.previewMap) window.previewMap.invalidateSize();
        }, 500);

        setupPreviewTabSwitcher();
    };

    // Update Marker Preview
    window.updatePreviewMarker = function (lat, lng) {
        if (!window.previewMap) return;
        if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
            if (window.previewMarker) window.previewMap.removeLayer(window.previewMarker);
            window.previewMarker = L.marker([lat, lng]).addTo(window.previewMap);
            window.previewMap.flyTo([lat, lng], 18);
        }
    };

    // Logic Ganti Tab di Modal (Map vs StreetView)
    function setupPreviewTabSwitcher() {
        const btnMap = document.getElementById('tab-btn-map');
        const btnSv = document.getElementById('tab-btn-streetview');

        if (btnMap && btnSv) {
            btnMap.onclick = function () {
                this.classList.add('border-indigo-500', 'text-indigo-600');
                this.classList.remove('border-transparent', 'text-gray-500');
                btnSv.classList.remove('border-indigo-500', 'text-indigo-600');
                btnSv.classList.add('border-transparent', 'text-gray-500');

                document.getElementById('tab-panel-map').classList.remove('hidden');
                document.getElementById('tab-panel-streetview').classList.add('hidden');

                if (window.previewMap) window.previewMap.invalidateSize();
            };

            btnSv.onclick = function () {
                this.classList.add('border-indigo-500', 'text-indigo-600');
                this.classList.remove('border-transparent', 'text-gray-500');
                btnMap.classList.remove('border-indigo-500', 'text-indigo-600');
                btnMap.classList.add('border-transparent', 'text-gray-500');

                document.getElementById('tab-panel-streetview').classList.remove('hidden');
                document.getElementById('tab-panel-map').classList.add('hidden');
            };
        }
    }

    // OBSERVER: Deteksi Modal Muncul (AJAX)
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.addedNodes.length) {
                const previewMapEl = document.getElementById('preview-map');
                // Jika elemen peta baru muncul & belum ada class leaflet
                if (previewMapEl && !previewMapEl.classList.contains('leaflet-container')) {
                    window.initPreviewMap();
                }
            }
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });

    // LISTENER INPUT GLOBAL: Update Marker & Link saat mengetik
    document.addEventListener('input', function (e) {
        // Cek apakah target adalah input koordinat Create/Edit
        if (['latitudey_create', 'longitudex_create', 'latitudey_edit', 'longitudex_edit'].includes(e.target.id)) {

            // Tentukan suffix ID (create atau edit)
            const suffix = e.target.id.includes('edit') ? '_edit' : '_create';

            const lat = parseFloat(document.getElementById('latitudey' + suffix)?.value);
            const lng = parseFloat(document.getElementById('longitudex' + suffix)?.value);

            if (window.previewMap) window.updatePreviewMarker(lat, lng);
            if (window.updateExternalStreetView) window.updateExternalStreetView();
        }
    });


})();