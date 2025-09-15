{{-- resources/views/admin/users/partials/_hierarchy_options.blade.php --}}

@foreach ($hierarchyLevels->where('parent_code', $parentCode)->sortBy('order') as $levelItem)
    <option value="{{ $levelItem->code }}" 
            {{ ($levelItem->code == $selectedCode) ? 'selected' : '' }}>
        {{ str_repeat('-- ', $level) }} {{ $levelItem->name }} ({{ $levelItem->code }})
    </option>

    @if($hierarchyLevels->where('parent_code', $levelItem->code)->count() > 0)
        @include('admin.users.partials._hierarchy_options', [
            'hierarchyLevels' => $hierarchyLevels,
            'parentCode' => $levelItem->code,
            'selectedCode' => $selectedCode,
            'level' => $level + 1
        ])
    @endif
@endforeach