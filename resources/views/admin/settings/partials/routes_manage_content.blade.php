<div class="p-6 bg-white dark:bg-gray-800 rounded-lg">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 flex items-center">
            <i class="fas fa-route mr-2 text-indigo-600"></i> Rute Area: <span class="text-indigo-600 ml-1">{{ $areaCode }}</span>
        </h3>
        
        {{-- Tombol Kembali --}}
        <button onclick="App.Tabs.activateTab('Pengaturan', '{{ route('admin.settings.index') }}')"
                class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition">
            <i class="fas fa-arrow-left mr-2"></i> Kembali
        </button>
    </div>

    {{-- Form Khusus Rute --}}
    <form action="{{ route('admin.settings.update') }}" method="POST" class="ajax-form" data-success-redirect-tab="Pengaturan">
        @csrf
        {{-- Marker Area Target --}}
        <input type="hidden" name="area_code_target" value="{{ $areaCode }}"> 

        <div id="ajax-errors" class="hidden mb-4 p-3 bg-red-100 text-red-700 rounded text-sm"></div>

        <div class="overflow-x-auto border rounded-lg max-h-[60vh] overflow-y-auto">
            <table class="w-full text-sm text-left border border-gray-300">
                <thead class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700">
                    <tr>
                        <th class="px-3 py-2 w-32">Kode Rute</th>
                        <th class="px-3 py-2">Keterangan</th>
                        <th class="px-3 py-2 w-10"></th>
                    </tr>
                </thead>
                <tbody id="route-rows-container">
                    @foreach($routes as $idx => $route)
                    <tr>
                        <td class="p-2">
                            <input type="text" name="settings[kddk_config_data][routes_manage][{{ $areaCode }}][{{ $idx }}][code]" value="{{ $route['code'] ?? '' }}" maxlength="2" class="w-full text-center font-bold uppercase rounded border-gray-300 dark:bg-gray-700 dark:text-white" required oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '')">
                        </td>
                        <td class="p-2">
                            <input type="text" name="settings[kddk_config_data][routes_manage][{{ $areaCode }}][{{ $idx }}][label]" value="{{ $route['label'] ?? '' }}" class="w-full rounded border-gray-300 dark:bg-gray-700 dark:text-white" placeholder="Keterangan Rute" required>
                        </td>
                        <td class="p-2 text-center">
                            <button type="button" class="text-red-500 hover:text-red-700 remove-row-btn"><i class="fas fa-times"></i></button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <button type="button" id="add-route-manager-btn" data-area-code="{{ $areaCode }}" class="mt-3 text-sm flex items-center text-green-600 hover:text-green-800 font-bold px-2 py-1 rounded border border-green-200">
            <i class="fas fa-plus-circle mr-1"></i> Tambah Rute Baru
        </button>
        
        <div class="mt-6 flex justify-end">
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded shadow">Simpan Rute</button>
        </div>
    </form>
</div>