{{-- resources/views/team/mapping-validation/partials/upload_photos_form.blade.php --}}
<div class="bg-white dark:bg-gray-800 w-full p-6 rounded-lg">

    {{-- Header dan Tombol Close --}}
    <div class="flex justify-between items-center pb-3">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
            Upload Foto Mapping (Batch)
        </h2>
        <button type="button" data-modal-close class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
             <i class="fas fa-times fa-lg"></i>
        </button>
    </div>

    {{-- Garis Pemisah --}}
    <hr class="border-gray-200 dark:border-gray-700 mb-4">

    {{-- Deskripsi --}}
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-5">
        Pilih satu atau lebih file foto (Maksimal 100mb per Upload dengan format .jpg, .jpeg, .png). Pastikan penamaan file foto sesuai format: <code>objectid_idpel_suffix</code> (contoh: <code>1_181100392xxx_foto_app</code>)-(contoh: <code>1_181100392xxx_foto_persil</code>) . Foto akan diunggah ke folder sementara dan diproses oleh sistem di latar belakang.
    </p>

    {{-- Form Utama --}}
    <form id="batch-photo-upload-form"
          action="{{ route('team.mapping_validation.upload.batch_photos') }}"
          method="POST"
          enctype="multipart/form-data">
        @csrf

        {{-- Dropzone --}}
        <div id="photo-dropzone" class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center transition-colors duration-200 mb-4 hover:border-indigo-400 dark:hover:border-indigo-500">
            <input type="file" id="photo-file-input" name="photos[]" class="hidden" multiple accept="image/jpeg,image/png">
            <label for="photo-file-input" class="cursor-pointer block">
                <div class="flex flex-col items-center">
                    <i class="fas fa-images text-4xl text-indigo-400 dark:text-indigo-500"></i>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Pilih file foto atau seret ke sini</p>
                    <p class="text-xs text-gray-500 dark:text-gray-500">(JPG, JPEG, PNG)</p>
                </div>
            </label>
        </div>

        {{-- Daftar File Terpilih --}}
        <div id="photo-file-list" class="mb-4 text-sm text-gray-700 dark:text-gray-300 max-h-32 overflow-y-auto bg-gray-50 dark:bg-gray-700 p-3 rounded border dark:border-gray-600">
            <p class="text-xs text-gray-500 italic">Belum ada file dipilih.</p> {{-- Placeholder awal --}}
        </div>

        {{-- Tombol Upload --}}
        <button type="submit" id="batch-upload-button" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
            <i class="fas fa-upload mr-2"></i> Upload Foto Terpilih
        </button>
    </form>

    {{-- Area Progress dan Status --}}
    <div id="batch-progress-container" class="mt-5 hidden">
        <p class="text-sm font-medium mb-1 dark:text-gray-300">Progress Upload:</p>
        <div class="w-full bg-gray-200 rounded-full dark:bg-gray-700">
            <div id="batch-progress-bar" class="bg-indigo-600 text-xs font-medium text-blue-100 text-center p-0.5 leading-none rounded-full transition-width duration-300" style="width: 0%">0%</div>
        </div>
        <p id="batch-progress-text" class="text-xs text-gray-500 mt-1 dark:text-gray-400"></p>
    </div>
    <div id="batch-status-message" class="mt-3 text-sm"></div>

</div>