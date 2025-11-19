{{-- 
  File: resources/views/team/validation_recap/partials/_repair_edit_form.blade.php
  Konten form edit perbaikan data (dimuat via AJAX).
--}}

<div class="mt-4 border-t dark:border-gray-700 pt-4">
    <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Hasil Ditemukan</h4>
    <p class="text-sm text-gray-500 mb-4">
        Data ditemukan di: <span class="font-medium text-indigo-500">{{ $sourceTable }}</span>.
        Silakan ubah data di bawah ini dan simpan.
    </p>

    {{-- Form ini ditangani oleh handleModalFormSubmit di tab-manager.js --}}
    <form id="repair-edit-form" 
          action="{{ route('team.validation_recap.repair.update') }}" 
          method="POST" 
          enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="source_table" value="{{ $sourceTable }}">
        <input type="hidden" name="item_id" value="{{ $item->id }}">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
            
            {{-- ================================================== --}}
            {{-- KOLOM KIRI: FOTO (INTERAKTIF GANTI FOTO) --}}
            {{-- ================================================== --}}
            <div class="space-y-6">
                
                {{-- FOTO KWH --}}
                <div>
                    <h5 class="font-medium text-gray-700 dark:text-gray-300 mb-2">Foto KWH</h5>
                    
                    {{-- Wrapper Relative untuk Overlay --}}
                    <div class="relative group w-full rounded-lg overflow-hidden shadow-md cursor-pointer" 
                         onclick="document.getElementById('input_foto_kwh').click()">
                        
                        {{-- Gambar Utama (Preview) --}}
                        @if ($item->foto_kwh)
                            <img id="preview_foto_kwh" src="{{ Storage::disk('public')->url($item->foto_kwh) }}" 
                                 alt="Foto KWH" class="w-full h-48 object-cover transition-opacity group-hover:opacity-75">
                        @else
                            <div id="placeholder_foto_kwh" class="h-48 flex items-center justify-center bg-gray-100 dark:bg-gray-700 text-gray-400 group-hover:opacity-75">
                                <i class="fas fa-camera fa-2x"></i>
                            </div>
                            <img id="preview_foto_kwh" src="" class="hidden w-full h-48 object-cover transition-opacity group-hover:opacity-75">
                        @endif

                        {{-- Overlay "Ganti Foto" --}}
                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-black bg-opacity-40">
                            <span class="px-3 py-1 bg-white text-gray-800 text-xs font-bold rounded-full shadow-lg">
                                <i class="fas fa-edit mr-1"></i> Ganti Foto
                            </span>
                        </div>
                    </div>
                    
                    {{-- Input File Tersembunyi (Memanggil window.previewImage) --}}
                    <input type="file" name="foto_kwh_new" id="input_foto_kwh" class="hidden" accept="image/*" 
                           onchange="previewImage(this, 'preview_foto_kwh', 'placeholder_foto_kwh')">
                    <p class="text-xs text-gray-400 mt-1 text-center">Klik gambar untuk mengganti.</p>
                </div>

                {{-- FOTO PERSIL --}}
                <div>
                    <h5 class="font-medium text-gray-700 dark:text-gray-300 mb-2">Foto Persil</h5>
                    
                    <div class="relative group w-full rounded-lg overflow-hidden shadow-md cursor-pointer" 
                         onclick="document.getElementById('input_foto_bangunan').click()">
                        
                        @if ($item->foto_bangunan)
                            <img id="preview_foto_bangunan" src="{{ Storage::disk('public')->url($item->foto_bangunan) }}" 
                                 alt="Foto Persil" class="w-full h-48 object-cover transition-opacity group-hover:opacity-75">
                        @else
                            <div id="placeholder_foto_bangunan" class="h-48 flex items-center justify-center bg-gray-100 dark:bg-gray-700 text-gray-400 group-hover:opacity-75">
                                <i class="fas fa-camera fa-2x"></i>
                            </div>
                            <img id="preview_foto_bangunan" src="" class="hidden w-full h-48 object-cover transition-opacity group-hover:opacity-75">
                        @endif

                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-black bg-opacity-40">
                            <span class="px-3 py-1 bg-white text-gray-800 text-xs font-bold rounded-full shadow-lg">
                                <i class="fas fa-edit mr-1"></i> Ganti Foto
                            </span>
                        </div>
                    </div>

                    <input type="file" name="foto_bangunan_new" id="input_foto_bangunan" class="hidden" accept="image/*" 
                           onchange="previewImage(this, 'preview_foto_bangunan', 'placeholder_foto_bangunan')">
                    <p class="text-xs text-gray-400 mt-1 text-center">Klik gambar untuk mengganti.</p>
                </div>
            </div>


            {{-- ================================================== --}}
            {{-- KOLOM KANAN: DATA PELANGGAN & SAMBUNGAN --}}
            {{-- ================================================== --}}
            <div class="space-y-4">
                
                {{-- Data Utama --}}
                <div>
                    <label for="idpel" class="block font-medium text-gray-700 dark:text-gray-300">IDPEL</label>
                    <input type="text" name="idpel" value="{{ $item->idpel }}" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                  bg-gray-100 text-gray-500 cursor-not-allowed 
                                  dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400"
                           readonly>
                    <p class="text-xs text-gray-400 mt-1">*IDPEL tidak dapat diubah.</p>
                </div>

                <div>
                    <label for="nokwhmeter" class="block font-medium text-gray-700 dark:text-gray-300">No. KWH Meter</label>
                    <input type="text" name="nokwhmeter" value="{{ $item->nokwhmeter ?? '' }}" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="latitudey" class="block font-medium text-gray-700 dark:text-gray-300">Latitude (Y)</label>
                        <input type="text" name="latitudey" value="{{ $item->latitudey ?? '' }}" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label for="longitudex" class="block font-medium text-gray-700 dark:text-gray-300">Longitude (X)</label>
                        <input type="text" name="longitudex" value="{{ $item->longitudex ?? '' }}" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    </div>
                </div>
                
                {{-- Data Sambungan (SR) --}}
                <div class="border-t dark:border-gray-700 pt-4 mt-4">
                    <h5 class="font-bold text-indigo-600 dark:text-indigo-400 mb-3 text-xs uppercase">
                        <i class="fas fa-plug mr-1"></i> Data Sambungan (SR)
                    </h5>

                    <div class="space-y-3">
                       <div>
