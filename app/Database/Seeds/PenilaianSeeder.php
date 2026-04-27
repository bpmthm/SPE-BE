<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class PenilaianSeeder extends Seeder
{
    public function run()
    {
        $data = [];
        
        // Generate 50 penilaian data (42 suppliers × multiple bulan)
        $suppliers = range(1, 42);
        $periodes = ['2026-01', '2026-02', '2026-03', '2026-04'];
        
        $grades = ['A', 'B', 'C'];
        $evaluations = ['BAIK', 'CUKUP', 'KURANG'];
        
        foreach ($suppliers as $supplierId) {
            foreach ($periodes as $periode) {
                // Random QC NG%
                $qcNG = mt_rand(0, 50) / 100; // 0.00 - 0.50%
                
                // Random PPIC On-Time%
                $ppicOT = mt_rand(80, 98); // 80% - 98%
                
                // Random Purchasing evaluations
                $pchHarga = $evaluations[array_rand($evaluations)];
                $pchMoq = $evaluations[array_rand($evaluations)];
                $pchTop = $evaluations[array_rand($evaluations)];
                $pchPelayanan = $evaluations[array_rand($evaluations)];
                
                // Random HSE evaluations
                $hseUjiEmisi = $evaluations[array_rand($evaluations)];
                $hseApd = $evaluations[array_rand($evaluations)];
                
                // Calculate scores (based on model logic)
                $qcScore = $this->calculateQCScore($qcNG);
                $ppicScore = $this->calculatePPICScore($ppicOT);
                $pchScore = $this->calculatePCHScore($pchHarga, $pchMoq, $pchTop, $pchPelayanan);
                $hseScore = $this->calculateHSEScore($hseUjiEmisi, $hseApd);
                
                $totalScore = $qcScore + $ppicScore + $pchScore + $hseScore;
                $grade = $this->assignGrade($totalScore);
                
                $data[] = [
                    'supplier_id' => $supplierId,
                    'periode' => $periode,
                    'qc_ng_percent' => $qcNG,
                    'qc_score' => $qcScore,
                    'ppic_ot_percent' => $ppicOT,
                    'ppic_score' => $ppicScore,
                    'pch_harga' => $pchHarga,
                    'pch_moq' => $pchMoq,
                    'pch_top' => $pchTop,
                    'pch_pelayanan' => $pchPelayanan,
                    'pch_score' => $pchScore,
                    'hse_uji_emisi' => $hseUjiEmisi,
                    'hse_apd' => $hseApd,
                    'hse_score' => $hseScore,
                    'total_score' => $totalScore,
                    'grade' => $grade,
                    'status_final' => 'SUBMITTED',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }
        }
        
        $this->db->table('t_penilaian')->insertBatch($data);
    }
    
    /**
     * Convert QC NG% to score
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
     * Convert PPIC On-Time% to score
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
     * Calculate Purchasing Score
     */
    private function calculatePCHScore($harga, $moq, $top, $pelayanan): int
    {
        $score = 0;
        $score += ($harga === 'BAIK' ? 3 : ($harga === 'CUKUP' ? 2 : 1)) * 2;
        $score += ($moq === 'BAIK' ? 3 : ($moq === 'CUKUP' ? 2 : 1)) * 2;
        $score += ($top === 'BAIK' ? 3 : ($top === 'CUKUP' ? 2 : 1)) * 2;
        $score += ($pelayanan === 'BAIK' ? 3 : ($pelayanan === 'CUKUP' ? 2 : 1)) * 2;
        return min($score, 40);
    }
    
    /**
     * Calculate HSE Score
     */
    private function calculateHSEScore($ujiEmisi, $apd): int
    {
        $score = 0;
        $score += ($ujiEmisi === 'BAIK' ? 3 : ($ujiEmisi === 'CUKUP' ? 2 : 1)) * 5;
        $score += ($apd === 'BAIK' ? 3 : ($apd === 'CUKUP' ? 2 : 1)) * 5;
        return min($score, 30);
    }
    
    /**
     * Assign grade based on total score
     */
    private function assignGrade($totalScore): string
    {
        if ($totalScore >= 100) return 'A';
        if ($totalScore >= 70) return 'B';
        return 'C';
    }
}
