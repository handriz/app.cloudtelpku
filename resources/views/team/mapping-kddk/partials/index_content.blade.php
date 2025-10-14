{{-- Kontainer utama yang menyusun semua bagian secara vertikal --}}
<div class="space-y-6">

    {{-- ====================================================== --}}
    {{-- BAGIAN ATAS (Tombol Aksi di Kiri, Pencarian di Kanan) --}}
    {{-- ====================================================== --}}
    <div class="flex flex-col md:flex-row justify-between items-start gap-6">
        {{-- Tombol Aksi (sekarang di kiri) --}}
        <div class="flex-shrink-0 flex space-x-2">
            @can('upload-master_data')
            <a href="{{ route('team.mapping.upload') }}" class="inline-flex items-center px-4 py-2 bg-green-600 border rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700" data-modal-link="true">
                <i class="fas fa-file-csv mr-2"></i><span>Upload</span>
            </a>
            @endcan
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
        <div class="mb-4 flex items-center justify-center">
            @if (isset($search) && $search && $mappingStatus)
                <h2 class="text-3xl font-semibold text-gray-800 dark:text-gray-200">
                    <span class="mr-3">Data Mapping Pelanggan - {{ $searchedIdpel }}</span>
                </h2>
                @if ($mappingStatus === 'valid')
                    <img src="{{ asset('images/verified_stamp.png') }}" alt="Verified" class="h-10 w-auto" title="Status: Valid">
                @else
                    <img src="{{ asset('images/unverified_stamp.png') }}" alt="Unverified" class="h-10 w-auto" title="Status: Unverified / Belum divalidasi">
                @endif
            @else
                <h2 class="text-3xl font-semibold text-gray-800 dark:text-gray-200">
                    Mapping Pelanggan
                </h2>
            @endif
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 bg-white dark:bg-gray-800 rounded-lg shadow">
                <div id="map" class="w-full h-full min-h-[450px] rounded-lg z-0"></div>
            </div>
            <div class="lg:col-span-1 bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex flex-col items-center justify-center min-h-[450px]">
                <i class="fas fa-camera text-5xl text-gray-300 dark:text-gray-600"></i>
                <p class="mt-4 font-semibold text-gray-500 dark:text-gray-400">Foto KWH Meter</p>
                <p class="text-sm text-gray-400 dark:text-gray-500">Akan tampil di sini</p>
            </div>
            <div class="lg:col-span-1 bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex flex-col items-center justify-center min-h-[450px]">
                <i class="fas fa-building text-5xl text-gray-300 dark:text-gray-600"></i>
                <p class="mt-4 font-semibold text-gray-500 dark:text-gray-400">Foto Bangunan (APP)</p>
                <p class="text-sm text-gray-400 dark:text-gray-500">Akan tampil di sini</p>
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
                        <th scope="col" class="relative py-2 px-6"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($mappings as $index => $map)
                        <tr class="text-sm text-gray-700 dark:text-gray-300">
                            <td class="py-2 px-6 text-center font-medium">{{ $mappings->firstItem() + $index }}</td>
                            <td class="py-2 px-6 whitespace-nowrap font-medium text-gray-900 dark:text-gray-100">{{ $map->objectid }}</td>
                            <td class="py-2 px-6 whitespace-nowrap font-medium text-gray-900 dark:text-gray-100">{{ $map->idpelanggan }}</td>
                            <td class="py-2 px-6 whitespace-nowrap">{{ $map->user_pendataan }}</td>
                            <!-- <td class="py-2 px-6 whitespace-nowrap">{{ $map->created_at->format('d M Y, H:i') }}</td> -->
                            <td class="py-2 px-6 whitespace-nowrap text-right font-medium">
                                <a href="{{ route('team.mapping.edit', $map) }}" class="text-indigo-600 hover:text-indigo-900 mr-3" data-modal-link="true">Edit</a>
                                <a href="#" class="text-red-600 hover:text-red-900" data-delete-url="{{ route('team.mapping.destroy', $map) }}" data-user-name="data mapping untuk {{ $map->idpelanggan }}">Hapus</a>
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