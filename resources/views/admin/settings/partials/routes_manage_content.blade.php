<div class="p-6 bg-white dark:bg-gray-800 rounded-lg h-full flex flex-col">
    
    {{-- HEADER --}}
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
        
        <button onclick="App.Tabs.closeTab(App.Utils.getActiveTabName())"
                class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Kembali
        </button>
    </div>

    {{-- TOOLBAR --}}
    <div class="flex space-x-3 mb-4">
        <div class="relative flex-1">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500"><i class="fas fa-search"></i></span>
            <input type="text" id="route-search-input" 
                   class="w-full py-2 pl-10 pr-4 text-sm border-gray-300 rounded-lg focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                   placeholder="Cari Kode atau Keterangan Rute...">
        </div>

        {{-- Tombol Tambah (ID PENTING: add-route-manager-btn) --}}
        <button type="button" id="add-route-manager-btn" data-area-code="{{ $areaCode }}" 
                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-bold rounded-lg shadow flex items-center transition">
            <i class="fas fa-plus mr-2"></i> Tambah Baru
        </button>
    </div>

    <form action="{{ route('admin.settings.update') }}" method="POST" class="ajax-form flex-1 flex flex-col min-h-0" data-success-redirect-tab="Pengaturan">
        @csrf
        <input type="hidden" name="area_code_target" value="{{ $areaCode }}"> 

        {{-- WADAH NOTIFIKASI SUKSES (PENTING: Agar tampil cantik, bukan alert) --}}
        <div id="kddk-notification-container" class="mb-2"></div>

        {{-- WADAH ERROR (Untuk Validasi) --}}
        <div id="ajax-errors" class="hidden mb-4 p-3 bg-red-100 text-red-700 rounded text-sm border border-red-200"></div>

        {{-- TABEL RUTE (HEADER SPLIT - VISUAL ONLY) --}}
{{-- TABEL RUTE (GROUPED & OPTIMIZED) --}}
        <div class="flex-1 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg relative custom-scrollbar">
            <table class="w-full text-sm text-left table-fixed"> 
                <thead class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-400 sticky top-0 z-20 shadow-sm">
                    {{-- HEADER BARIS 1 --}}
                    <tr>
                        {{-- Area: Lebar Fixed 60px --}}
                        <th rowspan="2" class="text-center bg-gray-100 dark:bg-gray-700 border-b border-r dark:border-gray-600 p-1" style="width: 60px;">Area</th>
                        
                        {{-- Rute Induk: Lebar Fixed 80px (40+40) --}}
                        <th colspan="2" class="text-center bg-indigo-50 dark:bg-gray-800 border-b border-r dark:border-gray-600 text-indigo-700 dark:text-indigo-300 font-bold p-1" style="width: 80px;">
                            RUTE
                        </th>
                        
                        {{-- Keterangan: Auto Width --}}
                        <th rowspan="2" class="text-center bg-gray-100 dark:bg-gray-700 border-b border-r dark:border-gray-600 px-3 align-middle w-auto">Keterangan Rute Baca</th>
                        
                        {{-- Aksi: Lebar Fixed 50px --}}
                        <th rowspan="2" class="text-center bg-gray-100 dark:bg-gray-700 border-b border-r dark:border-gray-600 p-1" style="width: 50px;">Aksi</th>
                    </tr>
                    
                    {{-- HEADER BARIS 2 (Sub Header Sempit) --}}
                    <tr>
                        {{-- Kode: Lebar 40px --}}
                        <th class="text-center bg-gray-100 dark:bg-gray-700 border-b border-r dark:border-gray-600 p-1" style="width: 50px;">
                            Kode
                        </th>
                        {{-- Hari: Lebar 40px --}}
                        <th class="text-center bg-gray-100 dark:bg-gray-700 border-b border-r dark:border-gray-600 p-1" style="width: 50px;">
                            Hari
                        </th>
                    </tr>
                </thead>
                
                {{-- KONTAINER UNTUK BARIS BARU (Selalu di atas, tidak masuk grup) --}}
                <tbody id="route-new-rows" class="bg-green-50/30 dark:bg-green-900/10"></tbody>

                {{-- LOOP GROUPING (A, B, C...) --}}
                @forelse($groupedRoutes as $groupChar => $items)
                    
                    {{-- HEADER GROUP (BARIS INDUK) --}}
                    <tbody class="border-b border-gray-200 dark:border-gray-700">
                        <tr class="bg-gray-50 dark:bg-gray-800 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition group-header" 
                            data-target="group-{{ $groupChar }}">
                            
                            {{-- Kolom Area TETAP TAMPIL --}}
                            <td class="text-center border-r dark:border-gray-700 p-1 bg-white dark:bg-gray-900">
                                <span class="font-bold font-mono text-gray-400 text-xs select-none">{{ $areaCode }}</span>
                            </td>

                            {{-- Kolom Expand/Collapse (Mencakup Kode & Hari) --}}
                            <td colspan="2" class="p-1 text-center bg-indigo-50 dark:bg-gray-700 border-r dark:border-gray-600">
                                <div class="flex items-center justify-center font-bold text-indigo-700 dark:text-indigo-300">
                                    <i class="fas fa-chevron-right text-[10px] mr-1 transition-transform duration-200 icon-chevron"></i>
                                    {{ $groupChar }}
                                </div>
                            </td>

                            {{-- Kolom Keterangan (Info Group) --}}
                            <td colspan="2" class="p-2 text-xs text-gray-500 font-medium italic bg-gray-50 dark:bg-gray-800">
                                Kelompok Rute {{ $groupChar }}... ({{ count($items) }} Item)
                            </td>
                        </tr>
                    </tbody>

                    {{-- ISI GROUP (DATA RUTE) --}}
                    <tbody id="group-{{ $groupChar }}" class="hidden bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 route-group-body">
                        @foreach($items as $route)
                        @php
                            $idx = $route['original_index']; 
                            $count = $route['customer_count'] ?? 0;
                            $hasData = $count > 0;
                            $fullCode = $route['code'] ?? '';
                            $char1 = substr($fullCode, 0, 1);
                            $char2 = substr($fullCode, 1, 1);
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors route-item">
                            
                            {{-- AREA (Kosongkan visualnya agar bersih, atau tampilkan samar) --}}
                            <td class="text-center align-middle border-r dark:border-gray-700 p-1">
                                <span class="block w-full text-center font-bold font-mono uppercase text-gray-400 text-[10px] select-none">
                                    {{ $areaCode }}
                                </span>
                                
                                {{-- PERBAIKAN: Input Hidden WAJIB di dalam TD --}}
                                <input type="hidden" 
                                    name="settings[kddk_config_data][routes_manage][{{ $areaCode }}][{{ $idx }}][code]" 
                                    value="{{ $fullCode }}" 
                                    class="route-code-real route-code-input" 
                                    required>
                            </td>

                            {{-- INPUT DIGIT 1 (Kode) --}}
                            <td class="text-center border-r dark:border-gray-700 p-0 align-middle">
                                <input type="text" value="{{ $char1 }}" maxlength="1" placeholder=""
                                       class="w-full h-9 text-center font-bold font-mono uppercase text-sm border-0 bg-transparent focus:ring-0 focus:bg-indigo-50 dark:focus:bg-gray-600 dark:text-white p-0 split-input part-1 {{ $hasData ? 'text-gray-500 cursor-not-allowed' : '' }}"
                                       {{ $hasData ? 'readonly' : '' }}
                                       oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '')">
                            </td>

                            {{-- INPUT DIGIT 2 (Hari) --}}
                            <td class="text-center border-r dark:border-gray-700 p-0 align-middle">
                                <input type="text" value="{{ $char2 }}" maxlength="1" placeholder=""
                                       class="w-full h-9 text-center font-bold font-mono uppercase text-sm border-0 bg-transparent focus:ring-0 focus:bg-indigo-50 dark:focus:bg-gray-600 dark:text-white p-0 split-input part-2 {{ $hasData ? 'text-gray-500 cursor-not-allowed' : '' }}"
                                       {{ $hasData ? 'readonly' : '' }}
                                       oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '')">
                            </td>
                            
                            {{-- KETERANGAN (Lebar Full) --}}
                            <td class="align-middle px-2 py-1">
                                <div class="flex flex-col w-full">
                                    <input type="text" 
                                           name="settings[kddk_config_data][routes_manage][{{ $areaCode }}][{{ $idx }}][label]" 
                                           value="{{ $route['label'] ?? '' }}" 
                                           class="w-full h-8 text-sm border-gray-300 dark:border-gray-600 rounded focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white route-label-input px-2" 
                                           placeholder="Keterangan Rute" 
                                           required>
                                    
                                    @if($hasData)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-yellow-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                            <i class="fas fa-users mr-1"></i> {{ number_format($count) }} Pelanggan Terdaftar
                                        </span>
                                        <span class="ml-2 text-[10px] text-red-600 italic">
                                            (Masih terdapat pelanggan dalam Kode ini, Update/hapus ditangguhkan)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                            <i class="far fa-user mr-1"></i> Kosong (0 Pelanggan)
                                        </span>
                                    @endif
                                </div>
                            </td>
                            
                            {{-- AKSI --}}
                            <td class="text-center align-middle p-1">
                                @if($hasData)
                                    <i class="fas fa-lock text-gray-300 text-xs"></i>
                                @else
                                    <button type="button" class="text-red-500 hover:text-red-700 p-1.5 rounded-full hover:bg-red-50 transition remove-route-row"><i class="fas fa-trash-alt text-xs"></i></button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                @empty
                    <tbody id="no-data-msg">
                        <tr><td colspan="5" class="p-8 text-center text-gray-500">Belum ada rute. Klik Tambah Baru.</td></tr>
                    </tbody>
                @endforelse

                 {{-- Pesan Not Found Pencarian --}}
                <tbody id="no-routes-found" class="hidden">
                    <tr><td colspan="5" class="p-8 text-center text-gray-500">Tidak ditemukan rute yang cocok.</td></tr>
                </tbody>
            </table>
        </div>
        
        {{-- FOOTER --}}
        <div id="no-routes-found" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <div class="text-xs text-gray-500 italic">
                * Data Rute baru akan tersimpan setelah klik tombol Simpan.
            </div>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow-md transition transform hover:scale-105">
                <i class="fas fa-save mr-2"></i> Simpan Semua Rute
            </button>
        </div>
    </form>
</div>

<style>
    /* 1. Custom Scrollbar (Minimalis) */
    .custom-scrollbar::-webkit-scrollbar {
        width: 5px;
        height: 5px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #cbd5e1; /* gray-300 */
        border-radius: 20px;
        border: 1px solid transparent;
        background-clip: content-box;
    }
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

    /* 2. Animasi Baris Baru (Fade In & Slide Down) */
    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
            background-color: #e0e7ff; /* indigo-100 highlight awal */
        }
        to {
            opacity: 1;
            transform: translateY(0);
            background-color: transparent;
        }
    }
    
    /* Class ini dipanggil oleh JS saat tambah baris */
    .animate-fade-in-down {
        animation: fadeInDown 0.4s ease-out forwards;
    }

    /* 3. Sticky Header Fix (Agar border tetap terlihat) */
    thead.sticky th {
        box-shadow: 0 1px 0 #e5e7eb; /* gray-200 */
    }
    .dark thead.sticky th {
        box-shadow: 0 1px 0 #374151; /* gray-700 */
    }
</style>