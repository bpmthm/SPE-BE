<?php

namespace App\Models;

use CodeIgniter\Model;

class PenilaianModel extends Model
{
    protected $table            = 't_penilaian';
    protected $primaryKey       = 'id';
    protected $allowedFields    = [
        'supplier_id', 'periode',
        'qc_qty_terima', 'qc_qty_reject',
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
        $supplierId = $data['data']['supplier_id'] ?? null;
        $periode = $data['data']['periode'] ?? null;
        if ($supplierId && $periode) {
            $existing = $this->db->table($this->table)
                ->where('supplier_id', $supplierId)
                ->where('periode', $periode)
                ->get()->getRowArray();
        }

        // --- QC ---
        $terima = isset($data['data']['qc_qty_terima']) ? $data['data']['qc_qty_terima'] : ($existing['qc_qty_terima'] ?? null);
        $reject = isset($data['data']['qc_qty_reject']) ? $data['data']['qc_qty_reject'] : ($existing['qc_qty_reject'] ?? null);

        if ($terima !== null || $reject !== null) {
            $terimaVal = (int)($terima ?? 0);
            $rejectVal = (int)($reject ?? 0);
            $totalQty = $terimaVal + $rejectVal;
            $ngPercent = 0.0;
            if ($totalQty > 0) {
                $ngPercent = ($rejectVal / $totalQty) * 100;
            }
            $data['data']['qc_qty_terima'] = $terimaVal;
            $data['data']['qc_qty_reject'] = $rejectVal;
            $data['data']['qc_ng_percent'] = $ngPercent;
            $data['data']['qc_score'] = $this->getQCScore($ngPercent);
        } else {
            $data['data']['qc_ng_percent'] = null;
            $data['data']['qc_score'] = null;
        }
        $qc = $data['data']['qc_score'] ?? ($existing['qc_score'] ?? null);

        // --- PPIC ---
        $ot = isset($data['data']['ppic_ot_percent']) ? $data['data']['ppic_ot_percent'] : ($existing['ppic_ot_percent'] ?? null);
        if ($ot !== null) {
            $data['data']['ppic_ot_percent'] = (float)$ot;
            $data['data']['ppic_score'] = $this->getPPICScore((float)$ot);
        } else {
            $data['data']['ppic_ot_percent'] = null;
            $data['data']['ppic_score'] = null;
        }
        $ppic = $data['data']['ppic_score'] ?? ($existing['ppic_score'] ?? null);

        // --- PCH ---
        $pchHarga = isset($data['data']['pch_harga']) ? $data['data']['pch_harga'] : ($existing['pch_harga'] ?? null);
        $pchMoq = isset($data['data']['pch_moq']) ? $data['data']['pch_moq'] : ($existing['pch_moq'] ?? null);
        $pchTop = isset($data['data']['pch_top']) ? $data['data']['pch_top'] : ($existing['pch_top'] ?? null);
        $pchPelayanan = isset($data['data']['pch_pelayanan']) ? $data['data']['pch_pelayanan'] : ($existing['pch_pelayanan'] ?? null);

        if ($pchHarga !== null || $pchMoq !== null || $pchTop !== null || $pchPelayanan !== null) {
            $pchData = [
                'pch_harga'     => $pchHarga,
                'pch_moq'       => $pchMoq,
                'pch_top'       => $pchTop,
                'pch_pelayanan' => $pchPelayanan
            ];
            $data['data']['pch_score'] = $this->getPCHScore($pchData);
        } else {
            $data['data']['pch_score'] = null;
        }
        $pch = $data['data']['pch_score'] ?? ($existing['pch_score'] ?? null);

        // --- HSE ---
        $hseUjiEmisi = isset($data['data']['hse_uji_emisi']) ? $data['data']['hse_uji_emisi'] : ($existing['hse_uji_emisi'] ?? null);
        $hseApd = isset($data['data']['hse_apd']) ? $data['data']['hse_apd'] : ($existing['hse_apd'] ?? null);

        if ($hseUjiEmisi !== null || $hseApd !== null) {
            $hseData = [
                'hse_uji_emisi' => $hseUjiEmisi,
                'hse_apd'       => $hseApd
            ];
            $data['data']['hse_score'] = $this->getHSEScore($hseData);
        } else {
            $data['data']['hse_score'] = null;
        }
        $hse = $data['data']['hse_score'] ?? ($existing['hse_score'] ?? null);

        // --- TOTAL & GRADE ---
        $total = (int)($qc ?? 0) + (int)($ppic ?? 0) + (int)($pch ?? 0) + (int)($hse ?? 0);
        $data['data']['total_score'] = $total;
        
        if ($total >= 90) $data['data']['grade'] = 'A'; // BAIK
        elseif ($total >= 70) $data['data']['grade'] = 'B'; // CUKUP
        else $data['data']['grade'] = 'C'; // KURANG

        return $data;
    }

    private function getQCScore($ng) { 
        if ($ng < 0.5) return 30;
        elseif ($ng >= 0.5 && $ng < 1) return 15;
        elseif ($ng >= 1) return 10;
        return 0;
    }
    
    private function getPPICScore($ot) { 
        if ($ot >= 90) return 30;
        elseif ($ot >= 71) return 15;
        return 10;
    }

