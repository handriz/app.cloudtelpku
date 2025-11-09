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

        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="" defer></script>
        
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .sidebar-collapsed { width: 70px !important; }
        .sidebar-collapsed .menu-text { display: none; }
    </style>
    </head>
    <body class="font-sans antialiased bg-gray-100 dark:bg-gray-900" 
          @php
              $dashboardRoute = 'dashboard';
              $userRole = Auth::check() && Auth::user()->role ? Auth::user()->role->name : null;
              switch ($userRole) {
                  case 'admin':
                      $dashboardRoute = 'admin.dashboard';
                      break;
                  case 'team':
                      $dashboardRoute = 'team.dashboard';
                      break;
                  case 'appuser':
                      $dashboardRoute = 'appuser.dashboard';
                      break;
                  case 'executive_user':
                      $dashboardRoute = 'executive.dashboard';
                      break;
              }
          @endphp
          data-dashboard-url="{{ route($dashboardRoute) }}">
        <!-- <div class="flex min-h-screen bg-gray-100 dark:bg-gray-900"> -->
        <div x-data="{ mobileSidebarOpen: false }" class="flex min-h-screen bg-gray-100 dark:bg-gray-900">

            {{-- Sidebar Kiri --}}
            @include('layouts.sidebar')

            <div class="flex-1 flex flex-col min-w-0 transition-all duration-300 ease-in-out">
                {{-- Navigasi Atas --}}
                @include('layouts.navigation')

                {{-- 2. Tambahkan overlay gelap yang muncul saat sidebar mobile terbuka --}}
                <div x-show="mobileSidebarOpen" @click="mobileSidebarOpen = false" 
                     class="fixed inset-0 bg-black bg-opacity-50 z-20 lg:hidden"
                     x-transition:enter="transition-opacity ease-linear duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity ease-linear duration-300"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     style="display: none;">
                </div>
                
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

        <div id="main-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 dark:bg-opacity-80 flex items-center justify-center z-50 hidden p-4">
            {{-- Panel utama dibuat menjadi flex container vertikal --}}
            <div id="modal-panel" class="bg-white dark:bg-gray-800 rounded-lg shadow-xl transform transition-all sm:max-w-4xl w-full max-h-[90vh] flex flex-col">
                <div id="modal-content" class="overflow-y-auto">
                    {{-- Konten dinamis (form, dll.) akan dimuat di sini --}}
                </div>
            </div>
        </div>

        <div id="image-viewer-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 dark:bg-opacity-90 flex items-center justify-center z-[99] hidden p-4" style="backdrop-filter: blur(4px);">
            <button id="image-viewer-close" class="absolute top-4 right-6 text-white text-4xl opacity-80 hover:opacity-100 z-[101]">&times;</button>
            
            <div class="relative w-full h-full flex items-center justify-center">
                <img id="image-viewer-img" src="" alt="Pratinjau Gambar" class="max-w-[90vw] max-h-[90vh] object-contain rounded-lg shadow-xl">
            
                {{-- == INPUT METER SEMENTARA (AWALNYA HIDDEN) == --}}
                <div id="modal-meter-input-container" class="w-full max-w-md hidden">
                    <label for="modal-meter-input" class="block text-sm font-medium text-gray-300 mb-1 text-center">
                        Ketik Nomor Meter Lengkap:
                    </label>
                    <input type="text" id="modal-meter-input" class="block w-full rounded-md border-gray-500 bg-gray-700 text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center text-lg p-2" placeholder="Nomor lengkap...">
                </div>
                
            </div>

            <div id="image-viewer-overlay" class="absolute inset-0 z-[100] cursor-pointer"></div>
        </div>
        
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

        {{-- ====================================================== --}}
        {{-- MODAL GLOBAL: Konfirmasi Kustom (Hapus, Invalidate, Promote) --}}
        {{-- ====================================================== --}}
        <div id="custom-confirm-modal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden items-center justify-center p-4 z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md">
                
                {{-- Header Modal --}}
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 id="custom-confirm-title" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Konfirmasi Tindakan</h3>
                </div>

                {{-- Body Pesan --}}
                <div class="p-6">
                    <p id="custom-confirm-message" class="text-sm text-gray-700 dark:text-gray-300">
                        Apakah Anda yakin ingin melanjutkan tindakan ini?
                    </p>
                </div>

                {{-- Footer (Tombol) --}}
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 flex justify-end space-x-3 rounded-b-lg">
                    <button id="custom-confirm-cancel" type="button" class="px-4 py-2 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500">
                        Batal
                    </button>
                    <button id="custom-confirm-ok" type="button" class="px-4 py-2 bg-red-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-red-700">
                        Ya, Lanjutkan
                    </button>
                </div>
            </div>
        </div>
    </body>
</html>