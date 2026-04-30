<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\SupplierModel;
use App\Models\PenilaianModel;
use CodeIgniter\API\ResponseTrait;

class Supplier extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        $model = new SupplierModel();
        return $this->respond($model->where('is_active', 1)->findAll());
    }

    public function getQtySap()
    {
        // Tangkap parameter dari request GET frontend
        $kode_vendor = $this->request->getGet('kode_vendor');
        $bulan       = $this->request->getGet('bulan');
        $tahun       = $this->request->getGet('tahun');

        // Validasi simpel
        if (!$kode_vendor || !$bulan || !$tahun) {
            return $this->fail('Parameter kode_vendor, bulan, dan tahun harus diisi', 400);
        }

        $model = new \App\Models\SupplierModel();
        
        // Panggil fungsi jembatan yang udah kita bikin di Model
        $qty = $model->getQtyFromSAP($kode_vendor, $bulan, $tahun);

        // null artinya SAP tidak bisa dijangkau (driver / koneksi error)
        if ($qty === null) {
            return $this->respond([
                'status'        => 200,
                'kode_vendor'   => $kode_vendor,
                'periode'       => "$tahun-$bulan",
                'total_qty'     => null,
                'sap_available' => false,
                'message'       => 'Koneksi ke SAP tidak tersedia. Isi QTY secara manual.'
            ]);
        }

        return $this->respond([
            'status'        => 200,
            'kode_vendor'   => $kode_vendor,
            'periode'       => "$tahun-$bulan",
            'total_qty'     => $qty,
            'sap_available' => true
        ]);
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