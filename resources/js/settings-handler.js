// resources/js/settings-handler.js

document.addEventListener('DOMContentLoaded', () => {
    
    let globalIndex = new Date().getTime();

    // ============================================================
    // A. EVENT LISTENERS GLOBAL (DELEGATION)
    // ============================================================
    document.addEventListener('click', function(e) {
        
        // 1. TAB SWITCHER
        const tabBtn = e.target.closest('.tab-toggle-btn');
        if (tabBtn) {
            e.preventDefault();
            const target = tabBtn.dataset.target;
            document.querySelectorAll('.setting-tab-content').forEach(el => el.classList.add('hidden'));
            document.getElementById('content-tab-' + target).classList.remove('hidden');
            
            document.querySelectorAll('.tab-toggle-btn').forEach(b => {
                b.classList.remove('text-indigo-600', 'border-b-2', 'border-indigo-600', 'dark:text-indigo-500', 'dark:border-indigo-500');
                b.classList.add('border-transparent', 'hover:text-gray-600', 'hover:border-gray-300');
            });
            tabBtn.classList.add('text-indigo-600', 'border-b-2', 'border-indigo-600');
            tabBtn.classList.remove('border-transparent', 'hover:text-gray-600', 'hover:border-gray-300');
            return;
        }

        // 2. TOMBOL TAMBAH (Memanggil fungsi window)
        if (e.target.closest('#add-area-row-btn')) {
             e.preventDefault();
             window.addAreaRow();
             return;
        }

        if (e.target.closest('#add-route-manager-btn')) {
             e.preventDefault();
             const areaCode = e.target.closest('#add-route-manager-btn').dataset.areaCode;
             window.addRouteRowManager(areaCode);
             return;
        }

        // 3. HAPUS AREA (PARENT)
        const deleteParentButton = e.target.closest('.remove-area-row, .btn-delete-parent');
        if (deleteParentButton) {
            e.preventDefault();
            const row = deleteParentButton.closest('tr');
            const codeInput = row.querySelector('input[name*="[code]"]');
            const areaCode = codeInput ? codeInput.value : '';

            if (!areaCode) {
                // Data baru (belum ada kode) -> Hapus Langsung
                removeAreaRowDOM(row);
                return;
            }

            // Data lama -> Validasi Server
            const confirmMsg = `Yakin ingin menghapus Area <strong>${areaCode}</strong>?`;
            if (typeof App !== 'undefined' && App.Utils) {
                App.Utils.showCustomConfirm('Hapus Area?', confirmMsg, () => {
                     performDeleteConfigItem('area', { area_code: areaCode }, () => removeAreaRowDOM(row));
                });
            } else {
                if (confirm(`Hapus Area ${areaCode}?`)) {
                    performDeleteConfigItem('area', { area_code: areaCode }, () => removeAreaRowDOM(row));
                }
            }
            return;
        }
        
        // 4. HAPUS RUTE (CHILD)
        const deleteRouteButton = e.target.closest('.remove-route-row, .btn-delete-child');
        if (deleteRouteButton) {
            e.preventDefault();
            
            const row = deleteRouteButton.closest('tr');
            const codeInput = row.querySelector('.route-code-real') || row.querySelector('input[name*="[code]"]'); 
            const routeCode = codeInput ? codeInput.value : '';
            
            // Cari Area Code dari hidden input (Halaman Manage Routes)
            const hiddenAreaInput = document.querySelector('input[name="area_code_target"]');
            const areaCode = hiddenAreaInput ? hiddenAreaInput.value : null;

            if (row.classList.contains('route-item-new')) {
                // Hapus langsung dari layar (DOM)
                row.remove();
                if(typeof window.updateRouteCount === 'function') window.updateRouteCount();
                checkFormValidity(); 
                return; 
            }

            if (!routeCode) {
                row.remove();
                if(typeof window.updateRouteCount === 'function') window.updateRouteCount();
                return;
            }

            const executeDelete = () => {
                if (areaCode) {
                    performDeleteConfigItem('route', { area_code: areaCode, route_code: routeCode }, () => {
                        row.remove();
                        if(typeof window.updateRouteCount === 'function') window.updateRouteCount();
                        checkFormValidity();
                    });
                } else {
                    // Fallback jika tidak tahu area-nya (misal di nested view)
                    row.remove();
                    if(typeof window.updateRouteCount === 'function') window.updateRouteCount();
                    checkFormValidity();
                }
            };

            if (typeof App !== 'undefined' && App.Utils) {
                App.Utils.showCustomConfirm('Hapus Rute?', `Hapus Rute <strong>${routeCode}</strong>?`, executeDelete);
            } else {
                if(confirm(`Hapus Rute ${routeCode}?`)) executeDelete();
            }
            return;
        }

        // 5. TOGGLE GROUP RUTE (Baru)
        const groupHeader = e.target.closest('.group-header');
        if (groupHeader) {
            e.preventDefault();
            const targetId = groupHeader.dataset.target;
            const icon = groupHeader.querySelector('.icon-chevron');
            const content = document.getElementById(targetId);
            
            if (content) {
                content.classList.toggle('hidden');
                if(icon) icon.classList.toggle('rotate-90');
            }
            return;
        }
    });

    // ============================================================
    // B. LOGIKA PENCARIAN (RUTE)
    // ============================================================
    document.addEventListener('input', function(e) {
        // 1. Pencarian Rute
        if (e.target.id === 'route-search-input') {
            const term = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.route-item');
            const groups = document.querySelectorAll('.route-group-body');
            const headers = document.querySelectorAll('.group-header');
            let visibleCount = 0;

            if (term === '') {
                rows.forEach(row => row.style.display = '');
                // Tutup semua group kembali (atau biarkan status terakhir)
                groups.forEach(g => g.classList.add('hidden'));
                document.querySelectorAll('.group-header .icon-chevron').forEach(i => i.classList.remove('rotate-90'));
                // Show headers
                headers.forEach(h => h.style.display = '');
                document.getElementById('no-routes-found').classList.add('hidden');
                return;
            }

            // Sembunyikan semua header dulu
            headers.forEach(h => h.style.display = 'none');

            rows.forEach(row => {
                const codeInput = row.querySelector('.route-code-input');
                const labelInput = row.querySelector('.route-label-input');
                
                const codeText = codeInput ? codeInput.value.toLowerCase() : '';
                const labelText = labelInput ? labelInput.value.toLowerCase() : '';
                const isMatch = codeText.includes(term) || labelText.includes(term);

                if (isMatch) {
                    row.style.display = '';
                    visibleCount++;
                    
                    // Buka Group Parent
                    const parentGroup = row.closest('.route-group-body');
                    if (parentGroup) {
                        parentGroup.classList.remove('hidden');
                        // Tampilkan Header Group ini
                        const groupId = parentGroup.id;
                        const header = document.querySelector(`.group-header[data-target="${groupId}"]`);
                        if (header) {
                            header.style.display = '';
                            header.querySelector('.icon-chevron')?.classList.add('rotate-90');
                        }
                    }
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Handle No Result
            const noResultDiv = document.getElementById('no-routes-found');
            if (noResultDiv) noResultDiv.classList.toggle('hidden', visibleCount > 0);
        }

        // 2. Validasi Duplikat Kode Rute (Real-time)
        if (e.target.classList.contains('route-code-input')) {
            validateRouteCode(e.target);
        }

        // LOGIKA SINKRONISASI INPUT SPLIT
        if (e.target.classList.contains('split-input')) {
            const input = e.target;
            const row = input.closest('tr');
            
            const val1 = row.querySelector('.part-1').value || '';
            const val2 = row.querySelector('.part-2').value || '';
            const realInput = row.querySelector('.route-code-real');
            
            // Gabungkan nilai ke input hidden
            realInput.value = val1 + val2;

            // Auto Focus: Pindah ke kotak sebelah jika sudah isi 1 huruf
            if (input.classList.contains('part-1') && input.value.length === 1) {
                const next = row.querySelector('.part-2');
                if(next) next.focus();
            }
            // Backspace: Pindah balik jika kosong
            // (Note: ini butuh event 'keydown', tapi untuk simplifikasi input sudah cukup oke)

            // Trigger Validasi Duplikat
            validateRouteCode(realInput);
        }

    });

    // ============================================================
    // C. FUNGSI GLOBAL (WINDOW SCOPE)
    // ============================================================
    window.manageAreaRoutes = function(areaCode) {
        const url = `/settings/manage-routes/${areaCode}`; 
        const tabName = `Rute [${areaCode}]`;
        if (typeof App !== 'undefined' && App.Tabs) {
            App.Tabs.createTab(tabName, url, true, true);
        } else {
            window.location.href = url;
        }
    };

    // Validasi Duplikat & UI Error
    function validateRouteCode(hiddenInput) {
        const currentValue = hiddenInput.value.toUpperCase();
        const row = hiddenInput.closest('tr');

        const form = hiddenInput.closest('form');
        if (!form) return;
        
        // Ambil elemen visual (Kotak A dan Kotak 1)
        const visualInputs = row.querySelectorAll('.split-input');
        
        const labelCell = row.querySelector('.route-label-input')?.closest('td');
        // 1. Reset State (Hapus Merah & Pesan Error lama)
        visualInputs.forEach(el => {
            el.classList.remove('border-red-500', 'ring-red-500', 'focus:border-red-500', 'focus:ring-red-500', 'bg-red-50');
        });
        if (labelCell) {
            const existingMsg = labelCell.querySelector('.validation-error');
            if (existingMsg) existingMsg.remove();
        }
        hiddenInput.classList.remove('is-invalid');

        // Jika kosong atau belum lengkap 2 digit, reset dan keluar
        if (!currentValue || currentValue.length < 2) {
            checkFormValidity(form); 
            return; 
        }

        // 2. CEK DUPLIKAT (GLOBAL DALAM FORM)
        // Cari semua input hidden kode di seluruh form ini
        const allHiddenInputs = form.querySelectorAll('.route-code-real');
        let isDuplicate = false;

        for (let other of allHiddenInputs) {
            // Jangan bandingkan dengan diri sendiri
            if (other !== hiddenInput) {
                if (other.value.toUpperCase() === currentValue) {
                    isDuplicate = true;
                    break; // Ketemu satu saja sudah cukup
                }
            }
        }

        // 3. JIKA DUPLIKAT
        if (isDuplicate) {
            // Tandai hidden input ini sebagai invalid
            hiddenInput.classList.add('is-invalid');

            // Merahkan kotak visual
            visualInputs.forEach(el => {
                el.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-500', 'bg-red-50');
            });
            
            // Tampilkan Pesan
            if (labelCell) {
                const msg = document.createElement('div');
                msg.className = 'validation-error text-[10px] text-red-600 font-bold mt-1 flex items-center animate-pulse';
                msg.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> Kode sudah digunakan!';
                labelCell.appendChild(msg);
            }
        }

        // Cek Validitas Form Global
        checkFormValidity(form);
    }

    // --- FUNGSI CEK STATUS TOMBOL SIMPAN ---
    function checkFormValidity(formElement = null) {
        // Jika form tidak dikirim, cari form ajax terdekat yang terlihat
        const form = formElement || document.querySelector('form.ajax-form'); 
        if(!form) return;

        const submitBtn = form.querySelector('button[type="submit"]');
        if(!submitBtn) return;

        // Cek apakah ada elemen invalid atau pesan error di dalam form ini
        const invalidInputs = form.querySelectorAll('.is-invalid');
        const errorMessages = form.querySelectorAll('.validation-error');
        
        // Cek juga apakah ada baris 'BARU' yang inputnya masih kosong (optional, tapi bagus untuk UX)
        // const emptyNewInputs = form.querySelectorAll('.route-item-new .route-code-real[value=""]');

        if (invalidInputs.length > 0 || errorMessages.length > 0) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            
            // Simpan teks asli jika belum ada
            if (!submitBtn.dataset.originalText) submitBtn.dataset.originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-ban mr-2"></i> Perbaiki Data Duplikat';
        } else {
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            // Kembalikan teks asli
            if (submitBtn.dataset.originalText) submitBtn.innerHTML = submitBtn.dataset.originalText;
        }
    }

    // Tambah Baris Area (Induk)
    window.addAreaRow = function() {
        const container = document.getElementById('area-rows-container');
        const mainContainer = document.getElementById('settings-main-container');
        
        let isAdmin = false;
        let existingCodes = [];
        
        if (mainContainer) {
            isAdmin = mainContainer.dataset.isAdmin === 'true';
            try { existingCodes = JSON.parse(mainContainer.dataset.existingCodes || '[]'); } catch (e) {}
        }

        let lastCode = ''; 
        const allCodeInputs = container.querySelectorAll('input[name*="[code]"]');
        if (allCodeInputs.length > 0) {
            const lastInput = allCodeInputs[allCodeInputs.length - 1];
            if (lastInput.value && lastInput.value.length === 2) {
                lastCode = lastInput.value.toUpperCase();
            }
        } 
        if (!lastCode && existingCodes.length > 0) {
            lastCode = existingCodes[existingCodes.length - 1];
        }

        let nextCode = (lastCode === '') ? 'AA' : getNextAlphabetCode(lastCode);
        const inputValue = isAdmin ? '' : nextCode;
        const readOnlyAttr = isAdmin ? '' : 'readonly';
        const inputClass = isAdmin 
            ? 'bg-white dark:bg-gray-800 dark:text-white' 
            : 'bg-gray-100 dark:bg-gray-700 cursor-not-allowed text-gray-500 dark:text-gray-400';

        globalIndex++;
        const idx = globalIndex;

        const row = `
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition border-b border-gray-200 dark:border-gray-700">
                <td class="p-3 text-center">
                    <input type="text" name="settings[kddk_config_data][areas][${idx}][code]" 
                           value="${inputValue}" 
                           maxlength="2" placeholder="XX" 
                           class="w-16 text-center font-bold uppercase rounded-md border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-indigo-500 ${inputClass}" 
                           required ${readOnlyAttr} 
                           oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '')">
                </td>
                <td class="p-3">
                    <input type="text" name="settings[kddk_config_data][areas][${idx}][label]" 
                           placeholder="Keterangan Area" 
                           class="w-full rounded-md border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-indigo-500 py-2 px-3" 
                           required>
                </td>
                <td class="p-3 text-center">
                    <span class="text-xs text-gray-400 italic">Simpan dulu</span>
                </td>
                <td class="p-3 text-center">
                    <button type="button" class="text-red-600 hover:text-red-800 remove-area-row">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
        `;
        container.insertAdjacentHTML('beforeend', row);
    };

    // Tambah Baris Rute (Detail Manager) - TOP INSERT & CEK BELUM DISIMPAN
    window.addRouteRowManager = function(areaCode) {
        let container = document.getElementById('route-new-rows');
        if (!container) container = document.getElementById('route-rows-container');
        if (!container) return;

        const existingNewRow = document.querySelector('.route-item-new'); // Cari di dokumen
        if (existingNewRow) {
            if (typeof App !== 'undefined' && App.Utils) {
                App.Utils.displayNotification('error', "Mohon simpan rute baru sebelumnya terlebih dahulu.");
            } else {
                alert("Mohon simpan rute baru sebelumnya terlebih dahulu.");
            }
            existingNewRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const input = existingNewRow.querySelector('.part-1');
            if(input) input.focus();
            return;
        }

        // Sembunyikan pesan kosong jika ada
        const noDataMsg = document.getElementById('no-data-msg');
        if(noDataMsg) noDataMsg.classList.add('hidden');

        globalIndex++;
        const idx = globalIndex;
        
        // TEMPLATE HTML BARIS BARU (Sesuai Desain Baru)
        const row = `
            <tr class="hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors animate-fade-in-down route-item route-item-new bg-indigo-50/50">
                
                <td class="text-center align-middle border-r dark:border-gray-700 p-1">
                    <span class="block w-full text-center font-bold font-mono uppercase text-gray-400 text-[10px] select-none">
                        ${areaCode}
                    </span>
                    
                    <input type="hidden" 
                           name="settings[kddk_config_data][routes_manage][${areaCode}][${idx}][code]" 
                           class="route-code-real route-code-input" required>
                </td>

                <td class="text-center align-middle border-r dark:border-gray-700 p-0">
                    <input type="text" maxlength="1" placeholder="" 
                           class="w-full h-10 text-center font-bold font-mono uppercase text-sm border-0 bg-transparent focus:ring-0 focus:bg-white dark:focus:bg-gray-600 dark:text-white p-0 split-input part-1" 
                           required oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '')">
                </td>

                <td class="text-center align-middle border-r dark:border-gray-700 p-0">
                    <input type="text" maxlength="1" placeholder="" 
                           class="w-full h-10 text-center font-bold font-mono uppercase text-sm border-0 bg-transparent focus:ring-0 focus:bg-white dark:focus:bg-gray-600 dark:text-white p-0 split-input part-2" 
                           required oninput="this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '')">
                </td>

                <td class="align-middle px-2 py-1">
                    <div class="flex flex-col w-full">
                        <input type="text" name="settings[kddk_config_data][routes_manage][${areaCode}][${idx}][label]" 
                               placeholder="Keterangan..." 
                               class="w-full h-8 text-sm border-gray-300 dark:border-gray-600 rounded focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white route-label-input px-2" 
                               required>
                        <div class="mt-0.5"><span class="text-[9px] text-green-600 bg-green-50 px-1 rounded font-bold">BARU</span></div>
                    </div>
                </td>

                <td class="text-center align-middle p-1">
                    <button type="button" class="text-red-500 hover:text-red-700 p-1.5 rounded-full hover:bg-red-50 dark:hover:bg-red-900/30 transition remove-route-row">
                        <i class="fas fa-trash-alt text-xs"></i>
                    </button>
                </td>
            </tr>
        `;
        
        container.insertAdjacentHTML('afterbegin', row);

        if (typeof window.updateRouteCount === 'function') window.updateRouteCount();
        
        const scrollParent = container.closest('.overflow-y-auto');
        if (scrollParent) {
            scrollParent.scrollTop = 0;
        }

        const newRow = container.firstElementChild;
        if (newRow) {
            const firstInput = newRow.querySelector('.part-1'); // Cari input visual part-1
            if (firstInput) {
                // Gunakan setTimeout agar rendering selesai dulu
                setTimeout(() => firstInput.focus(), 50);
            }
        }
        // Cek validitas (tombol save mungkin perlu dicek ulang)
        const form = container.closest('form');
        checkFormValidity(form);
    };
    
    window.updateRouteCount = function() {
        const countSpan = document.getElementById('total-routes-count');
        const rows = document.querySelectorAll('#route-rows-container tr');
        if(countSpan) countSpan.textContent = rows.length;
    };

    // ============================================================
    // C. FUNGSI HELPER (LOCAL) - INI YANG HILANG SEBELUMNYA
    // ============================================================

    // Helper 1: Hapus Baris DOM (Area)
    function removeAreaRowDOM(row) {
        const nextRow = row.nextElementSibling;
        // Jika ada baris detail/nested (meski di layout baru sudah tidak ada, jaga-jaga)
        if (nextRow && nextRow.id && nextRow.id.startsWith('area-')) {
            nextRow.remove();
        }
        row.remove();
    }

    // Helper 2: AJAX Hapus ke Server
    function performDeleteConfigItem(type, data, onSuccess) {
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        document.body.style.cursor = 'wait';

        fetch('/settings/delete-item', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                type: type,
                area_code: data.area_code,
                route_code: data.route_code || null
            })
        })
        .then(response => response.json())
        .then(result => {
            document.body.style.cursor = 'default';
            if (result.success) {
                if (typeof App !== 'undefined' && App.Utils) {
                    App.Utils.displayNotification('success', result.message, 'Pengaturan');
                }
                if (onSuccess) onSuccess();
            } else {
                // GAGAL (Misal data dipakai)
                if (typeof App !== 'undefined' && App.Utils) {
                    App.Utils.displayNotification('error', result.message, 'Pengaturan');
                } else {
                    alert(result.message);
                }
            }
        })
        .catch(err => {
            document.body.style.cursor = 'default';
            console.error(err);
            alert("Gagal menghapus. Terjadi kesalahan server.");
        });
    }

    // Helper 3: Hitung Kode Berikutnya
    function getNextAlphabetCode(currentCode) {
        if (!currentCode || currentCode.length !== 2) return 'AA';
        let char1 = currentCode.charCodeAt(0);
        let char2 = currentCode.charCodeAt(1);
        char2++;
        if (char2 > 90) { // Z
            char2 = 65; // A
            char1++;
            if (char1 > 90) return 'AA';
        }
        return String.fromCharCode(char1) + String.fromCharCode(char2);
    }

    // --- FUNGSI MEMBERSIHKAN AUDIT LOG ---
    window.clearAuditLogs = function() {
        const daysSelect = document.getElementById('audit-prune-days');
        const days = daysSelect.value;
        const isAll = days === 'all';
        
        let confirmMsg = `Yakin ingin menghapus riwayat aktivitas yang lebih lama dari <strong>${days} hari</strong>?`;
        if (isAll) confirmMsg = `<strong class="text-red-600">PERINGATAN KERAS!</strong><br>Anda akan menghapus <strong>SELURUH</strong> riwayat aktivitas. Tindakan ini tidak bisa dibatalkan.`;

        const executeClear = () => {
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            document.body.style.cursor = 'wait';

            fetch('/settings/clear-audit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ 
                    retention_days: isAll ? 0 : days,
                    mode: isAll ? 'all' : 'old'
                })
            })
            .then(res => res.json())
            .then(data => {
                document.body.style.cursor = 'default';
                if (data.success) {
                    if (typeof App !== 'undefined' && App.Utils) App.Utils.displayNotification('success', data.message);
                    else alert(data.message);
                } else {
                    alert(data.message);
                }
            })
            .catch(err => {
                document.body.style.cursor = 'default';
                alert("Terjadi kesalahan server.");
            });
        };

        if (typeof App !== 'undefined' && App.Utils) {
            App.Utils.showCustomConfirm('Bersihkan Log?', confirmMsg, executeClear);
        } else {
            if (confirm("Yakin ingin menghapus data log ini?")) executeClear();
        }
    }
    
    console.log('Settings Handler Loaded');
});