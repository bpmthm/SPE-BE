<?php
require '/home/ghost/Documents/chitose/SPE/evaluasi-backend/vendor/autoload.php';

$filename = "/home/ghost/Documents/chitose/PPIC_PENILAIAN PEMASOK APS MARET 2026'.xlsx";
$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filename);
$reader->setReadDataOnly(true);
$reader->setLoadSheetsOnly('LIST ');
$spreadsheet = $reader->load($filename);
$sheet = $spreadsheet->getSheetByName('LIST ');

$highestRow = $sheet->getHighestRow();

echo "Row | B (Kode) | C (Nama) | F (Score RAW) | F (Calculated) | F (Formatted)\n";
echo str_repeat("-", 80) . "\n";

for ($row = 6; $row <= 15; $row++) {
    $cellB = $sheet->getCell('B' . $row)->getValue();
    $cellC = $sheet->getCell('C' . $row)->getValue();
    $cellF = $sheet->getCell('F' . $row);
    
    $valFRaw = $cellF->getValue();
    $valFOldCalc = null;
    $valFCalc = null;
    try {
        $valFOldCalc = $cellF->getOldCalculatedValue();
        $valFCalc = $cellF->getCalculatedValue();
    } catch (\Exception $e) {
        $valFCalc = "ERROR: " . $e->getMessage();
    }
    
    $valFFormatted = $cellF->getFormattedValue();

    echo sprintf("%3d | %-8s | %-20s | %-12s | %-15s | %-15s | %-15s\n", 
        $row, 
        $cellB, 
        substr((string)$cellC, 0, 20), 
        (string)$valFRaw, 
        (string)$valFOldCalc,
        (string)$valFCalc,
        (string)$valFFormatted
    );
}
