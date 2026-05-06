<?php
/**
 * Script Diagnosa Database - jalankan via: php cek_db.php
 * Cek data NULL, data dummy, dan integritas data di t_penilaian
 */

$host = '127.0.0.1';
$user = 'root';
$pass = 'root';
$db   = 'db_evaluasi_pemasok';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("❌ Koneksi gagal: " . $e->getMessage() . "\n");
}

echo "========================================\n";
echo "  DIAGNOSA DATABASE t_penilaian\n";
echo "========================================\n\n";

// 1. Total record
$total = $pdo->query("SELECT COUNT(*) as c FROM t_penilaian")->fetch()['c'];
echo "📊 Total record: $total\n\n";

// 2. Record dengan PPIC NULL
$ppicNull = $pdo->query("SELECT COUNT(*) as c FROM t_penilaian WHERE ppic_ot_percent IS NULL")->fetch()['c'];
echo "⚠️  Record dengan ppic_ot_percent NULL: $ppicNull\n";

// 3. Record dengan QC NULL (qty belum diisi)
$qcNull = $pdo->query("SELECT COUNT(*) as c FROM t_penilaian WHERE qc_qty_terima IS NULL AND qc_qty_reject IS NULL")->fetch()['c'];
echo "⚠️  Record dengan QC QTY NULL: $qcNull\n";

// 4. Record dengan semua PCH NULL
$pchNull = $pdo->query("SELECT COUNT(*) as c FROM t_penilaian WHERE pch_harga IS NULL AND pch_moq IS NULL AND pch_pelayanan IS NULL")->fetch()['c'];
echo "⚠️  Record dengan semua PCH NULL: $pchNull\n";

// 5. Record dengan semua HSE NULL
$hseNull = $pdo->query("SELECT COUNT(*) as c FROM t_penilaian WHERE hse_uji_emisi IS NULL AND hse_apd IS NULL")->fetch()['c'];
echo "⚠️  Record dengan semua HSE NULL: $hseNull\n\n";

// 6. Tampilkan semua record (simplified)
echo "----------------------------------------\n";
echo "SEMUA RECORD (supplier_id | periode | ppic_ot | pch_harga | status):\n";
echo "----------------------------------------\n";
$rows = $pdo->query("
    SELECT 
        t.id, 
        t.supplier_id,
        s.kode_vendor,
        s.nama_vendor,
        t.periode,
        t.qc_ng_percent,
        t.ppic_ot_percent,
        t.pch_harga,
        t.pch_moq,
        t.pch_top,
        t.pch_pelayanan,
        t.hse_uji_emisi,
        t.hse_apd,
        t.total_score,
        t.grade,
        t.status_final,
        t.created_at
    FROM t_penilaian t
    LEFT JOIN m_supplier s ON t.supplier_id = s.id
    ORDER BY t.id DESC
    LIMIT 30
")->fetchAll();

foreach ($rows as $r) {
    $ppic = $r['ppic_ot_percent'] !== null ? $r['ppic_ot_percent'] . '%' : 'NULL';
    $pch  = $r['pch_harga'] ?? 'NULL';
    $hse  = $r['hse_uji_emisi'] ?? 'NULL';
    echo sprintf(
        "[%d] %s (%s) | %s | QC-NG: %s | PPIC-OT: %s | PCH: %s | HSE: %s | Total: %s | %s | %s\n",
        $r['id'],
        $r['kode_vendor'] ?? '?',
        $r['nama_vendor'] ? substr($r['nama_vendor'], 0, 20) : '?',
        $r['periode'],
        $r['qc_ng_percent'] ?? 'NULL',
        $ppic,
        $pch,
        $hse,
        $r['total_score'] ?? 'NULL',
        $r['grade'] ?? '?',
        $r['status_final']
    );
}

echo "\n========================================\n";
echo "CEK KODE VENDOR di m_supplier:\n";
echo "========================================\n";
$suppliers = $pdo->query("SELECT id, kode_vendor, nama_vendor FROM m_supplier ORDER BY kode_vendor LIMIT 20")->fetchAll();
foreach ($suppliers as $s) {
    echo sprintf("[%d] kode_vendor='%s' (len=%d, type=string) | %s\n",
        $s['id'], $s['kode_vendor'], strlen($s['kode_vendor']), $s['nama_vendor']
    );
}

echo "\n✅ Diagnosa selesai.\n";
