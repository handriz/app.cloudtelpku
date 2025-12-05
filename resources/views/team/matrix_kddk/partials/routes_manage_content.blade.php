<div class="p-6 bg-white dark:bg-gray-800 rounded-lg h-full flex flex-col">
    
    {{-- HEADER AREA --}}
    <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
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
        
        <button onclick="App.Tabs.loadTabContent('Pengaturan', '{{ route('admin.settings.index') }}')"
                class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Kembali
        </button>
    </div>

    {{-- TOOLBAR PENCARIAN & TAMBAH --}}
    <div class="flex space-x-3 mb-4">
        {{-- Input Pencarian Live --}}
        <div class="relative flex-1">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                <i class="fas fa-search"></i>
            </span>
            <input type="text" id="route-search-input" 
                   class="w-full py-2 pl-10 pr-4 text-sm border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                   placeholder="Cari Kode atau Keterangan Rute...">
        </div>

        {{-- Tombol Tambah (Di Atas) --}}
        <button type="button" id="add-route-manager-btn" data-area-code="{{ $areaCode }}" 
                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-bold rounded-lg shadow flex items-center transition">
            <i class="fas fa-plus mr-2"></i> Tambah Baru
        </button>
    </div>

    <form action="{{ route('admin.settings.update') }}" method="POST" class="ajax-form flex-1 flex flex-col min-h-0" data-success-redirect-tab="Pengaturan">
        @csrf
        <input type="hidden" name="area_code_target" value="{{ $areaCode }}"> 

        <div id="ajax-errors" class="hidden mb-4 p-3 bg-red-100 text-red-700 rounded text-sm"></div>

        {{-- TABEL SCROLLABLE (Sticky Header) --}}
        <div class="flex-1 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg relative custom-scrollbar">
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-400 sticky top-0 z-10 shadow-sm">
                    <tr>
                        <th class="px-4 py-3 w-32 bg-gray-100 dark:bg-gray-700">Kode</th>
                        <th class="px-4 py-3 bg-gray-100 dark:bg-gray-700">Keterangan</th>
                        <th class="px-4 py-3 w-16 text-center bg-gray-100 dark:bg-gray-700">Aksi</th>
                    </tr>
                </thead>
                <tbody id="route-rows-container" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($routes as $idx => $route)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors route-item">
                        <td class="p-3">
                            <input type="text" 
                                   name="settings[kddk_config_data][routes_manage][{{ $areaCode }}][{{ $idx }}][code]" 
                                   value="{{ $route['code'] ?? '' }}" 
                                   maxlength="2" 
                                   class="w-full text-center font-bold font-mono uppercase rounded-md border-gray-300 dark:bg-gray-700 dark:text-white focus:ring-indigo-500 sm:text-sm route-code-input" 
                                   required 
                                   oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '')">
                        </td>
                        <td class="p-3">
                            <input type="text" 
                                   name="settings[kddk_config_data][routes_manage][{{ $areaCode }}][{{ $idx }}][label]" 
                                   value="{{ $route['label'] ?? '' }}" 
                                   class="w-full rounded-md border-gray-300 dark:bg-gray-700 dark:text-white focus:ring-indigo-500 sm:text-sm route-label-input" 
                                   placeholder="Keterangan Rute" 
                                   required>
                        </td>
                        <td class="p-3 text-center">
                            <button type="button" onclick="this.closest('tr').remove(); updateRouteCount();" class="text-red-500 hover:text-red-700 p-2 rounded-full hover:bg-red-50 dark:hover:bg-red-900/30 transition remove-route-row">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            
            {{-- Empty State untuk Pencarian --}}
            <div id="no-routes-found" class="hidden p-8 text-center text-gray-500">
                <i class="fas fa-search text-3xl mb-2 text-gray-300"></i>
                <p>Tidak ditemukan rute yang cocok.</p>
            </div>
        </div>
        
        {{-- FOOTER --}}
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <div class="text-xs text-gray-500 italic">
                * Perubahan hanya disimpan setelah klik tombol Simpan.
            </div>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow-md transition transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <i class="fas fa-save mr-2"></i> Simpan Semua Rute
            </button>
        </div>
    </form>
</div>