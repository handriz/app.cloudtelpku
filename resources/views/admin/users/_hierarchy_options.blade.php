{{-- resources/views/admin/users/_hierarchy_options.blade.php --}}

@php
    // Fungsi bantuan untuk merender opsi hirarki dengan indentasi
    // @param Collection $hierarchyLevels - Koleksi semua level hirarki
    // @param string|null $parentId - parent_code dari level induk saat ini
    // @param int $level - Tingkat kedalaman hirarki untuk indentasi
    // @param string|null $selectedCode - Kode level hirarki yang saat ini terpilih
    function renderHierarchyOptions($hierarchyLevels, $parentId = null, $level = 0, $selectedCode = null) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level); // 4 spasi per level
        
        foreach ($hierarchyLevels->where('parent_code', $parentId)->sortBy('order') as $levelItem) {
            $isSelected = ($selectedCode === $levelItem->code) ? 'selected' : '';
            echo "<option value=\"{$levelItem->code}\" {$isSelected}>{$indent}{$levelItem->name} ({$levelItem->code})</option>";
            
            // Rekursif untuk anak-anak
            renderHierarchyOptions($hierarchyLevels, $levelItem->code, $level + 1, $selectedCode);
        }
    }
@endphp

@php
    // Pastikan $hierarchyLevelsCollection dan $selectedHierarchyCode didefinisikan
    $hierarchyLevelsCollection = $hierarchyLevels ?? collect(); // Variabel dari controller
    $selectedHierarchyCode = $currentUserHierarchyCode ?? null; // Variabel dari view yang memanggil partial
@endphp

{{-- Render opsi --}}
@php
    renderHierarchyOptions($hierarchyLevelsCollection, null, 0, $selectedHierarchyCode);
@endphp