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
        $existing = [];
        if (isset($data['id'])) {
            $id = is_array($data['id']) ? $data['id'][0] : $data['id'];
            $existing = $this->db->table($this->table)->where('id', $id)->get()->getRowArray();
        }

        // --- QC ---
        if (array_key_exists('qc_ng_percent', $data['data'])) {
            $ng = $data['data']['qc_ng_percent'];
            $data['data']['qc_score'] = ($ng !== null) ? $this->getQCScore((float)$ng) : 0;
        }
        $qc = $data['data']['qc_score'] ?? ($existing['qc_score'] ?? 0);

        // --- PPIC ---
        if (array_key_exists('ppic_ot_percent', $data['data'])) {
            $ot = $data['data']['ppic_ot_percent'];
            $data['data']['ppic_score'] = ($ot !== null) ? $this->getPPICScore((float)$ot) : 0;
        }
        $ppic = $data['data']['ppic_score'] ?? ($existing['ppic_score'] ?? 0);

        // --- PCH ---
        if (isset($data['data']['pch_harga']) || isset($data['data']['pch_moq']) || isset($data['data']['pch_pelayanan'])) {
            $pchData = [
                'pch_harga'     => $data['data']['pch_harga'] ?? ($existing['pch_harga'] ?? null),
                'pch_moq'       => $data['data']['pch_moq'] ?? ($existing['pch_moq'] ?? null),
                'pch_pelayanan' => $data['data']['pch_pelayanan'] ?? ($existing['pch_pelayanan'] ?? null),
                'pch_top'       => $existing['pch_top'] ?? 'BAIK' 
            ];
            $data['data']['pch_score'] = $this->getPCHScore($pchData);
        }
        $pch = $data['data']['pch_score'] ?? ($existing['pch_score'] ?? 0);

        // --- HSE ---
        if (isset($data['data']['hse_uji_emisi']) || isset($data['data']['hse_apd'])) {
            $hseData = [
                'hse_uji_emisi' => $data['data']['hse_uji_emisi'] ?? ($existing['hse_uji_emisi'] ?? null),
                'hse_apd'       => $data['data']['hse_apd'] ?? ($existing['hse_apd'] ?? null),
            ];
            $data['data']['hse_score'] = $this->getHSEScore($hseData);
        }
        $hse = $data['data']['hse_score'] ?? ($existing['hse_score'] ?? 0);

        // --- TOTAL & GRADE ---
        $total = $qc + $ppic + $pch + $hse;
        $data['data']['total_score'] = $total;

        if ($total >= 90) $data['data']['grade'] = 'A';
        elseif ($total >= 70) $data['data']['grade'] = 'B';
        else $data['data']['grade'] = 'C';

        return $data;
    }

    private function getQCScore($ng) { return $ng < 0.5 ? 30 : ($ng < 1.0 ? 15 : 10); }
    private function getPPICScore($ot) { return $ot >= 90 ? 30 : ($ot >= 71 ? 15 : 10); }

    private function getPCHScore($d) {
        $s = 0;
        if (($d['pch_harga'] ?? '') === 'BAIK') $s += 10; elseif (($d['pch_harga'] ?? '') === 'CUKUP') $s += 5; elseif (($d['pch_harga'] ?? '') === 'KURANG') $s += 3;
        if (($d['pch_moq'] ?? '') === 'BAIK') $s += 10; elseif (($d['pch_moq'] ?? '') === 'CUKUP') $s += 5; elseif (($d['pch_moq'] ?? '') === 'KURANG') $s += 3;
        if (($d['pch_top'] ?? '') === 'BAIK') $s += 5; elseif (($d['pch_top'] ?? '') === 'CUKUP') $s += 3; elseif (($d['pch_top'] ?? '') === 'KURANG') $s += 1;
        if (($d['pch_pelayanan'] ?? '') === 'BAIK') $s += 5; elseif (($d['pch_pelayanan'] ?? '') === 'CUKUP') $s += 3; elseif (($d['pch_pelayanan'] ?? '') === 'KURANG') $s += 1;
        return $s;
    }

    private function getHSEScore($d) {
        $s = 0;
        if (($d['hse_uji_emisi'] ?? '') === 'BAIK') $s += 5; elseif (($d['hse_uji_emisi'] ?? '') === 'CUKUP') $s += 3; elseif (($d['hse_uji_emisi'] ?? '') === 'KURANG') $s += 1;
        if (($d['hse_apd'] ?? '') === 'BAIK') $s += 5; elseif (($d['hse_apd'] ?? '') === 'CUKUP') $s += 3; elseif (($d['hse_apd'] ?? '') === 'KURANG') $s += 1;
        return $s;
    }
}