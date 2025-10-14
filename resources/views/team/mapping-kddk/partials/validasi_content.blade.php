{{-- Kontainer utama yang menyusun semua bagian secara vertikal --}}
<div class="space-y-6">

    {{-- ====================================================== --}}
    {{-- BAGIAN ATAS (Kartu Progress dan Tombol Aksi Sejajar) --}}
    {{-- ====================================================== --}}
    <div class="flex justify-between items-start gap-6">
        {{-- Kolom Kiri untuk Kartu --}}
        <div class="w-full md:w-1/4">
            <div x-data="{ open: true }" class="bg-white dark:bg-gray-800 rounded-lg shadow">
                {{-- Header Kartu dengan Tombol Toggle --}}
                <div class="p-5 flex justify-between items-center cursor-pointer" @click="open = !open">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Progress Pemetaan Pelanggan
                    </h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-chevron-down transform transition-transform duration-200" :class="{'rotate-180': !open}"></i>
                    </button>
                </div>

                {{-- Konten Kartu yang bisa di-collapse --}}
                <div x-show="open" x-collapse>
                    <div class="p-5 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {{ number_format($totalMappingEnabled) }}
                            <span class="text-lg font-medium text-gray-600 dark:text-gray-300">
                                ({{ number_format($mappingPercentage, 2) }}%)
                            </span>
                        </p>
                        <div class="mt-4">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center">
                                <i class="fas fa-chart-line mr-2 text-green-500"></i>
                                <span>Target</span>
                            </p>
                            <p class="text-2xl font-bold text-orange-500 dark:text-orange-400 mt-1">
                                {{ number_format($totalPelanggan) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Kolom Kanan untuk Tombol Aksi --}}
        <div class="flex-shrink-0">
             <div class="flex space-x-2">
                <a href="{{ route('team.mapping.upload') }}"
                   class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700"
                   data-modal-link="true">
                    <i class="fas fa-file-csv mr-2"></i>
                    <span>Upload Massal</span>
                </a>
                <a href="{{ route('team.mapping.create') }}" 
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                   data-modal-link="true">
                    <i class="fas fa-plus mr-2"></i>
                    <span>Tambah Data</span>
                </a>
            </div>
        </div>
    </div>

    {{-- ====================================================== --}}
    {{-- BAGIAN TENGAH (Judul Dinamis, Peta & Foto) --}}
    {{-- ====================================================== --}}
    <div>
        <div class="mb-4 flex items-center justify-center">
            @if (isset($search) && $search && $mappingStatus)
                {{-- Tampilan JIKA SEDANG MELAKUKAN PENCARIAN --}}
                <h2 class="text-3xl font-semibold text-gray-800 dark:text-gray-200">
                    <span class="mr-3">Data Mapping Pelanggan - {{ $searchedIdpel }}</span>
                </h2>
                @if ($mappingStatus === 'valid')
                    <img src="{{ asset('images/verified_stamp.png') }}" alt="Verified" class="h-10 w-auto" title="Status: Valid">
                @else
                    {{-- Ganti 'unverified_stamp.png' dengan nama file ikon unverified Anda --}}
                    <img src="{{ asset('images/unverified_stamp.png') }}" alt="Unverified" class="h-10 w-auto" title="Status: Unverified / Belum divalidasi">
                @endif
            @else
                {{-- Tampilan DEFAULT (tanpa pencarian) --}}
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
    {{-- BAGIAN BAWAH (Pencarian & Tabel Data) --}}
    {{-- ====================================================== --}}
    <div class="w-full">
        <form id="mapping-search-form" action="{{ route('team.mapping.index') }}" method="GET" class="mb-4">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3"><i class="fas fa-search text-gray-400"></i></span>
                <input type="text" name="search" value="{{ $search ?? '' }}" class="w-full pl-10 pr-4 py-2 border rounded-md dark:bg-gray-700 dark:border-gray-600" placeholder="Cari ID Pelanggan, No. KWH, atau User Pendataan...">
            </div>
        </form>
        <div class="overflow-x-auto bg-white dark:bg-gray-800 rounded-lg shadow">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="text-xs text-gray-900 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-200">
                    <tr>
                        <th scope="col" class="py-2 px-6 w-16">No.</th>
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
                            <td class="py-2 px-6 whitespace-nowrap font-medium text-gray-900 dark:text-gray-100">{{ $map->idpelanggan }}</td>
                            <td class="py-2 px-6 whitespace-nowrap">{{ $map->user_pendataan }}</td>
                            <td class="py-2 px-6 whitespace-nowrap">{{ $map->created_at->format('d M Y, H:i') }}</td>
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