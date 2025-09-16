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

        // A. Link Paginasi
        if (targetLink.closest('[data-pagination-container]')) {
            e.preventDefault();
            const activeTabName = getActiveTabName();
            if (activeTabName) {
                loadTabContent(activeTabName, targetLink.href);
            }
            return;
        }

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
            // Jika form punya handler khusus di file lain (seperti upload), ABAIKAN.
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
            }, 400);
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
        tabButton.className = 'tab-button flex items-center px-4 py-2 font-medium text-sm whitespace-nowrap rounded-t-lg transition-colors duration-150 ease-in-out text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700';
        tabButton.dataset.tabName = tabName;
        tabButton.onclick = (e) => {
            e.preventDefault();
            activateTab(tabName, url, true);
        };

        if (isClosable) {
            const closeButton = document.createElement('i');
            closeButton.className = 'tab-close-button fas fa-times text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 ml-2';
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
        tabContent.innerHTML = `<div class="p-8 text-center text-gray-500">Memuat...</div>`;
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
            updateScrollButtons();
        })
        .catch(error => {
            tabContent.innerHTML = `<div class="p-4 text-red-500">Gagal memuat konten.</div>`;
            console.error('Error loading tab content:', error);
        });
    }

    function activateTab(tabName, url, pushHistory = true) {
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active', 'bg-white', 'dark:bg-gray-800', 'text-indigo-600');
            btn.classList.add('text-gray-500', 'hover:bg-gray-200', 'dark:hover:bg-gray-700');
        });
        document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));

        const activeTabButton = tabsHeader.querySelector(`[data-tab-name="${tabName}"]`);
        const activeTabContent = document.getElementById(`${tabName}-content`);
        
        if (activeTabButton) {
            activeTabButton.classList.remove('text-gray-500', 'hover:bg-gray-200', 'dark:hover:bg-gray-700');
            activeTabButton.classList.add('active', 'bg-white', 'dark:bg-gray-800', 'text-indigo-600');
            activeTabButton.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
        if (activeTabContent) {
            activeTabContent.classList.remove('hidden');
        }
        if (pushHistory) {
            const newUrl = new URL(url, window.location.origin);
            newUrl.searchParams.delete('is_ajax');
            history.pushState({ tab: tabName }, '', newUrl.toString());
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
        if (!mainModal || !modalContent) return;
        modalContent.innerHTML = '<div class="text-center p-8">Memuat...</div>';
        mainModal.classList.remove('hidden');

        let fetchUrl = new URL(url, window.location.origin);
        fetchUrl.searchParams.set('is_ajax', '1');

        fetch(fetchUrl)
            .then(response => response.text())
            .then(html => {
                modalContent.innerHTML = html;
                if (window.UploadInitializers && typeof window.UploadInitializers.initializeUploadForm === 'function') {
                    window.UploadInitializers.initializeUploadForm();
                }
            })
            .catch(error => {
                modalContent.innerHTML = '<div class="text-center p-8 text-red-500">Gagal memuat konten.</div>';
                console.error("Fetch Error:", error);
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