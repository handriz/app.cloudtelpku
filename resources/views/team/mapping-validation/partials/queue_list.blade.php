{{-- resources/views/team/mapping-validation/partials/queue_list.blade.php --}}

@foreach ($availableItems as $item)
    <a href="#" 
       data-validation-queue-id="{{ $item->id }}" 
       class="validation-queue-item p-2 rounded text-left w-full text-sm font-medium
               {{ $item->locked_by == Auth::id() ? 'bg-indigo-100 dark:bg-indigo-900' : 'bg-white dark:bg-gray-800' }}
               hover:bg-indigo-50 dark:hover:bg-indigo-800 transition-colors duration-150">
        
        @php
            $idpel = $item->idpel;
            $user = Auth::user();
            
            // Tentukan apakah user memiliki role 'admin' ATAU 'team'
            $isAuthorized = $user && ($user->hasRole('admin') || $user->hasRole('team'));
            
            if ($idpel && strlen($idpel) > 3 && !$isAuthorized) {
                // Sembunyikan 3 digit terakhir untuk user yang TIDAK diizinkan
                $maskedIdpel = substr($idpel, 0, -3) . '***';
            } else {
                // Tampilkan IDPEL penuh untuk user yang diizinkan (admin/team)
                $maskedIdpel = $idpel; 
            }
        @endphp
        
        <p class="font-bold text-lg" title="{{ $item->idpel }}">{{ $maskedIdpel }}</p> 
        
        <p class="text-xs text-gray-500">{{ $item->user_pendataan }}-{{ $item->objectid  }}</p>
    </a>
@endforeach