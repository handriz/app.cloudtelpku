<x-app-layout>
    <div class="pt-0 pb-0">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mb-4">
            {{ __('Tambah Level Hirarki Baru') }}
        </h2>

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
                <form method="POST" action="{{ route('admin.hierarchies.store') }}">
                    @csrf

                    {{-- Kode Level Hirarki --}}
                    <div class="mb-4">
                        <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Kode Level</label>
                        <input type="text" name="code" id="code" value="{{ old('code') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" required autofocus placeholder="Contoh: REGIONAL_SUMATERA">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Kode unik untuk level ini (misal: JABODETABEK, DIVISI_HRD).</p>
                        @error('code')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Nama Level Hirarki --}}
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Level</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" required placeholder="Contoh: Regional Sumatera, Divisi HRD">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Nama yang mudah dibaca untuk level ini.</p>
                        @error('name')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Level Induk (Parent) --}}
                    <div class="mb-4">
                        <label for="parent_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Level Induk</label>
                        <select name="parent_code" id="parent_code"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                            <option value="">-- Tidak Ada (Level Utama) --</option>
                            {{-- Panggil Blade Directive untuk opsi hirarki --}}
                            @renderHierarchyParentOptions($parentHierarchyLevels, null, 0, old('parent_code'), null)
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pilih level hirarki yang menjadi induknya.</p>
                        @error('parent_code')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Urutan --}}
                    <div class="mb-4">
                        <label for="order" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Urutan</label>
                        <input type="number" name="order" id="order" value="{{ old('order', 0) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" required>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Nomor urut tampilan level hirarki (lebih kecil = lebih dulu).</p>
                        @error('order')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Aktif --}}
                    <div class="mb-4 flex items-center">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', 1) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600">
                        <label for="is_active" class="ml-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Aktif</label>
                    </div>

                    {{-- Tombol Submit dan Kembali --}}
                    <div class="flex items-center justify-end mt-6">
                        <a href="{{ route('admin.hierarchies.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 mr-4">Batal</a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-2 transition ease-in-out duration-150">
                            Tambah Level
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>