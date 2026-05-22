<?php

namespace App\Models;

use CodeIgniter\Model;

class QcDailyModel extends Model
{
    protected $table            = 't_qc_daily';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'tanggal_terima',
        'no_surat_jalan',
        'supplier_id',
        'material_code',
        'material_desc',
        'qty_masuk',
        'qty_reject',
        'keterangan'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
