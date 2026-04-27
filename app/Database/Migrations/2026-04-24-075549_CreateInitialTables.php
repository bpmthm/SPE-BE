<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInitialTables extends Migration
{
    public function up()
    {
        // Tabel Supplier (Master)
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'kode_vendor' => ['type' => 'VARCHAR', 'constraint' => 20],
            'nama_vendor' => ['type' => 'VARCHAR', 'constraint' => 100],
            'jenis_bahan' => ['type' => 'VARCHAR', 'constraint' => 50],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('m_supplier');

        // Tabel Penilaian (Transaksi)
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'supplier_id'  => ['type' => 'INT', 'unsigned' => true],
            'periode'      => ['type' => 'VARCHAR', 'constraint' => 7], // format YYYY-MM
            'qc_ng'        => ['type' => 'FLOAT', 'null' => true],
            'ppic_ot'      => ['type' => 'FLOAT', 'null' => true],
            'pch_price'    => ['type' => 'INT', 'null' => true],
            'hse_score'    => ['type' => 'INT', 'null' => true],
            'total_score'  => ['type' => 'FLOAT', 'null' => true],
            'grade'        => ['type' => 'VARCHAR', 'constraint' => 2, 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('t_penilaian');
    }

    public function down()
    {
        // Biar bisa dihapus kalau mau ngulang (Rollback)
        $this->forge->dropTable('t_penilaian', true);
        $this->forge->dropTable('m_supplier', true);
    }
}