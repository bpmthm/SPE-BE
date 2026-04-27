<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['kode_vendor' => '1003107', 'nama_vendor' => 'SRIREJEKI PERDANA STEEL, PT', 'jenis_bahan' => 'PIPA'],
            ['kode_vendor' => '1003043', 'nama_vendor' => 'INDONESIA STEEL TUBE WORKS, PT', 'jenis_bahan' => 'PIPA'],
            ['kode_vendor' => '1003078', 'nama_vendor' => 'POSCO ( IJPC ) PT.', 'jenis_bahan' => 'PLATE'],
            ['kode_vendor' => '1003011', 'nama_vendor' => 'CONEX INTI MAKMUR, PT', 'jenis_bahan' => 'KAYU'],
            ['kode_vendor' => '1003067', 'nama_vendor' => 'MULTIARTHA WIDJAJA SENTOSA, PT', 'jenis_bahan' => 'KAYU'],
            ['kode_vendor' => '1003012', 'nama_vendor' => 'DAEKAN INDAR INDONESIA, PT', 'jenis_bahan' => 'KAYU'],
            ['kode_vendor' => '1003042', 'nama_vendor' => 'INDONESIA MATSUYA, PT', 'jenis_bahan' => 'KAYU'],
            ['kode_vendor' => '1003061', 'nama_vendor' => 'MARGA BHARATA,PT', 'jenis_bahan' => 'KAYU'],
            ['kode_vendor' => '1003094', 'nama_vendor' => 'ROYAL ABADI SEJAHTERA, PT', 'jenis_bahan' => 'FOAM'],
            ['kode_vendor' => '1003115', 'nama_vendor' => 'TRI SUKSES JAYA, PT', 'jenis_bahan' => 'FABRIC'],
            ['kode_vendor' => '1003111', 'nama_vendor' => 'TJIKKO, PT', 'jenis_bahan' => 'HARDWARE'],
            ['kode_vendor' => '1003018', 'nama_vendor' => 'ERLANGGA, PT', 'jenis_bahan' => 'HARDWARE'],
            ['kode_vendor' => '1003006', 'nama_vendor' => 'ARMSTRONG, PT', 'jenis_bahan' => 'RUBBER'],
            ['kode_vendor' => '1003039', 'nama_vendor' => 'IMAI, PT', 'jenis_bahan' => 'HARDWARE'],
            ['kode_vendor' => '1003098', 'nama_vendor' => 'SANTO PLASTIC, PT', 'jenis_bahan' => 'PLASTIK'],
            ['kode_vendor' => '1003083', 'nama_vendor' => 'POLYNDO, PT', 'jenis_bahan' => 'CHEMICAL'],
            ['kode_vendor' => '1003031', 'nama_vendor' => 'HADI, PT', 'jenis_bahan' => 'HARDWARE'],
            ['kode_vendor' => '1003009', 'nama_vendor' => 'CAKRAWALA MEGA INDAH, PT', 'jenis_bahan' => 'PAPER'],
            ['kode_vendor' => '1003017', 'nama_vendor' => 'DWI KARYA PACKINDO, PT', 'jenis_bahan' => 'PACKING'],
            ['kode_vendor' => '1003005', 'nama_vendor' => 'ARTEK SEIKOU, PT', 'jenis_bahan' => 'HARDWARE'],
            ['kode_vendor' => '1003119', 'nama_vendor' => 'TRIJAYA MANDIRI DUSINDO, PT', 'jenis_bahan' => 'PACKING'],
            ['kode_vendor' => '1007528', 'nama_vendor' => 'ASAHI FAMILY, CV', 'jenis_bahan' => 'CARTON BOX'],
            ['kode_vendor' => '1003027', 'nama_vendor' => 'GARUDA METALINDO, PT', 'jenis_bahan' => 'FASTENER'],
            ['kode_vendor' => '1003028', 'nama_vendor' => 'GINSA INTI PRATAMA PT.', 'jenis_bahan' => 'FASTENER'],
            ['kode_vendor' => '1003062', 'nama_vendor' => 'MEGA WAJA CORPORINDO, PT', 'jenis_bahan' => 'FASTENER'],
            ['kode_vendor' => '1002993', 'nama_vendor' => 'ATEJA TRITUNGGAL, PT', 'jenis_bahan' => 'COVER'],
            ['kode_vendor' => '1002817', 'nama_vendor' => 'SINAR CONTINENTAL, PT.', 'jenis_bahan' => 'COVER'],
            ['kode_vendor' => '1003063', 'nama_vendor' => 'MEIWA INDONESIA, PT', 'jenis_bahan' => 'COVER'],
            ['kode_vendor' => '1003097', 'nama_vendor' => 'SAN CENTRAL INDAH, PT', 'jenis_bahan' => 'POWDER COATING'],
            ['kode_vendor' => '1002918', 'nama_vendor' => 'AKZONOBEL WOOD FINISHES AND ADHESIV', 'jenis_bahan' => 'POWDER COATING'],
            ['kode_vendor' => '1002994', 'nama_vendor' => 'AXALTA POWDER COATING SYSTEMS INDONESIA', 'jenis_bahan' => 'POWDER COATING'],
            ['kode_vendor' => '1003108', 'nama_vendor' => 'STAR MUSTIKA PLASTMETAL,PT', 'jenis_bahan' => 'OTHER'],
            ['kode_vendor' => '1003037', 'nama_vendor' => 'HIDAYAT MULIA SEJATI, PT', 'jenis_bahan' => 'SUB KONT'],
            ['kode_vendor' => '1003038', 'nama_vendor' => 'HINANI, CV', 'jenis_bahan' => 'SUB KONT'],
            ['kode_vendor' => '1003134', 'nama_vendor' => 'RAJAWALI SAKTI, CV', 'jenis_bahan' => 'SUB KONT'],
            ['kode_vendor' => '1003102', 'nama_vendor' => 'NUMAN BASIR / SINAR CEMERLANG JAYA', 'jenis_bahan' => 'SUB KONT'],
            ['kode_vendor' => '1002996', 'nama_vendor' => 'BAHAGIA SEJAHTERA METALINDO, PT', 'jenis_bahan' => 'SUB KONT'],
            ['kode_vendor' => '1003090', 'nama_vendor' => 'REKA CIPTA ANUGRAH, PT', 'jenis_bahan' => 'SUB KONT'],
            ['kode_vendor' => '1003124', 'nama_vendor' => 'TRISONS COVER JAYA, PT', 'jenis_bahan' => 'SUB KONT'],
            ['kode_vendor' => '1004161', 'nama_vendor' => 'BOSSAY MEDICAL APPLIANCE CO.,LTD.', 'jenis_bahan' => 'PART NURSING BED'],
            ['kode_vendor' => '1003269', 'nama_vendor' => 'TiMOTION Technology', 'jenis_bahan' => 'PART NURSING BED'],
            ['kode_vendor' => '1003093', 'nama_vendor' => 'RODA HAMERINDO JAYA, PT', 'jenis_bahan' => 'RODA'],
        ];

        $this->db->table('m_supplier')->insertBatch($data);
    }
}