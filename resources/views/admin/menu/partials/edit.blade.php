{{-- 
  File: resources/views/admin/menu/partials/edit.blade.php
  (Struktur ini meniru create.blade.php Anda agar rapi)
--}}

<form id="edit-menu-form" action="{{ route('admin.menu.update', $menu->id) }}" method="POST" class="ajax-form">
    @csrf
    @method('PATCH') {{-- Gunakan PATCH (sesuai Controller) --}}
    
    {{-- SATU PEMBUNGKUS UTAMA (Seperti create.blade.php) --}}
    <div class="p-6 space-y-4">
        
        {{-- Header (Judul + Tombol Close) --}}
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                Edit Item Menu: {{ $menu->name }}
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

        {{-- Body (Form Inputs) - Menggunakan grid 2 kolom seperti create.blade.php --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Kolom Kiri --}}
            <div class="space-y-4">
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Menu</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $menu->name) }}" class="mt-1 block w-full ..." required>
                </div>
                
                <div class="mb-4">
                    <label for="route_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Rute (Laravel)</label>
                    <input type="text" name="route_name" id="route_name" value="{{ old('route_name', $menu->route_name) }}" class="mt-1 block w-full ..." placeholder="Contoh: dashboard atau admin.users.index">
                </div>
                
                <div>
                    <label for="icon_input" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ikon (Pilih atau Ketik)</label>
                    <div class="mt-1 flex rounded-md shadow-sm">
                        <span id="icon-preview-box" class="inline-flex items-center px-4 py-2 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 dark:bg-gray-700 dark:border-gray-600 text-gray-500 dark:text-gray-400 text-lg">
                            {{-- Isi preview dengan ikon yang ada --}}
                            <i class="{{ old('icon', $menu->icon) ?? 'fas fa-grip-horizontal' }}"></i>
                        </span>
                        <input type="text" name="icon" id="icon_input" class="flex-1 block w-full rounded-none border-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500" placeholder="fas fa-users" value="{{ old('icon', $menu->icon) }}">
                        <button type="button" id="icon-picker-trigger" class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 dark:border-gray-600 dark:hover:bg-gray-600">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <div id="icon-picker-grid" class="hidden mt-2 p-4 border rounded-md bg-gray-50 dark:bg-gray-900 max-h-48 overflow-y-auto">
                        <div class="grid grid-cols-8 md:grid-cols-12 gap-2 text-center text-xl text-gray-700 dark:text-gray-300">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Kolom Kanan --}}
            <div class="space-y-4">
                <div class="mb-4">
                    <label for="parent_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Menu Induk</label>
                    <select name="parent_id" id="parent_id" class="mt-1 block w-full ...">
                        <option value="">-- Tidak Ada (Menu Utama) --</option>
                        @foreach($parentMenus as $parentMenu)
                            <option value="{{ $parentMenu->id }}" {{ old('parent_id', $menu->parent_id) == $parentMenu->id ? 'selected' : '' }}>
                                {{ $parentMenu->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-4">
                    <label for="permission_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Izin (Gate)</label>
                    <select name="permission_name" id="permission_name" class="mt-1 block w-full ...">
                        <option value="">-- Tidak Ada Izin --</option>
                        @foreach($permissions as $permission)
                            <option value="{{ $permission->name }}" {{ old('permission_name', $menu->permission_name) == $permission->name ? 'selected' : '' }}>
                                {{ $permission->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-4">
                    <label for="order" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Urutan</label>
                    <input type="number" name="order" id="order" value="{{ old('order', $menu->order) }}" class="mt-1 block w-full ..." required>
                </div>
            </div>
        </div>

        {{-- URL (Jika ada, Anda bisa menambahkannya di Controller dan di sini) --}}
        {{-- <div class="mb-4"> ... URL ... </div> --}}

        <div class="mb-4 flex items-center">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $menu->is_active) ? 'checked' : '' }} class="rounded ...">
            <label for="is_active" class="ml-2 ...">Aktif</label>
        </div>

        {{-- Footer (Tombol Aksi) --}}
        <hr class="border-gray-200 dark:border-gray-700">
        <div class="flex justify-end space-x-2">
        <button type="button" data-modal-close 
                class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-800 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
            Batal
        </button>
        <button type="submit" id="edit-menu-submit-button" 
                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
            Perbarui Menu
        </button>
            </button>
        </div>

    </div> {{-- Penutup <div class="p-6 space-y-4"> --}}
</form>