<form id="create-mapping-form" action="{{ route('team.mapping.store') }}" method="POST" enctype="multipart/form-data" autocomplete="off"  data-upload-photo-url="{{ route('team.mapping.upload-photo') }}"
        data-success-redirect-tab="Validasi Pendataan" 
        data-success-redirect-url="{{ route('team.mapping_validation.index') }}">
    @csrf
    <div class="p-6 space-y-4">
        {{-- Header, Body, dan Footer digabung dalam satu div --}}
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                Tambah Data Pemetaan Pelanggan
            </h2>
            <button type="button" data-modal-close class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                 <i class="fas fa-times fa-lg"></i>
            </button>
        </div>
        <hr class="border-gray-200 dark:border-gray-700">

        <div id="create-mapping-errors" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative"></div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-4">
            <div>
                <label for="idpel_create" class="block text-sm font-medium text-gray-700 dark:text-gray-300">ID Pelanggan</label>
                <div class="relative mt-1">
                    <input type="text" name="idpel" id="idpel_create" class="block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 pr-10" required maxlength="12">
                    {{-- Tempat untuk ikon loading/status --}}
                    <div id="idpel-status-icon" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none hidden">
                        <i class="fas fa-spinner fa-spin text-gray-400"></i>
                        {{-- Kita akan ganti ikon ini via JS --}}
                    </div>
                </div>
                {{-- Tempat untuk pesan status --}}
                <p id="idpel-status-message" class="mt-1 text-xs text-gray-500 dark:text-gray-400 h-4"></p> {{-- Beri tinggi agar layout tidak lompat --}}
            </div>
                <div>
                    <label for="user_pendataan_create" class="block text-sm font-medium text-gray-700 dark:text-gray-300">User Pendataan</label>
                    <input type="hidden" name="user_pendataan" value="{{ Auth::user()->name }}">
                    <div class="mt-1 block w-full px-3 py-2 rounded-md border border-gray-300 bg-gray-100 dark:bg-gray-800 dark:border-gray-600">
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ Auth::user()->name }}</p>
                    </div>
                </div>
                <div>
                    <label for="latitudey_create" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Latitude (Y)</label>
                    <input type="text" name="latitudey" id="latitudey_create" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600" placeholder="Contoh: 0.5071" required>
                </div>
                <div>
                    <label for="longitudex_create" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Longitude (X)</label>
                    <input type="text" name="longitudex" id="longitudex_create" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600" placeholder="Contoh: 101.4478" required>
                </div>
            </div>
{{-- Pratinjau Lokasi (Struktur Tab Baru) --}}
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Pratinjau Lokasi</label>
                
                {{-- Tab Headers --}}
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex -mb-px space-x-4" aria-label="Tabs">
                        {{-- Tombol Tab Peta Satelit (Default Aktif) --}}
                        <button type="button" id="tab-btn-map"
                           class="tab-preview-button whitespace-nowrap py-2 px-3 border-b-2 font-medium text-sm border-indigo-500 text-indigo-600 dark:text-indigo-400">
                           Peta Satelit
                        </button>
                        {{-- Tombol Tab Street View --}}
                        <button type="button" id="tab-btn-streetview"
                           class="tab-preview-button whitespace-nowrap py-2 px-3 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300">
                           Street View
                        </button>
                    </nav>
                </div>

                {{-- Tab Panels --}}
                <div class="pt-2">
                    {{-- Panel Peta Satelit (Default Terlihat) --}}
                    <div id="tab-panel-map" class="tab-preview-panel w-full h-64 rounded-lg z-0">
                         <div id="preview-map" class="w-full h-full rounded-lg z-0"></div>
                    </div>
                    
                    {{-- Panel Street View (Default Tersembunyi) --}}
                    <div id="tab-panel-streetview" class="tab-preview-panel w-full h-64 rounded-lg z-0 hidden">
                        {{-- Iframe untuk Street View --}}
                        <iframe id="create-street-view-iframe"
                                src=""
                                class="w-full h-full rounded-lg border-0"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                allowfullscreen>
                        </iframe>
                        {{-- Placeholder jika Lat/Lon kosong --}}
                        <div id="create-street-view-placeholder" class="flex items-center justify-center w-full h-full bg-gray-100 dark:bg-gray-800 rounded-lg">
                            <p class="text-gray-500 dark:text-gray-400">masukkan Latitude (Y)/Longitude (X) untuk melihat</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4">
            <div>
                <label for="foto_kwh_create" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Foto KWH Meter</label>
                <input type="file" name="foto_kwh_input" id="foto_kwh_create" class="photo-upload-input mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400">
                <input type="hidden" name="foto_kwh" id="foto_kwh_filename">
                <div id="foto_kwh_progress_container" class="mt-2 w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700 hidden">
                    <div id="foto_kwh_progress_bar" class="bg-blue-600 h-2.5 rounded-full" style="width: 0%"></div>
                </div>
                <div id="foto_kwh_status" class="text-xs mt-1"></div>
            </div>
            <div>
                <label for="foto_bangunan_create" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Foto Bangunan (APP)</label>
                <input type="file" name="foto_bangunan_input" id="foto_bangunan_create" class="photo-upload-input mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400">
                <input type="hidden" name="foto_bangunan" id="foto_bangunan_filename">
                <div id="foto_bangunan_progress_container" class="mt-2 w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700 hidden">
                    <div id="foto_bangunan_progress_bar" class="bg-blue-600 h-2.5 rounded-full" style="width: 0%"></div>
                </div>
                <div id="foto_bangunan_status" class="text-xs mt-1"></div>
            </div>
        </div>
        <div>
            <label for="ket_survey_create" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Keterangan Survey</label>
            <textarea name="ket_survey" id="ket_survey_create" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600" required></textarea>
        </div>

        <hr class="border-gray-200 dark:border-gray-700">
        <div class="flex justify-end space-x-2">
            <button type="button" data-modal-close class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Batal</button>
            <button type="submit" id="create-mapping-submit-button" data-modal-submit-button class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700" disabled>Simpan Data</button>
        </div>
    </div>
</form>