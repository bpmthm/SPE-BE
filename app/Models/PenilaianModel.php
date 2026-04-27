<?php

namespace App\Models;

use CodeIgniter\Model;

class PenilaianModel extends Model
{
    protected $table            = 't_penilaian';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'supplier_id', 'periode',
        'qc_ng_percent', 'qc_score',
        'ppic_ot_percent', 'ppic_score',
        'pch_harga', 'pch_moq', 'pch_top', 'pch_pelayanan', 'pch_score',
        'hse_uji_emisi', 'hse_apd', 'hse_score',
        'total_score', 'grade', 'status_final'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [
        'supplier_id' => 'required|numeric',
        'periode' => 'required|regex_match[/^\d{4}-\d{2}$/]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['calculateScores'];
    protected $afterInsert    = [];
    protected $beforeUpdate   = ['calculateScores'];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    /**
     * Callback: Calculate scores berdasarkan input
     */
    protected function calculateScores(array $data)
    {
        // Calculate QC Score
        if (isset($data['data']['qc_ng_percent'])) {
            $ngPercent = (float)$data['data']['qc_ng_percent'];
            $data['data']['qc_score'] = $this->calculateQCScore($ngPercent);
        }

        // Calculate PPIC Score
        if (isset($data['data']['ppic_ot_percent'])) {
            $otPercent = (float)$data['data']['ppic_ot_percent'];
            $data['data']['ppic_score'] = $this->calculatePPICScore($otPercent);
        }

        // Calculate Purchasing Score
        if (isset($data['data']['pch_harga'])) {
            $data['data']['pch_score'] = $this->calculatePCHScore(
                $data['data']['pch_harga'] ?? null,
                $data['data']['pch_moq'] ?? null,
                $data['data']['pch_top'] ?? null,
                $data['data']['pch_pelayanan'] ?? null
            );
        }

        // Calculate HSE Score
        if (isset($data['data']['hse_uji_emisi'])) {
            $data['data']['hse_score'] = $this->calculateHSEScore(
                $data['data']['hse_uji_emisi'] ?? null,
                $data['data']['hse_apd'] ?? null
            );
        }

        // Calculate Total Score & Grade
        $this->calculateTotalScoreAndGrade($data['data']);

        return $data;
    }

    /**
     * Convert QC NG% to score (0% NG = 30 pts, higher NG = lower score)
     */
    private function calculateQCScore($ngPercent): int
    {
        if ($ngPercent <= 0.5) return 30;
        if ($ngPercent <= 1.0) return 25;
        if ($ngPercent <= 2.0) return 20;
        if ($ngPercent <= 5.0) return 15;
        return 10;
    }

    /**
     * Convert PPIC On-Time% to score (higher OT = higher score)
     */
    private function calculatePPICScore($otPercent): int
    {
        if ($otPercent >= 95) return 30;
        if ($otPercent >= 90) return 25;
        if ($otPercent >= 85) return 20;
        if ($otPercent >= 80) return 15;
        return 10;
    }

    /**
     * Calculate Purchasing Score (Harga, MOQ, TOP, Pelayanan)
     * BAIK = 3, CUKUP = 2, KURANG = 1
     */
    private function calculatePCHScore($harga, $moq, $top, $pelayanan): int
    {
        $score = 0;
        $score += ($harga === 'BAIK' ? 3 : ($harga === 'CUKUP' ? 2 : 1)) * 2;
        $score += ($moq === 'BAIK' ? 3 : ($moq === 'CUKUP' ? 2 : 1)) * 2;
        $score += ($top === 'BAIK' ? 3 : ($top === 'CUKUP' ? 2 : 1)) * 2;
        $score += ($pelayanan === 'BAIK' ? 3 : ($pelayanan === 'CUKUP' ? 2 : 1)) * 2;
        
        // Max 40 pts
        return min($score, 40);
    }

    /**
     * Calculate HSE Score (Uji Emisi, APD)
     */
    private function calculateHSEScore($ujiEmisi, $apd): int
    {
        $score = 0;
        $score += ($ujiEmisi === 'BAIK' ? 3 : ($ujiEmisi === 'CUKUP' ? 2 : 1)) * 5;
        $score += ($apd === 'BAIK' ? 3 : ($apd === 'CUKUP' ? 2 : 1)) * 5;
        
        // Max 30 pts
        return min($score, 30);
    }

    /**
     * Calculate total score & assign grade
     * Max: 30 (QC) + 30 (PPIC) + 40 (PCH) + 30 (HSE) = 130
     */
    private function calculateTotalScoreAndGrade(&$data): void
    {
        $total = 0;
        $total += $data['qc_score'] ?? 0;
        $total += $data['ppic_score'] ?? 0;
        $total += $data['pch_score'] ?? 0;
        $total += $data['hse_score'] ?? 0;

        $data['total_score'] = $total;

        // Assign grade (A = 100-130, B = 70-99, C = <70)
        if ($total >= 100) {
            $data['grade'] = 'A';
        } elseif ($total >= 70) {
            $data['grade'] = 'B';
        } else {
            $data['grade'] = 'C';
        }
    }
}
