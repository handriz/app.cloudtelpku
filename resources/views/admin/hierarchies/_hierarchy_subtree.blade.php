{{-- resources/views/admin/hierarchies/_hierarchy_subtree.blade.php --}}

@foreach($hierarchyItems as $item)
    {{-- Atur warna latar belakang baris berdasarkan level untuk visualisasi yang lebih baik --}}
    {{-- Tambahkan data-hierarchy-code untuk induk, dan data-parent-code untuk anak-anak --}}
    <tr class="{{ $level % 2 == 0 ? 'bg-gray-50 dark:bg-gray-700' : 'bg-gray-100 dark:bg-gray-750' }}"
        data-hierarchy-code="{{ $item->code }}"
        @if($item->parent_code) data-parent-code="{{ $item->parent_code }}" style="display: none;" @endif>
        
        <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100" 
            style="padding-left: {{ 16 + ($level * 24) }}px;">
            
            @if($item->children->isNotEmpty())
                {{-- Tombol Expand/Collapse untuk item yang memiliki anak --}}
                <button type="button" class="hierarchy-toggle focus:outline-none text-gray-500 dark:text-gray-400 mr-1" data-target="{{ $item->code }}">
                    <i class="fas fa-caret-right hierarchy-icon-closed"></i>
                    <i class="fas fa-caret-down hierarchy-icon-open hidden"></i>
                </button>
            @else
                {{-- Placeholder untuk item tanpa anak agar sejajar dengan tombol toggle --}}
                <span class="mr-1" style="display: inline-block; width: 20px;"></span> 
            @endif

            {{-- Ikon level spesifik --}}
            @if($level == 0) {{-- Icon untuk level teratas --}}
                <i class="fas fa-sitemap mr-2 text-indigo-500"></i>
            @elseif($level == 1) {{-- Icon untuk level kedua (anak langsung) --}}
                <i class="far fa-dot-circle mr-2 text-blue-500 dark:text-blue-400"></i>
            @else {{-- Icon untuk sub-level selanjutnya --}}
                <i class="fas fa-minus-circle mr-2 text-green-500 dark:text-green-400"></i>
            @endif
            
            {{ $item->name }}
        </td>
        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
            {{ $item->code }}
        </td>
        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
            {{ $item->parent->name ?? '-' }}
        </td>
        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
            {{ $item->order }}
        </td>
        <td class="px-3 py-2 whitespace-nowrap text-sm">
            @if($item->is_active)
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100">Aktif</span>
            @else
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-100">Nonaktif</span>
            @endif
        </td>
        <td class="px-3 py-2 whitespace-nowrap text-center text-sm font-medium">
            @can('edit-hierarchy-level')
                <a href="{{ route('admin.hierarchies.edit', $item) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-500 mr-2">
                    <i class="fas fa-edit"></i> Edit
                </a>
            @endcan
            @can('delete-hierarchy-level')
                <button type="button"
                        onclick="confirmDeleteHierarchyLevel({{ $item->id }})"
                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-500">
                    <i class="fas fa-trash"></i> Hapus
                </button>
            @endcan
        </td>
    </tr>

    @if($item->children->isNotEmpty())
        @include('admin.hierarchies._hierarchy_subtree', ['hierarchyItems' => $item->children, 'level' => $level + 1])
    @endif
@endforeach