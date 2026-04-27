<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInitialTables extends Migration
{
    public function up()
    {
        // 1. Tabel Supplier (Master) - Tetap aman
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'kode_vendor' => ['type' => 'VARCHAR', 'constraint' => 20],
            'nama_vendor' => ['type' => 'VARCHAR', 'constraint' => 100],
            'jenis_bahan' => ['type' => 'VARCHAR', 'constraint' => 50],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('m_supplier', true);

        // 2. Tabel Penilaian (Transaksi) - Gue update detailnya
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'supplier_id'  => ['type' => 'INT', 'unsigned' => true],
            'periode'      => ['type' => 'VARCHAR', 'constraint' => 7], // Format YYYY-MM
            
            // --- QC (Quality Control) ---
            'qc_ng_percent' => ['type' => 'FLOAT', 'null' => true], // Input % NG
            'qc_score'      => ['type' => 'INT', 'null' => true],   // Hasil konversi poin

            // --- PPIC ---
            'ppic_ot_percent' => ['type' => 'FLOAT', 'null' => true], // Input % On-Time
            'ppic_score'      => ['type' => 'INT', 'null' => true],   // Hasil konversi poin

            // --- Purchasing (Harga & Pelayanan) ---
            // Kita pake ENUM biar di FE lo tinggal bikin dropdown/radio (BAIK=3, CUKUP=2, KURANG=1)
            'pch_harga'     => ['type' => 'ENUM', 'constraint' => ['BAIK', 'CUKUP', 'KURANG'], 'null' => true],
            'pch_moq'       => ['type' => 'ENUM', 'constraint' => ['BAIK', 'CUKUP', 'KURANG'], 'null' => true],
            'pch_top'       => ['type' => 'ENUM', 'constraint' => ['BAIK', 'CUKUP', 'KURANG'], 'null' => true],
            'pch_pelayanan' => ['type' => 'ENUM', 'constraint' => ['BAIK', 'CUKUP', 'KURANG'], 'null' => true],
            'pch_score'     => ['type' => 'INT', 'null' => true],

            // --- HSE (K3) ---
            'hse_uji_emisi' => ['type' => 'ENUM', 'constraint' => ['BAIK', 'CUKUP', 'KURANG'], 'null' => true],
            'hse_apd'       => ['type' => 'ENUM', 'constraint' => ['BAIK', 'CUKUP', 'KURANG'], 'null' => true],
            'hse_score'     => ['type' => 'INT', 'null' => true],

            // --- Summary ---
            'total_score'   => ['type' => 'FLOAT', 'null' => true],
            'grade'         => ['type' => 'VARCHAR', 'constraint' => 2, 'null' => true],
            'status_final'  => ['type' => 'ENUM', 'constraint' => ['DRAFT', 'SUBMITTED'], 'default' => 'DRAFT'],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // Tambahin Unique Index biar 1 supplier cuma punya 1 baris nilai per bulan
        $this->forge->addUniqueKey(['supplier_id', 'periode']); 
        $this->forge->createTable('t_penilaian', true);
    }

    public function down()
    {
        $this->forge->dropTable('t_penilaian', true);
        $this->forge->dropTable('m_supplier', true);
    }
}