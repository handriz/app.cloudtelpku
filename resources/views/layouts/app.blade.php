<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .sidebar-collapsed {
                width: 70px !important;
            }
            .sidebar-collapsed .menu-text {
                display: none;
            }
            html, body {
                width: 100%;
            }
            .flex {
            }
            .tab-button.active {
                border-color: #4f46e5;
                color: #4f46e5;
            }
            /* KUNCI PERBAIKAN CSS */
            .tabs-header-wrapper {
                display: flex;
                align-items: center;
                overflow: hidden;
            }
            #tabs-header {
                flex-grow: 1;
                flex-shrink: 1;
                display: flex;
                flex-wrap: nowrap;
                overflow-x: auto;
                scrollbar-width: none;
                -ms-overflow-style: none;
                white-space: nowrap; /* Penting untuk menjaga tab pada satu baris */
            }
            #tabs-header::-webkit-scrollbar {
                display: none;
            }
            .tab-scroll-button {
                flex-shrink: 0;
                padding: 0.5rem;
                cursor: pointer;
            }
            .tab-close-button {
                margin-left: 0.5rem;
                font-size: 0.75rem;
                cursor: pointer;
            }
        </style>
    </head>
    <body class="font-sans antialiased bg-gray-100 dark:bg-gray-900">
        <div class="flex min-h-screen bg-gray-100 dark:bg-gray-900">

            {{-- Sidebar Kiri --}}
            @include('layouts.sidebar')

            <div class="flex-1 flex flex-col min-w-0 transition-all duration-300 ease-in-out">
                {{-- Navigasi Atas --}}
                @include('layouts.navigation')

                {{-- Area Konten Halaman --}}
                <main class="flex-1 overflow-y-auto pt-6 pb-12">
                    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                        {{-- Wadah untuk Tab Dinamis --}}
                        <div id="tab-container" class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                            
                            <div class="tabs-header-wrapper border-b border-gray-200 dark:border-gray-700">
                                <button id="tab-scroll-left" class="tab-scroll-button bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-l-lg hidden">
                                    <i class="fas fa-chevron-left text-gray-700 dark:text-gray-200"></i>
                                </button>
                                
                                <div id="tabs-header" class="p-4">
                                    {{-- Tab headers go here --}}
                                </div>
                                
                                <button id="tab-scroll-right" class="tab-scroll-button bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-r-lg hidden">
                                    <i class="fas fa-chevron-right text-gray-700 dark:text-gray-200"></i>
                                </button>
                            </div>

                            <div id="tabs-content" class="p-4">
                                {{-- Konten tab akan ditambahkan di sini oleh JavaScript --}}
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
        <script>
            // KUNCI PERUBAHAN: Tentukan rute dashboard secara dinamis berdasarkan peran
            @php
                $dashboardRoute = 'dashboard'; // Rute default jika tidak ada yang cocok
                $userRole = Auth::check() ? Auth::user()->role->name : null;

                switch ($userRole) {
                    case 'admin':
                        $dashboardRoute = 'admin.dashboard';
                        break;
                    case 'tl_user':
                        $dashboardRoute = 'tl_user.dashboard';
                        break;
                    case 'app_user':
                        $dashboardRoute = 'app_user.dashboard';
                        break;
                    case 'executive_user':
                        $dashboardRoute = 'executive.dashboard';
                        break;
                }
            @endphp

            const dashboardUrl = '{{ route($dashboardRoute) }}?is_ajax=1';

            document.addEventListener('DOMContentLoaded', function () {
                const sidebar = document.getElementById('sidebarMenu');
                const toggleBtn = document.getElementById('sidebarToggle');
                const tabsHeader = document.getElementById('tabs-header');
                const tabsContent = document.getElementById('tabs-content');
                const sidebarLinks = document.querySelectorAll('a[data-tab-link]');
                const scrollLeftBtn = document.getElementById('tab-scroll-left');
                const scrollRightBtn = document.getElementById('tab-scroll-right');

                // Sidebar toggle logic
                if (sidebar && toggleBtn) {
                    toggleBtn.addEventListener('click', function () {
                        sidebar.classList.toggle('sidebar-collapsed');
                    });
                }

                // Sidebar link click handler
                sidebarLinks.forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const url = new URL(this.href);
                        url.searchParams.set('is_ajax', '1');
                        const tabName = this.dataset.tabLink;

                        const existingTabButton = tabsHeader.querySelector(`[data-tab-name="${tabName}"]`);
                        if (existingTabButton) {
                            activateTab(tabName);
                            return;
                        }

                        // Kirim informasi 'closable' ke fungsi createTab
                        const isClosable = this.dataset.closable !== 'false';
                        createTab(tabName, url.toString(), isClosable);
                    });
                });

                // Function to create a new tab
                function createTab(tabName, url, isClosable = true) {
                    const tabButton = document.createElement('a');
                    tabButton.href = '#';
                    tabButton.textContent = tabName;
                    tabButton.className = 'tab-button flex items-center px-4 py-2 border-b-2 font-medium text-sm whitespace-nowrap text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600';
                    tabButton.dataset.tabName = tabName;
                    tabButton.onclick = (e) => {
                        e.preventDefault();
                        activateTab(tabName);
                    };

                    // Tambahkan tombol close hanya jika tab bisa ditutup
                    if (isClosable) {
                        const closeButton = document.createElement('i');
                        closeButton.className = 'tab-close-button fas fa-times text-gray-500 hover:text-gray-900 ml-2';
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
                }

                // Function to load content via fetch
                function loadTabContent(tabName, url) {
                    const tabContent = document.getElementById(`${tabName}-content`);
                    tabContent.innerHTML = `<div class="p-4 text-center text-gray-500">Memuat...</div>`;

                    fetch(url)
                    .then(response => response.text())
                    .then(html => {
                        tabContent.innerHTML = html;
                        activateTab(tabName);
                        updateScrollButtons();
                    })
                    .catch(error => {
                        tabContent.innerHTML = `<div class="p-4 text-red-500">Gagal memuat konten.</div>`;
                        console.error('Error:', error);
                    });
                }

                // Function to activate a specific tab
                function activateTab(tabName) {
                    document.querySelectorAll('.tab-button').forEach(btn => {
                        btn.classList.remove('active', 'text-indigo-600', 'border-indigo-500');
                        btn.classList.add('text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                    });
                    document.querySelectorAll('.tab-content').forEach(content => {
                        content.classList.add('hidden');
                    });

                    const activeTabButton = tabsHeader.querySelector(`[data-tab-name="${tabName}"]`);
                    const activeTabContent = document.getElementById(`${tabName}-content`);
                    
                    activeTabButton.classList.add('active', 'text-indigo-600', 'border-indigo-500');
                    activeTabButton.classList.remove('text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                    activeTabContent.classList.remove('hidden');
                    
                    activeTabButton.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    updateScrollButtons();
                }

                // Function to close a tab
                function closeTab(tabName) {
                    const tabToClose = tabsHeader.querySelector(`[data-tab-name="${tabName}"]`);
                    const contentToClose = document.getElementById(`${tabName}-content`);
                    
                    if (!tabToClose || !contentToClose) return;

                    // Jika tab tidak memiliki tombol close, abaikan
                    if (!tabToClose.querySelector('.tab-close-button')) {
                        return;
                    }

                    const wasActive = tabToClose.classList.contains('active');
                    const nextTab = tabToClose.nextElementSibling || tabToClose.previousElementSibling;

                    tabToClose.remove();
                    contentToClose.remove();

                    if (wasActive && nextTab && nextTab.dataset.tabName) {
                        activateTab(nextTab.dataset.tabName);
                    } else if (tabsHeader.children.length > 0) {
                        activateTab(tabsHeader.children[0].dataset.tabName);
                    } else {
                        scrollLeftBtn.classList.add('hidden');
                        scrollRightBtn.classList.add('hidden');
                    }
                    
                    updateScrollButtons();
                }
                
                // Navigation buttons logic
                function updateScrollButtons() {
                    const shouldShowButtons = tabsHeader.scrollWidth > tabsHeader.clientWidth;

                    if (shouldShowButtons) {
                        scrollLeftBtn.classList.remove('hidden');
                        scrollRightBtn.classList.remove('hidden');
                    } else {
                        scrollLeftBtn.classList.add('hidden');
                        scrollRightBtn.classList.add('hidden');
                    }
                }
                
                scrollLeftBtn.addEventListener('click', () => {
                    tabsHeader.scrollBy({ left: -200, behavior: 'smooth' });
                });
                
                scrollRightBtn.addEventListener('click', () => {
                    tabsHeader.scrollBy({ left: 200, behavior: 'smooth' });
                });
                
                window.addEventListener('resize', updateScrollButtons);

                function initializeDashboardTab() {
                    createTab('Dashboard', dashboardUrl, false);
                }

                initializeDashboardTab();
            });
        </script>
    </body>
</html>