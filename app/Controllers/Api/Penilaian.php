<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\PenilaianModel;
use App\Models\SupplierModel;

class Penilaian extends ResourceController
{
    protected $modelName = 'App\Models\PenilaianModel';
    protected $format    = 'json';

    public function index()
    {
        $supplier_id = $this->request->getGet('supplier_id');
        $periode     = $this->request->getGet('periode');

        $query = $this->model;
        if ($supplier_id) $query = $query->where('supplier_id', $supplier_id);
        if ($periode)     $query = $query->where('periode', $periode);

        return $this->respond($query->findAll());
    }

    public function upsert()
    {
        $json = $this->request->getJSON(true);
        if (!$json || empty($json['supplier_id']) || empty($json['periode'])) {
            return $this->fail('Data supplier/periode kosong Pi!', 400);
        }

        $existing = $this->model->where('supplier_id', $json['supplier_id'])->where('periode', $json['periode'])->first();

        try {
            if ($existing) {
                $this->model->update($existing['id'], $json);
                $id = $existing['id'];
            } else {
                $id = $this->model->insert($json);
            }
            return $this->respond(['status' => 'success', 'message' => 'Data disubmit!', 'data' => $this->model->find($id)]);
        } catch (\Exception $e) {
            return $this->fail('Dapur Error: ' . $e->getMessage(), 500);
        }
    }

   public function uploadPpic()
    {
        // 1. Kasih napas lega
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '180'); // Tambah waktu jadi 3 menit

        $supplier_id = $this->request->getPost('supplier_id');
        $periode     = $this->request->getPost('periode');
        $file        = $this->request->getFile('ppic_file');

        if (!$supplier_id || !$file || !$file->isValid()) {
            return $this->fail('File gak valid atau data kurang Pi!', 400);
        }

        try {
            $supplierModel = new SupplierModel();
            $supplier = $supplierModel->find($supplier_id);
            $targetKode = trim((string)$supplier['kode_vendor']);

            $filename = $file->getTempName();
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filename);
            
            // 2. KUNCI: Cari nama sheet yang mengandung kata 'LIST' dulu tanpa load filenya
            $sheetNames = $reader->listWorksheetNames($filename);
            $targetSheet = null;
            foreach ($sheetNames as $name) {
                if (stripos($name, 'LIST') !== false) {
                    $targetSheet = $name;
                    break;
                }
            }

            if (!$targetSheet) return $this->fail('Sheet LIST gak ketemu boi!', 404);

            // 3. OPTIMASI: Cuma baca data murni, gak usah baca gaya/format/grafik
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly($targetSheet);
            
            // 4. FILTER: Batasi baca cuma kolom B sampe F (B=Kode, F=Score)
            // Ini biar memori gak jebol gara-gara sheet LPB yang gede banget itu
            $spreadsheet = $reader->load($filename);
            $sheet = $spreadsheet->getSheetByName($targetSheet);
            
            $highestRow = $sheet->getHighestRow();
            $scoreValue      = null;
            $namaVendorExcel = null;
            $tidakAdaPO      = false;

            // PENTING: Kolom B di Excel menyimpan kode vendor sebagai INTEGER (bukan string).
            // Kita bandingkan keduanya sebagai string setelah trim untuk handle leading zeros jika ada.
            // Cast $targetKode juga ke int lalu string agar konsisten.
            $targetKodeNorm = (string)(int)$targetKode; // "1003107" → sama dengan (int)1003107 → "1003107"

