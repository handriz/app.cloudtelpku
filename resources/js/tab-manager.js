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

    // --- Toggle Sidebar ---
    if (sidebar && toggleBtn) {
        toggleBtn.addEventListener('click', () => sidebar.classList.toggle('sidebar-collapsed'));
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
        if (targetLink.closest('#tabs-content') && !targetLink.hasAttribute('data-tab-link')) {
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
    // ===== SATU EVENT LISTENER UNTUK SEMUA SUBMIT FORM =====
    // ===================================================================
    document.addEventListener('submit', function(e) {
        const searchForm = e.target.closest('form[id*="-search-form"]');
        const formInModal = e.target.closest('#modal-content form');

        // A. Submit Form Pencarian
        if (searchForm) {
            e.preventDefault();
            clearTimeout(searchDebounceTimer);
            const params = new URLSearchParams(new FormData(searchForm)).toString();
            const url = `${searchForm.action}?${params}`;
            loadTabContent(getActiveTabName(), url);
            return;
        }

        // B. Submit Form di Dalam Modal (Edit User, dll)
        if (formInModal) {
            if (formInModal.hasAttribute('data-custom-handler')) {
                return;
            }
            e.preventDefault();
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
                    const tabNameToRefresh = 'Daftar Pengguna';
                    const tabContent = document.getElementById(`${tabNameToRefresh}-content`);
                    if (tabContent) {
                        const searchFormInTab = tabContent.querySelector('form[id*="-search-form"]');
                        let refreshUrl;
                        if (searchFormInTab) {
                            const params = new URLSearchParams(new FormData(searchFormInTab)).toString();
                            refreshUrl = `${searchFormInTab.action}?${params}`;
                        } else {
                            const tabButton = document.querySelector(`#tabs-header .tab-button[data-tab-name="${tabNameToRefresh}"]`);
                            if (tabButton) refreshUrl = tabButton.dataset.url || tabButton.href;
                        }
                        if (refreshUrl) loadTabContent(tabNameToRefresh, refreshUrl);
                    }
                    alert(data.message);
                } else if (data.errors) {
                    const errorDiv = formInModal.querySelector('#edit-user-errors');
                    if (errorDiv) {
                        let errorList = '<ul>';
                        for (const key in data.errors) {
                            errorList += `<li class="text-sm">- ${data.errors[key][0]}</li>`;
                        }
                        errorList += '</ul>';
                        errorDiv.innerHTML = errorList;
                        errorDiv.classList.remove('hidden');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(error.message || 'Terjadi kesalahan saat memproses data.');
            });
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
            tabContent.innerHTML = html;
            const searchInput = tabContent.querySelector('form[id*="-search-form"] input[name="search"]');
            if (searchInput && searchInput.value.length > 0) {
                const clearButton = tabContent.querySelector('#clear-search-button');
                if (clearButton) clearButton.classList.remove('hidden');
            }

            const mapContainer = tabContent.querySelector('#map');
            if (mapContainer) {
                initializeMap(mapContainer);
            }
            updateScrollButtons();
            
            const cleanUrl = new URL(url, window.location.origin);
            cleanUrl.searchParams.delete('is_ajax');
            history.pushState({ tab: tabName }, '', cleanUrl.toString());
        })
        .catch(error => {
            tabContent.innerHTML = `<div class="p-4 text-red-500">Gagal memuat konten.</div>`;
            console.error('Error loading tab content:', error);
        });

    }

    function initializeMap(mapContainer) {
        if (!mapContainer) return;

        if (window.mapInstance) {
            window.mapInstance.remove();
            window.mapInstance = null;
        }

        const map = L.map(mapContainer).setView([0.5071, 101.4478], 12);
        window.mapInstance = map;

        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri'
        }).addTo(map);

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
        
        const activeTabContent = mapContainer.closest('.tab-content');
        const searchInput = activeTabContent.querySelector('#mapping-search-form input[name="search"]');
        const searchValue = searchInput ? searchInput.value : '';

        let coordinatesUrl = new URL('/team/mapping-coordinates', window.location.origin);
        if (searchValue) {
            coordinatesUrl.searchParams.set('search', searchValue);
        }
        
        fetch(coordinatesUrl.toString())
            .then(response => response.json())
            .then(data => {
                const allMarkers = L.featureGroup();
                let markerToOpen = null;

                // KONDISI 1: Menampilkan semua marker (tanpa pencarian)
                if (data.all && data.all.length > 0) {
                    data.all.forEach(point => {
                        // Gunakan ikon default Leaflet (biru)
                        const marker = L.marker([point.latitudey, point.longitudex]);
                        marker.bindPopup(`<b>ID Pelanggan:</b> ${point.idpelanggan}`);
                        allMarkers.addLayer(marker);
                    });
                }

                // KONDISI 2: Menampilkan hasil pencarian (merah) dan terdekat (biru)
                if (data.searched && data.searched.length > 0) {
                    data.searched.forEach(point => {
                        const marker = L.marker([point.latitudey, point.longitudex], { icon: redIcon });
                        marker.bindPopup(`<b>IDpel (Dicari):</b> ${point.idpelanggan}`);
                        allMarkers.addLayer(marker);

                        if (!markerToOpen) {
                            markerToOpen = marker;
                         }
                    });
                }
                if (data.nearby && data.nearby.length > 0) {
                    data.nearby.forEach(point => {
                        const marker = L.marker([point.latitudey, point.longitudex], { icon: blueIcon });
                        marker.bindPopup(`<b>IDpel (Terdekat):</b> ${point.idpelanggan}`);
                        allMarkers.addLayer(marker);
                    });
                }
                
                if (allMarkers.getLayers().length > 0) {
                    allMarkers.addTo(map);
                    
                    // Jika hanya ada satu hasil pencarian (dan tidak ada data sekitar), langsung zoom ke sana
                    if (searchValue && data.searched && data.searched.length === 1 && (!data.nearby || data.nearby.length === 0)) {
                        map.setView(markerToOpen.getLatLng(), 18); // Zoom ke titik
                        markerToOpen.openPopup(); // Buka popup
                    } 
                    // Jika ada banyak hasil (hasil cari + data sekitar)
                    else if (markerToOpen) {
                        map.fitBounds(allMarkers.getBounds().pad(0.1));
                        // Kita akan buka popup setelah jeda singkat untuk memastikan fitBounds selesai
                        setTimeout(() => {
                            markerToOpen.openPopup();
                        }, 500); // Jeda 500ms
                    }
                    // Jika tidak ada pencarian, cukup tampilkan semua
                    else {
                        map.fitBounds(allMarkers.getBounds().pad(0.1));
                    }
                }
            })
            .catch(error => console.error('Error fetching map data:', error));
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
                }, 150);

                if (window.UploadInitializers && typeof window.UploadInitializers.initializeUploadForm === 'function') {
                    window.UploadInitializers.initializeUploadForm();
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

        if (!mapContainer || !latInput || !lonInput) return;

        if (mapContainer._leaflet_id) { mapContainer._leaflet_id = null; }

        const previewMap = L.map(mapContainer).setView([0.5071, 101.4478], 12);
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri'
        }).addTo(previewMap);

        let previewMarker = null;

        function updateMarker() {
            const lat = parseFloat(latInput.value);
            const lon = parseFloat(lonInput.value);
            if (!isNaN(lat) && !isNaN(lon)) {
                if (previewMarker) {
                    previewMarker.remove();
                }
                previewMarker = L.marker([lat, lon]).addTo(previewMap);
                previewMap.setView([lat, lon], 17);
            }
        }

        latInput.addEventListener('input', updateMarker);
        lonInput.addEventListener('input', updateMarker);
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

    // --- Panggil Inisialisasi di Akhir ---
    initializeDashboardTab();
});

