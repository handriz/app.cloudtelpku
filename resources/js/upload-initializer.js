// File: resources/js/upload-initializer.js
// VERSI FINAL DENGAN VALIDASI FORMAT FILE CSV

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

        const file = fileInput.files[0];

        // ===== BLOK VALIDASI TIPE FILE YANG BARU DITAMBAHKAN =====
        const allowedExtensions = ['.csv'];
        const fileExtension = '.' + file.name.split('.').pop().toLowerCase();

        if (!allowedExtensions.includes(fileExtension)) {
            statusMessage.textContent = 'Gagal: Format file tidak valid. Harap unggah file dengan format .csv';
            statusMessage.className = 'mt-2 text-sm text-red-500';
            // Reset pilihan file yang salah
            uploadForm.reset(); 
            fileNameDisplay.textContent = '';
            return; // Hentikan proses jika file salah
        }
        // ===== AKHIR BLOK VALIDASI =====

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

function initializeBatchPhotoUploadForm() {
    const photoForm = document.getElementById('batch-photo-upload-form');
    // Keluar jika form ini tidak ditemukan
    if (!photoForm) return;

    console.log("Initializing Batch Photo Upload Form..."); // Debug log

    const photoInput = document.getElementById('photo-file-input');
    const fileListDiv = document.getElementById('photo-file-list');
    const uploadButton = document.getElementById('batch-upload-button');
    const progressContainer = document.getElementById('batch-progress-container');
    const progressBar = document.getElementById('batch-progress-bar');
    const progressText = document.getElementById('batch-progress-text');
    const statusMessage = document.getElementById('batch-status-message');
    const dropzone = document.getElementById('photo-dropzone'); // Tambahkan dropzone

    // Pastikan semua elemen ditemukan sebelum melanjutkan
    if (!photoInput || !fileListDiv || !uploadButton || !progressContainer || !progressBar || !progressText || !statusMessage || !dropzone) {
        console.error("Satu atau lebih elemen form batch photo upload tidak ditemukan!");
        return;
    }


    let filesToUpload = [];

    function updateFileList() {
        fileListDiv.innerHTML = ''; // Kosongkan list
        if (filesToUpload.length > 0) {
            const list = document.createElement('ul');
            list.className = 'list-disc pl-5';
            filesToUpload.forEach((file) => { // Dihapus index agar tidak error jika array kosong
                const li = document.createElement('li');
                li.textContent = `${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
                list.appendChild(li);
            });
            fileListDiv.appendChild(list);
            uploadButton.disabled = false;
        } else {
             uploadButton.disabled = true;
        }
    }

     // Event listener untuk input file
     photoInput.addEventListener('change', (e) => {
        filesToUpload = Array.from(e.target.files);
        updateFileList();
    });

    // --- Logika Drag and Drop ---
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
    dropzone.addEventListener('drop', e => {
         // Ambil file, filter hanya gambar, lalu simpan ke filesToUpload
         const droppedFiles = Array.from(e.dataTransfer.files).filter(file => file.type.startsWith('image/'));
         filesToUpload = droppedFiles;

         // Buat objek FileList baru untuk disinkronkan ke input (penting agar form submit tahu filenya)
         const dataTransfer = new DataTransfer();
         droppedFiles.forEach(file => dataTransfer.items.add(file));
         photoInput.files = dataTransfer.files; // Sinkronkan

         updateFileList();
    }, false);
    // --- Akhir Drag and Drop ---

    // Pisahkan handler submit agar bisa dihapus listenernya
    const handleBatchSubmit = async (e) => {
        e.preventDefault();
        if (filesToUpload.length === 0) return;

        uploadButton.disabled = true;
        uploadButton.classList.add('opacity-50', 'cursor-not-allowed');
        progressContainer.classList.remove('hidden');
        statusMessage.textContent = '';
        statusMessage.className = 'mt-2 text-sm';
        progressBar.style.width = '0%';
        progressBar.textContent = '0%';

        let successCount = 0;
        let errorCount = 0;
        const totalFiles = filesToUpload.length;

        // Upload satu per satu
        for (let i = 0; i < totalFiles; i++) {
            const file = filesToUpload[i];
            const formData = new FormData();
            // Penting: Nama field harus 'photos[]' sesuai validasi controller
            formData.append('photos[]', file);

            progressText.textContent = `Mengunggah foto ${i + 1} dari ${totalFiles}: ${file.name}...`;

            try {
                const response = await fetch(photoForm.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const result = await response.json();

                if (!response.ok) {
                    const errorMsg = result.errors ? Object.values(result.errors).flat().join(', ') : (result.message || `Upload gagal (Status ${response.status})`);
                    console.error(`Gagal upload ${file.name}:`, errorMsg);
                    statusMessage.innerHTML += `<p class="text-red-500">- Gagal: ${file.name} (${errorMsg})</p>`;
                    errorCount++;
                } else {
                    successCount++;
                }

            } catch (error) {
                console.error(`Error jaringan upload ${file.name}:`, error);
                statusMessage.innerHTML += `<p class="text-red-500">- Error jaringan saat upload ${file.name}</p>`;
                errorCount++;
            }

            // Update progress bar setelah setiap file
            const progress = Math.round(((i + 1) / totalFiles) * 100);
            progressBar.style.width = progress + '%';
            progressBar.textContent = progress + '%';
        }

        // Tampilkan hasil akhir
        progressText.textContent = `Proses upload selesai.`;
        if (errorCount === 0) {
            statusMessage.innerHTML = `<p class="text-green-600">Berhasil mengunggah ${successCount} foto ke inbox server.</p>`;
            filesToUpload = [];
            photoInput.value = '';
            updateFileList();
        } else {
             statusMessage.innerHTML += `<p class="font-semibold mt-2">Total: ${successCount} sukses, ${errorCount} gagal.</p>`;
        }

        uploadButton.disabled = false;
        uploadButton.classList.remove('opacity-50', 'cursor-not-allowed');
    };

    // Hapus listener lama jika ada sebelum menambahkan yang baru
    if (photoForm._handleBatchSubmit) {
        photoForm.removeEventListener('submit', photoForm._handleBatchSubmit);
        console.log("Removed old batch submit listener."); // Debug log
    }
    photoForm.addEventListener('submit', handleBatchSubmit);
    photoForm._handleBatchSubmit = handleBatchSubmit; // Simpan referensi
    console.log("Added new batch submit listener."); // Debug log


    // Panggil updateFileList di awal
    updateFileList();
    console.log("Batch Photo Upload Form Initialized."); // Debug log
}

// Daftarkan fungsi ke objek global agar bisa dipanggil oleh tab-manager.js
window.UploadInitializers = {
    initializeUploadForm: initializeUploadForm,
    initializeBatchPhotoUploadForm: initializeBatchPhotoUploadForm
};