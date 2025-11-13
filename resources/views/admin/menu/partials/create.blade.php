{{-- 
  File: resources/views/admin/menu/partials/create.blade.php
  Struktur ini MENIRU create.blade.php (Mapping) Anda
--}}

<form id="create-menu-form" action="{{ route('admin.menu.store') }}" method="POST" class="ajax-form">
    @csrf
    
    {{-- SATU PEMBUNGKUS UTAMA (Mengikuti create.blade.php) --}}
    <div class="p-6 space-y-4">
        
        {{-- Header (Judul + Tombol Close) --}}
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                Tambah Item Menu Baru
            </h2>
            <button type="button" data-modal-close class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                 <i class="fas fa-times fa-lg"></i>
            </button>
        </div>
        <hr class="border-gray-200 dark:border-gray-700">

        {{-- Kontainer Error AJAX --}}
        <div id="ajax-errors" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative hidden mb-4">
            <strong class="font-bold">Oops!</strong>
            <ul id="error-list" class="mt-2 list-disc list-inside"></ul>
        </div>

        {{-- Body (Form Inputs) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Kolom Kiri --}}
            <div class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Menu (Wajib)</label>
                    <input type="text" name="name" id="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600" value="{{ old('name') }}" required autocomplete="off">
                </div>
                <div>
                    <label for="route_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Rute (Opsional)</label>
                    <input type="text" name="route_name" id="route_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600" value="{{ old('route_name') }}" placeholder="Contoh: admin.dashboard">
                </div>             
                <div>
                    <div class="flex justify-between items-center">
                        <label for="icon_input" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ikon (Pilih atau Ketik)</label>
                        <a href="https://fontawesome.com/v6/search?f=classic&s=regular&ic=free&it=round&o=r" target="_blank" class="text-xs text-indigo-500 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">
                            <i class="fas fa-external-link-alt mr-1"></i> Cari Ikon Lain
                        </a>
                    </div>
                    <div class="mt-1 flex rounded-md shadow-sm">
                        {{-- 1. Preview Box (Akan diupdate oleh JS) --}}
                        <span id="icon-preview-box" class="inline-flex items-center px-4 py-2 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 dark:bg-gray-700 dark:border-gray-600 text-gray-500 dark:text-gray-400 text-lg">
                            <i class="fas fa-grip-horizontal"></i>
                        </span>
                        {{-- 2. Input Teks (Tetap ada, tapi ID diganti) --}}
                        <input type="text" name="icon" id="icon_input" class="flex-1 block w-full rounded-none border-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500" placeholder="fas fa-users" value="{{ old('icon') }}">
                        {{-- 3. Tombol Trigger untuk Picker --}}
                        <button type="button" id="icon-picker-trigger" class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:border-gray-600 dark:hover:bg-gray-600">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    {{-- 4. Container Grid (Awalnya hidden) --}}
                    <div id="icon-picker-grid" class="hidden mt-2 p-4 border rounded-md bg-gray-50 dark:bg-gray-900 max-h-48 overflow-y-auto">
                        <div class="grid grid-cols-8 md:grid-cols-12 gap-2 text-center text-xl text-gray-700 dark:text-gray-300">
                            {{-- Akan diisi oleh JavaScript --}}
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Kolom Kanan --}}
            <div class="space-y-4">
                <div>
                    <label for="parent_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Menu Induk (Opsional)</label>
                    <select name="parent_id" id="parent_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600">
                        <option value="">-- Tidak Ada (Menu Utama) --</option>
                        @foreach($parentMenus as $item)
                            <option value="{{ $item->id }}" {{ old('parent_id') == $item->id ? 'selected' : '' }}>
                                {{ $item->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="permission_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Izin (Opsional)</label>
                    <select name="permission_name" id="permission_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600">
                        <option value="">-- Tidak Ada Izin --</option>
                        @foreach($permissions as $permission)
                            <option value="{{ $permission->name }}">{{ $permission->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="order" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Urutan (Wajib)</label>
                    <input type="number" name="order" id="order" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600" value="{{ old('order', 0) }}" required>
                </div>
            </div>
        </div>
        
        <div>
            <label for="url" class="block text-sm font-medium text-gray-700 dark:text-gray-300">URL Langsung (Opsional)</label>
            <input type="text" name="url" id="url" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600" value="{{ old('url') }}" placeholder="Contoh: /admin/settings (Gunakan jika tidak ada Nama Rute)">
        </div>

        <div class="flex items-center">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" id="is_active" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" checked>
            <label for="is_active" class="ml-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Aktif</an>
        </div>

        {{-- Footer (Tombol Aksi) --}}
        <hr class="border-gray-200 dark:border-gray-700">
        <div class="flex justify-end space-x-2">
        <button type="button" data-modal-close 
                class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-800 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
            Batal
        </button>
        <button type="submit" id="create-menu-submit-button" 
                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
            Simpan Menu
        </button>
        </div>

    </div> {{-- Penutup <div class="p-6 space-y-4"> --}}
</form>