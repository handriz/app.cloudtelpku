<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Manajemen Rute') }} - {{ $areaCode ?? '' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8" id="tabs-content">
            {{-- ID ini penting agar Tab Manager mengenali area ini sebagai konten tab --}}
            <div id="Rute [{{ $areaCode }}]-content" class="tab-content" data-loaded="true">
                @include('admin.settings.partials.routes_manage_content')
            </div>
        </div>
    </div>
</x-app-layout>