            for ($row = 1; $row <= $highestRow; $row++) {
                $cellBRaw  = $sheet->getCell('B' . $row)->getValue();
                $cellBNorm = (string)(int)trim((string)$cellBRaw); // int dari excel → cast ke string

                if ($cellBNorm === $targetKodeNorm && $cellBNorm !== '0') {
                    // Kolom C = Nama Vendor (untuk konfirmasi ke frontend)
                    $namaVendorExcel = trim((string)$sheet->getCell('C' . $row)->getValue());

                    // Kolom F = Score (disimpan Excel sebagai desimal: 0.8396 = 83.96%)
                    // PENTING: Gunakan getOldCalculatedValue() karena setLoadSheetsOnly('LIST')
                    // membuat formula SUMIF gagal menghitung (karena sheet 'jadwal' tidak diload).
                    // getOldCalculatedValue() akan mengambil hasil cache terakhir yang disimpan Excel.
                    $valF = null;
                    try {
                        $valF = $sheet->getCell('F' . $row)->getOldCalculatedValue();
                        // Fallback jika tidak ada cache sama sekali (jarang terjadi)
                        if ($valF === null) {
                            $valF = $sheet->getCell('F' . $row)->getCalculatedValue();
                        }
                    } catch (\Exception $e) {
                        $valF = $sheet->getCell('F' . $row)->getValue();
                    }

                    if ($valF === '-' || $valF === null || $valF === '' || (string)$valF === '-') {
                        // Tidak ada PO / delivery di bulan ini
                        $scoreValue = 0;
                        $tidakAdaPO = true;
                    } else {
                        // Excel simpan sebagai desimal (0.8396) → kita jadikan persen (83.96)
                        $scoreValue = round((float)$valF * 100, 2);
                    }
                    break;
                }
            }

            // Bersihin memori
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            if ($scoreValue === null) {
                return $this->fail(
                    "Kode vendor \"$targetKode\" (dinormalisasi: \"$targetKodeNorm\") tidak ditemukan di sheet \"$targetSheet\". Cek apakah kode vendor di database sesuai dengan yang ada di Excel.",
                    404
                );
            }

            // Simpan ke DB (UPSERT)
            $existing = $this->model->where('supplier_id', $supplier_id)->where('periode', $periode)->first();
            $dataPpic = [
                'supplier_id'     => $supplier_id,
                'periode'         => $periode,
                'ppic_ot_percent' => round($scoreValue, 2)
            ];

            if ($existing) {
                $this->model->update($existing['id'], $dataPpic);
                $id = $existing['id'];
            } else {
                $id = $this->model->insert($dataPpic);
            }

            $message = $tidakAdaPO
                ? "Tidak ada PO/delivery untuk vendor $targetKode di periode ini."
                : "Score berhasil dibaca: " . round($scoreValue, 2) . "%";

