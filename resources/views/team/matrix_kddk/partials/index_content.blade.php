{{-- 
  File: resources/views/team/matrix_kddk/partials/index_content.blade.php
  Konten ini dimuat via AJAX.
--}}

<div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-hidden">
    <div class="p-6 text-gray-900 dark:text-gray-100">
        
        <h3 class="text-lg font-bold mb-4">Progress per Unit Layanan (ULP)</h3>

        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border border-gray-300 dark:border-gray-600">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase border border-gray-300 dark:border-gray-600">Unit Layanan</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase border border-gray-300 dark:border-gray-600">Total Pelanggan</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase border border-gray-300 dark:border-gray-600">Sudah Mapping</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase border border-gray-300 dark:border-gray-600">Valid (Disetujui)</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase border border-gray-300 dark:border-gray-600">Ditolak</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase border border-gray-300 dark:border-gray-600">% Progress</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800">
                    @forelse ($matrixData as $row)
                        @php
                            $percentage = $row->total_pelanggan > 0 ? ($row->sudah_valid / $row->total_pelanggan) * 100 : 0;
                        @endphp
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600">
                                {{ $row->unit_layanan ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500 border border-gray-300 dark:border-gray-600">
                                {{ number_format($row->total_pelanggan) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-blue-600 font-bold border border-gray-300 dark:border-gray-600">
                                {{ number_format($row->sudah_di_mapping) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-green-600 font-bold border border-gray-300 dark:border-gray-600">
                                {{ number_format($row->sudah_valid) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-red-600 border border-gray-300 dark:border-gray-600">
                                {{ number_format($row->ditolak) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center border border-gray-300 dark:border-gray-600">
                                <div class="flex items-center justify-center">
                                    <span class="mr-2">{{ number_format($percentage, 1) }}%</span>
                                    <div class="w-16 bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                        <div class="bg-green-600 h-2.5 rounded-full" style="width: {{ $percentage }}%"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500 border border-gray-300 dark:border-gray-600">
                                Belum ada data matrix.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</div>