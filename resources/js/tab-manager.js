/*
===================================================================
  TAB MANAGER V2 (Refactored)
  
  Struktur file ini telah di-refactor ke dalam modul-modul
  untuk mempermudah pemeliharaan.
  
  - App.State: Variabel global
  - App.Icons: Ikon Leaflet
  - App.Utils: Fungsi helper (notifikasi, modal konfirmasi)
  - App.Tabs: Logika manajemen Tab (load, create, activate)
  - App.Modal: Logika modal (open, close, image zoom)
  - App.Kddk: Logika spesifik Tab Mapping KDDK
  - App.Validation: Logika spesifik Tab Validasi
  - App.FormCreate: Logika spesifik Modal "Tambah Data"
  - App.Listeners: Inisialisasi dan event listener global
===================================================================
*/

const GOOGLE_API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

// ===================================================================
// ===== 1. GLOBAL STATE & ICONS =====
// ===================================================================

const App = {};

App.State = {
    mappingFeatureGroup: null,
    mappingClickedMarker: null,
    validationMapInstance: null,
    validationMarker: null,
    currentValidationId: null,
    currentValidationDetails: null, // window.currentValidationDetails
    mapInstance: null, // window.mapInstance
    searchDebounceTimer: null
};

App.Icons = {
    red: new L.Icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
    }),
    blue: new L.Icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
    })
};

// ===================================================================
// ===== 2. CORE UTILITIES (Notifikasi, Modal, Helper) =====
// ===================================================================

App.Utils = (() => {
    
    function displayNotification(type, message) {
        let container = null;
        const activeTabContent = document.querySelector('.tab-content:not(.hidden)');
        
        if (activeTabContent) {
            container = activeTabContent.querySelector('#interactive-validation-container') || 
                        activeTabContent.querySelector('#kddk-notification-container');
        }

        if (!container) {
            console.warn("displayNotification: Tidak ada container di tab aktif. Fallback ke alert.");
            alert(message);
            return;
        }

        container.querySelectorAll('.bg-green-100, .bg-red-100').forEach(el => el.remove());

        let alertClass = type, strongText = type;
        if (type === 'success' || type === 'validate') { 
            alertClass = 'bg-green-100 border-green-400 text-green-700'; strongText = 'Berhasil!';
        } else if (type === 'reject') { 
            alertClass = 'bg-red-100 border-red-400 text-red-700'; strongText = 'Penolakan Berhasil!'; 
        } else { 
            alertClass = 'bg-red-100 border-red-400 text-red-700'; strongText = 'Error!';
        }            
        
        const notificationHtml = `
            <div id="action-notification-alert" class="mt-4 ${alertClass} border px-4 py-3 rounded relative" role="alert" style="margin-top: 0.5rem !important;">
                <strong class="font-bold">${strongText}</strong>
                <span class="block sm:inline"> ${message}</span>
                <button type="button" class="absolute top-0 right-0 p-4 text-xl" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
        `;
        container.insertAdjacentHTML('afterbegin', notificationHtml); 

        const newAlert = container.querySelector('#action-notification-alert');
        if (newAlert) {
            newAlert.scrollIntoView({ behavior: 'smooth', block: 'start' });
            const autoHideTimer = setTimeout(() => {
                if (newAlert) {
                    newAlert.style.opacity = 0; 
                    setTimeout(() => newAlert.remove(), 500);
                }
            }, 15000);

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
        const overlay = modal;

        if (!modal || !titleEl || !messageEl || !okButton || !cancelButton) {
            console.error('Elemen modal konfirmasi kustom tidak ditemukan!');
            if (confirm(message)) onConfirm();
            return;
        }

        titleEl.textContent = title || 'Konfirmasi Tindakan';
        messageEl.textContent = message;
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        const handleConfirm = () => { cleanup(); onConfirm(); };
        const handleCancel = () => cleanup();
        const handleOverlayClick = (e) => { if (e.target === overlay) cleanup(); };

        const cleanup = () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            okButton.removeEventListener('click', handleConfirm);
            cancelButton.removeEventListener('click', handleCancel);
            overlay.removeEventListener('click', handleOverlayClick);
        };

        okButton.removeEventListener('click', handleConfirm);
        cancelButton.removeEventListener('click', handleCancel);
        overlay.removeEventListener('click', handleOverlayClick);

        okButton.addEventListener('click', handleConfirm);
        cancelButton.addEventListener('click', handleCancel);
        overlay.addEventListener('click', handleOverlayClick);
    }

    function getActiveTabName(tabsHeader) {
        const activeTab = tabsHeader.querySelector('.tab-button.active');
        return activeTab ? activeTab.dataset.tabName : null; 
    }

    return {
        displayNotification,
        showCustomConfirm,
        getActiveTabName
    };
})();

// ===================================================================
// ===== 3. TAB MANAGEMENT =====
// ===================================================================

App.Tabs = (() => {
    let tabsHeader, tabsContent, scrollLeftBtn, scrollRightBtn, dashboardUrl;

    function init(elements) {
        tabsHeader = elements.tabsHeader;
        tabsContent = elements.tabsContent;
        scrollLeftBtn = elements.scrollLeftBtn;
        scrollRightBtn = elements.scrollRightBtn;
        dashboardUrl = elements.dashboardUrl;
    }

    function updateScrollButtons() {
        if (!tabsHeader || !scrollLeftBtn || !scrollRightBtn) return;
        const shouldShow = tabsHeader.scrollWidth > tabsHeader.clientWidth;
        scrollLeftBtn.classList.toggle('hidden', !shouldShow);
        scrollRightBtn.classList.toggle('hidden', !shouldShow);
    }

    function loadTabContent(tabName, url, callback = null) {
        const tabContent = document.getElementById(`${tabName}-content`);
        if (!tabContent) return;

        if (App.State.mapInstance) {
            App.State.mapInstance.remove();
            App.State.mapInstance = null;
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
            const newContentContainer = document.createElement('div');
            newContentContainer.innerHTML = html;
            tabContent.innerHTML = ''; 
            tabContent.appendChild(newContentContainer);
             
           setTimeout(() => {
                const searchInput = tabContent.querySelector('form[id*="-search-form"] input[name="search"]');
                if (searchInput && searchInput.value.length > 0) {
                    const clearButton = tabContent.querySelector('#clear-search-button');
                    if (clearButton) clearButton.classList.remove('hidden');
                }
    
                const mapContainer = tabContent.querySelector('#map');
                if (mapContainer) {
                    App.Kddk.initializeMap(mapContainer, callback);
                } else {
                    if (callback && typeof callback === 'function') callback();
                }

                const validationContainer = tabContent.querySelector('#interactive-validation-container');
                if (validationContainer) {
                    App.Validation.initializeValidationTab(validationContainer); 
                }
                
                updateScrollButtons();
                
                const cleanUrl = new URL(url, window.location.origin);
                cleanUrl.searchParams.delete('is_ajax');
                history.pushState({ tab: tabName }, '', cleanUrl.toString());
            }, 5);
        })
        .catch(error => {
            tabContent.innerHTML = `<div class="p-4 text-red-500">Gagal memuat konten.</div>`;
            console.error('Error loading tab content:', error);
        });
    }

    function activateTab(tabName, url, pushHistory = true) {
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
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
                setTimeout(() => App.Kddk.initializeMap(mapContainer), 150);
            }
        }
        
        if (pushHistory) {
            const newUrl = new URL(url, window.location.origin);
            newUrl.searchParams.delete('is_ajax');
            history.replaceState({ tab: tabName }, '', newUrl.toString());
        }
        updateScrollButtons();
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

    return {
        init,
        loadTabContent,
        createTab,
        activateTab,
        closeTab,
        updateScrollButtons,
        initializeDashboardTab
    };
})();

// ===================================================================
// ===== 4. MODAL MANAGEMENT =====
// ===================================================================

