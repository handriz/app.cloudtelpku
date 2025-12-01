<script>
    // Kita definisikan variabel global agar tab-manager.js bisa membacanya
    window.currentUserRole = "{{ Auth::user()->role->name ?? 'appuser' }}";
    window.googleMapsApiKey = "{{ env('VITE_GOOGLE_MAPS_API_KEY') }}";
</script>
<div id="interactive-validation-container" class="space-y-6">

    {{-- ====================================================== --}}
    {{-- BAGIAN NOTIFIKASI --}}
    {{-- ====================================================== --}}
    <div>
        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Berhasil!</strong><span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Error!</strong><span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif
    </div>

    {{-- ====================================================== --}}
    {{-- BAGIAN ATAS: ANTRIAN (DAFTAR RANDOM) --}}
    {{-- ====================================================== --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="p-4 border-b dark:border-gray-700 flex justify-between items-center">
            
            {{-- SISI KIRI: Tombol Upload (baru) dan Judul Teks --}}
            <div class="flex items-center gap-4">
                {{-- TOMBOL UPLOAD (POSISI BARU & AMAN) --}}
                @can('upload-mapping-data')
                <a href="{{ route('team.mapping_validation.upload.form') }}" class="inline-flex items-center px-4 py-2 bg-green-600 border rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700" data-modal-link="true">
                    <i class="fas fa-file-csv mr-2"></i><span>Upload</span>
                </a>
                @endcan
                @can('upload-mapping-data')
                <a href="{{ route('team.mapping_validation.upload.photos.form') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700" data-modal-link="true">
                    <i class="fas fa-images mr-2"></i><span>Upload Foto</span>
                </a>
                @endcan
                {{-- Judul Teks --}}
                <div>
                    <h3 class="font-semibold text-lg">Pilih Data Untuk Divalidasi</h3>
                    <p class="text-sm text-gray-500">
                        Menampilkan <span id="displayed-count">{{ $availableItems->count() }}</span> item dari total <span id="total-available-count">{{ $totalAvailable }}</span> yang tersedia.
                    </p>
                </div>
            </div>

            {{-- SISI KANAN: Tombol Refresh --}}
            <div class="flex-shrink-0">
                <button id="refresh-queue-list" type="button" class="px-3 py-1 bg-gray-200 dark:bg-gray-600 rounded text-sm hover:bg-gray-300">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh List
                </button>
            </div>

        </div>

        <div class="max-h-[25vh] overflow-y-auto p-4 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2" id="validation-queue-list">
            {{-- Daftar antrian dimuat dari partial terpisah --}}
            @include('team.mapping-validation.partials.queue_list', ['availableItems' => $availableItems])
        </div>
    </div>

    {{-- ====================================================== --}}
    {{-- BAGIAN BAWAH: PANEL DETAIL VALIDASI --}}
    {{-- ====================================================== --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        {{-- Placeholder (Default) --}}
        <div id="validation-placeholder" class="p-10 flex flex-col items-center justify-center min-h-[400px]">
            <i class="fas fa-mouse-pointer text-5xl text-gray-300"></i>
            <p class="mt-4 font-semibold text-gray-500">Pilih IDPEL dari daftar di atas</p>
            <p class="text-sm text-gray-400">Pilih salah satu data untuk memulai validasi.</p>
        </div>

        {{-- Loading Spinner --}}
        <div id="validation-loading" class="p-10 flex items-center justify-center min-h-[400px] hidden">
            <i class="fas fa-spinner fa-spin text-5xl text-indigo-500"></i>
        </div>

        {{-- Konten Detail (Awalnya tersembunyi) --}}
        <div id="validation-content" class="hidden">
            
            {{-- ====================================================== --}}
            {{-- BAGIAN HEADER (Informasi) --}}
            {{-- ====================================================== --}}
            <div class="p-5 border-b dark:border-gray-700">
                {{-- Baris 1: IDPEL (Judul Utama) --}}
                <h3 id="detail-idpel" class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-3"></h3>
                
                {{-- Baris 2: Informasi Detail Survey (Grid 4 Kolom) --}}
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 text-sm">
                    
                    {{-- Kolom 1: Didata oleh (1 Span) --}}
                    <div class="lg:col-span-2">
                        <p class="text-gray-500 font-semibold mb-1">Didata oleh:</p>
                        <p id="detail-user" class="text-gray-700 dark:text-gray-300 font-semibold"></p>
                    </div>

                    {{-- Kolom 2: Keterangan Survey (3 Span) --}}
                    <div class="lg:col-span-3">
                        <p class="text-gray-500 font-semibold mb-1">Keterangan Survey:</p>
                        <p id="detail-keterangan" class="text-gray-700 dark:text-gray-300 font-semibold"></p>
                    </div>
                </div>
            </div>

            {{-- ====================================================== --}}
            {{-- BAGIAN ALERT (Riwayat Penolakan) --}}
            {{-- ====================================================== --}}
            @include('team.mapping-validation.partials._rejection_history_alert')

            {{-- ====================================================== --}}
            {{-- BAGIAN KONTEN UTAMA (Peta, Foto, Form) --}}
            {{-- ====================================================== --}}
            <div class="p-5">
                 <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
                     
                     {{-- SEL 1: PETA, FOTO PERSIL, & INPUT VALIDASI PETA/PERSIL --}}
                    <div class="lg:col-span-3 space-y-4">
                        
                        {{-- 1. POSISI KOORDINAT --}}
                        <div class="space-y-2">
                            <h4 class="font-semibold">Posisi Koordinat</h4>
                            <div id="validation-map" class="w-full h-80 lg:h-[300px] rounded-lg z-0 bg-gray-200" style="height: 300px;"></div>
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-2 text-sm flex items-center justify-between mt-2">
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Koordinat:</span>
                                    <span id="validation-lat-lon" class="font-bold text-indigo-600 dark:text-indigo-400">Memuat...</span>
                                </div>
                                <a id="validation-street-view-link" href="#" rel="noopener noreferrer" title="Buka Google Street View" class="text-blue-500 hover:text-blue-700 hidden"> <i class="fas fa-street-view fa-lg"></i></a>
                            </div>
                        </div>

                        {{-- 2. VALIDASI PETA (LEBAR PENUH) --}}
                        <div class="grid grid-cols-1 gap-4 border-t dark:border-gray-700 pt-4">
                            {{-- BLOK VALIDASI PETA (Mengambil lebar penuh baris) --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Apakah posisi peta sudah sesuai?</label>
                                <div class="mt-2 space-x-4">
                                    <label class="inline-flex items-center"><input type="radio" name="eval_peta" value="sesuai" class="eval-radio text-indigo-600 focus:ring-indigo-500"><span class="ml-2 text-sm">Sesuai</span></label>
                                    <label class="inline-flex items-center"><input type="radio" name="eval_peta" value="tidak" class="eval-radio text-red-600 focus:ring-red-500"><span class="ml-2 text-sm">Tidak Sesuai</span></label>
                                </div>
                                <div id="eval_peta_reason_container" class="mt-3 hidden space-y-1">
                                    <label for="eval_peta_reason" class="block text-xs font-medium text-gray-600 dark:text-gray-400">Alasan Peta Tidak Sesuai:</label>
                                    <select id="eval_peta_reason" name="eval_peta_reason" class="eval-input block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">-- Pilih Alasan --</option>
                                        <option value="koordinat_not_valid">Posisi titik tagging tidak berada di bangunan</option>
                                        <option value="koordinat_luar_wilayah">Titik koordinat tidak valid atau berada diluar wilayah</option>
                                    </select>
                                </div>                                                
                            </div>
                        </div>
                        
                        {{-- 3. FOTO BANGUNAN --}}
                        <div class="space-y-2 pt-2 border-t dark:border-gray-700">
                            <h4 class="font-semibold">Foto Bangunan (Persil)</h4>
                            <button type="button" id="detail-foto-bangunan-link" data-zoom-type="persil" class="hidden block h-56 w-full rounded-lg shadow overflow-hidden image-zoom-trigger cursor-zoom-in focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"> <img id="detail-foto-bangunan" alt="Foto Persil" class="w-full h-full object-cover"> </button>
                            <div id="detail-foto-bangunan-none" class="h-56 flex items-center justify-center bg-gray-100 dark:bg-gray-700 rounded-lg text-gray-400 dark:text-gray-500 hidden">Foto tidak tersedia</div>
                        </div>

                        {{-- 4. VALIDASI PERSIL (LEBAR PENUH) --}}
                        <div class="grid grid-cols-1 gap-4 border-t dark:border-gray-700 pt-4">
                            {{-- BLOK VALIDASI PERSIL (Mengambil lebar penuh baris) --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Apakah Foto Persil sudah sesuai?</label>
                                <div class="mt-2 space-x-4">
                                    <label class="inline-flex items-center"><input type="radio" name="eval_persil" value="sesuai" class="eval-radio text-indigo-600 focus:ring-indigo-500"><span class="ml-2 text-sm">Sesuai</span></label>
                                    <label class="inline-flex items-center"><input type="radio" name="eval_persil" value="tidak" class="eval-radio text-red-600 focus:ring-red-500"><span class="ml-2 text-sm">Tidak Sesuai</span></label>
                                </div>
                                <div id="eval_persil_reason_container" class="mt-3 hidden space-y-1">
                                    <label for="eval_persil_reason" class="block text-xs font-medium text-gray-600 dark:text-gray-400">Alasan Foto Persil Tidak Sesuai:</label>
                                    <select id="eval_persil_reason" name="eval_persil_reason" class="eval-input block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">-- Pilih Alasan --</option>
                                        <option value="bukan_foto_persil">Bukan foto persil / bangunan</option>
                                        <option value="diragukan">Foto App tidak ada pada persil</option>
                                        <option value="tidak_valid_lapangan">Foto diragukan dari kegiatan lapangan</option>
                                        <option value="streetview_persil_tidak_tersedia">Streetview tidak tersedia, Validasi Persil Gagal Dilakukan</option>
                                        streetview_tidak_tersedia
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                    </div>

                     {{-- SEL 2: FOTO KWH, INPUT METER, INPUT TEKNIS, INPUT SAMBUNGAN --}}
                     <div class="lg:col-span-2 space-y-4">
                         {{-- Foto KWH & Input Meter --}}
                        <div class="space-y-2">
                            <h4 class="font-semibold">Foto KWH Meter</h4>
                            <button type="button" id="detail-foto-kwh-link" data-zoom-type="kwh" class="hidden block h-56 w-full rounded-lg shadow overflow-hidden image-zoom-trigger cursor-zoom-in focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"> <img id="detail-foto-kwh" alt="Foto KWH" class="w-full h-full object-cover"> </button>
                            <div id="detail-foto-kwh-none" class="h-56 flex items-center justify-center bg-gray-100 dark:bg-gray-700 rounded-lg text-gray-400 dark:text-gray-500 hidden">Foto tidak tersedia</div>
                            <div class="mt-2">
                                <label for="eval_meter_input" class="block text-xs font-medium text-red-600 dark:text-gray-400 mb-1"><b>Wajib !! Ketik No. Meter di Foto sebagai Validasi: </b></label>
                                <input type="text" id="eval_meter_input" class="eval-input block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Nomor Meter lengkap..." autocomplete="Off">
                                <div id="eval_meter_status" class="text-xs mt-1 h-4"></div>
                            </div>
                            <div class="grid grid-cols-1 gap-4 border-t dark:border-gray-700 pt-4 mt-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Apakah Kualitas Foto KWH sudah sesuai?</label>
                                    <div class="mt-2 space-x-4">
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="eval_foto_kwh" value="sesuai" class="eval-radio text-indigo-600 focus:ring-indigo-500">
                                            <span class="ml-2 text-sm">Sesuai</span>
                                        </label>
                                        <label class="inline-flex items-center">
                                            <input type="radio" name="eval_foto_kwh" value="tidak" class="eval-radio text-red-600 focus:ring-red-500">
                                            <span class="ml-2 text-sm">Tidak Sesuai</span>
                                        </label>
                                    </div>
                                    <div id="eval_foto_kwh_reason_container" class="mt-3 hidden space-y-1">
                                        <label for="eval_foto_kwh_reason" class="block text-xs font-medium text-gray-600 dark:text-gray-400">Alasan Foto KWH Tidak Sesuai:</label>
                                        <select id="eval_foto_kwh_reason" name="eval_foto_kwh_reason" class="eval-input block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">-- Pilih Alasan --</option>
                                            <option value="buram">Foto App Buram</option>
                                            <option value="salah">Foto App Salah</option>
                                        </select>
                                    </div>                                                
                                </div>
                            </div>
                        </div>

                        {{-- POSISI BARU: INPUT TEKNIS --}}
                        <div class="pt-4 border-t dark:border-gray-700 space-y-2">
                            <h4 class="font-semibold">Input Teknis</h4>
                            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="eval_mcb" class="block text-xs font-medium text-gray-700 dark:text-gray-400 mb-1">Kapasitas MCB (Contoh: 6A):</label>
                                    <input type="text" id="eval_mcb" class="eval-input block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="MCB...">
                                </div>
                                <div>
                                    <label for="eval_type_pbts" class="block text-xs font-medium text-gray-700 dark:text-gray-400 mb-1">Merk MCB:</label>
                                    <input type="text" id="eval_type_pbts" class="eval-input block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Tipe PB/TS...">
                                </div>
                                <div>
                                    <label for="eval_merkkwhmeter" class="block text-xs font-medium text-gray-700 dark:text-gray-400 mb-1">Merk KWH:</label>
                                    <input type="text" id="eval_merkkwhmeter" class="eval-input block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Merk KWH...">
                                </div>
                                <div>
                                    <label for="eval_tahun_buat" class="block text-xs font-medium text-gray-700 dark:text-gray-400 mb-1">Tahun Buat:</label>
                                    <input type="text" id="eval_tahun_buat" class="eval-input block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Tahun...">
                                </div>
                            </div>
                        </div>

                        {{-- POSISI BARU: INPUT SAMBUNGAN --}}
                        <div class="pt-4 border-t dark:border-gray-700 space-y-2">
                            <h4 class="font-semibold">Input Sambungan</h4>
                            
                            <div class="mt-2 grid grid-cols-1 md:grid-cols-3 gap-4">
                                
                                {{-- BARIS 1: HANYA TIPE SR --}}
                                <div class="md:col-span-3"> 
                                    <label for="eval_sr" class="block text-xs font-medium text-gray-700 dark:text-gray-400 mb-1">Tipe SR:</label>
                                    <select id="eval_sr" class="eval-input block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                        <option value="">-- Pilih Tipe --</option>
                                        <option value="Sambungan dari tiang">Sambungan dari tiang</option>
                                        <option value="SR deret">SR deret</option>
                                    </select>
                                </div>                              
                            </div> 
                            
                            {{-- BARIS 2: LATITUDE SR dan LONGITUDE SR (Membuat baris baru secara manual) --}}
                            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                                
                                <div>
                                    <label for="eval_latitudey_sr" class="block text-xs font-medium text-gray-700 dark:text-gray-400 mb-1">
                                        Latitude SR (Klik <i class="fas fa-street-view"></i>)
                                    </label>
                                    <input type="text" disabled id="eval_latitudey_sr" class="eval-input block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Latitude SR...">
                                </div>
                                
                                <div>
                                    <label for="eval_longitudex_sr" class="block text-xs font-medium text-gray-700 dark:text-gray-400 mb-1">
                                        Longitude SR (Klik <i class="fas fa-street-view"></i>)
                                    </label>
                                    <input type="text" disabled id="eval_longitudex_sr" class="eval-input block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Longitude SR...">
                                </div>
                            </div>
                        </div>

                    </div> {{-- <-- Penutup untuk lg:col-span-2 --}}

                 </div>
            </div> {{-- <-- Penutup untuk <div class="p-5"> --}}


            <div id="evaluation-form" class="p-5 border-t dark:border-gray-700 space-y-4">
                 <h4 class="font-semibold text-lg">Alasan Penolakan Khusus (Jika Ada)</h4>
                 <div id="rejection_reason_container" class="hidden space-y-1">
                      <label for="eval_rejection_reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300"> <p class="text-sm text-gray-500 italic text-red-500">Alasan Penolakan (Wajib diisi jika ada yang 'Tidak Sesuai' Minimal 10 Karakter):</p> </label>
                      <textarea id="eval_rejection_reason" rows="2" class="eval-input mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Contoh: Pin peta di jalan, foto persil beda rumah..."></textarea>
                 </div>
                 <div id="rejection_reason_placeholder" class="">
                     <p class="text-sm text-gray-500 italic text-red-500">Formulir alasan penolakan akan muncul di sini jika Anda memilih <b>'Tidak Sesuai'</b> pada salah satu item di atas. Wajib Isi !!</p>
                 </div>
            </div>
            <div class="p-5 bg-gray-50 dark:bg-gray-900 border-t dark:border-gray-700 flex justify-end space-x-3">
                <form id="detail-form-reject" action="#" method="POST"> 
                    @csrf 
                    @method('DELETE') 
                    <button id="detail-button-reject" type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md font-semibold text-xs uppercase hover:bg-red-700 opacity-50 cursor-not-allowed transition duration-150 ease-in-out" disabled> Tolak </button>
                </form>     
                <form id="detail-form-validate" action="#" method="POST"> @csrf <button id="detail-button-validate" type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md font-semibold text-xs uppercase hover:bg-indigo-700 opacity-50 cursor-not-allowed transition duration-150 ease-in-out" disabled> Validasi & Setujui </button> </form>
            </div>
            </div> {{-- <-- INI TAG PENUTUP UNTUK #validation-content --}}
    </div>

    {{-- ====================================================== --}}
    {{-- MODAL UNTUK GOOGLE STREET VIEW --}}
    {{-- ====================================================== --}}
    <div id="street-view-modal" class="fixed top-10 right-10 hidden z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl w-[50vw] h-[75vh] flex flex-col relative border dark:border-gray-700">
            
            <button id="street-view-close-button" class="absolute -top-3 -right-3 bg-red-500 hover:bg-red-700 text-white rounded-full p-2 z-10 w-8 h-8 flex items-center justify-center">
                <i class="fas fa-times"></i>
            </button>

            {{-- Header Modal --}}
            <div id="street-view-header" class="px-6 py-3 border-b border-gray-200 dark:border-gray-700 cursor-move flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Google Street View</h3>
                
                {{-- Tombol Ambil Koordinat (HANYA TAMPIL JIKA ADMIN) --}}
                @if(Auth::user()->hasRole('admin'))
                <button id="toggle-capture-mode" type="button" class="px-3 py-1 bg-indigo-600 text-white rounded-md font-semibold text-xs uppercase hover:bg-indigo-700 transition duration-150 ease-in-out">
                    <i class="fas fa-crosshairs mr-1"></i> Ambil Koordinat
                </button>
                @else
                <span class="text-xs text-gray-500">(View Only)</span>
                @endif
            </div>

           {{-- Konten Street View --}}
            <div class="flex-grow p-1 relative"> 
                {{-- CONTAINER 1: UNTUK ADMIN (BERBAYAR - JS API) --}}
                <div id="street-view-js-container" class="w-full h-full rounded-md bg-gray-200 dark:bg-gray-700 hidden"></div>
                
                {{-- CONTAINER 2: UNTUK USER LAIN (GRATIS - EMBED API) --}}
                <iframe id="street-view-iframe" 
                        class="w-full h-full rounded-md hidden"
                        frameborder="0" 
                        style="border:0" 
                        allowfullscreen>
                </iframe>

                {{-- OVERLAY CANVAS (Hanya untuk Admin) --}}
                @if(Auth::user()->hasRole('admin'))
                <div id="street-view-overlay" 
                    style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: 1000; cursor: crosshair; pointer-events: none;">
                </div>
                @endif
            </div>
        </div>
    </div>

</div>