    private function getPCHScore($d) {
        $s = 0;
        // HARGA (Maks 10)
        if (($d['pch_harga'] ?? '') === 'BAIK') $s += 10; 
        elseif (($d['pch_harga'] ?? '') === 'CUKUP') $s += 5; 
        elseif (($d['pch_harga'] ?? '') === 'KURANG') $s += 3;
        
        // MOQ (Maks 10)
        if (($d['pch_moq'] ?? '') === 'BAIK') $s += 10; 
        elseif (($d['pch_moq'] ?? '') === 'CUKUP') $s += 5; 
        elseif (($d['pch_moq'] ?? '') === 'KURANG') $s += 3;
        
        // TOP (Maks 5)
        if (($d['pch_top'] ?? '') === 'BAIK') $s += 5; 
        elseif (($d['pch_top'] ?? '') === 'CUKUP') $s += 3; 
        elseif (($d['pch_top'] ?? '') === 'KURANG') $s += 1;
        
        // PELAYANAN (Maks 5)
        if (($d['pch_pelayanan'] ?? '') === 'BAIK') $s += 5; 
        elseif (($d['pch_pelayanan'] ?? '') === 'CUKUP') $s += 3; 
        elseif (($d['pch_pelayanan'] ?? '') === 'KURANG') $s += 1;
        
        return $s;
    }

    private function getHSEScore($d) {
        $s = 0;
        if (($d['hse_uji_emisi'] ?? '') === 'BAIK') $s += 5; 
        elseif (($d['hse_uji_emisi'] ?? '') === 'CUKUP') $s += 3; 
        elseif (($d['hse_uji_emisi'] ?? '') === 'KURANG') $s += 1;
        
        if (($d['hse_apd'] ?? '') === 'BAIK') $s += 5; 
        elseif (($d['hse_apd'] ?? '') === 'CUKUP') $s += 3; 
        elseif (($d['hse_apd'] ?? '') === 'KURANG') $s += 1;
        
        return $s;
    }

    /**
     * Ambil data aktual bulan ke bulan untuk satu vendor dalam rentang periode.
     *
     * @param string $kodeVendor  Kode vendor (dari kolom s.kode_vendor)
     * @param string $periodeAwal Format YYYY-MM (inklusif)
     * @param string $periodeAkhir Format YYYY-MM (inklusif)
     * @return array [
     *   'data_aktual' => array of monthly rows,
     *   'rata_rata'   => object with AVG values,
     *   'nama_vendor' => string,
     *   'jenis_bahan' => string,
     * ]
     */
    public function getDetailEvaluasi(string $kodeVendor, string $periodeAwal, string $periodeAkhir): array
    {
        $db = \Config\Database::connect();

        // ── 1. Data aktual per bulan ──────────────────────────────────────────
        $dataAktual = $db->table('t_penilaian p')
            ->select([
                'p.periode',
                'p.qc_score', 'p.qc_ng_percent', 'p.qc_qty_terima', 'p.qc_qty_reject',
                'p.ppic_score', 'p.ppic_ot_percent',
                'p.pch_score', 'p.pch_harga', 'p.pch_moq', 'p.pch_top', 'p.pch_pelayanan',
                'p.hse_score', 'p.hse_uji_emisi', 'p.hse_apd',
                'p.total_score', 'p.grade',
                's.nama_vendor', 's.kode_vendor', 's.jenis_bahan',
            ])
            ->join('m_supplier s', 's.id = p.supplier_id', 'inner')
            ->where('s.kode_vendor', $kodeVendor)
            ->where('p.periode >=', $periodeAwal)
            ->where('p.periode <=', $periodeAkhir)
            ->orderBy('p.periode', 'ASC')
            ->get()
            ->getResultArray();

        // ── 2. Rata-rata agregat (AVG) via DB ────────────────────────────────
        $avgRow = $db->table('t_penilaian p')
            ->select([
                'AVG(p.qc_score)       AS avg_qc_score',
                'AVG(p.qc_ng_percent)  AS avg_qc_ng_percent',
                'SUM(p.qc_qty_terima)  AS sum_qc_qty_terima',
                'SUM(p.qc_qty_reject)  AS sum_qc_qty_reject',
                'AVG(p.ppic_score)     AS avg_ppic_score',
                'AVG(p.ppic_ot_percent)AS avg_ppic_ot_percent',
                'AVG(p.pch_score)      AS avg_pch_score',
                'AVG(p.hse_score)      AS avg_hse_score',
                'AVG(p.total_score)    AS avg_total_score',
                'COUNT(p.id)           AS jumlah_bulan',
            ], false)
            ->join('m_supplier s', 's.id = p.supplier_id', 'inner')
            ->where('s.kode_vendor', $kodeVendor)
            ->where('p.periode >=', $periodeAwal)
            ->where('p.periode <=', $periodeAkhir)
            ->get()
            ->getRowArray();

        // Tentukan avg_grade berdasarkan avg_total_score
        $avgTotal = (float)($avgRow['avg_total_score'] ?? 0);
        $avgGrade = $avgTotal >= 90 ? 'A' : ($avgTotal >= 70 ? 'B' : 'C');

        // Ambil info vendor dari baris pertama (jika ada data)
        $namaVendor  = $dataAktual[0]['nama_vendor']  ?? '-';
        $jenisBahan  = $dataAktual[0]['jenis_bahan']  ?? '-';

        return [
            'nama_vendor'  => $namaVendor,
            'jenis_bahan'  => $jenisBahan,
            'data_aktual'  => $dataAktual,
            'rata_rata'    => array_merge($avgRow ?? [], ['avg_grade' => $avgGrade]),
        ];
    }
}