<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'kode_vendor' => '1003107',
                'nama_vendor' => 'SRIREJEKI PERDANA STEEL, PT',
                'jenis_bahan' => 'PIPA'
            ],
            [
                'kode_vendor' => '1003043',
                'nama_vendor' => 'INDONESIA STEEL TUBE WORKS, PT',
                'jenis_bahan' => 'PIPA'
            ],
            [
                'kode_vendor' => '1003011',
                'nama_vendor' => 'CONEX INTI MAKMUR, PT',
                'jenis_bahan' => 'KAYU'
            ],
            [
                'kode_vendor' => '1003012',
                'nama_vendor' => 'DAEKAN INDAR INDONESIA, PT',
                'jenis_bahan' => 'KAYU'
            ],
            [
                'kode_vendor' => '1003078',
                'nama_vendor' => 'POSCO ( IJPC ) PT.',
                'jenis_bahan' => 'PLATE'
            ],
        ];

        // Masukin data ke tabel m_supplier
        $this->db->table('m_supplier')->insertBatch($data);
    }
}