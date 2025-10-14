<div class="pt-2 pb-0 px-4 sm:px-6 lg:px-8">
    {{-- Header dengan Tombol Upload --}}
    <div class="flex justify-between items-center mb-4">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight ml-4 sm:ml-6 lg:ml-8">
            {{ __('Dashboard Data Pelanggan ') }}
        </h2>
        @can('upload-master-data')
        <a href="{{ route('admin.manajemen_data.upload') }}" 
           class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
           data-modal-link="true">
            <i class="fas fa-upload mr-2"></i>
            <span>Upload Data</span>
        </a>
        @endcan
    </div>
    <hr class="border-gray-200 dark:border-gray-700 my-6">

    {{-- Grid Kartu Ringkasan Atas --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {{-- Card Total Pelanggan --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 flex items-center">
            <div class="bg-indigo-500 rounded-full p-3 mr-4">
                <i class="fas fa-users text-white text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Pelanggan Aktif</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($totalPelanggan) }}</p>
            </div>
        </div>

        {{-- Card Prabayar --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 flex items-center">
            <div class="bg-green-500 rounded-full p-3 mr-4">
                <i class="fas fa-bolt text-white text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Prabayar</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($distribusilayanan['PRABAYAR'] ?? 0) }}</p>
            </div>
        </div>

        {{-- Card Paskabayar --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 flex items-center">
            <div class="bg-yellow-500 rounded-full p-3 mr-4">
                <i class="fas fa-file-invoice-dollar text-white text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Paskabayar</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($distribusilayanan['PASKABAYAR'] ?? 0) }}</p>
            </div>
        </div>

        {{-- Card Bulan Rekap Terakhir --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 flex items-center">
            <div class="bg-red-500 rounded-full p-3 mr-4">
                <i class="fas fa-calendar-alt text-white text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Bulan Rekap Dil Terakhir</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $latestBulanRekap ? \Carbon\Carbon::createFromFormat('Ym', $latestBulanRekap)->format('F Y') : 'N/A' }}</p>
            </div>
        </div>
    </div>
    <hr class="border-gray-200 dark:border-gray-700 my-6">
    
    {{-- Tabel Pelanggan Berdasarkan Daya (Dengan Paginasi & Sorting) --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Pelanggan Berdasarkan Daya
            </h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-left text-gray-700 dark:text-gray-300">
                    <thead class="text-xs text-gray-900 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-200">
                        <tr>
                            {{-- 1. TAMBAHKAN HEADER UNTUK NOMOR URUT --}}
                            <th scope="col" class="py-2 px-6 w-16">
                                No.
                            </th>

                            {{-- Header untuk Sorting Kolom DAYA --}}
                            <th scope="col" class="py-2 px-6">
                                @php
                                    $dayaDirection = (request('sort') == 'DAYA' && request('direction') == 'asc') ? 'desc' : 'asc';
                                    $dayaSortParams = ['sort' => 'DAYA', 'direction' => $dayaDirection];
                                @endphp
                                <a href="{{ route(request()->route()->getName(), array_merge(request()->except('page'), $dayaSortParams)) }}" class="flex items-center hover:underline">
                                    Daya
                                    @if(request('sort', 'DAYA') == 'DAYA')
                                        <i class="fas fa-sort-{{ request('direction', 'asc') == 'asc' ? 'up' : 'down' }} ml-2"></i>
                                    @else
                                        <i class="fas fa-sort ml-2 text-gray-400"></i>
                                    @endif
                                </a>
                            </th>
                            
                            {{-- Header untuk Sorting Kolom JUMLAH --}}
                            <th scope="col" class="py-2 px-6 text-right">
                                @php
                                    $totalDirection = (request('sort') == 'total_pelanggan' && request('direction') == 'asc') ? 'desc' : 'asc';
                                    $totalSortParams = ['sort' => 'total_pelanggan', 'direction' => $totalDirection];
                                @endphp
                                <a href="{{ route(request()->route()->getName(), array_merge(request()->except('page'), $totalSortParams)) }}" class="flex items-center justify-end hover:underline">
                                    Jumlah Pelanggan
                                    @if(request('sort') == 'total_pelanggan')
                                        <i class="fas fa-sort-{{ request('direction') == 'asc' ? 'up' : 'down' }} ml-2"></i>
                                    @else
                                        <i class="fas fa-sort ml-2 text-gray-400"></i>
                                    @endif
                                </a>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Loop menggunakan data dari Paginator --}}
                        @forelse($pelangganByDaya as $index => $data)
                            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                {{-- 2. TAMBAHKAN DATA UNTUK NOMOR URUT --}}
                                <td class="py-2 px-6 font-medium text-center">
                                    {{ $pelangganByDaya->firstItem() + $index }}
                                </td>

                                <td class="py-2 px-6 font-medium whitespace-nowrap">
                                    {{ number_format($data->DAYA) }} VA
                                </td>
                                <td class="py-2 px-6 text-right">
                                    <span class="px-2 py-1 text-xs font-semibold bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full">
                                        {{ number_format($data->total_pelanggan) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                <td colspan="3" class="py-2 px-6 text-center text-gray-500">
                                    Tidak ada data daya ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            {{-- Link Paginasi --}}
            <div class="mt-4">
                {{ $pelangganByDaya->links() }}
            </div>
        </div>
    </div>

    <hr class="border-gray-200 dark:border-gray-700 my-6">
    
    {{-- Grid untuk Rekapitulasi Prabayar dan Paskabayar --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Card Rekapitulasi Prabayar --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4 text-center">
                    Rekapitulasi Pelanggan Prabayar
                </h3>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700 max-h-96 overflow-y-auto">
                    @forelse ($pelangganPrabayarByDaya->sortKeys() as $daya => $count)
                    <li class="py-2 flex justify-between items-center text-sm text-gray-700 dark:text-gray-300">
                        <span>Daya {{ number_format($daya) }} VA</span>
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
                <ul class="divide-y divide-gray-200 dark:divide-gray-700 max-h-96 overflow-y-auto">
                    @forelse ($pelangganPaskabayarByDaya->sortKeys() as $daya => $count)
                    <li class="py-2 flex justify-between items-center text-sm text-gray-700 dark:text-gray-300">
                        <span>Daya {{ number_format($daya) }} VA</span>
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