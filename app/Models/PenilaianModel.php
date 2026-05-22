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
        if (isset($data['id'])) {
            $id = is_array($data['id']) ? $data['id'][0] : $data['id'];
            $existing = $this->db->table($this->table)->where('id', $id)->get()->getRowArray();
        }

        // --- QC ---
        if (isset($data['data']['qc_qty_terima']) || isset($data['data']['qc_qty_reject'])) {
            $terima = (int)($data['data']['qc_qty_terima'] ?? ($existing['qc_qty_terima'] ?? 0));
            $reject = (int)($data['data']['qc_qty_reject'] ?? ($existing['qc_qty_reject'] ?? 0));
            $totalQty = $terima + $reject;
            
            if ($totalQty > 0) {
                $data['data']['qc_ng_percent'] = ($reject / $totalQty) * 100;
            } else {
                $data['data']['qc_ng_percent'] = 0;
            }
        }

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

        // --- PCH (UDAH DISESUAIKAN SAMA EXCEL) ---
        if (isset($data['data']['pch_harga']) || isset($data['data']['pch_moq']) || isset($data['data']['pch_top']) || isset($data['data']['pch_pelayanan'])) {
            $pchData = [
                'pch_harga'     => $data['data']['pch_harga'] ?? ($existing['pch_harga'] ?? null),
                'pch_moq'       => $data['data']['pch_moq'] ?? ($existing['pch_moq'] ?? null),
                'pch_top'       => $data['data']['pch_top'] ?? ($existing['pch_top'] ?? null),
                'pch_pelayanan' => $data['data']['pch_pelayanan'] ?? ($existing['pch_pelayanan'] ?? null),
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