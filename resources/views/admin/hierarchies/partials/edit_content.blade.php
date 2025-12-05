<div class="p-6 bg-white dark:bg-gray-800 rounded-lg">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">
            Edit Level: <span class="text-indigo-600">{{ $hierarchy->name }}</span>
        </h2>
        <button type="button" class="text-gray-400 hover:text-gray-500 focus:outline-none" data-modal-close>
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>

    <form method="POST" action="{{ route('admin.hierarchies.update', $hierarchy->id) }}" class="ajax-form" data-success-redirect-tab="Manajemen Hirarki">
        @csrf
        @method('PUT')

        {{-- Kode Level Hirarki --}}
        <div class="mb-4">
            <label for="edit_code" class="block text-sm font-bold text-gray-700 dark:text-gray-300">Kode Level (User)</label>
            <input type="text" name="code" id="edit_code" value="{{ $hierarchy->code }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white" required>
        </div>

        {{-- Nama Level --}}
        <div class="mb-4">
            <label for="edit_name" class="block text-sm font-bold text-gray-700 dark:text-gray-300">Nama Level</label>
            <input type="text" name="name" id="edit_name" value="{{ $hierarchy->name }}" class="mt-1 block w-full rounded-md border-gray-300 uppercase shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white" required>
        </div>

        {{-- Tipe Unit --}}
        <div class="mb-4">
            <label for="edit_unit_type" class="block text-sm font-bold text-gray-700 dark:text-gray-300">Tipe Unit</label>
            <select name="unit_type" id="edit_unit_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white">
                <option value="UID" {{ $hierarchy->unit_type == 'UID' ? 'selected' : '' }}>UID</option>
                <option value="UP3" {{ $hierarchy->unit_type == 'UP3' ? 'selected' : '' }}>UP3</option>
                <option value="ULP" {{ $hierarchy->unit_type == 'ULP' ? 'selected' : '' }}>ULP</option>
                <option value="SUB_ULP" {{ $hierarchy->unit_type == 'SUB_ULP' ? 'selected' : '' }}>SUB ULP</option>
                
            </select>
        </div>

        {{-- Level Induk --}}
        <div class="mb-4">
            <label for="edit_parent_code" class="block text-sm font-bold text-gray-700 dark:text-gray-300">Level Induk</label>
            <select name="parent_code" id="edit_parent_code" class="...">
                <option value="" data-type="ROOT">-- Tidak Ada (Level Utama) --</option>
                @foreach($parentHierarchyLevels as $parent)
                    <option value="{{ $parent->code }}" 
                            data-type="{{ $parent->unit_type }}"
                            {{ $hierarchy->parent_code == $parent->code ? 'selected' : '' }}>
                        [{{ $parent->unit_type ?? 'UNK' }}] {{ $parent->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Kode Huruf KDDK --}}
        <div class="mb-4">
            <label for="edit_kddk_code" class="block text-sm font-bold text-indigo-700 dark:text-indigo-400">Kode Huruf KDDK</label>
            <div class="flex items-center">
                <input type="text" name="kddk_code" id="edit_kddk_code" value="{{ $hierarchy->kddk_code }}" maxlength="1" class="w-20 text-center font-bold text-xl rounded-md border-indigo-300 uppercase shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white"
                oninput="this.value = this.value.toUpperCase()" required autocomplete="off">
                <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">Wajib 1 Huruf (A-Z).</span>
            </div>
        </div>

        {{-- Urutan & Aktif --}}
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label for="edit_order" class="block text-sm font-bold text-gray-700 dark:text-gray-300">Urutan</label>
                <input type="number" name="order" id="edit_order" value="{{ $hierarchy->order }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white">
            </div>
            <div class="flex items-center mt-6">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="edit_is_active" value="1" {{ $hierarchy->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                <label for="edit_is_active" class="ml-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Status Aktif</label>
            </div>
        </div>

        {{-- Tombol Aksi --}}
        <div class="flex items-center justify-end mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="button" class="mr-3 px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300" data-modal-close>
                Batal
            </button>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded shadow hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                Perbarui Level
            </button>
        </div>
        
        <div id="ajax-errors" class="mt-4 hidden p-3 bg-red-100 text-red-700 rounded text-sm"></div>
    </form>
</div>