            return $this->respond([
                'status'           => 'success',
                'message'          => $message,
                'tidak_ada_po'     => $tidakAdaPO,
                'excel_kode_vendor'=> $targetKode,
                'excel_nama_vendor'=> $namaVendorExcel,
                'score_percent'    => round($scoreValue, 2),
                'data'             => $this->model->find($id)
            ]);

        } catch (\Exception $e) {
            return $this->fail('Dapur Meledak: ' . $e->getMessage(), 500);
        }
    }


    /**
     * GET /api/penilaian/summary/dashboard
     * Ngitung statistik keseluruhan buat KPI cards di dashboard
     */
    public function dashboardSummary()
    {
        $db = \Config\Database::connect();

        // Hitung total supplier aktif
        $totalSuppliers = $db->table('m_supplier')->where('is_active', 1)->countAllResults();

        // Ambil periode terbaru yang ada datanya
        $latestPeriode = $db->table('t_penilaian')
            ->select('periode')
            ->orderBy('periode', 'DESC')
            ->limit(1)
            ->get()->getRow();

        $periode = $latestPeriode ? $latestPeriode->periode : null;

        $gradeA = 0;
        $gradeB = 0;
        $gradeC = 0;
        $pendingInput = $totalSuppliers; // Default: semua pending

        if ($periode) {
            $gradeA = 0;
            $gradeB = 0;
            $gradeC = 0;
            $sudahInput = $db->table('t_penilaian')->where('periode', $periode)->countAllResults();
            $pendingInput = max(0, $totalSuppliers - $sudahInput);
        }

        return $this->respond([
            'status'          => 200,
            'total_suppliers' => $totalSuppliers,
            'grade_a'         => $gradeA,
            'grade_b'         => $gradeB,
            'grade_c'         => $gradeC,
            'pending_input'   => $pendingInput,
            'latest_periode'  => $periode,
        ]);
    }

    /**
     * GET /api/penilaian/heatmap/data?periode=YYYY-MM
     * Ngambil semua data penilaian + info supplier buat master rekap heatmap
     */
    public function heatmapData()
    {
        $periode = $this->request->getGet('periode');

        $db = \Config\Database::connect();
        $builder = $db->table('t_penilaian p');
        $builder->select('p.*, s.nama_vendor, s.kode_vendor, s.jenis_bahan');
        $builder->join('m_supplier s', 's.id = p.supplier_id', 'inner');

        if ($periode) {
            $builder->where('p.periode', $periode);
        } else {
            // Kalo gak dikasih periode, ambil yang paling baru aja
            $latestPeriode = $db->table('t_penilaian')
                ->select('periode')
                ->orderBy('periode', 'DESC')
                ->limit(1)
                ->get()->getRow();
            if ($latestPeriode) {
                $builder->where('p.periode', $latestPeriode->periode);
            }
        }

        $builder->orderBy('p.id', 'DESC');
        $data = $builder->get()->getResultArray();

        return $this->respond($data);
    }

    /**
     * GET /api/penilaian/top-performers?limit=5
     * Ambil supplier dengan skor tertinggi di periode terbaru
     */
    public function topPerformers()
    {
        $limit = (int) ($this->request->getGet('limit') ?? 5);
        if ($limit <= 0 || $limit > 50) $limit = 5;

        $db = \Config\Database::connect();

        // Ambil periode terbaru dulu
        $latestPeriode = $db->table('t_penilaian')
            ->select('periode')
            ->orderBy('periode', 'DESC')
            ->limit(1)
            ->get()->getRow();

        if (!$latestPeriode) {
            return $this->respond([]);
        }

        $data = $db->table('t_penilaian p')
            ->select('p.qc_score, p.ppic_score, p.pch_score, p.hse_score, p.periode, s.nama_vendor, s.kode_vendor, s.jenis_bahan')
            ->join('m_supplier s', 's.id = p.supplier_id', 'inner')
            ->where('p.periode', $latestPeriode->periode)
            ->orderBy('p.qc_score', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();

        return $this->respond($data);
    }

    /**
     * GET /api/penilaian/evaluasi/detail
     *   ?kode_vendor=1003107&periode_awal=2025-07&periode_akhir=2025-12
     *
     * Mengembalikan data aktual bulan ke bulan dan rata-rata agregat
     * untuk satu vendor dalam rentang waktu yang ditentukan.
     */
    public function getDetailEvaluasi()
    {
        $kodeVendor   = $this->request->getGet('kode_vendor');
        $periodeAwal  = $this->request->getGet('periode_awal');
        $periodeAkhir = $this->request->getGet('periode_akhir');

        // ── Validasi parameter ────────────────────────────────────────────────
        if (empty($kodeVendor) || empty($periodeAwal) || empty($periodeAkhir)) {
            return $this->fail(
                'Parameter kode_vendor, periode_awal, dan periode_akhir wajib diisi.',
                400
            );
        }

        // Format YYYY-MM sederhana (tidak perlu regex berat)
        $periodePattern = '/^\d{4}-(0[1-9]|1[0-2])$/';
        if (!preg_match($periodePattern, $periodeAwal) || !preg_match($periodePattern, $periodeAkhir)) {
            return $this->fail('Format periode harus YYYY-MM (contoh: 2025-07).', 400);
        }

        if ($periodeAwal > $periodeAkhir) {
            return $this->fail('periode_awal tidak boleh lebih besar dari periode_akhir.', 400);
        }

        // ── Eksekusi query lewat Model ────────────────────────────────────────
        try {
            $result = $this->model->getDetailEvaluasi(
                trim($kodeVendor),
                $periodeAwal,
                $periodeAkhir
            );

            if (empty($result['data_aktual'])) {
                return $this->respond([
                    'status'       => 'empty',
                    'message'      => "Tidak ada data untuk vendor $kodeVendor dalam rentang $periodeAwal s/d $periodeAkhir.",
                    'nama_vendor'  => '-',
                    'jenis_bahan'  => '-',
                    'data_aktual'  => [],
                    'rata_rata'    => null,
                ]);
            }

            return $this->respond([
                'status'      => 'success',
                'nama_vendor' => $result['nama_vendor'],
                'jenis_bahan' => $result['jenis_bahan'],
                'data_aktual' => $result['data_aktual'],
                'rata_rata'   => $result['rata_rata'],
            ]);

        } catch (\Exception $e) {
            return $this->fail('Server error: ' . $e->getMessage(), 500);
        }
    }
}