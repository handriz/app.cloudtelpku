<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak RBM Multi Halaman</title>
    
    @vite(['resources/css/app.css'])
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
        @media print {
            @page { size: A4; margin: 5mm; } 
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
            .map-container { width: 100%; height: 400px; page-break-inside: avoid; border: 1px solid #ccc; }
        }
        /* Layar biasa */
        .map-container { width: 100%; height: 400px; background: #e5e5e5; }

        /* Marker Custom */
        .marker-pin {
            width: 20px; height: 20px; border-radius: 50%; 
            background: white; border: 2px solid #3730a3; 
            color: #3730a3; font-size: 9px; font-weight: bold;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 1px 5px rgba(0,0,0,0.5);
        }

        th { background-color: #f3f4f6 !important; color: #111827 !important; border-bottom: 2px solid #9ca3af !important; }
        tr:nth-child(even) { background-color: #f9fafb !important; }
        .qr-code-img { image-rendering: pixelated; border: 2px solid #000; }
    </style>
</head>
<body class="bg-white text-gray-900 text-xs font-sans">

    <div class="no-print fixed top-4 right-4 z-50 flex gap-2">
        <button onclick="window.print()" class="bg-indigo-600 text-white px-4 py-2 rounded shadow font-bold hover:bg-indigo-700 flex items-center transition">
            <i class="fas fa-print mr-2"></i> Cetak (Ctrl+P)
        </button>
        <button onclick="window.close()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded shadow font-bold hover:bg-gray-300 transition">Tutup</button>
    </div>

    {{-- LOOPING SETIAP HALAMAN (PER RUTE) --}}
    @foreach($groupedPages as $key => $page)
        
        {{-- Page Break (Kecuali halaman pertama) --}}
        @if(!$loop->first)
            <div class="page-break"></div>
        @endif

        {{-- 1. HEADER HALAMAN --}}
        <div class="mb-4 border-b-2 border-gray-800 pb-2 flex justify-between items-start pt-4">
            <div>
                <h1 class="text-xl font-bold uppercase tracking-wider mb-1">Lembar Kerja Lapangan</h1>
                <table class="text-xs font-mono">
                    <tr><td class="pr-4 text-gray-500">UNIT</td><td>: <strong>{{ $page['info']['unit'] }}</strong></td></tr>
                    <tr><td class="pr-4 text-gray-500">AREA</td><td>: <strong>{{ $page['info']['area'] }}</strong></td></tr>
                    <tr><td class="pr-4 text-gray-500">RUTE</td><td>: <strong class="bg-indigo-100 px-1 rounded text-indigo-800 border border-indigo-200" style="font-size: 1.2em;">{{ $page['info']['route'] }}</strong></td></tr>
                    <tr><td class="pr-4 text-gray-500">CETAK</td><td>: {{ $page['info']['date'] }}</td></tr>
                </table>
            </div>
            <div class="text-center pt-2">
                <div class="text-4xl font-bold text-indigo-700">{{ $page['info']['total'] }}</div>
                <div class="text-[10px] uppercase text-gray-500 font-bold tracking-wide">Pelanggan</div>
            </div>
            <div class="flex flex-col items-center">
                @if(!empty($page['qr_url']))
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data={{ urlencode($page['qr_url']) }}" 
                         alt="Scan Navigasi" class="qr-code-img w-20 h-20 mb-1">
                    <div class="text-[9px] font-bold text-center leading-tight">SCAN UNTUK<br>NAVIGASI</div>
                @else
                    <div class="w-20 h-20 border-2 border-dashed border-gray-300 flex items-center justify-center text-[9px] text-gray-400">No Coord</div>
                @endif
            </div>
        </div>

        {{-- 2. PETA (ID UNIK PER RUTE) --}}
        <div class="mb-4 border border-gray-400 rounded overflow-hidden relative shadow-sm">
            <div id="map-{{ $key }}" class="map-container"></div>
            
            <div class="absolute bottom-2 left-2 bg-white/90 px-2 py-1 text-[10px] rounded border border-gray-300 z-10 font-bold shadow-md">
                <span class="text-indigo-800">● Titik</span> | 
                <span class="text-yellow-500 font-extrabold">▬ Jalur</span> |
                <span class="text-red-600 font-extrabold">▶ Arah</span>
            </div>
        </div>

        {{-- 3. TABEL DATA --}}
        <table class="w-full border-collapse border border-gray-400 text-[10px]">
            <thead>
                <tr>
                    <th class="border border-gray-400 px-1 py-1 w-8 text-center bg-gray-100">NO</th>
                    <th class="border border-gray-400 px-2 py-1 bg-gray-100">ID PELANGGAN</th>
                    <th class="border border-gray-400 px-2 py-1 bg-gray-100">NAMA / INFO</th>
                    <th class="border border-gray-400 px-2 py-1 bg-gray-100">METER / TARIF</th>
                    <th class="border border-gray-400 px-2 py-1 w-24 bg-gray-100">STAN KWH</th>
                    <th class="border border-gray-400 px-2 py-1 w-32 bg-gray-100">KETERANGAN</th>
                </tr>
            </thead>
            <tbody>
                @foreach($page['items'] as $row)
                    @php 
                        $seq = substr($row->kddk, 7, 3);
                        $suffix = substr($row->kddk, 10, 2);
                        $fullSeq = $seq . ($suffix !== '00' ? '.' . $suffix : '');
                    @endphp
                    <tr>
                        <td class="border border-gray-400 px-1 py-1.5 text-center font-bold font-mono text-sm bg-gray-50">{{ $fullSeq }}</td>
                        <td class="border border-gray-400 px-2 py-1.5 font-bold text-xs">{{ $row->idpel }}</td>
                        <td class="border border-gray-400 px-2 py-1.5">
                            <div class="font-bold truncate w-48">{{ Str::limit($row->nama ?? 'PELANGGAN', 25) }}</div>
                            @if(!$row->latitudey) <span class="text-[9px] text-red-600 font-bold italic">⚠️ No Coord</span> @endif
                        </td>
                        <td class="border border-gray-400 px-2 py-1.5">
                            <div class="font-mono">{{ $row->nomor_meter_kwh }}</div>
                            <div class="text-[9px] text-gray-500">{{ $row->tarif }} / {{ $row->daya }} VA</div>
                        </td>
                        <td class="border border-gray-400 px-2 py-1.5"></td>
                        <td class="border border-gray-400 px-2 py-1.5"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-4 border-t border-gray-300 pt-1 text-[9px] flex justify-between text-gray-400 italic">
            <span>RBM Matrix System - Halaman {{ $loop->iteration }}</span>
            <span>Dicetak: {{ now()->format('d-m-Y H:i:s') }}</span>
        </div>

    @endforeach

    {{-- SCRIPTS --}}
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-polylinedecorator@1.6.0/dist/leaflet.polylineDecorator.js"></script>

    <script>
        // Data dari Controller
        const pagesData = @json($groupedPages);

        window.addEventListener("load", () => {
            
            // Loop setiap halaman/grup untuk render peta masing-masing
            Object.keys(pagesData).forEach(key => {
                const page = pagesData[key];
                const mapId = 'map-' + key;
                const points = page.map_points;

                if (!document.getElementById(mapId)) return; // Safety check

                // Init Map
                const map = L.map(mapId, { 
                    zoomControl: false,       
                    attributionControl: false 
                });

                L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    maxZoom: 19, attribution: 'Tiles © Esri'
                }).addTo(map);

                L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
                    maxZoom: 19
                }).addTo(map);

                const lineCoords = [];

                // Gambar Marker
                points.forEach(pt => {
                    const lat = parseFloat(pt.lat);
                    const lng = parseFloat(pt.lng);
                    if (!lat || !lng) return;

                    const latLng = [lat, lng];
                    lineCoords.push(latLng);

                    const icon = L.divIcon({
                        className: 'custom-map-marker',
                        html: `<div class="marker-pin">${pt.seq}</div>`,
                        iconSize: [20, 20],
                        iconAnchor: [10, 10]
                    });
                    
                    L.marker(latLng, { icon: icon }).addTo(map);
                });

                // Gambar Garis & Panah
                if (lineCoords.length > 1) {
                    const polyline = L.polyline(lineCoords, { 
                        color: '#FFFF00', weight: 4, opacity: 0.9, lineJoin: 'round'
                    }).addTo(map);
                    
                    if (L.polylineDecorator) {
                        L.polylineDecorator(polyline, {
                            patterns: [{
                                offset: 25, repeat: '60px',
                                symbol: L.Symbol.arrowHead({ 
                                    pixelSize: 15, polygon: true, 
                                    pathOptions: { stroke: true, color: '#FF0000', fillColor: '#FF0000', fillOpacity: 1 } 
                                })
                            }]
                        }).addTo(map);
                    }
                    
                    // Auto Center
                    const bounds = L.latLngBounds(lineCoords);
                    map.fitBounds(bounds, { padding: [30, 30], maxZoom: 18, animate: false });
                } else {
                    map.setView([0.5071, 101.4478], 13);
                }

                // Paksa render ulang agar tidak abu-abu saat print
                setTimeout(() => map.invalidateSize(), 500);
            });

        });
    </script>
</body>
</html>