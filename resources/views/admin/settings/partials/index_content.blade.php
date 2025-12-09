{{-- DATA FETCHING & INISIALISASI --}}
    @php
        $kddkData = $kddkConfig ?? []; 
        $areas = $kddkData['areas'] ?? [];
        
        $settingsGrouped = $settings->keyBy('group');
        $genSettings = $settingsGrouped->get('general') ?? collect(); 
        
        $activePeriodRow = $genSettings->where('key', 'kddk_active_period')->first();
        $activePeriodValue = $activePeriodRow ? $activePeriodRow->value : date('Y-m'); 

        // --- DATA PENTING UNTUK JS AUTO-SEQUENCE ---
        $isAdminRole = auth()->user()->hasRole('admin');
        $isAdminStr = $isAdminRole ? 'true' : 'false';
        // Ambil list kode existing untuk perhitungan JS (AA, AB, AC...)
        $existingCodes = collect($areas)->pluck('code')->sort()->values()->toJson();
    @endphp

    {{-- CONTAINER UTAMA (Dengan Data Context untuk JS) --}}
    <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6"
         id="settings-main-container"
         data-is-admin="{{ $isAdminStr }}"
         data-existing-codes="{{ htmlspecialchars($existingCodes, ENT_QUOTES, 'UTF-8') }}">

        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                <i class="fas fa-sliders-h mr-2 text-indigo-600"></i> Pengaturan Aplikasi
            </h3>
        </div>

        {{-- Notifikasi Sukses --}}
        <div id="kddk-notification-container">
            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <strong class="font-bold">Berhasil!</strong>
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif
        </div>

        {{-- Indikator Scope --}}
        <div class="mb-6 p-3 rounded-lg flex items-center shadow-sm {{ $currentScope === 'global' ? 'bg-indigo-50 border border-indigo-200' : 'bg-yellow-50 border border-yellow-200' }}">
            <i class="fas {{ $currentScope === 'global' ? 'fa-globe text-indigo-600' : 'fa-map-marker-alt text-yellow-700' }} mr-2"></i>
            <span class="text-sm font-medium">
                Anda saat ini mengelola pengaturan **{{ $currentScope === 'global' ? 'GLOBAL' : 'LOKAL' }}**. 
                Perubahan Anda {{ $currentScope === 'global' ? 'berlaku untuk semua unit.' : 'hanya berlaku untuk Unit Anda.' }}
            </span>
        </div>

        {{-- FORM UTAMA --}}
        <form action="{{ route('admin.settings.update') }}" method="POST" class="ajax-form" data-success-redirect-tab="Pengaturan">
            @csrf

            {{-- TAB NAVIGATION --}}
            <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
                    <li class="mr-2">
                        <button class="tab-toggle-btn inline-block p-4 text-indigo-600 border-b-2 border-indigo-600 rounded-t-lg active dark:text-indigo-500 dark:border-indigo-500" 
                                type="button" data-target="general">
                            <i class="fas fa-cube mr-2"></i> Umum
                        </button>
                    </li>
                    <li class="mr-2">
                        <button class="tab-toggle-btn inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300" 
                                type="button" data-target="kddk">
                            <i class="fas fa-map-marked-alt mr-2"></i> Konfigurasi KDDK
                        </button>
                    </li>
                </ul>
            </div>

            {{-- KONTEN TAB: UMUM --}}
            <div id="content-tab-general" class="setting-tab-content">
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Periode Data Aktif (Untuk Matriks)</label>
                    <input type="month" name="settings[kddk_active_period]" value="{{ $activePeriodValue }}" 
                           class="w-full md:w-1/3 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                    <p class="text-xs text-gray-500 mt-1">Menentukan bulan dan tahun data yang akan difilter di Matrix KDDK.</p>
                </div>
                @if($isAdminRole)
                <hr class="my-6 border-gray-200 dark:border-gray-700">

                {{-- MAINTENANCE SECTION --}}
                <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg border border-red-200 dark:border-red-800">
                    <h4 class="text-sm font-bold text-red-800 dark:text-red-300 flex items-center mb-3">
                        <i class="fas fa-tools mr-2"></i> Pemeliharaan Data (Audit Log)
                    </h4>
                    
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="text-xs text-red-600 dark:text-red-400">
                            Hapus riwayat aktivitas lama untuk menghemat kapasitas penyimpanan server.<br>
                            Data yang dihapus <strong>tidak dapat dikembalikan</strong>.
                        </div>

                        <div class="flex items-center space-x-2">
                            {{-- Opsi Hapus --}}
                            <select id="audit-prune-days" class="text-xs rounded border-red-300 bg-white text-gray-700 focus:ring-red-500 py-1.5">
                                <option value="30">Lebih dari 1 Bulan (30 Hari)</option>
                                <option value="60" selected>Lebih dari 2 Bulan (60 Hari)</option>
                                <option value="90">Lebih dari 3 Bulan (90 Hari)</option>
                                <option value="all" class="font-bold text-red-600">⚠ HAPUS SEMUA DATA</option>
                            </select>

                            <button type="button" onclick="window.clearAuditLogs()" 
                                    class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded shadow transition flex items-center whitespace-nowrap">
                                <i class="fas fa-trash-alt mr-1.5"></i> Bersihkan
                            </button>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            {{-- KONTEN TAB: KDDK CONFIG --}}
            <div id="content-tab-kddk" class="setting-tab-content hidden">
                
                <input type="hidden" name="settings[kddk_config_data][route_format]" value="ALPHA">
                
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Daftar Kode Area Baca (Digit ke 4 & 5 pada KDDK)</label>
                    
                    <div class="overflow-x-auto border border-gray-300 rounded-lg">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3 w-24 text-center">KODE</th>
                                    <th class="px-4 py-3 text-center ">KETERANGAN (DESKRIPSI PENAMAAN AREA BACA)</th>
                                    <th class="px-4 py-3 w-40 text-center">KELOLA RUTE</th>
                                    <th class="px-4 py-3 w-20 text-center">AKSI</th>
                                </tr>
                            </thead>
                            <tbody id="area-rows-container" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($areas as $idx => $area)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                    
                                    {{-- Kode Area --}}
                                    <td class="p-3 text-center">
                                        <input type="text" 
                                               name="settings[kddk_config_data][areas][{{ $idx }}][code]" 
                                               value="{{ $area['code'] }}" 
                                               maxlength="2" 
                                               class="w-16 text-center font-bold uppercase rounded-md border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-indigo-500 py-2 {{ $isAdminRole ? '' : 'bg-gray-100 cursor-not-allowed text-gray-500' }}" 
                                               {{ $isAdminRole ? '' : 'readonly' }}
                                               required>
                                    </td>
                                    
                                    {{-- Label --}}
                                    <td class="p-3">
                                        <input type="text" 
                                               name="settings[kddk_config_data][areas][{{ $idx }}][label]" 
                                               value="{{ $area['label'] }}" 
                                               class="w-full rounded-md border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-indigo-500 py-2 px-3" 
                                               placeholder="Keterangan Area" 
                                               required>
                                    </td>

                                    {{-- Tombol Rute --}}
                                    <td class="p-3 text-center">
                                        <button type="button" onclick="window.manageAreaRoutes('{{ $area['code'] }}')" 
                                                class="inline-flex items-center justify-center px-3 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 rounded-md text-xs font-bold transition shadow-sm whitespace-nowrap">
                                            <i class="fas fa-list mr-1.5"></i> RUTE ({{ count($area['routes'] ?? []) }})
                                        </button>
                                    </td>
                                    
                                    {{-- Tombol Hapus (Class btn-delete-parent untuk JS Handler) --}}
                                    <td class="p-3 text-center">
                                        <button type="button" class="text-red-500 hover:text-red-700 p-2 rounded-full hover:bg-red-50 dark:hover:bg-red-900/20 transition btn-delete-parent">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    {{-- Tombol Tambah --}}
                    <button type="button" id="add-area-row-btn" class="mt-3 text-sm text-white bg-indigo-600 hover:bg-indigo-700 font-bold flex items-center px-4 py-2 rounded shadow transition">
                        <i class="fas fa-plus mr-2"></i> Tambah Kode Area Baru
                    </button>
                </div>
            </div>

            {{-- WADAH ERROR AJAX --}}
            <div id="ajax-errors" class="mt-4 hidden p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg border border-red-400 dark:bg-red-200 dark:text-red-900 dark:border-red-500" role="alert">
            </div>
            
            {{-- FOOTER TOMBOL SIMPAN --}}
            <div class="mt-8 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded shadow hover:bg-indigo-700 transition transform hover:scale-105">
                    Simpan Perubahan
                </button>
            </div>
        </form>

        {{-- MODAL KONFIRMASI HAPUS (CUSTOM) --}}
        <div id="custom-confirm-modal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-gray-900 bg-opacity-50 backdrop-blur-sm p-4 transition-opacity">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-sm transform scale-100 transition-transform overflow-hidden">
                
                {{-- Header Warning --}}
                <div class="bg-red-50 dark:bg-red-900/20 p-4 flex items-center border-b border-red-100 dark:border-red-800">
                    <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-800 flex items-center justify-center text-red-600 dark:text-red-200 mr-3 shrink-0">
                        <i class="fas fa-exclamation-triangle text-lg"></i>
                    </div>
                    <h3 id="custom-confirm-title" class="text-lg font-bold text-red-700 dark:text-red-300">Konfirmasi Hapus</h3>
                </div>

                {{-- Body Pesan --}}
                <div class="p-6">
                    <p id="custom-confirm-message" class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                        Apakah Anda yakin ingin menghapus data ini?
                    </p>
                </div>

                {{-- Footer Tombol --}}
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 flex justify-end space-x-2 border-t border-gray-100 dark:border-gray-600">
                    <button type="button" id="custom-confirm-cancel" class="px-4 py-2 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg text-gray-700 dark:text-gray-200 text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-200 transition">
                        Batal
                    </button>
                    <button type="button" id="custom-confirm-ok" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-bold shadow-md transition transform active:scale-95 focus:outline-none focus:ring-2 focus:ring-red-500">
                        Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>