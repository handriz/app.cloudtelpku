{{-- resources/views/admin/users/_hierarchy_options.blade.php --}}

@php
    // Fungsi bantuan untuk merender opsi anak-anak dari level hierarki tertentu dengan indentasi
    // @param Collection $allAvailableHierarchyLevels - Koleksi semua level hirarki yang *diizinkan* (sudah difilter oleh controller)
    // @param string|null $parentId - Kode parent dari level hierarki yang akan dirender anak-anaknya
    // @param int $level - Tingkat kedalaman hirarki untuk indentasi
    // @param string|null $selectedCode - Kode level hirarki yang saat ini terpilih
    function renderHierarchyChildrenOptions($allAvailableHierarchyLevels, $parentId = null, $level = 0, $selectedCode = null) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level); // 4 spasi per level

        // Filter item yang memiliki parent_code sesuai dengan $parentId
        // Penting: $allAvailableHierarchyLevels sudah difilter di controller, jadi kita hanya mencari anak dalam koleksi yang sudah diizinkan ini.
        $children = $allAvailableHierarchyLevels->where('parent_code', $parentId)->sortBy('order');

        foreach ($children as $levelItem) {
            $isSelected = ($selectedCode === $levelItem->code) ? 'selected' : '';
            echo "<option value=\"{$levelItem->code}\" {$isSelected}>{$indent}{$levelItem->name} ({$levelItem->code})</option>";

            // Rekursif untuk anak-anaknya
            renderHierarchyChildrenOptions($allAvailableHierarchyLevels, $levelItem->code, $level + 1, $selectedCode);
        }
    }
@endphp

@php
    // Pastikan variabel didefinisikan
    $hierarchyLevelsCollection = $hierarchyLevels ?? collect(); // Variabel dari controller (sudah difilter)
    $selectedHierarchyCode = $selectedCode ?? null; // Variabel dari view yang memanggil partial
    $loggedInUser = Auth::user();
@endphp

{{-- Render opsi --}}
@php
    if ($loggedInUser->hasRole('admin')) {
        // Admin bisa melihat semua, mulai dari level teratas (parent_code null)
        renderHierarchyChildrenOptions($hierarchyLevelsCollection, null, 0, $selectedHierarchyCode);
    } elseif ($loggedInUser->hasRole('tl_user')) {
        // Untuk TL User, kita perlu memastikan levelnya sendiri terdaftar sebagai opsi,
        // kemudian secara rekursif merender anak-anaknya.
        $tlUserHierarchyCode = $loggedInUser->hierarchy_level_code;
        $tlUserHierarchyLevel = $hierarchyLevelsCollection->where('code', $tlUserHierarchyCode)->first();

        if ($tlUserHierarchyLevel) {
            // Render level TL User itu sendiri
            $isSelected = ($selectedHierarchyCode === $tlUserHierarchyLevel->code) ? 'selected' : '';
            echo "<option value=\"{$tlUserHierarchyLevel->code}\" {$isSelected}>{$tlUserHierarchyLevel->name} ({$tlUserHierarchyLevel->code})</option>";

            // Kemudian render anak-anaknya, dimulai dengan indentasi level 1
            renderHierarchyChildrenOptions($hierarchyLevelsCollection, $tlUserHierarchyCode, 1, $selectedHierarchyCode);
        } else {
            // Fallback: Jika level hierarki TL User tidak ditemukan dalam koleksi yang diizinkan
            // Ini bisa terjadi jika hierarchy_level_code TL User tidak valid atau tidak ada dalam data.
            // Kita bisa tampilkan semua yang ada di koleksi, atau pesan error.
            // Untuk saat ini, kita akan merender apa pun yang ada (ini tidak ideal jika ada data anomali)
            foreach ($hierarchyLevelsCollection as $levelItem) {
                 $isSelected = ($selectedHierarchyCode === $levelItem->code) ? 'selected' : '';
                 echo "<option value=\"{$levelItem->code}\" {$isSelected}>{$levelItem->name} ({$levelItem->code})</option>";
            }
        }
    } else {
        // Untuk app_user dan peran lainnya, controller sudah memfilter hanya level hierarki yang relevan.
        // Cukup tampilkan semua yang ada di koleksi yang sudah difilter.
        foreach ($hierarchyLevelsCollection as $levelItem) {
            $isSelected = ($selectedHierarchyCode === $levelItem->code) ? 'selected' : '';
            echo "<option value=\"{$levelItem->code}\" {$isSelected}>{$levelItem->name} ({$levelItem->code})</option>";
        }
    }
@endphp


