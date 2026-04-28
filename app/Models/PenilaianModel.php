<?php

namespace App\Models;

use CodeIgniter\Model;

class PenilaianModel extends Model
{
    protected $table            = 't_penilaian';
    protected $primaryKey       = 'id';
    protected $allowedFields    = [
        'supplier_id', 'periode',
        'qc_ng_percent', 'qc_score',
        'ppic_ot_percent', 'ppic_score',
        'pch_harga', 'pch_moq', 'pch_top', 'pch_pelayanan', 'pch_score',
        'hse_uji_emisi', 'hse_apd', 'hse_score',
        'total_score', 'grade', 'status_final'
    ];

    protected $useTimestamps = true;
    protected $beforeInsert   = ['calculateScores'];
    protected $beforeUpdate   = ['calculateScores'];

    protected function calculateScores(array $data)
    {
        // 1. Ambil data lama
        $existing = [];
        if (isset($data['id'])) {
            $id = is_array($data['id']) ? $data['id'][0] : $data['id'];
            // FIX: Pake Query Builder biar CI4 nggak error (hindari $this->find)
            $existing = $this->db->table($this->table)->where('id', $id)->get()->getRowArray();
        }

        // 2. Hitung QC
        if (array_key_exists('qc_ng_percent', $data['data'])) {
            $ng = $data['data']['qc_ng_percent'];
            $data['data']['qc_score'] = ($ng !== null) ? $this->getQCScore((float)$ng) : 0;
        }
        $qc = $data['data']['qc_score'] ?? ($existing['qc_score'] ?? 0);

        // 3. Hitung PPIC
        if (array_key_exists('ppic_ot_percent', $data['data'])) {
            $ot = $data['data']['ppic_ot_percent'];
            $data['data']['ppic_score'] = ($ot !== null) ? $this->getPPICScore((float)$ot) : 0;
        }
        $ppic = $data['data']['ppic_score'] ?? ($existing['ppic_score'] ?? 0);

        // 4. Hitung PCH (Purchasing)
        if (isset($data['data']['pch_harga']) || isset($data['data']['pch_moq']) || isset($data['data']['pch_pelayanan'])) {
            $pchData = [
                'pch_harga'     => $data['data']['pch_harga'] ?? ($existing['pch_harga'] ?? null),
                'pch_moq'       => $data['data']['pch_moq'] ?? ($existing['pch_moq'] ?? null),
                'pch_pelayanan' => $data['data']['pch_pelayanan'] ?? ($existing['pch_pelayanan'] ?? null),
                'pch_top'       => $existing['pch_top'] ?? 'BAIK' // Default 5 poin (BAIK) karena nunggu integrasi SAP
            ];
            $data['data']['pch_score'] = $this->getPCHScore($pchData);
        }
        $pch = $data['data']['pch_score'] ?? ($existing['pch_score'] ?? 0);

        // 5. Hitung HSE
        if (isset($data['data']['hse_uji_emisi']) || isset($data['data']['hse_apd'])) {
            $hseData = [
                'hse_uji_emisi' => $data['data']['hse_uji_emisi'] ?? ($existing['hse_uji_emisi'] ?? null),
                'hse_apd'       => $data['data']['hse_apd'] ?? ($existing['hse_apd'] ?? null),
            ];
            $data['data']['hse_score'] = $this->getHSEScore($hseData);
        }
        $hse = $data['data']['hse_score'] ?? ($existing['hse_score'] ?? 0);

        // 6. Update Total & Grade
        $total = $qc + $ppic + $pch + $hse;
        $data['data']['total_score'] = $total;

        // Threshold dari Excel: >= 90 BAIK(A), 70-89 CUKUP(B), < 70 KURANG(C)
        if ($total >= 90) $data['data']['grade'] = 'A';
        elseif ($total >= 70) $data['data']['grade'] = 'B';
        else $data['data']['grade'] = 'C';

        return $data;
    }

    // --- HELPER SCORING PERSIS EXCEL ---
    private function getQCScore($ng) {
        if ($ng < 0.5) return 30;       // < 0.5% -> 30
        if ($ng < 1.0) return 15;       // 0.5% - <1% -> 15
        return 10;                      // >= 1% -> 10
    }

    private function getPPICScore($ot) {
        if ($ot >= 90) return 30;       // >= 90% -> 30
        if ($ot >= 71) return 15;       // 71% - 89% -> 15
        return 10;                      // <= 70% -> 10
    }

    private function getPCHScore($d) {
        $score = 0;
        // Harga (Maks 10)
        if (($d['pch_harga'] ?? '') === 'BAIK') $score += 10;
        elseif (($d['pch_harga'] ?? '') === 'CUKUP') $score += 5;
        elseif (($d['pch_harga'] ?? '') === 'KURANG') $score += 3;

        // MOQ (Maks 10)
        if (($d['pch_moq'] ?? '') === 'BAIK') $score += 10;
        elseif (($d['pch_moq'] ?? '') === 'CUKUP') $score += 5;
        elseif (($d['pch_moq'] ?? '') === 'KURANG') $score += 3;

        // TOP (Maks 5)
        if (($d['pch_top'] ?? '') === 'BAIK') $score += 5;
        elseif (($d['pch_top'] ?? '') === 'CUKUP') $score += 3;
        elseif (($d['pch_top'] ?? '') === 'KURANG') $score += 1;

        // Pelayanan (Maks 5)
        if (($d['pch_pelayanan'] ?? '') === 'BAIK') $score += 5;
        elseif (($d['pch_pelayanan'] ?? '') === 'CUKUP') $score += 3;
        elseif (($d['pch_pelayanan'] ?? '') === 'KURANG') $score += 1;

        return $score;
    }

    private function getHSEScore($d) {
        $score = 0;
        // Uji Emisi (Maks 5)
        if (($d['hse_uji_emisi'] ?? '') === 'BAIK') $score += 5;
        elseif (($d['hse_uji_emisi'] ?? '') === 'CUKUP') $score += 3;
        elseif (($d['hse_uji_emisi'] ?? '') === 'KURANG') $score += 1;

        // APD (Maks 5)
        if (($d['hse_apd'] ?? '') === 'BAIK') $score += 5;
        elseif (($d['hse_apd'] ?? '') === 'CUKUP') $score += 3;
        elseif (($d['hse_apd'] ?? '') === 'KURANG') $score += 1;

        return $score;
    }
}