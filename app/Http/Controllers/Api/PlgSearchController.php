<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MappingKddk;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PlgSearchController extends Controller
{
    /**
     * Cari pelanggan berdasarkan IDPEL atau Nomor Meter.
     * Hanya mengembalikan data yang sudah 'valid'.
     */

    public function search(Request $request)
    {
        // 1. Validasi input query
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:9|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $searchQuery = $request->input('query');

        // 2. Query utama
        $result = MappingKddk::query()
            // Join ke tabel master untuk data nama/alamat
            // (Asumsi nama & alamat ada di master_data_pelanggan)
            ->join('master_data_pelanggan', 'mapping_kddk.idpel', '=', 'master_data_pelanggan.idpel')
            
            // == INI ADALAH LOGIKA KUNCI ANDA ==
            ->where('mapping_kddk.ket_validasi', 'valid') 
            
            // Cari berdasarkan IDPEL atau No Meter di tabel mapping
            ->where(function ($query) use ($searchQuery) {
                $query->where('mapping_kddk.idpel', 'LIKE', "%{$searchQuery}%")
                      ->orWhere('mapping_kddk.nokwhmeter', 'LIKE', "%{$searchQuery}%");
            })
            
            // Pilih data yang dibutuhkan oleh Flutter
            ->select(
                'mapping_kddk.idpel',
                'mapping_kddk.nokwhmeter',
                'mapping_kddk.foto_kwh', //
                'mapping_kddk.foto_bangunan', //
            )
            ->first(); // Ambil satu data saja

        // 3. Handle jika tidak ditemukan
        if (!$result) {
            return response()->json([
                'message' => 'Data tidak ditemukan atau belum divalidasi.'
            ], 404);
        }

        // 4. Ubah path foto menjadi URL lengkap
        // Asumsi file disimpan di 'storage/app/public/...'
        $fotoKwhUrl = $result->foto_kwh ? Storage::url($result->foto_kwh) : null;
        $fotoPersilUrl = $result->foto_bangunan ? Storage::url($result->foto_bangunan) : null;

        // 5. Kembalikan respon JSON
        return response()->json([
            'data' => [
                'idpel' => $result->idpel,
                'nomor_meter' => $result->nokwhmeter,
                'url_foto_kwh' => $fotoKwhUrl,       // Key 'url_foto_kwh'
                'url_foto_persil' => $fotoPersilUrl    // Key 'url_foto_persil'
            ]
        ]);
    }
}
