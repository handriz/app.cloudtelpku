{{-- DEFINISI VARIABEL GLOBAL (Pindah ke Paling Atas) --}}
@php 
    // Ambil 3 Digit Awal (UP3 + ULP + Sub Default A)
    $basePrefix = ($hierarchy->parent->kddk_code ?? '?') . ($hierarchy->kddk_code ?? '?') . 'A';

    $dayColors = [
    'A' => 'bg-green-100 text-green-800 border-green-200',
    'B' => 'bg-blue-100 text-blue-800 border-blue-200',
    'C' => 'bg-red-100 text-red-800 border-red-200',
    'D' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
    'E' => 'bg-purple-100 text-purple-800 border-purple-200',
    'F' => 'bg-pink-100 text-pink-800 border-pink-200',
    'G' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
    'H' => 'bg-teal-100 text-teal-800 border-teal-200',
    'I' => 'bg-orange-100 text-orange-800 border-orange-200',
    'J' => 'bg-lime-100 text-lime-800 border-lime-200',

    // Default
    'DEFAULT' => 'bg-gray-100 text-gray-800 border-gray-200'
];

@endphp

{{-- WRAPPER UTAMA (WORKSPACE) --}}
<div id="rbm-workspace" class="flex flex-col h-[calc(100vh-80px)] bg-gray-100 dark:bg-gray-900 transition-all duration-300 group/workspace">

    {{-- 1. TOP BAR (Toolbar & Info) --}}
    {{-- Ubah z-20 menjadi z-40 --}}
    <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 py-3 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-sm z-40 shrink-0 relative">
        
        {{-- BAGIAN KIRI: JUDUL & BREADCRUMB --}}
        <div class="flex items-center overflow-hidden">
            <div class="mr-4 pr-4 border-r border-gray-200 dark:border-gray-700 flex items-center space-x-2">
                <button onclick="App.Tabs.loadTabContent(App.Utils.getActiveTabName(), '{{ route('team.matrix_kddk.index') }}')" 
                        class="text-gray-500 hover:text-indigo-600 transition" title="Kembali">
                    <i class="fas fa-arrow-left text-xl"></i>
                </button>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 flex items-center whitespace-nowrap">
                    <span class="text-indigo-600 mr-2">{{ $hierarchy->name ?? $unitCode }}</span>
                </h3>
                <div class="flex items-center text-xs text-gray-500 space-x-2 mt-0.5">
                    <span class="bg-blue-100 text-blue-800 px-1.5 rounded font-mono font-bold">{{ $hierarchy->parent->kddk_code ?? '?' }}</span>
                    <i class="fas fa-chevron-right text-[10px]"></i>
                    <span class="bg-indigo-100 text-indigo-800 px-1.5 rounded font-mono font-bold">{{ $hierarchy->kddk_code ?? '?' }}</span>
                    <span class="text-gray-300">|</span>
                    <span class="hidden md:inline">Mode Manajemen Rute</span>
                </div>
            </div>
        </div>
        
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- BAGIAN KANAN: POSISI INDICATOR & TOMBOL --}}
        <div class="flex items-center gap-3 md:overflow-visible custom-scrollbar pb-1 md:pb-0 relative z-[999]">
            
            {{-- [BARU] INDIKATOR POSISI (Sejajar dengan Tombol) --}}
            <div class="flex items-center h-9 px-3 bg-gray-800 text-white rounded-md shadow-sm border border-gray-700 whitespace-nowrap">
                <span class="text-[10px] uppercase text-gray-400 font-bold mr-2 tracking-wider">POSISI:</span>
                <span id="live-kddk-display" class="font-mono font-bold text-yellow-400 text-sm tracking-widest">
                    {{ $basePrefix }}
                </span>
            </div>

            {{-- GROUP 1: TAMPILAN --}}
            <div class="flex items-center gap-1 bg-gray-50 dark:bg-gray-700/50 p-1 rounded-lg border border-gray-200 dark:border-gray-600 shrink-0">
                <button type="button" data-action="toggle-map-layout" 
                        class="flex items-center justify-center h-8 px-2 bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-200 hover:text-indigo-600 hover:bg-indigo-50 border border-gray-200 dark:border-gray-500 rounded transition shadow-sm"
                        title="Sembunyikan/Tampilkan Peta">
                    <i class="fas fa-map-marked-alt text-sm"></i>
                    <span class="text-btn hidden xl:inline ml-2 text-xs font-bold">Map</span>
                </button>
                <button type="button" data-action="toggle-fullscreen" 
                        class="flex items-center justify-center h-8 px-2 bg-white dark:bg-gray-600 text-gray-700 dark:text-gray-200 hover:text-indigo-600 hover:bg-indigo-50 border border-gray-200 dark:border-gray-500 rounded transition shadow-sm"
                        title="Mode Layar Penuh">
                    <i class="fas fa-expand icon-expand text-sm"></i>
                    <i class="fas fa-compress icon-compress hidden text-sm"></i>
                </button>
            </div>

            <div class="h-6 w-px bg-gray-300 dark:bg-gray-600 hidden md:block"></div>

            {{-- GROUP 2: DATA ACTIONS --}}
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" onclick="window.openHistoryModal()" 
                        class="flex items-center justify-center h-9 px-3 bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:text-indigo-600 hover:border-indigo-300 border border-gray-300 dark:border-gray-600 rounded-md transition shadow-sm">
                    <i class="fas fa-history text-sm"></i>
                    <span class="hidden lg:inline ml-2 text-xs font-bold">Riwayat</span>
                </button>

                <div class="relative group">
                    {{-- Tombol Pemicu --}}
                    <button type="button" id="btn-export-trigger" 
                            class="flex items-center justify-center h-9 px-3 bg-white dark:bg-gray-700 text-green-600 hover:bg-green-50 hover:border-green-300 border border-gray-300 dark:border-gray-600 rounded-md transition shadow-sm"
                            title="Export Data">
                        <i class="fas fa-file-export text-sm"></i>
                        <span class="hidden lg:inline ml-2 text-xs font-bold">Export</span>
                        <i class="fas fa-chevron-down ml-2 text-[10px] text-gray-400"></i>
                    </button>

                    {{-- Isi Dropdown (Awalnya Hidden) --}}
                    <div id="export-dropdown-menu" class="hidden absolute right-0 mt-2 w-40 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-2xl overflow-hidden ring-1 ring-black ring-opacity-5 z-[9999]">
                        {{-- Pilihan Excel --}}
                        <a href="javascript:void(0)" onclick="window.exportRbmCheck('excel')" 
                        class="export-item block px-4 py-2 text-xs font-bold text-gray-700 dark:text-gray-300 hover:bg-green-50 hover:text-green-700 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700 dropdown-item">
                            <i class="fas fa-file-excel mr-2 text-green-600"></i> Excel (.xls)
                        </a>
                        
                        {{-- Pilihan CSV --}}
                        <a href="javascript:void(0)" onclick="window.exportRbmCheck('csv')"
                        class="export-item block px-4 py-2 text-xs font-bold text-gray-700 dark:text-gray-300 hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-gray-700 dropdown-item">
                            <i class="fas fa-file-csv mr-2 text-blue-600"></i> CSV (Pipa |)
                        </a>
                    </div>
                </div>  

                <button type="button" id="btn-print-worksheet" onclick="window.printWorksheetCheck()" 
                        class="flex items-center justify-center h-9 px-3 bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:text-indigo-600 hover:border-indigo-300 border border-gray-300 dark:border-gray-600 rounded-md transition shadow-sm">
                    <i class="fas fa-print text-sm" title="Cetak Lembar Kerja (Ctrl + P)"></i>
                    <span class="hidden lg:inline ml-2 text-xs font-bold">Cetak</span>
                </button>
            </div>

            {{-- GROUP 3: PRIMARY --}}
            <button form="rbm-form" type="submit" 
                    class="flex items-center justify-center h-9 px-4 bg-indigo-600 hover:bg-indigo-700 text-white border border-transparent rounded-md shadow-md transition transform active:scale-95 shrink-0">
                <i class="fas fa-save text-sm" title="Simpan Perubahan (Ctrl + S)"></i>
                <span class="ml-2 text-xs font-bold uppercase tracking-wide">Simpan</span>
            </button>
        </div>
    </div>

    <div id="kddk-notification-container" class="px-4 mt-2"></div>
    
    {{-- 2. MAIN CONTENT (SPLIT VIEW) --}}
    <div class="flex flex-1 overflow-hidden relative">
        
        {{-- PANEL KIRI: LIST DATA --}}
        {{-- ID: panel-list DITAMBAHKAN UNTUK JS --}}
        <div id="panel-list" class="w-full md:w-[450px] flex flex-col bg-gray-50 dark:bg-gray-900 border-r border-gray-200 dark:border-gray-700 relative z-10 shadow-xl transition-all duration-300">
            
            <div class="p-3 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 relative">
                {{-- Input Hidden URL API --}}
                <input type="hidden" id="api-search-customer" value="{{ route('team.matrix_kddk.search_customer', ['unit' => $unitCode]) }}">
                
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-search"></i></span>
                    <input type="text" id="kddk-search-input" 
                           class="w-full py-2 pl-9 pr-8 text-sm bg-gray-100 dark:bg-gray-700 border-transparent focus:bg-white focus:border-indigo-500 rounded-md transition"
                           placeholder="Cari IDPEL / Nama... (Min 3 Karakter)">
                    
                    {{-- Tombol Clear (X) --}}
                    <button id="clear-search-btn" class="hidden absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 cursor-pointer">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                {{-- [BARU] HASIL PENCARIAN DROPDOWN --}}
                <div id="search-results-dropdown" class="hidden absolute top-full left-0 right-0 z-50 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 shadow-xl max-h-60 overflow-y-auto rounded-b-lg">
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700 text-sm" id="search-results-list">
                        {{-- Hasil AJAX masuk sini --}}
                    </ul>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-3">
                <form id="rbm-form" action="{{ route('team.matrix_kddk.rbm_update') }}" method="POST" class="ajax-form" data-success-redirect-tab="Matrix KDDK">
                    @csrf
                    <input type="hidden" id="api-route-table" value="{{ route('team.matrix_kddk.get_route_table', ['unit' => $unitCode]) }}">
                    <input type="hidden" name="unitup" value="{{ $unitCode }}">
                    <input type="hidden" id="move-route" value="{{ route('team.matrix_kddk.move_idpel') }}">
                    <input type="hidden" id="reorder-route" value="{{ route('team.matrix_kddk.reorder_idpel') }}">
                    <input type="hidden" id="remove-route" value="{{ route('team.matrix_kddk.remove_idpel') }}">
                    
                    {{-- Hidden Inputs Bulk --}}
                    <input type="hidden" id="bulk-move-route" value="{{ route('team.matrix_kddk.bulk_move') }}">
                    <input type="hidden" id="bulk-remove-route" value="{{ route('team.matrix_kddk.bulk_remove') }}">

                    <div class="space-y-4">
                        @forelse($groupedData as $areaCode => $digit6Groups)
                            @php
                                $areaLabel = $areaLabels[$areaCode] ?? 'Area ' . $areaCode;
                                $areaId = 'area-' . $areaCode;
                                $totalAreaPlg = collect($digit6Groups)->flatten(1)->sum('count');
                                $displayArea = $basePrefix . ' ' . $areaCode;
                            @endphp

                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-300 dark:border-gray-600 overflow-hidden area-container">
                                {{-- HEADER AREA --}}
                                <div class="bg-gray-800 text-white p-3 cursor-pointer flex justify-between items-center area-header transition hover:bg-gray-700"
                                     data-action="toggle-area-map" 
                                     data-target="{{ $areaId }}" 
                                     data-area-code="{{ $areaCode }}"
                                     data-display-code="{{ $displayArea }}">
                                    <div class="flex items-center">
                                        <span class="bg-indigo-500 text-white font-bold px-2 py-0.5 rounded text-xs mr-2">{{ $areaCode }}</span>
                                        <div>
                                            <span class="font-bold text-sm block">{{ $areaLabel }}</span>
                                            <span class="text-[10px] text-gray-300">{{ $totalAreaPlg }} Pelanggan</span>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-down transition icon-chevron"></i>
                                </div>

                                {{-- BODY AREA --}}
                                <div id="{{ $areaId }}" class="hidden p-2 space-y-2 bg-gray-50 dark:bg-gray-900/50">
                                    
                                    {{-- WRAPPER DIGIT 6 (Agar bisa jadi Grid) --}}
                                    <div class="digit6-wrapper space-y-2">
                                        @foreach($digit6Groups as $digit6 => $digit7Groups)
                                            @php
                                                $digit6Id = 'd6-' . $areaCode . '-' . $digit6;
                                                $totalD6Plg = collect($digit7Groups)->sum('count');
                                                $displayDigit6 = $displayArea . ' ' . $digit6;
                                            @endphp
    
                                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 digit6-container">
                                                {{-- HEADER DIGIT 6 --}}
                                                <div class="p-2 bg-indigo-50 dark:bg-gray-700 cursor-pointer flex justify-between items-center digit6-header hover:bg-indigo-100 dark:hover:bg-gray-600 transition"
                                                     data-action="toggle-digit6" 
                                                     data-target="{{ $digit6Id }}"
                                                     data-display-code="{{ $displayDigit6 }}">
                                                    <div class="flex items-center">
                                                        <div class="w-6 h-6 rounded-full bg-indigo-200 text-indigo-800 flex items-center justify-center text-xs font-bold mr-2 border border-indigo-300">
                                                            {{ $digit6 }}
                                                        </div>
                                                        <span class="text-xs font-bold text-gray-700 dark:text-gray-200 uppercase">Kelompok Rute {{ $digit6 }}</span>
                                                    </div>
                                                    <div class="flex items-center">
                                                        <span class="text-[12px] bg-gray-200 text-gray-800 px-1.5 rounded mr-2">{{ $totalD6Plg }} Plg</span>
                                                        <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200 icon-chevron-d6"></i>
                                                    </div>
                                                </div>
    
                                                {{-- BODY DIGIT 6 --}}
                                                <div id="{{ $digit6Id }}" class="hidden border-t border-gray-100 dark:border-gray-600">
                                                    
                                                    {{-- CONTAINER GRID TARGET (Untuk Mode Luas) --}}
                                                    <div class="routes-grid-container space-y-0 transition-all duration-300">
                                                        
                                                        @foreach($digit7Groups as $digit7 => $info)
                                                            @php 
                                                                // FIX 500: Data sekarang Array, bukan Collection Object
                                                                // Kita akses array key, bukan method ->first()
                                                                
                                                                $routeId = 'route-' . $areaCode . '-' . $digit6 . $digit7;
                                                                $fullRouteCode = $digit6 . $digit7; 
                                                                
                                                                // Ambil data dari Array Info
                                                                $firstKddk = $info['first_kddk'];
                                                                $countPelanggan = $info['count'];
                                                                $assignedUser = $info['user_id'];
                                                                
                                                                // Rekonstruksi prefix (7 digit)
                                                                $routePrefix = substr($firstKddk, 0, 7);
                                                                $displayDigit7 = $displayDigit6 . $digit7;
                                                            @endphp
        
                                                            <div class="route-container border-b last:border-b-0 border-gray-100 dark:border-gray-700 h-full">
                                                                {{-- HEADER HARI BACA --}}
                                                                <div class="p-2 pl-8 pr-2 flex justify-between items-center cursor-pointer hover:bg-green-50 dark:hover:bg-gray-900 transition route-header"
                                                                     data-action="toggle-route-map" 
                                                                     data-target="{{ $routeId }}"
                                                                     data-area-code="{{ $areaCode }}"
                                                                     data-route-code="{{ $fullRouteCode }}"
                                                                     data-display-code="{{ $displayDigit7 }}">
                                                                    
                                                                    @php $colorClass = $dayColors[$digit7] ?? $dayColors['DEFAULT']; @endphp

                                                                    <div class="flex items-center">
                                                                        <i class="far fa-calendar-alt text-gray-400 mr-2 text-xs"></i>
                                                                        <span class="text-xs font-bold text-gray-600 dark:text-gray-300 mr-2">Hari:</span>
                                                                        
                                                                        <span class="px-2 py-0.5 rounded text-xs font-bold font-mono border {{ $colorClass }}">
                                                                            {{ $digit7 }}
                                                                        </span>
                                                                    </div>
                                                                    <div class="flex items-center">
                                                                        {{-- TAMPILKAN COUNT (Dari Array) --}}
                                                                        <span class="text-[9px] font-bold bg-green-100 text-green-700 px-1.5 rounded">{{ $countPelanggan }}</span>
                                                                        <i class="fas fa-chevron-down text-gray-300 text-xs ml-2 transition-transform duration-200 icon-chevron-sub"></i>
                                                                    </div>
                                                                </div>
        
                                                                {{-- BODY HARI BACA (Default: Kosong & Hidden) --}}
                                                                <div id="{{ $routeId }}" class="hidden pl-8 pr-0 pb-0" data-loaded="false">
                                                                    <div class="kddk-drop-zone p-0 relative transition-colors border-l-2 border-green-200" data-route-prefix="{{ $routePrefix }}">
                                                                        
                                                                        {{-- Toolbar Petugas --}}
                                                                        <div class="p-1 bg-gray-50 dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 flex justify-end items-center">
                                                                            <span class="text-[9px] text-gray-400 mr-2">Petugas:</span>
                                                                            <select name="assignments[{{ $firstKddk }}]" class="text-[10px] py-0.5 px-2 border-gray-200 rounded bg-white focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white w-40">
                                                                                <option value="">- Pilih Petugas -</option>
                                                                                @foreach($officers as $off)
                                                                                    <option value="{{ $off->id }}" {{ $assignedUser == $off->id ? 'selected' : '' }}>
                                                                                        {{ Str::limit($off->name, 20) }}
                                                                                    </option>
                                                                                @endforeach
                                                                            </select>
                                                                        </div>
        
                                                                        {{-- Indicator Drop --}}
                                                                        <div class="hidden drop-indicator absolute inset-0 bg-green-100/90 text-green-800 z-20 flex items-center justify-center text-xs font-bold border-2 border-dashed border-green-500">
                                                                            <i class="fas fa-file-import mr-1"></i> Pindah ke Hari {{ $digit7 }}
                                                                        </div>
        
                                                                        {{-- TABEL KOSONG (Akan diisi AJAX) --}}
                                                                        <table class="min-w-full text-[10px] text-left">
                                                                            <thead class="bg-gray-100 dark:bg-gray-700 text-gray-500 uppercase font-bold">
                                                                                <tr>
                                                                                    <th class="py-1 pl-2 w-6 text-center">
                                                                                        <input type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 select-all-route h-3 w-3" title="Pilih Semua">
                                                                                    </th>
                                                                                    <th class="py-1 pl-1 w-4"></th>
                                                                                    <th class="py-1 w-6">No</th>
                                                                                    <th class="py-1 px-1">Pelanggan</th>
                                                                                    <th class="py-1 px-1">Meter</th>
                                                                                    <th class="py-1 w-8 text-center">Aksi</th>
                                                                                </tr>
                                                                            </thead>
                                                                            {{-- ID UNIK UNTUK TARGET AJAX --}}
                                                                            <tbody id="tbody-{{ $routeId }}" class="divide-y divide-gray-100 dark:divide-gray-700">
                                                                                {{-- Placeholder Loading --}}
                                                                                <tr>
                                                                                    <td colspan="6" class="p-4 text-center text-gray-400">
                                                                                        <i class="fas fa-spinner fa-spin mr-2"></i> Klik header untuk memuat data...
                                                                                    </td>
                                                                                </tr>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    {{-- SAMPAI SINI --}}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-10 text-center text-gray-500">Belum ada data.</div>
                        @endforelse
                    </div>

                    {{-- FLOATING BULK BAR --}}
                    <div id="bulk-action-bar" class="hidden fixed bottom-4 left-1/2 transform -translate-x-1/2 z-[60] bg-white dark:bg-gray-800 rounded-full shadow-2xl border border-indigo-200 dark:border-indigo-700 px-4 py-2 flex items-center space-x-3 animate-bounce-in-up">
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-200 whitespace-nowrap">
                            <span id="bulk-count" class="text-indigo-600 text-sm">0</span> <span class="hidden sm:inline">Dipilih</span>
                        </span>
                        <div class="h-4 w-px bg-gray-300 dark:bg-gray-600"></div>
                        <button type="button" onclick="window.openBulkMoveModal()" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center transition px-2 py-1 hover:bg-indigo-50 rounded">
                            <i class="fas fa-exchange-alt mr-1.5"></i> Pindah
                        </button>
                        <button type="button" onclick="window.executeBulkRemove()" class="text-xs font-bold text-red-600 hover:text-red-800 flex items-center transition px-2 py-1 hover:bg-red-50 rounded">
                            <i class="fas fa-trash-alt mr-1.5"></i> Hapus
                        </button>
                        <button type="button" onclick="window.clearBulkSelection()" class="text-gray-400 hover:text-gray-600 ml-1 p-1 rounded-full hover:bg-gray-100">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                </form>
            </div>
        </div>

        {{-- PANEL KANAN: PETA --}}
        {{-- ID: panel-map DITAMBAHKAN UNTUK JS --}}
        <div id="panel-map" class="flex-1 bg-gray-200 dark:bg-gray-800 relative h-full hidden md:block">
            {{-- Map Controls --}}
            <div class="absolute top-4 left-4 z-[400] bg-white/90 backdrop-blur-sm p-2 rounded-lg shadow-md border border-gray-200 flex items-center space-x-3">
                <span class="text-[10px] bg-green-100 text-green-800 px-2 py-0.5 rounded font-bold" id="map-count">0 Titik</span>
                <div class="h-3 w-px bg-gray-300 mx-1"></div>
                <span class="text-[10px] text-gray-700 font-bold truncate max-w-[200px]" id="map-context-title">Pilih Area/Rute di kiri</span>
            </div>
            <div id="anomaly-alert" class="hidden absolute top-16 left-4 z-[5000] bg-red-100/95 backdrop-blur-md border border-red-400 text-red-800 px-3 py-2 rounded-lg sshadow-2xl flex items-center space-x-3 animate-bounce-in-up">
                <div class="bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center font-bold text-xs">!</div>
                <div class="text-xs">
                    <span class="font-bold block">Terdeteksi Anomali!</span>
                    <span id="anomaly-count">0</span> pelanggan > 2KM dari pusat rute.
                </div>
                <button onclick="document.getElementById('anomaly-alert').classList.add('hidden')" class="text-red-500 hover:text-red-700 ml-2"><i class="fas fa-times"></i></button>
            </div>
            {{-- Data Map --}}
            <div id="rbm-map-data" data-points="{{ json_encode($mapData ?? []) }}" class="hidden"></div>
            <input type="hidden" id="map-data-url" value="{{ route('team.matrix_kddk.map_data', ['unit' => $unitCode]) }}">
            <div id="rbm-map" class="w-full h-full"></div>
        </div>

    </div>

    {{-- DROP ZONE REMOVE --}}
    <div id="remove-drop-zone" class="hidden fixed bottom-6 left-6 z-[1000] w-40 h-16 bg-red-100/90 backdrop-blur border-2 border-dashed border-red-400 rounded-lg flex flex-col items-center justify-center text-red-600 shadow-xl transition-all duration-300 opacity-0 transform translate-y-10 kddk-remove-zone">
        <i class="fas fa-trash-alt text-xl mb-1"></i><p class="text-[9px] font-bold uppercase">Lepas = Hapus</p>
    </div>
    
    {{-- Include Components --}}
    @include('team.matrix_kddk.partials.drag_components') 
    
    {{-- MODAL KONFIRMASI KELUARKAN (PREMIUM) --}}
    <div id="custom-confirm-modal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4 transition-opacity duration-300">
        <div class="relative w-full max-w-sm transform rounded-2xl bg-white dark:bg-gray-800 shadow-2xl transition-all duration-300 scale-100 animate-pop-up overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-red-500 to-pink-600"></div>
            <button type="button" id="custom-confirm-cancel-x" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                <i class="fas fa-times text-lg"></i>
            </button>
            <div class="p-8 text-center">
                <div class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-red-50 dark:bg-red-900/20 ring-8 ring-red-50 dark:ring-red-900/10">
                    <i class="fas fa-trash-alt text-3xl text-red-500 animate-pulse"></i>
                </div>
                <h3 id="custom-confirm-title" class="mb-2 text-xl font-extrabold text-gray-900 dark:text-white">Keluarkan Pelanggan?</h3>
                <div id="custom-confirm-message" class="mb-6 text-sm text-gray-500 dark:text-gray-400 leading-relaxed"></div>
                <div class="flex items-center justify-center space-x-3">
                    <button type="button" id="custom-confirm-cancel" class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 transition">Batal</button>
                    <button type="button" id="custom-confirm-ok" class="rounded-xl bg-red-600 px-6 py-2.5 text-sm font-bold text-white shadow-lg shadow-red-500/30 hover:bg-red-700 hover:-translate-y-0.5 transition-all">Ya, Keluarkan</button>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL SUKSES GENERIC (PREMIUM CENTERED) --}}
    <div id="modal-success-generic" class="fixed inset-0 z-[10000] hidden items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4 transition-opacity duration-300">
        <div class="relative w-full max-w-sm transform rounded-2xl bg-white dark:bg-gray-800 shadow-2xl transition-all duration-300 scale-100 animate-pop-up overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-green-400 to-emerald-600"></div>
            <div class="p-8 text-center">
                <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-green-50 dark:bg-green-900/20 ring-8 ring-green-50 dark:ring-green-900/10">
                    <i class="fas fa-check text-4xl text-green-500 animate-bounce-short"></i>
                </div>
                <h3 class="mb-2 text-2xl font-extrabold text-gray-900 dark:text-white tracking-tight">Berhasil!</h3>
                <p id="generic-success-message" class="mb-8 text-sm text-gray-500 dark:text-gray-400 leading-relaxed font-medium">Aksi berhasil dilakukan.</p>
                <button type="button" onclick="window.closeGenericSuccessModal()" class="inline-flex w-full items-center justify-center rounded-xl bg-green-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-green-500/30 transition-all duration-200 hover:bg-green-700 hover:-translate-y-0.5">OK, Mengerti</button>
            </div>
        </div>
    </div>

    {{-- MODAL RIWAYAT AKTIVITAS --}}
    <div id="modal-history" class="fixed inset-0 z-[3000] hidden items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4 transition-opacity duration-300">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl h-[80vh] flex flex-col transform transition-all scale-100">
            
            {{-- Header Modal --}}
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-800 rounded-t-xl">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center">
                    <i class="fas fa-history text-indigo-500 mr-2"></i> Riwayat Aktivitas (50 Terakhir)
                </h3>
                <button onclick="document.getElementById('modal-history').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            {{-- Content (Loading / Data) --}}
            <div id="history-content" class="flex-1 overflow-y-auto p-4 custom-scrollbar relative">
                {{-- Spinner Default --}}
                <div class="absolute inset-0 flex items-center justify-center">
                    <i class="fas fa-spinner fa-spin text-3xl text-indigo-300"></i>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 rounded-b-xl flex justify-end">
                <button onclick="document.getElementById('modal-history').classList.add('hidden')" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-bold text-gray-700 hover:bg-gray-50">
                    Tutup
                </button>
            </div>
        </div>
    </div>
    
    {{-- Hidden Route untuk JS --}}
    <input type="hidden" id="history-route" value="{{ route('team.matrix_kddk.history', ['unit' => $unitCode]) }}">

