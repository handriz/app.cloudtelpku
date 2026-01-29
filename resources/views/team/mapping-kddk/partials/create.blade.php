<style>
    /* Scrollbar Kustom & Cantik */
    .custom-scrollbar::-webkit-scrollbar {
        width: 8px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #cbd5e1; /* Warna abu-abu soft */
        border-radius: 20px;       /* Bikin scrollbar BULAT */
        border: 3px solid transparent;
        background-clip: content-box;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background-color: #94a3b8;
    }
</style>

<form id="create-mapping-form" action="{{ route('team.mapping.store') }}" method="POST" enctype="multipart/form-data" autocomplete="off" 
    data-upload-photo-url="{{ route('team.mapping.upload-photo') }}">
    @csrf
    <div class="p-6 space-y-4 max-h-[85vh] overflow-y-auto custom-scrollbar">
        {{-- Header Modal --}}
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                Tambah Data Pemetaan Pelanggan
            </h2>
            <button type="button" data-modal-close class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                 <i class="fas fa-times fa-lg"></i>
            </button>
        </div>
        <hr class="border-gray-200 dark:border-gray-700">

        {{-- Area Pesan Error AJAX --}}
        <div id="create-mapping-errors" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative"></div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- KOLOM KIRI: INPUT DATA --}}
            <div class="space-y-4">
                <div>
                    <label for="idpel_create" class="block text-sm font-medium text-gray-700 dark:text-gray-300">ID Pelanggan</label>
                    <div class="relative mt-1">
                        <input type="text" name="idpel" id="idpel_create" class="block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 pr-10" required maxlength="12">
                        {{-- Ikon Loading Status IDPEL --}}
                        <div id="idpel-status-icon" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none hidden">
                            <i class="fas fa-spinner fa-spin text-gray-400"></i>
                        </div>
                    </div>
                    <p id="idpel-status-message" class="mt-1 text-xs text-gray-500 dark:text-gray-400 h-4"></p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">User Pendataan</label>
                    <input type="hidden" name="user_pendataan" value="{{ Auth::user()->name }}">
                    <div class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 bg-gray-100 dark:bg-gray-800 dark:border-gray-600">
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ Auth::user()->name }}</p>
                    </div>
                </div>

                {{-- Input Latitude & Longitude dengan Event Listener Inline --}}
                <div>
                    <label for="latitudey_create" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Latitude (Y)</label>
                    <input type="text" name="latitudey" id="latitudey_create" 
                           oninput="if(window.updateExternalStreetView) window.updateExternalStreetView(); if(window.updatePreviewMarker) window.updatePreviewMarker(this.value, document.getElementById('longitudex_create').value);"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600" 
                           placeholder="Contoh: 0.5071" required>
                </div>
                <div>
                    <label for="longitudex_create" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Longitude (X)</label>
                    <input type="text" name="longitudex" id="longitudex_create" 
                           oninput="if(window.updateExternalStreetView) window.updateExternalStreetView(); if(window.updatePreviewMarker) window.updatePreviewMarker(document.getElementById('latitudey_create').value, this.value);"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600" 
                           placeholder="Contoh: 101.4478" required>
                </div>
            </div>

            {{-- KOLOM KANAN: PRATINJAU LOKASI --}}
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Pratinjau Lokasi</label>
                
                {{-- Tab Headers --}}
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex -mb-px space-x-4" aria-label="Tabs">
                        <button type="button" id="tab-btn-map"
                           class="tab-preview-button whitespace-nowrap py-2 px-3 border-b-2 font-medium text-sm border-indigo-500 text-indigo-600 dark:text-indigo-400">
                           Peta Satelit
                        </button>
                        <button type="button" id="tab-btn-streetview"
                           class="tab-preview-button whitespace-nowrap py-2 px-3 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300">
                           Street View
                        </button>
                    </nav>
                </div>

                {{-- Tab Panels --}}
                <div class="pt-2">
                    {{-- Panel Peta Satelit (Wadah Kosong untuk Leaflet) --}}
                    <div id="tab-panel-map" class="tab-preview-panel w-full h-64 rounded-lg z-0 bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 relative">
                         <div id="preview-map" class="w-full h-full rounded-lg z-0"></div>
                         
                         <div id="map-drag-hint" class="hidden absolute bottom-4 left-0 right-0 mx-auto w-max max-w-[90%] bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm border border-indigo-200 dark:border-gray-600 px-3 py-2 rounded-full shadow-lg text-xs text-indigo-700 dark:text-indigo-300 animate-bounce-slow z-[400] flex items-center gap-2">
                            <i class="fas fa-hand-pointer text-indigo-500"></i>
                            <span><b>Tips:</b> Geser (Drag) Pin Marker untuk posisi lebih akurat.</span>
                         </div>
                    </div>
                    
                    {{-- Panel Street View (Link Eksternal) --}}
                    <div id="tab-panel-streetview" class="tab-preview-panel w-full h-64 rounded-lg z-0 hidden bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex flex-col items-center justify-center text-center p-6">
                        
                        <div class="mb-3 p-3 bg-orange-100 text-orange-600 rounded-full">
                            <i class="fas fa-street-view text-2xl"></i>
                        </div>

                        <h4 class="text-gray-900 dark:text-gray-100 font-bold text-sm mb-1">Pratinjau Street View</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4 max-w-xs">
                            Klik tombol di bawah untuk membuka Google Street View di tab baru.
                        </p>

                        {{-- Tombol Buka Street View --}}
                        <a id="btn-open-streetview-external" href="#" target="_blank" 
                           class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-bold rounded-lg shadow transition transform active:scale-95 pointer-events-none opacity-50">
                            <i class="fas fa-external-link-alt mr-2"></i> Buka Google Street View
                        </a>
                        
                        <p id="streetview-warning" class="text-[10px] text-red-500 mt-2">
                            *Masukkan Latitude & Longitude terlebih dahulu
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- AREA UPLOAD FOTO --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4">
            {{-- Foto KWH --}}
            <div>
                <label for="foto_kwh_create" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Foto KWH Meter</label>
                <input type="file" name="foto_kwh_input" id="foto_kwh_create" class="photo-upload-input mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400">
                <input type="hidden" name="foto_kwh" id="foto_kwh_filename">
                
                {{-- Progress Bar --}}
                <div id="foto_kwh_progress_container" class="mt-2 w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700 hidden">
                    <div id="foto_kwh_progress_bar" class="bg-blue-600 h-2.5 rounded-full" style="width: 0%"></div>
                </div>
                <div id="foto_kwh_status" class="text-xs mt-1"></div>
            </div>

            {{-- Foto Bangunan --}}
            <div>
                <label for="foto_bangunan_create" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Foto Bangunan (APP)</label>
                <input type="file" name="foto_bangunan_input" id="foto_bangunan_create" class="photo-upload-input mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400">
                <input type="hidden" name="foto_bangunan" id="foto_bangunan_filename">
                
                {{-- Progress Bar --}}
                <div id="foto_bangunan_progress_container" class="mt-2 w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700 hidden">
                    <div id="foto_bangunan_progress_bar" class="bg-blue-600 h-2.5 rounded-full" style="width: 0%"></div>
                </div>
                <div id="foto_bangunan_status" class="text-xs mt-1"></div>
            </div>
        </div>

        {{-- KETERANGAN --}}
        <div>
            <label for="ket_survey_create" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Keterangan Survey</label>
            <textarea name="ket_survey" id="ket_survey_create" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600" required></textarea>
        </div>

        <hr class="border-gray-200 dark:border-gray-700">
        
        {{-- TOMBOL AKSI --}}
        <div class="flex justify-end space-x-2">
            <button type="button" data-modal-close class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Batal</button>
            <button type="submit" id="create-mapping-submit-button" data-modal-submit-button class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700" disabled>Simpan Data</button>
        </div>
    </div>
</form>