App.Modal = (() => {
    let mainModal, modalContent, imageModal, imageModalImg, imageModalClose, imageModalOverlay, modalMeterInputContainer, modalMeterInput;

    function init(elements) {
        mainModal = elements.mainModal;
        modalContent = elements.modalContent;
        imageModal = elements.imageModal;
        imageModalImg = elements.imageModalImg;
        imageModalClose = elements.imageModalClose;
        imageModalOverlay = elements.imageModalOverlay;
        modalMeterInputContainer = elements.modalMeterInputContainer;
        modalMeterInput = elements.modalMeterInput;

        // Pasang listener tutup modal gambar
        if (imageModal) {
            imageModalClose.addEventListener('click', closeImageModal);
            imageModalOverlay.addEventListener('click', closeImageModal);
        }
    }

    function open(url) {
        if (!mainModal || !modalContent) return;
        modalContent.innerHTML = '<div class="text-center p-8"><i class="fas fa-spinner fa-spin fa-2x text-gray-400"></i></div>';
        mainModal.classList.remove('hidden');

        let fetchUrl = new URL(url, window.location.origin);
        fetchUrl.searchParams.set('is_ajax', '1');

        fetch(fetchUrl)
            .then(response => response.text())
            .then(html => {
                modalContent.innerHTML = html;
                
                // Inisialisasi script spesifik modal
                setTimeout(function() {
                    const previewMapContainer = modalContent.querySelector('#preview-map');
                    if (previewMapContainer) {
                        App.FormCreate.initializePreviewMap(modalContent);
                    }
                    const photoUploadInputs = modalContent.querySelectorAll('.photo-upload-input');
                    if (photoUploadInputs.length > 0) {
                        App.FormCreate.initializePhotoUpload(modalContent);
                    }
                    const createForm = modalContent.querySelector('#create-mapping-form');
                    if (createForm) {
                        App.FormCreate.initializeCreateFormValidation(createForm);
                    }
                    // ... (Inisialisasi form upload chunk/foto bisa ditambahkan di sini juga) ...
                }, 150);
            })
            .catch(error => {
                modalContent.innerHTML = '<div class="text-center p-8 text-red-500">Gagal memuat konten.</div>';
                console.error("Fetch Error:", error);
            });
    }

    function close() {
        if (!mainModal) return;
        mainModal.classList.add('hidden');
        modalContent.innerHTML = '';
    }

    function showImage(imgElement, zoomType) {
        if (imgElement && imgElement.src) {
            imageModalImg.src = imgElement.src;

            if (zoomType === 'kwh' && modalMeterInputContainer) {
                const mainMeterInput = imgElement.closest('#validation-content')?.querySelector('#eval_meter_input');
                modalMeterInput.value = mainMeterInput ? mainMeterInput.value : ''; 
                modalMeterInputContainer.classList.remove('hidden');
                setTimeout(() => modalMeterInput.focus(), 50); 
            } else if (modalMeterInputContainer) {
                modalMeterInputContainer.classList.add('hidden');
            }
            imageModal.classList.remove('hidden');
        }
    }

    function closeImageModal() {
        if (modalMeterInputContainer && !modalMeterInputContainer.classList.contains('hidden')) {
            const modalValue = modalMeterInput.value;
            const activeTabContent = document.querySelector('.tab-content:not(.hidden)');
            const mainMeterInput = activeTabContent?.querySelector('#eval_meter_input');
            
            if (mainMeterInput) {
                mainMeterInput.value = modalValue;
                mainMeterInput.dispatchEvent(new Event('input', { bubbles: true })); 
            }
            modalMeterInputContainer.classList.add('hidden');
            modalMeterInput.value = '';
        }
        imageModal.classList.add('hidden');
        imageModalImg.src = ''; 
    }

    return {
        init,
        open,
        close,
        showImage,
        closeImageModal
    };
})();

// ===================================================================
// ===== 5. KDDK MAPPING TAB LOGIC =====
// ===================================================================