</div>

<style>
    /* ===============================================
       1. CUSTOM SCROLLBAR (MODERN & MINIMALIS)
       =============================================== */
    .custom-scrollbar::-webkit-scrollbar {
        width: 5px;
        height: 5px;
    }
    
    /* Track (Jalur) Transparan */
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    
    /* Thumb (Batang Scroll) */
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #cbd5e1; /* gray-300 */
        border-radius: 20px;
        border: 1px solid transparent;
        background-clip: content-box;
    }
    
    /* Thumb Hover */
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background-color: #94a3b8; /* gray-400 */
    }

    /* Dark Mode Scrollbar */
    .dark .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #4b5563; /* gray-600 */
    }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background-color: #6b7280; /* gray-500 */
    }


    /* ===============================================
       2. MODE FULLSCREEN (FORCE LAYOUT)
       =============================================== */
    
    /* Sembunyikan elemen layout Laravel bawaan (Sidebar, Nav, Header) saat mode fullscreen aktif */
    /* Menggunakan !important untuk menimpa style bawaan framework */
    body.rbm-fullscreen nav,
    body.rbm-fullscreen aside,
    body.rbm-fullscreen header, 
    body.rbm-fullscreen footer,
    body.rbm-fullscreen .min-h-screen > div:first-child {
        display: none !important;
    }

    /* Konfigurasi Workspace saat Fullscreen */
    #rbm-workspace.fullscreen-mode {
        position: fixed !important;      /* Lepas dari aliran dokumen normal */
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;         /* Lebar 100% Viewport */
        height: 100vh !important;        /* Tinggi 100% Viewport */
        
        z-index: 2147483647 !important;  /* Z-Index Maksimum Browser agar selalu di paling atas */
        
        margin: 0 !important;
        padding: 0 !important;
        border-radius: 0 !important;     /* Hilangkan rounded corner */
        background-color: #f3f4f6;       /* bg-gray-100 (Light Mode) */
        
        transform: none !important;      /* Reset transformasi CSS jika ada */
        overflow: hidden !important;     /* Mencegah scrollbar ganda pada body */
    }

    /* Background untuk Dark Mode saat Fullscreen */
    .dark #rbm-workspace.fullscreen-mode {
        background-color: #111827;       /* bg-gray-900 (Dark Mode) */
    }


    /* ===============================================
       3. PENYESUAIAN KONTEN DALAM FULLSCREEN
       =============================================== */

    /* Pastikan Grid Layout mengisi tinggi penuh dikurangi header toolbar */
    #rbm-workspace.fullscreen-mode .grid {
        height: 100% !important;
    }

    /* Pastikan Peta mengisi sisa ruang vertikal sepenuhnya */
    #rbm-workspace.fullscreen-mode #rbm-map {
        height: 100% !important;
        min-height: 0 !important; /* Reset min-height agar fleksibel */
    }

    /* Animasi Transisi Halus saat masuk/keluar fullscreen */
    #rbm-workspace {
        transition: all 0.3s ease-in-out;
    }

    /* Paksa Leaflet agar z-index nya tidak liar */
    .leaflet-pane { z-index: 400 !important; }
    .leaflet-top, .leaflet-bottom { z-index: 500 !important; } /* Kontrol Zoom dll */
        
        /* Pastikan Modal selalu di atas Leaflet */
    #modal-move-route, #custom-context-menu {
        z-index: 2000 !important;
    }
    @keyframes bounceInUp {
        0% { transform: translate(-50%, 100%); opacity: 0; }
        60% { transform: translate(-50%, -10%); opacity: 1; }
        100% { transform: translate(-50%, 0); }
    }
    .animate-bounce-in-up { animation: bounceInUp 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }

    @keyframes popup {
        0% { transform: scale(0.9); opacity: 0; }
        100% { transform: scale(1); opacity: 1; }
    }
    .animate-pop-up { animation: popup 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }


    /* MARKER MERAH (OUTLIER / ANOMALI) */
    .marker-outlier {
        background-color: #ef4444 !important; /* Red 500 */
        color: white !important;
        border-color: #7f1d1d !important; /* Red 900 */
        box-shadow: 0 0 10px rgba(239, 68, 68, 0.7);
        z-index: 1000 !important;
    }

    /* Animasi Berkedip */
    @keyframes pulse-red {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        70% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }
    
    .animate-marker-pulse {
        animation: pulse-red 1.5s infinite;
    }
    
    /* Panel Peringatan Anomali */
    #anomaly-alert {
        transition: all 0.3s ease;
    }

</style>