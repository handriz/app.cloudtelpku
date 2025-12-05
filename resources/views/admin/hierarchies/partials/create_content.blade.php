<div class="p-6 bg-white dark:bg-gray-800 rounded-lg">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">
            {{ __('Tambah Level Hirarki Baru') }}
        </h2>
        <button type="button" class="text-gray-400 hover:text-gray-500 focus:outline-none" data-modal-close>
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>

    {{-- Notifikasi Error Validasi (Akan muncul via JS, tapi kita sediakan wadahnya) --}}
    <div id="create-hierarchy-errors" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4"></div>

    <form method="POST" action="{{ route('admin.hierarchies.store') }}" class="ajax-form" data-success-redirect-tab="Manajemen Hirarki">
        @csrf

        {{-- Kode Level Hirarki --}}
        <div class="mb-4">
            <label for="code" class="block text-sm font-bold text-gray-700 dark:text-gray-300">Kode Level (User)</label>
            <input type="text" name="code" id="code" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required placeholder="Contoh: 18199">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Kode unik untuk mapping user.</p>
        </div>

        {{-- Nama Level Hirarki --}}
        <div class="mb-4">
            <label for="name" class="block text-sm font-bold text-gray-700 dark:text-gray-300">Nama Level</label>
            <input type="text" name="name" id="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white uppercase" required placeholder="CONTOH: ULP KOTA BARU">
        </div>

        {{-- Kolom Baru: Tipe Unit --}}
        <div class="mb-4">
            <label for="unit_type" class="block text-sm font-bold text-gray-700 dark:text-gray-300">Tipe Unit</label>
            <select name="unit_type" id="create_unit_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <option value="UID">UID</option>  
                <option value="UP3">UP3</option>  
                <option value="ULP">ULP</option>
                <option value="SUB_ULP">SUB ULP</option>
                
            </select>
        </div>

        {{-- Level Induk (Parent) --}}
        <div class="mb-4">
            <label for="parent_code" class="block text-sm font-bold text-gray-700 dark:text-gray-300">Level Induk</label>
            <select name="parent_code" id="create_parent_code" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <option value="" data-type="ROOT">-- Tidak Ada (Level Utama) --</option>
                @foreach($parentHierarchyLevels as $parent)
                    <option value="{{ $parent->code }}" data-type="{{ $parent->unit_type }}">
                        [{{ $parent->unit_type ?? 'UNK' }}] {{ $parent->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Kolom Baru: Kode KDDK (1 Digit) --}}
        <div class="mb-4">
            <label for="kddk_code" class="block text-sm font-bold text-indigo-700 dark:text-indigo-400">Kode Huruf KDDK</label>
            <div class="flex items-center">
                <input type="text" name="kddk_code" id="create_kddk_code" maxlength="1" class="w-20 text-center font-bold text-xl rounded-md border-indigo-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white uppercase"  placeholder="A">
                <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">Wajib 1 Huruf (A-Z). Harus unik di levelnya.</span>
            </div>
        </div>

        {{-- Urutan & Aktif --}}
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label for="order" class="block text-sm font-bold text-gray-700 dark:text-gray-300">Urutan</label>
                <input type="number" name="order" id="order" value="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white">
            </div>
            <div class="flex items-center mt-6">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="is_active" value="1" checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                <label for="is_active" class="ml-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Status Aktif</label>
            </div>
        </div>

        {{-- Tombol Aksi --}}
        <div class="flex items-center justify-end mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
            {{-- Tombol Batal diganti jadi Tutup Modal --}}
            <button type="button" class="mr-3 px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600" data-modal-close>
                Batal
            </button>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded shadow hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                Simpan Level
            </button>
        </div>
        
        {{-- Wadah Error AJAX Global --}}
        <div id="ajax-errors" class="mt-4 hidden p-3 bg-red-100 text-red-700 rounded text-sm"></div>
    </form>
</div>