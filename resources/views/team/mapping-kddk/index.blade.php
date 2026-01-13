<x-app-layout>
    {{-- 
        CONTAINER UTAMA 
        Membungkus konten utama. ID ini berguna jika nanti Anda ingin 
        me-refresh seluruh halaman konten via AJAX tanpa reload sidebar/header.
    --}}
    <div id="mapping-kddk-main-content">
        @include('team.mapping-kddk.partials.index_content')
    </div>

    {{-- 
        [PENTING] CONTAINER MODAL (WADAH KOSONG)
        Ini adalah tempat di mana form 'create.blade.php' atau 'edit.blade.php' 
        akan disuntikkan oleh Javascript saat tombol ditekan.
        
        Tanpa elemen ini, Javascript akan error karena tidak menemukan target.
    --}}
    <div id="modal-container"></div>

</x-app-layout>