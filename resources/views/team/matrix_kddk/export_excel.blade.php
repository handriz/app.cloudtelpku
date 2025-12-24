<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<body>
    <table>
        {{-- JUDUL UTAMA --}}
        <tr>
            <td colspan="11" style="font-size: 18px; font-weight: bold; text-align: center; height: 35px; vertical-align: middle;">
                LAPORAN DATA RBM & KOORDINAT PELANGGAN
            </td>
        </tr>

        {{-- NAMA UNIT --}}
        <tr>
            <td colspan="11" style="font-size: 14px; font-weight: bold; text-align: center; height: 25px; vertical-align: middle;">
                UNIT LAYANAN: {{ strtoupper($unitCode) }}
            </td>
        </tr>

        {{-- DETAIL AREA / RUTE (DINAMIS) --}}
        <tr>
            <td colspan="11" style="font-size: 12px; font-weight: bold; text-align: center; height: 25px; vertical-align: middle; color: #0000FF;">
                {{ $titleDetails ?? 'SEMUA DATA' }}
            </td>
        </tr>

        {{-- TANGGAL CETAK --}}
        <tr>
            <td colspan="11" style="font-size: 11px; font-style: italic; text-align: center; color: #555555;">
                Dicetak pada: {{ now()->format('d F Y, H:i:s') }}
            </td>
        </tr>

        {{-- SPASI KOSONG --}}
        <tr>
            <td colspan="11" style="height: 15px;"></td>
        </tr>

        {{-- HEADER TABEL (LENGKAP) --}}
        <thead>
            <tr>
                <th style="background-color: #312e81; color: #ffffff; font-weight: bold; border: 1px solid #000000; width: 150px; text-align: center; vertical-align: middle;">IDPEL</th>
                
                <th style="background-color: #312e81; color: #ffffff; font-weight: bold; border: 1px solid #000000; width: 250px; text-align: left; vertical-align: middle;">NAMA PELANGGAN</th>
                
                <th style="background-color: #312e81; color: #ffffff; font-weight: bold; border: 1px solid #000000; width: 80px; text-align: center; vertical-align: middle;">TARIF</th>
                
                <th style="background-color: #312e81; color: #ffffff; font-weight: bold; border: 1px solid #000000; width: 80px; text-align: center; vertical-align: middle;">DAYA</th>
                
                <th style="background-color: #312e81; color: #ffffff; font-weight: bold; border: 1px solid #000000; width: 150px; text-align: left; vertical-align: middle;">NO METER</th>
                
                <th style="background-color: #059669; color: #ffffff; font-weight: bold; border: 1px solid #000000; width: 180px; text-align: center; vertical-align: middle;">KODE KDDK FULL</th>
                
                <th style="background-color: #10b981; color: #ffffff; font-weight: bold; border: 1px solid #000000; width: 100px; text-align: center; vertical-align: middle;">PREFIX</th>
                
                <th style="background-color: #10b981; color: #ffffff; font-weight: bold; border: 1px solid #000000; width: 60px; text-align: center; vertical-align: middle;">URUT</th>
                
                <th style="background-color: #10b981; color: #ffffff; font-weight: bold; border: 1px solid #000000; width: 60px; text-align: center; vertical-align: middle;">SISIP</th>

                <th style="background-color: #d97706; color: #ffffff; font-weight: bold; border: 1px solid #000000; width: 120px; text-align: center; vertical-align: middle;">LATITUDE</th>
                
                <th style="background-color: #d97706; color: #ffffff; font-weight: bold; border: 1px solid #000000; width: 120px; text-align: center; vertical-align: middle;">LONGITUDE</th>
            </tr>
        </thead>

        {{-- ISI DATA --}}
        <tbody>
            @foreach($data as $row)
                @php
                    $kddk = $row->kddk ?? '';
                    $len = strlen($kddk);
                    $prefix = ''; $urut = '-'; $sisip = '-';

                    if ($len >= 10) {
                        $prefix = substr($kddk, 0, 7);
                        $urut   = substr($kddk, 7, 3);
                        $sisip  = ($len >= 12) ? substr($kddk, 10, 2) : '00';
                    } elseif ($len > 0) {
                        $prefix = $kddk;
                    }
                @endphp
                <tr>
                    <td style="border: 1px solid #000000; mso-number-format:'\@'; text-align: left;">{{ $row->idpel }}</td>
                    
                    <td style="border: 1px solid #000000;">{{ $row->nama ?? '-' }}</td>
                    
                    <td style="border: 1px solid #000000; text-align: center;">{{ $row->tarif }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $row->daya }}</td>
                    
                    <td style="border: 1px solid #000000; mso-number-format:'\@';">{{ $row->nomor_meter_kwh }}</td>
                    
                    <td style="border: 1px solid #000000; mso-number-format:'\@'; font-weight:bold; text-align: center; background-color: #f0fdf4;">{{ $kddk }}</td>

                    <td style="border: 1px solid #000000; mso-number-format:'\@'; text-align: center;">{{ $prefix }}</td>
                    <td style="border: 1px solid #000000; mso-number-format:'\@'; text-align: center;">{{ $urut }}</td>
                    <td style="border: 1px solid #000000; mso-number-format:'\@'; text-align: center;">{{ $sisip }}</td>

                    <td style="border: 1px solid #000000; text-align: right; mso-number-format:'\@';">{{ $row->latitudey }}</td>
                    <td style="border: 1px solid #000000; text-align: right; mso-number-format:'\@';">{{ $row->longitudex }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>