{{-- resources/views/admin/menu/partials/menu_row.blade.php --}}
<tr>
    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
        {{ $index }}
    </td>
    <td class="px-6 py-4 whitespace-nowrap">
        <span style="padding-left: {{ $level * 20 }}px;" class="text-sm font-medium text-gray-900 dark:text-gray-100">
            @if ($level > 0)
                <i class="fas fa-level-up-alt fa-rotate-90 mr-2 text-gray-500 dark:text-gray-400"></i>
            @endif
            {{ $menuItem->name }}
        </span>
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
        @if($menuItem->icon)
            <i class="{{ $menuItem->icon }} mr-2"></i> {{ $menuItem->icon }}
        @else
            -
        @endif
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
        @if($menuItem->route_name)
            <span class="font-semibold">Rute:</span> {{ $menuItem->route_name }}
        @elseif($menuItem->url)
            <span class="font-semibold">URL:</span> {{ $menuItem->url }}
        @else
            -
        @endif
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $menuItem->permission_name ?? '-' }}</td>
    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $menuItem->order }}</td>
    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
        <a href="{{ route('admin.menu.edit', $menuItem->id) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-500 mr-2">
            <i class="fas fa-edit"></i> Edit
        </a>
        <form action="{{ route('admin.menu.destroy', $menuItem->id) }}" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus item menu ini dan sub-menunya?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-500">
                <i class="fas fa-trash"></i> Hapus
            </button>
        </form>
    </td>
</tr>

@if ($menuItem->children->isNotEmpty())
    @foreach($menuItem->children as $childMenuItem)
        @include('admin.menu.partials.menu_row', ['menuItem' => $childMenuItem, 'level' => $level + 1, 'index' => $index . '.' . ($loop->index + 1)])
    @endforeach
@endif