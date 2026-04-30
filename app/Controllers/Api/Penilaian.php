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
            $scoreValue    = null;
            $namaVendorExcel = null;
            $tidakAdaPO    = false;

            // Cari baris yang cocok di kolom B (Kode Vendor)
            for ($row = 1; $row <= $highestRow; $row++) {
                $cellB = trim((string)$sheet->getCell('B' . $row)->getValue());

                if ($cellB === $targetKode) {
                    // Kolom C = Nama Vendor (untuk konfirmasi ke frontend)
                    $namaVendorExcel = trim((string)$sheet->getCell('C' . $row)->getValue());

                    // Kolom F = Score
                    $valF = $sheet->getCell('F' . $row)->getCalculatedValue();

                    if ($valF === '-' || $valF === null || $valF === '') {
                        // Tidak ada PO / delivery di bulan ini
                        $scoreValue  = 0;
                        $tidakAdaPO  = true;
                    } else {
                        // Excel simpan sebagai desimal (0.8396), kita jadikan persen (83.96)
                        $scoreValue = (float)$valF * 100;
                    }
                    break;
                }
            }

            // Bersihin memori
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            if ($scoreValue === null) {
                return $this->fail(
                    "Kode vendor \"$targetKode\" tidak ditemukan di sheet \"$targetSheet\". Pastikan kode vendor di Excel sesuai.",
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
            $gradeA = $db->table('t_penilaian')->where('periode', $periode)->where('grade', 'A')->countAllResults();
            $gradeB = $db->table('t_penilaian')->where('periode', $periode)->where('grade', 'B')->countAllResults();
            $gradeC = $db->table('t_penilaian')->where('periode', $periode)->where('grade', 'C')->countAllResults();
            $sudahInput = $gradeA + $gradeB + $gradeC;
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

        $builder->orderBy('p.total_score', 'DESC');
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
            ->select('p.total_score, p.grade, p.periode, s.nama_vendor, s.kode_vendor, s.jenis_bahan')
            ->join('m_supplier s', 's.id = p.supplier_id', 'inner')
            ->where('p.periode', $latestPeriode->periode)
            ->orderBy('p.total_score', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();

        return $this->respond($data);
    }
}