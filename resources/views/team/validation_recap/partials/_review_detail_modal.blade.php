{{-- 
  Konten ini akan dimuat via AJAX ke #main-modal
  Variabel dikirim dari ValidationRecapController@showReviewDetails
--}}

<div class="p-6">
    <div class="flex justify-between items-start">
        <div>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $item->idpel }}</h3>
            <p class="text-sm text-gray-500">OBJECTID: {{ $item->objectid }} | Divalidasi oleh: <span class="font-medium">{{ $validatorName }}</span></p>
        </div>
        <button type="button" data-modal-close class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            <i class="fas fa-times fa-lg"></i>
        </button>
    </div>

    <div class="mt-4 border-t dark:border-gray-700 pt-4 grid grid-cols-1 md:grid-cols-2 gap-6">
        
        {{-- Kolom Kiri: Foto-Foto --}}
        <div class="space-y-4">
            <div>
                <h4 class="font-semibold text-gray-800 dark:text-gray-200">Foto KWH</h4>
                @if ($fotoKwhUrl)
                    <img src="{{ $fotoKwhUrl }}" alt="Foto KWH" class="w-full rounded-lg shadow-md object-cover">
                @else
                    <div class="h-48 flex items-center justify-center bg-gray-100 dark:bg-gray-700 rounded-lg text-gray-400">Foto KWH tidak tersedia</div>
                @endif
            </div>
            <div>
                <h4 class="font-semibold text-gray-800 dark:text-gray-200">Foto Persil</h4>
                @if ($fotoBangunanUrl)
                    <img src="{{ $fotoBangunanUrl }}" alt="Foto Persil" class="w-full rounded-lg shadow-md object-cover">
                @else
                    <div class="h-48 flex items-center justify-center bg-gray-100 dark:bg-gray-700 rounded-lg text-gray-400">Foto Persil tidak tersedia</div>
                @endif
            </div>
        </div>

        {{-- Kolom Kanan: Data yang Diisi Validator --}}
        <div class="space-y-4">
            <div>
                <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">Data Input Teknis</h4>
                <div class="text-sm space-y-1 text-gray-700 dark:text-gray-300">
                    <p><strong>No. Meter (Input):</strong> {{ $validationData['eval_meter_input'] ?? $item->nokwhmeter }}</p>
                    <p><strong>Kapasitas MCB:</strong> {{ $item->mcb }}</p>
                    <p><strong>Merk MCB:</strong> {{ $item->type_pbts }}</p>
                    <p><strong>Merk KWH:</strong> {{ $item->merkkwhmeter }}</p>
                    <p><strong>Tahun Buat:</strong> {{ $item->tahun_buat }}</p>
                </div>
            </div>
            <div class="border-t dark:border-gray-700 pt-4">
                <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">Data Input Sambungan</h4>
                <div class="text-sm space-y-1 text-gray-700 dark:text-gray-300">
                    <p><strong>Tipe SR:</strong> {{ $item->sr }}</p>
                    <p><strong>Latitude SR:</strong> {{ $item->latitudey_sr }}</p>
                    <p><strong>Longitude SR:</strong> {{ $item->longitudex_sr }}</p>
                </div>
            </div>
            <div class="border-t dark:border-gray-700 pt-4">
                <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">Hasil Validasi</h4>
                <div class="text-sm space-y-1 text-gray-700 dark:text-gray-300">
                    <p><strong>Validasi Peta:</strong> <span class="font-medium {{ $validationData['eval_peta'] === 'tidak' ? 'text-red-500' : 'text-green-500' }}">{{ $validationData['eval_peta'] ?? 'N/A' }}</span></p>
                    <p><strong>Validasi Persil:</strong> <span class="font-medium {{ $validationData['eval_persil'] === 'tidak' ? 'text-red-500' : 'text-green-500' }}">{{ $validationData['eval_persil'] ?? 'N/A' }}</span></p>
                    <p><strong>Kualitas Foto KWH:</strong> <span class="font-medium {{ $validationData['eval_foto_kwh'] === 'tidak' ? 'text-red-500' : 'text-green-500' }}">{{ $validationData['eval_foto_kwh'] ?? 'N/A' }}</span></p>
                    @if($item->validation_notes)
                        <p><strong>Catatan Validator:</strong> {{ $item->validation_notes }}</p>
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Footer Modal dengan Tombol Aksi --}}
<div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t dark:border-gray-700 flex justify-end space-x-3">
    
    {{-- Tombol Reject (Kembalikan ke Antrian) --}}
    <form id="reject-form-{{ $item->id }}" action="{{ route('team.validation_recap.reject_review', $item->id) }}" method="POST" class="inline-block" onsubmit="return handleSupervisorReject(this, '{{ $item->idpel }}');">
        @csrf
        <input type="hidden" name="reason" id="reject-reason-{{ $item->id }}">
        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md font-semibold text-xs uppercase hover:bg-red-700 transition">
            <i class="fas fa-times-circle mr-1"></i> Tolak Review
        </button>
    </form>
    
    {{-- Tombol Promote (Setujui Final) --}}
    <form action="{{ route('team.validation_recap.promote', $item->id) }}" method="POST" class="inline-block" data-custom-handler="promote-action">
        @csrf
        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md font-semibold text-xs uppercase hover:bg-green-700 transition">
            <i class="fas fa-check-circle mr-1"></i> Approve & Promote
        </button>
    </form>
</div>