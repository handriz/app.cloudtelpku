{{-- 
    UX UPGRADE: DASHBOARD MAPPING PROFESIONAL 
    Konsep: Split View (Map Left, Inspector Right) + Interactive Table
--}}

<div id="kddk-notification-container" class="px-6"></div>
<input type="hidden" id="api-map-coordinates" value="{{ route('team.mapping-kddk.coordinates') }}">

<div class="space-y-4 h-full flex flex-col">

    {{-- 1. TOP BAR: STATS & ACTIONS (Compact) --}}
    <div
        class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">

        {{-- Stats (Kiri) --}}
        <div class="md:col-span-8 flex items-center space-x-6 overflow-x-auto">
            {{-- Progress Ring Mini --}}
            <div class="flex items-center space-x-3">
                <div class="relative w-14 h-14 flex items-center justify-center rounded-full border-4 border-gray-100 dark:border-gray-700">
                    
                    {{-- TEKS PERSENTASE --}}
                    <div class="flex items-baseline text-blue-600 dark:text-blue-400 font-bold leading-none">
                        {{-- Angka Utama: Ukuran text-xs (12px) --}}
                        <span class="text-[11px] tracking-tighter">
                            {{ $mappingPercentage }}
                        </span>
                        {{-- Simbol Persen: Lebih kecil (Superscript style) --}}
                        <span class="text-[8px] ml-[1px]">%</span>
                    </div>

                    {{-- SVG PROGRESS --}}
                    <svg class="absolute inset-0 w-full h-full -rotate-90" viewBox="0 0 36 36">
                        <path class="text-blue-500"
                            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                            fill="none" stroke="currentColor" stroke-width="3"
                            stroke-dasharray="{{ $mappingPercentage }}, 100" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase font-bold">Terpetakan</p>
                    <p class="text-lg font-bold text-gray-800 dark:text-white">{{ number_format($totalMappingEnabled) }}
                        <span class="text-xs text-gray-400">/ {{ number_format($totalPelanggan) }}</span>
                    </p>
                </div>
            </div>

            <div class="h-8 w-px bg-gray-200 dark:bg-gray-700"></div>

            {{-- Search Bar (Lebar) --}}
            <div class="flex-1 max-w-sm">
                <form id="mapping-search-form" action="{{ route('team.mapping.index') }}" method="GET"
                    class="relative group">
                    <span
                        class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 group-focus-within:text-indigo-500 transition"><i
                            class="fas fa-search"></i></span>
                    <input type="text" name="search" value="{{ $search ?? '' }}"
                        class="w-full pl-10 pr-10 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 transition-all"
                        placeholder="Cari IDPEL, Nama, atau No Meter...">
                    @if (request('search'))
                        <a href="{{ route('team.mapping.index') }}"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-red-400 hover:text-red-600 cursor-pointer">
                            <i class="fas fa-times-circle"></i>
                        </a>
                    @endif
                </form>
            </div>
        </div>

        {{-- Actions (Kanan) --}}
        <div class="md:col-span-4 flex justify-end items-center gap-2">

            {{-- TOMBOL 1: REQUEST KOORDINAT (Style disamakan dimensinya) --}}
            <button onclick="window.openRequestModal()"
                class="inline-flex items-center justify-center h-10 px-4 py-2 bg-white dark:bg-gray-700 border border-indigo-200 dark:border-gray-600 rounded-lg font-bold text-xs text-indigo-700 dark:text-gray-200 uppercase tracking-widest shadow-sm hover:bg-indigo-50 dark:hover:bg-gray-600 transition">
                <i class="fas fa-map-marked-alt mr-2"></i> Request Koordinat
            </button>

            {{-- TOMBOL 2: BARU (Height dikunci h-10 agar sama persis) --}}
            <a href="{{ route('team.mapping.create') }}"
                class="inline-flex items-center justify-center h-10 px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-bold text-xs text-white uppercase tracking-widest shadow-lg shadow-indigo-500/30 hover:bg-indigo-700 transition active:scale-95"
                data-modal-link="true">
                <i class="fas fa-plus mr-2"></i> Baru
            </a>

        </div>
    </div>

    {{-- 2. WORKSPACE: MAP & INSPECTOR (Split View) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 h-[500px] overflow-hidden">

        {{-- KOLOM KIRI: PETA (Dominan) --}}
        <div
            class="lg:col-span-8 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden relative group z-0">

            {{-- 1. KONTAINER PETA --}}
            <div id="rbm-map" class="w-full h-full z-0"></div>

            {{-- 2. OVERLAY ERROR (Muncul jika Koordinat Invalid) --}}
            <div id="map-error-overlay"
                class="hidden absolute inset-0 z-[1000] bg-gray-50/90 dark:bg-gray-800/95 backdrop-blur-sm flex flex-col items-center justify-center text-center p-6 animate-fade-in">
                <div
                    class="w-16 h-16 bg-red-100 text-red-500 rounded-full flex items-center justify-center mb-3 shadow-sm">
                    <i class="fas fa-map-marker-slash text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-700 dark:text-gray-200">Lokasi Tidak Tersedia</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 max-w-xs mt-1">
                    Data pelanggan ini belum memiliki titik koordinat (Latitude/Longitude kosong atau 0).
                </p>
                <button type="button" id="btn-input-manual"
                    class="mt-4 px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-lg hover:bg-indigo-700 shadow-lg transition transform active:scale-95 flex items-center">
                    <i class="fas fa-edit mr-2"></i> Input Manual Sekarang
                </button>
            </div>

            {{-- 3. OVERLAY INFO KOORDINAT (Floating) --}}
            <div
                class="absolute bottom-4 left-4 bg-white/90 dark:bg-gray-900/90 backdrop-blur px-3 py-2 rounded-lg shadow-lg border border-gray-200 dark:border-gray-600 text-xs z-[400] flex items-center space-x-3">
                <div class="flex flex-col">
                    <span class="text-gray-500 uppercase text-[10px] font-bold">Koordinat Terpilih</span>
                    <span id="detail-lat-lon" class="font-mono font-bold text-indigo-600 dark:text-indigo-400">
                        -
                    </span>
                </div>
                <div class="h-6 w-px bg-gray-300"></div>
                <button type="button" id="google-street-view-link"
                    class="text-gray-500 hover:text-orange-500 transition hidden pointer-events-none opacity-50"
                    title="Buka Street View">
                    <i class="fas fa-street-view text-2xl"></i>
                </button>
            </div>
        </div>

        {{-- KOLOM KANAN: INSPECTOR PANEL --}}
        <div class="lg:col-span-4 flex flex-col gap-4 h-full min-h-0">

            {{-- Tab Switcher & Foto Wrapper --}}
            <div
                class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 flex-1 flex flex-col overflow-hidden p-1 min-h-0">

                {{-- Tombol Tab --}}
                <div class="flex p-1 bg-gray-100 dark:bg-gray-700 rounded-lg mb-2 shrink-0">
                    <button onclick="switchInspectorTab('kwh')" id="tab-btn-kwh"
                        class="flex-1 py-1.5 text-xs font-bold rounded-md shadow-sm bg-white dark:bg-gray-600 text-indigo-600 dark:text-white transition-all">
                        <i class="fas fa-bolt mr-1"></i> KWH Meter
                    </button>
                    <button onclick="switchInspectorTab('bangunan')" id="tab-btn-bangunan"
                        class="flex-1 py-1.5 text-xs font-bold rounded-md text-gray-500 hover:text-gray-700 dark:text-gray-400 transition-all">
                        <i class="fas fa-home mr-1"></i> Bangunan
                    </button>
                </div>

                {{-- Area Foto KWH --}}
                <div id="inspector-kwh"
                    class="relative flex-1 bg-gray-50 dark:bg-gray-900 rounded-lg overflow-hidden group border border-gray-100 dark:border-gray-600 min-h-0">
                    <img id="detail-foto-kwh"
                        src="{{ isset($searchedMapping) && $searchedMapping->foto_kwh ? Storage::disk('public')->url($searchedMapping->foto_kwh) : '' }}"
                        class="w-full h-full object-contain transition-transform duration-500 group-hover:scale-105 {{ isset($searchedMapping) && $searchedMapping->foto_kwh ? '' : 'hidden' }}">

                    <div id="placeholder-foto-kwh"
                        class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 {{ isset($searchedMapping) && $searchedMapping->foto_kwh ? 'hidden' : 'flex' }}">
                        <i class="fas fa-camera text-4xl mb-2 opacity-20"></i>
                        <span class="text-xs font-medium opacity-50">Pilih data untuk melihat foto</span>
                    </div>

                    <button type="button" onclick="viewImage('kwh')" id="zoom-kwh"
                        class="absolute bottom-2 right-2 bg-black/50 hover:bg-black/70 text-white p-2 rounded-full backdrop-blur-sm opacity-0 group-hover:opacity-100 transition {{ isset($searchedMapping) && $searchedMapping->foto_kwh ? '' : 'hidden' }}">
                        <i class="fas fa-expand-alt"></i>
                    </button>
                </div>

                {{-- Area Foto Bangunan --}}
                <div id="inspector-bangunan"
                    class="hidden relative flex-1 bg-gray-50 dark:bg-gray-900 rounded-lg overflow-hidden group border border-gray-100 dark:border-gray-600 min-h-0">
                    <img id="detail-foto-bangunan"
                        src="{{ isset($searchedMapping) && $searchedMapping->foto_bangunan ? Storage::disk('public')->url($searchedMapping->foto_bangunan) : '' }}"
                        class="w-full h-full object-contain transition-transform duration-500 group-hover:scale-105 {{ isset($searchedMapping) && $searchedMapping->foto_bangunan ? '' : 'hidden' }}">

                    <div id="placeholder-foto-bangunan"
                        class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 {{ isset($searchedMapping) && $searchedMapping->foto_bangunan ? 'hidden' : 'flex' }}">
                        <i class="fas fa-building text-4xl mb-2 opacity-20"></i>
                        <span class="text-xs font-medium opacity-50">Pilih data untuk melihat foto</span>
                    </div>

                    <button type="button" onclick="viewImage('bangunan')" id="zoom-bangunan"
                        class="absolute bottom-2 right-2 bg-black/50 hover:bg-black/70 text-white p-2 rounded-full backdrop-blur-sm opacity-0 group-hover:opacity-100 transition {{ isset($searchedMapping) && $searchedMapping->foto_bangunan ? '' : 'hidden' }}">
                        <i class="fas fa-expand-alt"></i>
                    </button>
                </div>
            </div>

            {{-- Detail Mini Card --}}
            <div
                class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 shrink-0">
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
                            @if (isset($searchedMapping))
                                @include('team.mapping-kddk.partials.status_badge', [
                                    'status' => $searchedMapping->ket_validasi,
                                    'enabled' => $searchedMapping->enabled,
                                ])
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
    <div
        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden flex-1 flex flex-col">
        <div
            class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex justify-between items-center">
            <h3 class="font-bold text-gray-700 dark:text-gray-200">Data Pelanggan</h3>
            <span class="text-xs text-gray-500">Klik baris untuk melihat detail di atas</span>
        </div>

        <div class="overflow-x-auto flex-1 custom-scrollbar">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">No
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">ID
                            Pelanggan</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                            Petugas</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                            Tanggal</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">
                            Status</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($mappings as $index => $map)
                        <tr class="group hover:bg-indigo-50 dark:hover:bg-indigo-900/20 cursor-pointer transition-colors"
                            onclick="selectMappingRow(this, {{ json_encode($map) }})"
                            data-lat="{{ $map->latitudey ?? 0 }}" data-lon="{{ $map->longitudex ?? 0 }}"
                            data-edit-url="{{ route('team.mapping.edit', $map->id) }}"
                            data-verified="{{ $map->enabled || $map->ket_validasi == 'verified' ? '1' : '0' }}">

                            <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-500">
                                {{ $mappings->firstItem() + $index }}</td>

                            <td class="px-6 py-3 whitespace-nowrap">
                                <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $map->idpel }}</div>
                                <div class="text-xs text-gray-400 font-mono">{{ $map->objectid }}</div>
                            </td>

                            <td class="px-6 py-3 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div
                                        class="h-6 w-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-bold mr-2">
                                        {{ substr($map->user_pendataan, 0, 1) }}
                                    </div>
                                    <span
                                        class="text-xs text-gray-700 dark:text-gray-300">{{ Str::limit($map->user_pendataan, 15) }}</span>
                                </div>
                            </td>

                            <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-500">
                                @if ($map->created_at)
                                    {{ $map->created_at->format('d M Y') }}
                                    <span class="text-[10px] text-gray-400 block">
                                        {{ $map->created_at->format('H:i') }}
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>

                            <td class="px-6 py-3 whitespace-nowrap text-center">
                                @include('team.mapping-kddk.partials.status_badge', [
                                    'status' => $map->ket_validasi,
                                    'enabled' => $map->enabled,
                                ])
                            </td>

                            <td class="px-6 py-3 whitespace-nowrap text-right text-sm font-medium"
                                onclick="event.stopPropagation()">
                                <div
                                    class="flex items-center justify-end space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">

                                    @php
                                        // Cek apakah IDPEL ini punya "Raja" (Data Aktif) di database?
                                        $hasActiveMaster = in_array($map->idpel, $activeIdpels);
                                    @endphp

                                    {{-- SKENARIO A: DATA INI ADALAH RAJA (YANG SEDANG AKTIF) --}}
                                    @if ($map->enabled || $map->ket_validasi == 'verified')
                                        {{-- Tombol Revisi (Wajib ada untuk menurunkan tahta) --}}
                                        <form action="{{ route('team.mapping-kddk.invalidate', $map->id) }}"
                                            method="POST" class="inline" data-custom-handler="invalidate-action">
                                            @csrf
                                            <button type="submit"
                                                onclick="event.stopPropagation(); return confirm('Tarik kembali status Validasi? Peta akan kosong sampai Anda memilih pengganti.');"
                                                class="text-yellow-500 hover:text-yellow-600 transition flex items-center bg-yellow-50 px-2 py-1 rounded border border-yellow-200"
                                                title="Tarik Kembali">
                                                <i class="fas fa-undo mr-1"></i> Revisi
                                            </button>
                                        </form>

                                        {{-- KONDISI 2: Data MATI / NON-AKTIF (Unverified / Recalled / Superseded) --}}
                                    @else
                                        {{-- Tombol Edit (Tetap muncul agar bisa perbaiki data draft) --}}
                                        @if ($map->ket_validasi !== 'superseded')
                                            <button type="button"
                                                onclick="event.stopPropagation(); openEditModal('{{ route('team.mapping.edit', $map->id) }}')"
                                                class="text-gray-400 hover:text-indigo-600 transition"
                                                title="Edit Data">
                                                <i class="fas fa-pencil-alt fa-lg"></i>
                                            </button>
                                        @endif

                                        @if (!$hasActiveMaster)
                                            <form action="{{ route('team.mapping-kddk.promote', $map->id) }}"
                                                method="POST" class="inline">
                                                @csrf
                                                <button type="submit"
                                                    onclick="return confirm('Jadikan data ini sebagai DATA UTAMA di Peta?');"
                                                    class="text-green-500 hover:text-green-600 bg-green-50 px-2 py-1 rounded border border-green-200 flex items-center"
                                                    title="Set Aktif (Tampilkan di Peta)">
                                                    <i class="fas fa-check-circle mr-1"></i> Set Aktif
                                                </button>
                                            </form>
                                        @else
                                            {{-- Opsi: Tampilkan status terkunci jika mau --}}
                                            <span class="text-xs text-gray-400 italic mr-2"
                                                title="Ada data lain yang sedang aktif">
                                                <i class="fas fa-lock"></i> Locked
                                            </span>
                                        @endif
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
            {{ $mappings->onEachSide(1)->links() }}
        </div>
    </div>

    {{-- 
        =======================================================
        SIMPLE TOAST ELEMENT (HTML MURNI)
        =======================================================
    --}}
    <div id="simple-toast"
        style="
        display: none; 
        position: fixed; 
        top: 20px; 
        right: 20px; 
        z-index: 99999; 
        background-color: white; 
        border-left: 6px solid #ef4444; /* Merah */
        padding: 20px; 
        border-radius: 8px; 
        box-shadow: 0 10px 25px rgba(0,0,0,0.3); 
        width: 350px; 
        font-family: sans-serif;">

        <div style="display: flex; align-items: start;">
            <div style="margin-right: 15px; font-size: 24px;">⛔</div>
            <div>
                <h3 style="margin: 0 0 5px 0; font-weight: bold; color: #333;">AKSES DITOLAK</h3>
                <p style="margin: 0; font-size: 14px; color: #666; line-height: 1.5;">
                    Data ini sudah <b>VERIFIED (Aktif)</b>.<br>
                    Silakan klik tombol kuning <b>'Revisi'</b> di tabel untuk membuka kunci.
                </p>
            </div>
            <button onclick="document.getElementById('simple-toast').style.display='none'"
                style="margin-left: auto; background: none; border: none; font-size: 20px; cursor: pointer; color: #999;">
                &times;
            </button>
        </div>
    </div>

    <div id="request-coord-modal"
        style="
    display: none; position: fixed; inset: 0; z-index: 2147483647;
    background-color: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px);
    align-items: center; justify-content: center;">

        <div
            style="
        background: white; width: 90%; max-width: 500px; border-radius: 12px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden;
        animation: modalFadeIn 0.2s ease-out;">

            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-gray-800"><i class="fas fa-satellite-dish mr-2 text-indigo-500"></i>Request
                    Koordinat Massal</h3>
                <button onclick="document.getElementById('request-coord-modal').style.display='none'"
                    class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>

            {{-- Body --}}
            <div class="p-6">

                {{-- STEP 1: FORM UPLOAD --}}
                <div id="req-step-1">
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
                        <p class="text-sm text-blue-700">
                            Upload file <b>.CSV</b> berisi daftar ID Pelanggan (1 kolom ke bawah).<br>
                            Sistem akan mengecek ketersediaan koordinat di database.
                        </p>
                        <p class="text-xs text-blue-500 mt-1">*Maksimal 1.000 IDPEL per request.</p>
                    </div>

                    <form id="form-request-coord" onsubmit="handleRequestSubmit(event)">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pilih File CSV/TXT</label>
                        <input type="file" name="file_idpel" accept=".csv, .txt" required
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer border border-gray-300 rounded-lg">

                        <button type="submit"
                            class="mt-6 w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold shadow-md transition-all flex justify-center items-center">
                            <i class="fas fa-search-location mr-2"></i> Cek Data Sekarang
                        </button>
                    </form>
                </div>

                {{-- STEP 2: LOADING --}}
                <div id="req-step-loading" style="display: none; text-align: center; padding: 20px;">
                    <svg class="animate-spin h-10 w-10 text-indigo-600 mx-auto mb-3"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    <p class="text-gray-600 font-medium">Sedang mencocokkan data...</p>
                    <p class="text-xs text-gray-400 mt-1">Mohon tunggu sebentar.</p>
                </div>

                {{-- STEP 3: RESULT --}}
                <div id="req-step-result" style="display: none;">
                    <div class="text-center mb-6">
                        <div
                            class="inline-flex items-center justify-center w-16 h-16 bg-green-100 text-green-500 rounded-full mb-3 animate-bounce">
                            <i class="fas fa-check text-2xl"></i>
                        </div>
                        <h4 class="text-lg font-bold text-gray-800">Proses Selesai!</h4>
                        <p class="text-sm text-gray-500">Data berhasil dicocokkan.</p>
                    </div>

                    {{-- Statistik Grid --}}
                    <div class="grid grid-cols-3 gap-3 mb-8 text-center">
                        <div class="bg-gray-50 p-3 rounded-xl border border-gray-100">
                            <span
                                class="block text-[10px] uppercase font-bold text-gray-400 tracking-wider">Total</span>
                            <span id="res-total" class="font-extrabold text-xl text-gray-800">0</span>
                        </div>
                        <div class="bg-green-50 p-3 rounded-xl border border-green-100">
                            <span
                                class="block text-[10px] uppercase font-bold text-green-600 tracking-wider">Ditemukan</span>
                            <span id="res-found" class="font-extrabold text-xl text-green-600">0</span>
                        </div>
                        <div class="bg-red-50 p-3 rounded-xl border border-red-100">
                            <span class="block text-[10px] uppercase font-bold text-red-500 tracking-wider">Tidak
                                Ada</span>
                            <span id="res-notfound" class="font-extrabold text-xl text-red-500">0</span>
                        </div>
                    </div>

                    {{-- DUA TOMBOL SAMA BESAR (Stack Vertikal) --}}
                    <div class="space-y-3">
                        {{-- Tombol 1: Download (Hijau) --}}
                        <a id="btn-download-result" href="#" target="_blank"
                            class="flex items-center justify-center w-full h-12 bg-green-600 hover:bg-green-700 text-white rounded-lg font-bold shadow-md hover:shadow-lg transition-all transform hover:-translate-y-0.5">
                            <i class="fas fa-file-download mr-2 text-lg"></i> Download Requests
                        </a>

                        {{-- Tombol 2: Tutup (Abu-abu) - Gantikan Cek File Lain --}}
                        <button onclick="document.getElementById('request-coord-modal').style.display='none'"
                            class="flex items-center justify-center w-full h-12 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-bold shadow-sm transition-all">
                            <i class="fas fa-times mr-2 text-lg"></i> Tutup
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

{{-- Helper Styles --}}
<style>
    .btn-primary-sm {
        @apply inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-bold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition active:scale-95;
    }

    .btn-secondary-sm {
        @apply inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg font-bold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition;
    }

    /* KUSTOMISASI POPUP PETA */
    .pretty-popup .leaflet-popup-content-wrapper {
        padding: 0 !important;
        border-radius: 0.5rem;
        overflow: hidden;
    }

    .pretty-popup .leaflet-popup-content {
        margin: 0 !important;
        width: 100% !important;
        line-height: 1.2;
    }

    .pretty-popup .leaflet-popup-close-button {
        top: 4px !important;
        right: 4px !important;
        color: #9ca3af !important;
        font-size: 14px !important;
        z-index: 10;
    }

    .pretty-popup .leaflet-popup-close-button:hover {
        color: #ef4444 !important;
    }

    .pretty-popup .leaflet-popup-tip {
        background: white;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
</style>
