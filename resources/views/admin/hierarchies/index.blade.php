<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Manajemen Hirarki') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8" id="hierarchies-content">
            {{-- Panggil Partial Content --}}
            @include('admin.hierarchies.partials.index_content')
        </div>
    </div>
</x-app-layout>