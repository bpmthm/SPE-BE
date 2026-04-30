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
        $json = $this->request->getJSON(true);
        if (!$json || empty($json['supplier_id']) || empty($json['periode'])) {
            return $this->fail('Data supplier/periode kosong Pi!', 400);
        }

        $existing = $this->model->where('supplier_id', $json['supplier_id'])->where('periode', $json['periode'])->first();

        try {
            if ($existing) {
                $this->model->update($existing['id'], $json);
                $id = $existing['id'];
            } else {
                $id = $this->model->insert($json);
            }
            return $this->respond(['status' => 'success', 'message' => 'Data disubmit!', 'data' => $this->model->find($id)]);
        } catch (\Exception $e) {
            return $this->fail('Dapur Error: ' . $e->getMessage(), 500);
        }
    }

   public function uploadPpic()
    {
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
            $scoreValue = null;

            // Cari barisnya (mulai baris 5 biasanya di excel si ibu)
            for ($row = 1; $row <= $highestRow; $row++) {
                $cellB = trim((string)$sheet->getCell('B' . $row)->getValue());
                
                if ($cellB === $targetKode) {
                    $valF = $sheet->getCell('F' . $row)->getCalculatedValue();
                    
                    if ($valF === '-' || $valF === null || $valF === '') {
                        $scoreValue = 0;
                    } else {
                        // Kalo di excel 0.8396, jadi 83.96
                        $scoreValue = (float)$valF * 100;
                    }
                    break;
                }
            }

            // Bersihin memori abis dipake
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            if ($scoreValue === null) {
                return $this->fail("Kode vendor $targetKode gak ada di sheet $targetSheet.", 404);
            }

            // Simpan ke DB (Logic UPSERT)
            $existing = $this->model->where('supplier_id', $supplier_id)->where('periode', $periode)->first();
            $dataPpic = [
                'supplier_id' => $supplier_id,
                'periode' => $periode,
                'ppic_ot_percent' => round($scoreValue, 2)
            ];

            if ($existing) {
                $this->model->update($existing['id'], $dataPpic);
                $id = $existing['id'];
            } else {
                $id = $this->model->insert($dataPpic);
            }

            return $this->respond([
                'status' => 'success',
                'message' => "Selesai! Skor: " . round($scoreValue, 2) . "%",
                'data' => $this->model->find($id)
            ]);

        } catch (\Exception $e) {
            return $this->fail('Dapur Meledak: ' . $e->getMessage(), 500);
        }
    }
}