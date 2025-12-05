<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Pengaturan Aplikasi') }}
        </h2>
    </x-slot>

    <div class="py-12">
        {{-- ID Container untuk Tab Manager agar bisa melakukan reload konten di sini --}}
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8" id="settings-content">
            
            {{-- Panggil Konten Partial yang sudah kita buat sebelumnya --}}
            @include('admin.settings.partials.index_content')
            
        </div>
    </div>
</x-app-layout>