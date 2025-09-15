{{-- Konten untuk Halaman Monitor Queue --}}
<div class="pt-2 pb-0 px-4 sm:px-6 lg:px-8">
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight ml-4 sm:ml-6 lg:ml-8">
        Monitor Antrian (Queue)
    </h2>
    <hr class="border-gray-200 dark:border-gray-700 my-2">

    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg w-full p-6 mb-6">
        <h3 class="text-lg font-medium text-red-600 dark:text-red-400 mb-4">Pekerjaan Gagal ({{ $failedJobs->count() }})</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">ID</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Pekerjaan</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Waktu Gagal</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase">Error</th>
                        <th class="px-4 py-2 text-center text-xs font-medium uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($failedJobs as $job)
                    <tr>
                        <td class="px-4 py-2 whitespace-nowrap text-sm">{{ $job->id }}</td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm">{{ $job->displayName }}</td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm">{{ \Carbon\Carbon::parse($job->failed_at)->diffForHumans() }}</td>
                        <td class="px-4 py-2 text-sm text-red-500">
                            <details>
                                <summary class="cursor-pointer">Lihat Error</summary>
                                <pre class="mt-2 text-xs bg-gray-100 dark:bg-gray-900 p-2 rounded overflow-auto">{{ $job->exception }}</pre>
                            </details>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap text-sm text-center">
                            <button data-retry-url="{{ route('admin.queue.retry', $job->id) }}" class="text-green-600 hover:text-green-900">Retry</button>
                            <button data-delete-url="{{ route('admin.queue.delete', $job->id) }}" class="ml-2 text-red-600 hover:text-red-900">Hapus</button>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-2 text-center text-sm text-gray-500">Tidak ada pekerjaan yang gagal.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg w-full p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Pekerjaan Sedang Antri ({{ $pendingJobs->count() }})</h3>
        {{-- Tabel untuk pending jobs bisa ditambahkan di sini jika perlu --}}
        @if($pendingJobs->isEmpty())
            <p class="text-sm text-gray-500">Tidak ada pekerjaan dalam antrian.</p>
        @else
            <p class="text-sm text-gray-500">Ada {{ $pendingJobs->count() }} pekerjaan menunggu untuk diproses.</p>
        @endif
    </div>
</div>

<script>
// Logika AJAX untuk tombol Retry dan Hapus
(function() {
    const contentArea = document.getElementById('Monitor Antrian-content');
    if (!contentArea || contentArea.dataset.initialized) return;
    contentArea.dataset.initialized = 'true';

    contentArea.addEventListener('click', function(e) {
        const retryButton = e.target.closest('[data-retry-url]');
        const deleteButton = e.target.closest('[data-delete-url]');
        const actionUrl = retryButton ? retryButton.dataset.retryUrl : (deleteButton ? deleteButton.dataset.deleteUrl : null);
        const actionType = retryButton ? 'Retry' : 'Delete';
        
        if (!actionUrl) return;

        if (actionType === 'Delete' && !confirm('Apakah Anda yakin ingin menghapus pekerjaan ini secara permanen?')) return;
        
        fetch(actionUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            // Refresh tab monitor
            const monitorTabButton = document.querySelector('#tabs-header .tab-button[data-tab-name="Monitor Antrian"]');
            if (monitorTabButton) {
                loadTabContent('Monitor Antrian', monitorTabButton.dataset.url);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan.');
        });
    });
})();
</script>