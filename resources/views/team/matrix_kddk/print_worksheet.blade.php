<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lembar Kerja RBM - {{ $info['area'] }} {{ $info['route'] }}</title>
    
    @vite(['resources/css/app.css'])
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
        /* PENGATURAN CETAK */
        @media print {
            @page { size: A4; margin: 5mm; } 
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
            #print-map { width: 100%; height: 500px; page-break-inside: avoid; }
        }

        /* CUSTOM MARKER (Lingkaran Putih, Angka Biru) */
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
        
        #print-map { cursor: grab; background: #e5e5e5; }
        #print-map:active { cursor: grabbing; }
    </style>
</head>
<body class="bg-white text-gray-900 text-xs font-sans">

    <div class="no-print fixed top-4 right-4 z-50 flex gap-2">
        <button onclick="window.print()" class="bg-indigo-600 text-white px-4 py-2 rounded shadow font-bold hover:bg-indigo-700 flex items-center transition">
            <i class="fas fa-print mr-2"></i> Cetak (Ctrl+P)
        </button>
        <button onclick="window.close()" class="bg-gray-200 text-gray-700 px-4 py-2 rounded shadow font-bold hover:bg-gray-300 transition">
            Tutup
        </button>
    </div>

    <div class="mb-4 border-b-2 border-gray-800 pb-2 flex justify-between items-start">
        <div>
            <h1 class="text-xl font-bold uppercase tracking-wider mb-1">Lembar Kerja Lapangan</h1>
            <table class="text-xs font-mono">
                <tr><td class="pr-4 text-gray-500">UNIT</td><td>: <strong>{{ $info['unit'] }}</strong></td></tr>
                <tr><td class="pr-4 text-gray-500">AREA</td><td>: <strong>{{ $info['area'] }}</strong></td></tr>
                <tr><td class="pr-4 text-gray-500">RUTE</td><td>: <strong class="bg-indigo-100 px-1 rounded">{{ $info['route'] }}</strong></td></tr>
                <tr><td class="pr-4 text-gray-500">CETAK</td><td>: {{ $info['date'] }}</td></tr>
            </table>
        </div>
        <div class="text-center pt-2">
            <div class="text-4xl font-bold text-indigo-700">{{ $info['total'] }}</div>
            <div class="text-[10px] uppercase text-gray-500 font-bold tracking-wide">Total Pelanggan</div>
        </div>
        <div class="flex flex-col items-center">
            @if(!empty($qrUrl))
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data={{ urlencode($qrUrl) }}" 
                     alt="Scan Navigasi" class="qr-code-img w-20 h-20 mb-1">
                <div class="text-[9px] font-bold text-center leading-tight">SCAN UNTUK<br>NAVIGASI MULAI</div>
            @else
                <div class="w-20 h-20 border-2 border-dashed border-gray-300 flex items-center justify-center text-[9px] text-gray-400">No Coord</div>
            @endif
        </div>
    </div>

    <div class="mb-4 border border-gray-400 rounded overflow-hidden relative shadow-sm">
        <div id="print-map" style="height: 500px; width: 100%; z-index: 0;"></div>
        
        <div class="absolute bottom-2 left-2 bg-white/90 px-2 py-1 text-[10px] rounded border border-gray-300 z-10 font-bold shadow-md">
            <span class="text-indigo-800">● Titik</span> | 
            <span class="text-yellow-500 font-extrabold">▬ Jalur</span> |
            <span class="text-red-600 font-extrabold">▶ Arah</span>
        </div>
        
        <div class="no-print absolute top-2 right-2 bg-white/80 px-2 py-1 text-[10px] rounded border border-gray-300 z-10 font-bold text-gray-600 shadow-sm pointer-events-none">
            <i class="fas fa-hand-paper mr-1"></i> Geser/Zoom peta
        </div>
    </div>

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
            @foreach($data as $row)
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
                        @if(!$row->latitudey)
                            <span class="text-[9px] text-red-600 font-bold italic">⚠️ No Coord</span>
                        @endif
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
        <span>RBM Matrix System</span>
        <span>Dicetak: {{ now()->format('d-m-Y H:i:s') }}</span>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script src="https://unpkg.com/leaflet-polylinedecorator@1.6.0/dist/leaflet.polylineDecorator.js"></script>

    <script>
        const mapData = @json($data);
        
        const map = L.map('print-map', { 
            zoomControl: false,       
            attributionControl: false 
        });

        // Tile Layer Satelit
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
            attribution: 'Tiles © Esri'
        }).addTo(map);

        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19
        }).addTo(map);

        const lineCoords = [];

        mapData.forEach(item => {
            const lat = parseFloat(item.latitudey);
            const lng = parseFloat(item.longitudex);

            if (!lat || !lng) return;

            const seq = item.kddk.substring(7, 10);
            const latLng = [lat, lng];
            lineCoords.push(latLng);

            const icon = L.divIcon({
                className: 'custom-map-marker',
                html: `<div class="marker-pin">${seq}</div>`,
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });
            
            L.marker(latLng, { icon: icon }).addTo(map);
        });

        // RENDER GARIS & PANAH
        if (lineCoords.length > 1) {
            
            // 1. Garis Kuning Neon (Tebal)
            const polyline = L.polyline(lineCoords, { 
                color: '#FFFF00', // Yellow
                weight: 4, 
                opacity: 0.9,
                lineJoin: 'round'
            }).addTo(map);
            
            // 2. Panah Merah
            // Cek apakah plugin terload dengan benar
            if (L.polylineDecorator) {
                const arrowDecorator = L.polylineDecorator(polyline, {
                    patterns: [{
                        offset: 25,       // Mulai 25px dari titik awal
                        repeat: '60px',   // [FIX] Jarak antar panah dirapatkan (dulu 100px)
                        symbol: L.Symbol.arrowHead({ 
                            pixelSize: 15, // [FIX] Panah diperbesar (dulu 12px)
                            polygon: true, 
                            pathOptions: { 
                                stroke: true, 
                                color: '#FF0000',     // Border Merah
                                fillColor: '#FF0000', // Isi Merah
                                fillOpacity: 1 
                            } 
                        })
                    }]
                }).addTo(map);
            } else {
                console.error("Plugin PolylineDecorator gagal dimuat!");
            }
        }

        function autoCenterMap() {
            if (lineCoords.length > 0) {
                const bounds = L.latLngBounds(lineCoords);
                map.fitBounds(bounds, { 
                    padding: [50, 50],
                    maxZoom: 18,       
                    animate: false     
                });
            } else {
                map.setView([0.5071, 101.4478], 13);
            }
        }

        autoCenterMap();

        window.addEventListener("load", () => {
            setTimeout(() => {
                map.invalidateSize(); 
                autoCenterMap();      
            }, 800);
        });

    </script>
</body>
</html>