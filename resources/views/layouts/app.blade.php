<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Font Awesome CSS -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .sidebar-collapsed {
                width: 70px !important;
            }
            .sidebar-collapsed .menu-text {
                display: none;
            }
            /* Menghapus overflow-x: hidden dari html, body, dan div.flex utama */
            /* Kita ingin flexbox menangani ini secara alami dengan min-w-0 */
            html, body {
                width: 100%;
                /* overflow-x: hidden; */ /* Hapus atau komentari ini */
            }
            .flex {
                /* overflow-x: hidden; */ /* Hapus atau komentari ini */
            }
        </style>

    </head>
    <body class="font-sans antialiased bg-gray-100 dark:bg-gray-900">
        {{-- Kontainer Flex Utama --}}
        <div class="flex min-h-screen bg-gray-100 dark:bg-gray-900">

            {{-- Sidebar Kiri --}}
            @include('layouts.sidebar')

            {{-- Area Konten Utama --}}
            {{-- KUNCI PERUBAHAN: Tambahkan min-w-0 --}}
            <div class="flex-1 flex flex-col min-w-0 transition-all duration-300 ease-in-out"> 
                {{-- Navigasi Atas --}}
                @include('layouts.navigation')

                {{-- Area Konten Halaman --}}
                <main class="flex-1 overflow-y-auto pt-0 pb-12"> 
                    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                        <!-- Page Heading -->
                        @if (isset($header))
                            <header class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
                                <div class="py-6"> 
                                    {{ $header }}
                                </div>
                            </header>
                        @endif

                        <!-- Page Content -->
                        <div> 
                            {{ $slot }}
                        </div>
                    </div>
                </main>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const sidebar = document.getElementById('sidebarMenu');
                const toggleBtn = document.getElementById('sidebarToggle');

                if (sidebar && toggleBtn) {
                    toggleBtn.addEventListener('click', function () {
                        sidebar.classList.toggle('sidebar-collapsed');
                    });
                }
            });
        </script>
    </body>
</html>