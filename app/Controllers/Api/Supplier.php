<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\SupplierModel;
use App\Models\PenilaianModel;

class Supplier extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        $model = new SupplierModel();
        return $this->respond($model->findAll());
    }

    public function savePenilaian()
    {
        $model = new PenilaianModel();
        
        $supplier_id = $this->request->getPost('supplier_id');
        $periode     = $this->request->getPost('periode');
        $divisi      = $this->request->getPost('divisi');
        $val         = $this->request->getPost('value');

        // Cari apakah baris periode ini sudah ada?
        $existing = $model->where(['supplier_id' => $supplier_id, 'periode' => $periode])->first();

        $saveData = [
            'supplier_id' => $supplier_id,
            'periode'     => $periode,
        ];

        // Mapping input ke kolom yang bener
        if ($divisi == 'QC') $saveData['qc_ng_percent'] = $val;
        if ($divisi == 'PPIC') $saveData['ppic_ot_percent'] = $val;

        if ($existing) {
            $model->update($existing['id'], $saveData);
            $msg = "Data $divisi berhasil diupdate!";
        } else {
            $model->insert($saveData);
            $msg = "Data $divisi baru berhasil disimpan!";
        }

        return $this->respond(['status' => 'success', 'message' => $msg]);
    }
}