@foreach($items as $item)
    @php
        $hasChildren = $item->childrenRecursive->isNotEmpty();
        $rowId = $item->code;
        $parentId = $item->parent_code;
    @endphp

    {{-- 
        PERBAIKAN 1: Tambahkan 'duration-200 ease-in-out' pada TR 
        agar durasi transisinya konsisten 
    --}}
    <tr class="group hover:bg-indigo-50 dark:hover:bg-gray-700 transition-colors duration-200 ease-in-out border-b dark:border-gray-700 tree-row {{ $level > 0 ? 'hidden' : '' }}"
        data-id="{{ $rowId }}"
        data-parent="{{ $parentId ?? 'root' }}"
        data-level="{{ $level }}">
        
        {{-- KOLOM TIPE UNIT --}}
        <td class="px-6 py-4 whitespace-nowrap">
            @php
                $badgeColor = match($item->unit_type) {
                    'UP3' => 'bg-blue-100 text-blue-800 border-blue-200',
                    'ULP' => 'bg-green-100 text-green-800 border-green-200',
                    'SUB_ULP' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                    default => 'bg-gray-100 text-gray-800 border-gray-200'
                };
            @endphp
            <span class="px-2 py-1 text-xs font-bold rounded border {{ $badgeColor }}">
                {{ $item->unit_type ?? '-' }}
            </span>
        </td>

        {{-- KOLOM KODE UNIT --}}
        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-600 dark:text-gray-300">
            {{ $item->code }}
        </td>

        {{-- KOLOM KODE KDDK --}}
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex items-center" style="padding-left: {{ $level * 20 }}px;">
                @if($item->kddk_code)
                    <span class="h-8 w-8 rounded-full bg-white text-indigo-700 border-2 border-indigo-200 flex items-center justify-center font-bold shadow-sm relative z-10">
                    {{ $item->kddk_code }}
                    </span>
                @if($level > 0)
                        <span class="absolute border-b-2 border-gray-300 dark:border-gray-500 w-3" 
                              style="left: {{ ($level * 20) - 10 }}px; top: 50%;"></span>
                    @endif
                @else
                    <span class="text-gray-300 ml-2">-</span>
                @endif
            </div>
        </td>

        {{-- KOLOM NAMA UNIT --}}
        <td class="px-6 py-4 whitespace-nowrap relative">
            <div style="padding-left: {{ $level * 30 }}px;" class="flex items-center h-full relative">
                
                {{-- GARIS PENGHUBUNG --}}
                @if($level > 0)
                    <span class="absolute border-l-2 border-b-2 border-gray-300 dark:border-gray-500 rounded-bl-lg"
                          style="left: {{ ($level * 30) - 20 }}px; width: 20px; height: 42px; top: -23px;">
                    </span>
                @endif

                {{-- TOMBOL EXPAND/COLLAPSE --}}
                @if($hasChildren)
                    {{-- 
                        PERBAIKAN 2: Gunakan 'transition-all duration-200 ease-in-out' 
                        agar perubahan warnanya mulus bersamaan dengan TR 
                    --}}
                    <button type="button" 
                            class="tree-toggle-btn mr-2 text-gray-500 hover:text-indigo-600 focus:outline-none 
                                   transition-all duration-200 ease-in-out relative z-10 rounded-sm
                                   bg-white dark:bg-gray-800 
                                   group-hover:bg-indigo-50 dark:group-hover:bg-gray-700"
                            data-target="{{ $rowId }}"
                            data-state="closed">
                        <i class="far fa-plus-square text-lg"></i>
                    </button>
                @else
                    <span class="inline-flex items-center justify-center w-[18px] mr-2">
                        <span class="w-1.5 h-1.5 bg-gray-300 rounded-full"></span>
                    </span>
                @endif

                {{-- IKON FOLDER/UNIT --}}
                {{-- 
                    PERBAIKAN 3: Sama, tambahkan transition-all duration-200 
                --}}
                <i class="fas {{ $level == 0 ? 'fa-sitemap text-indigo-600' : ($hasChildren ? 'fa-building text-blue-500' : 'fa-map-marker-alt text-red-400') }} mr-2 relative z-10 
                          transition-all duration-200 ease-in-out
                          bg-white dark:bg-gray-800 
                          group-hover:bg-indigo-50 dark:group-hover:bg-gray-700 px-1"></i>
                
                <span class="text-sm font-bold text-gray-800 dark:text-gray-100 relative z-10">
                    {{ $item->name }}
                </span>
            </div>
        </td>

        {{-- KOLOM INDUK --}}
        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
            {{ $item->parent_code ?? 'ROOT' }}
        </td>

        {{-- KOLOM AKSI --}}
        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
            <a href="{{ route('admin.hierarchies.edit', $item->id) }}" class="text-indigo-600 hover:text-indigo-900 mr-3 transition-colors" data-modal-link>
                <i class="fas fa-edit"></i>
            </a>
            <button class="text-red-600 hover:text-red-900 focus:outline-none transition-colors" 
                    data-delete-url="{{ route('admin.hierarchies.destroy', $item->id) }}" 
                    data-user-name="{{ $item->name }}">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>

    {{-- REKURSIF --}}
    @if($hasChildren)
        @include('admin.hierarchies.partials._hierarchy_row', [
            'items' => $item->childrenRecursive, 
            'level' => $level + 1
        ])
    @endif
@endforeach