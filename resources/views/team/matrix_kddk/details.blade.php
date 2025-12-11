<x-app-layout>
    
    {{-- Slot Header (Opsional, biasanya ada di layout Breeze) --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Detail Pekerjaan') }}
        </h2>
    </x-slot>

    {{-- Slot Utama (Pengganti @section('content')) --}}
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Container Tab System --}}
            <div id="tabs-content">
                
                {{-- Area Konten --}}
                <div id="Detail Pelanggan-content" class="tab-content" data-loaded="true">
                    
                    {{-- PANGGIL FILE TEKNIS YANG SUDAH ADA --}}
                    @include('team.matrix_kddk.partials.detail_content')
                    
                </div>

            </div>

        </div>
    </div>

</x-app-layout>