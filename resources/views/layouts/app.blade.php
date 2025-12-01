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
    

<script>
        (g => {
            var h, a, k, p = "The Google Maps JavaScript API",
                c = "google",
                l = "importLibrary",
                q = "__ib__",
                m = document,
                b = window;
            b[c] || (b[c] = {});
            var d = b[c];
            d[l] || (d[l] = function() {
                (d[q] || (d[q] = [])).push(arguments)
            });
            var e = d[l];
            Object.defineProperty(d, l, {
                value: function() {
                    e.apply(null, arguments)
                }
            });
            
            // Di sinilah kita menyisipkan kunci API dari PHP
            var f = new URLSearchParams;
            f.append("key", @json(config('services.google-maps.key')));
            f.append("libraries", "streetView"); // Muat library yang Anda butuhkan
            f.append("callback", "initMap"); // Tetap gunakan initMap
            f.append("loading", "async"); 
            f.append("callback", "initMap");

            var g = m.createElement("script");
            g.async = !0;
            g.src = "https://maps.googleapis.com/maps/api/js?" + f.toString();
            h = m.getElementsByTagName("script")[0];
            h.parentNode.insertBefore(g, h)
        })(window);

        // Fungsi initMap tetap dibutuhkan, biarkan kosong
        function initMap() {
            // Biarkan kosong
        }
    </script>
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
            
            <div class="absolute top-4 right-4 md:top-6 md:right-6 z-[102] flex space-x-2">
                <button id="image-viewer-rotate" class="p-2 text-white text-xl opacity-80 hover:opacity-100 rounded-full bg-gray-800/50 hover:bg-gray-700/70 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-redo"></i> </button>
                <button id="image-viewer-close" class="p-2 text-white text-3xl opacity-80 hover:opacity-100">&times;</button>
            </div>

            <div class="flex max-w-[95vw] max-h-[95vh] h-full items-center justify-center">

                <div id="image-zoom-area" class="relative flex-shrink-0 h-full flex items-center justify-center">
                    
                    <img id="image-viewer-img" 
                         src="" 
                         alt="Pratinjau Gambar" 
                         class="rounded-lg shadow-xl" 
                         style="display: block; max-width: 100%; max-height: 100%; object-fit: contain; width: auto; height: auto;">
                </div>
                
            </div>
            
            <div id="draggable-input-wrapper" class="absolute w-[300px] hidden z-[103]" style="top: 10%; right: 10%;"> 
            
                <div id="modal-meter-input-container" class="w-full h-auto p-4 bg-gray-900/90 backdrop-blur-sm rounded-lg shadow-2xl"> 
                    
                    <h4 class="text-sm font-bold text-indigo-400 mb-4 cursor-move" id="input-drag-handler">
                        Data Attribute <i class="fas fa-arrows-alt text-gray-500 ml-2"></i>
                    </h4>
                    
                    <div class="space-y-4">
                        <div>
                            <label for="modal-input-meter" class="block text-xs font-medium text-red-400 mb-1">No. Meter **(Wajib)**:</label>
                            <input type="text" id="modal-input-meter" class="modal-eval-input block w-full rounded-md border-gray-500 bg-gray-700 text-white shadow-sm text-sm p-1.5" placeholder="Nomor lengkap...">
                        </div>
                        <div>
                            <label for="modal-input-mcb" class="block text-xs font-medium text-gray-300 mb-1">Kapasitas MCB:</label>
                            <input type="text" id="modal-input-mcb" class="modal-eval-input block w-full rounded-md border-gray-500 bg-gray-700 text-white shadow-sm text-sm p-1.5" placeholder="MCB...">
                        </div>
                        <div>
                            <label for="modal-input-pbts" class="block text-xs font-medium text-gray-300 mb-1">Merk MCB:</label>
                            <input type="text" id="modal-input-pbts" class="modal-eval-input block w-full rounded-md border-gray-500 bg-gray-700 text-white shadow-sm text-sm p-1.5" placeholder="Tipe PB/TS...">
                        </div>
                        <div>
                            <label for="modal-input-merkkwh" class="block text-xs font-medium text-gray-300 mb-1">Merk KWH:</label>
                            <input type="text" id="modal-input-merkkwh" class="modal-eval-input block w-full rounded-md border-gray-500 bg-gray-700 text-white shadow-sm text-sm p-1.5" placeholder="Merk KWH...">
                        </div>
                        <div class="col-span-2 md:col-span-4"> <label for="modal-input-tahun_buat" class="block text-xs font-medium text-gray-300 mb-1">Tahun Buat:</label>
                            <input type="text" id="modal-input-tahun_buat" class="modal-eval-input block w-full rounded-md border-gray-500 bg-gray-700 text-white shadow-sm text-sm p-1.5" placeholder="Tahun...">
                        </div>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-4">*Tutup modal untuk menyimpan perubahan data.</p>
                </div>
            </div>
        </div>
    
    </body>
</html>