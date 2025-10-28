{{-- 
Wadah ini akan di-clone dan diisi oleh JavaScript.
Kita sembunyikan (hidden) secara default.
--}}
<div id="rejection-history-alert" class="hidden p-5 border-b dark:border-gray-700">
    <div class="bg-yellow-50 border border-yellow-300 dark:bg-gray-800 dark:border-yellow-600 rounded-lg p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-yellow-400 text-xl" aria-hidden="true"></i>
            </div>
            
            {{-- GANTI DARI SINI --}}
            <div class="ml-3 w-full"> <details class="group">
                    {{-- Ini adalah header yang selalu terlihat --}}
                    <summary class="flex justify-between items-center cursor-pointer list-none">
                        <h3 class="text-sm font-semibold text-yellow-800 dark:text-yellow-300">
                            Perhatian: Data ini Pernah Ditolak ( <span id="rejection-status" class="font-bold"></span> )
                        </h3>
                        {{-- Ikon panah (akan berputar saat dibuka) --}}
                        <span class="group-open:rotate-180 transition-transform duration-200">
                            <i class="fas fa-chevron-down text-xs text-yellow-700 dark:text-yellow-300"></i>
                        </span>
                    </summary>

                    {{-- Ini adalah konten yang tersembunyi --}}
                    <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-200">
                        <p class="mb-2">Harap perhatikan catatan berikut dari validasi sebelumnya sebelum melanjutkan:</p>
                        
                        {{-- Kita buat daftarnya lebih rapat --}}
                        <ul role="list" class="list-disc pl-5 space-y-0.5" id="rejection-list-items">
                            {{-- Contoh: <li><strong>Alasan Peta:</strong> Posisi titik tidak di bangunan</li> --}}
                        </ul>
                    </div>
                </details>
            </div>
            {{-- GANTI SAMPAI SINI --}}

        </div>
    </div>
</div>