App.Kddk = (() => {

    function initializeMap(mapContainer, callback = null) {
        if (!mapContainer) return;

        if (App.State.mapInstance) {
            App.State.mapInstance.remove();
            App.State.mapInstance = null;
        }

        const map = L.map(mapContainer).setView([0.5071, 101.4478], 12);
        App.State.mapInstance = map;

        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles © Esri'
        }).addTo(map);
       
        const activeTabContent = mapContainer.closest('.tab-content');
        const searchInput = activeTabContent.querySelector('#mapping-search-form input[name="search"]');
        const searchValue = searchInput ? searchInput.value : '';

        if (App.State.mappingFeatureGroup) App.State.mappingFeatureGroup.clearLayers();
        if (App.State.mappingClickedMarker) {
            App.State.mappingClickedMarker.remove();
            App.State.mappingClickedMarker = null;
        }

        if (!searchValue) {
            console.log("initializeMap: Tidak ada search value, peta akan kosong.");
            if (callback) callback();
            return;
        }

        let coordinatesUrl = new URL('/team/mapping-coordinates', window.location.origin);
        coordinatesUrl.searchParams.set('search', searchValue);
        
        fetch(coordinatesUrl.toString())
            .then(response => response.json())
            .then(data => {
                App.State.mappingFeatureGroup = L.featureGroup();
                let markerToOpen = null;

                if (data.all && data.all.length > 0) {
                    data.all.forEach(point => {
                        const marker = L.marker([point.latitudey, point.longitudex], { icon: App.Icons.blue });
                        marker.bindPopup(`<b>Idpel:</b> ${point.idpel}`);
                        App.State.mappingFeatureGroup.addLayer(marker); 
                    });
                }

                if (data.searched && data.searched.length > 0) {
                    data.searched.forEach(point => {
                        const marker = L.marker([point.latitudey, point.longitudex], { icon: App.Icons.red });
                        marker.bindPopup(`<b>Idpel (dicari):</b> ${point.idpel}`);
                        App.State.mappingFeatureGroup.addLayer(marker); 
                        if (!markerToOpen) markerToOpen = marker;
                    });
                }

                if (data.nearby && data.nearby.length > 0) {
                    data.nearby.forEach(point => {
                        const marker = L.marker([point.latitudey, point.longitudex], { icon: App.Icons.blue });
                        marker.bindPopup(`<b>Idpel Terdekat:</b> ${point.idpel}`);
                        App.State.mappingFeatureGroup.addLayer(marker); 
                    });
                }
                
                if (App.State.mappingFeatureGroup.getLayers().length > 0) {
                    App.State.mappingFeatureGroup.addTo(map);
                    
                    if (searchValue && data.searched && data.searched.length === 1 && (!data.nearby || data.nearby.length === 0)) {
                        map.setView(markerToOpen.getLatLng(), 18);
                        markerToOpen.openPopup();
                    } 
                    else if (markerToOpen) {
                        map.fitBounds(App.State.mappingFeatureGroup.getBounds().pad(0.1));
                        setTimeout(() => markerToOpen.openPopup(), 500);
                    }
                    else {
                        map.fitBounds(App.State.mappingFeatureGroup.getBounds().pad(0.1));
                    }
                }
                
                if (callback) callback();
            })
            .catch(error => {
                console.error('Error fetching map data:', error);
                if (callback) callback();
            });
    }

    async function renderClickedMapMarkers(idpel, objectid, lat, lon) {
        if (App.State.mappingFeatureGroup) App.State.mappingFeatureGroup.clearLayers();
        if (App.State.mappingClickedMarker) {
            App.State.mappingClickedMarker.remove();
            App.State.mappingClickedMarker = null;
        }

        let coordinatesUrl = new URL('/team/mapping-coordinates', window.location.origin);
        coordinatesUrl.searchParams.set('search', idpel);

        try {
            const response = await fetch(coordinatesUrl.toString());
            const data = await response.json();

            App.State.mappingFeatureGroup = L.featureGroup();
            let clickedMarkerRef = null;

            if (data.nearby && data.nearby.length > 0) {
                data.nearby.forEach(point => {
                    const marker = L.marker([point.latitudey, point.longitudex], { icon: App.Icons.blue });
                    marker.bindPopup(`<b>Idpel (terdekat):</b> ${point.idpel}`).openPopup();
                    App.State.mappingFeatureGroup.addLayer(marker);
                });
            }

            clickedMarkerRef = L.marker([lat, lon], { 
                icon: App.Icons.red,
                zIndexOffset: 1000 
            });
            clickedMarkerRef.on('popupopen', function() {
                const popupElement = clickedMarkerRef.getPopup().getElement();
                if (popupElement) {
                    const closeButton = popupElement.querySelector('.leaflet-popup-close-button');
                    if (closeButton) {
                        closeButton.addEventListener('click', e => e.stopPropagation());
                    }
                }
            });
            clickedMarkerRef.bindPopup(`<b>Idpel:</b> ${idpel}<br><b>Object ID:</b> ${objectid}`,{
                maxWidth: 250,
                className: 'leaflet-popup-small'
            });
            
            App.State.mappingClickedMarker = clickedMarkerRef; 

            if (App.State.mappingFeatureGroup.getLayers().length > 0) {
                 App.State.mappingFeatureGroup.addTo(App.State.mapInstance);
            }
            if (App.State.mappingClickedMarker) {
                 App.State.mappingClickedMarker.addTo(App.State.mapInstance);
            }
            if (clickedMarkerRef) {
                setTimeout(() => clickedMarkerRef.openPopup(), 50);
            }

        } catch (error) {
            console.error('Error fetching coordinates for clicked item:', error);
        }
    }

    function handleDataRowClick(dataRow) {
        const data = dataRow.dataset;
        const activePanel = dataRow.closest('.tab-content');
        if (!activePanel) return;

        // Ambil semua elemen UI
        const titleSpanEl = activePanel.querySelector('#detail-title-span');
        const stampEl = activePanel.querySelector('#detail-status-stamp');
        const kwhLinkEl = activePanel.querySelector('#detail-foto-kwh-link');
        const kwhImgEl = activePanel.querySelector('#detail-foto-kwh');
        const kwhPlaceholderEl = activePanel.querySelector('#placeholder-foto-kwh');
        const bangunanLinkEl = activePanel.querySelector('#detail-foto-bangunan-link');
        const bangunanImgEl = activePanel.querySelector('#detail-foto-bangunan');
        const bangunanPlaceholderEl = activePanel.querySelector('#placeholder-foto-bangunan');
        const latLonEl = activePanel.querySelector('#detail-lat-lon');
        const streetViewLinkEl = activePanel.querySelector('#google-street-view-link');

        // Update Koordinat & Street View Link
        if (latLonEl && streetViewLinkEl) {
            if (data.lat && data.lon && parseFloat(data.lat) !== 0 && parseFloat(data.lon) !== 0) {
                const lat = parseFloat(data.lat);
                const lon = parseFloat(data.lon);
                latLonEl.textContent = `${lat.toFixed(6)}, ${lon.toFixed(6)}`;
                streetViewLinkEl.href = `http://googleusercontent.com/maps/google.com/2{lat},${lon}`;
                streetViewLinkEl.classList.remove('hidden');
            } else {
                latLonEl.textContent = 'Koordinat tidak valid';
                streetViewLinkEl.classList.add('hidden');
            }
        }
        
        // Update Street View Modal Draggable Handlers
        handleStreetViewModalClick(activePanel, data);

        // Update Judul
        if (titleSpanEl) {
            titleSpanEl.textContent = `Detail Peta - ${data.idpel} (Object ID: ${data.objectid})`;
        }

        // Update Stamp
        if (stampEl) {
            const verifiedStamp = '/images/verified_stamp.png'; 
            const unverifiedStamp = '/images/unverified_stamp.png';
            if (data.enabled === 'true') {
                stampEl.src = verifiedStamp;
                stampEl.alt = 'Valid (Aktif)';
                stampEl.title = 'Status: Valid (Aktif)';
            } else {
                stampEl.src = unverifiedStamp;
                stampEl.alt = 'Tidak Aktif';
                let statusTitle = 'Status: Data Tidak Aktif';
                if (data.status === 'verified') statusTitle = 'Status: Terverifikasi (Belum Aktif)';
                if (data.status === 'superseded') statusTitle = 'Status: Digantikan';
                if (data.status === 'recalled_1') statusTitle = 'Status: Ditarik';
                stampEl.title = statusTitle;
            }
            stampEl.classList.remove('hidden');
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
        
        // Update Peta
        if (App.State.mapInstance && data.lat && data.lon) {
            const lat = parseFloat(data.lat);
            const lon = parseFloat(data.lon);
            if (!isNaN(lat) && !isNaN(lon)) {
                App.State.mapInstance.setView([lat, lon], 18);
                renderClickedMapMarkers(data.idpel, data.objectid, lat, lon);
            }
        }
        
        // Highlight baris
        const table = dataRow.closest('table');
        if (table) {
            table.querySelectorAll('.data-row-clickable').forEach(row => {
                row.classList.remove('bg-blue-100', 'dark:bg-blue-900');
            });
        }
        dataRow.classList.add('bg-blue-100', 'dark:bg-blue-900');
    }

    function handleStreetViewModalClick(activePanel, data) {
        const streetViewLinkEl = activePanel.querySelector('#google-street-view-link');
        const streetViewModal = activePanel.querySelector('#street-view-modal');
        const streetViewIframe = activePanel.querySelector('#street-view-iframe');
        const streetViewCloseButton = activePanel.querySelector('#street-view-close-button');
        const streetViewHeader = activePanel.querySelector('#street-view-header');

        if (!streetViewModal || !streetViewIframe || !streetViewCloseButton || !streetViewHeader || !streetViewLinkEl) {
            console.error("Elemen modal Street View KDDK tidak ditemukan.");
            return;
        }
        
        const handleStreetViewClick = (e) => {
            e.preventDefault(); e.stopPropagation();
            if (data.lat && data.lon && parseFloat(data.lat) !== 0 && parseFloat(data.lon) !== 0) {
                const lat = parseFloat(data.lat);
                const lon = parseFloat(data.lon);
                const streetViewUrl = `https://www.google.com/maps/embed/v1/streetview?location=${lat},${lon}&key=${GOOGLE_API_KEY}`;
                streetViewIframe.src = streetViewUrl; 
                streetViewModal.classList.remove('hidden');
            } else {
                alert('Koordinat tidak valid untuk Street View.');
            }
        };

        const closeStreetViewModal = () => {
            streetViewModal.classList.add('hidden');
            streetViewIframe.src = ""; 
            streetViewModal.style.left = '';
            streetViewModal.style.top = '';
            streetViewModal.style.right = '';
        };

        streetViewModal.style.left = '';
        streetViewModal.style.top = '';
        streetViewModal.style.right = '';

        let isDragging = false, offsetX, offsetY;
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
        
        streetViewLinkEl.removeEventListener('click', streetViewLinkEl.__handler);
        streetViewCloseButton.removeEventListener('click', streetViewCloseButton.__handler);
        streetViewHeader.removeEventListener('mousedown', streetViewHeader.__handler);

        streetViewLinkEl.__handler = handleStreetViewClick;
        streetViewCloseButton.__handler = closeStreetViewModal;
        streetViewHeader.__handler = onMouseDown;

        streetViewLinkEl.addEventListener('click', handleStreetViewClick);
        streetViewCloseButton.addEventListener('click', closeStreetViewModal);
        streetViewHeader.addEventListener('mousedown', onMouseDown);
    }

    return {
        initializeMap,
        renderClickedMapMarkers,
        handleDataRowClick
    };
})();

// ===================================================================
// ===== 6. VALIDATION TAB LOGIC =====
// ===================================================================

