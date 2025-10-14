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

        <div id="main-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 dark:bg-opacity-80 flex items-center justify-center z-50 hidden p-4">
            {{-- Panel utama dibuat menjadi flex container vertikal --}}
            <div id="modal-panel" class="bg-white dark:bg-gray-800 rounded-lg shadow-xl transform transition-all sm:max-w-4xl w-full max-h-[90vh] flex flex-col">
                <div id="modal-content" class="overflow-y-auto">
                    {{-- Konten dinamis (form, dll.) akan dimuat di sini --}}
                </div>
            </div>
        </div>
        
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    </body>
</html>