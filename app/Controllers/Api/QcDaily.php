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
        $db = \Config\Database::connect();
        $builder = $db->table('t_qc_daily q');
        $builder->select('q.*, s.nama_vendor, s.kode_vendor');
        $builder->join('m_supplier s', 'q.supplier_id = s.id', 'left');
        $builder->orderBy('q.id', 'DESC');
        $results = $builder->get()->getResultArray();

        return $this->respond($results);
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
