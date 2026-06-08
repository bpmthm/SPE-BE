<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\PenilaianModel;
use App\Models\SupplierModel;

class Penilaian extends ResourceController
{
    protected $modelName = 'App\Models\PenilaianModel';
    protected $format    = 'json';

    public function index()
    {
        $supplier_id = $this->request->getGet('supplier_id');
        $periode     = $this->request->getGet('periode');

        $query = $this->model;
        if ($supplier_id) $query = $query->where('supplier_id', $supplier_id);
        if ($periode)     $query = $query->where('periode', $periode);

        return $this->respond($query->findAll());
    }

    public function upsert()
    {
        $user = $this->request->user ?? null;
        if (!$user) {
            return $this->failUnauthorized('Akses ditolak: Token tidak valid!');
        }
        $role = strtoupper($user->role ?? 'GUEST');

        $json = $this->request->getJSON(true);
        if (!$json || empty($json['supplier_id']) || empty($json['periode'])) {
            return $this->fail('Data supplier/periode kosong Pi!', 400);
        }

        // Selalu buang fields kalkulasi/skor/grade agar client tidak bisa memanipulasi
        $stripFields = ['qc_ng_percent', 'qc_score', 'ppic_score', 'pch_score', 'hse_score', 'total_score', 'grade'];
        foreach ($stripFields as $field) {
            unset($json[$field]);
        }

        // Tentukan field yang diizinkan berdasarkan role
        $allowedFields = ['supplier_id', 'periode', 'status_final'];

        if ($role === 'QC') {
            $allowedFields = array_merge($allowedFields, ['qc_qty_terima', 'qc_qty_reject']);
        } elseif ($role === 'PPIC') {
            $allowedFields = array_merge($allowedFields, ['ppic_ot_percent']);
        } elseif ($role === 'PCH') {
            $allowedFields = array_merge($allowedFields, ['pch_harga', 'pch_moq', 'pch_top', 'pch_pelayanan']);
        } elseif ($role === 'HSE') {
            $allowedFields = array_merge($allowedFields, ['hse_uji_emisi', 'hse_apd']);
        } elseif ($role === 'ADMIN' || $role === 'MANAGER') {
            // Admin/Manager boleh isi semua kolom raw
            $allowedFields = array_merge($allowedFields, [
                'qc_qty_terima', 'qc_qty_reject',
                'ppic_ot_percent',
                'pch_harga', 'pch_moq', 'pch_top', 'pch_pelayanan',
                'hse_uji_emisi', 'hse_apd'
            ]);
        } else {
            return $this->failForbidden('Akses ditolak: Role Anda tidak memiliki izin untuk menginput data.');
        }

        // Filter payload hanya menyisakan field yang diizinkan
        $filteredJson = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $json)) {
                $filteredJson[$field] = $json[$field];
            }
        }

        $existing = $this->model->where('supplier_id', $filteredJson['supplier_id'])->where('periode', $filteredJson['periode'])->first();

        try {
            if ($existing) {
                $this->model->update($existing['id'], $filteredJson);
                $id = $existing['id'];
            } else {
                $id = $this->model->insert($filteredJson);
            }
            return $this->respond(['status' => 'success', 'message' => 'Data disubmit!', 'data' => $this->model->find($id)]);
        } catch (\Exception $e) {
            return $this->fail('Dapur Error: ' . $e->getMessage(), 500);
        }
    }

    public function uploadPpic()
    {
        $user = $this->request->user ?? null;
        if (!$user) {
            return $this->failUnauthorized('Akses ditolak: Token tidak valid!');
        }
        $role = strtoupper($user->role ?? 'GUEST');
        if ($role !== 'PPIC' && $role !== 'ADMIN' && $role !== 'MANAGER') {
            return $this->failForbidden('Akses ditolak: Hanya divisi PPIC/Admin/Manager yang boleh mengupload file PPIC.');
        }

        // 1. Kasih napas lega
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '180'); // Tambah waktu jadi 3 menit

        $supplier_id = $this->request->getPost('supplier_id');
        $periode     = $this->request->getPost('periode');
        $file        = $this->request->getFile('ppic_file');

        if (!$supplier_id || !$file || !$file->isValid()) {
            return $this->fail('File gak valid atau data kurang Pi!', 400);
        }

        try {
            $supplierModel = new SupplierModel();
            $supplier = $supplierModel->find($supplier_id);
            $targetKode = trim((string)$supplier['kode_vendor']);

            $filename = $file->getTempName();
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filename);
            
            // 2. KUNCI: Cari nama sheet yang mengandung kata 'LIST' dulu tanpa load filenya
            $sheetNames = $reader->listWorksheetNames($filename);
            $targetSheet = null;
            foreach ($sheetNames as $name) {
                if (stripos($name, 'LIST') !== false) {
                    $targetSheet = $name;
                    break;
                }
            }

            if (!$targetSheet) return $this->fail('Sheet LIST gak ketemu boi!', 404);

            // 3. OPTIMASI: Cuma baca data murni, gak usah baca gaya/format/grafik
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly($targetSheet);
            
            // 4. FILTER: Batasi baca cuma kolom B sampe F (B=Kode, F=Score)
            // Ini biar memori gak jebol gara-gara sheet LPB yang gede banget itu
            $spreadsheet = $reader->load($filename);
            $sheet = $spreadsheet->getSheetByName($targetSheet);
            
            $highestRow = $sheet->getHighestRow();
            $scoreValue      = null;
            $namaVendorExcel = null;
            $tidakAdaPO      = false;

            // PENTING: Kolom B di Excel menyimpan kode vendor sebagai INTEGER (bukan string).
            // Kita bandingkan keduanya sebagai string setelah trim untuk handle leading zeros jika ada.
            // Cast $targetKode juga ke int lalu string agar konsisten.
            $targetKodeNorm = (string)(int)$targetKode; // "1003107" → sama dengan (int)1003107 → "1003107"

            for ($row = 1; $row <= $highestRow; $row++) {
                $cellBRaw  = $sheet->getCell('B' . $row)->getValue();
                $cellBNorm = (string)(int)trim((string)$cellBRaw); // int dari excel → cast ke string

                if ($cellBNorm === $targetKodeNorm && $cellBNorm !== '0') {
                    // Kolom C = Nama Vendor (untuk konfirmasi ke frontend)
                    $namaVendorExcel = trim((string)$sheet->getCell('C' . $row)->getValue());

                    // Kolom F = Score (disimpan Excel sebagai desimal: 0.8396 = 83.96%)
                    // PENTING: Gunakan getOldCalculatedValue() karena setLoadSheetsOnly('LIST')
                    // membuat formula SUMIF gagal menghitung (karena sheet 'jadwal' tidak diload).
                    // getOldCalculatedValue() akan mengambil hasil cache terakhir yang disimpan Excel.
                    $valF = null;
                    try {
                        $valF = $sheet->getCell('F' . $row)->getOldCalculatedValue();
                        // Fallback jika tidak ada cache sama sekali (jarang terjadi)
                        if ($valF === null) {
                            $valF = $sheet->getCell('F' . $row)->getCalculatedValue();
                        }
                    } catch (\Exception $e) {
                        $valF = $sheet->getCell('F' . $row)->getValue();
                    }

                    if ($valF === '-' || $valF === null || $valF === '' || (string)$valF === '-') {
                        // Tidak ada PO / delivery di bulan ini
                        $scoreValue = 0;
                        $tidakAdaPO = true;
                    } else {
                        // Excel simpan sebagai desimal (0.8396) → kita jadikan persen (83.96)
                        $scoreValue = round((float)$valF * 100, 2);
                    }
                    break;
                }
            }

            // Bersihin memori
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            if ($scoreValue === null) {
                return $this->fail(
                    "Kode vendor \"$targetKode\" (dinormalisasi: \"$targetKodeNorm\") tidak ditemukan di sheet \"$targetSheet\". Cek apakah kode vendor di database sesuai dengan yang ada di Excel.",
                    404
                );
            }

            // Simpan ke DB (UPSERT)
            $existing = $this->model->where('supplier_id', $supplier_id)->where('periode', $periode)->first();
            $dataPpic = [
                'supplier_id'     => $supplier_id,
                'periode'         => $periode,
                'ppic_ot_percent' => round($scoreValue, 2)
            ];

            if ($existing) {
                $this->model->update($existing['id'], $dataPpic);
                $id = $existing['id'];
            } else {
                $id = $this->model->insert($dataPpic);
            }

            $message = $tidakAdaPO
                ? "Tidak ada PO/delivery untuk vendor $targetKode di periode ini."
                : "Score berhasil dibaca: " . round($scoreValue, 2) . "%";

            return $this->respond([
                'status'           => 'success',
                'message'          => $message,
                'tidak_ada_po'     => $tidakAdaPO,
                'excel_kode_vendor'=> $targetKode,
                'excel_nama_vendor'=> $namaVendorExcel,
                'score_percent'    => round($scoreValue, 2),
                'data'             => $this->model->find($id)
            ]);

        } catch (\Exception $e) {
            return $this->fail('Dapur Meledak: ' . $e->getMessage(), 500);
        }
    }


    /**
     * GET /api/penilaian/summary/dashboard
     * Ngitung statistik keseluruhan buat KPI cards di dashboard
     */
    public function dashboardSummary()
    {
        $db = \Config\Database::connect();

        // Hitung total supplier aktif
        $totalSuppliers = $db->table('m_supplier')->where('is_active', 1)->countAllResults();

        // Ambil periode terbaru yang ada datanya
        $latestPeriode = $db->table('t_penilaian')
            ->select('periode')
            ->orderBy('periode', 'DESC')
            ->limit(1)
            ->get()->getRow();

        $periode = $latestPeriode ? $latestPeriode->periode : date('Y-m');

        $gradeA = 0;
        $gradeB = 0;
        $gradeC = 0;
        $pendingInput = 0;

        if ($totalSuppliers > 0) {
            // Ambil data semua supplier aktif + penilaian (jika ada) untuk periode terbaru
            $builder = $db->table('m_supplier s');
            $builder->select('p.grade, p.qc_ng_percent, p.ppic_ot_percent, p.pch_score, p.hse_score');
            $builder->join('t_penilaian p', 'p.supplier_id = s.id AND p.periode = ' . $db->escape($periode), 'left');
            $builder->where('s.is_active', 1);
            $rows = $builder->get()->getResultArray();

            foreach ($rows as $row) {
                $g = $row['grade'] ?? '';
                if ($g === 'A' || $g === 'BAIK') {
                    $gradeA++;
                } elseif ($g === 'B' || $g === 'CUKUP') {
                    $gradeB++;
                } elseif ($g === 'C' || $g === 'KURANG') {
                    $gradeC++;
                }

                // Hitung pending jika ada salah satu divisi yang bernilai null
                if ($row['qc_ng_percent'] === null || $row['ppic_ot_percent'] === null || 
                    $row['pch_score'] === null || $row['hse_score'] === null) {
                    $pendingInput++;
                }
            }
        }

        return $this->respond([
            'status'          => 200,
            'total_suppliers' => $totalSuppliers,
            'grade_a'         => $gradeA,
            'grade_b'         => $gradeB,
            'grade_c'         => $gradeC,
            'pending_input'   => $pendingInput,
            'latest_periode'  => $periode,
        ]);
    }

    /**
     * GET /api/penilaian/heatmap/data?periode=YYYY-MM
     * Ngambil semua data penilaian + info supplier buat master rekap heatmap
     */
    public function heatmapData()
    {
        $periode = $this->request->getGet('periode');

        $db = \Config\Database::connect();

        if (!$periode) {
            // Kalo gak dikasih periode, ambil yang paling baru aja dari t_penilaian
            $latestPeriode = $db->table('t_penilaian')
                ->select('periode')
                ->orderBy('periode', 'DESC')
                ->limit(1)
                ->get()->getRow();
            $periode = $latestPeriode ? $latestPeriode->periode : date('Y-m');
        }

        $builder = $db->table('m_supplier s');
        $builder->select("
            COALESCE(p.id, CONCAT('temp-', s.id)) as id, 
            s.id as supplier_id,
            COALESCE(p.periode, " . $db->escape($periode) . ") as periode, 
            p.qc_qty_terima, p.qc_qty_reject, p.qc_ng_percent, p.qc_score,
            p.ppic_ot_percent, p.ppic_score,
            p.pch_harga, p.pch_moq, p.pch_top, p.pch_pelayanan, p.pch_score,
            p.hse_uji_emisi, p.hse_apd, p.hse_score,
            p.total_score, p.grade, p.status_final,
            s.nama_vendor, s.kode_vendor, s.jenis_bahan
        ", false);
        $builder->join('t_penilaian p', 'p.supplier_id = s.id AND p.periode = ' . $db->escape($periode), 'left');
        $builder->where('s.is_active', 1);
        $builder->orderBy('p.id', 'DESC');
        $builder->orderBy('s.nama_vendor', 'ASC');

        $data = $builder->get()->getResultArray();

        return $this->respond($data);
    }

    /**
     * GET /api/penilaian/top-performers?limit=5
     * Ambil supplier dengan skor tertinggi di periode terbaru
     */
    public function topPerformers()
    {
        $limit = (int) ($this->request->getGet('limit') ?? 5);
        if ($limit <= 0 || $limit > 50) $limit = 5;

        $db = \Config\Database::connect();

        // Ambil periode terbaru dulu
        $latestPeriode = $db->table('t_penilaian')
            ->select('periode')
            ->orderBy('periode', 'DESC')
            ->limit(1)
            ->get()->getRow();

        if (!$latestPeriode) {
            return $this->respond([]);
        }

        $data = $db->table('t_penilaian p')
            ->select('p.qc_score, p.ppic_score, p.pch_score, p.hse_score, p.periode, s.nama_vendor, s.kode_vendor, s.jenis_bahan')
            ->join('m_supplier s', 's.id = p.supplier_id', 'inner')
            ->where('p.periode', $latestPeriode->periode)
            ->orderBy('p.qc_score', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();

        return $this->respond($data);
    }

    /**
     * GET /api/penilaian/evaluasi/detail
     *   ?kode_vendor=1003107&periode_awal=2025-07&periode_akhir=2025-12
     *
     * Mengembalikan data aktual bulan ke bulan dan rata-rata agregat
     * untuk satu vendor dalam rentang waktu yang ditentukan.
     */
    public function getDetailEvaluasi()
    {
        $kodeVendor   = $this->request->getGet('kode_vendor');
        $periodeAwal  = $this->request->getGet('periode_awal');
        $periodeAkhir = $this->request->getGet('periode_akhir');

        // ── Validasi parameter ────────────────────────────────────────────────
        if (empty($kodeVendor) || empty($periodeAwal) || empty($periodeAkhir)) {
            return $this->fail(
                'Parameter kode_vendor, periode_awal, dan periode_akhir wajib diisi.',
                400
            );
        }

        // Format YYYY-MM sederhana (tidak perlu regex berat)
        $periodePattern = '/^\d{4}-(0[1-9]|1[0-2])$/';
        if (!preg_match($periodePattern, $periodeAwal) || !preg_match($periodePattern, $periodeAkhir)) {
            return $this->fail('Format periode harus YYYY-MM (contoh: 2025-07).', 400);
        }

        if ($periodeAwal > $periodeAkhir) {
            return $this->fail('periode_awal tidak boleh lebih besar dari periode_akhir.', 400);
        }

        // ── Eksekusi query lewat Model ────────────────────────────────────────
        try {
            $result = $this->model->getDetailEvaluasi(
                trim($kodeVendor),
                $periodeAwal,
                $periodeAkhir
            );

            if (empty($result['data_aktual'])) {
                return $this->respond([
                    'status'       => 'empty',
                    'message'      => "Tidak ada data untuk vendor $kodeVendor dalam rentang $periodeAwal s/d $periodeAkhir.",
                    'nama_vendor'  => '-',
                    'jenis_bahan'  => '-',
                    'data_aktual'  => [],
                    'rata_rata'    => null,
                ]);
            }

            return $this->respond([
                'status'      => 'success',
                'nama_vendor' => $result['nama_vendor'],
                'jenis_bahan' => $result['jenis_bahan'],
                'data_aktual' => $result['data_aktual'],
                'rata_rata'   => $result['rata_rata'],
            ]);

        } catch (\Exception $e) {
            return $this->fail('Server error: ' . $e->getMessage(), 500);
        }
    }

    public function exportExcel()
    {
        $json = $this->request->getJSON(true);
        if (!$json || empty($json['headers']) || !isset($json['rows'])) {
            return $this->fail('Data export tidak valid.', 400);
        }

        $filename = $json['filename'] ?? 'export.xlsx';
        $title    = $json['title'] ?? 'LAPORAN PENILAIAN VENDOR';
        $periode  = $json['periode'] ?? '';
        $headers  = $json['headers'];
        $rows     = $json['rows'];

        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Tentukan range kolom berdasarkan jumlah headers
            $maxColIndex = count($headers);
            $maxColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($maxColIndex);

            // 1. Judul Laporan
            $sheet->mergeCells("A1:{$maxColLetter}1");
            $sheet->setCellValue('A1', strtoupper($title));
            $sheet->getStyle("A1:{$maxColLetter}1")->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle("A1:{$maxColLetter}1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // 2. Sub-judul Periode
            if ($periode) {
                $sheet->mergeCells("A2:{$maxColLetter}2");
                $sheet->setCellValue('A2', $periode);
                $sheet->getStyle("A2:{$maxColLetter}2")->getFont()->setItalic(true)->setSize(11);
                $sheet->getStyle("A2:{$maxColLetter}2")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            }

            // 3. Headers Laporan
            $headerRow = 4;
            foreach ($headers as $colIdx => $headerText) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
                $sheet->setCellValue("{$colLetter}{$headerRow}", $headerText);
            }

            // Styling Header
            $sheet->getStyle("A{$headerRow}:{$maxColLetter}{$headerRow}")->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle("A{$headerRow}:{$maxColLetter}{$headerRow}")->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            
            $sheet->getStyle("A{$headerRow}:{$maxColLetter}{$headerRow}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE2E8F0'); // Slate 200

            // 4. Data Rows
            $startDataRow = 5;
            $currentRow = $startDataRow;
            foreach ($rows as $rowData) {
                foreach ($rowData as $colIdx => $val) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
                    $sheet->setCellValue("{$colLetter}{$currentRow}", $val);
                }
                $currentRow++;
            }
            $lastDataRow = $currentRow - 1;

            // 5. Gridlines & Borders
            $sheet->setShowGridlines(true);
            $borderStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['argb' => 'FFCBD5E1'], // Slate 300
                    ],
                ],
            ];
            $sheet->getStyle("A{$headerRow}:{$maxColLetter}{$lastDataRow}")->applyFromArray($borderStyle);

            // 6. Alignment & Formatting
            for ($row = $startDataRow; $row <= $lastDataRow; $row++) {
                for ($col = 1; $col <= $maxColIndex; $col++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $cell = $sheet->getCell("{$colLetter}{$row}");
                    $val = $cell->getValue();
                    
                    $alignment = \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER;
                    
                    // Kolom Nama Vendor (kolom 3) set left
                    if ($col === 3) {
                        $alignment = \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT;
                    }
                    
                    $sheet->getStyle("{$colLetter}{$row}")->getAlignment()
                        ->setHorizontal($alignment)
                        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                }
            }

            // 7. Auto-fit Columns
            for ($col = 1; $col <= $maxColIndex; $col++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            }

            // Set headers for file transfer
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;

        } catch (\Exception $e) {
            return $this->fail('Gagal mengekspor Excel: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/penilaian/analisis/{supplier_id}?periode=YYYY-MM
     * Menganalisis performa vendor secara dinamis di server-side.
     */
    public function analisis($supplierId = null)
    {
        if (!$supplierId) {
            return $this->fail('Supplier ID tidak boleh kosong!', 400);
        }

        $periode = $this->request->getGet('periode');
        $db = \Config\Database::connect();

        if (!$periode) {
            $latestPeriode = $db->table('t_penilaian')
                ->select('periode')
                ->orderBy('periode', 'DESC')
                ->limit(1)
                ->get()->getRow();
            $periode = $latestPeriode ? $latestPeriode->periode : date('Y-m');
        }

        // Cari data supplier dan penilaian untuk periode tsb
        $supplier = $db->table('m_supplier')->where('id', $supplierId)->get()->getRowArray();
        if (!$supplier) {
            return $this->failNotFound('Supplier tidak ditemukan!');
        }

        $penilaian = $db->table('t_penilaian')
            ->where('supplier_id', $supplierId)
            ->where('periode', $periode)
            ->get()->getRowArray();

        $strengths = [];
        $weaknesses = [];
        $recommendation = "Belum ada rekomendasi karena data penilaian belum lengkap.";

        if ($penilaian) {
            // Evaluasi QC (Maks 30)
            $qcScore = $penilaian['qc_score'];
            $qcNg = $penilaian['qc_ng_percent'];
            if ($qcScore !== null) {
                if ($qcScore >= 30) {
                    $strengths[] = "Kualitas material sangat prima dengan tingkat reject (NG) rendah sebesar " . round($qcNg, 2) . "%.";
                } elseif ($qcScore <= 15) {
                    $weaknesses[] = "Tingkat reject (NG) cukup tinggi sebesar " . round($qcNg, 2) . "%, memerlukan perbaikan kualitas produksi.";
                }
            }

            // Evaluasi PPIC (Maks 30)
            $ppicScore = $penilaian['ppic_score'];
            $ppicOt = $penilaian['ppic_ot_percent'];
            if ($ppicScore !== null) {
                if ($ppicScore >= 30) {
                    $strengths[] = "Ketepatan pengiriman sangat baik (On-Time Delivery sebesar " . round($ppicOt, 2) . "%).";
                } elseif ($ppicScore <= 15) {
                    $weaknesses[] = "Sering terjadi keterlambatan pengiriman (On-Time Delivery hanya " . round($ppicOt, 2) . "%).";
                }
            }

            // Evaluasi PCH (Maks 25)
            $pchHarga = $penilaian['pch_harga'];
            $pchMoq = $penilaian['pch_moq'];
            $pchPelayanan = $penilaian['pch_pelayanan'];
            if ($pchHarga === 'BAIK' && $pchMoq === 'BAIK') {
                $strengths[] = "Kesesuaian harga dan MOQ sangat kooperatif bagi kebutuhan pabrik.";
            } elseif ($pchHarga === 'KURANG' || $pchPelayanan === 'KURANG') {
                $weaknesses[] = "Harga kurang bersaing atau kualitas pelayanan/respon kurang memuaskan bagi Purchasing.";
            }

            // Evaluasi HSE (Maks 10)
            $hseEmisi = $penilaian['hse_uji_emisi'];
            $hseApd = $penilaian['hse_apd'];
            if ($hseEmisi === 'BAIK' && $hseApd === 'BAIK') {
                $strengths[] = "Kepatuhan terhadap aspek K3 (penggunaan APD driver) & uji emisi kendaraan sangat tertib.";
            } elseif ($hseEmisi === 'KURANG' || $hseApd === 'KURANG') {
                $weaknesses[] = "Kedisiplinan K3 driver atau standar kelayakan emisi armada pengiriman perlu diperbaiki.";
            }

            // Tentukan rekomendasi berdasarkan Grade
            $grade = $penilaian['grade'];
            if ($grade === 'A') {
                $recommendation = "Pertahankan kualitas dan layanan luar biasa ini. Sangat direkomendasikan untuk kelanjutan kontrak PO jangka panjang.";
            } elseif ($grade === 'B') {
                $recommendation = "Kinerja cukup baik secara umum, namun direkomendasikan untuk melakukan koordinasi perbaikan pada area: " . 
                    (!empty($weaknesses) ? implode(', ', array_map(function($w) { return strstr($w, ' ', true) ?: $w; }, $weaknesses)) : "efisiensi pengiriman/proses.");
            } else {
                $recommendation = "PERINGATAN: Performa vendor berada di bawah standar minimum perusahaan. Perlu diterbitkan Surat Peringatan (SP) Supplier, audit kualitas langsung ke plant supplier, atau pembekuan PO sementara.";
            }
        }

        // Fallback jika tidak ada kekuatan / kelemahan terdeteksi secara spesifik
        if (empty($strengths) && $penilaian) {
            $strengths[] = "Performa standar di semua departemen.";
        }
        if (empty($weaknesses) && $penilaian) {
            $weaknesses[] = "Tidak ada kelemahan kritis yang terdeteksi di periode ini.";
        }

        return $this->respond([
            'status' => 'success',
            'supplier_id' => (int)$supplierId,
            'periode' => $periode,
            'has_data' => !empty($penilaian),
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'recommendation' => $recommendation
        ]);
    }
}