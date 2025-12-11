{{-- 
    UX UPGRADE: DASHBOARD MAPPING PROFESIONAL 
    Konsep: Split View (Map Left, Inspector Right) + Interactive Table
--}}

<div id="kddk-notification-container" class="px-6"></div>

<div class="space-y-4 h-full flex flex-col">

   {{-- 1. TOP BAR: STATS & ACTIONS (Compact) --}}
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        
        {{-- Stats (Kiri) --}}
        <div class="md:col-span-8 flex items-center space-x-6 overflow-x-auto">
            {{-- Progress Ring Mini --}}
            <div class="flex items-center space-x-3">
                <div class="relative w-12 h-12 flex items-center justify-center rounded-full border-4 border-gray-100 dark:border-gray-700">
                    <span class="text-[10px] font-bold text-blue-600 dark:text-blue-400">{{ number_format($mappingPercentage, 0) }}%</span>
                    <svg class="absolute inset-0 w-full h-full -rotate-90" viewBox="0 0 36 36">
                        <path class="text-blue-500" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="{{ $mappingPercentage }}, 100" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase font-bold">Terpetakan</p>
                    <p class="text-lg font-bold text-gray-800 dark:text-white">{{ number_format($totalMappingEnabled) }} <span class="text-xs text-gray-400">/ {{ number_format($totalPelanggan) }}</span></p>
                </div>
            </div>

            <div class="h-8 w-px bg-gray-200 dark:bg-gray-700"></div>

            {{-- Search Bar (Lebar) --}}
            <div class="flex-1 max-w-md">
                <form id="mapping-search-form" action="{{ route('team.mapping.index') }}" method="GET" class="relative group">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 group-focus-within:text-indigo-500 transition"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" value="{{ $search ?? '' }}" 
                           class="w-full pl-10 pr-10 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 transition-all" 
                           placeholder="Cari IDPEL, Nama, atau No Meter...">
                    @if(request('search'))
                        <a href="{{ route('team.mapping.index') }}" class="absolute inset-y-0 right-0 flex items-center pr-3 text-red-400 hover:text-red-600 cursor-pointer">
                            <i class="fas fa-times-circle"></i>
                        </a>
                    @endif
                </form>
            </div>
        </div>

        {{-- Actions (Kanan) --}}
        <div class="md:col-span-4 flex justify-end space-x-2">
            <a href="" class="btn-secondary-sm" data-modal-link="true">
                <i class="fas fa-file-import mr-2"></i> Upload
            </a>
            <a href="{{ route('team.mapping.create') }}" class="btn-primary-sm shadow-lg shadow-indigo-500/30" data-modal-link="true">
                <i class="fas fa-plus mr-2"></i> Baru
            </a>
        </div>
    </div>

    {{-- 2. WORKSPACE: MAP & INSPECTOR (Split View) --}}
    {{-- FIXED: Tambahkan 'overflow-hidden' di sini untuk mencegah anak elemen keluar jalur --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 h-[500px] overflow-hidden"> 
        
        {{-- KOLOM KIRI: PETA --}}
        {{-- FIXED: Tambahkan 'z-0' agar tidak menutupi elemen lain jika ada glitch --}}
        <div class="lg:col-span-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden relative group z-0">
            <div id="rbm-map" class="w-full h-full z-0"></div>
            
            {{-- Overlay Info --}}
            <div class="absolute bottom-4 left-4 bg-white/90 dark:bg-gray-900/90 backdrop-blur px-3 py-2 rounded-lg shadow-lg border border-gray-200 dark:border-gray-600 text-xs z-[400] flex items-center space-x-3">
                <div class="flex flex-col">
                    <span class="text-gray-500 uppercase text-[10px] font-bold">Koordinat Terpilih</span>
                    <span id="detail-lat-lon" class="font-mono font-bold text-indigo-600 dark:text-indigo-400">
                        {{ (isset($searchedMapping) && $searchedMapping->latitudey) ? number_format($searchedMapping->latitudey, 6) . ', ' . number_format($searchedMapping->longitudex, 6) : '-' }}
                    </span>
                </div>
                <div class="h-6 w-px bg-gray-300"></div>
                <button type="button" id="google-street-view-link" class="text-gray-500 hover:text-orange-500 transition {{ (isset($searchedMapping) && $searchedMapping->latitudey) ? '' : 'hidden pointer-events-none opacity-50' }}" title="Buka Street View">
                    <i class="fas fa-street-view text-2xl"></i>
                </button>
            </div>
        </div>

        {{-- KOLOM KANAN: INSPECTOR PANEL --}}
        {{-- FIXED: 'min-h-0' sangat penting agar flex container bisa menyusut --}}
        <div class="lg:col-span-4 flex flex-col gap-4 h-full min-h-0">
            
            {{-- Tab Switcher & Foto Wrapper --}}
            {{-- FIXED: 'min-h-0' ditambahkan di sini juga --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 flex-1 flex flex-col overflow-hidden p-1 min-h-0">
                
                {{-- Tombol Tab --}}
                <div class="flex p-1 bg-gray-100 dark:bg-gray-700 rounded-lg mb-2 shrink-0">
                    <button onclick="switchInspectorTab('kwh')" id="tab-btn-kwh" class="flex-1 py-1.5 text-xs font-bold rounded-md shadow-sm bg-white dark:bg-gray-600 text-indigo-600 dark:text-white transition-all">
                        <i class="fas fa-bolt mr-1"></i> KWH Meter
                    </button>
                    <button onclick="switchInspectorTab('bangunan')" id="tab-btn-bangunan" class="flex-1 py-1.5 text-xs font-bold rounded-md text-gray-500 hover:text-gray-700 dark:text-gray-400 transition-all">
                        <i class="fas fa-home mr-1"></i> Bangunan
                    </button>
                </div>

                {{-- Area Foto KWH --}}
                {{-- FIXED: Pastikan img memiliki 'object-contain' dan parent 'overflow-hidden' --}}
                <div id="inspector-kwh" class="relative flex-1 bg-gray-50 dark:bg-gray-900 rounded-lg overflow-hidden group border border-gray-100 dark:border-gray-600 min-h-0">
                    <img id="detail-foto-kwh" 
                         src="{{ (isset($searchedMapping) && $searchedMapping->foto_kwh) ? Storage::disk('public')->url($searchedMapping->foto_kwh) : '' }}" 
                         class="w-full h-full object-contain transition-transform duration-500 group-hover:scale-105 {{ (isset($searchedMapping) && $searchedMapping->foto_kwh) ? '' : 'hidden' }}">
                    
                    <div id="placeholder-foto-kwh" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 {{ (isset($searchedMapping) && $searchedMapping->foto_kwh) ? 'hidden' : 'flex' }}">
                        <i class="fas fa-camera text-4xl mb-2 opacity-20"></i>
                        <span class="text-xs font-medium opacity-50">Pilih data untuk melihat foto</span>
                    </div>

                    <button type="button" onclick="viewImage('kwh')" id="zoom-kwh" class="absolute bottom-2 right-2 bg-black/50 hover:bg-black/70 text-white p-2 rounded-full backdrop-blur-sm opacity-0 group-hover:opacity-100 transition {{ (isset($searchedMapping) && $searchedMapping->foto_kwh) ? '' : 'hidden' }}">
                        <i class="fas fa-expand-alt"></i>
                    </button>
                </div>

                {{-- Area Foto Bangunan --}}
                <div id="inspector-bangunan" class="hidden relative flex-1 bg-gray-50 dark:bg-gray-900 rounded-lg overflow-hidden group border border-gray-100 dark:border-gray-600 min-h-0">
                    <img id="detail-foto-bangunan" 
                         src="{{ (isset($searchedMapping) && $searchedMapping->foto_bangunan) ? Storage::disk('public')->url($searchedMapping->foto_bangunan) : '' }}" 
                         class="w-full h-full object-contain transition-transform duration-500 group-hover:scale-105 {{ (isset($searchedMapping) && $searchedMapping->foto_bangunan) ? '' : 'hidden' }}">
                    
                    <div id="placeholder-foto-bangunan" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 {{ (isset($searchedMapping) && $searchedMapping->foto_bangunan) ? 'hidden' : 'flex' }}">
                        <i class="fas fa-building text-4xl mb-2 opacity-20"></i>
                        <span class="text-xs font-medium opacity-50">Pilih data untuk melihat foto</span>
                    </div>

                    <button type="button" onclick="viewImage('bangunan')" id="zoom-bangunan" class="absolute bottom-2 right-2 bg-black/50 hover:bg-black/70 text-white p-2 rounded-full backdrop-blur-sm opacity-0 group-hover:opacity-100 transition {{ (isset($searchedMapping) && $searchedMapping->foto_bangunan) ? '' : 'hidden' }}">
                        <i class="fas fa-expand-alt"></i>
                    </button>
                </div>
            </div>

            {{-- Detail Mini Card --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 shrink-0">
                <h4 class="text-xs font-bold text-gray-400 uppercase mb-3 tracking-wider">Informasi Singkat</h4>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between border-b border-gray-100 dark:border-gray-700 pb-2">
                        <span class="text-gray-500">ID Pelanggan</span>
                        <span id="detail-idpel" class="font-mono font-bold text-gray-800 dark:text-white">
                            {{ $searchedMapping->idpel ?? '-' }}
                        </span>
                    </div>
                    <div class="flex justify-between border-b border-gray-100 dark:border-gray-700 pb-2">
                        <span class="text-gray-500">Surveyor</span>
                        <span id="detail-user" class="font-semibold text-gray-800 dark:text-white">
                            {{ $searchedMapping->user_pendataan ?? '-' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">Status</span>
                        <span id="detail-status-badge">
                            @if(isset($searchedMapping))
                                @include('team.mapping-kddk.partials.status_badge', ['status' => $searchedMapping->ket_validasi, 'enabled' => $searchedMapping->enabled])
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. BOTTOM: DATA TABLE (Full Width) --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden flex-1 flex flex-col">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex justify-between items-center">
            <h3 class="font-bold text-gray-700 dark:text-gray-200">Data Pelanggan</h3>
            <span class="text-xs text-gray-500">Klik baris untuk melihat detail di atas</span>
        </div>
        
        <div class="overflow-x-auto flex-1 custom-scrollbar">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">No</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">ID Pelanggan</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Petugas</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Tanggal</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($mappings as $index => $map)
                        <tr class="group hover:bg-indigo-50 dark:hover:bg-indigo-900/20 cursor-pointer transition-colors"
                            onclick="selectMappingRow(this, {{ json_encode($map) }})"
                            data-lat="{{ $map->latitudey ?? 0 }}"
                            data-lon="{{ $map->longitudex ?? 0 }}">
                            
                            <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-500">{{ $mappings->firstItem() + $index }}</td>
                            
                            <td class="px-6 py-3 whitespace-nowrap">
                                <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $map->idpel }}</div>
                                <div class="text-xs text-gray-400 font-mono">{{ $map->objectid }}</div>
                            </td>
                            
                            <td class="px-6 py-3 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="h-6 w-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-bold mr-2">
                                        {{ substr($map->user_pendataan, 0, 1) }}
                                    </div>
                                    <span class="text-xs text-gray-700 dark:text-gray-300">{{ Str::limit($map->user_pendataan, 15) }}</span>
                                </div>
                            </td>
                            
                            <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-500">
                                {{-- Cek apakah created_at ada isinya? --}}
                                @if($map->created_at)
                                    {{ $map->created_at->format('d M Y') }}
                                    <span class="text-[10px] text-gray-400 block">
                                        {{ $map->created_at->format('H:i') }}
                                    </span>
                                @else
                                    {{-- Jika null, tampilkan tanda strip --}}
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>

                            <td class="px-6 py-3 whitespace-nowrap text-center">
                                @include('team.mapping-kddk.partials.status_badge', ['status' => $map->ket_validasi, 'enabled' => $map->enabled])
                            </td>

                            <td class="px-6 py-3 whitespace-nowrap text-right text-sm font-medium" onclick="event.stopPropagation()">
                                <div class="flex items-center justify-end space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    {{-- Tombol Aksi (Edit, Invalidate, dll) --}}
                                    <a href="{{ route('team.mapping.edit', $map) }}" class="text-gray-400 hover:text-indigo-600 transition" title="Edit">
                                        <i class="fas fa-pencil-alt"></i>
                                    </a>
                                    
                                    @if($map->enabled)
                                        <form action="{{ route('team.mapping-kddk.invalidate', $map->id) }}" method="POST" class="inline" data-custom-handler="invalidate-action">
                                            @csrf
                                            <button type="submit" class="text-gray-400 hover:text-yellow-500 transition" title="Tarik Kembali">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </form>
                                    @endif
                                    
                                    @if(!$map->enabled && $map->ket_validasi == 'verified')
                                         <form action="{{ route('team.mapping-kddk.promote', $map->id) }}" method="POST" class="inline" data-custom-handler="promote-action">
                                            @csrf
                                            <button type="submit" class="text-gray-400 hover:text-green-500 transition" title="Set Aktif">
                                                <i class="fas fa-check-circle"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-search text-4xl mb-3 text-gray-300"></i>
                                    <p>Tidak ada data ditemukan.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            {{ $mappings->links() }}
        </div>
    </div>

</div>

{{-- Helper Styles --}}
<style>
    .btn-primary-sm { @apply inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-bold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition active:scale-95; }
    .btn-secondary-sm { @apply inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg font-bold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition; }
</style>

    <div id="custom-confirm-modal" class="fixed inset-0 bg-gray-600 bg-opacity-75 hidden items-center justify-center p-4 z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md">
            
            {{-- Header Modal --}}
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 id="custom-confirm-title" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Konfirmasi Tindakan</h3>
            </div>

            {{-- Body Pesan --}}
            <div class="p-6">
                <p id="custom-confirm-message" class="text-sm text-gray-700 dark:text-gray-300">
                    Apakah Anda yakin ingin melanjutkan tindakan ini?
                </p>
            </div>

            {{-- Footer (Tombol) --}}
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 flex justify-end space-x-3 rounded-b-lg">
                <button id="custom-confirm-cancel" type="button" class="px-4 py-2 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500">
                    Batal
                </button>
                <button id="custom-confirm-ok" type="button" class="px-4 py-2 bg-red-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-red-700">
                    Ya, Lanjutkan
                </button>
            </div>
        </div>
    </div>

    {{-- ====================================================== --}}
    {{-- MODAL UNTUK GOOGLE STREET VIEW --}}
    {{-- ====================================================== --}}
    <div id="street-view-modal" class="fixed top-10 right-10 hidden z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl w-[50vw] h-[75vh] flex flex-col relative border dark:border-gray-700">
            
            {{-- Tombol Close (dibuat lebih besar dan mudah di-klik) --}}
            <button id="street-view-close-button" class="absolute -top-3 -right-3 bg-red-500 hover:bg-red-700 text-white rounded-full p-2 z-10 w-8 h-8 flex items-center justify-center">
                <i class="fas fa-times"></i>
            </button>

            {{-- Header Modal --}}
            <div id="street-view-header" class="px-6 py-3 border-b border-gray-200 dark:border-gray-700 cursor-move">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Google Street View</h3>
            </div>

            {{-- Konten Iframe --}}
            <div class="flex-grow p-1"> {{-- p-1 agar ada sedikit padding --}}
                <iframe id="street-view-iframe" 
                        src="" 
                        frameborder="0" 
                        allowfullscreen="" 
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        class="w-full h-full rounded-md"></iframe>
            </div>
        </div>
    </div>

    {{-- MODAL IMAGE VIEWER (INTERACTIVE) --}}
    <div id="image-viewer-modal" class="fixed inset-0 z-[9999] hidden bg-black/95 backdrop-blur-sm flex flex-col items-center justify-center transition-opacity duration-300 opacity-0 pointer-events-none">
        
        {{-- Tombol Close --}}
        <button type="button" onclick="closeImageViewer()" class="absolute top-4 right-4 z-50 text-white/70 hover:text-white transition transform hover:scale-110 bg-black/50 rounded-full w-10 h-10 flex items-center justify-center">
            <i class="fas fa-times text-xl"></i>
        </button>

        {{-- Container Gambar (Area Drag & Zoom) --}}
        {{-- PENTING: overflow-hidden agar gambar tidak keluar layar --}}
        <div id="image-container" class="relative w-full h-full flex items-center justify-center overflow-hidden cursor-grab active:cursor-grabbing">
            
            {{-- Gambar Target --}}
            {{-- PENTING: transition-transform diatur via JS nanti agar drag tidak delay --}}
            <img id="image-viewer-img" src="" 
             class="max-w-full max-h-full object-contain origin-center select-none"
             draggable="false" 
             oncontextmenu="return false;"
             style="transform: translate(0px, 0px) scale(1) rotate(0deg); touch-action: none; user-select: none; -webkit-user-drag: none;">
        </div>

        {{-- Toolbar Kontrol (Floating di Bawah) --}}
        <div class="absolute bottom-6 left-1/2 transform -translate-x-1/2 bg-gray-900/80 backdrop-blur-md border border-gray-700 rounded-full px-4 py-2 flex items-center space-x-4 shadow-2xl pointer-events-auto z-50">
            <button onclick="adjustImage('zoom', -0.2)" class="text-white hover:text-indigo-400"><i class="fas fa-search-minus"></i></button>
            <button onclick="resetImageState()" class="text-xs font-bold text-gray-400 hover:text-white border border-gray-600 px-2 py-0.5 rounded">RESET</button>
            <button onclick="adjustImage('zoom', 0.2)" class="text-white hover:text-indigo-400"><i class="fas fa-search-plus"></i></button>
            <div class="w-px h-4 bg-gray-600"></div>
            <button onclick="adjustImage('rotate', -90)" class="text-white hover:text-orange-400"><i class="fas fa-undo"></i></button>
            <button onclick="adjustImage('rotate', 90)" class="text-white hover:text-orange-400"><i class="fas fa-redo"></i></button>
        </div>
    </div>
    
</div>