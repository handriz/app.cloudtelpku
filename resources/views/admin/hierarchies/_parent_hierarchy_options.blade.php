{{-- resources/views/admin/hierarchies/_parent_hierarchy_options.blade.php --}}

{{--
    Konten file ini tidak lagi mendefinisikan fungsi PHP renderHierarchyParentOptions().
    Fungsi tersebut telah dipindahkan ke Blade Directive di AppServiceProvider.php
    untuk menghindari error "Cannot redeclare function()".

    View yang memanggil partial ini sekarang harus menggunakan Blade Directive:
    @renderHierarchyParentOptions($parentHierarchyLevels, null, 0, old('parent_code'), null)
    atau
    @renderHierarchyParentOptions($parentHierarchyLevels, null, 0, old('parent_code', $hierarchies->parent_code), $hierarchies->code)
--}}