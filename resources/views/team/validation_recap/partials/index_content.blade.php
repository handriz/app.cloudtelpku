{{-- 
  File: resources/views/team/validation_recap/partials/index_content.blade.php
  Tampilan lengkap dengan header lebih pendek dan teks rata tengah.
--}}

<div id="kddk-notification-container">
    {{-- Notifikasi Sukses/Error dari tab-manager.js akan muncul di sini --}}
</div>

<div class="mb-4 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-3">
        Rekap Performa Validator
    </h3>
    <div class="overflow-x-auto">
        <table class="min-w-full border-collapse border border-gray-300 dark:border-gray-600">
            
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    {{-- MODIFIKASI: py-3 -> py-2 dan text-center --}}
                    <th rowspan="2" class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase border border-gray-300 dark:border-gray-600">Validator</th>
                    <th rowspan="2" class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase border border-gray-300 dark:border-gray-600">Total Divalidasi</th>
                    <th rowspan="2" class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase border border-gray-300 dark:border-gray-600">Menunggu Review</th>
                    
                    <th colspan="3" class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase border border-gray-300 dark:border-gray-600">
                        Detail Tolak Data
                    </th>
                    
                    <th rowspan="2" class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase border border-gray-300 dark:border-gray-600">Total Ditolak</th>
                </tr>
                <tr>
                    {{-- MODIFIKASI: py-3 -> py-2 dan text-center --}}
                    <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase border border-gray-300 dark:border-gray-600">Tolak Peta</th>
                    <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase border border-gray-300 dark:border-gray-600">Tolak Persil</th>
                    <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase border border-gray-300 dark:border-gray-600">Tolak Foto KWH</th>
                </tr>
            </thead>
            
            <tbody class="bg-white dark:bg-gray-800">
                
                @forelse ($validatorStats as $stat)
                    <tr>
                        {{-- MODIFIKASI: Tambahkan text-center --}}
                        <td class="px-6 py-4 text-center font-medium text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600">{{ $stat->name }}</td>
                        <td class="px-6 py-4 text-center text-gray-500 border border-gray-300 dark:border-gray-600">{{ $stat->total_validated }}</td>
                        <td class="px-6 py-4 text-center text-blue-500 font-medium border border-gray-300 dark:border-gray-600">{{ $stat->pending_review }}</td>

                        <td class="px-6 py-4 text-center text-red-500 border border-gray-300 dark:border-gray-600">{{ $stat->rejected_peta }}</td>
                        <td class="px-6 py-4 text-center text-red-500 border border-gray-300 dark:border-gray-600">{{ $stat->rejected_persil }}</td>
                        <td class="px-6 py-4 text-center text-red-500 border border-gray-300 dark:border-gray-600">{{ $stat->rejected_foto_kwh }}</td>
                        
                        <td class="px-6 py-4 text-center text-red-500 font-bold border border-gray-300 dark:border-gray-600">{{ $stat->total_rejected }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500 border border-gray-300 dark:border-gray-600">Belum ada statistik validator.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="bg-white dark:bg-gray-800 rounded-lg shadow mt-4">
    <div class="p-4 border-b dark:border-gray-700">
        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">
            Daftar Menunggu Review ({{ $reviewItems->total() }})
        </h3>
        <p class="text-sm text-gray-500">
            @if (Auth::check() && (Auth::user()->hasRole('admin') || Auth::user()->hasRole('team')))
                Menampilkan data yang divalidasi oleh App User dan siap untuk di-review.
            @else
                Menampilkan riwayat data yang telah Anda validasi.
            @endif
        </p>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full border-collapse border border-gray-300 dark:border-gray-600">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    {{-- MODIFIKASI: py-3 -> py-2 dan text-center --}}
                    <th scope="col" class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase border border-gray-300 dark:border-gray-600">IDPEL / Object ID</th>
                    <th scope="col" class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase border border-gray-300 dark:border-gray-600">Validator</th>
                    <th scope="col" class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase border border-gray-300 dark:border-gray-600">Tgl Validasi</th>
                    
                    @if (Auth::check() && (Auth::user()->hasRole('admin') || Auth::user()->hasRole('team')))
                    <th scope="col" class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase border border-gray-300 dark:border-gray-600">Aksi Review</th>
                    @endif
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800">
                
                @forelse ($reviewItems as $item)
                    <tr>
                        {{-- MODIFIKASI: Tambahkan text-center --}}
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center border border-gray-300 dark:border-gray-600">
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $item->idpel }}</div>
                            <div class="text-gray-500">{{ $item->objectid }}</div>
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500 border border-gray-300 dark:border-gray-600">
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                {{ $item->validator->name ?? 'User Tidak Ditemukan' }}
                            </div>
                            <div class="text-xs text-gray-500">
                                (ID: {{ $item->user_validasi }})
                            </div>
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500 border border-gray-300 dark:border-gray-600">
                            {{ $item->updated_at->format('d M Y H:i') }}
                        </td>
                        
                        @if (Auth::check() && (Auth::user()->hasRole('admin') || Auth::user()->hasRole('team')))
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-medium space-x-2 border border-gray-300 dark:border-gray-600">
                            
                            <form action="{{ route('team.validation_recap.promote', $item->id) }}" method="POST" class="inline-block" data-custom-handler="promote-action">
                                @csrf
                                <button type="submit" class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-check-circle mr-1"></i> Approve
                                </button>
                            </form>

                            <form id="reject-form-{{ $item->id }}" action="{{ route('team.validation_recap.reject_review', $item->id) }}" method="POST" class="inline-block" onsubmit="return handleSupervisorReject(this, '{{ $item->idpel }}');">
                                @csrf
                                <input type="hidden" name="reason" id="reject-reason-{{ $item->id }}">
                                <button type="submit" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-times-circle mr-1"></i> Reject
                                </button>
                            </form>
                        </td>
                        @endif
                    </tr>
                
                @empty 
                    <tr>
                        @if (Auth::check() && (Auth::user()->hasRole('admin') || Auth::user()->hasRole('team')))
                            <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500 border border-gray-300 dark:border-gray-600">
                        @else
                            <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500 border border-gray-300 dark:border-gray-600">
                        @endif
                                Tidak ada data yang menunggu review.
                            </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    {{-- Paginasi --}}
    <div class="p-4">
        {{ $reviewItems->appends(['review_page' => $reviewItems->currentPage()])->links('pagination::tailwind') }}
    </div>
</div>
{{-- Script popup alasan reject (jika diperlukan) --}}
<script>
    if (typeof handleSupervisorReject !== 'function') {
        function handleSupervisorReject(form, idpel) {
            const reason = prompt(`Masukkan alasan menolak (mengembalikan) data IDPEL ${idpel}:`);
            
            if (reason && reason.length >= 10) {
                form.querySelector('input[name="reason"]').value = reason;
                return true; 
            } else if (reason !== null) { 
                alert('Alasan penolakan minimal 10 karakter.');
            }
            return false; // Batalkan submit
        }
    }
</script>