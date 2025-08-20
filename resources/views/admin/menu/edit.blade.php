<x-app-layout>
    {{-- Container utama tanpa padding atau max-width, karena akan diambil alih oleh layout app.blade.php --}}
    <div class="pt-0 pb-0">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mb-4">
            {{ __('Edit Menu Item') }}
        </h2>

        {{-- Notifikasi Sukses --}}
        @if (session('success'))
            <div id="success-alert" class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 px-4 py-3 rounded-lg shadow-md relative w-full mb-4">
                <strong class="font-bold">Berhasil!</strong>
                <span class="block sm:inline">{{ session('success') }}</span>
                <span class="absolute top-2 right-2 px-2 py-1 cursor-pointer" onclick="document.getElementById('success-alert').style.display='none'">
                    <svg class="fill-current h-5 w-5 text-green-500 dark:text-green-300" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l3.029-2.651-3.029-2.651a1.2 1.2 0 0 1 1.697-1.697L10 8.183l2.651-3.029a1.2 1.2 0 1 1 1.697 1.697L11.819 10l3.029 2.651a1.2 1.2 0 0 1 0 1.698z"/></svg>
                </span>
            </div>
        @endif

        {{-- Notifikasi Error Validasi --}}
        @if ($errors->any())
            <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 px-4 py-3 rounded-lg shadow-md mb-4">
                <strong class="font-bold">Ada kesalahan!</strong>
                <ul class="mt-3 list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <hr class="border-gray-200 dark:border-gray-700 my-6">

        {{-- Kartu Formulir --}}
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <form action="{{ route('admin.menu.update', $menu->id) }}" method="POST">
                    @csrf
                    @method('PUT') 

                    {{-- Nama Menu --}}
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Menu</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $menu->name) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" required>
                    </div>

                    {{-- Rute --}}
                    <div class="mb-4">
                        <label for="route" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Rute (Laravel)</label>
                        <input type="text" name="route" id="route" value="{{ old('route', $menu->route_name) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" placeholder="Contoh: dashboard atau admin.users.index">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Biarkan kosong jika ini adalah menu induk tanpa rute langsung.</p>
                    </div>

                    {{-- Ikon --}}
                    <div class="mb-4">
                        <label for="icon" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Kelas Ikon (Font Awesome)</label>
                        <input type="text" name="icon" id="icon" value="{{ old('icon', $menu->icon) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" placeholder="Contoh: fas fa-tachometer-alt">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Lihat <a href="https://fontawesome.com/icons" target="_blank" class="text-indigo-500 hover:underline">Font Awesome</a> untuk daftar ikon.</p>
                    </div>

                    {{-- Nama Izin (Permission Name) --}}
                    <div class="mb-4">
                        <label for="permission_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Izin (Gate)</label>
                        <select name="permission_name" id="permission_name"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                            <option value="">-- Tidak Ada Izin --</option>
                            @foreach($permissions as $permission)
                                <option value="{{ $permission->name }}" {{ old('permission_name', $menu->permission_name) == $permission->name ? 'selected' : '' }}>
                                    {{ $permission->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Izin (Gate) yang diperlukan untuk melihat menu ini.</p>
                    </div>

                    {{-- Parent Menu (Untuk Sub-menu) --}}
                    <div class="mb-4">
                        <label for="parent_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Menu Induk</label>
                        <select name="parent_id" id="parent_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                            <option value="">-- Tidak Ada (Menu Utama) --</option>
                            @foreach($parentMenus as $parentMenu)
                                <option value="{{ $parentMenu->id }}" {{ old('parent_id', $menu->parent_id) == $parentMenu->id ? 'selected' : '' }}>
                                    {{ $parentMenu->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pilih jika ini adalah sub-menu.</p>
                    </div>

                    {{-- Urutan --}}
                    <div class="mb-4">
                        <label for="order" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Urutan</label>
                        <input type="number" name="order" id="order" value="{{ old('order', $menu->order) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" required>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Nomor urut menu (lebih kecil = lebih dulu).</p>
                    </div>

                    {{-- Aktif --}}
                    <div class="mb-4 flex items-center">
                        <input type="hidden" name="is_active" value="0"> {{-- Hidden field untuk memastikan nilai 0 terkirim jika checkbox tidak dicentang --}}
                        <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $menu->is_active) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                        <label for="is_active" class="ml-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Aktif</label>
                    </div>

                    {{-- Tombol Submit dan Kembali --}}
                    <div class="flex items-center justify-end mt-6">
                        <a href="{{ route('admin.menu.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 mr-4">Batal</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-2 transition ease-in-out duration-150">
                            Perbarui Menu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>