<div class="p-6 space-y-6 bg-gray-50 dark:bg-gray-900 min-h-screen">

    {{-- HEADER --}}
    <div class="flex flex-col md:flex-row justify-between items-center bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800 dark:text-white tracking-tight">Dashboard Monitoring</h2>
            <p class="text-sm text-gray-500">Unit Layanan: <span class="font-mono font-bold text-indigo-600">{{ $unitCode }}</span></p>
        </div>
        <div class="flex space-x-2 mt-4 md:mt-0">
            <button onclick="App.Tabs.loadTabContent(App.Utils.getActiveTabName(), '{{ route('team.matrix_kddk.index') }}')" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-bold text-sm transition">
                <i class="fas fa-arrow-left mr-2"></i> Kembali ke Menu
            </button>
            <button onclick="App.Tabs.reloadActiveTab()" class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg font-bold text-sm transition">
                <i class="fas fa-sync-alt mr-2"></i> Refresh Data
            </button>
        </div>
    </div>

    {{-- 1. KARTU STATISTIK (KPI) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border-l-4 border-blue-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase">Total Pelanggan</p>
                    <h3 class="text-2xl font-extrabold text-gray-800 dark:text-white mt-1">{{ number_format($stats['total_plg']) }}</h3>
                </div>
                <div class="p-2 bg-blue-50 rounded-lg text-blue-600"><i class="fas fa-users text-xl"></i></div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border-l-4 border-green-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase">Sudah Masuk Rute</p>
                    <h3 class="text-2xl font-extrabold text-green-600 mt-1">{{ number_format($stats['sudah_petakan']) }}</h3>
                    <span class="text-xs font-bold bg-green-100 text-green-800 px-2 py-0.5 rounded-full mt-2 inline-block">
                        {{ $stats['progress_persen'] }}% Selesai
                    </span>
                </div>
                <div class="p-2 bg-green-50 rounded-lg text-green-600"><i class="fas fa-map-marked-alt text-xl"></i></div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border-l-4 border-purple-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase">Total Rute</p>
                    <h3 class="text-2xl font-extrabold text-gray-800 dark:text-white mt-1">{{ number_format($stats['total_rute']) }}</h3>
                    <p class="text-xs text-gray-400 mt-1">Kelompok Terbentuk</p>
                </div>
                <div class="p-2 bg-purple-50 rounded-lg text-purple-600"><i class="fas fa-route text-xl"></i></div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border-l-4 border-red-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase">Tanpa Koordinat</p>
                    <h3 class="text-2xl font-extrabold text-red-600 mt-1">{{ number_format($stats['tanpa_coord']) }}</h3>
                    <p class="text-xs text-red-400 mt-1 cursor-pointer hover:underline" onclick="window.location.href='{{ route('team.matrix_kddk.details', ['unit' => $unitCode]) }}?search=no_coord'">
                        Perlu Survey Lapangan <i class="fas fa-arrow-right ml-1"></i>
                    </p>
                </div>
                <div class="p-2 bg-red-50 rounded-lg text-red-600"><i class="fas fa-exclamation-triangle text-xl"></i></div>
            </div>
        </div>
    </div>

    {{-- 2. GRAFIK (CHARTS) --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {{-- Chart 1: Progress per Area (Bar) --}}
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-4 border-b pb-2">
                <i class="fas fa-chart-bar text-indigo-500 mr-2"></i> Sebaran Pelanggan per Area
            </h4>
            <div id="chart-area-bar" class="h-80 w-full"></div>
        </div>

        {{-- Chart 2: Kualitas Data (Pie) --}}
        <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 mb-4 border-b pb-2">
                <i class="fas fa-chart-pie text-green-500 mr-2"></i> Kelengkapan Koordinat
            </h4>
            <div id="chart-quality-pie" class="h-64 w-full flex justify-center"></div>
            
            {{-- Legend Custom --}}
            <div class="mt-4 space-y-2">
                <div class="flex justify-between text-xs">
                    <span class="flex items-center"><span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span> Lengkap</span>
                    <span class="font-bold">{{ number_format($qualityStats['valid']) }}</span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="flex items-center"><span class="w-3 h-3 bg-red-500 rounded-full mr-2"></span> Belum Ada</span>
                    <span class="font-bold">{{ number_format($qualityStats['invalid']) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. TABEL TOP RUTE (TERPADAT) --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300">
                <i class="fas fa-list-ol text-orange-500 mr-2"></i> 5 Rute Terpadat (Perlu Dipecah?)
            </h4>
        </div>
        <table class="min-w-full text-sm text-left">
            <thead class="bg-gray-100 dark:bg-gray-700 text-gray-500 font-bold uppercase text-xs">
                <tr>
                    <th class="px-6 py-3">Nama Rute</th>
                    <th class="px-6 py-3 text-center">Jumlah Pelanggan</th>
                    <th class="px-6 py-3 text-center">Status Beban</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($topRoutes as $r)
                    @php
                        $statusColor = $r->total > 300 ? 'bg-red-100 text-red-700' : ($r->total > 200 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700');
                        $statusText = $r->total > 300 ? 'Overload' : ($r->total > 200 ? 'Padat' : 'Normal');
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-3 font-mono font-bold text-indigo-600">{{ $r->rute }}</td>
                        <td class="px-6 py-3 text-center font-bold">{{ $r->total }}</td>
                        <td class="px-6 py-3 text-center">
                            <span class="px-2 py-1 rounded text-xs font-bold {{ $statusColor }}">{{ $statusText }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- DATA HOLDER (Untuk JS) --}}
    <div id="dashboard-analytics-data" class="hidden"
         data-area-labels="{{ json_encode($areaStats->pluck('area_code')) }}"
         data-area-values="{{ json_encode($areaStats->pluck('total')) }}"
         data-quality-valid="{{ $qualityStats['valid'] }}"
         data-quality-invalid="{{ $qualityStats['invalid'] }}"
    ></div>

</div>