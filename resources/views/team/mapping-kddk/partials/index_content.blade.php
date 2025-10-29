{{-- Kontainer utama yang menyusun semua bagian secara vertikal --}}
<div class="space-y-6">

    {{-- ====================================================== --}}
    {{-- MODAL UNTUK KONFIRMASI KUSTOM (INVALIDATE, DELETE, DLL) --}}
    {{-- ====================================================== --}}
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
    {{-- BAGIAN ATAS (Tombol Aksi di Kiri, Pencarian di Kanan) --}}
    {{-- ====================================================== --}}
    <div class="flex flex-col md:flex-row justify-between items-start gap-6">
        {{-- Tombol Aksi (sekarang di kiri) --}}
        <div class="flex-shrink-0 flex space-x-2">
            <a href="{{ route('team.mapping.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700" data-modal-link="true">
                <i class="fas fa-plus mr-2"></i><span>Tambah</span>
            </a>
        </div>
        
        {{-- Form Pencarian (sekarang di kanan) --}}
        <div class="w-full md:w-1/3">
            <form id="mapping-search-form" action="{{ route('team.mapping.index') }}" autocomplete="off" method="GET">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3"><i class="fas fa-search text-gray-400"></i></span>
                    <input type="text" name="search" value="{{ $search ?? '' }}" class="w-full pl-10 pr-4 py-2 border rounded-md dark:bg-gray-700 dark:border-gray-600" placeholder="Cari ID Pelanggan, No. KWH...">
                    <button type="button" id="clear-search-button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 hidden">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </button>                
                </div>
            </form>
        </div>
    </div>

    {{-- ====================================================== --}}
    {{-- BAGIAN TENGAH (Judul, Peta & Foto) --}}
    {{-- ====================================================== --}}
    <div>
        {{-- Judul dan Status Stamp --}}
        <div class="mb-4 flex items-center justify-center">
            @php
               $isSearched = (isset($search) && $search);
               $hasData = isset($searchedMapping) && $searchedMapping;
               $isVerified = $hasData && $mappingStatus === 'valid';
               $titleText = 'Data Mapping Pelanggan';
               if ($isSearched) {
                if ($hasData) {
                    $titleText = 'Data Mapping Pelanggan - ' . $searchedMapping->idpel;
                }else {
                    $titleText = 'Data Mapping Pelanggan - No Data';
                }
               }
            @endphp

            <h2 class="text-3xl font-semibold text-gray-800 dark:text-gray-200">
                <span class="mr-3" id="detail-title-span">{{ $titleText }}</span>
            </h2>
            
            {{-- Status Stamp --}}
            <img id="detail-status-stamp" 
                src="{{ $isVerified ? asset('images/verified_stamp.png') : asset('images/unverified_stamp.png') }}" 
                alt="{{ $isVerified ? 'Verified' : 'Unverified' }}" 
                class="h-10 w-auto {{ ($isSearched && $hasData) ? '' : 'hidden' }}"
                title="Status: {{ $isVerified ? 'Valid' : 'Unverified / Belum divalidasi' }}">
        </div>
        
        {{-- LAYOUT HEADER BARU: 3 Kolom --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6"> 
            
            {{-- 1. PETA (Map Container) --}}
            <div class="lg:col-span-1 space-y-2">
                <p class="font-semibold text-gray-700 dark:text-gray-300">Posisi Koordinat</p>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                    <div id="map" class="w-full h-full min-h-[450px] rounded-lg z-0"></div>
                </div>
                {{-- Detail Koordinat Dinamis di bawah peta (Opsional, agar mirip Validasi yang ada di bawah) --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-2 text-sm">
                    <div>
                        <span class="font-medium text-gray-700 dark:text-gray-300">Koordinat:</span>
                        <span id="detail-lat-lon" class="font-bold text-indigo-600 dark:text-indigo-400">
                            @if(isset($searchedMapping) && $searchedMapping->latitudey)
                                {{ number_format($searchedMapping->latitudey, 6) }}, {{ number_format($searchedMapping->longitudex, 6) }}
                            @else
                                Pilih data dari tabel
                            @endif
                        </span>
                    </div>
                    {{-- TOMBOL GOOGLE STREET VIEW BARU --}}
                    @php
                        $hasInitialCoords = isset($searchedMapping) && $searchedMapping->latitudey && $searchedMapping->latitudey != 0;
                        $initialLat = $searchedMapping->latitudey ?? 0;
                        $initialLon = $searchedMapping->longitudex ?? 0;
                    @endphp
                    <a id="google-street-view-link" 
                       href="#" {{-- Ubah href menjadi # --}}
                       rel="noopener noreferrer" 
                       title="Buka Google Street View"
                       class="text-blue-500 hover:text-blue-700 {{ $hasInitialCoords ? '' : 'hidden' }}">
                        <i class="fas fa-street-view fa-lg"></i>
                    </a>
                </div>
            </div>


            {{-- 2. FOTO KWH METER (Zoom & Tampilan Dinamis) --}}
            <div class="lg:col-span-1 space-y-2">
                <p class="font-semibold text-gray-700 dark:text-gray-300">Foto </p>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex flex-col items-center justify-center min-h-[450px]">
                    {{-- Link Pemicu Zoom (<a>) --}}
                    <a href="#" class="image-zoom-trigger {{ (isset($searchedMapping) && $searchedMapping->foto_kwh) ? '' : 'hidden' }}" 
                       data-zoom-type="kwh" id="detail-foto-kwh-link">
                        <img id="detail-foto-kwh" 
                             src="{{ (isset($searchedMapping) && $searchedMapping->foto_kwh) ? Storage::disk('public')->url($searchedMapping->foto_kwh) : '' }}" 
                             alt="Foto KWH" 
                             class="max-w-full max-h-[450px] object-contain rounded-lg">
                    </a>
                    
                    {{-- Placeholder --}}
                    <div id="placeholder-foto-kwh" 
                         class="flex-col items-center justify-center {{ (isset($searchedMapping) && $searchedMapping->foto_kwh) ? 'hidden' : 'flex' }}">
                        <i class="fas fa-camera text-5xl text-gray-300 dark:text-gray-600"></i>
                        <p class="mt-4 font-semibold text-gray-500 dark:text-gray-400">Foto KWH Meter</p>
                        <p class="text-sm text-gray-400 dark:text-gray-500">Akan tampil di sini</p>
                    </div>
                </div>
            </div>
            
            {{-- 3. FOTO BANGUNAN (Zoom & Tampilan Dinamis) --}}
            <div class="lg:col-span-1 space-y-2">
                <p class="font-semibold text-gray-700 dark:text-gray-300">Foto Bangunan (Persil)</p>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex flex-col items-center justify-center min-h-[450px]">
                    {{-- Link Pemicu Zoom (<a>) --}}
                    <a href="#" class="image-zoom-trigger {{ (isset($searchedMapping) && $searchedMapping->foto_bangunan) ? '' : 'hidden' }}" 
                       data-zoom-type="persil" id="detail-foto-bangunan-link">
                        <img id="detail-foto-bangunan" 
                             src="{{ (isset($searchedMapping) && $searchedMapping->foto_bangunan) ? Storage::disk('public')->url($searchedMapping->foto_bangunan) : '' }}" 
                             alt="Foto Bangunan" 
                             class="max-w-full max-h-[450px] object-contain rounded-lg">
                    </a>
                         
                    {{-- Placeholder --}}
                    <div id="placeholder-foto-bangunan" 
                         class="flex-col items-center justify-center {{ (isset($searchedMapping) && $searchedMapping->foto_bangunan) ? 'hidden' : 'flex' }}">
                        <i class="fas fa-building text-5xl text-gray-300 dark:text-gray-600"></i>
                        <p class="mt-4 font-semibold text-gray-500 dark:text-gray-400">Foto Bangunan (Persil)</p>
                        <p class="text-sm text-gray-400 dark:text-gray-500">Akan tampil di sini</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ====================================================== --}}
    {{-- BAGIAN BAWAH (Hanya Tabel Data) --}}
    {{-- ====================================================== --}}
    <div class="w-full">
        <div class="overflow-x-auto bg-white dark:bg-gray-800 rounded-lg shadow">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="text-xs text-gray-900 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-200">
                    <tr>
                        <th scope="col" class="py-2 px-6 w-16">No.</th>
                        <th scope="col" class="py-2 px-6 text-left">Object Id</th>
                        <th scope="col" class="py-2 px-6 text-left">Idpel</th>
                        <th scope="col" class="py-2 px-6 text-left">User Pendataan</th>
                        <th scope="col" class="py-2 px-6 text-left">Total Object Id</th>
                        <th scope="col" class="py-2 px-6 text-left">Status</th>
                        <th scope="col" class="relative py-2 px-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($mappings as $index => $map)
                        @php
                        $status = $map->ket_validasi;
                        $isEnabled = $map->enabled;
                        $rowClass = '';
                        $statusText = 'N/A';
                        $statusClass = 'text-gray-500';
                            if ($isEnabled) {
                                // --- INI ADALAH DATA AKTIF ---
                                $rowClass = 'bg-green-50 dark:bg-green-900/50';
                                $statusText = 'Valid (Aktif)';
                                $statusClass = 'font-semibold text-green-600 dark:text-green-400';
                            
                            } else {
                                // --- INI ADALAH DATA NON-AKTIF, cek alasannya ---
                                if ($status === 'verified') {
                                    $rowClass = 'bg-blue-50 dark:bg-blue-900/50';
                                    $statusText = 'Terverifikasi';
                                    $statusClass = 'text-blue-600 dark:text-blue-400';
                                } elseif ($status === 'superseded') {
                                    $rowClass = 'bg-yellow-50 dark:bg-yellow-900/50 opacity-70';
                                    $statusText = 'Digantikan';
                                    $statusClass = 'text-yellow-600 dark:text-yellow-400';
                                } elseif ($status === 'recalled_1' || $status === 'rejected') {
                                    $rowClass = 'bg-red-50 dark:bg-red-900/50 opacity-70';
                                    $statusText = 'Ditarik/Ditolak';
                                    $statusClass = 'text-red-500';
                                } else {
                                    $statusText = $status; // Tampilkan status lain jika ada
                                }
                            }
                        @endphp                     
                        <tr class="data-row-clickable cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 text-sm text-gray-700 dark:text-gray-300"
                            data-objectid="{{ $map->objectid }}"
                            data-idpel="{{ $map->idpel }}"
                            data-foto-kwh-url="{{ $map->foto_kwh ? Storage::disk('public')->url($map->foto_kwh) : '' }}"
                            data-foto-bangunan-url="{{ $map->foto_bangunan ? Storage::disk('public')->url($map->foto_bangunan) : '' }}"
                            data-lat="{{ $map->latitudey ?? 0 }}"
                            data-lon="{{ $map->longitudex ?? 0 }}"
                            data-status="{{ $status }}"
                            data-enabled="{{ $map->enabled ? 'true' : 'false' }}"
                            >
                            <td class="py-2 px-6 text-center font-medium">{{ $mappings->firstItem() + $index }}</td>
                            <td class="py-2 px-6 whitespace-nowrap font-medium text-gray-900 dark:text-gray-100">{{ $map->objectid }}</td>
                            <td class="py-2 px-6 whitespace-nowrap font-medium text-gray-900 dark:text-gray-100">{{ $map->idpel }}</td>
                            <td class="py-2 px-6 whitespace-nowrap">{{ $map->user_pendataan }}</td>
                            <td class="py-2 px-6 whitespace-nowrap">{{ $map->user_pendataan }}</td>
                            <td class="py-2 px-6 whitespace-nowrap {{ $statusClass }}">
                                {{ $statusText }}
                            </td>
                            <td class="py-2 px-6 whitespace-nowrap text-right font-medium">
                                @if(
                                    Auth::user()->hasRole('admin') ||
                                    trim(Auth::user()->hierarchy_level_code) == trim($map->unitup) ||
                                    trim(Authg->user()->hierarchy_level_code) == trim($map->unitap)
                                )
                                @if(!$map->enabled && $map->ket_validasi === 'verified')
                                    <form action="{{ route('team.mapping-kddk.promote', $map->id) }}" 
                                          method="POST" 
                                          data-custom-handler="promote-action"
                                          class="inline-block mr-4">
                                        @csrf
                                        <button type="submit" class="text-green-600 hover:text-green-900" title="Set as Active Valid">
                                            <i class="fas fa-arrow-alt-circle-up fa-lg"></i>
                                        </button>
                                    </form>
                                @endif
                                {{-- Tombol Edit (Selalu bisa) --}}
                                <a href="{{ route('team.mapping.edit', $map) }}" class="text-indigo-600 hover:text-indigo-900 mr-4" data-modal-link="true" title="Edit Data">
                                    <i class="fas fa-pencil-alt fa-lg"></i>
                                </a>
                                {{-- Tombol Invalidate (HANYA jika status 'valid') --}}
                                @if($map->enabled)
                                <form action="{{ route('team.mapping-kddk.invalidate', $map->id) }}" 
                                      method="POST" 
                                      data-custom-handler="invalidate-action" {{-- MARKER UNTUK JS --}}
                                      class="inline-block mr-4">
                                    @csrf
                                    <button type="submit" class="text-yellow-600 hover:text-yellow-900" title="Invalidate (Tarik Kembali)">
                                        <i class="fas fa-undo fa-lg"></i>
                                    </button>
                                </form>
                                @endif

                                {{-- Tombol Hapus --}}
                                <a href="#" class="text-red-600 hover:text-red-900" data-delete-url="{{ route('team.mapping.destroy', $map) }}" data-user-name="data mapping untuk {{ $map->idpelanggan }}" title="Hapus Data">
                                    <i class="fas fa-trash-alt fa-lg"></i>
                                </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 px-6 text-center text-gray-500">Tidak ada data ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $mappings->links() }}
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
</div>