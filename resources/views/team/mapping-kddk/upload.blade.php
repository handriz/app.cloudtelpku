<x-app-layout>
    <div class="pt-0 pb-0">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mb-4">
            {{ __('Upload Master Data Pelanggan') }}
        </h2>

        {{-- Notifikasi Sukses --}}
        @if (session('success'))
            <div id="success-alert" class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 px-4 py-3 rounded-lg shadow-md relative w-full mb-4">
                <strong class="font-bold">Berhasil!</strong>
                <span class="block sm:inline">{{ session('success') }}</span>
                <span class="absolute top-2 right-2 px-2 py-1 cursor-pointer" onclick="document.getElementById('success-alert').style.display='none'">
                    <svg class="fill-current h-5 w-5 text-green-500 dark:text-green-300" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l3.029-2.651-3.029-2.651a1.2 1.2 0 0 1 1.697-1.697L10 8.183l2.651-3.029a1.2 1.2 0 1 1 1.697 1.697L11.819 10l3.029 2.651a1.2 1.2 0 0 1 0 1.698z"/></svg>
                </span>
            </div>
        @endif

        {{-- Notifikasi Error --}}
        @if (session('error'))
            <div id="error-alert" class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 px-4 py-3 rounded-lg shadow-md relative w-full mb-4">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline">{{ session('error') }}</span>
                <span class="absolute top-2 right-2 px-2 py-1 cursor-pointer" onclick="document.getElementById('error-alert').style.display='none'">
                    <svg class="fill-current h-5 w-5 text-red-500 dark:text-red-300" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l3.029-2.651-3.029-2.651a1.2 1.2 0 0 1 1.697-1.697L10 8.183l2.651-3.029a1.2 1.2 0 1 1 1.697 1.697L11.819 10l3.029 2.651a1.2 1.2 0 0 1 0 1.698z"/></svg>
                </span>
            </div>
        @endif

        <hr class="border-gray-200 dark:border-gray-700 my-6">

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <form id="uploadForm" class="space-y-4">
                    @csrf
                    <div>
                        <label for="file_data" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Pilih File Excel (.xlsx, .xls)</label>
                        <input type="file" name="file_data" id="file_data" accept=".xlsx,.xls" class="mt-1 block w-full text-sm text-gray-900 dark:text-gray-100
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-md file:border-0
                            file:text-sm file:font-semibold
                            file:bg-indigo-50 file:text-indigo-700
                            hover:file:bg-indigo-100 dark:file:bg-indigo-800 dark:file:text-indigo-200 dark:hover:file:bg-indigo-700" required>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Ukuran file maksimal: 10MB (akan diunggah dalam chunk).</p>
                    </div>

                    <div id="progressBarContainer" class="hidden">
                        <label for="progressBar" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Progress Upload:</label>
                        <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                            <div id="progressBar" class="bg-indigo-600 h-2.5 rounded-full" style="width: 0%"></div>
                        </div>
                        <p id="uploadStatus" class="mt-2 text-sm text-gray-700 dark:text-gray-300"></p>
                    </div>

                    <div id="uploadMessages" class="mt-4 space-y-2"></div>

                    <div class="flex items-center justify-end mt-6">
                        <a href="{{ route('admin.manajemen_data.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 mr-4">Batal</a>
                        <button type="submit" id="uploadButton" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-2 transition ease-in-out duration-150">
                            <i class="fas fa-upload mr-2"></i> Mulai Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const uploadForm = document.getElementById('uploadForm');
            const fileInput = document.getElementById('file_data');
            const uploadButton = document.getElementById('uploadButton');
            const progressBarContainer = document.getElementById('progressBarContainer');
            const progressBar = document.getElementById('progressBar');
            const uploadStatus = document.getElementById('uploadStatus');
            const uploadMessages = document.getElementById('uploadMessages');

            let fileToUpload = null;
            const chunkSize = 1024 * 1024; // 1 MB per chunk (Anda bisa menyesuaikan ini)
            let resumableIdentifier = null; // ID unik untuk sesi upload ini

            // Event listener saat file dipilih
            fileInput.addEventListener('change', function(event) {
                fileToUpload = event.target.files[0];
                if (fileToUpload) {
                    // Buat ID unik berdasarkan properti file
                    resumableIdentifier = generateUniqueId(fileToUpload);
                    uploadButton.disabled = false; // Aktifkan tombol upload
                    uploadMessages.innerHTML = ''; // Bersihkan pesan sebelumnya
                } else {
                    resumableIdentifier = null;
                    uploadButton.disabled = true; // Nonaktifkan tombol upload
                }
            });

            // Event listener saat form disubmit (mulai upload)
            uploadForm.addEventListener('submit', function(event) {
                event.preventDefault(); // Mencegah form disubmit secara tradisional
                if (!fileToUpload) {
                    addMessage('danger', 'Mohon pilih file terlebih dahulu.');
                    return;
                }
                
                uploadButton.disabled = true; // Nonaktifkan tombol saat upload
                progressBarContainer.classList.remove('hidden'); // Tampilkan progress bar
                progressBar.style.width = '0%'; // Reset progress bar
                uploadStatus.textContent = 'Memulai upload...'; // Perbarui status
                uploadMessages.innerHTML = ''; // Bersihkan pesan

                uploadFileInChunks(); // Panggil fungsi untuk memulai chunking upload
            });

            // Fungsi untuk membuat ID unik
            function generateUniqueId(file) {
                // Membuat ID unik berdasarkan nama file, ukuran, dan tanggal modifikasi
                return btoa(encodeURIComponent(file.name + '-' + file.size + '-' + file.lastModified));
            }

            // Fungsi utama untuk mengupload file dalam chunk
            async function uploadFileInChunks() {
                const totalChunks = Math.ceil(fileToUpload.size / chunkSize);
                let currentChunk = 0;
                let uploadedBytes = 0;

                while (currentChunk < totalChunks) {
                    const start = currentChunk * chunkSize;
                    const end = Math.min(fileToUpload.size, start + chunkSize);
                    const chunk = fileToUpload.slice(start, end); // Ambil chunk file

                    const formData = new FormData();
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                    formData.append('file_data', chunk, fileToUpload.name); // Kirim chunk file
                    formData.append('resumableIdentifier', resumableIdentifier);
                    formData.append('resumableFilename', fileToUpload.name);
                    formData.append('resumableChunkNumber', currentChunk + 1); // Nomor chunk berbasis 1
                    formData.append('resumableTotalChunks', totalChunks);
                    formData.append('resumableTotalSize', fileToUpload.size);

                    try {
                        const response = await fetch('{{ route('admin.manajemen_data.upload.chunk') }}', {
                            method: 'POST',
                            body: formData,
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.error || 'Gagal mengupload chunk.');
                        }

                        uploadedBytes += chunk.size; // Hitung byte yang sudah diupload
                        const progress = Math.round((uploadedBytes / fileToUpload.size) * 100);
                        progressBar.style.width = progress + '%'; // Perbarui progress bar
                        uploadStatus.textContent = `Mengupload chunk ${currentChunk + 1} dari ${totalChunks} (${progress}%)`; // Perbarui status

                        currentChunk++; // Lanjut ke chunk berikutnya
                    } catch (error) {
                        addMessage('danger', `Gagal mengupload file: ${error.message}`);
                        uploadButton.disabled = false; // Aktifkan tombol kembali
                        progressBarContainer.classList.add('hidden'); // Sembunyikan progress bar
                        return; // Hentikan upload jika ada error
                    }
                }

                // Jika semua chunk berhasil diupload, panggil server untuk menggabungkan
                mergeChunksOnServer(totalChunks, fileToUpload.name, fileToUpload.size);
            }

            // Fungsi untuk memberi sinyal ke server agar menggabungkan chunk
            async function mergeChunksOnServer(totalChunks, filename, totalSize) {
                uploadStatus.textContent = 'Semua chunk terupload. Menggabungkan file...';

                const formData = new FormData();
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                formData.append('resumableIdentifier', resumableIdentifier);
                formData.append('resumableFilename', filename);
                formData.append('resumableTotalChunks', totalChunks);
                formData.append('resumableTotalSize', totalSize);

                try {
                    const response = await fetch('{{ route('admin.manajemen_data.merge.chunks') }}', {
                        method: 'POST',
                        body: formData,
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.error || 'Gagal menggabungkan file.');
                    }

                    addMessage('success', 'File berhasil diupload dan sedang diproses di latar belakang!');
                    uploadStatus.textContent = 'Upload Selesai. File sedang diproses.';
                    
                    // Reset UI setelah berhasil
                    fileInput.value = '';
                    fileToUpload = null;
                    resumableIdentifier = null;
                    uploadButton.disabled = false;
                    progressBarContainer.classList.add('hidden');

                    // Opsional: Redirect ke halaman index setelah beberapa detik
                    setTimeout(() => {
                        window.location.href = '{{ route('admin.manajemen_data.index') }}';
                    }, 3000);

                } catch (error) {
                    addMessage('danger', `Gagal menggabungkan file: ${error.message}`);
                    uploadStatus.textContent = 'Upload gagal!';
                    uploadButton.disabled = false;
                    progressBarContainer.classList.add('hidden');
                }
            }

            // Fungsi untuk menampilkan pesan notifikasi
            function addMessage(type, text) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `px-4 py-3 rounded-lg shadow-md relative w-full ${type === 'success' ? 'bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200' : 'bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200'}`;
                alertDiv.innerHTML = `
                    <strong class="font-bold">${type === 'success' ? 'Berhasil!' : 'Error!'}</strong>
                    <span class="block sm:inline">${text}</span>
                    <span class="absolute top-2 right-2 px-2 py-1 cursor-pointer" onclick="this.parentNode.style.display='none'">
                        <svg class="fill-current h-5 w-5 ${type === 'success' ? 'text-green-500 dark:text-green-300' : 'text-red-500 dark:text-red-300'}" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l3.029-2.651-3.029-2.651a1.2 1.2 0 0 1 1.697-1.697L10 8.183l2.651-3.029a1.2 1.2 0 1 1 1.697 1.697L11.819 10l3.029 2.651a1.2 1.2 0 0 1 0 1.698z"/></svg>
                    </span>
                `;
                uploadMessages.appendChild(alertDiv);
            }
        });
    </script>
</x-app-layout>