App.Validation = (() => {
    const MIN_REJECTION_CHARS = 5;

    function initializeValidationTab(container) {
        if (!container) return;
        console.log("Initializing validation tab...");
        App.State.currentValidationId = container.dataset.currentId || null; 
        App.State.currentValidationDetails = null; 

        const placeholder = container.querySelector('#validation-placeholder');
        const content = container.querySelector('#validation-content');
        const loading = container.querySelector('#validation-loading');
        if(placeholder) placeholder.classList.remove('hidden');
        if(content) content.classList.add('hidden');
        if(loading) loading.classList.add('hidden');

        if (App.State.validationMapInstance) { 
            App.State.validationMapInstance.remove(); 
            App.State.validationMapInstance = null; 
            App.State.validationMarker = null;
            console.log("Destroyed old validation map instance.");
        }
        console.log("Validation tab initialized. Ready for item selection.");
    }

    async function lockAndLoadDetails(id, buttonElement) {
        let container = buttonElement.closest('#interactive-validation-container');
        if (!container) {
            console.warn("### Gagal via closest(). Mencoba via tab aktif...");
            const activeTabName = App.Utils.getActiveTabName(document.getElementById('tabs-header'));
            const activeTabContent = activeTabName ? document.getElementById(`${activeTabName}-content`) : null;
            if (activeTabContent) container = activeTabContent.querySelector('#interactive-validation-container');
            else console.error("### Tidak bisa menemukan elemen konten tab aktif!");
        }
        if (!container) {
            console.error("### KRITIS: Container #interactive-validation-container tetap tidak ditemukan.");
            alert("Kesalahan internal: Tidak dapat menemukan kontainer validasi utama.");
            return;
        }
        
        const loading = container.querySelector('#validation-loading');
        const content = container.querySelector('#validation-content');
        const placeholder = container.querySelector('#validation-placeholder');

        if(!loading || !content || !placeholder) {
            console.error("### Error: Elemen UI (loading/content/placeholder) tidak ditemukan.");
            return;
        }

        placeholder.classList.add('hidden');
        content.classList.add('hidden');
        loading.classList.remove('hidden');

        const oldAlert = container.querySelector('#action-notification-alert');
        if (oldAlert) oldAlert.remove();

        container.querySelectorAll('.validation-queue-item.bg-indigo-100').forEach(btn => {
            btn.classList.remove('bg-indigo-100', 'dark:bg-indigo-900');
        });

        try {
            const fetchUrl = `/team/mapping-validation/item/${id}/lock`;
            const csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : null;
            if (!csrfToken) throw new Error('Token CSRF tidak ditemukan.');

            const fetchOptions = {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
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

            App.State.currentValidationId = data.currentItemId;
            App.State.currentValidationDetails = data.details;
            buttonElement.classList.add('bg-indigo-100', 'dark:bg-indigo-900');
            
            updateValidationUI(container, data.details);

        } catch (error) {
            console.error('### Error dalam lockAndLoadDetails:', error);
            alert(`Terjadi kesalahan: ${error.message}. Coba refresh halaman atau login ulang.`);
            loading.classList.add('hidden');
            placeholder.classList.remove('hidden');
            content.classList.add('hidden');
            App.State.currentValidationId = null;
            App.State.currentValidationDetails = null;
        }
    }

    function updateValidationUI(container, details) {
        const loading = container.querySelector('#validation-loading');
        const content = container.querySelector('#validation-content');
        const placeholder = container.querySelector('#validation-placeholder'); 

        if (!loading || !content || !placeholder) {
            console.error("Elemen UI dasar (loading/content/placeholder) tidak ditemukan!");
            return;
        }

        if (!details) {
            console.warn("updateValidationUI dipanggil tanpa data 'details'. Menampilkan placeholder.");
            loading.classList.add('hidden'); 
            placeholder.classList.remove('hidden'); 
            content.classList.add('hidden');
             if (App.State.validationMapInstance) { App.State.validationMapInstance.remove(); App.State.validationMapInstance = null; App.State.validationMarker = null;}
             return;
        }

        // 1. Update Header
        content.querySelector('#detail-idpel').textContent = details.idpel || 'IDPEL Tidak Tersedia';
        content.querySelector('#detail-user').textContent = details.user_pendataan || 'User Tidak Diketahui';
        content.querySelector('#detail-keterangan').textContent = details.keterangan || 'Tidak ada keterangan.';

        // 2. Isi Foto
        const kwhLink = content.querySelector('#detail-foto-kwh-link');
        const kwhImg = content.querySelector('#detail-foto-kwh');
        const kwhNone = content.querySelector('#detail-foto-kwh-none');
        if (kwhLink && kwhImg && kwhNone) {
            if (details.foto_kwh_url) {
                kwhImg.src = details.foto_kwh_url;
                kwhLink.classList.remove('hidden');
                kwhNone.classList.add('hidden');
            } else {
                kwhLink.classList.add('hidden');
                kwhNone.classList.remove('hidden');
            }
        }

        const bangunanLink = content.querySelector('#detail-foto-bangunan-link');
        const bangunanImg = content.querySelector('#detail-foto-bangunan');
        const bangunanNone = content.querySelector('#detail-foto-bangunan-none');
         if (bangunanLink && bangunanImg && bangunanNone) {
            if (details.foto_bangunan_url) {
                bangunanImg.src = details.foto_bangunan_url;
                bangunanLink.classList.remove('hidden');
                bangunanNone.classList.add('hidden');
            } else {
                bangunanLink.classList.add('hidden');
                bangunanNone.classList.remove('hidden');
            }
        }

        // 3. Update Action URL Tombol Form
        content.querySelector('#detail-form-reject').action = `/team/mapping-validation/${App.State.currentValidationId}/reject`; 
        content.querySelector('#detail-form-validate').action = `/team/mapping-validation/${App.State.currentValidationId}/approve`; 

        // 4. Reset Form Evaluasi
        resetEvaluationForm(content); 
        checkEvaluationForm(content, details); 

        // 5. Riwayat Penolakan
        const historyAlert = content.querySelector('#rejection-history-alert');
        const historyStatus = content.querySelector('#rejection-status');
        const historyList = content.querySelector('#rejection-list-items');
        if (historyAlert && historyStatus && historyList) {
            if (details.rejection_history && details.rejection_history.length > 0) {
                historyStatus.textContent = details.status_validasi || 'Ditolak';
                historyList.innerHTML = ''; 
                details.rejection_history.forEach(item => {
                    const li = document.createElement('li');
                    li.innerHTML = `<strong class="font-semibold">${item.label}:</strong> ${item.value}`;
                    historyList.appendChild(li);
                });
                historyAlert.classList.remove('hidden');
            } else {
                historyAlert.classList.add('hidden');
                historyList.innerHTML = '';
            }
        }
        
        // 6. Logika Street View
        handleValidationStreetView(content, container, details);

        // 7. Tampilkan Konten
        content.classList.remove('hidden');
        loading.classList.add('hidden');
        placeholder.classList.add('hidden');

        // 8. Update Peta
        setTimeout(() => {
            const mapContainer = content.querySelector('#validation-map');
            if (mapContainer) {
                if (App.State.validationMapInstance) { 
                    try { App.State.validationMapInstance.remove(); } catch(e){ console.warn("Gagal remove map lama:", e); }
                    App.State.validationMapInstance = null; 
                    App.State.validationMarker = null; 
                }
                
                try {
                    if (typeof details.lat === 'number' && typeof details.lon === 'number') {
                        const newLatLng = [details.lat, details.lon];
                        App.State.validationMapInstance = L.map(mapContainer).setView(newLatLng, 18);
                         L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                             attribution: 'Tiles © Esri'
                         }).addTo(App.State.validationMapInstance);
                         App.State.validationMarker = L.marker(newLatLng).addTo(App.State.validationMapInstance);
                         
                         setTimeout(() => {
                             if(App.State.validationMapInstance) App.State.validationMapInstance.invalidateSize();
                         }, 50); 
                    } else {
                         console.error("Data Latitude/Longitude tidak valid:", details.lat, details.lon);
                         mapContainer.innerHTML = '<div class="flex items-center justify-center h-full text-red-500">Data Koordinat Tidak Valid</div>';
                    }
                } catch(e) { 
                    console.error("Gagal total membuat peta Leaflet:", e); 
                    mapContainer.innerHTML = '<div class="flex items-center justify-center h-full text-red-500">Gagal Memuat Peta</div>';
                }
            }
        }, 200);
    }
    
    function handleValidationStreetView(content, container, details) {
        const latLonEl = content.querySelector('#validation-lat-lon');
        const streetViewLinkEl = content.querySelector('#validation-street-view-link');
        const validationTabPanel = container.closest('.tab-content'); 
        const streetViewModal = validationTabPanel ? validationTabPanel.querySelector('#street-view-modal') : null;
        const streetViewIframe = validationTabPanel ? validationTabPanel.querySelector('#street-view-iframe') : null;
        const streetViewHeader = validationTabPanel ? validationTabPanel.querySelector('#street-view-header') : null;
        const streetViewCloseButton = validationTabPanel ? validationTabPanel.querySelector('#street-view-close-button') : null;

        if (latLonEl && streetViewLinkEl) {
            if (details.lat && details.lon && parseFloat(details.lat) !== 0) {
                latLonEl.textContent = `${details.lat.toFixed(6)}, ${details.lon.toFixed(6)}`;
                streetViewLinkEl.classList.remove('hidden');
            } else {
                latLonEl.textContent = 'Koordinat tidak valid';
                streetViewLinkEl.classList.add('hidden');
            }
        }
        
        if (streetViewLinkEl && streetViewModal && streetViewIframe && streetViewHeader && streetViewCloseButton) {
            const handleValidationStreetViewClick = (e) => {
                e.preventDefault(); e.stopPropagation();
                if (details.lat && details.lon && parseFloat(details.lat) !== 0) {
                    const lat = details.lat;
                    const lon = details.lon;
                    const streetViewUrl = `https://www.google.com/maps/embed/v1/streetview?location=${lat},${lon}&key=${GOOGLE_API_KEY}`;
                    streetViewIframe.src = streetViewUrl; 
                    streetViewModal.classList.remove('hidden'); 
                    streetViewModal.style.left = '';
                    streetViewModal.style.top = '';
                    streetViewModal.style.right = ''; 
                } else {
                    alert('Koordinat tidak valid untuk Street View.');
                }
            };
            const closeValidationStreetView = () => {
                streetViewModal.classList.add('hidden');
                streetViewIframe.src = ""; 
                streetViewModal.style.left = '';
                streetViewModal.style.top = '';
                streetViewModal.style.right = '';
            };
        
            let isDragging = false, offsetX, offsetY;
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

            streetViewLinkEl.removeEventListener('click', streetViewLinkEl.__handler);
            streetViewCloseButton.removeEventListener('click', streetViewCloseButton.__handler);
            streetViewHeader.removeEventListener('mousedown', streetViewHeader.__handler);

            streetViewLinkEl.__handler = handleValidationStreetViewClick;
            streetViewCloseButton.__handler = closeValidationStreetView;
            streetViewHeader.__handler = onMouseDown;
            
            streetViewLinkEl.addEventListener('click', handleValidationStreetViewClick);
            streetViewCloseButton.addEventListener('click', closeValidationStreetView);
            streetViewHeader.addEventListener('mousedown', onMouseDown);
        }
    }

    async function refreshValidationQueue(resetPanel = false) {
        const queueListDiv = document.getElementById('validation-queue-list');
        const container = queueListDiv?.closest('#interactive-validation-container');
        
        if (!queueListDiv || !container) {
             console.error("Tidak dapat menemukan elemen #validation-queue-list atau #interactive-validation-container saat refresh.");
             return;
        }
        if (resetPanel) {
            const placeholder = container.querySelector('#validation-placeholder');
            const content = container.querySelector('#validation-content');
            const loading = container.querySelector('#validation-loading');
            if(placeholder) placeholder.classList.remove('hidden');
            if(content) content.classList.add('hidden');
            if(loading) loading.classList.add('hidden');
            
            App.State.currentValidationId = null;
            App.State.currentValidationDetails = null;
            
            if (App.State.validationMapInstance) { 
                try { App.State.validationMapInstance.remove(); } catch(e){}
                App.State.validationMapInstance = null; 
                App.State.validationMarker = null;
            }
        }
        queueListDiv.innerHTML = '<div class="col-span-full text-center p-4"><i class="fas fa-spinner fa-spin text-gray-400"></i> Memuat ulang daftar...</div>'; 

        try {
            const response = await fetch('/team/mapping-validation?is_ajax_list=1'); 
            if (!response.ok) {
                 let errorMsg = 'Gagal memuat ulang daftar.';
                 try {
                     const errorData = await response.json();
                     errorMsg = errorData.error || errorMsg;
                 } catch(e){}
                 throw new Error(errorMsg + ` (Status: ${response.status})`);
            }
            
            const html = await response.text();
            queueListDiv.innerHTML = html; 

            const displayedEl = document.getElementById('displayed-count');
            if(displayedEl) displayedEl.textContent = queueListDiv.querySelectorAll('.validation-queue-item').length;

            console.log(">>> Validation queue refreshed successfully.");

        } catch (error) {
            console.error("Gagal refresh:", error);
            queueListDiv.innerHTML = `<div class="col-span-full text-center text-red-500 p-4">Gagal memuat ulang daftar: ${error.message}. Coba lagi nanti.</div>`;
        }
    }
    
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
        if (petaReasonSelect) petaReasonSelect.value = '';

        const persilReasonContainer = panel.querySelector('#eval_persil_reason_container');
        const persilReasonSelect = panel.querySelector('#eval_persil_reason');
        if (persilReasonContainer) persilReasonContainer.classList.add('hidden');
        if (persilReasonSelect) persilReasonSelect.value = '';

        const rejectionContainer = panel.querySelector('#rejection_reason_container');
        if (rejectionContainer) rejectionContainer.classList.add('hidden');
        const rejectionReason = panel.querySelector('#eval_rejection_reason');
        if (rejectionReason) rejectionReason.value = '';
        const rejectionPlaceholder = panel.querySelector('#rejection_reason_placeholder');
        if (rejectionPlaceholder) rejectionPlaceholder.classList.remove('hidden');

        const validateButton = panel.querySelector('#detail-button-validate');
        const rejectButton = panel.querySelector('#detail-button-reject');
        if (validateButton) { validateButton.disabled = true; validateButton.classList.add('opacity-50', 'cursor-not-allowed'); }
        if (rejectButton) { rejectButton.disabled = true; rejectButton.classList.add('opacity-50', 'cursor-not-allowed'); }
    }    

    function handleEvaluationChange(e) {
        const evalElement = e.target.closest('.eval-input, .eval-radio, #eval_rejection_reason, #eval_peta_reason, #eval_persil_reason');
        if (!evalElement) return;

        const panel = evalElement.closest('#validation-content') || document.querySelector('#validation-content');
        if (!panel) return;

        const currentDetails = App.State.currentValidationDetails;
        if (!currentDetails) {
            console.warn("### Data detail global (App.State.currentValidationDetails) kosong, tidak bisa cek form.");
            return;
        }
        checkEvaluationForm(panel, currentDetails);
    }

    function checkEvaluationForm(panel, details) {
        if (!panel || !details) return;

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
        
        const answerKey = details.full_meter_number || details.no_meter || details.meter_number || details.nomor_meter || '';
        let meterMatch = false;
        let meterNotMatch = false;
        const currentMeter = meterInput ? meterInput.value.trim() : '';

        if (answerKey && currentMeter.length > 0) {
            if (currentMeter === String(answerKey)) {
                meterMatch = true;
                if (meterStatus) { meterStatus.textContent = 'Nomor meter cocok!'; meterStatus.classList.add('text-green-500'); }
            } else {
                if (currentMeter.length >= String(answerKey).length) {
                    meterNotMatch = true;
                    if (meterStatus) { meterStatus.textContent = 'Nomor meter tidak cocok!'; meterStatus.classList.add('text-red-500'); }
                } else {
                    if (meterStatus) { meterStatus.textContent = 'Mengetik...'; }
                }
            }
        }

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

        const hasAnyRejection = meterNotMatch || petaTidakSesuai || persilTidakSesuai;

        if (hasAnyRejection) {
            if (rejectionContainer) rejectionContainer.classList.remove('hidden');
            if (rejectionPlaceholder) rejectionPlaceholder.classList.add('hidden');
        } else {
            if (rejectionContainer) rejectionContainer.classList.add('hidden');
            if (rejectionPlaceholder) rejectionPlaceholder.classList.remove('hidden');
        }

        const isPetaSelected = typeof petaValue !== 'undefined';
        const isPersilSelected = typeof persilValue !== 'undefined';
        const isPetaReasonSelected = !petaTidakSesuai || (petaTidakSesuai && petaReasonSelect && petaReasonSelect.value.trim() !== '');
        const isPersilReasonSelected = !persilTidakSesuai || (persilTidakSesuai && persilReasonSelect && persilReasonSelect.value.trim() !== '');
        const isRejectionReasonFilled = !hasAnyRejection || (rejectionReason && rejectionReason.value.trim().length >= MIN_REJECTION_CHARS);

        if (meterMatch && petaValue === 'sesuai' && persilValue === 'sesuai') {
            validateButton.disabled = false;
            validateButton.classList.remove('opacity-50', 'cursor-not-allowed');
        }

        if (hasAnyRejection) {
            if (isPetaSelected && isPersilSelected && isPetaReasonSelected && isPersilReasonSelected && isRejectionReasonFilled) {
                rejectButton.disabled = false;
                rejectButton.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }
    }
    
    // Publikasikan fungsi-fungsi yang perlu diakses
    return {
        init: initializeValidationTab,
        lockAndLoadDetails,
        updateValidationUI,
        refreshValidationQueue,
        handleEvaluationChange,
        checkEvaluationForm,
        resetEvaluationForm,
        handleFormSubmit: null // Akan diisi oleh event listener 'submit' global
    };
})();

