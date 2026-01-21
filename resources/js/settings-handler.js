/**
 * SETTINGS HANDLER (CLEAN VERSION - INTEGRATED WITH TAB MANAGER V2)
 * Fokus hanya pada logika Form & Data Settings.
 * Logika Tab diserahkan sepenuhnya ke tab-manager.js.
 */

(function () {
    'use strict';

    window.settingsHandler = {

        // --- 1. GENERIC AUTO-SAVE ---
        autoSaveSetting: function (inputElement) {
            const key = inputElement.name;
            const value = inputElement.type === 'checkbox' ? inputElement.checked : inputElement.value;
            const group = inputElement.dataset.group || 'general';
            const label = inputElement.dataset.label || key;

            if (!key) return;

            let feedbackId = `feedback-${key}`;
            let feedbackEl = document.getElementById(feedbackId);

            if (!feedbackEl) {
                feedbackEl = document.createElement('span');
                feedbackEl.id = feedbackId;
                if (inputElement.nextSibling) {
                    inputElement.parentNode.insertBefore(feedbackEl, inputElement.nextSibling);
                } else {
                    inputElement.parentNode.appendChild(feedbackEl);
                }
            }

            feedbackEl.className = 'ml-2 text-xs font-bold text-gray-400';
            feedbackEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            inputElement.classList.add('bg-gray-50', 'transition-colors');

            fetch('/settings/save-generic-setting', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ key, value, group, label })
            })
                .then(res => res.json())
                .then(data => {
                    inputElement.classList.remove('bg-gray-50');
                    if (data.success) {
                        feedbackEl.className = 'ml-2 text-xs font-bold text-green-600';
                        feedbackEl.innerHTML = '<i class="fas fa-check"></i>';
                        setTimeout(() => { feedbackEl.innerHTML = ''; }, 2000);
                    } else {
                        throw new Error(data.message || 'Gagal menyimpan');
                    }
                })
                .catch(err => {
                    inputElement.classList.remove('bg-gray-50');
                    feedbackEl.className = 'ml-2 text-xs font-bold text-red-600';
                    feedbackEl.innerHTML = '<i class="fas fa-times"></i> Err';
                    console.error(err);
                });
        },

        // --- 2. AREA MANAGEMENT ---
        addNewArea: function () {
            const codeInput = document.getElementById('new-area-code');
            const labelInput = document.getElementById('new-area-label');
            const categoryInput = document.getElementById('new-area-category');

            if (!codeInput || !labelInput) return;

            const code = codeInput.value.trim();
            const label = labelInput.value.trim();
            const category = categoryInput ? categoryInput.value.trim() : 'Umum';

            if (!/^[A-Z]+$/.test(code)) {
                alert("Kode Area hanya boleh Huruf (A-Z). Angka tidak diperbolehkan.");
                codeInput.focus();
                return;
            }

            if (code.length !== 2) {
                alert("Kode Area wajib 2 karakter huruf!");
                codeInput.focus();
                return;
            }
            if (!label) {
                alert("Nama Area wajib diisi!");
                labelInput.focus();
                return;
            }

            document.body.style.cursor = 'wait';

            fetch('/settings/add-area', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ code, label, category })
            })
                .then(res => res.json())
                .then(data => {
                    document.body.style.cursor = 'default';
                    if (data.success) {
                        alert("Berhasil! Halaman akan dimuat ulang.");
                        // Refresh Tab Settings Menggunakan Tab Manager
                        if (App && App.Tabs && App.Utils) {
                            const activeTab = App.Utils.getActiveTabName();
                            const tabBtn = document.querySelector(`.tab-button[data-tab-name="${activeTab}"]`);
                            if (tabBtn) {
                                const url = tabBtn.dataset.url || tabBtn.href;
                                // Tambah timestamp agar cache busted
                                const sep = url.includes('?') ? '&' : '?';
                                App.Tabs.loadTabContent(activeTab, url + sep + '_t=' + new Date().getTime());
                            } else {
                                window.location.reload();
                            }
                        } else {
                            window.location.reload();
                        }
                    } else {
                        alert(data.message);
                    }
                })
                .catch(err => {
                    document.body.style.cursor = 'default';
                    alert("Terjadi kesalahan koneksi.");
                });
        },

        deleteArea: function (areaCode) {
            if (!confirm(`Yakin ingin menghapus AREA "${areaCode}" beserta seluruh rutenya?`)) return;
            document.body.style.cursor = 'wait';

            fetch('/settings/delete-item', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ type: 'area', area_code: areaCode })
            })
                .then(res => res.json())
                .then(data => {
                    document.body.style.cursor = 'default';
                    if (data.success) {
                        alert("Area berhasil dihapus.");
                        // Refresh logic (sama seperti add)
                        const activeTab = App.Utils.getActiveTabName();
                        const tabBtn = document.querySelector(`.tab-button[data-tab-name="${activeTab}"]`);
                        if (tabBtn) {
                            const url = tabBtn.dataset.url || tabBtn.href;
                            const sep = url.includes('?') ? '&' : '?';
                            App.Tabs.loadTabContent(activeTab, url + sep + '_t=' + new Date().getTime());
                        } else {
                            window.location.reload();
                        }
                    } else {
                        alert(data.message);
                    }
                })
                .catch(err => {
                    document.body.style.cursor = 'default';
                    alert("Gagal menghapus area.");
                });
        },

        // --- FILTER / SEARCH AREA ---
        filterAreas: function () {
            const input = document.getElementById('area-search-filter');
            if (!input) return;

            const filter = input.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.area-row-item');
            const groups = document.querySelectorAll('details.group');

            rows.forEach(row => {
                const text = row.dataset.search || '';
                if (text.includes(filter)) {
                    row.style.display = '';
                    row.classList.add('matches-search');
                } else {
                    row.style.display = 'none';
                    row.classList.remove('matches-search');
                }
            });

            groups.forEach(group => {
                const visibleChildren = group.querySelectorAll('.area-row-item.matches-search');
                if (filter === '') {
                    group.style.display = '';
                } else {
                    if (visibleChildren.length > 0) {
                        group.style.display = '';
                        group.open = true;
                    } else {
                        group.style.display = 'none';
                    }
                }
            });
        },

        // --- 3. ROUTE MANAGEMENT (UI Helper) ---
        /**
         * Menambah Baris Rute Baru dengan CEGAH DUPLIKAT KETAT
         */
        addRouteItem: function () {
            const codeInput = document.getElementById('new-route-code');
            const labelInput = document.getElementById('new-route-label');
            const targetContainer = document.getElementById('route-new-rows');
            const areaCode = document.getElementById('current-area-code') ? document.getElementById('current-area-code').value : '';

            if (!codeInput || !labelInput || !targetContainer) return;

            let code = codeInput.value.trim().toUpperCase();
            const label = labelInput.value.trim() || `Rute ${code}`;

            // 1. Validasi Input Kosong
            if (!code) {
                alert("Kode Rute kosong!");
                codeInput.focus();
                return;
            }
            // 2. Validasi Format (Hanya Huruf Angka)
            if (!/^[A-Z]+$/.test(code)) {
                alert("Kode Rute hanya boleh Huruf (A-Z). Angka tidak diperbolehkan.");
                codeInput.classList.add('ring-2', 'ring-red-500', 'bg-red-50');
                codeInput.focus();
                return;
            }

            // 3. VALIDASI DUPLIKAT (Client Side Check)
            let isDuplicate = false;
            const allCodeInputs = document.querySelectorAll('input[name*="[code]"]');

            allCodeInputs.forEach(input => {
                if (input.id !== 'new-route-code') {
                    if (input.value.trim().toUpperCase() === code) {
                        isDuplicate = true;
                    }
                }
            });

            if (isDuplicate) {
                alert(`Gagal: Kode Rute "${code}" sudah ada di daftar ini (Mohon cek tabel).`);

                // Efek Visual Error
                codeInput.classList.add('ring-2', 'ring-red-500', 'bg-red-50', 'text-red-900');
                codeInput.focus();

                // Hilangkan efek merah setelah 3 detik
                setTimeout(() => {
                    codeInput.classList.remove('ring-2', 'ring-red-500', 'bg-red-50', 'text-red-900');
                }, 3000);

                return; // STOP! Jangan buat baris baru.
            }

            // 4. Jika Lolos, Masukkan ke Tabel
            const newItemHTML = `
                <tr class="animate-fade-in-down route-item bg-green-50 dark:bg-green-900/20" data-code="${code}">
                    <td class="text-center p-2 border-r dark:border-gray-700">
                        <span class="font-bold text-gray-400 text-xs">${areaCode}</span>
                        <input type="hidden" name="settings[kddk_config_data][routes_manage][${areaCode}][new_${Date.now()}][code]" value="${code}">
                    </td>
                    <td class="text-center p-2 border-r dark:border-gray-700 font-bold text-indigo-600">${code.charAt(0)}</td>
                    <td class="text-center p-2 border-r dark:border-gray-700 font-bold text-indigo-600">${code.charAt(1)}</td>
                    <td class="p-2 border-r dark:border-gray-700">
                        <input type="text" name="settings[kddk_config_data][routes_manage][${areaCode}][new_${Date.now()}][label]" value="${label}" 
                               class="w-full text-sm border-gray-300 rounded focus:ring-indigo-500 route-label-input">
                        <span class="text-[9px] text-green-600 font-bold block mt-1">*Baru (Belum Disimpan)</span>
                    </td>
                    <td class="text-center p-2">
                        <button type="button" onclick="this.closest('tr').remove();" 
                                class="text-red-500 hover:text-red-700"><i class="fas fa-trash-alt"></i></button>
                    </td>
                </tr>
            `;
            targetContainer.insertAdjacentHTML('afterbegin', newItemHTML);

            // Reset Form & Update Counter
            codeInput.value = '';
            labelInput.value = '';
            codeInput.classList.remove('ring-2', 'ring-red-500', 'bg-red-50'); // Pastikan bersih
            codeInput.focus();

            const countEl = document.getElementById('total-routes-count');
            if (countEl) countEl.innerText = parseInt(countEl.innerText) + 1;
        },

        // --- 4. SYSTEM UTILS ---
        clearAuditLog: function (isAll = false) {
            const inputDays = document.querySelector('input[name="system_audit_retention_days"]');
            const days = inputDays ? inputDays.value : 60;
            const confirmMsg = isAll ? "Hapus SEMUA log?" : `Hapus log > ${days} hari?`;

            App.Utils.showCustomConfirm('Konfirmasi', confirmMsg, () => {
                document.body.style.cursor = 'wait';
                fetch('/settings/clear-audit', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                    body: JSON.stringify({ retention_days: isAll ? 0 : days, mode: isAll ? 'all' : 'old' })
                }).then(res => res.json()).then(data => {
                    document.body.style.cursor = 'default';
                    alert(data.message);
                }).catch(err => { document.body.style.cursor = 'default'; alert("Error."); });
            });
        },

        // --- 5. EDIT AREA (Baru) ---
        editAreaMode: function (code, label, category) {
            const codeIn = document.getElementById('new-area-code');
            const labelIn = document.getElementById('new-area-label');
            const catIn = document.getElementById('new-area-category');

            if (codeIn) { codeIn.value = code; codeIn.disabled = true; }
            if (labelIn) labelIn.value = label;
            if (catIn) catIn.value = category;

            document.getElementById('btn-add-area').classList.add('hidden');
            document.getElementById('btn-update-area').classList.remove('hidden');
            document.getElementById('btn-cancel-edit').classList.remove('hidden');

            const title = document.getElementById('area-form-title');
            if (title) {
                title.innerText = `Edit Area: ${code}`;
                title.className = "text-xs text-orange-600 font-bold animate-pulse";
            }
            if (labelIn) labelIn.focus();
        },

        cancelEditArea: function () {
            const codeIn = document.getElementById('new-area-code');
            const labelIn = document.getElementById('new-area-label');
            const catIn = document.getElementById('new-area-category');

            if (codeIn) { codeIn.value = ''; codeIn.disabled = false; }
            if (labelIn) labelIn.value = '';
            if (catIn) catIn.value = '';

            document.getElementById('btn-add-area').classList.remove('hidden');
            document.getElementById('btn-update-area').classList.add('hidden');
            document.getElementById('btn-cancel-edit').classList.add('hidden');

            const title = document.getElementById('area-form-title');
            if (title) {
                title.innerText = "Manajemen Area";
                title.className = "text-xs text-gray-500";
            }
        },

        submitUpdateArea: function () {
            const code = document.getElementById('new-area-code').value;
            const label = document.getElementById('new-area-label').value.trim();
            const category = document.getElementById('new-area-category').value.trim() || 'Umum';

            if (!label) { alert("Nama Area wajib diisi!"); return; }

            document.body.style.cursor = 'wait';

            fetch('/settings/update-area', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ code, label, category })
            })
                .then(res => res.json())
                .then(data => {
                    document.body.style.cursor = 'default';
                    if (data.success) {
                        alert("Data Area berhasil diperbarui.");
                        // Refresh Tab via Tab Manager
                        const activeTab = App.Utils.getActiveTabName();
                        const tabBtn = document.querySelector(`.tab-button[data-tab-name="${activeTab}"]`);
                        if (tabBtn) {
                            const url = tabBtn.dataset.url || tabBtn.href;
                            const sep = url.includes('?') ? '&' : '?';
                            App.Tabs.loadTabContent(activeTab, url + sep + '_t=' + new Date().getTime());
                        } else {
                            window.location.reload();
                        }
                    } else {
                        alert(data.message);
                    }
                })
                .catch(err => {
                    document.body.style.cursor = 'default';
                    alert("Terjadi kesalahan koneksi.");
                });
        },


        uploadApkFile: function (input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const formData = new FormData();
                formData.append('apk_file', file);
                // Ambil CSRF Token dari meta tag head
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                formData.append('_token', csrfToken);

                // UI Elements
                const btnLabel = document.getElementById('apk_btn_label');
                const progressBar = document.getElementById('apk_progress_bar');
                const originalText = '<i class="fas fa-cloud-upload-alt"></i> Upload APK Baru'; // Text default

                // State Loading
                btnLabel.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengupload...';
                progressBar.style.width = '30%';

                // AJAX Request (Fetch)
                // Pastikan route URL ini sesuai dengan definisi route di Laravel Anda
                fetch('/settings/upload-apk', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        progressBar.style.width = '100%';
                        if (data.success) {
                            btnLabel.innerHTML = '<i class="fas fa-check"></i> Berhasil!';
                            // Reload halaman atau update UI parsial setelah 1 detik
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            alert('Gagal: ' + (data.message || 'Terjadi kesalahan'));
                            btnLabel.innerHTML = originalText;
                            progressBar.style.width = '0';
                            input.value = ''; // Reset input file
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Gagal Upload. Cek koneksi atau ukuran file (Max 50MB).');
                        btnLabel.innerHTML = originalText;
                        progressBar.style.width = '0';
                        input.value = '';
                    });
            }
        },

        filterRoutesTable: function () {
            const input = document.getElementById('route-search-input');
            if (!input) return;

            const filter = input.value.toLowerCase();
            const rows = document.querySelectorAll('.route-row-item');

            rows.forEach(row => {
                // Ambil data search dari atribut data-search
                const text = row.dataset.search || '';
                if (text.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        },

        // --- 6. AUDIT LOG VIEWER (YANG KURANG) ---
        showAuditLogs: function () {
            // 1. Siapkan Konten Modal (Skeleton Loading)
            const modalContent = `
                <div class="p-6 h-full flex flex-col">
                    <div class="flex justify-between items-center mb-4 border-b pb-4 flex-none">
                        <h3 class="text-xl font-bold text-gray-800 dark:text-white">
                            <i class="fas fa-history text-indigo-500 mr-2"></i> Riwayat Aktivitas Sistem
                        </h3>
                        <button onclick="document.getElementById('main-modal').classList.add('hidden')" class="text-gray-400 hover:text-red-500 text-2xl">&times;</button>
                    </div>
                    
                    <div id="audit-log-table-container" class="flex-1 overflow-y-auto custom-scrollbar min-h-0">
                        <div class="flex justify-center py-10 text-gray-400 animate-pulse">
                            <i class="fas fa-spinner fa-spin mr-2"></i> Memuat data log...
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t flex justify-end flex-none">
                        <button onclick="document.getElementById('main-modal').classList.add('hidden')" 
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 font-bold transition">Tutup</button>
                    </div>
                </div>
            `;

            // 2. Tampilkan Modal Generik
            const modal = document.getElementById('main-modal');
            const modalBody = document.getElementById('modal-content');

            if (modal && modalBody) {
                modalBody.innerHTML = modalContent;
                modal.classList.remove('hidden');
            } else {
                console.error("Modal element #main-modal not found!");
                return;
            }

            // 3. Fetch Data dari Server (URL SUDAH BENAR: /settings/logs)
            fetch('/settings/logs')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.renderLogTable(data.logs);
                    } else {
                        document.getElementById('audit-log-table-container').innerHTML =
                            '<p class="text-red-500 text-center py-4">Gagal memuat data log.</p>';
                    }
                })
                .catch(err => {
                    console.error(err);
                    const container = document.getElementById('audit-log-table-container');
                    if (container) container.innerHTML = '<p class="text-red-500 text-center py-4">Terjadi kesalahan koneksi.</p>';
                });
        },

        renderLogTable: function (logs) {
            const container = document.getElementById('audit-log-table-container');
            if (!container) return;

            if (logs.length === 0) {
                container.innerHTML = '<div class="text-center py-12 text-gray-400 bg-gray-50 dark:bg-gray-800 rounded-lg">Belum ada riwayat aktivitas.</div>';
                return;
            }

            let html = `
                <table class="w-full text-sm text-left border-collapse">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50 dark:bg-gray-700 sticky top-0 z-10 shadow-sm">
                        <tr>
                            <th class="px-4 py-3 border-b dark:border-gray-600">Waktu</th>
                            <th class="px-4 py-3 border-b dark:border-gray-600">User</th>
                            <th class="px-4 py-3 border-b dark:border-gray-600">Setting</th>
                            <th class="px-4 py-3 border-b dark:border-gray-600">Perubahan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-800">
            `;

            logs.forEach(log => {
                // Style badge untuk aksi
                let actionColor = 'bg-blue-100 text-blue-700 border-blue-200';

                html += `
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <td class="px-4 py-3 whitespace-nowrap align-top w-32">
                            <div class="font-bold text-gray-700 dark:text-gray-300 text-xs">${log.time_ago}</div>
                            <div class="text-[10px] text-gray-400">${log.date_formatted}</div>
                        </td>
                        <td class="px-4 py-3 align-top w-40">
                            <div class="font-bold text-gray-800 dark:text-white text-xs truncate max-w-[150px]" title="${log.user_name || 'System'}">
                                <i class="fas fa-user-circle mr-1 text-gray-400"></i> ${log.user_name || 'System'}
                            </div>
                        </td>
                        <td class="px-4 py-3 align-top w-48">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border ${actionColor}">
                                UPDATE
                            </span>
                            <div class="mt-1 text-[11px] text-gray-500 font-mono break-all leading-tight bg-gray-50 dark:bg-gray-900 p-1 rounded border border-gray-100 dark:border-gray-700">
                                ${log.setting_key}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs align-top">
                            <div class="grid grid-cols-1 gap-1">
                                ${log.old_value ? `
                                    <div class="bg-red-50 text-red-600 p-1.5 rounded border border-red-100 break-all">
                                        <i class="fas fa-minus mr-1 text-[9px]"></i> <span class="line-through opacity-70">${log.old_value}</span>
                                    </div>` : ''}
                                <div class="bg-green-50 text-green-700 p-1.5 rounded border border-green-100 break-all font-medium shadow-sm">
                                    <i class="fas fa-plus mr-1 text-[9px]"></i> ${log.new_value}
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        },

        // --- 7. CONFIG SNAPSHOTS (BACKUP & RESTORE) ---
        showConfigHistory: function () {
            // 1. UI Skeleton
            const modalContent = `
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4 border-b pb-4">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                            <i class="fas fa-history text-indigo-500 mr-2"></i> Riwayat Konfigurasi Area
                        </h3>
                        <button onclick="document.getElementById('main-modal').classList.add('hidden')" class="text-2xl">&times;</button>
                    </div>
                    <div id="snapshot-list-container" class="space-y-3 max-h-[60vh] overflow-y-auto pr-2">
                        <div class="text-center py-8 text-gray-400"><i class="fas fa-spinner fa-spin"></i> Memuat backup...</div>
                    </div>
                </div>
            `;

            const modal = document.getElementById('main-modal');
            document.getElementById('modal-content').innerHTML = modalContent;
            modal.classList.remove('hidden');

            // 2. Fetch Data
            fetch('/settings/snapshots?key=kddk_config_data')
                .then(res => res.json())
                .then(resp => {
                    const container = document.getElementById('snapshot-list-container');
                    if (resp.data.length === 0) {
                        container.innerHTML = '<div class="text-center py-8 text-gray-400 italic">Belum ada data backup tersimpan.</div>';
                        return;
                    }

                    let html = '';
                    resp.data.forEach(item => {
                        html += `
                            <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:border-indigo-400 transition">
                                <div>
                                    <div class="font-bold text-gray-800 dark:text-white text-sm">
                                        Versi: ${item.date} 
                                        <span class="text-[10px] bg-gray-100 text-gray-500 px-2 rounded-full ml-2">${item.size}</span>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <i class="fas fa-user-edit mr-1"></i> ${item.user} &bull; ${item.ago}
                                    </div>
                                </div>
                                <button onclick="window.settingsHandler.confirmRestore(${item.id}, '${item.date}')" 
                                    class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded border border-indigo-200 transition">
                                    <i class="fas fa-undo-alt mr-1"></i> Restore
                                </button>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                });
        },

        confirmRestore: function (id, dateStr) {
            if (!confirm(`⚠️ PERINGATAN KERAS!\n\nAnda akan mengembalikan konfigurasi ke versi tanggal:\n[ ${dateStr} ]\n\nData konfigurasi saat ini akan ditimpa. Lanjutkan?`)) return;

            document.body.style.cursor = 'wait';
            fetch('/settings/restore', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ id: id })
            })
                .then(res => res.json())
                .then(data => {
                    document.body.style.cursor = 'default';
                    if (data.success) {
                        alert('Sukses! Halaman akan dimuat ulang.');
                        location.reload();
                    } else {
                        alert('Gagal: ' + data.message);
                    }
                })
                .catch(err => {
                    document.body.style.cursor = 'default';
                    alert('Terjadi kesalahan koneksi.');
                });
        },

        // --- 8. DEVICE MANAGER ---
        showDeviceManager: function () {
            const modalContent = `
                <div class="p-6 h-full flex flex-col">
                    <div class="flex justify-between items-center mb-4 border-b pb-4 flex-none">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                            <i class="fas fa-shield-alt text-indigo-500 mr-2"></i> Perangkat Terdaftar
                        </h3>
                        <button onclick="document.getElementById('main-modal').classList.add('hidden')" class="text-2xl text-gray-400">&times;</button>
                    </div>
                    <div id="device-list-container" class="space-y-3 flex-1 overflow-y-auto pr-2 custom-scrollbar">
                        <div class="text-center py-10 text-gray-400 animate-pulse">
                            <i class="fas fa-spinner fa-spin mr-2"></i> Memuat data perangkat...
                        </div>
                    </div>
                </div>
            `;

            const modal = document.getElementById('main-modal');
            document.getElementById('modal-content').innerHTML = modalContent;
            modal.classList.remove('hidden');

            this.loadDevices();
        },

        loadDevices: function () {
            fetch('/settings/devices')
                .then(res => res.json())
                .then(resp => {
                    const container = document.getElementById('device-list-container');
                    if (!resp.success || resp.data.length === 0) {
                        container.innerHTML = '<div class="text-center py-8 text-gray-400">Belum ada perangkat yang login.</div>';
                        return;
                    }

                    let html = '<div class="grid gap-3">';
                    resp.data.forEach(dev => {
                        const statusClass = dev.is_blocked
                            ? 'bg-red-50 border-red-200 opacity-75'
                            : 'bg-white border-gray-200';

                        const btnText = dev.is_blocked
                            ? '<i class="fas fa-unlock mr-1"></i> Buka Blokir'
                            : '<i class="fas fa-ban mr-1"></i> Blokir';

                        const btnColor = dev.is_blocked
                            ? 'bg-white border-red-200 text-red-600 hover:bg-red-50'
                            : 'bg-white border-gray-300 text-gray-700 hover:text-red-600 hover:border-red-300';

                        html += `
                            <div class="flex items-center justify-between p-3 rounded-lg border ${statusClass} shadow-sm transition">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500">
                                        <i class="fas fa-mobile-alt text-lg"></i>
                                    </div>
                                    <div>
                                        <div class="font-bold text-gray-800 text-sm">
                                            ${dev.user_name} 
                                            <span class="font-normal text-gray-500 text-xs">(${dev.model})</span>
                                        </div>
                                        <div class="text-xs text-gray-400 flex gap-2 mt-0.5">
                                            <span>v${dev.app_ver || '?'}</span> &bull; 
                                            <span>${dev.ip || 'Unknown IP'}</span> &bull; 
                                            <span>${dev.last_seen}</span>
                                        </div>
                                    </div>
                                </div>
                                <button onclick="window.settingsHandler.toggleBlockDevice(${dev.id})" 
                                    class="px-3 py-1.5 text-xs font-bold rounded border ${btnColor} transition">
                                    ${btnText}
                                </button>
                            </div>
                        `;
                    });
                    html += '</div>';
                    container.innerHTML = html;
                });
        },

        toggleBlockDevice: function (id) {
            if (!confirm('Ubah status blokir perangkat ini?')) return;

            // UI Optimistic Update (Loading)
            document.body.style.cursor = 'wait';

            fetch('/settings/device-block', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ id: id })
            })
                .then(res => res.json())
                .then(data => {
                    document.body.style.cursor = 'default';
                    if (data.success) {
                        this.loadDevices(); // Reload list
                    } else {
                        alert(data.message);
                    }
                })
                .catch(err => {
                    document.body.style.cursor = 'default';
                    alert('Gagal koneksi.');
                });
        }
    };

    // --- EVENT DELEGATION (PENTING untuk konten yang di-load AJAX) ---
    document.addEventListener('DOMContentLoaded', () => {

        // Delegasi untuk Search Filter di Tab Area
        document.body.addEventListener('input', (e) => {
            if (e.target.id === 'area-search-filter') {
                window.settingsHandler.filterAreas();
            }
            if (e.target.id === 'route-search-input') {
                // Filter Table Rute
                const filter = e.target.value.toLowerCase();
                const rows = document.querySelectorAll('.route-row-item');
                rows.forEach(row => {
                    const text = row.dataset.search || '';
                    row.style.display = text.includes(filter) ? '' : 'none';
                });
            }
        });

        // Delegasi untuk Navigasi Tab Internal (General, Parameters, System, Areas)
        // Karena Settings Content di-load via AJAX oleh Tab Manager, kita pakai event delegation di Body
        document.body.addEventListener('click', (e) => {
            const tabBtn = e.target.closest('.tab-toggle-btn');

            if (tabBtn) {
                e.preventDefault();
                const targetId = tabBtn.dataset.target;
                if (!targetId) return;

                // Cari parent container terdekat agar tidak konflik dengan tab lain
                const container = tabBtn.closest('#settings-content') || document;

                // 1. Sembunyikan semua konten tab settings
                container.querySelectorAll('.setting-tab-content').forEach(el => el.classList.add('hidden'));

                // 2. Tampilkan konten target
                const contentEl = container.querySelector('#content-tab-' + targetId);
                if (contentEl) contentEl.classList.remove('hidden');

                // 3. Update Style Tombol
                container.querySelectorAll('.tab-toggle-btn').forEach(btn => {
                    btn.classList.remove('text-indigo-600', 'dark:text-indigo-400', 'border-indigo-500', 'font-bold');
                    btn.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'font-medium');
                });

                tabBtn.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'font-medium');
                tabBtn.classList.add('text-indigo-600', 'dark:text-indigo-400', 'border-indigo-500', 'font-bold');
            }

            // Handler Hapus Rute
            if (e.target.closest('.remove-route-row')) {
                const btn = e.target.closest('.remove-route-row');
                if (confirm('Hapus rute ini dari daftar simpan?')) {
                    btn.closest('tr').remove();
                    // Trigger dirty state pada tombol simpan
                    const saveBtn = document.querySelector('#routes_manage_content button[type="submit"]'); // Selector kasar
                    // Logic dirty state ada di html onclick biasanya, atau kita biarkan submit menghandle
                }
            }
        });
    });

})();