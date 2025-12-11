/**
 * MAPPING HANDLER
 * Mengurus interaksi UI pada halaman Mapping/Validasi
 */

(function() {
    // 1. Fungsi Ganti Tab (KWH vs Bangunan)
    window.switchInspectorTab = function(tab) {
        const btnKwh = document.getElementById('tab-btn-kwh');
        const btnBang = document.getElementById('tab-btn-bangunan');
        const panelKwh = document.getElementById('inspector-kwh');
        const panelBang = document.getElementById('inspector-bangunan');
        
        // Safety check jika elemen belum ada (misal tab belum dimuat)
        if (!btnKwh || !panelKwh) return;

        if (tab === 'kwh') {
            // Aktifkan KWH
            setActiveBtn(btnKwh);
            setInactiveBtn(btnBang);
            panelKwh.classList.remove('hidden');
            panelBang.classList.add('hidden');
        } else {
            // Aktifkan Bangunan
            setActiveBtn(btnBang);
            setInactiveBtn(btnKwh);
            panelBang.classList.remove('hidden');
            panelKwh.classList.add('hidden');
        }
    };

    // Helper Styles
    function setActiveBtn(btn) {
        btn.classList.add('bg-white', 'dark:bg-gray-600', 'text-indigo-600', 'dark:text-white', 'shadow-sm');
        btn.classList.remove('text-gray-500', 'hover:text-gray-700');
    }

    function setInactiveBtn(btn) {
        btn.classList.remove('bg-white', 'dark:bg-gray-600', 'text-indigo-600', 'dark:text-white', 'shadow-sm');
        btn.classList.add('text-gray-500', 'hover:text-gray-700');
    }

    // 2. Fungsi Klik Baris Tabel (Master-Detail)
    window.selectMappingRow = function(row, data) {
        // A. Highlight Baris
        document.querySelectorAll('tbody tr').forEach(tr => {
            tr.classList.remove('bg-indigo-50', 'dark:bg-indigo-900/30');
        });
        if(row) row.classList.add('bg-indigo-50', 'dark:bg-indigo-900/30');

        // B. Update Teks Info
        setText('detail-idpel', data.idpel);
        setText('detail-user', data.user_pendataan);
        
        // C. Update Badge Status (Opsional, jika ingin update badge detail juga)
        const badgeContainer = document.getElementById('detail-status-badge');
        if (badgeContainer) {
             // Logika sederhana untuk teks status, atau biarkan kosong jika sudah di-handle blade
             badgeContainer.innerHTML = `<span class="text-xs font-bold text-gray-600">${data.ket_validasi}</span>`;
        }

        // D. Update Foto
        updatePhotoInspector('kwh', data.foto_kwh);
        updatePhotoInspector('bangunan', data.foto_bangunan);

        // E. Update Peta & Koordinat
        if (data.latitudey && data.longitudex) {
            setText('detail-lat-lon', `${data.latitudey}, ${data.longitudex}`);
            
            const svBtn = document.getElementById('google-street-view-link');
            if(svBtn) {
                svBtn.classList.remove('hidden', 'pointer-events-none', 'opacity-50');
                // Tambahkan event onclick dinamis
                svBtn.onclick = function() {
                    window.open(`https://www.google.com/maps?q&layer=c&cbll=${data.latitudey},${data.longitudex}`, '_blank');
                };
            }

            // F. Trigger Event agar Peta Bergerak (Loose Coupling)
            // Kita mengirim sinyal "tolong fokus ke koordinat ini"
            // File peta (matrix-handler.js atau lainnya) harus mendengarkan event ini.
            window.dispatchEvent(new CustomEvent('map:focus', { 
                detail: { lat: data.latitudey, lng: data.longitudex } 
            }));
        } else {
            setText('detail-lat-lon', '-');
            const svLink = document.getElementById('google-street-view-link');
            if(svLink) svLink.classList.add('hidden', 'pointer-events-none', 'opacity-50');
        }
    };

    // Helper Text
    function setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text || '-';
    }

    // 3. Helper Update Foto Inspector
    function updatePhotoInspector(type, path) {
        const imgEl = document.getElementById(`detail-foto-${type}`);
        const phEl = document.getElementById(`placeholder-foto-${type}`);
        const zoomEl = document.getElementById(`zoom-${type}`);
        
        if (!imgEl || !phEl) return;

        if (path) {
            // Asumsi storage symlink sudah benar
            imgEl.src = `/storage/${path}`;
            imgEl.classList.remove('hidden');
            phEl.classList.add('hidden');
            if(zoomEl) zoomEl.classList.remove('hidden');
        } else {
            imgEl.classList.add('hidden');
            phEl.classList.remove('hidden');
            if(zoomEl) zoomEl.classList.add('hidden');
        }
    }

    // 4. IMAGE VIEWER LOGIC (ZOOM, ROTATE, PAN)
    // State Penyimpanan Posisi
    let imgState = {
        scale: 1,
        rotate: 0,
        pX: 0, // Posisi X (Pan)
        pY: 0, // Posisi Y (Pan)
        isDragging: false,
        startX: 0,
        startY: 0
    };

    const modal = document.getElementById('image-viewer-modal');
    const imgEl = document.getElementById('image-viewer-img');
    const container = document.getElementById('image-container');

    // --- FUNGSI UTAMA ---

    window.viewImage = function(type) {
        const source = document.getElementById(`detail-foto-${type}`);
        if (source && source.src && modal && imgEl) {
            imgEl.src = source.src;
            resetImageState(); // Reset posisi tiap buka baru
            
            modal.classList.remove('hidden', 'pointer-events-none');
            setTimeout(() => modal.classList.remove('opacity-0'), 10);
        }
    };

    window.closeImageViewer = function() {
        if (modal) {
            modal.classList.add('opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden', 'pointer-events-none');
                imgEl.src = '';
            }, 300);
        }
    };

    window.resetImageState = function() {
        imgState = { scale: 1, rotate: 0, pX: 0, pY: 0, isDragging: false };
        updateTransform(true); // true = gunakan animasi halus
    };

    window.adjustImage = function(action, val) {
        if (action === 'zoom') {
            const newScale = imgState.scale + val;
            if (newScale >= 0.5 && newScale <= 10) imgState.scale = newScale;
        } else if (action === 'rotate') {
            imgState.rotate += val;
        }
        updateTransform(true); // Gunakan animasi halus saat klik tombol
    };

    // Fungsi Update CSS
    function updateTransform(useTransition = false) {
        if (!imgEl) return;
        
        // KUNCI: Hanya gunakan animasi (transition) saat Zoom/Rotate via tombol.
        // Saat Drag, transition harus 'none'.
        imgEl.style.transition = useTransition ? 'transform 0.2s cubic-bezier(0.1, 0.7, 1.0, 0.1)' : 'none';
        
        imgEl.style.transform = `translate(${imgState.pX}px, ${imgState.pY}px) scale(${imgState.scale}) rotate(${imgState.rotate}deg)`;
    }


    // --- EVENT LISTENERS (MOUSE WHEEL & DRAG) ---

    if (container) {
        
        // 1. MOUSE WHEEL ZOOM (Tetap sama)
        container.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -0.2 : 0.2;
            const newScale = imgState.scale + delta;
            if (newScale >= 0.5 && newScale <= 10) {
                imgState.scale = newScale;
                // Gunakan true (animasi) untuk zoom agar halus
                updateTransform(true); 
            }
        }, { passive: false });


        // 2. DRAG START (Klik Kiri Tahan)
        container.addEventListener('mousedown', (e) => {
            // Pastikan hanya klik kiri dan targetnya gambar/container
            if (e.button !== 0) return;
            e.preventDefault(); // Matikan seleksi teks bawaan
            e.stopPropagation();

            imgState.isDragging = true;
            
            // TITIK KRUSIAL: Matikan animasi CSS seketika saat mulai geser!
            // Jika tidak dimatikan, ini yang bikin BERGETAR.
            imgEl.style.transition = 'none'; 
            
            imgState.startX = e.clientX - imgState.pX;
            imgState.startY = e.clientY - imgState.pY;
            
            container.style.cursor = 'grabbing';
        });

        // 3. DRAGGING (Gerakkan Mouse)
        window.addEventListener('mousemove', (e) => {
            if (!imgState.isDragging) return;
            e.preventDefault(); // PENTING: Mencegah seleksi browser
            
            // Hitung posisi baru (Raw calculation)
            // Kita tidak pakai requestAnimationFrame agar instan mengikuti mouse
            imgState.pX = e.clientX - imgState.startX;
            imgState.pY = e.clientY - imgState.startY;

            // Update posisi langsung ke Style Element
            // Kita bypass fungsi updateTransform() agar lebih cepat & tanpa logika lain
            imgEl.style.transform = `translate(${imgState.pX}px, ${imgState.pY}px) scale(${imgState.scale}) rotate(${imgState.rotate}deg)`;
        });

        // 4. DRAG END (Lepas Klik)
        window.addEventListener('mouseup', () => {
            if (imgState.isDragging) {
                imgState.isDragging = false;
                container.style.cursor = 'grab';
                
                // Opsional: Nyalakan lagi transisi supaya kalau di-zoom/rotate setelah ini jadi halus lagi
                // imgEl.style.transition = 'transform 0.2s ease-out'; 
            }
        });
        
        // Mencegah "Ghost Image" saat drag
        container.addEventListener('dragstart', (e) => e.preventDefault());
    }

    // Keyboard Shortcuts (Tambahan UX)
    document.addEventListener('keydown', (e) => {
        if (modal && !modal.classList.contains('hidden')) {
            if (e.key === 'Escape') closeImageViewer();
            if (e.key === '+' || e.key === '=') adjustImage('zoom', 0.2);
            if (e.key === '-') adjustImage('zoom', -0.2);
            if (e.key === '0') resetImageState();
        }
    });

    console.log("Mapping Handler Loaded");
    // Init Drag Events saat DOM siap
    setTimeout(() => {
        const cont = document.getElementById('image-container');
        if(cont) {
            cont.onmousedown = (e) => {
                if(e.target.tagName !== 'IMG') return;
                e.preventDefault();
                imgState.panning = true;
                imgState.startX = e.clientX - imgState.pointX;
                imgState.startY = e.clientY - imgState.pointY;
                cont.style.cursor = 'grabbing';
            };
            
            // ... (Event listener lain sudah di handle global window di atas)
        }
    }, 1000);
})();