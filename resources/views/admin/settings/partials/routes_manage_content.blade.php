<div class="p-6 bg-white dark:bg-gray-800 rounded-lg h-full flex flex-col">

    {{-- HEADER --}}
    <div class="flex justify-between items-center mb-2 pb-2 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center">
            <div class="h-10 w-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center mr-3 shadow-sm">
                <i class="fas fa-route text-lg"></i>
            </div>
            <div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                    Manajemen Rute <span class="text-indigo-600">{{ $areaCode }}</span>
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Total: <span id="total-routes-count" class="font-bold">{{ count($routes) }}</span> Rute
                </p>
            </div>
        </div>

        <button onclick="App.Tabs.closeTab('Rute [{{ $areaCode }}]')"
            class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition flex items-center">
            <i class="fas fa-times mr-2"></i> Tutup Tab
        </button>
    </div>

    {{-- VISUAL GUIDE (SKEMA RPP) --}}
    <div class="mb-5 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-lg p-3">
        <div class="flex items-start gap-3">
            <div class="text-blue-500 mt-1"><i class="fas fa-info-circle"></i></div>
            <div class="flex-1">
                <h4 class="text-xs font-bold text-blue-800 dark:text-blue-300 uppercase mb-2">Panduan Struktur Kode RPP dan RBM (12 Digit)</h4>
                
                {{-- DIAGRAM SKEMA --}}
                <div class="flex flex-wrap items-center text-center text-[10px] font-mono gap-1">
                    
                    {{-- 1. Blok Unit (Digit 1-3) --}}
                    <div class="flex flex-col opacity-50">
                        <div class="flex gap-0.5">
                            <span class="bg-gray-200 dark:bg-gray-700 px-1.5 py-1 rounded-l border border-gray-500">Digit 1</span>
                            <span class="bg-gray-200 dark:bg-gray-700 px-1.5 py-1 border-t border-b border-gray-500">Digit 2</span>
                            <span class="bg-gray-200 dark:bg-gray-700 px-1.5 py-1 rounded-r border border-gray-500">Digit 3</span>
                        </div>
                        <span class="mt-1 text-black font-semibold">UNIT (UP3 + ULP + SUB)</span>
                    </div>

                    <span class="text-gray-300 mx-1">+</span>

                    {{-- 2. Blok Area (Digit 4-5) --}}
                    <div class="flex flex-col">
                        <div class="flex gap-0.5">
                            <span class="bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 font-bold px-1.5 py-1 rounded-l border border-indigo-200">Digit 4</span>
                            <span class="bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 font-bold px-1.5 py-1 rounded-r border border-indigo-200">Digit 5</span>
                        </div>
                        <span class="mt-1 text-indigo-600 font-bold">AREA BACA</span>
                    </div>

                    <span class="text-gray-300 mx-1">+</span>

                    {{-- 3. Blok Rute (Digit 6-7) - INI YANG DIINPUT --}}
                    <div class="flex flex-col relative group cursor-help">
                        <div class="flex gap-0.5">
                            {{-- DIGIT 6: KELOMPOK/PETUGAS --}}
                            <div class="flex flex-col">
                                <span class="bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 font-bold px-1.5 py-1 rounded-l border border-green-200 ring-2 ring-green-400 ring-offset-1">
                                    Digit 6
                                </span>
                            </div>
                            
                            {{-- DIGIT 7: HARI BACA --}}
                            <div class="flex flex-col">
                                <span class="bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 font-bold px-1.5 py-1 rounded-r border border-green-200 ring-2 ring-green-400 ring-offset-1">
                                    Digit 7
                                </span>
                            </div>
                        </div>
                        <span class="mt-1 text-green-600 font-bold">RUTE</span>
                        
                        {{-- Tooltip Penjelasan --}}
                        <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 w-56 bg-gray-800 text-white text-[9px] p-2 rounded hidden group-hover:block z-50 text-left shadow-lg leading-relaxed">
                            <strong class="text-green-300">KODE RUTE RPP (Digit 6 & 7):</strong><br>
                            • <strong>Digit 6 (Kelompok):</strong> Digunakan sebagai Kode Petugas / Grup Rute.<br>
                            • <strong>Digit 7 (Hari):</strong> Digunakan sebagai Kode Hari Baca Ke (A=1, B=2, dst).
                        </div>
                    </div>

                    <span class="text-gray-300 mx-1">+</span>

                    {{-- 4. Blok Urut (Digit 8-12) --}}
                    <div class="flex flex-col opacity-50">
                        <div class="flex gap-0.5">
                            <span class="bg-gray-200 dark:bg-gray-700 px-1.5 py-1 rounded-l border border-gray-500">NO.URUT</span>
                        </div>
                        <span class="mt-1 text-black font-semibold">PELANGGAN</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TOOLBAR INPUT --}}
    <div class="bg-gray-50 dark:bg-gray-700/30 p-3 rounded-lg border border-gray-200 dark:border-gray-600 mb-4 flex flex-col md:flex-row gap-3 items-end">
        
        {{-- Search --}}
        <div class="relative flex-1 w-full">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-search"></i></span>
            <input type="text" id="route-search-input" onkeyup="filterRoutesTable()"
                class="w-full py-1.5 pl-9 pr-4 text-sm border-gray-300 rounded focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                placeholder="Cari Kode atau Keterangan...">
        </div>

        {{-- Input Group --}}
        <div class="flex gap-2 items-center w-full md:w-auto">
            <div class="flex flex-col">
                <label class="text-[9px] font-bold text-green-600 uppercase">Kode (Kelompok + Hari)</label>
                <input type="text" id="new-route-code" placeholder="AA" maxlength="2"
                    class="w-24 text-sm font-bold text-center border-green-300 ring-1 ring-green-100 rounded focus:ring-green-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white uppercase"
                    oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '')">
            </div>
            <div class="flex flex-col flex-1 md:w-64">
                <label class="text-[9px] font-bold text-gray-400 uppercase">Keterangan / Nama Petugas</label>
                <input type="text" id="new-route-label" placeholder="Contoh: Rute TRAFO PNM 01 - Hari 1"
                    class="w-full text-sm border-gray-300 rounded focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
            <div class="flex flex-col justify-end">
                <button type="button" onclick="window.settingsHandler.addRouteItem()"
                    class="h-[38px] px-4 bg-green-600 hover:bg-green-700 text-white font-bold rounded shadow flex items-center transition">
                    <i class="fas fa-plus mr-1"></i> Tambah
                </button>
            </div>
        </div>

        <input type="hidden" id="current-area-code" value="{{ $areaCode }}">
    </div>

    {{-- TABEL UTAMA --}}
    <form action="{{ route('admin.settings.update') }}" method="POST" class="ajax-form flex-1 flex flex-col min-h-0" data-success-redirect-tab="Pengaturan">
        @csrf
        <input type="hidden" name="area_code_target" value="{{ $areaCode }}">

        <div class="flex-1 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg relative custom-scrollbar">
            <table class="w-full text-sm text-left table-fixed">
                <thead class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-400 sticky top-0 z-20 shadow-sm">
                    <tr>
                        <th class="text-center w-[100px] py-2 border-r dark:border-gray-600">
                            Area <span class="text-[9px] text-indigo-500 block normal-case">(Digit 4-5)</span>
                        </th>
                        
                        {{-- HEADER BARU: SESUAI SKEMA --}}
                        <th class="text-center w-[70px] py-2 border-r dark:border-gray-600 bg-green-50 dark:bg-green-900/20">
                            KELOMPOK <span class="text-[9px] text-green-600 block normal-case" title="Digit 6 (Petugas)">Digit 6</span>
                        </th>
                        <th class="text-center w-[70px] py-2 border-r dark:border-gray-600 bg-green-50 dark:bg-green-900/20">
                            HARI <span class="text-[9px] text-green-600 block normal-case" title="Digit 7 (Kode Hari)">Digit 7</span>
                        </th>

                        <th class="px-4 py-2 border-r dark:border-gray-600">Keterangan / Label</th>
                        <th class="text-center w-[50px] py-2">Hapus</th>
                    </tr>
                </thead>

                <tbody id="route-new-rows"></tbody>

                @forelse($groupedRoutes as $groupChar => $items)
                    <tbody class="border-b border-gray-200 dark:border-gray-700">
                        {{-- Header Group --}}
                        <tr class="bg-gray-50 dark:bg-gray-800 text-xs font-bold text-gray-500">
                            <td class="text-center py-1 border-r">{{ $areaCode }}</td>
                            <td colspan="4" class="px-2 py-1 bg-indigo-50/50 dark:bg-gray-700 text-indigo-800 dark:text-indigo-300">
                                Kelompok Rute {{ $groupChar }} ({{ count($items) }})
                            </td>
                        </tr>

                        @foreach ($items as $route)
                            @php
                                $idx = $route['original_index'];
                                $count = $route['customer_count'] ?? 0;
                                $hasData = $count > 0;
                                $code = $route['code'] ?? '';
                                
                                // Progress Bar Logic
                                $targetMax = 218; 
                                $percent = ($count / $targetMax) * 100;
                                if($percent > 100) $percent = 100;
                                $barColor = 'bg-green-500'; 
                                if($count > 250) $barColor = 'bg-yellow-400'; 
                                if($count > 350) $barColor = 'bg-red-500';    
                            @endphp
                            <tr class="hover:bg-indigo-50 dark:hover:bg-gray-700 transition-colors route-row-item"
                                data-search="{{ strtolower($code . ' ' . ($route['label'] ?? '')) }}">

                                {{-- Area --}}
                                <td class="text-center border-r dark:border-gray-700 py-1 align-middle">
                                    <span class="text-gray-400 font-bold text-xs">{{ $areaCode }}</span>
                                    <input type="hidden"
                                        name="settings[kddk_config_data][routes_manage][{{ $areaCode }}][{{ $idx }}][code]"
                                        value="{{ $code }}" class="route-code-real">
                                </td>

                                {{-- DIGIT 6: KELOMPOK --}}
                                <td class="text-center font-bold text-gray-800 dark:text-gray-200 border-r dark:border-gray-700 py-1 align-middle bg-green-50/30 dark:bg-green-900/10">
                                    {{ substr($code, 0, 1) }}
                                </td>

                                {{-- DIGIT 7: KODE HARI --}}
                                <td class="text-center font-bold text-gray-800 dark:text-gray-200 border-r dark:border-gray-700 py-1 align-middle bg-green-50/30 dark:bg-green-900/10">
                                    {{ substr($code, 1, 1) }}
                                </td>

                                {{-- Label & Progress --}}
                                <td class="p-2 border-r dark:border-gray-700 align-middle">
                                    <div class="flex flex-col justify-center h-full">
                                        <input type="text"
                                            name="settings[kddk_config_data][routes_manage][{{ $areaCode }}][{{ $idx }}][label]"
                                            value="{{ $route['label'] ?? '' }}"
                                            class="w-full text-sm border-0 bg-transparent focus:ring-0 p-0 text-gray-800 dark:text-gray-200 font-medium placeholder-gray-300"
                                            placeholder="Nama Rute / Petugas">

                                        {{-- Visual Load --}}
                                        @if ($hasData)
                                            <div class="mt-1.5 w-full">
                                                <div class="flex justify-between items-end mb-0.5">
                                                    <div class="text-[10px] text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                                        <i class="fas fa-users text-indigo-400"></i> 
                                                        <span class="font-bold text-indigo-700 dark:text-indigo-300">{{ number_format($count) }}</span> 
                                                        <span class="text-[9px] opacity-70">Plg</span>
                                                    </div>
                                                    <span class="text-[9px] text-gray-400">{{ round($percent) }}% Beban</span>
                                                </div>
                                                <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1.5 overflow-hidden">
                                                    <div class="{{ $barColor }} h-1.5 rounded-full transition-all duration-500" style="width: {{ $percent }}%"></div>
                                                </div>
                                            </div>

                                            @if(str_contains($route['label'], '⚠️'))
                                                <div class="mt-1 text-[9px] text-red-500 font-bold bg-red-50 px-1 rounded inline-block">
                                                    <i class="fas fa-exclamation-circle"></i> Cek Unit
                                                </div>
                                            @endif
                                        @else
                                            <div class="mt-1 text-[10px] text-gray-400 italic opacity-60">Belum ada pelanggan</div>
                                        @endif
                                    </div>
                                </td>

                                {{-- Hapus --}}
                                <td class="text-center py-1 align-middle">
                                    @if ($hasData)
                                        <i class="fas fa-lock text-gray-300 text-xs cursor-not-allowed" title="Terkunci"></i>
                                    @else
                                        <button type="button"
                                            class="text-red-400 hover:text-red-600 p-1 rounded-full hover:bg-red-50 transition remove-route-row">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                @empty
                    <tbody id="empty-state">
                        <tr>
                            <td colspan="5" class="py-10 text-center text-gray-400">
                                <div class="mb-3">
                                    <span class="inline-block p-4 rounded-full bg-gray-50 dark:bg-gray-700">
                                        <i class="fas fa-route text-3xl opacity-30"></i>
                                    </span>
                                </div>
                                <p class="font-bold">Belum ada rute di Area {{ $areaCode }}</p>
                                <p class="text-xs mt-1">Gunakan form di atas (Kelompok & Hari) untuk menambah rute.</p>
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
        </div>

        {{-- FOOTER --}}
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <span class="text-xs text-gray-500 italic">* Klik Simpan untuk menerapkan perubahan.</span>

            <button type="submit"
                class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow transition transform active:scale-95 flex items-center">
                <i class="fas fa-save mr-2"></i> Simpan Semua Rute
            </button>
        </div>
    </form>
</div>

<script>
    function filterRoutesTable() {
        const input = document.getElementById('route-search-input');
        const filter = input.value.toLowerCase();
        const rows = document.querySelectorAll('.route-row-item');

        rows.forEach(row => {
            const text = row.dataset.search || '';
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    }
</script>