<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTopDaysAndIsActiveToSupplier extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        
        // Add top_days if it doesn't exist
        if (!$db->fieldExists('top_days', 'm_supplier')) {
            $this->forge->addColumn('m_supplier', [
                'top_days' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
                    'null'       => false,
                    'after'      => 'jenis_bahan'
                ]
            ]);
        }

        // Add is_active if it doesn't exist
        if (!$db->fieldExists('is_active', 'm_supplier')) {
            $this->forge->addColumn('m_supplier', [
                'is_active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                    'null'       => false,
                    'after'      => 'top_days'
                ]
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('m_supplier', 'top_days');
        $this->forge->dropColumn('m_supplier', 'is_active');
    }
}
