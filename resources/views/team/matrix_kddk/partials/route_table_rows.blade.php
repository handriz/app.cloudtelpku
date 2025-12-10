@foreach($data as $c)
    @php 
        $seq = substr($c->kddk, 7, 3); 
        $routePrefix = substr($c->kddk, 0, 7);

        // [LOGIKA CEK KOORDINAT]
        // Anggap tidak valid jika: Null, Kosong, atau '0'
        $hasCoord = !empty($c->latitudey) && !empty($c->longitudex) && 
                    $c->latitudey != '0' && $c->longitudex != '0';

        // Tentukan Class CSS Baris
        $rowClass = $hasCoord 
            ? 'hover:bg-yellow-50 dark:hover:bg-gray-700' 
            : 'bg-red-100 hover:bg-red-200 dark:bg-red-900/40 dark:hover:bg-red-900/60 cursor-not-allowed';
            
        // Warna Teks Sequence
        $textClass = $hasCoord ? 'text-indigo-600' : 'text-red-700 font-extrabold';

        // [BARU] Tentukan Pesan Tooltip
        $rowTitle = $hasCoord ? "IDPEL: {$c->idpel}" : "PERINGATAN: Koordinat Null / Belum Ada Titik Peta";
    @endphp
    
    {{-- Tambahkan atribut title="{{ $rowTitle }}" di sini --}}
    <tr class="draggable-idpel transition cursor-move group animate-fade-in {{ $rowClass }}" 
        draggable="true" 
        data-idpel="{{ $c->idpel }}" 
        data-origin-prefix="{{ $routePrefix }}"
        title="{{ $rowTitle }}">
        
        {{-- CHECKBOX --}}
        <td class="py-1 pl-2 text-center">
            <input type="checkbox" value="{{ $c->idpel }}" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 select-item-row h-3 w-3">
        </td>

        {{-- DRAG HANDLE --}}
        <td class="py-1 pl-1 w-4 text-center text-gray-300 group-hover:text-indigo-400">
            <i class="fas fa-grip-vertical text-[8px]"></i>
        </td>
        
        {{-- DATA SEQUENCE --}}
        <td class="py-1 w-8 font-mono font-bold {{ $textClass }}">{{ $seq }}</td>
        
        {{-- IDPEL --}}
        <td class="py-1 px-1 font-bold text-gray-700 dark:text-gray-200">
            {{ $c->idpel }}
            
            {{-- Ikon Peringatan jika Koordinat Hilang --}}
            @if(!$hasCoord)
                <i class="fas fa-map-marker-slash text-red-500 text-[9px] ml-1"></i>
            @endif
        </td>
        
        {{-- METER --}}
        <td class="py-1 px-1 truncate max-w-[100px]">
            {{ Str::limit($c->nomor_meter_kwh, 15) }}
        </td>
        
        {{-- TOMBOL HAPUS --}}
        <td class="py-1 text-center">
            <button type="button" class="text-gray-300 hover:text-red-600 transition btn-remove-customer p-1 rounded-full hover:bg-red-50 dark:hover:bg-red-900/30" 
                    title="Keluarkan dari Grup"
                    data-idpel="{{ $c->idpel }}">
                <i class="fas fa-trash-alt text-[10px]"></i>
            </button>
        </td>
    </tr>
@endforeach