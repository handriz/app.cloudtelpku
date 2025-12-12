{{-- 
    FILE PARTIAL EDIT 
    Dipanggil via AJAX Modal oleh TabManager/MappingHandler 
--}}
<form id="edit-mapping-form" action="{{ route('team.mapping.update', $mapping->id) }}" method="POST" enctype="multipart/form-data" autocomplete="off" 
      data-success-redirect-tab="Mapping Pelanggan" 
      {{-- Redirect kembali ke index setelah simpan --}}
      data-success-redirect-url="{{ route('team.mapping.index') }}">
    
    @csrf
    @method('PUT') {{-- PENTING: Method PUT untuk Update --}}

    <div class="p-6 space-y-4">
        {{-- HEADER MODAL --}}
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                Edit Data Pelanggan
            </h2>
            {{-- Tombol Close (Data Attribute untuk JS menutup modal) --}}
            <button type="button" data-modal-close class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                 <i class="fas fa-times fa-lg"></i>
            </button>
        </div>
        <hr class="border-gray-200 dark:border-gray-700">

        {{-- AREA ERROR AJAX --}}
        <div id="edit-mapping-errors" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative"></div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            {{-- KOLOM KIRI: DATA --}}
            <div class="space-y-4">
                {{-- IDPEL (Read Only) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">ID Pelanggan</label>
                    <input type="text" value="{{ $mapping->idpel }}" readonly class="block w-full rounded-md border-gray-300 bg-gray-100 text-gray-500 shadow-sm cursor-not-allowed mt-1">
                </div>

                {{-- User --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">User Pendataan</label>
                    <input type="text" value="{{ $mapping->user_pendataan }}" readonly class="block w-full rounded-md border-gray-300 bg-gray-100 text-gray-500 shadow-sm mt-1">
                </div>

                {{-- Latitude --}}
                <div>
                    <label for="latitudey_edit" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Latitude (Y)</label>
                    <input type="text" name="latitudey" id="latitudey_edit" value="{{ $mapping->latitudey }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                </div>
                
                {{-- Longitude --}}
                <div>
                    <label for="longitudex_edit" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Longitude (X)</label>
                    <input type="text" name="longitudex" id="longitudex_edit" value="{{ $mapping->longitudex }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                </div>
            </div>

            {{-- KOLOM KANAN: FOTO (Preview Lama & Input Baru) --}}
            <div class="space-y-4">
                
                {{-- Foto KWH --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Foto KWH Meter</label>
                    @if($mapping->foto_kwh)
                        <div class="mb-2 relative w-full h-32 bg-gray-100 rounded-lg overflow-hidden border border-gray-200 group">
                            <img src="{{ Storage::disk('public')->url($mapping->foto_kwh) }}?t={{ time() }}" class="w-full h-full object-cover">
                            <div class="absolute bottom-0 left-0 right-0 bg-black/50 text-white text-[10px] px-2 py-1 text-center">Foto Saat Ini</div>
                        </div>
                    @endif
                    <input type="file" name="foto_kwh_input" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <p class="text-[10px] text-gray-400 mt-1">*Upload baru untuk mengganti.</p>
                </div>

                {{-- Foto Bangunan --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Foto Bangunan</label>
                    @if($mapping->foto_bangunan)
                        <div class="mb-2 relative w-full h-32 bg-gray-100 rounded-lg overflow-hidden border border-gray-200 group">
                            <img src="{{ Storage::disk('public')->url($mapping->foto_bangunan) }}?t={{ time() }}" class="w-full h-full object-cover">
                            <div class="absolute bottom-0 left-0 right-0 bg-black/50 text-white text-[10px] px-2 py-1 text-center">Foto Saat Ini</div>
                        </div>
                    @endif
                    <input type="file" name="foto_bangunan_input" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                </div>
            </div>
        </div>

        {{-- KETERANGAN --}}
        <div>
            <label for="ket_survey_edit" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Keterangan Survey</label>
            <textarea name="ket_survey" id="ket_survey_edit" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>{{ $mapping->ket_survey }}</textarea>
        </div>

        <hr class="border-gray-200 dark:border-gray-700">
        
        {{-- FOOTER TOMBOL --}}
        <div class="flex justify-end space-x-2">
            <button type="button" data-modal-close class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 text-sm font-bold">Batal</button>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-bold shadow-lg">Simpan Perubahan</button>
        </div>
    </div>
</form>