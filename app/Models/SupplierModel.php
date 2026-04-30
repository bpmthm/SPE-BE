<?php

namespace App\Models;

use CodeIgniter\Model;

class SupplierModel extends Model
{
    protected $table            = 'm_supplier';
    protected $primaryKey       = 'id';
    
    // WAJIB: Tambahin is_active di sini!
    protected $allowedFields    = ['kode_vendor', 'nama_vendor', 'jenis_bahan', 'is_active'];

    // Jembatan buat narik total QTY dari SAP
    public function getQtyFromSAP($kode_vendor, $bulan, $tahun) {
        try {
            // Panggil koneksi ke-2 yang tadi kita bikin di Database.php
            $dbSap = \Config\Database::connect('sap');
            
            $builder = $dbSap->table('TBL_BPB H');
            $builder->select('SUM(L.QTY_BPB) as total');
            $builder->join('TBL_BPB_LINE L', 'H.NO_BPB = L.NO_BPB', 'inner');
            $builder->where('H.NO_VENDOR', $kode_vendor);
            $builder->where('MONTH(H.POSTING_DATE)', $bulan);
            $builder->where('YEAR(H.POSTING_DATE)', $tahun);
            
            $builder->groupStart()
                    ->where('H.STATUS_DELETE', 0)
                    ->orWhere('H.STATUS_DELETE IS NULL')
                    ->groupEnd();

            $builder->groupStart()
                    ->where('H.STATUS_CANCEL', 0)
                    ->orWhere('H.STATUS_CANCEL IS NULL')
                    ->groupEnd();

            $result = $builder->get()->getRow();
            return $result ? (float) $result->total : 0;

        } catch (\Throwable $e) {
            // Kalo driver sqlsrv belum install atau koneksi SAP gagal,
            // log errornya tapi jangan crash — return 0 aja biar UI tetap jalan
            log_message('error', '[SupplierModel::getQtyFromSAP] SAP connection failed: ' . $e->getMessage());
            return null; // null = SAP tidak bisa dijangkau (beda dari 0 = emang kosong)
        }
    }
}