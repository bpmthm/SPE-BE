<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\SupplierModel;

class Supplier extends ResourceController
{
    protected $format = 'json';

    // Munculin 42 Vendor buat Dropdown di FE
    public function index()
    {
        $model = new SupplierModel();
        $data = $model->findAll();
        return $this->respond($data);
    }

    // Fungsi "Pinter" buat simpen data per divisi (UPSERT)
    public function savePenilaian()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('t_penilaian');

        $supplier_id = $this->request->getPost('supplier_id');
        $periode     = $this->request->getPost('periode'); // Format: 2026-04
        $divisi      = $this->request->getPost('divisi');  // 'QC', 'PPIC', 'PCH', atau 'HSE'
        
        // 1. Ambil data input mentah
        $val = $this->request->getPost('value');
        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        // 2. Logic Itung Skor Otomatis sesuai standar Si Ibu
        if ($divisi == 'QC') {
            $updateData['qc_ng_percent'] = $val;
            $updateData['qc_score'] = ($val < 0.5) ? 30 : (($val < 1.0) ? 15 : 10);
        } 
        elseif ($divisi == 'PPIC') {
            $updateData['ppic_ot_percent'] = $val;
            $updateData['ppic_score'] = ($val >= 90) ? 30 : (($val >= 71) ? 15 : 10);
        }
        // ... (lo bisa tambahin buat PCH & HSE di sini)

        // 3. Proses UPSERT (Update if exists, else Insert)
        $existing = $builder->where(['supplier_id' => $supplier_id, 'periode' => $periode])->get()->getRow();

        if ($existing) {
            $builder->where('id', $existing->id)->update($updateData);
            return $this->respondUpdated(['message' => "Data $divisi Berhasil di-Update, Pi!"]);
        } else {
            $updateData['supplier_id'] = $supplier_id;
            $updateData['periode']     = $periode;
            $builder->insert($updateData);
            return $this->respondCreated(['message' => "Data $divisi Baru Berhasil Disimpan!"]);
        }
    }
}