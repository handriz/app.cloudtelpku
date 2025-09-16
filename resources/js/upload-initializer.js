// File: resources/js/upload-initializer.js
// VERSI FINAL PALING STABIL

function initializeUploadForm() {
    const uploadForm = document.getElementById('upload-form');
    // Keluar jika form tidak ditemukan, untuk mencegah error
    if (!uploadForm) return;

    // Ambil semua elemen yang dibutuhkan dari form
    const fileInput = document.getElementById('file-input');
    const uploadButton = document.getElementById('upload-button');
    const fileNameDisplay = document.getElementById('file-name');
    const progressContainer = document.getElementById('progress-container');
    const progressBar = document.getElementById('progress-bar');
    const statusMessage = document.getElementById('status-message');
    const dropzone = document.getElementById('dropzone');

    /**
     * Fungsi pembantu untuk menangani file yang dipilih (baik via klik maupun drag-drop)
     */
    function handleFiles(files) {
        if (files && files.length > 0) {
            fileInput.files = files; // Sinkronkan file ke input file tersembunyi
            fileNameDisplay.textContent = files[0].name; // Tampilkan nama file
            // Reset tampilan ke kondisi awal
            statusMessage.textContent = '';
            progressContainer.classList.add('hidden');
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
        }
    }

    // Event listener saat file dipilih via dialog "Pilih File"
    fileInput.addEventListener('change', () => handleFiles(fileInput.files));

    // Logika untuk Drag and Drop
    if (dropzone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, e => {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => dropzone.classList.add('border-indigo-500', 'bg-indigo-50', 'dark:bg-gray-700'), false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => dropzone.classList.remove('border-indigo-500', 'bg-indigo-50', 'dark:bg-gray-700'), false);
        });
        dropzone.addEventListener('drop', e => handleFiles(e.dataTransfer.files), false);
    }
    
    // Pisahkan handler submit ke dalam fungsi sendiri agar bisa dikelola
    const handleSubmit = async (e) => {
        e.preventDefault();
        
        if (fileInput.files.length === 0) {
            statusMessage.textContent = 'Silakan pilih file terlebih dahulu.';
            statusMessage.className = 'mt-2 text-sm text-red-500';
            return;
        }

        // Ambil URL dari atribut data-* DARI DALAM FUNGSI INI
        const chunkUrl = uploadForm.dataset.chunkUrl;
        const mergeUrl = uploadForm.dataset.mergeUrl;

        if (!chunkUrl || !mergeUrl) {
            statusMessage.textContent = 'Gagal: URL untuk upload tidak ditemukan. Periksa atribut data-* di form HTML.';
            statusMessage.className = 'mt-2 text-sm text-red-500';
            return;
        }

        uploadButton.disabled = true;
        uploadButton.classList.add('opacity-50', 'cursor-not-allowed');

        const file = fileInput.files[0];
        const chunkSize = 1 * 1024 * 1024; // 1MB
        const totalChunks = Math.ceil(file.size / chunkSize);
        const uniqueFileName = Date.now() + '-' + file.name.replace(/[^a-zA-Z0-9.-_]/g, '');
        
        progressContainer.classList.remove('hidden');
        statusMessage.textContent = 'Mengunggah file...';
        statusMessage.className = 'mt-2 text-sm text-gray-600 dark:text-gray-400';

        // 1. Proses Upload per Potongan (Chunk)
        for (let i = 0; i < totalChunks; i++) {
            const chunk = file.slice(i * chunkSize, (i + 1) * chunkSize);
            const formData = new FormData();
            formData.append('file', chunk, uniqueFileName);
            formData.append('chunkIndex', i);
            formData.append('totalChunks', totalChunks);
            formData.append('fileName', uniqueFileName);
            
            try {
                const response = await fetch(chunkUrl, {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')},
                    body: formData
                });
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({ message: 'Upload chunk gagal. Status: ' + response.status }));
                    throw new Error(errorData.message || 'Upload chunk gagal.');
                }
                const progress = Math.round(((i + 1) / totalChunks) * 100);
                progressBar.style.width = progress + '%';
                progressBar.textContent = progress + '%';
            } catch (error) {
                statusMessage.textContent = 'Gagal: ' + error.message;
                statusMessage.className = 'mt-2 text-sm text-red-500';
                uploadButton.disabled = false;
                uploadButton.classList.remove('opacity-50', 'cursor-not-allowed');
                return;
            }
        }

        // 2. Proses Penggabungan (Merge) dan Memulai Import
        statusMessage.textContent = 'Finalisasi: Menggabungkan file & memulai import...';
        try {
            const mergeResponse = await fetch(mergeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify({ totalChunks, fileName: uniqueFileName, totalSize: file.size })
            });
            const result = await mergeResponse.json();
            if (!mergeResponse.ok) throw new Error(result.message || 'Gagal memulai proses import.');
            
            statusMessage.textContent = result.message;
            statusMessage.className = 'mt-2 text-sm text-green-600';
            uploadForm.reset();
            fileNameDisplay.textContent = '';
        } catch (error) {
            statusMessage.textContent = 'Gagal: ' + error.message;
            statusMessage.className = 'mt-2 text-sm text-red-500';
        } finally {
            uploadButton.disabled = false;
            uploadButton.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    };
    
    // Hapus listener lama (jika ada) dan tambahkan yang baru. Ini mencegah duplikasi.
    if (uploadForm._handleSubmit) {
        uploadForm.removeEventListener('submit', uploadForm._handleSubmit);
    }
    uploadForm.addEventListener('submit', handleSubmit);
    uploadForm._handleSubmit = handleSubmit; // Simpan referensi ke handler saat ini
}

// Daftarkan fungsi ke objek global agar bisa dipanggil oleh tab-manager.js
window.UploadInitializers = {
    initializeUploadForm: initializeUploadForm
};