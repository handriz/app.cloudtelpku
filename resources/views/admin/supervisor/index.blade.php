<x-app-layout>
    <div class="pt-0 pb-0">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mb-4">
            {{ __('Manajemen Queue Workers') }}
        </h2>

        {{-- Area untuk notifikasi dinamis (sukses/error dari AJAX) --}}
        <div id="dynamic-alerts" class="mb-4 space-y-2"></div>

        <hr class="border-gray-200 dark:border-gray-700 my-6">

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Status Queue Workers</h3>

                {{-- Pesan error jika Supervisor tidak bisa dihubungi --}}
                @if($error)
                    <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 px-4 py-3 rounded-lg shadow-md mb-4">
                        <strong class="font-bold">Koneksi Gagal!</strong>
                        <span class="block sm:inline">{{ $error }}</span>
                        <p class="mt-2 text-sm">Pastikan Supervisor berjalan di `{{ env('SUPERVISOR_HOST') }}:{{ env('SUPERVISOR_PORT') }}` dengan kredensial yang benar (`SUPERVISOR_USERNAME`, `SUPERVISOR_PASSWORD`) di file `.env` Anda.</p>
                    </div>
                @else
                    {{-- Pesan peringatan jika Supervisor tidak RUNNING --}}
                    @if(!$pingSuccess)
                        <div class="bg-yellow-100 dark:bg-yellow-900 border border-yellow-400 dark:border-yellow-700 text-yellow-700 dark:text-yellow-200 px-4 py-3 rounded-lg shadow-md mb-4">
                            <strong class="font-bold">Supervisor Tidak Aktif!</strong>
                            <span class="block sm:inline">Supervisor tidak berjalan atau dalam kondisi tidak sehat. Beberapa fungsionalitas mungkin terganggu.</span>
                        </div>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                        Nama Proses
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                        Grup
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                        Uptime
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                {{-- Loop untuk menampilkan setiap proses worker --}}
                                @forelse($processes as $process)
                                    {{-- ID baris untuk update status dengan JavaScript --}}
                                    <tr id="process-row-{{ Str::slug($process['name']) }}">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $process['name'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            {{ $process['group'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            {{-- Span untuk menampilkan status dengan warna dinamis --}}
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                @if($process['statename'] == 'RUNNING') bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100
                                                @elseif($process['statename'] == 'STOPPED') bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-100
                                                @elseif($process['statename'] == 'FATAL') bg-red-200 text-red-900 dark:bg-red-800 dark:text-red-200
                                                @else bg-yellow-100 text-yellow-800 dark:bg-yellow-700 dark:text-yellow-100 @endif"
                                                id="status-{{ Str::slug($process['name']) }}">
                                                {{ $process['statename'] }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            {{-- Menghitung uptime (waktu aktif) --}}
                                            {{ gmdate("H:i:s", $process['now'] - $process['start']) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            {{-- Tombol Start --}}
                                            <button type="button" data-action="start" data-process="{{ $process['name'] }}" class="action-btn px-3 py-1 text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed mr-2" {{ $process['statename'] == 'RUNNING' ? 'disabled' : '' }}>
                                                Start
                                            </button>
                                            {{-- Tombol Stop --}}
                                            <button type="button" data-action="stop" data-process="{{ $process['name'] }}" class="action-btn px-3 py-1 text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed mr-2" {{ $process['statename'] == 'STOPPED' || $process['statename'] == 'FATAL' ? 'disabled' : '' }}>
                                                Stop
                                            </button>
                                            {{-- Tombol Restart --}}
                                            <button type="button" data-action="restart" data-process="{{ $process['name'] }}" class="action-btn px-3 py-1 text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed" {{ $process['statename'] == 'RUNNING' ? '' : 'disabled' }}>
                                                Restart
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada worker yang terdaftar atau ditemukan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const actionButtons = document.querySelectorAll('.action-btn');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const dynamicAlertsContainer = document.getElementById('dynamic-alerts');

            actionButtons.forEach(button => {
                button.addEventListener('click', async function() {
                    const action = this.dataset.action; // Aksi: 'start', 'stop', atau 'restart'
                    const processName = this.dataset.process; // Nama proses worker

                    // Konfirmasi sebelum melakukan aksi
                    if (!confirm(`Apakah Anda yakin ingin ${action} proses ${processName}?`)) {
                        return;
                    }

                    // Nonaktifkan semua tombol untuk proses ini saat aksi sedang berjalan
                    document.querySelectorAll(`#process-row-${slugify(processName)} .action-btn`).forEach(btn => btn.disabled = true);
                    
                    // Update status visual menjadi 'Memproses...'
                    const statusSpan = document.getElementById(`status-${slugify(processName)}`);
                    statusSpan.textContent = 'Memproses...';
                    // Ubah kelas warna menjadi kuning (indikator proses)
                    statusSpan.className = 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-700 dark:text-yellow-100';

                    try {
                        // Kirim permintaan AJAX ke controller Laravel
                        const response = await fetch('{{ route('admin.supervisor.updateProcess') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken, // CSRF token untuk keamanan
                                'X-Requested-With': 'XMLHttpRequest' // Menandakan permintaan AJAX
                            },
                            body: JSON.stringify({ // Kirim data sebagai JSON
                                process_name: processName,
                                action: action
                            })
                        });

                        const data = await response.json(); // Parse respons JSON

                        if (response.ok) {
                            addMessage('success', data.success); // Tampilkan pesan sukses
                            // Karena aksi dilakukan secara async, kita perlu polling atau refresh
                            // untuk mendapatkan status terbaru. Untuk kesederhanaan, kita refresh halaman.
                            setTimeout(() => {
                                window.location.reload(); 
                            }, 1500); // Refresh setelah 1.5 detik
                        } else {
                            // Tangani error dari server
                            addMessage('danger', data.error);
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        }
                    } catch (error) {
                        // Tangani error jaringan atau JavaScript
                        addMessage('danger', 'Terjadi kesalahan jaringan atau server: ' + error.message);
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                });
            });

            // Fungsi untuk menambahkan pesan notifikasi dinamis ke UI
            function addMessage(type, text) {
                const alertDiv = document.createElement('div');
                // Tentukan kelas CSS berdasarkan tipe pesan (sukses/danger)
                alertDiv.className = `px-4 py-3 rounded-lg shadow-md relative w-full mb-4 ${type === 'success' ? 'bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200' : 'bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200'}`;
                alertDiv.innerHTML = `
                    <strong class="font-bold">${type === 'success' ? 'Berhasil!' : 'Error!'}</strong>
                    <span class="block sm:inline">${text}</span>
                    <span class="absolute top-2 right-2 px-2 py-1 cursor-pointer" onclick="this.parentNode.style.display='none'">
                        <i class="fas fa-times"></i>
                    </span>
                `;
                // Bersihkan pesan dinamis sebelumnya dan tambahkan yang baru
                dynamicAlertsContainer.innerHTML = ''; 
                dynamicAlertsContainer.appendChild(alertDiv);
            }

            // Fungsi utility untuk mengubah teks menjadi format slug (misal: "my-process-name")
            // Digunakan untuk membuat ID HTML yang valid dari nama proses
            function slugify(text) {
                return text.toString().toLowerCase()
                    .replace(/\s+/g, '-')           // Ganti spasi dengan -
                    .replace(/[^\w-]+/g, '')       // Hapus semua karakter non-kata
                    .replace(/--+/g, '-')         // Ganti multiple - dengan single -
                    .replace(/^-+/, '')             // Hapus - dari awal teks
                    .replace(/-+$/, '');            // Hapus - dari akhir teks
            }
        });
    </script>
</x-app-layout>
