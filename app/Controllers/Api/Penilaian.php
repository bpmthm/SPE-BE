<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class Penilaian extends ResourceController
{
    protected $modelName = 'App\Models\PenilaianModel';
    protected $format    = 'json';

    // GET /api/penilaian (Daftar semua dengan filter)
    public function index()
    {
        $supplier_id = $this->request->getVar('supplier_id');
        $periode = $this->request->getVar('periode');

        $query = $this->model;
        if ($supplier_id) $query = $query->where('supplier_id', $supplier_id);
        if ($periode) $query = $query->where('periode', $periode);

        return $this->respond($query->orderBy('periode', 'DESC')->findAll());
    }

    // POST /api/penilaian (The Smart UPSERT)
    public function create()
    {
        $data = $this->request->getJSON(true);

        if (!isset($data['supplier_id']) || !isset($data['periode'])) {
            return $this->fail('supplier_id dan periode wajib diisi Pi!', 400);
        }

        // Cari data lama buat UPSERT
        $existing = $this->model
            ->where('supplier_id', $data['supplier_id'])
            ->where('periode', $data['periode'])
            ->first();

        if ($existing) {
            $this->model->update($existing['id'], $data);
            return $this->respond(['message' => 'Data updated!', 'data' => $this->model->find($existing['id'])]);
        }

        $id = $this->model->insert($data);
        return $this->respondCreated(['id' => $id, 'message' => 'Data created!']);
    }

    // GET /api/penilaian/summary/dashboard
    public function dashboardSummary()
    {
        $latest = $this->model->selectMax('periode')->first();
        $periode = $latest['periode'] ?? date('Y-m');

        $data = $this->model->where('periode', $periode)->findAll();

        $summary = [
            'total_suppliers' => count($data),
            'grade_a'         => count(array_filter($data, fn($p) => ($p['grade'] ?? '') === 'A')),
            'grade_c'         => count(array_filter($data, fn($p) => ($p['grade'] ?? '') === 'C')),
            'pending_input'   => 42 - count($data)
        ];

        return $this->respond($summary);
    }

    // GET /api/penilaian/heatmap
    public function heatmapData()
    {
        $periode = $this->request->getVar('periode') ?? date('Y-m');

        return $this->respond(
            $this->model
                ->join('m_supplier', 'm_supplier.id = t_penilaian.supplier_id')
                ->where('periode', $periode)
                ->select('t_penilaian.*, m_supplier.nama_vendor, m_supplier.jenis_bahan')
                ->orderBy('total_score', 'DESC')
                ->findAll()
        );
    }

    // GET /api/penilaian/top-performers
    public function topPerformers()
    {
        $limit = $this->request->getVar('limit') ?? 5;
        return $this->respond(
            $this->model
                ->join('m_supplier', 'm_supplier.id = t_penilaian.supplier_id')
                ->select('m_supplier.nama_vendor, t_penilaian.total_score')
                ->orderBy('total_score', 'DESC')
                ->limit($limit)
                ->findAll()
        );
    }

    /**
     * POST /api/penilaian/upload-ppic
     * Handle Excel upload dari departemen PPIC
     */
  /**
     * POST /api/penilaian/upload-ppic
     * Handle Excel upload dari departemen PPIC (Support multi-sheet & auto-search vendor)
     */
    public function uploadPpic()
    {
        $supplier_id = $this->request->getPost('supplier_id');
        $periode     = $this->request->getPost('periode');
        $file        = $this->request->getFile('ppic_file');

        if (!$supplier_id || !$periode || !$file) {
            return $this->fail('Data tidak lengkap Pi! Pastiin supplier, periode, dan file Excel udah diisi.', 400);
        }

        if (!$file->isValid() || $file->hasMoved()) {
            return $this->fail('File gagal diupload atau error.', 400);
        }

        try {
            // 1. Cari Kode Vendor dari Database biar bisa dicocokin sama Excel
            $supplierModel = new \App\Models\SupplierModel();
            $supplierData = $supplierModel->find($supplier_id);
            if (!$supplierData) return $this->fail('Supplier tidak ditemukan di database.', 404);
            
            $kode_vendor_target = $supplierData['kode_vendor'];

            // 2. Load File Excel
            $filepath = $file->getTempName();
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);
            
            // 3. Fokus cari sheet yang namanya "LIST" atau "LIST " (karena di file lo ada spasinya)
            $worksheet = $spreadsheet->getSheetByName('LIST ') 
                         ?? $spreadsheet->getSheetByName('LIST') 
                         ?? $spreadsheet->getActiveSheet(); // Fallback ke sheet manapun yg kebuka

            $highestRow = $worksheet->getHighestRow();
            $ot_percent = null;

            // 4. SCANNING EXCEL: Cari baris yang Kode Vendor-nya cocok (Mulai dari baris 6 karena atasnya header)
            for ($row = 2; $row <= $highestRow; $row++) {
                // Di file lo, Kode Vendor ada di Kolom B (Index 2)
                $kodeVendorExcel = $worksheet->getCell('B' . $row)->getValue();

                if ((string)$kodeVendorExcel === (string)$kode_vendor_target) {
                    // Kalo ketemu! Ambil nilai Score di Kolom F
                    $nilaiDesimal = $worksheet->getCell('F' . $row)->getCalculatedValue();
                    
                    // Kalo dapetnya string "-" (Kayak vendor AKZONOBEL di file lo), kita anggap 0
                    if ($nilaiDesimal === '-') {
                        $ot_percent = 0;
                    } else {
                        // Ubah desimal (0.839) jadi persen (83.9)
                        $ot_percent = (float)$nilaiDesimal * 100;
                    }
                    break; // Stop pencarian, udah dapet datanya
                }
            }

            // Kalo sampe baris terakhir vendornya gak ada di Excel
            if ($ot_percent === null) {
                return $this->fail("Data vendor {$supplierData['nama_vendor']} ({$kode_vendor_target}) tidak ditemukan di dalam sheet LIST Excel ini.", 404);
            }

            // 5. Simpan ke Database (UPSERT)
            $existing = $this->model
                ->where('supplier_id', $supplier_id)
                ->where('periode', $periode)
                ->first();

            $dataToSave = [
                'supplier_id' => $supplier_id,
                'periode'     => $periode,
                'ppic_ot_percent' => round($ot_percent, 2), // Bulatkan 2 angka di belakang koma
            ];

            if ($existing) {
                $this->model->update($existing['id'], $dataToSave);
                $id = $existing['id'];
            } else {
                $id = $this->model->insert($dataToSave);
            }

            return $this->respond([
                'status'  => 'success',
                'message' => 'Excel berhasil discan! Ketepatan waktu ditarik: ' . round($ot_percent, 2) . '%',
                'data'    => $this->model->find($id)
            ]);

        } catch (\Exception $e) {
            return $this->fail('Gagal baca Excel: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/penilaian/upsert
     * Jalur khusus (Ultimate) buat nyimpen data dari UI Form
     */
    public function upsert()
    {
        $json = $this->request->getJSON(true);
        
        if (!$json || empty($json['supplier_id']) || empty($json['periode'])) {
            return $this->fail('Supplier dan Periode wajib diisi', 400);
        }

        // Cek apakah bulan ini vendor tersebut udah punya rapor?
        $existing = $this->model
            ->where('supplier_id', $json['supplier_id'])
            ->where('periode', $json['periode'])
            ->first();

        try {
            if ($existing) {
                $this->model->update($existing['id'], $json);
                $id = $existing['id'];
            } else {
                $id = $this->model->insert($json);
            }

            return $this->respond([
                'status' => 'success',
                'message' => 'Data penilaian berhasil disubmit!',
                'data' => $this->model->find($id)
            ]);
        } catch (\Exception $e) {
            return $this->fail('Gagal nyimpen ke database: ' . $e->getMessage(), 500);
        }
    }
}