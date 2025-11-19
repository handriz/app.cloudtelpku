{{-- 
  File: _repair_search_modal.blade.php
  Ini adalah konten modal pencarian perbaikan data.
--}}
<div class="p-6">
    <div class="flex justify-between items-start">
        <div>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Cari Data Untuk Perbaikan</h3>
            <p class="text-sm text-gray-500">Masukkan IDPEL atau OBJECTID untuk diperbaiki.</p>
        </div>
        <button type="button" data-modal-close class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            <i class="fas fa-times fa-lg"></i>
        </button>
    </div>

    <div class="mt-4 border-t dark:border-gray-700 pt-4">
        
        {{-- 
          Form ini akan kita buat berfungsi di Fase 2.
          Kita akan membuat 'tab-manager.js' menangani 'repair-search-form'
        --}}
        <form id="repair-search-form" 
              action="{{ route('team.validation_recap.repair.search') }}" 
              method="POST"> 
            @csrf 
            
            <div>
                <label for="search_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">IDPEL / OBJECTID</label>
                <input type="text" name="search_id" id="search_id"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                       placeholder="Masukkan IDPEL (12 digit) atau OBJECTID..."
                       required>
            </div>

            <div class="mt-4 flex justify-end">
                <button type="submit" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-md font-semibold text-xs uppercase hover:bg-blue-700 transition">
                    <i class="fas fa-search mr-1"></i> Cari Data
                </button>
            </div>
        </form>

        {{-- Wadah ini akan kita isi dengan hasil pencarian (form edit) di Fase 2 --}}
        <div id="repair-results-container" class="mt-4">
            {{-- Hasil pencarian akan dimuat di sini oleh AJAX --}}
        </div>

    </div>
</div>