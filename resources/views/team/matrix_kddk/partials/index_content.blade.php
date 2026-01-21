<div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
    {{-- HEADER SECTION --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 flex items-center">
                <i class="fas fa-sitemap mr-2 text-indigo-600"></i> Progress Pemetaan KDDK
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Data summary diperbarui otomatis tiap 60 menit.
            </p>
        </div>
        {{-- Indikator Update --}}
        <div class="hidden sm:inline-flex items-center px-3 py-1 rounded-md text-xs font-medium bg-yellow-50 text-yellow-700 border border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-400 dark:border-yellow-700">
            <i class="fas fa-clock mr-1.5 animate-pulse"></i> Auto-Update
        </div>
    </div>

    <div id="Matrix KDDK-content" class="tab-content" data-loaded="true">
        <div class="notification-container"></div>
        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-3 text-sm" role="alert">
                <p class="font-bold">Berhasil</p>
                <p>{{ session('success') }}</p>
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-3 text-sm" role="alert">
                <p class="font-bold">Error</p>
                <p>{{ session('error') }}</p>
            </div>
        @endif
    </div>

    {{-- TREEVIEW TABLE --}}
    <div class="border border-gray-300 dark:border-gray-700 rounded-lg shadow-sm">
        {{-- Menggunakan table-fixed agar layout stabil --}}
        <table class="w-full table-fixed divide-y divide-gray-300 dark:divide-gray-700">

            {{-- HEADER --}}
            <thead>
                <tr class="divide-x divide-gray-300 dark:divide-gray-600">
                    {{-- Definisi Lebar Kolom --}}
                    <th rowspan="2" class="w-[4%] bg-gray-100 dark:bg-gray-800"></th> {{-- Icon --}}

                    <th rowspan="2" class="w-[20%] px-3 py-3 text-left bg-gray-100 dark:bg-gray-800 align-middle">
                        <span class="text-xs font-extrabold text-gray-900 uppercase tracking-wide">Unit Layanan</span>
                    </th>

                    <th rowspan="2" class="w-[6%] px-1 py-3 text-center bg-gray-100 dark:bg-gray-800 align-middle">
                        <span class="text-xs font-bold text-gray-800 uppercase">Kode</span>
                    </th>

                    {{-- Grouping Header --}}
                    <th colspan="3" class="w-[18%] py-2 text-center bg-gray-200/80 dark:bg-gray-900 border-b border-gray-300">
                        <span class="text-xs font-bold text-gray-800 uppercase tracking-wide">Target DIL</span>
                    </th>
                    
                    <th colspan="3" class="w-[18%] py-2 text-center bg-indigo-100/50 dark:bg-indigo-900/30 border-b border-indigo-300">
                        <span class="text-xs font-bold text-indigo-800 uppercase tracking-wide">Realisasi KDDK</span>
                    </th>
                    
                    <th colspan="3" class="w-[28%] py-2 text-center bg-teal-100/50 dark:bg-teal-900/30 border-b border-teal-300">
                        <span class="text-xs font-bold text-teal-800 uppercase tracking-wide">Persentase (%)</span>
                    </th>

                    <th rowspan="2" class="w-[6%] px-1 py-3 bg-gray-100 dark:bg-gray-800 text-center align-middle">
                        <span class="text-xs font-bold text-gray-600 uppercase">Aksi</span>
                    </th>
                </tr>

                {{-- Sub-Header --}}
                <tr class="divide-x divide-gray-200 dark:divide-gray-600 text-[11px] uppercase font-bold tracking-tight leading-4">
                    {{-- Target --}}
                    <th class="py-2 text-center text-gray-600 bg-gray-50">Pra</th>
                    <th class="py-2 text-center text-gray-600 bg-gray-50">Pasca</th>
                    <th class="py-2 text-center text-black bg-gray-200">Total</th>

                    {{-- KDDK --}}
                    <th class="py-2 text-center text-indigo-700 bg-indigo-50">Pra</th>
                    <th class="py-2 text-center text-pink-700 bg-indigo-50">Pasca</th>
                    <th class="py-2 text-center text-indigo-900 bg-indigo-200">Total</th>

                    {{-- Persentase --}}
                    <th class="px-1 py-2 text-center text-indigo-700 bg-teal-50">% Pra (RPP)</th>
                    <th class="px-1 py-2 text-center text-pink-700 bg-teal-50">% Paska (RBM)</th>
                    <th class="px-1 py-2 text-center text-teal-900 bg-teal-100">% Tot</th>
                </tr>
            </thead>

            {{-- BODY --}}
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($matrixData as $up3Name => $ulps)
                    @php
                        // Hitung Data Angka
                        $up3TargetPra = $ulps->sum('target_prabayar');
                        $up3TargetPasca = $ulps->sum('target_pascabayar');
                        $up3TargetTotal = $ulps->sum('target_pelanggan');

                        $up3KddkPra = $ulps->sum('sudah_kddk_prabayar');
                        $up3KddkPasca = $ulps->sum('sudah_kddk_pascabayar');
                        $up3KddkTotal = $ulps->sum('sudah_kddk');

                        // Hitung Persentase
                        $up3PercPra   = $up3TargetPra > 0   ? ($up3KddkPra / $up3TargetPra) * 100 : 0;
                        $up3PercPasca = $up3TargetPasca > 0 ? ($up3KddkPasca / $up3TargetPasca) * 100 : 0;
                        $up3PercTotal = $up3TargetTotal > 0 ? ($up3KddkTotal / $up3TargetTotal) * 100 : 0;

                        $uniqueId = md5($up3Name);
                        $up3Code = $ulps->first()->parent_code ?? '?';
                    @endphp

                    {{-- BARIS UP3 (PARENT) --}}
                    <tr class="group hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors cursor-pointer border-b border-gray-300"
                        data-action="toggle-tree" data-target="{{ $uniqueId }}">

                        <td class="px-1 py-3 text-center bg-gray-50 align-middle">
                            <div class="w-6 h-6 mx-auto flex items-center justify-center rounded-full bg-white border border-gray-300 group-hover:bg-blue-50 transition">
                                <i class="fas fa-chevron-right text-xs text-gray-600 transition-transform duration-200 icon-arrow" id="icon-{{ $uniqueId }}"></i>
                            </div>
                        </td>

                        {{-- Nama Unit --}}
                        <td class="px-3 py-3 bg-gray-50 align-middle">
                            <div class="flex items-center">
                                <i class="fas fa-building text-blue-600 text-base mr-2 flex-shrink-0"></i>
                                <span class="text-xs font-extrabold text-gray-900 dark:text-white uppercase leading-snug whitespace-normal break-words">
                                    {{ $up3Name }}
                                </span>
                            </div>
                        </td>

                        <td class="px-1 py-3 text-center bg-gray-50 border-r border-gray-300 align-middle">
                            <span class="inline-block px-2 py-0.5 text-[11px] font-bold text-gray-700 bg-white rounded border border-gray-300">
                                {{ $up3Code }}
                            </span>
                        </td>

                        {{-- Data Target --}}
                        <td class="text-center text-xs font-semibold text-gray-600 border-l border-gray-200">{{ number_format($up3TargetPra) }}</td>
                        <td class="text-center text-xs font-semibold text-gray-600">{{ number_format($up3TargetPasca) }}</td>
                        <td class="text-center text-xs font-black text-black bg-gray-100 border-r border-gray-300">{{ number_format($up3TargetTotal) }}</td>

                        {{-- Data Realisasi --}}
                        <td class="text-center text-xs font-bold text-indigo-700">{{ number_format($up3KddkPra) }}</td>
                        <td class="text-center text-xs font-bold text-pink-600">{{ number_format($up3KddkPasca) }}</td>
                        <td class="text-center text-xs font-black text-indigo-900 bg-indigo-50 border-r border-indigo-200">{{ number_format($up3KddkTotal) }}</td>

                        {{-- Progress Bars --}}
                        <td class="px-2 py-3 align-middle border-l border-gray-200">
                             <div class="flex flex-col justify-center">
                                <div class="w-full h-1.5 bg-gray-200 rounded-full mb-1">
                                    <div class="h-1.5 bg-indigo-600 rounded-full" style="width: {{ min($up3PercPra, 100) }}%"></div>
                                </div>
                                <span class="text-[11px] font-bold text-indigo-700 text-right leading-none">{{ number_format($up3PercPra, 0) }}%</span>
                            </div>
                        </td>
                        <td class="px-2 py-3 align-middle">
                             <div class="flex flex-col justify-center">
                                <div class="w-full h-1.5 bg-gray-200 rounded-full mb-1">
                                    <div class="h-1.5 bg-pink-500 rounded-full" style="width: {{ min($up3PercPasca, 100) }}%"></div>
                                </div>
                                <span class="text-[11px] font-bold text-pink-600 text-right leading-none">{{ number_format($up3PercPasca, 0) }}%</span>
                            </div>
                        </td>
                         <td class="px-2 py-3 align-middle bg-teal-50 border-r border-teal-100">
                             <div class="flex flex-col justify-center">
                                <div class="w-full h-2 bg-gray-200 rounded-full mb-1 border border-gray-300">
                                    <div class="h-2 bg-teal-600 rounded-full" style="width: {{ min($up3PercTotal, 100) }}%"></div>
                                </div>
                                <span class="text-xs font-black text-teal-800 text-right leading-none">{{ number_format($up3PercTotal, 1) }}%</span>
                            </div>
                        </td>

                        <td class="bg-gray-50"></td>
                    </tr>

                    {{-- BARIS ULP (CHILD) --}}
                    @foreach ($ulps as $row)
                        @php
                            $percPra   = $row->target_prabayar > 0   ? ($row->sudah_kddk_prabayar / $row->target_prabayar) * 100 : 0;
                            $percPasca = $row->target_pascabayar > 0 ? ($row->sudah_kddk_pascabayar / $row->target_pascabayar) * 100 : 0;
                            $percTotal = $row->target_pelanggan > 0  ? ($row->sudah_kddk / $row->target_pelanggan) * 100 : 0;
                            $fullCode = ($row->unit_code ?? '?');
                        @endphp

                        <tr class="hidden tree-child-{{ $uniqueId }} bg-white hover:bg-yellow-50 dark:bg-gray-800 border-b border-gray-100 transition-colors cursor-pointer"
                            data-action="drill-down"
                            data-url="{{ route('team.matrix_kddk.details', ['unit' => $row->unit_code]) }}">

                            <td></td>
                            {{-- Unit Name --}}
                            <td class="px-3 py-3 pl-8 relative border-r-0 align-middle">
                                <div class="absolute left-4 top-0 bottom-1/2 w-4 border-l-2 border-b-2 border-gray-300 rounded-bl"></div>
                                <span class="text-xs font-bold text-gray-700 hover:text-indigo-700 transition-colors relative z-10 whitespace-normal break-words leading-tight block">
                                    {{ $row->unit_name }}
                                </span>
                            </td>

                            <td class="px-1 py-3 text-center border-r border-gray-200 align-middle">
                                <span class="text-[11px] font-mono font-medium text-gray-800">{{ $fullCode }}</span>
                            </td>

                            {{-- Target --}}
                            <td class="text-center text-xs font-medium text-gray-500 border-l border-gray-100">{{ number_format($row->target_prabayar) }}</td>
                            <td class="text-center text-xs font-medium text-gray-500">{{ number_format($row->target_pascabayar) }}</td>
                            <td class="text-center text-xs font-extrabold text-gray-800 bg-gray-50/80 border-r border-gray-200">{{ number_format($row->target_pelanggan) }}</td>

                            {{-- Realisasi --}}
                            <td class="text-center text-xs font-bold text-indigo-600">{{ number_format($row->sudah_kddk_prabayar) }}</td>
                            <td class="text-center text-xs font-bold text-pink-600">{{ number_format($row->sudah_kddk_pascabayar) }}</td>
                            <td class="text-center text-xs font-extrabold text-indigo-800 bg-indigo-50/50 border-r border-indigo-100">{{ number_format($row->sudah_kddk) }}</td>

                            {{-- Progress Bars ULP --}}
                            <td class="px-2 py-3 align-middle border-l border-gray-50">
                                <div class="flex items-center">
                                    <div class="flex-1 h-1.5 bg-gray-100 rounded-full mr-2">
                                        <div class="h-1.5 bg-indigo-500 rounded-full" style="width: {{ min($percPra, 100) }}%"></div>
                                    </div>
                                    <span class="text-[10px] text-gray-600 w-8 text-right leading-none">{{ number_format($percPra, 0) }}%</span>
                                </div>
                            </td>
                            <td class="px-2 py-3 align-middle">
                                <div class="flex items-center">
                                    <div class="flex-1 h-1.5 bg-gray-100 rounded-full mr-2">
                                        <div class="h-1.5 bg-pink-500 rounded-full" style="width: {{ min($percPasca, 100) }}%"></div>
                                    </div>
                                    <span class="text-[10px] text-gray-600 w-8 text-right leading-none">{{ number_format($percPasca, 0) }}%</span>
                                </div>
                            </td>
                             <td class="px-2 py-3 align-middle bg-teal-50/30 border-r border-teal-50">
                                <div class="flex items-center">
                                    <div class="flex-1 h-2 bg-gray-200 rounded-full mr-2 border border-gray-200">
                                        <div class="h-2 bg-teal-600 rounded-full" style="width: {{ min($percTotal, 100) }}%"></div>
                                    </div>
                                    <span class="text-[11px] font-bold text-teal-800 w-9 text-right leading-none">{{ number_format($percTotal, 0) }}%</span>
                                </div>
                            </td>

                            <td class="px-1 py-3 text-center align-middle">
                                <button type="button" data-action="manage-rbm"
                                    data-url="{{ route('team.matrix_kddk.rbm_manage', ['unit' => $row->unit_code]) }}"
                                    class="text-gray-400 hover:text-white hover:bg-indigo-600 p-1.5 rounded transition shadow-sm border border-transparent hover:border-indigo-700 focus:outline-none"
                                    title="Susun RBM">
                                    <i class="fas fa-arrow-right text-xs"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="13" class="text-center py-8">
                            <span class="text-sm text-gray-400">Belum ada data.</span>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>