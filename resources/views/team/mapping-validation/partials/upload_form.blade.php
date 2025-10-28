{{-- Hapus padding 'pt-2' dari div pembungkus utama --}}
<div>
    {{-- Pindahkan padding 'p-6' ke div ini --}}
    <div class="bg-white dark:bg-gray-800 w-full p-6">

        {{-- 1. PINDAHKAN HEADER KE DALAM DIV INI --}}
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                Upload Data Mapping Kolektif
            </h2>
            <button type="button" data-modal-close class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                 <i class="fas fa-times fa-lg"></i>
            </button>
        </div>

        {{-- 2. PINDAHKAN HR KE DALAM DIV INI --}}
        <hr class="border-gray-200 dark:border-gray-700 my-2">

        {{-- 3. Sisa konten Anda sekarang akan sejajar --}}
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            Unggah file format CSV dengan delimiter titik koma (;). Pastikan nama header kolom di file Anda sesuai dengan template format.
        </p>
        
        <form id="upload-form"
            data-custom-handler="true"
            data-chunk-url="{{ route('team.mapping_validation.upload.chunk') }}"
            data-merge-url="{{ route('team.mapping_validation.merge.chunks') }}">
            
            <div id="dropzone" class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center transition-colors duration-200">
                <input type="file" id="file-input" class="hidden" accept=".csv,text/csv">
                <label for="file-input" class="cursor-pointer">
                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400"></i>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Pilih file atau seret ke sini</p>
                </label>
                <div id="file-name" class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-200"></div>
            </div>
            
            <div class="mt-4 flex items-center space-x-2">
                <button type="submit" id="upload-button" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                    <i class="fas fa-upload mr-2"></i> Mulai Upload
                </button>
                <a href="{{ route('team.mapping.download-format') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    <i class="fas fa-download mr-2"></i> Download Format
                </a>                
            </div>
        </form>

        <div id="progress-container" class="mt-4 hidden">
            <div class="w-full bg-gray-200 rounded-full dark:bg-gray-700">
                <div id="progress-bar" class="bg-indigo-600 text-xs font-medium text-blue-100 text-center p-0.5 leading-none rounded-full" style="width: 0%">0%</div>
            </div>
        </div>
        <div id="status-message" class="mt-2 text-sm"></div>
        
    </div>
</div>