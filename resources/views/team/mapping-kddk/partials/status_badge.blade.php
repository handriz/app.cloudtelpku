@php
    $badges = [
        'valid' => 'bg-green-100 text-green-700 border-green-200',
        'verified' => 'bg-blue-100 text-blue-700 border-blue-200',
        'superseded' => 'bg-gray-100 text-gray-600 border-gray-200',
        'recalled_1' => 'bg-orange-100 text-orange-700 border-orange-200',
        'rejected' => 'bg-red-100 text-red-700 border-red-200',
    ];

    $labels = [
        'valid' => 'Valid (Aktif)',
        'verified' => 'Terverifikasi',
        'superseded' => 'Non-Aktif',
        'recalled_1' => 'Ditarik',
        'rejected' => 'Ditolak'
    ];

    // Penanganan Null Safety
    $statusKey = $status ?? 'verified'; 
    $isEnabled = $enabled ?? false;

    $key = $statusKey;
    if ($isEnabled) $key = 'valid';

    $class = $badges[$key] ?? 'bg-gray-100 text-gray-600 border-gray-200';
    $label = $labels[$key] ?? ucfirst($statusKey);
@endphp

<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase tracking-wide {{ $class }}">
    @if($isEnabled) <i class="fas fa-check-circle mr-1.5 text-[8px]"></i> @endif
    {{ $label }}
</span>