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