<label for="sr" class="block font-medium text-gray-700 dark:text-gray-300">Tipe Sambungan (SR)</label>
                            <select name="sr" id="sr" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">-- Pilih Tipe --</option>
                                
                                {{-- Definisi Opsi Standar --}}
                                @php
                                    $standardOptions = ['Sambungan dari tiang', 'SR deret'];
                                    $currentValue = $item->sr ?? '';
                                    $isStandard = in_array($currentValue, $standardOptions);
                                @endphp

                                {{-- Tampilkan Opsi Standar --}}
                                @foreach($standardOptions as $option)
                                    <option value="{{ $option }}" {{ $currentValue == $option ? 'selected' : '' }}>
                                        {{ $option }} {{ $currentValue == $option ? '(Data Saat Ini)' : '' }}
                                    </option>
                                @endforeach

                                {{-- Jika data database TIDAK standar (format lama/typo), tampilkan sebagai opsi tambahan --}}
                                @if(!$isStandard && $currentValue !== '')
                                    <option value="{{ $currentValue }}" selected>
                                        {{ $currentValue }} (Data Saat Ini - Non Standar)
                                    </option>
                                @endif
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="latitudey_sr" class="block font-medium text-gray-700 dark:text-gray-300">Latitude SR</label>
                                <input type="text" name="latitudey_sr" value="{{ $item->latitudey_sr ?? '' }}" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                       placeholder="-0.xxxxx">
                            </div>
                            <div>
                                <label for="longitudex_sr" class="block font-medium text-gray-700 dark:text-gray-300">Longitude SR</label>
                                <input type="text" name="longitudex_sr" value="{{ $item->longitudex_sr ?? '' }}" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                       placeholder="101.xxxxx">
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="mt-6 flex justify-end pt-4 border-t dark:border-gray-700">
            <button type="submit" 
                    class="px-4 py-2 bg-green-600 text-white rounded-md font-semibold text-xs uppercase hover:bg-green-700 transition shadow-md">
                <i class="fas fa-save mr-1"></i> Simpan Perubahan
            </button>
        </div>
    </form>
</div>