<div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">
            <i class="fas fa-sliders-h mr-2 text-indigo-600"></i> Pengaturan Aplikasi
        </h3>
    </div>

    {{-- DATA FETCHING & INISIALISASI AMAN --}}
    @php
        // --- AMBIL DATA KDDK DENGAN PRIORITAS AMAN ---
        // Gunakan variabel $kddkConfig yang sudah diproses di Controller (Lokal > Global)
        $kddkData = $kddkConfig ?? []; 
        
        // INISIALISASI VARIABEL FINAL
        $areas = $kddkData['areas'] ?? [];
        $routes = $kddkData['routes'] ?? [];
        $routeFormat = $kddkData['route_format'] ?? 'ALPHA';
        
        // --- AMBIL DATA UMUM (Tetap dari $settings collection) ---
        $settingsGrouped = $settings->keyBy('group');
        $genSettings = $settingsGrouped->get('general') ?? collect(); 
        
        $activePeriodRow = $genSettings->where('key', 'kddk_active_period')->first();
        $activePeriodValue = $activePeriodRow ? $activePeriodRow->value : date('Y-m'); 
    @endphp

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
        </div>


        {{-- KONTEN TAB: KDDK CONFIG (MASTER AREA LIST) --}}
        <div id="content-tab-kddk" class="setting-tab-content hidden">
            
            <input type="hidden" name="settings[kddk_config_data][route_format]" value="ALPHA">
            
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Daftar Kode Area (Digit 4 & 5)</label>
                
                <table class="w-full text-sm text-left border border-gray-300 rounded-lg overflow-hidden">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2 w-32">KODE</th>
                            <th class="px-3 py-2">KETERANGAN</th>
                            <th class="px-3 py-2 w-32 text-center">KELOLA RUTE</th>
                            <th class="px-3 py-2 w-16 text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody id="area-rows-container">
                        @foreach($areas as $idx => $area)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition border-b border-gray-200 dark:border-gray-700">
                            <td class="p-2">
                                <input type="text" name="settings[kddk_config_data][areas][{{ $idx }}][code]" value="{{ $area['code'] }}" maxlength="2" class="w-full text-center font-bold uppercase rounded border-gray-300 dark:bg-gray-800 dark:text-white" required>
                            </td>
                            <td class="p-2">
                                <input type="text" name="settings[kddk_config_data][areas][{{ $idx }}][label]" value="{{ $area['label'] }}" class="w-full rounded border-gray-300 dark:bg-gray-800 dark:text-white" placeholder="Keterangan Area" required>
                            </td>
                            {{-- TOMBOL KELOLA RUTE --}}
                            <td class="p-2 text-center">
                                <button type="button" onclick="window.manageAreaRoutes('{{ $area['code'] }}')" 
                                        class="text-white bg-indigo-500 hover:bg-indigo-600 px-3 py-1 rounded text-xs font-bold shadow transition">
                                    <i class="fas fa-list mr-1"></i> RUTE ({{ count($area['routes'] ?? []) }})
                                </button>
                            </td>
                            <td class="p-2 text-center">
                                <button type="button" class="text-red-600 hover:text-red-800 remove-row-btn"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <button type="button" id="add-area-row-btn" class="mt-3 text-sm text-white bg-indigo-600 hover:bg-indigo-700 font-bold flex items-center px-4 py-2 rounded shadow transition">
                    <i class="fas fa-plus mr-2"></i> Tambah Kode Area Baru
                </button>
            </div>
        </div>

        <div id="ajax-errors" class="mt-4 hidden p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg border border-red-400 dark:bg-red-200 dark:text-red-900 dark:border-red-500" role="alert">
        </div>
        
        <div class="mt-8 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-end">
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded shadow hover:bg-indigo-700 transition transform hover:scale-105">
                Simpan Perubahan
            </button>
        </div>
    </form>
</div>