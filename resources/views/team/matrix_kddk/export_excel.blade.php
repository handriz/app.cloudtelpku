<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        /* STYLE KHUSUS UNTUK EXCEL */
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        /* Header Tabel: Biru Gelap, Teks Putih, Tengah */
        th {
            background-color: #4f46e5; /* Indigo */
            color: #ffffff;
            border: 1px solid #000000;
            padding: 10px 5px;
            text-align: center;
            vertical-align: middle;
            font-weight: bold;
            height: 30px;
        }

        /* Isi Tabel: Border Hitam Tipis */
        td {
            border: 1px solid #000000;
            padding: 5px;
            vertical-align: middle;
        }

        /* Utility Classes */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        
        /* Warna Baris Selang-seling (Opsional, kadang Excel mengabaikan ini tapi worth trying) */
        tr:nth-child(even) { background-color: #f3f4f6; }

        /* FORMAT KHUSUS EXCEL (PENTING!) */
        .str { mso-number-format:'\@'; } /* Paksa jadi Text (0 di depan aman) */
        .num { mso-number-format:'0'; }  /* Angka bulat */
    </style>
</head>
<body>

    {{-- JUDUL LAPORAN (Merged Cells) --}}
    <table>
        <tr>
            {{-- Gabungkan 9 kolom agar judul di tengah --}}
            <td colspan="9" style="font-size: 16px; font-weight: bold; text-align: center; height: 40px; border: none;">
                DATA REKAP RBM - UNIT {{ $unitCode }}
            </td>
        </tr>
        <tr>
            <td colspan="9" style="text-align: center; font-style: italic; border: none;">
                Tanggal Export: {{ date('d F Y, H:i') }} | Total Data: {{ count($data) }} Pelanggan
            </td>
        </tr>
        <tr><td colspan="9" style="border: none;"></td></tr> {{-- Spasi Kosong --}}
    </table>

    {{-- TABEL DATA --}}
    <table>
        <thead>
            <tr>
                {{-- Width di sini membantu Excel menentukan lebar awal kolom --}}
                <th width="5" style="background-color: #4f46e5; color: #ffffff;">NO</th>
                <th width="15" style="background-color: #4f46e5; color: #ffffff;">KDDK (RUTE)</th>
                <th width="15" style="background-color: #4f46e5; color: #ffffff;">IDPEL</th>
                <th width="35" style="background-color: #4f46e5; color: #ffffff;">LATITUDE</th>
                <th width="40" style="background-color: #4f46e5; color: #ffffff;">LOGITUDE</th>
                <th width="8" style="background-color: #4f46e5; color: #ffffff;">TARIF</th>
                <th width="10" style="background-color: #4f46e5; color: #ffffff;">DAYA</th>
                <th width="20" style="background-color: #4f46e5; color: #ffffff;">NO. METER</th>
                <th width="10" style="background-color: #4f46e5; color: #ffffff;">URUT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    
                    {{-- Class 'str' mencegah Excel membuang angka 0 di depan --}}
                    <td class="str text-center">{{ $row->kddk }}</td>
                    <td class="str text-center">{{ $row->idpel }}</td>
                    
                    <td>{{ $row->latitudey }}</td>
                    <td>{{ $row->longitudex }}</td>
                    
                    <td class="text-center">{{ $row->tarif }}</td>
                    <td class="text-center num">{{ $row->daya }}</td>
                    
                    <td class="str text-center">{{ $row->nomor_meter_kwh }}</td>
                    
                    {{-- Highlight Kuning untuk Sequence --}}
                    <td class="text-center str" style="background-color: #fef3c7; font-weight: bold;">
                        {{ substr($row->kddk, -3) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>