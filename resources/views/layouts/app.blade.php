<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Font Awesome CSS (Penting untuk ikon menu) -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" xintegrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{-- Tambahan CSS untuk collapse --}}
        <style>
            .sidebar-collapsed {
                width: 70px !important;
            }
            .sidebar-collapsed .menu-text {
                display: none;
            }
        </style>

    </head>
    <body class="font-sans antialiased">
        {{--
            Container utama aplikasi:
            - `flex`: Mengatur elemen anak (sidebar dan area konten utama) secara horizontal.
            - `min-h-screen`: Memastikan tinggi minimal layar penuh.
            - `bg-gray-100`: Warna latar belakang abu-abu muda yang konsisten dengan contoh gambar.
        --}}
        <div class="flex min-h-screen bg-gray-100">

            {{-- Sidebar Kiri - Membentang penuh secara vertikal --}}
            {{-- Ini adalah tempat logo utama akan berada. --}}
            @include('layouts.sidebar')

            {{--
                Area Konten Utama:
                - `flex-1`: Memastikan div ini mengambil semua ruang horizontal yang tersisa setelah sidebar.
                - `flex flex-col`: Mengatur elemen anak (navigasi atas dan konten halaman) secara vertikal.
            --}}
            <div class="flex-1 flex flex-col">
                {{-- Navigasi Atas (Header dari Laravel Breeze) --}}
                {{-- Navigasi ini sekarang akan membentang di seluruh lebar sisa layar (di sebelah sidebar). --}}
                @include('layouts.navigation')

                {{--
                    Area Konten Halaman (dibawah navigasi atas).
                    - `flex-1`: Memastikan main ini mengambil semua ruang vertikal yang tersisa
                                 setelah navigasi atas.
                    - `overflow-y-auto`: Memungkinkan scroll vertikal jika konten melebihi tinggi layar.
                    - `p-4`: Padding di sekitar konten untuk menjaga jarak dari tepi.
                --}}
                <main class="flex-1 overflow-y-auto p-4">
                    <!-- Page Heading -->
                    {{-- Judul halaman (misal: "Dashboard Admin") --}}
                    @if (isset($header))
                        <header class="bg-white shadow-sm rounded-lg mb-6">
                            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                                {{ $header }}
                            </div>
                        </header>
                    @endif

                    <!-- Page Content -->
                    {{-- Slot untuk konten spesifik halaman (dari dashboard.blade.php, dll.) --}}
                    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
        {{-- Tambahan JS untuk toggle --}}
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const sidebar = document.getElementById('sidebarMenu');
                const toggleBtn = document.getElementById('sidebarToggle');

                toggleBtn.addEventListener('click', function () {
                    sidebar.classList.toggle('sidebar-collapsed');
                });
            });
        </script>

    </body>
</html>

