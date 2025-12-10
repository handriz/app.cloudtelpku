<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lembar Kerja RBM - {{ $info['area'] }} {{ $info['route'] }}</title>
    <style>
        @page { size: A4; margin: 10mm; }
        body { font-family: 'Helvetica', Arial, sans-serif; font-size: 11px; color: #000; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        .title h1 { margin: 0; font-size: 18px; text-transform: uppercase; }
        .title p { margin: 2px 0 0; font-size: 12px; }
        .qr-box { text-align: center; }
        .qr-box img { width: 80px; height: 80px; border: 1px solid #000; }
        .qr-caption { font-size: 9px; margin-top: 2px; font-weight: bold; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 6px 4px; text-align: left; vertical-align: top; }
        th { background-color: #f0f0f0; font-weight: bold; text-transform: uppercase; font-size: 10px; text-align: center; }
        
        /* Kolom Khusus */
        .col-no { width: 30px; text-align: center; }
        .col-seq { width: 40px; text-align: center; font-weight: bold; }
        .col-meter { width: 80px; text-align: center; }
        .col-stand { width: 100px; } /* Kosong untuk tulis tangan */
        .col-ket { width: 120px; }   /* Kosong untuk keterangan */
        
        /* Utility */
        .page-break { page-break-after: always; }
        .no-print { margin-bottom: 20px; padding: 10px; background: #e0e7ff; border: 1px solid #6366f1; color: #312e81; text-align: center; font-weight: bold; border-radius: 5px; cursor: pointer; }
        
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print" onclick="window.print()">
        🖨️ KLIK DI SINI UNTUK MENCETAK (CTRL + P)
    </div>

    <div class="header">
        <div class="title">
            <h1>Lembar Kerja RBM</h1>
            <p><strong>UNIT:</strong> {{ $info['unit'] }} | <strong>AREA:</strong> {{ $info['area'] }} | <strong>RUTE:</strong> {{ $info['route'] }}</p>
            <p>Total: {{ $info['total'] }} Pelanggan | Tgl Cetak: {{ $info['date'] }}</p>
        </div>
        
        @if($qrUrl)
        <div class="qr-box">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($qrUrl) }}" alt="QR Navigasi">
            <div class="qr-caption">SCAN NAVIGASI</div>
        </div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th class="col-seq">URUT</th>
                <th>IDPEL / NAMA</th>
                <th>ALAMAT</th>
                <th class="col-meter">NO. METER</th>
                <th>TARIF/DAYA</th>
                <th class="col-stand">STAND METER</th>
                <th class="col-ket">KETERANGAN</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $d)
                <tr>
                    <td class="col-seq">{{ substr($d->kddk, 7, 3) }}</td>
                    <td>
                        <strong>{{ $d->idpel }}</strong><br>
                        {{ Str::limit($d->nomor_meter_kwh, 25) }}
                    </td>
                    <td style="font-size: 9px;">{{ Str::limit($d->nomor_meter_kwh, 40) }}</td>
                    <td class="col-meter">{{ $d->nomor_meter_kwh }}</td>
                    <td class="text-center" style="font-size: 9px;">{{ $d->tarif }}<br>{{ $d->daya }} VA</td>
                    
                    <td></td> 
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>