// ===================================================================
// ===== 7. "CREATE DATA" MODAL LOGIC =====
// ===================================================================

App.FormCreate = (() => {

    function initializePreviewMap(modalContent) {
        const mapContainer = modalContent.querySelector('#preview-map');
        const latInput = modalContent.querySelector('#latitudey_create');
        const lonInput = modalContent.querySelector('#longitudex_create');
        const mapTabButton = modalContent.querySelector('#tab-btn-map');
        const mapTabPanel = modalContent.querySelector('#tab-panel-map');
        const streetViewTabButton = modalContent.querySelector('#tab-btn-streetview');
        const streetViewTabPanel = modalContent.querySelector('#tab-panel-streetview');
        const streetViewIframe = modalContent.querySelector('#create-street-view-iframe');
        const streetViewPlaceholder = modalContent.querySelector('#create-street-view-placeholder');

        if (!mapContainer || !latInput || !lonInput || !streetViewIframe || !mapTabButton) return;
        if (mapContainer._leaflet_id) { mapContainer._leaflet_id = null; }

        const previewMap = L.map(mapContainer).setView([0.5071, 101.4478], 12);
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles © Esri'
        }).addTo(previewMap);
        let previewMarker = null;

        function updatePreviews() {
            const lat = parseFloat(latInput.value);
            const lon = parseFloat(lonInput.value);
            
            if (!isNaN(lat) && !isNaN(lon)) {
                if (previewMarker) previewMarker.remove();
                previewMarker = L.marker([lat, lon]).addTo(previewMap);
                previewMap.setView([lat, lon], 17);
                
                const streetViewUrl = `https://www.google.com/maps/embed/v1/streetview?location=${lat},${lon}&key=${GOOGLE_API_KEY}`;
                streetViewIframe.src = streetViewUrl;
                streetViewIframe.classList.remove('hidden');
                streetViewPlaceholder.classList.add('hidden');
            } else {
                if (previewMarker) { previewMarker.remove(); previewMarker = null; }
                streetViewIframe.src = '';
                streetViewIframe.classList.add('hidden');
                streetViewPlaceholder.classList.remove('hidden');
            }
        }

        latInput.addEventListener('input', updatePreviews);
        lonInput.addEventListener('input', updatePreviews);

        streetViewTabButton.addEventListener('click', () => {
            streetViewTabButton.classList.add('border-indigo-500', 'text-indigo-600', 'dark:text-indigo-400');
            streetViewTabButton.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300');
            mapTabButton.classList.remove('border-indigo-500', 'text-indigo-600', 'dark:text-indigo-400');
            mapTabButton.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300');
            streetViewTabPanel.classList.remove('hidden');
            mapTabPanel.classList.add('hidden');
        });

        mapTabButton.addEventListener('click', () => {
            mapTabButton.classList.add('border-indigo-500', 'text-indigo-600', 'dark:text-indigo-400');
            mapTabButton.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300');
            streetViewTabButton.classList.remove('border-indigo-500', 'text-indigo-600', 'dark:text-indigo-400');
            streetViewTabButton.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300');
            mapTabPanel.classList.remove('hidden');
            streetViewTabPanel.classList.add('hidden');
            setTimeout(() => previewMap.invalidateSize(), 50);
        });
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

                const oldFilename = filenameInput.value;
                if (oldFilename) {
                    fetch('/team/mapping-delete-photo', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                        body: JSON.stringify({ filename: oldFilename })
                    });
                }

                statusDiv.innerHTML = '';
                filenameInput.value = '';
                if(progressContainer) progressContainer.classList.add('hidden');
                if(progressBar) progressBar.style.width = '0%';
                if (!file) return;
                
                progressContainer.classList.remove('hidden');
                const formData = new FormData();
                formData.append('photo', file);

                const xhr = new XMLHttpRequest();
                xhr.open('POST', uploadUrl, true);
                xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                xhr.setRequestHeader('Accept', 'application/json');

                xhr.upload.onprogress = function(event) {
                    if (event.lengthComputable) {
                        const percentComplete = Math.round((event.loaded / event.total) * 100);
                        progressBar.style.width = percentComplete + '%';
                        statusDiv.textContent = `Mengunggah... ${percentComplete}%`;
                    }
                };

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
        const checkUrlBase = 'master-pelanggan/check/';

        if (!idpelInput || !statusIconDiv || !statusMessageEl || !submitButton || !ketSurveyTextarea) {
            console.error("Elemen form create tidak lengkap untuk validasi live IDPEL.");
            return;
        }

        const updateIdpelStatusUI = (isLoading, exists, isActive, message) => {
            statusMessageEl.textContent = message || '';
            statusIconDiv.classList.toggle('hidden', !isLoading && !exists && message === '');
            statusMessageEl.classList.remove('text-green-600', 'text-red-600', 'text-yellow-600', 'text-gray-500');

            if (isLoading) {
                statusIconDiv.innerHTML = '<i class="fas fa-spinner fa-spin text-gray-400"></i>';
                statusIconDiv.classList.remove('hidden');
                statusMessageEl.classList.add('text-gray-500');
                submitButton.disabled = true;
                ketSurveyTextarea.readOnly = true;
                ketSurveyTextarea.value = '';
            } else if (exists && isActive) {
                statusIconDiv.innerHTML = '<i class="fas fa-check-circle text-green-500"></i>';
                statusIconDiv.classList.remove('hidden');
                statusMessageEl.classList.add('text-green-600');
                submitButton.disabled = false;
                ketSurveyTextarea.readOnly = false;
                ketSurveyTextarea.value = '';
                ketSurveyTextarea.placeholder = '';
            } else if (exists && !isActive) {
                statusIconDiv.innerHTML = '<i class="fas fa-exclamation-triangle text-yellow-500"></i>';
                statusIconDiv.classList.remove('hidden');
                statusMessageEl.classList.add('text-yellow-600');
                statusMessageEl.textContent = message || 'Pelanggan ditemukan tapi status tidak aktif.';
                submitButton.disabled = false;
                ketSurveyTextarea.readOnly = true;
                ketSurveyTextarea.value = 'Pelanggan Non Aktif';
                ketSurveyTextarea.placeholder = '';
            } else if (!exists && message) {
                statusIconDiv.innerHTML = '<i class="fas fa-times-circle text-red-500"></i>';
                statusIconDiv.classList.remove('hidden');
                statusMessageEl.classList.add('text-red-600');
                submitButton.disabled = true;
                ketSurveyTextarea.readOnly = true;
                ketSurveyTextarea.value = '';
                ketSurveyTextarea.placeholder = 'ID Pelanggan tidak valid';
            } else {
                statusIconDiv.classList.add('hidden');
                statusMessageEl.classList.add('text-gray-500');
                submitButton.disabled = true;
                ketSurveyTextarea.readOnly = true;
                ketSurveyTextarea.value = '';
                ketSurveyTextarea.placeholder = 'Masukkan ID Pelanggan untuk mengecek status';
            }

             if (ketSurveyTextarea.readOnly) {
                ketSurveyTextarea.classList.add('bg-gray-100', 'dark:bg-gray-800', 'cursor-not-allowed');
             } else {
                ketSurveyTextarea.classList.remove('bg-gray-100', 'dark:bg-gray-800', 'cursor-not-allowed');
             }
        };

        idpelInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            const idpelValue = idpelInput.value.trim();

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

            debounceTimer = setTimeout(() => {
                updateIdpelStatusUI(true, null, null, 'Mengecek...');
                fetch(`${checkUrlBase}${idpelValue}`)
                    .then(response => response.json().then(data => ({ ok: response.ok, status: response.status, data })))
                    .then(({ ok, status, data }) => {
                        if (!ok) throw new Error(data.message || `Error ${status}`);
                        updateIdpelStatusUI(false, data.exists, data.is_active, data.message);
                    })
                    .catch(error => {
                        console.error("Error cek IDPEL:", error);
                        updateIdpelStatusUI(false, false, null, `Gagal mengecek: ${error.message}`);
                    });
            }, 800);
        });
        updateIdpelStatusUI(false, null, null, '');
    }

    return {
        initializePreviewMap,
        initializePhotoUpload,
        initializeCreateFormValidation
    };
})();

