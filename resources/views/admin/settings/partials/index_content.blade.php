{{-- 
    SETTINGS DASHBOARD CONTENT
    Fitur:
    1. Tab Navigation (Reordered: Area First)
    2. Atomic Auto-Save Inputs (Generic)
    3. Area & Route Management
--}}

@php
    $isAdmin = Auth::user()->hasRole('admin');
    
    // Style Helper
    $activeClass = 'border-b-2 font-bold text-sm border-indigo-500 text-indigo-600 dark:text-indigo-400';
    $inactiveClass = 'border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300';
@endphp

<div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-hidden h-full flex flex-col">

    {{-- A. TAB NAVIGATION --}}
    <div class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 flex-none">
        <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">

            {{-- URUTAN 1: AREA & RUTE (DEFAULT ACTIVE UNTUK SEMUA) --}}
            <button type="button" data-target="areas"
                class="tab-toggle-btn group inline-flex items-center py-4 px-1 {{ $activeClass }}">
                <i class="fas fa-map-marked-alt mr-2"></i> Area & Rute
            </button>

            {{-- URUTAN 2: PARAMETERS (SEMUA USER) --}}
            <button type="button" data-target="parameters"
                class="tab-toggle-btn group inline-flex items-center py-4 px-1 {{ $inactiveClass }}">
                <i class="fas fa-cog mr-2"></i> Parameter Logika
            </button>

            {{-- URUTAN 3: UMUM (KHUSUS ADMIN) --}}
            @if($isAdmin)
                <button type="button" data-target="general"
                    class="tab-toggle-btn group inline-flex items-center py-4 px-1 {{ $inactiveClass }}">
                    <i class="fas fa-sliders-h mr-2"></i> Umum & Periode
                </button>
            @endif

            {{-- URUTAN 4: SISTEM (KHUSUS ADMIN) --}}
            @if($isAdmin)
                <button type="button" data-target="system"
                    class="tab-toggle-btn group inline-flex items-center py-4 px-1 {{ $inactiveClass }}">
                    <i class="fas fa-server mr-2"></i> Konfigurasi Sistem
                </button>
            @endif

        </nav>
    </div>

    {{-- B. TAB CONTENTS --}}
    <div class="p-6 flex-1 overflow-y-auto custom-scrollbar">

        {{-- ==================================================== --}}
        {{-- CONTENT 1: AREA & RUTE (DEFAULT VISIBLE)             --}}
        {{-- ==================================================== --}}
        <div id="content-tab-areas" class="setting-tab-content pb-16">

            {{-- HEADER & FORM --}}
            <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm mb-6 sticky top-0 z-20">
                <div class="flex flex-col md:flex-row justify-between items-end md:items-center gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <i class="fas fa-map-marked-alt text-indigo-500"></i> Manajemen Area
                        </h3>
                        <p class="text-xs text-gray-500" id="area-form-title">Kelola wilayah baca dan rute petugas</p>
                    </div>

                    {{-- FORM INPUT --}}
                    <div class="flex flex-wrap gap-3 items-end">
                        {{-- Kategori --}}
                        <div>
                            <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">Kategori</label>
                            <input type="text" id="new-area-category" list="category-suggestions" placeholder="Cth: PASKABAYAR"
                                class="w-56 text-sm rounded-lg border-gray-300 dark:bg-gray-700 dark:text-white px-3 py-2 focus:ring-indigo-500 shadow-sm">
                            <datalist id="category-suggestions">
                                <option value="PASKABAYAR"><option value="PRABAYAR"><option value="AMR"><option value="ASSET"><option value="TEMPORARY">
                            </datalist>
                        </div>
                        {{-- Kode --}}
                        <div>
                            <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">Kode</label>
                            <input type="text" id="new-area-code" placeholder="AA" maxlength="2"
                                class="w-20 text-sm rounded-lg border-gray-300 dark:bg-gray-700 dark:text-white uppercase font-bold text-center px-2 py-2 shadow-sm disabled:bg-gray-100 disabled:text-gray-500"
                                oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '')">
                        </div>
                        {{-- Nama --}}
                        <div>
                            <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">Nama Area</label>
                            <input type="text" id="new-area-label" placeholder="Area Timur"
                                class="w-72 text-sm rounded-lg border-gray-300 dark:bg-gray-700 dark:text-white px-3 py-2 shadow-sm">
                        </div>
                        {{-- Action Buttons --}}
                        <div class="flex gap-2 h-[38px]">
                            <button type="button" id="btn-add-area" onclick="window.settingsHandler.addNewArea()"
                                class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-bold shadow-md hover:shadow-lg transition flex items-center gap-2">
                                <i class="fas fa-plus"></i> Tambah
                            </button>
                            <button type="button" id="btn-update-area" onclick="window.settingsHandler.submitUpdateArea()"
                                class="hidden px-5 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-bold shadow-md animate-pulse flex items-center gap-2">
                                <i class="fas fa-save"></i> Simpan
                            </button>
                            <button type="button" id="btn-cancel-edit" onclick="window.settingsHandler.cancelEditArea()"
                                class="hidden px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg text-sm font-bold transition border border-gray-300">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Search Bar --}}
                <div class="mt-5 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <div class="relative group">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 group-focus-within:text-indigo-500 transition-colors">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" id="area-search-filter"
                            class="w-full pl-10 pr-4 py-2 text-sm bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all"
                            placeholder="Cari Kode atau Nama Area...">
                    </div>
                </div>
            </div>

            @php
                $userScope = $isAdmin ? null : Auth::user()->hierarchy_level_code;
                $kddkConfig = App\Models\AppSetting::findValue('kddk_config_data', $userScope, ['areas' => []]);
                $allAreas = collect($kddkConfig['areas'] ?? []);
                $groupedAreas = $allAreas->map(function ($item) {
                        $item['category'] = $item['category'] ?? 'UMUM';
                        return $item;
                    })->groupBy('category')->sortKeys();
            @endphp

            <div id="area-groups-container" class="space-y-4">
                @forelse($groupedAreas as $category => $areas)
                    <details class="group bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl overflow-hidden shadow-sm transition-all duration-300 hover:shadow-md open:pb-2" open>
                        <summary class="flex items-center justify-between px-5 py-4 cursor-pointer bg-gray-50/80 dark:bg-gray-800 hover:bg-indigo-50/50 dark:hover:bg-gray-700 select-none transition-colors border-l-4 border-transparent group-open:border-indigo-500">
                            <div class="flex items-center gap-3">
                                <div class="w-6 h-6 rounded-full bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 flex items-center justify-center text-gray-400 group-open:rotate-180 group-open:text-indigo-500 transition-transform duration-300">
                                    <i class="fas fa-chevron-down text-[10px]"></i>
                                </div>
                                <h4 class="font-bold text-gray-700 dark:text-gray-200 text-sm uppercase tracking-wide">{{ $category }}</h4>
                                <span class="text-[10px] bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300 px-2 py-0.5 rounded-md font-bold">{{ count($areas) }}</span>
                            </div>
                        </summary>
                        <div class="p-4 grid gap-3 grid-cols-1 border-t border-gray-100 dark:border-gray-600">
                            @foreach ($areas as $area)
                                <div class="relative flex items-center p-3 rounded-xl border border-gray-100 dark:border-gray-600 bg-white dark:bg-gray-800 hover:border-indigo-300 dark:hover:border-indigo-500 hover:bg-indigo-50/20 transition-all duration-200 group/card area-row-item shadow-sm"
                                     data-search="{{ strtolower($area['code'] . ' ' . ($area['label'] ?? '')) }}">
                                    
                                    <div class="flex-shrink-0 h-12 w-12 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 flex flex-col items-center justify-center border border-gray-200 dark:border-gray-600 group-hover/card:bg-indigo-600 group-hover/card:text-white group-hover/card:border-indigo-600 transition-colors duration-300">
                                        <span class="text-[8px] font-bold uppercase opacity-60">KODE</span>
                                        <span class="text-lg font-extrabold tracking-tighter">{{ $area['code'] }}</span>
                                    </div>

                                    <div class="ml-4 flex-1 min-w-0">
                                        <h5 class="text-sm font-bold text-gray-900 dark:text-white truncate group-hover/card:text-indigo-700 dark:group-hover/card:text-indigo-300 transition-colors">
                                            {{ $area['label'] ?? 'Area ' . $area['code'] }}
                                        </h5>
                                        <p class="text-[11px] text-gray-400 mt-0.5 flex items-center gap-1.5">
                                            <span class="bg-gray-100 dark:bg-gray-700 px-1.5 rounded text-gray-500 dark:text-gray-400">
                                                <i class="fas fa-route"></i> {{ count($area['routes'] ?? []) }} Rute
                                            </span>
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-2 pr-2">
                                        <button onclick="App.Tabs.createTab('Rute [{{ $area['code'] }}]', '{{ route('admin.settings.manage_routes', $area['code']) }}', true)"
                                            class="h-9 px-4 rounded-lg bg-indigo-50 dark:bg-gray-700 text-indigo-700 dark:text-indigo-300 border border-indigo-100 dark:border-gray-600 text-xs font-bold hover:bg-indigo-600 hover:text-white hover:border-indigo-600 shadow-sm transition-all duration-200 flex items-center gap-2 group/btn">
                                            <i class="fas fa-route group-hover/btn:animate-pulse"></i> 
                                            <span class="hidden sm:inline">Kelola Rute</span>
                                        </button>
                                        <div class="h-6 w-px bg-gray-200 dark:bg-gray-600 mx-1"></div>
                                        <button onclick="window.settingsHandler.editAreaMode('{{ $area['code'] }}', '{{ $area['label'] }}', '{{ $area['category'] }}')"
                                            class="h-9 w-9 rounded-lg border border-gray-200 dark:border-gray-600 text-gray-400 hover:text-yellow-600 hover:bg-yellow-50 hover:border-yellow-200 transition-all flex items-center justify-center" title="Edit">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        <button onclick="window.settingsHandler.deleteArea('{{ $area['code'] }}')"
                                            class="h-9 w-9 rounded-lg border border-gray-200 dark:border-gray-600 text-gray-400 hover:text-red-600 hover:bg-red-50 hover:border-red-200 transition-all flex items-center justify-center" title="Hapus">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @empty
                    <div class="flex flex-col items-center justify-center h-64 text-center border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-2xl bg-gray-50/50 dark:bg-gray-800">
                        <div class="w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-map-marked-alt text-gray-400 text-3xl opacity-50"></i>
                        </div>
                        <h4 class="text-gray-600 dark:text-gray-300 font-bold text-lg">Belum ada Area</h4>
                        <p class="text-sm text-gray-400 mt-1 max-w-[250px]">Silakan tambahkan area baru melalui formulir di atas untuk memulai.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ==================================================== --}}
        {{-- CONTENT 2: PARAMETERS (SEMUA USER)                   --}}
        {{-- ==================================================== --}}
        <div id="content-tab-parameters" class="setting-tab-content hidden pb-16">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Logika Peta & Validasi</h3>
                @if ($isAdmin)
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-700 border border-purple-200">
                        <i class="fas fa-globe mr-1"></i> Mode: Global (Default Pusat)
                    </span>
                @else
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700 border border-green-200">
                        <i class="fas fa-map-marker-alt mr-1"></i> Mode: Unit Lokal ({{ Auth::user()->name }})
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Toleransi Anomali --}}
                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Toleransi Anomali (Meter)</label>
                    <input type="number" name="kddk_anomaly_distance" data-group="parameters"
                        @php $scope = $isAdmin ? null : Auth::user()->hierarchy_level_code; @endphp
                        value="{{ App\Models\AppSetting::findValue('kddk_anomaly_distance', $scope, 5000) }}"
                        class="auto-save-input w-full rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 focus:ring-orange-500"
                        onchange="window.settingsHandler.autoSaveSetting(this)">
                    <p class="text-[10px] text-gray-400 mt-1">
                        @if (!$isAdmin)
                            *Settingan ini hanya berlaku untuk Unit Anda. Jika kosong, akan mengikuti nilai UP3.
                        @else
                            *Standar baku untuk seluruh unit (jika unit tidak mengatur sendiri).
                        @endif
                    </p>
                </div>

                {{-- Koordinat Default --}}
                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-700/30">
                    <div class="flex items-center mb-3">
                        <label class="block text-xs font-bold text-gray-500 uppercase flex-1">Pusat Peta Default</label>
                        <button type="button"
                            onclick="navigator.geolocation.getCurrentPosition(pos => { 
                                document.getElementsByName('kddk_default_lat')[0].value = pos.coords.latitude; 
                                document.getElementsByName('kddk_default_lng')[0].value = pos.coords.longitude;
                                document.getElementsByName('kddk_default_lat')[0].dispatchEvent(new Event('change'));
                                document.getElementsByName('kddk_default_lng')[0].dispatchEvent(new Event('change'));
                            })"
                            class="text-[10px] text-blue-600 hover:underline cursor-pointer flex items-center gap-1">
                            <i class="fas fa-crosshairs"></i> Lokasi Saya
                        </button>
                    </div>

                    <div class="flex gap-4">
                        <div class="w-1/2">
                            <span class="block text-[10px] font-bold text-gray-400 mb-1 uppercase tracking-wider">Lat (Y)</span>
                            <input type="text" name="kddk_default_lat" placeholder="-0.900"
                                value="{{ App\Models\AppSetting::findValue('kddk_default_lat', $scope, '-0.900') }}"
                                class="auto-save-input w-full rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 text-sm font-mono"
                                onchange="window.settingsHandler.autoSaveSetting(this)">
                        </div>
                        <div class="w-1/2">
                            <span class="block text-[10px] font-bold text-gray-400 mb-1 uppercase tracking-wider">Lng (X)</span>
                            <input type="text" name="kddk_default_lng" placeholder="101.350"
                                value="{{ App\Models\AppSetting::findValue('kddk_default_lng', $scope, '100.350') }}"
                                class="auto-save-input w-full rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 text-sm font-mono"
                                onchange="window.settingsHandler.autoSaveSetting(this)">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================================================== --}}
        {{-- CONTENT 3: UMUM & PERIODE (ADMIN ONLY)               --}}
        {{-- ==================================================== --}}
        @if($isAdmin)
        <div id="content-tab-general" class="setting-tab-content hidden">

            <div class="flex items-start gap-4 mb-6 bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-100 dark:border-blue-800">
                <div class="text-blue-500 mt-1">
                    <i class="fas fa-info-circle text-xl"></i>
                </div>
                <div>
                    <h4 class="font-bold text-blue-800 dark:text-blue-300 text-sm">Tentang Tab Umum</h4>
                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1 leading-relaxed">
                        Pengaturan di sini bersifat <strong>Universal</strong>. "Periode Referensi Data" menentukan filter waktu global untuk seluruh modul aplikasi.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                {{-- A. PENGATURAN PERIODE --}}
                <div class="space-y-6">
                    <div class="border-b border-gray-200 dark:border-gray-700 pb-2 mb-4">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">
                            <i class="far fa-calendar-alt mr-2 text-indigo-500"></i> Kontrol Waktu Utama
                        </h3>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">
                            Periode Referensi Data
                        </label>
                        <input type="month" name="data_active_period" data-group="general"
                            data-label="Periode Operasi Global"
                            value="{{ App\Models\AppSetting::findValue('data_active_period') }}"
                            class="auto-save-input w-full md:w-2/3 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            onchange="window.settingsHandler.autoSaveSetting(this)">
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/50 p-3 rounded border border-gray-200 dark:border-gray-600">
                            <span class="font-bold text-gray-700 dark:text-gray-200 block mb-1">Dampak Global:</span>
                            <ul class="list-disc list-inside space-y-1 ml-1">
                                <li>Semua modul akan mengacu pada bulan ini.</li>
                                <li>Mengunci input data di luar periode ini.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- B. IDENTITAS LAPORAN --}}
                <div class="space-y-6">
                    <div class="border-b border-gray-200 dark:border-gray-700 pb-2 mb-4">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">
                            <i class="fas fa-file-signature mr-2 text-indigo-500"></i> Identitas & Kop Laporan
                        </h3>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nama Aplikasi</label>
                        <input type="text" name="app_name_label" data-group="general" data-label="Nama Aplikasi"
                            value="{{ App\Models\AppSetting::findValue('app_name_label', null, 'Aplikasi Pemetaan Pelanggan') }}"
                            class="auto-save-input w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            onchange="window.settingsHandler.autoSaveSetting(this)">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nama UID</label>
                        <input type="text" name="app_company_label" data-group="general" data-label="Nama Instansi"
                            placeholder="Contoh: PT PLN (Persero) UID RIAU KEPRI"
                            value="{{ App\Models\AppSetting::findValue('app_company_label', null, 'PT PLN (Persero)') }}"
                            class="auto-save-input w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            onchange="window.settingsHandler.autoSaveSetting(this)">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Kota Default (Pusat)</label>
                        <input type="text" name="app_report_city" data-group="general" data-label="Kota Laporan"
                            value="{{ App\Models\AppSetting::findValue('app_report_city', null, 'Pekanbaru') }}"
                            class="auto-save-input w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            onchange="window.settingsHandler.autoSaveSetting(this)">
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ==================================================== --}}
        {{-- CONTENT 4: SISTEM (ADMIN ONLY)                       --}}
        {{-- ==================================================== --}}
        @if($isAdmin)
        <div id="content-tab-system" class="setting-tab-content hidden pb-16">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Konfigurasi Sistem</h3>
                    <p class="text-xs text-gray-500">Pengaturan teknis server dan keamanan aplikasi.</p>
                </div>
            </div>

            {{-- Maintenance Mode --}}
            <div class="bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 rounded-lg p-6 mb-8 relative overflow-hidden">
                <div class="flex flex-col md:flex-row justify-between items-center gap-4 relative z-10">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i>
                            <h4 class="font-bold text-lg text-red-800 dark:text-red-300">Mode Pemeliharaan</h4>
                        </div>
                        <p class="text-sm text-red-700 dark:text-red-400">
                            Jika aktif, user non-admin tidak bisa login. Gunakan saat update aplikasi.
                        </p>
                    </div>
                    <div class="flex items-center bg-white dark:bg-gray-800 p-2 rounded-full shadow-sm border border-red-100 dark:border-red-900">
                        <span class="text-xs font-bold text-gray-400 mr-3 ml-2 uppercase">Status:</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="system_maintenance_mode" data-group="system"
                                class="auto-save-input sr-only peer"
                                {{ App\Models\AppSetting::findValue('system_maintenance_mode', null, false) ? 'checked' : '' }}
                                onchange="window.settingsHandler.autoSaveSetting(this)">
                            <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-red-600"></div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                    <h4 class="font-bold text-gray-800 dark:text-gray-200 mb-3"><i class="fas fa-cloud-upload-alt mr-2 text-indigo-500"></i> Batasan Upload</h4>
                    <div class="flex items-center gap-2">
                        <input type="number" name="system_max_upload_mb" data-group="system"
                            value="{{ App\Models\AppSetting::findValue('system_max_upload_mb', null, 5) }}"
                            class="auto-save-input w-24 rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 font-bold text-center"
                            onchange="window.settingsHandler.autoSaveSetting(this)">
                        <span class="text-sm text-gray-600 dark:text-gray-400">MB</span>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                    <h4 class="font-bold text-gray-800 dark:text-gray-200 mb-3"><i class="fas fa-history mr-2 text-indigo-500"></i> Audit Log</h4>
                    <div class="flex items-center gap-2">
                        <input type="number" name="system_audit_retention_days" data-group="system"
                            value="{{ App\Models\AppSetting::findValue('system_audit_retention_days', null, 60) }}"
                            class="auto-save-input w-24 rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600 font-bold text-center"
                            onchange="window.settingsHandler.autoSaveSetting(this)">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Hari</span>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                <button type="button" onclick="window.settingsHandler.clearAuditLog(false)"
                    class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded hover:text-red-600 transition shadow-sm">
                    <i class="fas fa-trash-alt mr-2"></i> Bersihkan Log Lama
                </button>
            </div>
        </div>
        @endif

    </div>
</div>