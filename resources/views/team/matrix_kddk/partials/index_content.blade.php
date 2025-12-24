<div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
    {{-- HEADER SECTION --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 flex items-center">
                <i class="fas fa-sitemap mr-2 text-indigo-600"></i> Progress Pemetaan KDDK
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Rekapitulasi penyelesaian berdasarkan update data pelanggan aktif.
            </p>
            <div
                class="mt-2 inline-flex items-center px-3 py-1 rounded-md text-xs font-medium bg-yellow-50 text-yellow-700 border border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-400 dark:border-yellow-700">
                <i class="fas fa-clock mr-1.5 animate-pulse"></i>
                <span>Data diperbarui otomatis</span>
            </div>
        </div>
    </div>

    <div id="Matrix KDDK-content" class="tab-content" data-loaded="true">

        <div class="notification-container"></div>

        @if (session('success'))
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative"
                role="alert">
                <strong class="font-bold">Berhasil!</strong>
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

    </div>

    {{-- ... Bagian Header Tetap Sama ... --}}

    {{-- TREEVIEW TABLE --}}
    <div class="overflow-x-auto border border-gray-300 dark:border-gray-700 rounded-xl shadow-sm">
        <table class="min-w-max w-full divide-y divide-gray-300 dark:divide-gray-700">

            {{-- HEADER --}}
            {{-- HEADER --}}
            <thead>
                <tr class="divide-x divide-gray-300 dark:divide-gray-600">
                    <th rowspan="2" class="w-8 bg-gray-100 dark:bg-gray-800"></th> {{-- Perkecil w-10 jadi w-8 --}}

                    <th rowspan="2" class="px-3 py-3 text-left bg-gray-100 dark:bg-gray-800 align-middle">
                        {{-- px-4 jadi px-3 --}}
                        <span class="text-xs font-extrabold text-gray-800 uppercase tracking-wide">Unit Layanan</span>
                    </th>

                    <th rowspan="2" class="w-14 px-1 py-3 text-center bg-gray-100 dark:bg-gray-800 align-middle">
                        {{-- w-16 jadi w-14 --}}
                        <span class="text-xs font-bold text-gray-800 uppercase">Kode</span>
                    </th>

                    {{-- Grouping Header --}}
                    <th colspan="3"
                        class="px-1 py-2 text-center bg-gray-200/80 dark:bg-gray-900 border-b-2 border-gray-300">
                        <span class="text-xs font-bold text-gray-800 uppercase tracking-wide">Target (DIL)</span>
                    </th>
                    <th colspan="3"
                        class="px-1 py-2 text-center bg-indigo-100/50 dark:bg-indigo-900/30 border-b-2 border-indigo-300">
                        <span class="text-xs font-bold text-indigo-800 uppercase tracking-wide">Sudah KDDK</span>
                    </th>
                    <th colspan="3"
                        class="px-1 py-2 text-center bg-emerald-100/50 dark:bg-emerald-900/30 border-b-2 border-emerald-300">
                        <span class="text-xs font-bold text-emerald-800 uppercase tracking-wide">Progress</span>
                    </th>

                    <th rowspan="2" class="w-14 px-2 py-3 bg-gray-100 dark:bg-gray-800 text-center align-middle">
                        {{-- px-4 jadi px-2 --}}
                        <span class="text-xs font-bold text-gray-600 uppercase">Aksi</span>
                    </th>
                </tr>

                {{-- Sub-Header (Perkecil text jadi text-[10px] atau text-xs) --}}
                <tr
                    class="divide-x divide-gray-200 dark:divide-gray-600 text-[11px] uppercase font-bold tracking-tight">
                    {{-- Target --}}
                    <th class="px-1 py-2 text-center text-gray-600 bg-gray-50">Pra</th>
                    <th class="px-1 py-2 text-center text-gray-600 bg-gray-50">Pasca</th>
                    <th class="px-2 py-2 text-center text-black bg-gray-200">Total</th>

                    {{-- KDDK --}}
                    <th class="px-1 py-2 text-center text-indigo-700 bg-indigo-50">Pra</th>
                    <th class="px-1 py-2 text-center text-pink-700 bg-indigo-50">Pasca</th>
                    <th class="px-2 py-2 text-center text-indigo-900 bg-indigo-200">Total</th>

                    {{-- Progress --}}
                    <th class="px-1 py-2 text-center text-gray-600 bg-emerald-50">Survey</th>
                    <th class="px-1 py-2 text-center text-emerald-700 bg-emerald-50">Valid</th>
                    <th class="px-2 py-2 text-center text-black bg-emerald-50">%</th>
                </tr>
            </thead>

            {{-- BODY --}}
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($matrixData as $up3Name => $ulps)
                    @php
                        $up3TargetPra = $ulps->sum('target_prabayar');
                        $up3TargetPasca = $ulps->sum('target_pascabayar');
                        $up3TargetTotal = $ulps->sum('target_pelanggan');

                        $up3KddkPra = $ulps->sum('sudah_kddk_prabayar');
                        $up3KddkPasca = $ulps->sum('sudah_kddk_pascabayar');
                        $up3KddkTotal = $ulps->sum('sudah_kddk');

                        $up3Survey = $ulps->sum('realisasi_survey');
                        $up3Valid = $ulps->sum('valid');

                        $up3Perc = $up3TargetTotal > 0 ? ($up3KddkTotal / $up3TargetTotal) * 100 : 0;

                        $uniqueId = md5($up3Name);
                        $up3Code = $ulps->first()->kode_up3 ?? '?';
                    @endphp

                    {{-- BARIS UP3 (PARENT) --}}
                    <tr class="group hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors cursor-pointer border-b-2 border-gray-300"
                        data-action="toggle-tree" data-target="{{ $uniqueId }}">

                        <td class="px-2 py-4 text-center bg-gray-50">
                            <div
                                class="w-6 h-6 mx-auto flex items-center justify-center rounded-full bg-white border border-gray-300 group-hover:bg-blue-50 transition">
                                <i class="fas fa-chevron-right text-xs text-gray-600 transition-transform duration-200 icon-arrow"
                                    id="icon-{{ $uniqueId }}"></i>
                            </div>
                        </td>

                        <td class="px-4 py-4 whitespace-nowrap bg-gray-50">
                            <div class="flex items-center">
                                <i class="fas fa-building text-blue-600 text-lg mr-3"></i>
                                <span
                                    class="text-xs font-extrabold text-gray-900 dark:text-white uppercase">{{ $up3Name }}</span>
                            </div>
                        </td>

                        <td class="px-2 py-4 text-center bg-gray-50 border-r border-gray-300">
                            <span
                                class="inline-block px-2 py-1 text-xs font-bold text-gray-700 bg-white rounded border border-gray-300">
                                {{ $up3Code }}
                            </span>
                        </td>

                        {{-- Data Target --}}
                        <td
                            class="px-2 py-4 text-center text-xs font-semibold text-gray-600 tabular-nums border-l border-gray-200">
                            {{ number_format($up3TargetPra) }}</td>
                        <td class="px-2 py-4 text-center text-xs font-semibold text-gray-600 tabular-nums">
                            {{ number_format($up3TargetPasca) }}</td>
                        <td
                            class="px-2 py-4 text-center text-xs font-black text-black bg-gray-100 border-r border-gray-300 tabular-nums">
                            {{ number_format($up3TargetTotal) }}
                        </td>

                        {{-- Data Realisasi --}}
                        <td class="px-2 py-4 text-center text-xs font-bold text-indigo-700 tabular-nums">
                            {{ number_format($up3KddkPra) }}</td>
                        <td class="px-2 py-4 text-center text-xs font-bold text-pink-600 tabular-nums">
                            {{ number_format($up3KddkPasca) }}</td>
                        <td
                            class="px-2 py-4 text-center text-xs font-black text-indigo-900 bg-indigo-50 border-r border-indigo-200 tabular-nums">
                            {{ number_format($up3KddkTotal) }}
                        </td>

                        {{-- Data Progress --}}
                        <td class="px-2 py-4 text-center text-xs font-medium text-gray-600 tabular-nums">
                            {{ number_format($up3Survey) }}</td>
                        <td class="px-2 py-4 text-center text-xs font-bold text-emerald-700 tabular-nums">
                            {{ number_format($up3Valid) }}</td>
                        <td class="px-4 py-4 align-middle">
                            <div class="flex items-center justify-end space-x-2">
                                <div
                                    class="flex-1 h-2 w-16 bg-gray-200 rounded-full overflow-hidden border border-gray-300">
                                    <div class="h-full bg-blue-600 rounded-full" style="width: {{ $up3Perc }}%">
                                    </div>
                                </div>
                                <span
                                    class="text-xs font-bold text-gray-900 min-w-[40px] text-right">{{ number_format($up3Perc, 1) }}%</span>
                            </div>
                        </td>
                        <td class="bg-gray-50"></td>
                    </tr>

                    {{-- BARIS ULP (CHILD) --}}
                    @foreach ($ulps as $row)
                        @php
                            $percentage =
                                $row->target_pelanggan > 0 ? ($row->sudah_kddk / $row->target_pelanggan) * 100 : 0;
                            $fullCode = ($row->kode_up3 ?? '?') . ($row->kode_ulp ?? '?') . 'A';
                        @endphp

                        <tr class="hidden tree-child-{{ $uniqueId }} bg-white hover:bg-yellow-50 dark:bg-gray-800 border-b border-gray-100 transition-colors cursor-pointer"
                            data-action="drill-down"
                            data-url="{{ route('team.matrix_kddk.details', ['unit' => $row->unit_code]) }}">

                            <td></td>
                            <td class="px-4 py-3 whitespace-nowrap pl-12 relative border-r-0">
                                {{-- Garis Pohon --}}
                                <div
                                    class="absolute left-6 top-0 bottom-1/2 w-4 border-l-2 border-b-2 border-gray-300 rounded-bl">
                                </div>
                                <span
                                    class="text-xs font-bold text-gray-700 hover:text-indigo-700 transition-colors relative z-10">
                                    {{ $row->unit_layanan }}
                                </span>
                            </td>

                            <td class="px-2 py-3 text-center border-r border-gray-200">
                                <span class="text-xs font-mono font-medium text-gray-800">{{ $fullCode }}</span>
                            </td>

                            {{-- Data Target --}}
                            <td
                                class="px-2 py-3 text-center text-xs font-medium text-gray-500 tabular-nums border-l border-gray-100">
                                {{ number_format($row->target_prabayar) }}</td>
                            <td class="px-2 py-3 text-center text-xs font-medium text-gray-500 tabular-nums">
                                {{ number_format($row->target_pascabayar) }}</td>
                            <td
                                class="px-2 py-3 text-center text-xs font-extrabold text-gray-800 bg-gray-50/80 border-r border-gray-200 tabular-nums">
                                {{ number_format($row->target_pelanggan) }}
                            </td>

                            {{-- Data Realisasi --}}
                            <td class="px-2 py-3 text-center text-xs font-bold text-indigo-600 tabular-nums">
                                {{ number_format($row->sudah_kddk_prabayar) }}</td>
                            <td class="px-2 py-3 text-center text-xs font-bold text-pink-600 tabular-nums">
                                {{ number_format($row->sudah_kddk_pascabayar) }}</td>
                            <td
                                class="px-2 py-3 text-center text-xs font-extrabold text-indigo-800 bg-indigo-50/50 border-r border-indigo-100 tabular-nums">
                                {{ number_format($row->sudah_kddk) }}
                            </td>

                            {{-- Data Progress --}}
                            <td class="px-2 py-3 text-center text-xs text-gray-500 tabular-nums">
                                {{ number_format($row->realisasi_survey) }}</td>
                            <td class="px-2 py-3 text-center text-xs font-bold text-emerald-600 tabular-nums">
                                {{ number_format($row->valid) }}</td>
                            <td class="px-4 py-3 align-middle">
                                <div class="flex items-center justify-end space-x-2">
                                    <div
                                        class="flex-1 h-1.5 w-16 bg-gray-100 rounded-full overflow-hidden border border-gray-200">
                                        <div class="h-full bg-indigo-500 rounded-full"
                                            style="width: {{ $percentage }}%"></div>
                                    </div>
                                    <span
                                        class="text-xs font-bold {{ $percentage >= 100 ? 'text-emerald-700' : 'text-gray-600' }} min-w-[35px] text-right">
                                        {{ number_format($percentage, 1) }}%
                                    </span>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-center">
                                <button type="button" data-action="manage-rbm"
                                    data-url="{{ route('team.matrix_kddk.rbm_manage', ['unit' => $row->unit_code]) }}"
                                    class="text-gray-400 hover:text-white hover:bg-indigo-600 p-1.5 rounded-full transition shadow-sm border border-transparent hover:border-indigo-700 focus:outline-none"
                                    title="Susun RBM">
                                    <i class="fas fa-arrow-right text-xs"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="12" class="text-center py-12">
                            <div class="flex flex-col items-center justify-center text-gray-400">
                                <i class="fas fa-database text-4xl mb-3 opacity-30"></i>
                                <span class="text-sm font-medium">Belum ada data rekapitulasi.</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
