<div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6">
    {{-- HEADER SECTION --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 flex items-center">
                <i class="fas fa-sitemap mr-2 text-indigo-600"></i> Matrix Progress Pemetaan KDDK
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Rekapitulasi penyelesaian berdasarkan Master Data Pelanggan Aktif.
            </p>
        </div>

        @if(isset($activePeriod))
            <div class="flex items-center space-x-2 bg-indigo-50 dark:bg-indigo-900/30 px-3 py-1.5 rounded-full border border-indigo-100 dark:border-indigo-800">
                <i class="far fa-calendar-alt text-indigo-600 dark:text-indigo-400"></i>
                <span class="text-sm font-semibold text-indigo-700 dark:text-indigo-300">
                    {{ \Carbon\Carbon::createFromFormat('Y-m', $activePeriod)->translatedFormat('F Y') }}
                </span>
            </div>
        @endif
    </div>

    {{-- TREEVIEW TABLE --}}
    <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="w-10 px-4 py-3"></th> {{-- Toggle Icon --}}
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Unit Layanan</th>
                    {{-- PERUBAHAN: Header diganti jadi KDDK --}}
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">KDDK</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Target (DIL)</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Realisasi</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-green-600 uppercase tracking-wider">Valid</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-red-600 uppercase tracking-wider">Ditolak</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Progress</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($matrixData as $up3Name => $ulps)
                    @php
                        // Aggregasi Data untuk Baris Induk (UP3)
                        $up3Target = $ulps->sum('target_pelanggan');
                        $up3Real = $ulps->sum('realisasi');
                        $up3Valid = $ulps->sum('valid');
                        $up3Reject = $ulps->sum('ditolak');
                        $up3Perc = $up3Target > 0 ? ($up3Real / $up3Target) * 100 : 0;
                        
                        $uniqueId = md5($up3Name);
                        // Kode UP3 (Digit ke-1)
                        $up3Code = $ulps->first()->kode_up3 ?? '?';
                    @endphp

                    {{-- 1. BARIS INDUK (UP3) --}}
                    <tr class="bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer transition-colors"
                        data-action="toggle-tree" 
                        data-target="{{ $uniqueId }}">
                        
                        {{-- Icon Toggle --}}
                        <td class="px-4 py-4 text-center">
                            <i class="fas fa-chevron-right text-gray-500 transition-transform duration-200 icon-arrow" id="icon-{{ $uniqueId }}"></i>
                        </td>
                        
                        {{-- Nama UP3 --}}
                        <td class="px-4 py-4 whitespace-nowrap font-bold text-gray-800 dark:text-white">
                            <i class="fas fa-building text-blue-500 mr-2"></i> {{ $up3Name }}
                        </td>
                        
                        {{-- Kode 1 Digit --}}
                        <td class="px-4 py-4 text-center font-mono">
                            <span class="bg-blue-100 text-blue-800 text-xs font-bold px-2 py-1 rounded border border-blue-200">
                                {{ $up3Code }}..
                            </span>
                        </td>
                        
                        {{-- Data Agregat UP3 --}}
                        <td class="px-4 py-4 text-center text-sm font-bold">{{ number_format($up3Target) }}</td>
                        <td class="px-4 py-4 text-center text-sm font-bold text-gray-600 dark:text-gray-300">{{ number_format($up3Real) }}</td>
                        <td class="px-4 py-4 text-center text-sm font-bold text-green-600">{{ number_format($up3Valid) }}</td>
                        <td class="px-4 py-4 text-center text-sm font-bold text-red-600">{{ number_format($up3Reject) }}</td>
                        
                        {{-- Progress Bar UP3 --}}
                        <td class="px-4 py-4 align-middle">
                            <div class="flex items-center">
                                <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-600 mr-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $up3Perc }}%"></div>
                                </div>
                                <span class="text-xs font-bold">{{ number_format($up3Perc, 1) }}%</span>
                            </div>
                        </td>
                        <td></td>
                    </tr>

                    {{-- 2. BARIS ANAK (ULP) - HIDDEN BY DEFAULT --}}
                    @foreach($ulps as $row)
                        @php
                            $percentage = $row->target_pelanggan > 0 ? ($row->realisasi / $row->target_pelanggan) * 100 : 0;
                            // Konstruksi 3 Digit KDDK: UP3 + ULP + A
                            $fullCode = ($row->kode_up3 ?? '?') . ($row->kode_ulp ?? '?') . 'A';
                        @endphp
                        
                        {{-- Perhatikan penggunaan unit_code pada data-url --}}
                        <tr class="hidden tree-child-{{ $uniqueId }} bg-white dark:bg-gray-800 hover:bg-indigo-50 dark:hover:bg-gray-900 border-b dark:border-gray-700 cursor-pointer transition-colors group"
                            data-action="drill-down"
                            data-url="{{ route('team.matrix_kddk.details', ['unit' => $row->unit_code]) }}">
                            
                            <td></td> {{-- Spacer Indentasi --}}
                            
                            {{-- Nama ULP (Indented) --}}
                            <td class="px-4 py-3 whitespace-nowrap pl-8 relative">
                                {{-- Garis L Connector --}}
                                <span class="absolute left-6 top-0 bottom-1/2 border-l-2 border-b-2 border-gray-300 w-4 h-full rounded-bl-sm"></span>
                                <span class="text-sm font-medium text-indigo-600 dark:text-indigo-400 group-hover:underline relative z-10">
                                    {{ $row->unit_layanan }}
                                </span>
                            </td>

                            {{-- Kode 3 Digit --}}
                            <td class="px-4 py-3 text-center">
                                <span class="font-mono font-bold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded border border-gray-200">
                                    {{ $fullCode }}
                                </span>
                            </td>

                            {{-- Data Statistik ULP --}}
                            <td class="px-4 py-3 text-center text-sm font-medium">{{ number_format($row->target_pelanggan) }}</td>
                            <td class="px-4 py-3 text-center text-sm text-gray-500">{{ number_format($row->realisasi) }}</td>
                            <td class="px-4 py-3 text-center text-xs font-bold text-green-600">{{ number_format($row->valid) }}</td>
                            <td class="px-4 py-3 text-center text-xs font-bold text-red-600">{{ number_format($row->ditolak) }}</td>
                            
                            {{-- Progress Bar ULP --}}
                            <td class="px-4 py-3 align-middle">
                                <div class="w-full bg-gray-200 rounded-full h-1.5 dark:bg-gray-700">
                                    <div class="bg-indigo-500 h-1.5 rounded-full" style="width: {{ $percentage }}%"></div>
                                </div>
                                <div class="text-[10px] text-center mt-1 text-gray-500">{{ number_format($percentage, 1) }}%</div>
                            </td>

                            {{-- Tombol Aksi (Kelola RBM) --}}
                            <td class="px-4 py-3 text-center">
                                <button type="button"
                                        data-action="manage-rbm"
                                        data-url="{{ route('team.matrix_kddk.rbm_manage', ['unit' => $row->unit_code]) }}"
                                        class="text-gray-400 hover:text-indigo-600 p-1.5 rounded-full hover:bg-gray-100 transition focus:outline-none" 
                                        title="Managemen KDDK">
                                    <i class="fas fa-cogs fa-lg"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach

                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            <div class="flex flex-col items-center justify-center">
                                <i class="fas fa-database text-4xl mb-4 text-gray-300 dark:text-gray-600"></i>
                                <p class="text-lg font-medium text-gray-900 dark:text-gray-200">
                                    Belum ada data Master Pelanggan.
                                </p>
                                <p class="text-sm mt-1">
                                    Silakan upload master data untuk periode ini agar matrix muncul.
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>