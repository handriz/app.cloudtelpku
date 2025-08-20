<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
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
        </style>

    </head>
    <body class="font-sans antialiased bg-gray-100 dark:bg-gray-900">
        <div class="flex min-h-screen bg-gray-100 dark:bg-gray-900">

            {{-- Sidebar Kiri --}}
            @include('layouts.sidebar')

            {{-- Area Konten Utama --}}
            {{-- `flex-1` membuat div ini mengisi semua ruang horizontal yang tersisa. --}}
            <div class="flex-1 flex flex-col transition-all duration-300 ease-in-out">
                {{-- Navigasi Atas (biarkan file ini tidak berubah seperti permintaan) --}}
                @include('layouts.navigation')

                {{-- Area Konten Halaman --}}
                {{-- PENTING: `px-4 sm:px-6 lg:px-8` DITERAPKAN LANGSUNG PADA `<main>`.
                     Ini akan membuat konten mengambil lebar penuh yang tersedia dan memberikan padding yang konsisten. --}}
                <main class="flex-1 overflow-y-auto px-4 sm:px-6 lg:px-8 pt-6 pb-12"> 
                    @if (isset($header))
                        <header class="bg-white dark:bg-gray-800 shadow rounded-lg mb-6">
                            <div class="py-6 px-4 sm:px-6 lg:px-8"> 
                                {{ $header }}
                            </div>
                        </header>
                    @endif

                    {{-- PENTING: HAPUS SEMUA `max-w-*` dan `px-*` DARI DIV INI. --}}
                    <div>
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
        {{-- JS untuk toggle sidebar --}}
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const sidebar = document.getElementById('sidebarMenu');
                const toggleBtn = document.getElementById('sidebarToggle');
                const mainContentWrapper = document.querySelector('.flex-1.flex-col');

                if (sidebar && toggleBtn && mainContentWrapper) {
                    toggleBtn.addEventListener('click', function () {
                        sidebar.classList.toggle('sidebar-collapsed');
                    });
                }
            });
        </script>
    </body>
</html>