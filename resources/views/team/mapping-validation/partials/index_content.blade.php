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
        {{-- Panel ini harus berada DI DALAM #interactive-validation-container --}}
        <div id="validation-content" class="hidden">
            
            {{-- ====================================================== --}}
            {{-- BAGIAN HEADER (Informasi) --}}
            {{-- ====================================================== --}}
            <div class="p-5 border-b dark:border-gray-700">
                {{-- Baris 1: IDPEL (Judul Utama) --}}
                <h3 id="detail-idpel" class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-3"></h3>
                
                {{-- Baris 2: Informasi Detail Survey (Grid 4 Kolom) --}}
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 text-sm">
                    
                    {{-- Kolom 1: Didata oleh (1 Span) --}}
                    <div class="lg:col-span-1">
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
            {{-- Diposisikan setelah header, sebelum konten utama --}}
            @include('team.mapping-validation.partials._rejection_history_alert')

            {{-- ====================================================== --}}
            {{-- BAGIAN KONTEN UTAMA (Peta, Foto, Form) --}}
            {{-- ====================================================== --}}
            <div class="p-5">
                 <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                     {{-- SEL 1: PETA & EVAL PETA --}}
                     <div class="lg:col-span-2 space-y-4">
                        <div class="space-y-2">
                            <h4 class="font-semibold">Posisi Koordinat</h4>
                            <div id="validation-map" class="w-full h-80 lg:h-[300px] rounded-lg z-0 bg-gray-200" style="height: 300px;"></div>
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-2 text-sm flex items-center justify-between mt-2">
                                {{-- Tampilan Koordinat --}}
                                <div>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Koordinat:</span>
                                    <span id="validation-lat-lon" class="font-bold text-indigo-600 dark:text-indigo-400">
                                        Memuat...
                                    </span>
                                </div>
                                
                                {{-- Ikon Street View (Gunakan ID unik: validation-street-view-link) --}}
                                <a id="validation-street-view-link" 
                                href="#" 
                                rel="noopener noreferrer" 
                                title="Buka Google Street View"
                                class="text-blue-500 hover:text-blue-700 hidden"> <i class="fas fa-street-view fa-lg"></i>
                                </a>
                            </div>
                        @if(isset($itemToValidate) && $itemToValidate->latitudey && $itemToValidate->longitudex)
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    
                                    // Panggil fungsi inisialisasi Validation Map yang baru dibuat
                                    // Menggunakan ID kontainer yang Anda sebutkan: "validation-map"
                                    initializeValidationMap(
                                        document.getElementById('validation-map'),
                                        parseFloat({{ $itemToValidate->latitudey }}),
                                        parseFloat({{ $itemToValidate->longitudex }}),
                                        '{{ $itemToValidate->idpel }}'
                                    );
                                });
                            </script>
                        @endif
                        </div>
                        <div class="pt-2 border-t dark:border-gray-700">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Apakah posisi peta sudah sesuai?</label>
                            <div class="mt-2 space-x-4">
                                <label class="inline-flex items-center"><input type="radio" name="eval_peta" value="sesuai" class="eval-radio text-indigo-600 focus:ring-indigo-500"><span class="ml-2 text-sm">Sesuai</span></label>
                                <label class="inline-flex items-center"><input type="radio" name="eval_peta" value="tidak" class="eval-radio text-red-600 focus:ring-red-500"><span class="ml-2 text-sm">Tidak Sesuai</span></label>
                            </div>
                            <div id="eval_peta_reason_container" class="mt-3 hidden space-y-1">
                                         <label for="eval_peta_reason" class="block text-xs font-medium text-gray-600 dark:text-gray-400">
                                            Alasan Peta Tidak Sesuai:
                                        </label>
                                        <select id="eval_peta_reason" name="eval_peta_reason" class="eval-input block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="">-- Pilih Alasan --</option>
                                            <option value="posisi_bangunan">Posisi titik tagging tidak berada di bangunan</option>
                                            <option value="luar_wilayah">Titik koordinat tidak valid atau berada diluar wilayah ULP / UP3</option>
                                        </select>
                            </div>                                                
                        </div>
                     </div>
                     {{-- SEL 2: FOTO KWH, INPUT, FOTO PERSIL, EVAL PERSIL --}}
                     <div class="lg:col-span-1 space-y-4">
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
                         </div>
                         {{-- Foto Persil & Evaluasi Persil --}}
                         <div class="space-y-2">
                            <h4 class="font-semibold">Foto Bangunan (Persil)</h4>
                            <button type="button" id="detail-foto-bangunan-link" data-zoom-type="persil" class="hidden block h-56 w-full rounded-lg shadow overflow-hidden image-zoom-trigger cursor-zoom-in focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"> <img id="detail-foto-bangunan" alt="Foto Persil" class="w-full h-full object-cover"> </button>
                            <div id="detail-foto-bangunan-none" class="h-56 flex items-center justify-center bg-gray-100 dark:bg-gray-700 rounded-lg text-gray-400 dark:text-gray-500 hidden">Foto tidak tersedia</div>
                            <div class="pt-2 border-t dark:border-gray-700">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Apakah Foto Persil sudah sesuai?</label>
                                <div class="mt-2 space-x-4">
                                     <label class="inline-flex items-center"><input type="radio" name="eval_persil" value="sesuai" class="eval-radio text-indigo-600 focus:ring-indigo-500"><span class="ml-2 text-sm">Sesuai</span></label>
                                     <label class="inline-flex items-center"><input type="radio" name="eval_persil" value="tidak" class="eval-radio text-red-600 focus:ring-red-500"><span class="ml-2 text-sm">Tidak Sesuai</span></label>
                                </div>
                                <div id="eval_persil_reason_container" class="mt-3 hidden space-y-1">
                                             <label for="eval_persil_reason" class="block text-xs font-medium text-gray-600 dark:text-gray-400">
                                                Alasan Foto Persil Tidak Sesuai:
                                            </label>
                                            <select id="eval_persil_reason" name="eval_persil_reason" class="eval-input block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                <option value="">-- Pilih Alasan --</option>
                                                <option value="bukan_persil">Bukan Foto Persil / Bangunan</option>
                                                <option value="diragukan">Foto App Tidak Ada </option>
                                                <option value="tidak_valid">Foto Diragukan dari kegiatan lapangan</option>
                                            </select>
                                        </div>
                            </div>
                         </div>
                     </div>
                 </div>
            </div>

            {{-- ====================================================== --}}
            {{-- BAGIAN FOOTER (Form Alasan & Tombol Aksi) --}}
            {{-- ====================================================== --}}
            <div id="evaluation-form" class="p-5 border-t dark:border-gray-700 space-y-4">
                 <h4 class="font-semibold text-lg">Alasan Penolakan Khusus (Jika Ada)</h4>
                 <div id="rejection_reason_container" class="hidden space-y-1">
                      <label for="eval_rejection_reason" class="block text-sm font-medium text-gray-700 dark:text-gray-300"> Alasan Penolakan (Wajib diisi jika ada yang 'Tidak Sesuai' Minimal 10 Karakter): </label>
                      <textarea id="eval_rejection_reason" rows="2" class="eval-input mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Contoh: Pin peta di jalan, foto persil beda rumah..."></textarea>
                 </div>
                 <div id="rejection_reason_placeholder" class="">
                     <p class="text-sm text-gray-500 italic">Formulir alasan penolakan akan muncul di sini jika Anda memilih 'Tidak Sesuai' pada salah satu item di atas.</p>
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
        </div>
    </div>

        {{-- ====================================================== --}}
    {{-- MODAL UNTUK GOOGLE STREET VIEW --}}
    {{-- ====================================================== --}}
    <div id="street-view-modal" class="fixed top-10 right-10 hidden z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-2xl w-[50vw] h-[75vh] flex flex-col relative border dark:border-gray-700">
            
            {{-- Tombol Close (dibuat lebih besar dan mudah di-klik) --}}
            <button id="street-view-close-button" class="absolute -top-3 -right-3 bg-red-500 hover:bg-red-700 text-white rounded-full p-2 z-10 w-8 h-8 flex items-center justify-center">
                <i class="fas fa-times"></i>
            </button>

            {{-- Header Modal --}}
            <div id="street-view-header" class="px-6 py-3 border-b border-gray-200 dark:border-gray-700 cursor-move">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Google Street View</h3>
            </div>

            {{-- Konten Iframe --}}
            <div class="flex-grow p-1"> {{-- p-1 agar ada sedikit padding --}}
                <iframe id="street-view-iframe" 
                        src="" 
                        frameborder="0" 
                        allowfullscreen="" 
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        class="w-full h-full rounded-md"></iframe>
            </div>
        </div>
    </div>
</div>
