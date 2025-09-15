<x-app-layout>
    @include('admin.queue.partials.monitor_content', [
        'pendingJobs' => $pendingJobs,
        'failedJobs' => $failedJobs
    ])
</x-app-layout>