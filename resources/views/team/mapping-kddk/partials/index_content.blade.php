{{-- Kontainer utama yang menyusun semua bagian secara vertikal --}}
<div class="space-y-6">

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
                $displayIdpel = $searchedIdpel ?? 'Belum Ada Pencarian';
                $isSearched = (isset($search) && $search);
                $isVerified = $mappingStatus === 'valid';
            @endphp

            <h2 class="text-3xl font-semibold text-gray-800 dark:text-gray-200">
                <span class="mr-3" id="detail-title-span">Data Mapping Pelanggan - {{ $displayIdpel }}</span>
            </h2>
            
            {{-- Status Stamp --}}
            <img id="detail-status-stamp" 
                src="{{ $isVerified ? asset('images/verified_stamp.png') : asset('images/unverified_stamp.png') }}" 
                alt="{{ $isVerified ? 'Verified' : 'Unverified' }}" 
                class="h-10 w-auto {{ $isSearched ? '' : 'hidden' }}" 
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
                    <span class="font-medium text-gray-700 dark:text-gray-300">Koordinat:</span>
                    <span id="detail-lat-lon" class="font-bold text-indigo-600 dark:text-indigo-400">
                        @if(isset($searchedMapping) && $searchedMapping->latitudey)
                            {{ number_format($searchedMapping->latitudey, 6) }}, {{ number_format($searchedMapping->longitudex, 6) }}
                        @else
                            Pilih data dari tabel
                        @endif
                    </span>
                </div>
            </div>


            {{-- 2. FOTO KWH METER (Zoom & Tampilan Dinamis) --}}
            <div class="lg:col-span-1 space-y-2">
                <p class="font-semibold text-gray-700 dark:text-gray-300">Foto KWH Meter</p>
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
                        <th scope="col" class="py-2 px-6 text-left">Object ID</th>
                        <th scope="col" class="py-2 px-6 text-left">ID Pelanggan</th>
                        <th scope="col" class="py-2 px-6 text-left">User Pendataan</th>
                        <th scope="col" class="py-2 px-6 text-left">Tanggal Dibuat</th>
                        <th scope="col" class="relative py-2 px-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($mappings as $index => $map)
                        {{-- [MODIFIKASI] Menambah class "data-row-clickable" dan semua "data-*" attribute --}}
                        <tr class="data-row-clickable cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 text-sm text-gray-700 dark:text-gray-300"
                            data-objectid="{{ $map->objectid }}"
                            data-idpel="{{ $map->idpel }}"
                            data-foto-kwh-url="{{ $map->foto_kwh ? Storage::disk('public')->url($map->foto_kwh) : '' }}"
                            data-foto-bangunan-url="{{ $map->foto_bangunan ? Storage::disk('public')->url($map->foto_bangunan) : '' }}"
                            data-lat="{{ $map->latitudey ?? 0 }}"
                            data-lon="{{ $map->longitudex ?? 0 }}"
                            data-status="{{ $map->ket_validasi === 'valid' ? 'valid' : 'unverified' }}"
                            >
                            <td class="py-2 px-6 text-center font-medium">{{ $mappings->firstItem() + $index }}</td>
                            <td class="py-2 px-6 whitespace-nowrap font-medium text-gray-900 dark:text-gray-100">{{ $map->objectid }}</td>
                            <td class="py-2 px-6 whitespace-nowrap font-medium text-gray-900 dark:text-gray-100">{{ $map->idpel }}</td>
                            <td class="py-2 px-6 whitespace-nowrap">{{ $map->user_pendataan }}</td>
                            <td class="py-2 px-6 whitespace-nowrap">{{ $map->user_pendataan }}</td>
                            <td class="py-2 px-6 whitespace-nowrap text-right font-medium">
                                @if(
                                    Auth::user()->hasRole('admin') ||
                                    trim(Auth::user()->hierarchy_level_code) == trim($map->unitup) ||
                                    trim(Authg->user()->hierarchy_level_code) == trim($map->unitap)
                                )
                                {{-- Tombol Edit --}}
                                <a href="{{ route('team.mapping.edit', $map) }}" class="text-indigo-600 hover:text-indigo-900 mr-3" data-modal-link="true">Edit</a>

                                {{-- Tombol Invalidate --}}
                                <form action="{{ route('team.mapping-kddk.invalidate', $map->id) }}" 
                                      method="POST" 
                                      data-custom-handler="invalidate-action" {{-- MARKER UNTUK JS --}}
                                      class="inline-block mr-3">
                                    @csrf
                                    <button type="submit" class="text-yellow-600 hover:text-yellow-900">
                                        Invalidate
                                    </button>
                                </form>

                                {{-- Tombol Hapus --}}
                                <a href="#" class="text-red-600 hover:text-red-900" data-delete-url="{{ route('team.mapping.destroy', $map) }}" data-user-name="data mapping untuk {{ $map->idpelanggan }}">Hapus</a>
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
</div>