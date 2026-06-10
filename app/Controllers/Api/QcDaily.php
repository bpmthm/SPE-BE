<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\QcDailyModel;
use CodeIgniter\API\ResponseTrait;

class QcDaily extends ResourceController
{
    use ResponseTrait;

    protected $modelName = 'App\Models\QcDailyModel';
    protected $format    = 'json';

    /**
     * GET /api/qc-daily
     * Mengambil daftar input daily QC harian beserta nama vendor
     */
    public function index()
    {
        $limit = $this->request->getGet('limit') ? (int) $this->request->getGet('limit') : 25;
        $page  = $this->request->getGet('page') ? (int) $this->request->getGet('page') : 1;
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 25;
        $offset = ($page - 1) * $limit;

        $search = $this->request->getGet('search');
        $bulan  = $this->request->getGet('bulan');

        $db = \Config\Database::connect();
        
        $buildQuery = function() use ($db, $search, $bulan) {
            $builder = $db->table('t_qc_daily q');
            $builder->join('m_supplier s', 'q.supplier_id = s.id', 'left');
            
            if (!empty($search)) {
                $builder->groupStart()
                        ->like('s.nama_vendor', $search)
                        ->orLike('s.kode_vendor', $search)
                        ->orLike('q.material_desc', $search)
                        ->orLike('q.material_code', $search)
                        ->orLike('q.no_surat_jalan', $search)
                        ->groupEnd();
            }
            
            if (!empty($bulan)) {
                $builder->like('q.tanggal_terima', $bulan, 'after');
            }
            
            return $builder;
        };

        // 1. Dapatkan statisktik dari dataset yang difilter
        $statsBuilder = $buildQuery();
        $statsBuilder->selectCount('q.id', 'total_penerimaan');
        $statsBuilder->selectSum('q.qty_masuk', 'total_qty_masuk');
        $statsBuilder->selectSum('q.qty_reject', 'total_qty_reject');
        $stats = $statsBuilder->get()->getRowArray();

        $totalPenerimaan = (int) ($stats['total_penerimaan'] ?? 0);
        $totalQtyMasuk   = (int) ($stats['total_qty_masuk'] ?? 0);
        $totalQtyReject  = (int) ($stats['total_qty_reject'] ?? 0);
        $ngRate = $totalQtyMasuk > 0 ? ($totalQtyReject / $totalQtyMasuk) * 100 : 0;

        // 2. Ambil data per halaman
        $dataBuilder = $buildQuery();
        $dataBuilder->select('q.*, s.nama_vendor, s.kode_vendor');
        $dataBuilder->orderBy('q.id', 'DESC');
        $dataBuilder->limit($limit, $offset);
        $results = $dataBuilder->get()->getResultArray();

        return $this->respond([
            'status' => 'success',
            'data'   => $results,
            'pagination' => [
                'total'       => $totalPenerimaan,
                'limit'       => $limit,
                'page'        => $page,
                'total_pages' => ceil($totalPenerimaan / $limit)
            ],
            'stats' => [
                'total_penerimaan' => $totalPenerimaan,
                'qty_masuk'        => $totalQtyMasuk,
                'qty_reject'       => $totalQtyReject,
                'ng_rate'          => $ngRate
            ]
        ]);
    }

    /**
     * POST /api/qc-daily
     * Menyimpan data input harian baru ke t_qc_daily
     */
    public function create()
    {
        $rules = [
            'tanggal_terima' => 'required|valid_date[Y-m-d]',
            'supplier_id'    => 'required|integer',
            'no_surat_jalan' => 'required|min_length[1]|max_length[50]',
            'material_code'  => 'required|min_length[1]|max_length[50]',
            'material_desc'  => 'required|min_length[1]|max_length[255]',
            'qty_masuk'      => 'required|integer|greater_than_equal_to[0]',
            'qty_reject'     => 'required|integer|greater_than_equal_to[0]',
        ];

        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors());
        }

        $data = $this->request->getPost();
        
        // Simpan
        $model = new QcDailyModel();
        if ($model->insert($data)) {
            return $this->respondCreated([
                'status'  => 'success',
                'message' => 'Data daily QC berhasil disimpan'
            ]);
        }

        return $this->fail('Gagal menyimpan data daily QC');
    }

    /**
     * GET /api/sap/materials
     * Live search material ke SQL Server (sap)
     */
    public function searchMaterials()
    {
        $queryStr = $this->request->getGet('q');
        if (empty($queryStr) || strlen($queryStr) < 2) {
            return $this->respond([]);
        }

        try {
            ini_set('sqlsrv.ConnectTimeout', 5);
            $dbSap = \Config\Database::connect('sap');
            $builder = $dbSap->table('TBL_MATERIAL');
            
            // Ambil MATERIALCODE dan DESCRIPTION
            $builder->select('MATERIALCODE, DESCRIPTION');
            $builder->groupStart()
                    ->like('MATERIALCODE', $queryStr)
                    ->orLike('DESCRIPTION', $queryStr)
                    ->groupEnd();
            
            // Batasi agar query super cepat (20 records)
            $builder->limit(20);
            
            $results = $builder->get()->getResultArray();
            return $this->respond($results);

        } catch (\Throwable $e) {
            log_message('error', '[QcDaily::searchMaterials] SAP connection error: ' . $e->getMessage());
            return $this->fail('Gagal melakukan pencarian ke database SAP: ' . $e->getMessage());
        }
    }

    /**
     * PUT/PATCH /api/qc-daily/{id}
     * Mengupdate data input harian di t_qc_daily
     */
    public function update($id = null)
    {
        $model = new QcDailyModel();
        
        // Cek apakah datanya ada di database
        if (!$model->find($id)) {
            return $this->failNotFound('Data tidak ditemukan');
        }

        $rules = [
            'tanggal_terima' => 'required|valid_date[Y-m-d]',
            'supplier_id'    => 'required|integer',
            'no_surat_jalan' => 'required|min_length[1]|max_length[50]',
            'material_code'  => 'required|min_length[1]|max_length[50]',
            'material_desc'  => 'required|min_length[1]|max_length[255]',
            'qty_masuk'      => 'required|integer|greater_than_equal_to[0]',
            'qty_reject'     => 'required|integer|greater_than_equal_to[0]',
        ];

        // Ambil data dari request PUT
        // Mendukung format JSON (frontend) maupun x-www-form-urlencoded
        $data = $this->request->getJSON(true) ?: $this->request->getRawInput();

        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors());
        }

        // Jalankan proses update
        if ($model->update($id, $data)) {
            return $this->respond([
                'status'  => 'success',
                'message' => 'Data daily QC berhasil diperbarui'
            ]);
        }

        return $this->fail('Gagal memperbarui data daily QC');
    }

    // Menghapus data input harian dari t_qc_daily
    public function delete($id = null)
    {
        $model = new QcDailyModel();
        
        // Cek apakah datanya ada di database
        if (!$model->find($id)) {
            return $this->failNotFound('Data tidak ditemukan');
        }

        // Jalankan proses delete
        if ($model->delete($id)) {
            return $this->respondDeleted([
                'status'  => 'success',
                'message' => 'Data daily QC berhasil dihapus'
            ]);
        }

        return $this->fail('Gagal menghapus data daily QC');
    }
}
