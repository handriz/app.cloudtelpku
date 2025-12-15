{{-- PENANDA KONTEKS UNIT --}}
<input type="hidden" id="page-context-unit" value="{{ $unit }}">

{{-- WRAPPER UTAMA --}}
<div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl flex flex-col max-h-[calc(100vh-130px)] min-h-0 border border-gray-200 dark:border-gray-700 relative">
    
    {{-- 1. HEADER & TOOLBAR (Fixed di Atas) --}}
    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-t-xl shrink-0 z-20">
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
            
            {{-- BAGIAN KIRI: JUDUL --}}
            <div>
                <div class="flex items-center gap-3">
                    <div class="bg-indigo-100 dark:bg-indigo-900/50 p-2 rounded-lg text-indigo-600 dark:text-indigo-400">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 leading-tight">
                            Detail Pelanggan
                        </h3>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-600 border border-gray-200 font-mono">
                                UNIT: {{ $unit }}
                            </span>
                            <span class="text-xs text-gray-400">| Seleksi & Grouping</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- BAGIAN KANAN: ACTIONS TOOLBAR --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full xl:w-auto">
                
                {{-- A. SEARCH BAR --}}
                <form action="{{ route('team.matrix_kddk.details', ['unit' => $unit]) }}" method="GET" 
                      class="relative w-full sm:w-60 group" 
                      onsubmit="event.preventDefault(); App.Tabs.loadTabContent(App.Utils.getActiveTabName(), this.action + '?search=' + this.search.value);">
                    
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 group-focus-within:text-indigo-500"></i>
                    </div>
                    <input type="text" name="search" 
                           class="block w-full pl-9 pr-8 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all shadow-sm" 
                           placeholder="Cari IDPEL / Meter..."
                           value="{{ request('search') }}" autocomplete="off">
                    
                    @if(request('search'))
                        <button type="button" 
                                onclick="App.Tabs.loadTabContent(App.Utils.getActiveTabName(), '{{ route('team.matrix_kddk.details', ['unit' => $unit]) }}')"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-red-500 cursor-pointer transition-colors">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    @endif
                </form>

                <div class="w-px h-8 bg-gray-200 dark:bg-gray-700 hidden sm:block mx-1"></div>

                {{-- B. BUTTON GROUP --}}
                <div class="flex items-center gap-2">
                    {{-- Tombol Upload --}}
                    <button type="button" onclick="window.openUploadModal()" 
                            class="flex-1 sm:flex-none flex items-center justify-center px-3 py-2 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/20 hover:text-green-700 hover:border-green-300 transition shadow-sm text-sm font-medium"
                            title="Upload CSV">
                        <i class="fas fa-file-csv text-green-600 text-lg mr-2"></i>
                        <span>Import</span>
                    </button>
                    <input type="file" id="csv-selection-detail" class="hidden" accept=".csv,.txt">

                    {{-- Tombol Grouping (Hidden Default) --}}
                    <button onclick="window.confirmGrouping()" id="btn-group-kddk" 
                            class="hidden flex-1 sm:flex-none items-center justify-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg shadow-md hover:shadow-lg transition transform active:scale-95 animate-fade-in-down">
                        <i class="fas fa-layer-group mr-2"></i> Grouping
                    </button>

                    {{-- Tombol Kembali --}}
                    <button onclick="App.Tabs.loadTabContent(App.Utils.getActiveTabName(), '{{ route('team.matrix_kddk.index') }}')" 
                            class="flex-none w-10 h-10 flex items-center justify-center text-gray-400 hover:text-gray-600 bg-gray-50 hover:bg-gray-200 border border-gray-200 rounded-lg transition"
                            title="Kembali">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                </div>
            </div>
        </div>
        
        {{-- NOTIFIKASI --}}
        <div id="kddk-notification-container" class="mt-2"></div>
    </div>

    {{-- 2. TABEL SCROLLABLE (Bagian Tengah) --}}
    <div class="flex-1 min-h-0 overflow-auto custom-scrollbar bg-gray-50 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 border-separate border-spacing-0">
             <thead class="bg-gray-100 dark:bg-gray-800 sticky top-0 z-10 shadow-sm">
                <tr>
                    <th class="px-4 py-3 text-center w-12 border-b dark:border-gray-700">
                        <input type="checkbox" id="check-all-rows" class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer">
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b dark:border-gray-700">
                        Informasi Pelanggan
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider border-b dark:border-gray-700">
                        Data Lapangan
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider border-b dark:border-gray-700 w-24">
                        Status
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider border-b dark:border-gray-700 w-32">
                        KDDK
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($customers as $c)
                <tr class="hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition group" data-idpel="{{ $c->idpel }}">
                    {{-- Checkbox --}}
                    <td class="px-4 py-3 text-center">
                        <input type="checkbox" name="idpel_select[]" value="{{ $c->idpel }}" data-jenis="{{ $c->jenislayanan ?? 'UMUM' }}" class="row-checkbox rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer">
                    </td>

                    {{-- Info Pelanggan --}}
                    <td class="px-4 py-3 align-top">
                        <div class="flex flex-col">
                            <span class="text-sm font-bold text-indigo-700 dark:text-indigo-400 font-mono">{{ $c->idpel }}</span>
                            <span class="text-xs text-gray-600 dark:text-gray-300 font-medium mt-0.5">
                                <i class="fas fa-tachometer-alt text-gray-400 mr-1"></i> {{ $c->nomor_meter_kwh ?? '-' }}
                            </span>
                            <span class="text-[10px] text-gray-400 mt-1 uppercase tracking-wide">
                                {{ $c->merk_meter_kwh }} • {{ $c->tarif }}/{{ $c->daya }}
                            </span>
                        </div>
                    </td>
                    
                    {{-- Data Lapangan (Compact) --}}
                    <td class="px-4 py-3 align-top">
                        <div class="space-y-2">
                            {{-- Baris 1: Koordinat & User --}}
                            <div class="flex items-center justify-between gap-2">
                                @if($c->latitudey && $c->longitudex)
                                    <a href="https://www.google.com/maps?q={{ $c->latitudey }},{{ $c->longitudex }}" target="_blank" 
                                       class="flex items-center text-[10px] font-bold text-green-700 bg-green-100 hover:bg-green-200 px-2 py-1 rounded border border-green-200 transition"
                                       title="Lihat di Maps">
                                        <i class="fas fa-map-marker-alt mr-1.5"></i>
                                        {{ number_format($c->latitudey, 5) }}, {{ number_format($c->longitudex, 5) }}
                                    </a>
                                @else
                                    <span class="text-[10px] font-bold text-gray-400 bg-gray-100 px-2 py-1 rounded border border-gray-200 cursor-not-allowed">
                                        <i class="fas fa-ban mr-1"></i> No Coord
                                    </span>
                                @endif
                                
                                <span class="text-[10px] text-gray-500 truncate max-w-[100px]" title="{{ $c->user_pendataan }}">
                                    <i class="fas fa-user text-gray-300 mr-1"></i> {{ Str::limit($c->user_pendataan, 12) }}
                                </span>
                            </div>

                            {{-- Baris 2: Foto & Gardu --}}
                            <div class="flex items-center gap-2">
                                @if($c->foto_kwh)
                                    <button class="image-zoom-trigger group/btn relative px-2 py-1 bg-white border border-gray-300 rounded hover:border-indigo-500 hover:text-indigo-600 transition text-[10px]"
                                            data-zoom-type="image">
                                        <i class="fas fa-camera"></i> KWH
                                        <img src="{{ asset('storage/' . $c->foto_kwh) }}" class="hidden">
                                    </button>
                                @endif

                                @if($c->foto_bangunan)
                                    <button class="image-zoom-trigger group/btn relative px-2 py-1 bg-white border border-gray-300 rounded hover:border-indigo-500 hover:text-indigo-600 transition text-[10px]"
                                            data-zoom-type="bangunan">
                                        <i class="fas fa-home"></i> Rumah
                                        <img src="{{ asset('storage/' . $c->foto_bangunan) }}" class="hidden">
                                    </button>
                                @endif

                                @if($c->namagd)
                                    <span class="text-[10px] bg-yellow-50 text-yellow-700 border border-yellow-200 px-1.5 py-0.5 rounded ml-auto">
                                        {{ $c->namagd }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </td>

                    {{-- Status DIL --}}
                    <td class="px-4 py-3 text-center align-middle">
                        @php
                            $status = strtoupper(trim($c->status_dil));
                            $activeKeywords = ['1', 'NYALA', 'AKTIF', 'HIDUP', 'ON'];
                            $isActive = in_array($status, $activeKeywords);
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ $isActive ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $isActive ? 'bg-green-500' : 'bg-red-500' }} mr-1.5"></span>
                            {{ $isActive ? 'AKTIF' : 'NON' }}
                        </span>
                    </td>

                    {{-- KDDK --}}
                    <td class="px-4 py-3 text-center align-middle">
                        <span class="font-mono text-xs font-bold text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                            {{ $c->current_kddk ?? '-' }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-16 text-center">
                        <div class="flex flex-col items-center justify-center text-gray-400">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-3">
                                <i class="fas fa-check-double text-2xl text-gray-300"></i>
                            </div>
                            <h4 class="text-sm font-bold text-gray-600">Semua Beres!</h4>
                            <p class="text-xs mt-1">Tidak ada pelanggan tanpa grup di unit ini.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- 3. FOOTER & PAGINASI (Fixed di Bawah) --}}
    <div class="bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 rounded-b-xl shrink-0">
        {{ $customers->links() }}
    </div>

    {{-- ======================================================= --}}
    {{-- MODAL AREA (TETAP SAMA TAPI DENGAN Z-INDEX DIPERBAIKI) --}}
    {{-- ======================================================= --}}


    {{-- 1. MODAL GENERATOR KDDK (FIXED SCROLL & LAYOUT) --}}
    <div id="modal-create-kddk" class="fixed inset-0 bg-gray-900/80 hidden items-center justify-center z-[1500] p-4 backdrop-blur-sm transition-opacity opacity-0 flex">
        
        {{-- Container Modal --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col max-h-[90vh] transform transition-all scale-95 duration-300 relative">
            
            <form action="{{ route('team.matrix_kddk.store_group') }}" 
                method="POST" 
                class="ajax-form flex flex-col h-full overflow-hidden" {{-- PENTING: h-full & overflow-hidden --}}
                id="kddk-generator-form" 
                data-success-redirect-tab="Matrix KDDK"
                data-sequence-url="{{ route('team.matrix_kddk.next_sequence', '') }}">
                
                @csrf
                <input type="hidden" name="unitup" value="{{ $unit }}">
                
                {{-- A. HEADER (FIXED) --}}
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 flex justify-between items-center shrink-0 z-10 rounded-t-2xl">
                    <div>
                        <h3 class="text-lg font-extrabold text-gray-900 dark:text-white flex items-center">
                            <span class="bg-indigo-100 text-indigo-600 w-8 h-8 rounded-lg flex items-center justify-center mr-3 text-sm">
                                <i class="fas fa-barcode"></i>
                            </span>
                            Generator KDDK
                        </h3>
                        <p class="text-xs text-gray-500 ml-11">Setup kode rute untuk pelanggan terpilih.</p>
                    </div>
                    <div class="bg-green-50 text-green-700 px-3 py-1.5 rounded-lg text-xs font-bold border border-green-100 shadow-sm">
                        <i class="fas fa-users mr-1.5"></i> <span id="count-selected" class="text-sm">0</span> <span class="text-[10px] uppercase text-green-500 ml-1">Dipilih</span>
                    </div>
                </div>
                
                {{-- B. BODY (SCROLLABLE) --}}
                {{-- PENTING: overflow-y-auto di sini agar scrollbar muncul --}}
                <div class="p-6 overflow-y-auto flex-1 custom-scrollbar bg-gray-50/50 dark:bg-gray-900/50">
                    <div id="hidden-inputs-container"></div> 

                    {{-- SECTION 1: PREVIEW BOX --}}
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-indigo-100 dark:border-indigo-900 p-5 mb-6 shadow-sm relative overflow-hidden group">
                        <div class="absolute top-0 right-0 p-2 opacity-10 group-hover:opacity-20 transition">
                            <i class="fas fa-qrcode text-6xl text-indigo-500"></i>
                        </div>
                        
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Estimasi Hasil Kode</label>
                        
                        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mt-2">
                            <div class="text-center">
                                <span class="block text-[10px] text-gray-400 mb-1">Awal</span>
                                <div id="preview-start" class="font-mono text-xl sm:text-2xl font-black text-gray-800 dark:text-white tracking-tight border-b-2 border-dashed border-gray-300 pb-1">
                                    _______001__
                                </div>
                            </div>
                            
                            <div class="text-gray-300 dark:text-gray-600"><i class="fas fa-arrow-right"></i></div>
                            
                            <div class="text-center">
                                <span class="block text-[10px] text-gray-400 mb-1">Akhir</span>
                                <div id="preview-end" class="font-mono text-xl sm:text-2xl font-black text-indigo-600 tracking-tight border-b-2 border-dashed border-indigo-200 pb-1">
                                    _______0XX__
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-3 h-5">
                            <p id="kddk_error_msg" class="text-xs font-bold text-red-500"></p>
                        </div>
                        <input type="hidden" id="final_kddk_preview"> 
                    </div>

                    {{-- SECTION 2: GRID INPUT --}}
                    <div class="space-y-5">
                        
                        {{-- Baris 1: Hirarki --}}
                        <div>
                            <label class="block text-xs font-extrabold text-gray-700 dark:text-gray-300 uppercase mb-3 border-b border-gray-200 pb-1">Hirarki Unit</label>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <span class="block text-[10px] font-bold text-gray-500 uppercase mb-1.5 ml-1">UP3</span>
                                    <input type="text" id="part_up3" value="{{ $autoCodes['up3'] ?? '_' }}" class="w-full bg-gray-100 border-gray-300 rounded-lg text-sm font-bold text-gray-500 py-2.5 text-center cursor-not-allowed kddk-part" readonly>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-bold text-gray-500 uppercase mb-1.5 ml-1">ULP</span>
                                    <input type="text" id="part_ulp" value="{{ $autoCodes['ulp'] ?? '_' }}" class="w-full bg-gray-100 border-gray-300 rounded-lg text-sm font-bold text-gray-500 py-2.5 text-center cursor-not-allowed kddk-part" readonly>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-bold text-gray-500 uppercase mb-1.5 ml-1">Sub Unit</span>
                                    @if(isset($subUnits) && $subUnits->isNotEmpty())
                                        <select id="part_sub" class="w-full bg-white border-gray-300 rounded-lg text-sm font-bold text-gray-800 py-2.5 text-center focus:ring-indigo-500 kddk-part">
                                            <option value="">-</option>
                                            <option value="A">A</option>
                                            @foreach($subUnits as $sub)
                                                <option value="{{ $sub->kddk_code }}">{{ $sub->kddk_code }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" id="part_sub" value="{{ $autoCodes['sub'] }}" class="w-full bg-gray-100 border-gray-300 rounded-lg text-sm font-bold text-gray-500 py-2.5 text-center cursor-not-allowed kddk-part" readonly>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Baris 2: Wilayah & Rute --}}
                        <div class="p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-xl border border-indigo-100 dark:border-indigo-800">
                            <label class="block text-xs font-extrabold text-indigo-700 dark:text-indigo-300 uppercase mb-3">Penentuan Rute</label>
                            <div class="grid grid-cols-2 gap-4">
                                {{-- Area --}}
                                <div>
                                    <span class="block text-[10px] font-bold text-indigo-600 uppercase mb-1.5 ml-1">Kode Area (Dgt 4-5)</span>
                                    <select id="part_area" class="w-full bg-white border-indigo-300 rounded-lg text-sm font-bold text-gray-800 py-2.5 focus:ring-indigo-500 focus:border-indigo-500 kddk-part">
                                        <option value="">-- Pilih Area --</option>
                                        @foreach($kddkConfig['areas'] ?? [] as $area)
                                            <option value="{{ $area['code'] }}" data-label="{{ $area['label'] }}" data-routes="{{ json_encode($area['routes'] ?? []) }}">{{ $area['code'] }} - {{ Str::limit($area['label'], 30) }}</option>
                                        @endforeach
                                    </select>
                                    <div id="area-label-display" class="text-[10px] font-bold text-indigo-400 mt-1 h-3 truncate px-1"></div>
                                </div>

                                {{-- Rute --}}
                                <div>
                                    <span class="block text-[10px] font-bold text-indigo-600 uppercase mb-1.5 ml-1">Kelompok Rute (Dgt 6-7)</span>
                                    <select id="part_rute" class="w-full bg-white border-indigo-300 rounded-lg text-sm font-bold text-gray-800 py-2.5 focus:ring-indigo-500 focus:border-indigo-500 kddk-part" onchange="window.updateLabelDisplay && window.updateLabelDisplay()">
                                        <option value="">-- Pilih Area Dulu --</option>
                                    </select>
                                    <div id="rute-label-display" class="text-[10px] font-bold text-indigo-400 mt-1 h-3 truncate px-1"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Baris 3: Urutan & Sisipan --}}
                        <div class="grid grid-cols-12 gap-4">
                            <div class="col-span-8">
                                <div class="flex justify-between items-end mb-1">
                                    <label class="lbl-input mb-0">SEQUENCE (DGT 8-10)</label>
                                    
                                    {{-- CHECKBOX MODE SISIP --}}
                                    <div class="flex items-center">
                                        {{-- TAMBAHAN: onchange="window.toggleManualSequence(this)" --}}
                                        <input type="checkbox" id="mode_insert_sequence" 
                                            onchange="window.toggleManualSequence(this)"
                                            class="w-3 h-3 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500 cursor-pointer mr-1">
                                        <label for="mode_insert_sequence" class="text-[9px] font-bold text-indigo-600 cursor-pointer select-none">Sisipkan (Manual)</label>
                                    </div>
                                </div>
                                
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 font-mono text-xs">#</span>
                                    
                                    {{-- INPUT SEQUENCE --}}
                                    {{-- TAMBAHAN: oninput="window.handleManualSequenceInput(this)" --}}
                                    <input type="text" id="part_urut" value="..." 
                                        oninput="window.handleManualSequenceInput(this)"
                                        class="w-full pl-7 bg-gray-100 border-gray-300 rounded-lg text-sm font-bold text-gray-500 py-2.5 font-mono tracking-widest kddk-part" 
                                        readonly>
                                </div>
                            </div>
                            <div class="col-span-4">
                                <span class="block text-[10px] font-bold text-gray-500 uppercase mb-1.5 ml-1">Sisipan (Dgt 11-12)</span>
                                <input type="text" id="part_sisip" maxlength="2" value="00" class="w-full bg-white border-gray-300 rounded-lg text-sm font-bold text-gray-800 py-2.5 text-center font-mono kddk-part focus:ring-green-500 focus:border-green-500">
                            </div>
                        </div>

                        {{-- Info Tambahan --}}
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">Sampel Urutan (3 Data Pertama)</label>
                            <div id="sequence-preview-list" class="space-y-1 text-xs font-mono text-gray-600 dark:text-gray-300">
                                {{-- Diisi JS --}}
                            </div>
                        </div>

                    </div>
                    
                    {{-- Hidden Inputs --}}
                    <input type="hidden" name="kddk_code" id="hidden_full_code_prefix">
                    <input type="hidden" name="prefix_code" id="hidden_prefix_code"> 
                    <input type="hidden" name="sisipan" id="hidden_sisipan">
                </div>

                {{-- C. FOOTER (FIXED) --}}
                <div class="px-6 py-4 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3 shrink-0 z-10 rounded-b-2xl">
                    <button type="button" onclick="window.closeKddkModal()" class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 font-bold rounded-xl hover:bg-gray-50 text-sm transition shadow-sm">
                        Batal
                    </button>
                    <button type="submit" id="btn-save-kddk" class="px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl shadow-lg hover:bg-indigo-700 hover:-translate-y-0.5 text-sm transition transform disabled:opacity-50 disabled:cursor-not-allowed flex items-center" disabled>
                        <i class="fas fa-save mr-2"></i> Simpan Group
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    {{-- 2. MODAL KONFIRMASI (Styled) --}}
    <div id="modal-confirm-selection" class="fixed inset-0 bg-gray-900/80 hidden items-center justify-center z-[1600] p-4 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-sm transform transition-all p-6 text-center">
            <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-clipboard-list text-2xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Konfirmasi Seleksi</h3>
            <p class="text-sm text-gray-500 mb-4">
                Total <span id="confirm-total-count" class="font-bold text-indigo-600 text-lg">0</span> pelanggan akan diproses.
            </p>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 mb-6 text-left border border-gray-100 dark:border-gray-600 max-h-32 overflow-y-auto custom-scrollbar">
                <ul id="confirm-detail-list" class="space-y-1 text-xs"></ul>
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('modal-confirm-selection').classList.add('hidden')" class="flex-1 px-4 py-2 bg-white border border-gray-300 text-gray-700 font-bold rounded-lg hover:bg-gray-50">Batal</button>
                <button type="button" onclick="window.proceedToGenerator()" class="flex-1 px-4 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 shadow-lg">Lanjut</button>
            </div>
        </div>
    </div>

    {{-- 3. MODAL SUKSES (Styled) --}}
    <div id="modal-success-generator" class="fixed inset-0 bg-gray-900/80 hidden items-center justify-center z-[1700] p-4 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm transform transition-all scale-100 overflow-hidden text-center">
            <div class="bg-green-500 p-6 flex justify-center relative overflow-hidden">
                <div class="absolute inset-0 bg-white opacity-10 transform -skew-y-12"></div>
                <div class="w-20 h-20 bg-white text-green-500 rounded-full flex items-center justify-center text-4xl shadow-lg relative z-10 animate-bounce-short">
                    <i class="fas fa-check"></i>
                </div>
            </div>
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Sukses!</h3>
                <p id="success-modal-message" class="text-gray-600 dark:text-gray-300 text-sm mb-5">Grup KDDK baru berhasil dibuat.</p>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-4 border border-gray-100 dark:border-gray-600 flex justify-between items-center text-sm">
                    <div class="text-left">
                        <span class="block text-[10px] text-gray-400 uppercase font-bold">Start Code</span>
                        <span id="success-start-code" class="font-mono font-bold text-indigo-600"></span>
                    </div>
                    <div class="text-right">
                        <span class="block text-[10px] text-gray-400 uppercase font-bold">Total</span>
                        <span id="success-total-count" class="font-bold text-gray-800 dark:text-white"></span>
                    </div>
                </div>
            </div>
            <div class="p-6 pt-0">
                <button type="button" onclick="window.closeSuccessModal()" class="w-full px-4 py-3 bg-gray-900 text-white font-bold rounded-xl hover:bg-gray-800 shadow-lg transition">
                    Tutup & Refresh
                </button>
            </div>
        </div>
    </div>
    
    {{-- 4. MODAL UPLOAD (FIXED STYLES) --}}
    <div id="modal-upload-csv-preview" class="fixed inset-0 bg-gray-900/80 hidden items-center justify-center z-[2000] p-4 backdrop-blur-sm transition-opacity opacity-0">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg transform scale-95 transition-all duration-300" id="upload-modal-panel">
            
            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                <h3 class="text-lg font-extrabold text-gray-900 dark:text-white flex items-center">
                    <i class="fas fa-cloud-upload-alt text-indigo-500 mr-2.5"></i> Upload Data IDPEL
                </h3>
                <button onclick="window.closeUploadModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-6">
                {{-- Drop Zone --}}
                <div id="upload-drop-zone" class="relative group w-full h-56">
                    <input type="file" id="real-file-input" onchange="window.handleFileFromInput(this)" class="absolute inset-0 w-full h-full opacity-0 z-50 cursor-pointer" accept=".csv, .txt">
                    <div class="absolute inset-0 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl flex flex-col items-center justify-center transition-all duration-200 group-hover:border-indigo-500 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/20 pointer-events-none" id="drop-zone-visual">
                        <div class="w-14 h-14 bg-indigo-50 dark:bg-indigo-900/50 text-indigo-600 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                            <i class="fas fa-file-csv text-2xl"></i>
                        </div>
                        <h4 class="text-sm font-bold text-gray-700 dark:text-gray-200">Klik atau Drag file CSV</h4>
                        <p class="text-xs text-gray-500 mt-1">Satu IDPEL per baris</p>
                    </div>
                </div>

                {{-- Loading --}}
                <div id="upload-loading" class="hidden py-6 text-center">
                    <i class="fas fa-spinner fa-spin text-3xl text-indigo-500 mb-2"></i>
                    <p class="text-xs font-bold text-gray-500">Memproses...</p>
                </div>

                {{-- Result Stats (Fixed Styles) --}}
                <div id="upload-result-stats" class="hidden mt-6 animate-fade-in-down">
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-600 p-4">
                        
                        {{-- File Info --}}
                        <div class="flex items-center justify-between mb-3 pb-3 border-b border-gray-200 dark:border-gray-600">
                            <span class="text-xs font-bold text-gray-500 uppercase">File</span>
                            <span id="stat-filename" class="text-xs font-mono font-bold text-indigo-600 truncate max-w-[200px]"></span>
                        </div>

                        {{-- Grid 4 Kolom --}}
                        <div class="grid grid-cols-4 gap-2 text-center">
                            {{-- 1. Total --}}
                            <div class="p-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-600">
                                <span class="block text-[9px] text-gray-400 font-bold uppercase">Total</span>
                                <span id="stat-total" class="text-base font-extrabold text-gray-800 dark:text-gray-200">0</span>
                            </div>

                            {{-- 2. Ready (Hijau) --}}
                            <div class="p-2 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-100 dark:border-green-800">
                                <span class="block text-[9px] text-green-600 font-bold uppercase">Ready</span>
                                <span id="stat-valid" class="text-base font-extrabold text-green-600">0</span>
                            </div>

                            {{-- 3. Mapped / Sudah Ada (Kuning) --}}
                            <div class="p-2 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-100 dark:border-yellow-800" title="Sudah memiliki KDDK">
                                <span class="block text-[9px] text-yellow-600 font-bold uppercase">Sudah Ada KDDK</span>
                                <span id="stat-mapped" class="text-base font-extrabold text-yellow-600">0</span>
                            </div>

                            {{-- 4. Invalid (Merah) --}}
                            <div class="p-2 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-100 dark:border-red-800">
                                <span class="block text-[9px] text-red-500 font-bold uppercase">Invalid</span>
                                <span id="stat-invalid" class="text-base font-extrabold text-red-500">0</span>
                            </div>
                        </div>
                        
                        {{-- Pesan Info --}}
                        <div class="mt-2 text-[10px] text-center text-gray-400">
                            *Hanya data <strong>Ready</strong> yang akan diproses.
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer (Fixed Button Styles) --}}
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800 rounded-b-2xl border-t border-gray-100 dark:border-gray-700 flex justify-end gap-3">
                <button onclick="window.closeUploadModal()" 
                        class="px-4 py-2 text-sm font-bold text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition shadow-sm">
                    Batal
                </button>
                <button id="btn-apply-upload" disabled onclick="window.applyUploadSelection()" 
                        class="px-5 py-2 text-sm font-bold text-white bg-indigo-600 rounded-lg shadow-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition transform active:scale-95 flex items-center">
                    <span>Gunakan Data</span> <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>
    </div>

    {{-- 5. MODAL WARNING GENERIC (KUNING) --}}
    <div id="modal-warning-generic" class="fixed inset-0 bg-gray-900/80 hidden items-center justify-center z-[2200] p-4 backdrop-blur-sm transition-opacity opacity-0">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-sm transform transition-all scale-100 overflow-hidden text-center relative">
            
            {{-- Header Warna Kuning --}}
            <div class="bg-yellow-500 p-6 flex justify-center relative overflow-hidden">
                <div class="absolute inset-0 bg-white opacity-10 transform -skew-y-12"></div>
                <div class="w-20 h-20 bg-white text-yellow-500 rounded-full flex items-center justify-center text-4xl shadow-lg relative z-10 animate-bounce-short">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>

            {{-- Body Content --}}
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Peringatan</h3>
                <p id="warning-modal-message" class="text-gray-600 dark:text-gray-300 text-sm mb-4 leading-relaxed">
                    Pesan peringatan akan muncul di sini.
                </p>
            </div>

            {{-- Footer --}}
            <div class="p-4 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-100 dark:border-gray-700">
                <button type="button" onclick="document.getElementById('modal-warning-generic').classList.add('hidden'); document.getElementById('modal-warning-generic').classList.remove('flex');" 
                        class="w-full px-4 py-2.5 bg-gray-900 dark:bg-gray-600 text-white font-bold rounded-xl hover:bg-gray-800 dark:hover:bg-gray-500 shadow-lg transition transform active:scale-95">
                    Mengerti
                </button>
            </div>
        </div>
    </div>



    {{-- HELPER CSS CLASSES (Inline Style untuk Custom Komponen) --}}
    <style>
        .min-h-0 { min-height: 0; }
        @keyframes bounce-short { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
        .animate-bounce-short { animation: bounce-short 0.5s ease-in-out 2; }
        
        @keyframes fadeInDown { from { opacity: 0; transform: translate3d(0, -10px, 0); } to { opacity: 1; transform: translate3d(0, 0, 0); } }
        .animate-fade-in-down { animation: fadeInDown 0.3s ease-out forwards; }
    </style>

</div>