<div class="p-6 bg-white dark:bg-gray-800 rounded-lg h-full flex flex-col">

    {{-- HEADER --}}
    <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center">
            <div
                class="h-10 w-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center mr-3 shadow-sm">
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

    {{-- TOOLBAR INPUT (ADD NEW) --}}
    <div
        class="bg-gray-50 dark:bg-gray-700/30 p-3 rounded-lg border border-gray-200 dark:border-gray-600 mb-4 flex flex-col md:flex-row gap-3 items-end">

        {{-- Search (Filter Client Side) --}}
        <div class="relative flex-1 w-full">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i
                    class="fas fa-search"></i></span>
            <input type="text" id="route-search-input" onkeyup="filterRoutesTable()"
                class="w-full py-1.5 pl-9 pr-4 text-sm border-gray-300 rounded focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                placeholder="Cari Kode atau Keterangan...">
        </div>

        {{-- Input Group Tambah Baru --}}
        <div class="flex gap-2 items-center w-full md:w-auto">
            <div class="flex flex-col">
                <label class="text-[10px] font-bold text-gray-400 uppercase">Kode</label>
                <input type="text" id="new-route-code" placeholder="AA" maxlength="2"
                    class="w-16 text-sm font-bold text-center border-gray-300 rounded focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white uppercase"
                    oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '')">
            </div>
            <div class="flex flex-col flex-1 md:w-64">
                <label class="text-[10px] font-bold text-gray-400 uppercase">Keterangan Rute</label>
                <input type="text" id="new-route-label" placeholder="Contoh: Rute Batu Aji"
                    class="w-full text-sm border-gray-300 rounded focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
            <div class="flex flex-col justify-end">
                <button type="button" onclick="window.settingsHandler.addRouteItem()"
                    class="h-[38px] px-4 bg-green-600 hover:bg-green-700 text-white font-bold rounded shadow flex items-center transition">
                    <i class="fas fa-plus mr-1"></i> Tambah
                </button>
            </div>
        </div>

        {{-- Hidden Input untuk JS --}}
        <input type="hidden" id="current-area-code" value="{{ $areaCode }}">
    </div>

    {{-- FORM UTAMA (Tabel Rute) --}}
    <form action="{{ route('admin.settings.update') }}" method="POST" class="ajax-form flex-1 flex flex-col min-h-0"
        data-success-redirect-tab="Pengaturan">
        @csrf
        <input type="hidden" name="area_code_target" value="{{ $areaCode }}">

        <div
            class="flex-1 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg relative custom-scrollbar">
            <table class="w-full text-sm text-left table-fixed">
                <thead
                    class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-400 sticky top-0 z-20 shadow-sm">
                    <tr>
                        <th class="text-center w-[60px] py-2 border-r dark:border-gray-600">Area</th>
                        <th class="text-center w-[40px] py-2 border-r dark:border-gray-600">K1</th>
                        <th class="text-center w-[40px] py-2 border-r dark:border-gray-600">K2</th>
                        <th class="px-4 py-2 border-r dark:border-gray-600">Keterangan / Label</th>
                        <th class="text-center w-[50px] py-2">Hapus</th>
                    </tr>
                </thead>

                {{-- KONTAINER BARIS BARU (JS akan menyisipkan di sini) --}}
                <tbody id="route-new-rows"></tbody>

                {{-- LOOP DATA EKSISTING (GROUPED) --}}
                @forelse($groupedRoutes as $groupChar => $items)
                    <tbody class="border-b border-gray-200 dark:border-gray-700">
                        {{-- Header Group --}}
                        <tr class="bg-gray-50 dark:bg-gray-800 text-xs font-bold text-gray-500">
                            <td class="text-center py-1 border-r">{{ $areaCode }}</td>
                            <td colspan="4"
                                class="px-2 py-1 bg-indigo-50/50 dark:bg-gray-700 text-indigo-800 dark:text-indigo-300">
                                Kelompok {{ $groupChar }} ({{ count($items) }})
                            </td>
                        </tr>

                        {{-- Item Rute --}}
                        @foreach ($items as $route)
                            @php
                                $idx = $route['original_index'];
                                $count = $route['customer_count'] ?? 0;
                                $hasData = $count > 0;
                                $code = $route['code'] ?? '';
                            @endphp
                            <tr class="hover:bg-indigo-50 dark:hover:bg-gray-700 transition-colors route-row-item"
                                data-search="{{ strtolower($code . ' ' . ($route['label'] ?? '')) }}">

                                {{-- Area (Hidden Input Kode Asli) --}}
                                <td class="text-center border-r dark:border-gray-700 py-1">
                                    <span class="text-gray-300 font-bold text-[10px]">{{ $areaCode }}</span>
                                    <input type="hidden"
                                        name="settings[kddk_config_data][routes_manage][{{ $areaCode }}][{{ $idx }}][code]"
                                        value="{{ $code }}" class="route-code-real">
                                </td>

                                {{-- Kode Char 1 --}}
                                <td
                                    class="text-center font-bold text-gray-700 dark:text-gray-300 border-r dark:border-gray-700 py-1">
                                    {{ substr($code, 0, 1) }}
                                </td>

                                {{-- Kode Char 2 --}}
                                <td
                                    class="text-center font-bold text-gray-700 dark:text-gray-300 border-r dark:border-gray-700 py-1">
                                    {{ substr($code, 1, 1) }}
                                </td>

                                {{-- Label Input --}}
                                <td class="p-1 border-r dark:border-gray-700">
                                    <input type="text"
                                        name="settings[kddk_config_data][routes_manage][{{ $areaCode }}][{{ $idx }}][label]"
                                        value="{{ $route['label'] ?? '' }}"
                                        class="w-full text-sm border-0 bg-transparent focus:ring-0 p-1 text-gray-800 dark:text-gray-200"
                                        placeholder="Nama Rute">

                                    @if ($hasData)
                                        <div class="text-[9px] text-blue-600 mt-0.5 ml-1">
                                            <i class="fas fa-users"></i> {{ number_format($count) }} Pelanggan
                                        </div>
                                    @endif
                                </td>

                                {{-- Tombol Hapus --}}
                                <td class="text-center py-1">
                                    @if ($hasData)
                                        <i class="fas fa-lock text-gray-300 text-xs"
                                            title="Terkunci (Ada Pelanggan)"></i>
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
                                <i class="fas fa-route text-3xl mb-2 opacity-30"></i>
                                <p>Belum ada rute di area ini.</p>
                                <p class="text-xs">Gunakan form di atas untuk menambah.</p>
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
        </div>

        {{-- FOOTER (Save Button) --}}
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <span class="text-xs text-gray-500 italic">* Klik Simpan untuk menerapkan perubahan.</span>

            <button type="submit"
                class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow transition transform active:scale-95 flex items-center">
                <i class="fas fa-save mr-2"></i> Simpan Semua Rute
            </button>
        </div>
    </form>
</div>

{{-- SCRIPT SEARCH SEDERHANA --}}
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
