<x-app-layout>
    {{-- Container utama --}}
    <div class="pt-2 pb-0">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mb-4">
            {{ __('Dashboard Data Pelanggan ') }}
        </h2>

        <hr class="border-gray-200 dark:border-gray-700 my-6">

        {{-- Grid Kartu Konten Dashboard --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

            {{-- Card Total Pelanggan --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 uppercase tracking-wider">
                              
                            Total Pelanggan Aktif
                            </p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {{ $totalPelanggan }}
                            </p>
                            {{-- Tampilkan informasi bulan rekap di dalam kartu --}}
                            @if ($latestBulanRekap)
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Bulan Rekap: <span class="font-semibold">{{ $latestBulanRekap }}</span>
                                </p>
                            @endif
                        </div>
                        <i class="fas fa-users text-4xl text-gray-300 dark:text-gray-600"></i>
                    </div>
                </div>
            </div>

            {{-- Card Pelanggan Berdasarkan Jenis Layanan (diperbarui) --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        Pelanggan Berdasarkan Jenis Layanan
                    </h3>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($distribusilayanan as $jenislayanan => $count)
                        <li class="py-2 flex justify-between items-center text-sm text-gray-700 dark:text-gray-300">
                            <span>{{ $jenislayanan }}</span>
                            <span class="px-2 py-1 text-xs font-semibold bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full">{{ $count }}</span>
                        </li>
                        @empty
                        <li class="py-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada data jenis layanan ditemukan.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            {{-- Card Pelanggan Berdasarkan Daya --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        Pelanggan Berdasarkan Daya
                    </h3>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($pelangganByDaya as $daya => $count)
                        <li class="py-2 flex justify-between items-center text-sm text-gray-700 dark:text-gray-300">
                            <span>{{ $daya }} VA</span>
                            <span class="px-2 py-1 text-xs font-semibold bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full">{{ $count }}</span>
                        </li>
                        @empty
                        <li class="py-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada data daya ditemukan.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        
        </div>

        <hr class="border-gray-200 dark:border-gray-700 my-6">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Card Rekapitulasi Prabayar --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4 text-center">
                        Rekapitulasi Pelanggan Prabayar
                    </h3>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($pelangganPrabayarByDaya as $daya => $count)
                        <li class="py-2 flex justify-between items-center text-sm text-gray-700 dark:text-gray-300">
                            <span>Daya {{ $daya }} VA</span>
                            <span class="px-2 py-1 text-xs font-semibold bg-blue-200 dark:bg-blue-700 text-blue-800 dark:text-blue-200 rounded-full">{{ $count }}</span>
                        </li>
                        @empty
                        <li class="py-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada data prabayar ditemukan.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
            {{-- Card Rekapitulasi Paskabayar --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4 text-center">
                        Rekapitulasi Pelanggan Paskabayar
                    </h3>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($pelangganPaskabayarByDaya as $daya => $count)
                        <li class="py-2 flex justify-between items-center text-sm text-gray-700 dark:text-gray-300">
                            <span>Daya {{ $daya }} VA</span>
                            <span class="px-2 py-1 text-xs font-semibold bg-red-200 dark:bg-red-700 text-red-800 dark:text-red-200 rounded-full">{{ $count }}</span>
                        </li>
                        @empty
                        <li class="py-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada data paskabayar ditemukan.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>  
</x-app-layout>
