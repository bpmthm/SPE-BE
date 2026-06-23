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

    /**
     * GET /api/supplier/all
     * Ambil SEMUA vendor termasuk yang non-aktif (untuk Master Vendor page)
     */
    public function allSuppliers()
    {
        $model = new SupplierModel();
        return $this->respond($model->findAll());
    }

    /**
     * POST /api/supplier/toggle-status/{id}
     * Flip is_active: 1 → 0, 0 → 1
     */
    public function toggleStatus($id = null)
    {
        $model = new SupplierModel();
        $vendor = $model->find($id);

        if (!$vendor) {
            return $this->failNotFound('Vendor tidak ditemukan');
        }

        $newStatus = (int)$vendor['is_active'] === 1 ? 0 : 1;
        $model->update($id, ['is_active' => $newStatus]);

        return $this->respond([
            'status'    => 'success',
            'id'        => $id,
            'is_active' => $newStatus,
            'message'   => $newStatus === 1 ? 'Vendor berhasil diaktifkan' : 'Vendor berhasil dinonaktifkan'
        ]);
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

    /**
     * GET /api/supplier/search-sap
     * Mencari vendor/supplier dari SAP (SQL Server) secara live.
     * Menggunakan try-catch agar jika server SAP offline atau driver SQLSRV belum terinstall,
     * sistem tetap berjalan dengan fallback mock data SAP yang realistis.
     */
    public function searchSap()
    {
        $keyword = $this->request->getGet('q');
        if (empty($keyword) || strlen($keyword) < 2) {
            return $this->respond([]);
        }

        try {
            $dbSap = \Config\Database::connect('sap');
            // Kolom aktual di SQL Server SAP: NO_VENDOR, NAMA_VENDOR, TOP_DAYS
            $builder = $dbSap->table('TBL_VENDOR');
            $builder->select('NO_VENDOR as VENDOR_CODE, NAMA_VENDOR as VENDOR_NAME, TOP_DAYS');
            $builder->groupStart()
                    ->like('NO_VENDOR', $keyword)
                    ->orLike('NAMA_VENDOR', $keyword)
                    ->groupEnd();
            $builder->orderBy('NAMA_VENDOR', 'ASC');
            $builder->limit(50);
            $results = $builder->get()->getResultArray();
            return $this->respond($results);

        } catch (\Throwable $e) {
            log_message('error', '[Supplier::searchSap] SQL Server/SAP search failed, falling back to mock: ' . $e->getMessage());

            // List Vendor SAP Mock yang Realistis dengan TOP_DAYS
            $mockSapVendors = [
                ['VENDOR_CODE' => '1003112', 'VENDOR_NAME' => 'INDOMETAL PERKASA, PT', 'TOP_DAYS' => 60],
                ['VENDOR_CODE' => '1003113', 'VENDOR_NAME' => 'SURYA BAJA UTAMA, CV', 'TOP_DAYS' => 45],
                ['VENDOR_CODE' => '1003114', 'VENDOR_NAME' => 'GLOBAL PLASTINDO, PT', 'TOP_DAYS' => 30],
                ['VENDOR_CODE' => '1003115', 'VENDOR_NAME' => 'MITRA MANDIRI TEKNIK, CV', 'TOP_DAYS' => 15],
                ['VENDOR_CODE' => '1003116', 'VENDOR_NAME' => 'KAYU INDAH NUSANTARA, PT', 'TOP_DAYS' => 90],
                ['VENDOR_CODE' => '1003117', 'VENDOR_NAME' => 'BINTANG LOGAM JAYA, PT', 'TOP_DAYS' => 60],
                ['VENDOR_CODE' => '1003118', 'VENDOR_NAME' => 'CAHAYA ABADI ELEKTRIK, CV', 'TOP_DAYS' => 30],
                ['VENDOR_CODE' => '1003119', 'VENDOR_NAME' => 'DWIJAYA PACKAGING, PT', 'TOP_DAYS' => 45],
                ['VENDOR_CODE' => '1003120', 'VENDOR_NAME' => 'ALUMEX NUSANTARA, PT', 'TOP_DAYS' => 0],
                ['VENDOR_CODE' => '1003121', 'VENDOR_NAME' => 'ANUGERAH PLASTIK, CV', 'TOP_DAYS' => 15]
            ];

            // Filter manual berdasarkan keyword pencarian
            $filtered = [];
            foreach ($mockSapVendors as $vendor) {
                if (stripos($vendor['VENDOR_CODE'], $keyword) !== false || stripos($vendor['VENDOR_NAME'], $keyword) !== false) {
                    $filtered[] = $vendor;
                }
            }

            return $this->respond($filtered);
        }
    }

    /**
     * POST /api/supplier/sync
     * Mensinkronkan/menyimpan data vendor dari SAP ke database lokal m_supplier.
     */
    public function sync()
    {
        $input = $this->request->getJSON(true);
        $vendorCode = $input['vendor_code'] ?? null;
        $vendorName = $input['vendor_name'] ?? null;
        $topDays    = isset($input['top_days']) ? (int)$input['top_days'] : 0;

        if (!$vendorCode || !$vendorName) {
            return $this->fail('Parameter vendor_code dan vendor_name wajib diisi', 400);
        }

        $model = new SupplierModel();

        // Cek apakah kode vendor sudah terdaftar secara lokal
        $existing = $model->where('kode_vendor', $vendorCode)->first();
        if ($existing) {
            return $this->fail('Vendor dengan kode "' . $vendorCode . '" sudah terdaftar di database lokal!', 409);
        }

        // Tentukan kategori default untuk vendor baru dari SAP
        $defaultCategory = 'RAW MATERIAL';

        $newData = [
            'kode_vendor' => $vendorCode,
            'nama_vendor' => $vendorName,
            'jenis_bahan' => $defaultCategory,
            'top_days'    => $topDays,
            'is_active'   => 1
        ];

        try {
            $model->insert($newData);
            return $this->respondCreated([
                'status'  => 'success',
                'message' => 'Vendor "' . $vendorName . '" berhasil disinkronkan ke database lokal!',
                'data'    => $newData
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[Supplier::sync] Save failed: ' . $e->getMessage());
            return $this->fail('Gagal menyinkronkan data vendor ke lokal: ' . $e->getMessage());
        }
    }

    /**
     * PUT/PATCH /api/supplier/{id}
     * Mengupdate data master vendor (misal jenis_bahan atau top_days)
     */
    public function update($id = null)
    {
        $model = new SupplierModel();
        
        $vendor = $model->find($id);
        if (!$vendor) {
            return $this->failNotFound('Vendor tidak ditemukan');
        }

        $input = $this->request->getJSON(true) ?: $this->request->getRawInput();
        
        $updateData = [];
        if (isset($input['nama_vendor'])) $updateData['nama_vendor'] = $input['nama_vendor'];
        if (isset($input['jenis_bahan'])) $updateData['jenis_bahan'] = $input['jenis_bahan'];
        if (isset($input['top_days']))    $updateData['top_days']    = (int)$input['top_days'];
        if (isset($input['is_active']))   $updateData['is_active']   = (int)$input['is_active'];

        if (empty($updateData)) {
            return $this->fail('Tidak ada data untuk diupdate');
        }

        try {
            $model->update($id, $updateData);
            return $this->respond([
                'status'  => 'success',
                'message' => 'Data vendor berhasil diupdate',
                'data'    => array_merge($vendor, $updateData)
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[Supplier::update] Update failed: ' . $e->getMessage());
            return $this->fail('Gagal mengupdate data vendor: ' . $e->getMessage());
        }
    }
}