<div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Waktu</th>
                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">User</th>
                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Aktivitas</th>
                <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Detail</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
            @forelse($logs as $log)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                    <td class="px-4 py-2 whitespace-nowrap text-gray-500 text-xs">
                        {{ $log->created_at->format('d M H:i') }}
                        <div class="text-[10px] text-gray-400">{{ $log->created_at->diffForHumans() }}</div>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap font-medium text-gray-900 dark:text-white text-xs">
                        {{ $log->user->name ?? 'System' }}
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold 
                            {{ str_contains($log->action_type, 'REMOVE') ? 'bg-red-100 text-red-800' : 
                              (str_contains($log->action_type, 'MOVE') ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800') }}">
                            {{ $log->action_type }}
                        </span>
                    </td>
                    <td class="px-4 py-2 text-gray-600 dark:text-gray-300 text-xs break-all max-w-xs">
                        {{ $log->description }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                        Belum ada riwayat aktivitas.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>