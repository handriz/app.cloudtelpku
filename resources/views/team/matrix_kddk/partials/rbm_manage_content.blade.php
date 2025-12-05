{{-- WRAPPER UTAMA (WORKSPACE) --}}
<div id="rbm-workspace" class="flex flex-col h-[calc(100vh-80px)] bg-gray-100 dark:bg-gray-900 transition-all duration-300 group/workspace">
    

    {{-- 1. TOP BAR (Toolbar & Info) --}}
    <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 py-3 flex items-center justify-between shadow-sm z-20 shrink-0">
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
        <div class="sticky top-0 z-30 mb-4 flex justify-center pointer-events-none">
            <div class="bg-gray-800 text-white px-6 py-2 rounded-b-xl shadow-lg flex items-center space-x-3 transform transition-all duration-300 pointer-events-auto" id="kddk-context-bar">
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Posisi Kode:</span>
                @php 
                    // Ambil 3 Digit Awal (UP3 + ULP + Sub Default A)
                    $basePrefix = ($hierarchy->parent->kddk_code ?? '?') . ($hierarchy->kddk_code ?? '?') . 'A'; 
                @endphp
                <span id="live-kddk-display" class="text-2xl font-mono font-bold tracking-widest text-yellow-400">
                    {{ $basePrefix }}
                </span>
            </div>
    </div>
    <meta name="csrf-token" content="{{ csrf_token() }}">

        <div class="flex items-center space-x-2">
            {{-- TOMBOL FULL SCREEN BARU --}}
            <button type="button" data-action="toggle-fullscreen" 
                    class="flex items-center px-3 py-2 bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm font-bold transition border border-gray-300 dark:border-gray-600"
                    title="Mode Layar Penuh">
                <i class="fas fa-expand mr-2 icon-expand"></i>
                <i class="fas fa-compress mr-2 icon-compress hidden"></i>
                <span class="text-btn">Full Screen</span>
            </button>

            <div class="h-6 w-px bg-gray-300 dark:bg-gray-600 mx-1"></div>

            <a href="{{ route('team.matrix_kddk.export_rbm', ['unit' => $unitCode]) }}" target="_blank" 
               class="hidden md:flex items-center px-3 py-2 bg-green-50 text-green-700 hover:bg-green-100 rounded-md text-sm font-bold transition border border-green-200">
                <i class="fas fa-file-excel mr-2"></i> Export
            </a>
            
            <button form="rbm-form" type="submit" class="flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-md shadow-md transition transform active:scale-95">
                <i class="fas fa-save mr-2"></i> Simpan
            </button>
        </div>
    </div>

    {{-- 2. MAIN CONTENT (SPLIT VIEW) --}}
    <div class="flex flex-1 overflow-hidden relative">
        
        {{-- PANEL KIRI: LIST DATA (30-35% Width) --}}
        {{-- Width responsive: Full di HP, 35% di Desktop --}}
        <div class="w-full md:w-[400px] lg:w-[450px] flex flex-col bg-gray-50 dark:bg-gray-900 border-r border-gray-200 dark:border-gray-700 relative z-10 shadow-xl transition-all duration-300">
            
            {{-- Info 3 Digit (Compact) --}}
            <div class="p-2 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                 <div class="flex gap-2 overflow-x-auto pb-1">
                    {{-- Sub Unit List --}}
                    <span class="px-2 py-1 rounded bg-purple-50 text-purple-700 text-[10px] font-bold border border-purple-200 whitespace-nowrap">
                        Sub: A (Induk)
                    </span>
                    @foreach($hierarchy->children as $sub)
                        <span class="px-2 py-1 rounded bg-white text-gray-600 text-[10px] border border-gray-200 whitespace-nowrap" title="{{ $sub->name }}">
                            Sub: {{ $sub->kddk_code }}
                        </span>
                    @endforeach
                 </div>
            </div>

            {{-- Search Bar --}}
            <div class="p-3 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-search"></i></span>
                    <input type="text" id="kddk-search-input" 
                           class="w-full py-2 pl-9 pr-4 text-sm bg-gray-100 dark:bg-gray-700 border-transparent focus:bg-white focus:border-indigo-500 rounded-md transition"
                           placeholder="Filter IDPEL, Rute, Petugas...">
                </div>
            </div>

            {{-- List Accordion (Scrollable Area) --}}
            <div class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-3">
                
                <form id="rbm-form" action="{{ route('team.matrix_kddk.rbm_update') }}" method="POST" class="ajax-form" data-success-redirect-tab="Matrix KDDK">
                    @csrf
                    <input type="hidden" name="unitup" value="{{ $unitCode }}">
                    <input type="hidden" id="move-route" value="{{ route('team.matrix_kddk.move_idpel') }}">
                    <input type="hidden" id="reorder-route" value="{{ route('team.matrix_kddk.reorder_idpel') }}">
                    <input type="hidden" id="remove-route" value="{{ route('team.matrix_kddk.remove_idpel') }}">

                <div class="space-y-4">
            
                    {{-- LEVEL 1: AREA (Digit 4-5) --}}
                    @forelse($groupedData as $areaCode => $digit6Groups)
                        @php
                            $areaLabel = $areaLabels[$areaCode] ?? 'Area ' . $areaCode;
                            $areaId = 'area-' . $areaCode;
                            $totalAreaPlg = $digit6Groups->flatten(2)->count();
                        @endphp

                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-300 dark:border-gray-600 overflow-hidden area-container">
                            
                            {{-- Header Area --}}
                            <div class="bg-gray-800 text-white p-3 cursor-pointer flex justify-between items-center area-header transition hover:bg-gray-700"
                                 data-action="toggle-area-map" data-target="{{ $areaId }}" data-area-code="{{ $areaCode }}">
                                <div class="flex items-center">
                                    <span class="bg-indigo-500 text-white font-bold px-2 py-0.5 rounded text-xs mr-2">{{ $areaCode }}</span>
                                    <div>
                                        <span class="font-bold text-sm block">{{ $areaLabel }}</span>
                                        <span class="text-[10px] text-gray-300">{{ $totalAreaPlg }} Pelanggan</span>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-down transition icon-chevron"></i>
                            </div>

                            {{-- Body Area --}}
                            <div id="{{ $areaId }}" class="p-2 space-y-2 bg-gray-50 dark:bg-gray-900/50 hidden">
                                
                                {{-- LEVEL 2: KODE RUTE (Digit 6) --}}
                                @foreach($digit6Groups as $digit6 => $digit7Groups)
                                    @php
                                        $digit6Id = 'd6-' . $areaCode . '-' . $digit6;
                                        $totalD6Plg = $digit7Groups->flatten()->count();
                                    @endphp

                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-800 digit6-container">
                                        
                                        {{-- Header Rute (Digit 6) --}}
                                        <div class="p-2 bg-indigo-50 dark:bg-gray-700 cursor-pointer flex justify-between items-center digit6-header hover:bg-indigo-100 dark:hover:bg-gray-600 transition"
                                             onclick="document.getElementById('{{ $digit6Id }}').classList.toggle('hidden'); this.querySelector('.icon-chevron-d6').classList.toggle('rotate-180');">
                                            <div class="flex items-center">
                                                <div class="w-6 h-6 rounded-full bg-indigo-200 text-indigo-800 flex items-center justify-center text-xs font-bold mr-2 border border-indigo-300">
                                                    {{ $digit6 }}
                                                </div>
                                                <span class="text-xs font-bold text-gray-700 dark:text-gray-200 uppercase">Kode Rute {{ $digit6 }}</span>
                                            </div>
                                            <div class="flex items-center">
                                                <span class="text-[9px] bg-gray-200 text-gray-600 px-1.5 rounded mr-2">{{ $totalD6Plg }} Plg</span>
                                                <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform duration-200 icon-chevron-d6"></i>
                                            </div>
                                        </div>

                                        {{-- Body Rute --}}
                                        <div id="{{ $digit6Id }}" class="hidden border-t border-gray-100 dark:border-gray-600">
                                            
                                            {{-- LEVEL 3: HARI BACA (Digit 7) --}}
                                            @foreach($digit7Groups as $digit7 => $customers)
                                                @php 
                                                    $routeId = 'route-' . $areaCode . '-' . $digit6 . $digit7;
                                                    // Full Rute: Digit 6 + Digit 7 (Misal: A1)
                                                    $fullRouteCode = $digit6 . $digit7; 
                                                    $routePrefix = substr($customers->first()->kddk, 0, 7);
                                                    $firstKddk = $customers->first()->kddk;
                                                @endphp

                                                <div class="route-container border-b last:border-b-0 border-gray-100 dark:border-gray-700">
                                                    
                                                    {{-- Header Hari Baca --}}
                                                    <div class="p-2 pl-8 pr-2 flex justify-between items-center cursor-pointer hover:bg-green-50 dark:hover:bg-gray-900 transition route-header"
                                                         data-action="toggle-route-map" 
                                                         data-target="{{ $routeId }}"
                                                         data-area-code="{{ $areaCode }}"
                                                         data-route-code="{{ $fullRouteCode }}">
                                                        
                                                        <div class="flex items-center">
                                                            <i class="far fa-calendar-alt text-green-600 mr-2 text-xs"></i>
                                                            {{-- LABEL HARI BACA --}}
                                                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 mr-2">
                                                                Hari Baca: <span class="text-green-600 font-mono text-sm">{{ $digit7 }}</span>
                                                            </span>
                                                            <span class="text-[9px] font-mono text-gray-400 bg-gray-100 px-1 rounded">Prefix: {{ $routePrefix }}...</span>
                                                        </div>
                                                        
                                                        <div class="flex items-center">
                                                            <span class="text-[9px] font-bold bg-green-100 text-green-700 px-1.5 rounded">{{ $customers->count() }}</span>
                                                            <i class="fas fa-chevron-down text-gray-300 text-[10px] ml-2 transition-transform duration-200 icon-chevron-sub"></i>
                                                        </div>
                                                    </div>

                                                    {{-- Body Hari Baca (Tabel Pelanggan) --}}
                                                    <div id="{{ $routeId }}" class="hidden pl-8 pr-0 pb-0">
                                                        <div class="kddk-drop-zone p-0 relative transition-colors border-l-2 border-green-200" data-route-prefix="{{ $routePrefix }}">
                                                            
                                                            {{-- Toolbar Petugas --}}
                                                            <div class="p-1 bg-gray-50 dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 flex justify-end items-center">
                                                                <span class="text-[9px] text-gray-400 mr-2">Petugas Hari Ini:</span>
                                                                <select name="assignments[{{ $firstKddk }}]" class="text-[10px] py-0.5 px-2 border-gray-200 rounded bg-white focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white w-40">
                                                                    <option value="">- Pilih Petugas -</option>
                                                                    @foreach($officers as $off)
                                                                        <option value="{{ $off->id }}" {{ $customers->first()->user_pendataan == $off->id ? 'selected' : '' }}>
                                                                            {{ Str::limit($off->name, 20) }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>

                                                            {{-- Indikator Drop --}}
                                                            <div class="hidden drop-indicator absolute inset-0 bg-green-100/90 text-green-800 z-20 flex items-center justify-center text-xs font-bold border-2 border-dashed border-green-500">
                                                                <i class="fas fa-file-import mr-1"></i> Pindah ke Hari Baca {{ $digit7 }}
                                                            </div>

                                                            {{-- Tabel Pelanggan --}}
                                                            <table class="min-w-full text-xs text-left">
                                                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                                    @foreach($customers as $c)
                                                                        @php $seq = substr($c->kddk, 7, 3); @endphp
                                                                        <tr class="draggable-idpel hover:bg-yellow-50 dark:hover:bg-gray-700 transition cursor-move group" 
                                                                            draggable="true" data-idpel="{{ $c->idpel }}" data-origin-prefix="{{ $routePrefix }}">
                                                                            <td class="py-1.5 pl-2 w-4 text-center text-gray-300 group-hover:text-indigo-400"><i class="fas fa-grip-vertical text-[9px]"></i></td>
                                                                            <td class="py-1.5 w-8 font-mono font-bold text-indigo-600">{{ $seq }}</td>
                                                                            <td class="py-1.5 px-1">
                                                                                <div class="font-bold text-gray-700 dark:text-gray-200">{{ $c->idpel }}</div>
                                                                                <div class="text-[9px] text-gray-400">{{ $c->nomor_meter_kwh }}</div>
                                                                            </td>
                                                                            <td class="py-1.5 px-1 truncate max-w-[100px]" title="{{ $c->nomor_meter_kwh }}">{{ Str::limit($c->nomor_meter_kwh, 15) }}</td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach {{-- End Loop Hari Baca --}}

                                        </div>
                                    </div>
                                @endforeach {{-- End Loop Kode Rute --}}
                            </div>
                        </div>
                    @empty
                        <div class="p-10 text-center text-gray-500">
                            <div class="inline-block p-3 rounded-full bg-gray-100 mb-2"><i class="fas fa-box-open text-2xl text-gray-400"></i></div>
                            <p>Belum ada data.</p>
                        </div>
                    @endforelse
                </div>
                </form>
            </div>
        </div>

        {{-- PANEL KANAN: PETA (65% Width - Full Height) --}}
        <div class="flex-1 bg-gray-200 dark:bg-gray-800 relative h-full hidden md:block">
            
            {{-- Map Controls Overlay --}}
            <div class="absolute top-4 left-4 z-[400] bg-white/90 backdrop-blur-sm p-2 rounded-lg shadow-md border border-gray-200 flex items-center space-x-3">
                <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded font-bold" id="map-count">0 Titik</span>
                <div class="h-4 w-px bg-gray-300 mx-2"></div>
                <span class="text-xs text-gray-700 font-bold truncate max-w-[250px]" id="map-context-title">Pilih Area/Rute di kiri</span>
            </div>

            {{-- Data Map (Hidden) --}}
            <div id="rbm-map-data" data-points="{{ json_encode($mapData ?? []) }}" class="hidden"></div>
            <input type="hidden" id="map-data-url" value="{{ route('team.matrix_kddk.map_data', ['unit' => $unitCode]) }}">

            <div id="rbm-map" class="w-full h-full"></div>
        </div>

    </div>

    {{-- DROP ZONE REMOVE (Floating) --}}
    <div id="remove-drop-zone" 
         class="hidden fixed bottom-10 left-10 z-[100] w-48 h-48 rounded-full bg-red-100/90 backdrop-blur border-4 border-dashed border-red-400 flex flex-col items-center justify-center text-red-600 shadow-2xl transition-all duration-300 opacity-0 transform translate-y-20 scale-50 kddk-remove-zone hover:scale-110 hover:bg-red-200">
        <i class="fas fa-trash-alt text-4xl mb-2"></i>
        <p class="text-[10px] font-bold uppercase tracking-wide">Lepas Disini</p>
    </div>

    {{-- CONTEXT MENU & MODAL (Include atau simpan di sini) --}}
    @include('team.matrix_kddk.partials.drag_components') 
    
</div>

{{-- CSS KHUSUS FULLSCREEN --}}
<style>
    /* Mode Fullscreen */
    body.rbm-fullscreen #sidebarMenu, 
    body.rbm-fullscreen header, 
    body.rbm-fullscreen nav {
        display: none !important;
    }
    
    #rbm-workspace.fullscreen-mode {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: 9999;
        margin: 0 !important;
        border-radius: 0;
    }
</style>