// ===================================================================
// ===== 8. EVENT LISTENERS & INITIALIZATION =====
// ===================================================================

App.Listeners = (() => {

    function init() {
        document.addEventListener('DOMContentLoaded', () => {
            // --- Inisialisasi Variabel DOM ---
            const elements = {
                dashboardUrl: document.body.dataset.dashboardUrl,
                sidebar: document.getElementById('sidebarMenu'),
                toggleBtn: document.getElementById('sidebarToggle'),
                tabsHeader: document.getElementById('tabs-header'),
                tabsContent: document.getElementById('tabs-content'),
                scrollLeftBtn: document.getElementById('tab-scroll-left'),
                scrollRightBtn: document.getElementById('tab-scroll-right'),
                mainModal: document.getElementById('main-modal'),
                modalContent: document.getElementById('modal-content'),
                imageModal: document.getElementById('image-viewer-modal'),
                imageModalImg: document.getElementById('image-viewer-img'),
                imageModalClose: document.getElementById('image-viewer-close'),
                imageModalOverlay: document.getElementById('image-viewer-overlay'),
                modalMeterInputContainer: document.getElementById('modal-meter-input-container'),
                modalMeterInput: document.getElementById('modal-meter-input')
            };

            // --- Inisialisasi Modul ---
            App.Tabs.init(elements);
            App.Modal.init(elements);
            
            // --- Toggle Sidebar ---
            if (elements.sidebar && elements.toggleBtn) {
                elements.toggleBtn.addEventListener('click', () => elements.sidebar.classList.toggle('sidebar-collapsed'));
            }

            // --- Listener Scroll Tab ---
            elements.scrollLeftBtn.addEventListener('click', () => elements.tabsHeader.scrollBy({ left: -200, behavior: 'smooth' }));
            elements.scrollRightBtn.addEventListener('click', () => elements.tabsHeader.scrollBy({ left: 200, behavior: 'smooth' }));
            window.addEventListener('resize', App.Tabs.updateScrollButtons);

            // --- Listener Popstate (Tombol Back/Forward Browser) ---
            window.addEventListener('popstate', (e) => handlePopstate(e, elements.tabsHeader, elements.dashboardUrl));

            // --- Mencegah Drag-Drop Default ---
            window.addEventListener("dragover", e => e.preventDefault(), false);
            window.addEventListener("drop", e => e.preventDefault(), false);
            
            // --- Listener Global ---
            document.addEventListener('click', e => handleGlobalClick(e, elements));
            document.addEventListener('submit', handleGlobalSubmit);
            document.addEventListener('input', handleGlobalInput);
            document.addEventListener('change', App.Validation.handleEvaluationChange);

            // --- Inisialisasi Tab Awal ---
            App.Tabs.initializeDashboardTab();
        });
    }

    function handleGlobalClick(e, elements) {
        const modalLink = e.target.closest('[data-modal-link]');
        const deleteButton = e.target.closest('[data-delete-url]');
        const modalCloseButton = e.target.closest('[data-modal-close]');
        const clearButton = e.target.closest('#clear-search-button');
        const targetLink = e.target.closest('a');
        const isActionOrForm = e.target.closest('form[data-custom-handler="invalidate-action"]') ||
                               e.target.closest('form[data-custom-handler="promote-action"]') || 
                               e.target.closest('[data-delete-url]');

        if (isActionOrForm) return; 

        const dataRow = e.target.closest('.data-row-clickable');
        if (dataRow) {
            e.preventDefault();
            App.Kddk.handleDataRowClick(dataRow);
            return;
        }

        const imageZoomButton = e.target.closest('.image-zoom-trigger');
        if (imageZoomButton && elements.imageModal) {
            e.preventDefault();
            const imgElement = imageZoomButton.querySelector('img');
            const zoomType = imageZoomButton.dataset.zoomType;
            App.Modal.showImage(imgElement, zoomType);
            return;
        }
        
        const validationQueueButton = e.target.closest('[data-validation-queue-id]');
        if (validationQueueButton) {
            e.preventDefault();
            const id = validationQueueButton.dataset.validationQueueId;
            App.Validation.lockAndLoadDetails(id, validationQueueButton);
            return;
        }
        
        const refreshButton = e.target.closest('#refresh-queue-list');
        if (refreshButton) {
            e.preventDefault();
            App.Validation.refreshValidationQueue(true);
            return;
        }

        if (modalLink) {
            e.preventDefault();
            App.Modal.open(modalLink.href);
            return;
        }
        
        if (deleteButton) {
            handleDeleteClick(deleteButton);
            return;
        }

        if (modalCloseButton) {
            App.Modal.close();
            return;
        }
        
        if (clearButton) {
            const searchForm = clearButton.closest('form');
            const searchInput = searchForm.querySelector('input[name="search"]');
            searchInput.value = '';
            clearButton.classList.add('hidden');
            const activeTabName = App.Utils.getActiveTabName(elements.tabsHeader);
            if (activeTabName) App.Tabs.loadTabContent(activeTabName, searchForm.action);
            return;
        }

        if (e.target === elements.mainModal) {
            App.Modal.close();
            return;
        }

        if (!targetLink) return;

        if (targetLink.closest('#tabs-content') &&
            !targetLink.hasAttribute('data-tab-link') && 
            targetLink.getAttribute('target') !== '_blank' &&
            targetLink.id !== 'google-street-view-link' &&
            targetLink.id !== 'validation-street-view-link')
        {
            e.preventDefault();
            const activeTabName = App.Utils.getActiveTabName(elements.tabsHeader);
            if (activeTabName) App.Tabs.loadTabContent(activeTabName, targetLink.href);
            return;
        }

        if (targetLink.hasAttribute('data-tab-link')) {
            e.preventDefault();
            const url = targetLink.href;
            const tabName = targetLink.dataset.tabLink;
            const existingTab = elements.tabsHeader.querySelector(`[data-tab-name="${tabName}"]`);
            if (existingTab) {
                App.Tabs.activateTab(tabName, url, true);
            } else {
                const isClosable = targetLink.dataset.closable !== 'false';
                App.Tabs.createTab(tabName, url, isClosable, true);
            }
            return;
        }
    }

    function handleDeleteClick(deleteButton) {
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
                    const tabNameToRefresh = App.Utils.getActiveTabName(document.getElementById('tabs-header'));
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
                        if (refreshUrl) App.Tabs.loadTabContent(tabNameToRefresh, refreshUrl);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(error.message || 'Terjadi kesalahan saat menghapus data.');
            });
        }
    }

    function handleGlobalSubmit(e) {
        const searchForm = e.target.closest('form[id*="-search-form"]');
        const validationForm = e.target.closest('#detail-form-validate, #detail-form-reject');
        const invalidateForm = e.target.closest('form[data-custom-handler="invalidate-action"]');
        const formInModal = e.target.closest('#modal-content form');
        const promoteForm = e.target.closest('form[data-custom-handler="promote-action"]');

        if (searchForm) {
            e.preventDefault();
            clearTimeout(App.State.searchDebounceTimer);
            const params = new URLSearchParams(new FormData(searchForm)).toString();
            const url = `${searchForm.action}?${params}`;
            App.Tabs.loadTabContent(App.Utils.getActiveTabName(document.getElementById('tabs-header')), url);
            return;
        }

        if (invalidateForm) {
            e.preventDefault();
            handleInvalidateSubmit(invalidateForm);
            return;
        }

        if (promoteForm) {
            e.preventDefault();
            handlePromoteSubmit(promoteForm);
            return;
        }

        if (formInModal) {
            if (formInModal.hasAttribute('data-custom-handler')) return;
            e.preventDefault();
            handleModalFormSubmit(formInModal);
            return;
        }

        if (validationForm) {
            e.preventDefault();
            handleValidationFormSubmit(validationForm);
            return;
        }
    }

    function handleInvalidateSubmit(form) {
        const onConfirmAction = () => {
            const originalButton = form.querySelector('button[type="submit"]');
            const originalText = originalButton.textContent;
            originalButton.textContent = 'Memproses...';
            originalButton.disabled = true;
            originalButton.classList.add('opacity-50', 'cursor-not-allowed');

            fetch(form.action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), 'Accept': 'application/json' },
                body: new FormData(form)
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (response.ok) {
                    if (contentType && contentType.indexOf("application/json") !== -1) return response.json();
                    return { message: 'Aksi invalidate berhasil diproses.' }; 
                } else {
                    if (contentType && contentType.indexOf("application/json") !== -1) return response.json().then(err => { throw err; });
                    throw new Error('Sesi Anda mungkin telah habis. Halaman akan dimuat ulang.');
                }
            })
            .then(data => {
                originalButton.textContent = originalText;
                originalButton.disabled = false;
                originalButton.classList.remove('opacity-50', 'cursor-not-allowed');
                
                const successMessage = data.message || 'Aksi berhasil diproses.';
                const tabNameToRefresh = App.Utils.getActiveTabName(document.getElementById('tabs-header'));
                
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
                            App.Tabs.loadTabContent(tabNameToRefresh, bustUrl.toString(), () => {
                                App.Utils.displayNotification('success', successMessage);
                            }); 
                        }
                    }
                }
            })
            .catch(error => {
                originalButton.textContent = originalText;
                originalButton.disabled = false;
                originalButton.classList.remove('opacity-50', 'cursor-not-allowed');
                console.error('Error Invalidate:', error);
                if (error.message.includes('Sesi Anda mungkin telah habis')) {
                    alert(error.message);
                    window.location.reload();
                } else {
                    App.Utils.displayNotification('error', error.message || 'Terjadi kesalahan.');
                }
            });
        };
        App.Utils.showCustomConfirm('Konfirmasi Invalidate', 'Anda yakin ingin mengembalikan data ini ke antrian validasi?', onConfirmAction);
    }

    function handlePromoteSubmit(form) {
        const onConfirmAction = () => {
            const originalButton = form.querySelector('button[type="submit"]');
            const originalInnerHTML = originalButton.innerHTML;
            originalButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            originalButton.disabled = true;
            
            fetch(form.action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), 'Accept': 'application/json' },
                body: new FormData(form)
            })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 419) throw new Error('Sesi telah kedaluwarsa. Halaman akan dimuat ulang.');
                    return response.json().then(err => { 
                        throw new Error(err.message || `Gagal: ${response.status}`);
                    }).catch(() => {
                        throw new Error(`Aksi Gagal. Status: ${response.status}.`);
                    });
                }
                return response.json(); 
            })
            .then(data => {
                originalButton.innerHTML = originalInnerHTML;
                originalButton.disabled = false;
                const successMessage = data.message || 'Data berhasil dipromosikan.';
                const tabNameToRefresh = App.Utils.getActiveTabName(document.getElementById('tabs-header'));

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
                            App.Tabs.loadTabContent(tabNameToRefresh, bustUrl.toString(), () => {
                                App.Utils.displayNotification('success', successMessage);
                            }); 
                        }
                    }
                }
            })
            .catch(error => {
                originalButton.innerHTML = originalInnerHTML;
                originalButton.disabled = false;
                console.error('Error Promote:', error);
                if (error.message.includes('Sesi telah kedaluwarsa')) {
                    alert(error.message);
                    window.location.reload();
                } else {
                    App.Utils.displayNotification('error', error.message || 'Gagal mempromosikan data.');
                }
            });
        };
        App.Utils.showCustomConfirm('Konfirmasi Promosi Data', 'Jadikan data ini sebagai data VALID (AKTIF)?', onConfirmAction);
    }

    function handleModalFormSubmit(form) {
        const submitButton = form.querySelector('button[type="submit"]');
        const originalButtonText = submitButton ? submitButton.innerHTML : 'Simpan';
        if(submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
        }

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), 'Accept': 'application/json' },
            body: new FormData(form)
        })
        .then(response => {
            if (!response.ok) return response.json().then(err => { throw err; });
            return response.json();
        })
        .then(data => {
            if (data.message) {
                App.Modal.close();
                App.Utils.displayNotification('success', data.message);

                let tabNameToRefresh = 'Dashboard';
                if (form.id === 'create-mapping-form') tabNameToRefresh = 'Data Mapping Pelanggan';
                else if (form.id === 'edit-user-form') tabNameToRefresh = 'Daftar Pengguna';

                const tabButton = document.querySelector(`#tabs-header .tab-button[data-tab-name="${tabNameToRefresh}"]`);
                if(tabButton) App.Tabs.loadTabContent(tabNameToRefresh, tabButton.dataset.url || tabButton.href);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if(submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }

            if (error.errors) {
                let errorDiv = null;
                if (form.id === 'create-mapping-form') errorDiv = form.querySelector('#create-mapping-errors');
                else if (form.id === 'edit-user-form') errorDiv = form.querySelector('#edit-user-errors');

                if (errorDiv) {
                    let errorList = '<ul>';
                    for (const key in error.errors) {
                        errorList += `<li class="text-sm">- ${error.errors[key][0]}</li>`;
                    }
                    errorList += '</ul>';
                    errorDiv.innerHTML = errorList;
                    errorDiv.classList.remove('hidden');
                    document.getElementById('modal-content').scrollTop = 0;
                } else {
                    alert('Validasi gagal. Cek console untuk detail.');
                }
            } else {
                alert(error.message || 'Terjadi kesalahan.');
            }
        });
    }

    function handleValidationFormSubmit(form) {
        const isReject = form.id === 'detail-form-reject';
        if (isReject) {
            if (!confirm('Anda yakin ingin MENOLAK data ini?')) {
                return;
            }
        }

        const originalButton = form.querySelector('button[type="submit"]');
        const originalText = originalButton.textContent;
        originalButton.textContent = 'Memproses...';
        originalButton.disabled = true;
        originalButton.classList.add('opacity-50', 'cursor-not-allowed');

        const panel = form.closest('#validation-content');
        const formData = new FormData(form);

        if (panel) {
            const evalData = {
                eval_peta: panel.querySelector('input[name="eval_peta"]:checked')?.value || null,
                eval_peta_reason: panel.querySelector('#eval_peta_reason')?.value || null,
                eval_persil: panel.querySelector('input[name="eval_persil"]:checked')?.value || null,
                eval_persil_reason: panel.querySelector('#eval_persil_reason')?.value || null,
                eval_meter_input: panel.querySelector('#eval_meter_input')?.value || null,
            };
            const rejectionReason = panel.querySelector('#eval_rejection_reason')?.value || ''; 
            formData.append('validation_data', JSON.stringify(evalData));
            formData.append('validation_notes', rejectionReason);
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
        .then(async response => {
            const status = response.status;
            let data = null;
            const responseText = await response.text(); 
            try {
                 if (responseText) data = JSON.parse(responseText);
            } catch(e) {
                console.error("Gagal parsing JSON. Response text:", responseText);
                throw new Error(`Respons JSON tidak valid. Status: ${response.status}`);
            }
            if (!response.ok) {
                if (data && (data.error || data.message)) throw new Error(data.error || data.message);
                throw new Error(`Gagal memproses. Status HTTP: ${status}.`);
            }
            if (data) return data;
            return { action_type: 'validate', status_message: 'Operasi berhasil.' };
        })
        .then(data => {
            originalButton.textContent = originalText;
            originalButton.disabled = false;
            originalButton.classList.remove('opacity-50', 'cursor-not-allowed');
            
            App.Validation.checkEvaluationForm(form.closest('#validation-content'), App.State.currentValidationDetails); 
            
            let notificationType = data.action_type || (isReject ? 'reject' : 'success');
            const successMessage = data.status_message || (notificationType === 'reject' ? 'Penolakan berhasil.' : 'Validasi berhasil.');

            App.Utils.displayNotification(notificationType, successMessage);
            
            if (data.queue_empty) {
                const container = form.closest('#interactive-validation-container');
                container.querySelector('#validation-content').classList.add('hidden');
                container.querySelector('#validation-placeholder').classList.remove('hidden');
                App.State.currentValidationId = null;
                App.State.currentValidationDetails = null;
            } else {
                App.State.currentValidationId = data.currentItemId;
                App.State.currentValidationDetails = data.details;
                const container = form.closest('#interactive-validation-container');
                App.Validation.updateValidationUI(container, data.details);
                container.querySelectorAll('.validation-queue-item.bg-indigo-100').forEach(btn => {
                    btn.classList.remove('bg-indigo-100', 'dark:bg-indigo-900');
                });
            }
            App.Validation.refreshValidationQueue(false);
        })
        .catch(error => {
            originalButton.textContent = originalText;
            originalButton.disabled = false;
            originalButton.classList.add('opacity-50', 'cursor-not-allowed');
            console.error('Validation/Rejection Error:', error);
            App.Utils.displayNotification('error', error.message || 'Terjadi kesalahan.');
        });
    }

    function handleGlobalInput(e) {
        const searchInput = e.target.closest('form[id*="-search-form"] input[name="search"]');
        if (searchInput) {
            const searchForm = searchInput.closest('form');
            const clearButton = searchForm.querySelector('#clear-search-button');
            if (clearButton) clearButton.classList.toggle('hidden', searchInput.value.length === 0);
            
            clearTimeout(App.State.searchDebounceTimer);
            App.State.searchDebounceTimer = setTimeout(() => {
                const params = new URLSearchParams(new FormData(searchForm)).toString();
                const url = `${searchForm.action}?${params}`;
                App.Tabs.loadTabContent(App.Utils.getActiveTabName(document.getElementById('tabs-header')), url);
            }, 900);
        }
    }

    function handlePopstate(e, tabsHeader, dashboardUrl) {
        const state = e.state;
        if (state && state.tab) {
            const tabName = state.tab;
            const url = window.location.href;
            const existingTab = tabsHeader.querySelector(`[data-tab-name="${tabName}"]`);
            if (existingTab) {
                App.Tabs.activateTab(tabName, url, false);
            } else {
                App.Tabs.createTab(tabName, url, true, false);
            }
        } else if (tabsHeader.children.length > 0) {
            const dashboardTab = tabsHeader.querySelector('[data-tab-name="Dashboard"]');
            if (dashboardTab) {
                App.Tabs.activateTab('Dashboard', dashboardUrl, false);
            }
        } else {
            App.Tabs.initializeDashboardTab();
        }
    }

    return {
        init
    };
})();

// --- Jalankan Inisialisasi ---
App.Listeners.init();