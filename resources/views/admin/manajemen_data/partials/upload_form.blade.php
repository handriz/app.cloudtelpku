{{-- 
  File: resources/views/admin/manajemen_data/partials/upload_form.blade.php
  Struktur baru: Form -> div.p-6 -> Header, Body, Footer
--}}

<form id="upload-form"
    {{-- HAPUS: data-custom-handler="true" untuk menghindari penangkapan oleh handleGlobalSubmit --}}
    data-chunk-url="{{ route('admin.manajemen_data.upload.chunk') }}"
    data-merge-url="{{ route('admin.manajemen_data.merge.chunks') }}">

    <div class="p-6 space-y-4">
        
        {{-- ====================================================== --}}
        {{-- HEADER MODAL --}}
        {{-- ====================================================== --}}
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Upload Data Pelanggan Massal
            </h2>
            <button type="button" data-modal-close class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                 <i class="fas fa-times fa-lg"></i>
            </button>
        </div>
        <hr class="border-gray-200 dark:border-gray-700">

        {{-- ====================================================== --}}
        {{-- BODY MODAL (Isian Form) --}}
        {{-- ====================================================== --}}
        <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Unggah file hanya format CSV UTF-8 (**Semicolon ; delimited**). Pastikan nama header kolom di file Anda sudah sesuai dengan template yang dibutuhkan oleh sistem.
            </p>
            
            {{-- Dropzone --}}
            <div id="dropzone" class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center transition-colors duration-200">
                <input type="file" id="file-input" class="hidden" accept=".csv,text/csv">
                <label for="file-input" class="cursor-pointer">
                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400"></i>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Pilih file atau seret ke sini</p>
                </label>
                <div id="file-name" class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-200"></div>
            </div>
            
            {{-- Progress Bar & Status --}}
            <div id="progress-container" class="mt-4 hidden">
                <div class="w-full bg-gray-200 rounded-full dark:bg-gray-700">
                    <div id="progress-bar" class="bg-indigo-600 text-xs font-medium text-blue-100 text-center p-0.5 leading-none rounded-full" style="width: 0%">0%</div>
                </div>
            </div>
            <div id="status-message" class="mt-2 text-sm"></div>
        </div>

        {{-- ====================================================== --}}
        {{-- FOOTER MODAL (Tombol Aksi) --}}
        {{-- ====================================================== --}}
        <hr class="border-gray-200 dark:border-gray-700">
        <div class="flex justify-end space-x-2">
            {{-- Tombol Batal standar --}}
            <button type="button" data-modal-close class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                Batal
            </button>
            
            {{-- Tombol Aksi --}}
            <a href="{{ route('admin.manajemen_data.download-format') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                <i class="fas fa-download mr-2"></i> Download Format
            </a>
            <button type="button" 
                    id="start-chunk-upload" 
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 opacity-50 cursor-not-allowed" disabled>
                    <i class="fas fa-upload mr-2"></i> Mulai Upload
            </button>
        </div>

    </div>
</form>