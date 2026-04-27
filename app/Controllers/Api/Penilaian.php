<?php

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;

class Penilaian extends ResourceController
{
    protected $modelName = 'App\Models\PenilaianModel';
    protected $format    = 'json';

    /**
     * Return an array of resource objects (dengan filter optional)
     * GET /api/penilaian
     * GET /api/penilaian?supplier_id=1
     * GET /api/penilaian?periode=2026-04
     */
    public function index()
    {
        $supplier_id = $this->request->getVar('supplier_id');
        $periode = $this->request->getVar('periode');

        $query = $this->model;

        // Filter by supplier_id jika ada
        if ($supplier_id) {
            $query = $query->where('supplier_id', $supplier_id);
        }

        // Filter by periode jika ada
        if ($periode) {
            $query = $query->where('periode', $periode);
        }

        $data = $query->orderBy('periode', 'DESC')->findAll();
        return $this->respond($data);
    }

    /**
     * Return the properties of a resource object (detail 1 penilaian)
     * GET /api/penilaian/:id
     */
    public function show($id = null)
    {
        if (!$id) {
            return $this->fail('ID penilaian tidak ditemukan', 400);
        }

        $data = $this->model->find($id);

        if (!$data) {
            return $this->failNotFound('Penilaian tidak ditemukan');
        }

        return $this->respond($data);
    }

    /**
     * Create a new resource (UPSERT logic)
     * POST /api/penilaian
     * 
     * Body example:
     * {
     *   "supplier_id": 1,
     *   "periode": "2026-04",
     *   "qc_ng_percent": 0.5,
     *   "ppic_ot_percent": 92.5,
     *   "pch_harga": "BAIK",
     *   "pch_moq": "CUKUP",
     *   "pch_top": "BAIK",
     *   "pch_pelayanan": "BAIK",
     *   "hse_uji_emisi": "BAIK",
     *   "hse_apd": "BAIK"
     * }
     */
    public function create()
    {
        $data = $this->request->getJSON(true);

        // Validasi required fields
        if (!$data['supplier_id'] || !$data['periode']) {
            return $this->fail('supplier_id dan periode wajib diisi', 400);
        }

        // Check apakah data untuk supplier & periode ini sudah ada (untuk UPSERT)
        $existing = $this->model
            ->where('supplier_id', $data['supplier_id'])
            ->where('periode', $data['periode'])
            ->first();

        if ($existing) {
            // Update existing
            $this->model->update($existing['id'], $data);
            return $this->respondCreated([
                'id' => $existing['id'],
                'message' => 'Penilaian updated successfully',
                'data' => $this->model->find($existing['id'])
            ]);
        }

        // Create new
        $id = $this->model->insert($data);

        if ($id) {
            return $this->respondCreated([
                'id' => $id,
                'message' => 'Penilaian created successfully',
                'data' => $this->model->find($id)
            ]);
        }

        return $this->fail('Gagal membuat penilaian', 500);
    }

    /**
     * Update a model resource
     * PATCH /api/penilaian/:id
     */
    public function update($id = null)
    {
        if (!$id) {
            return $this->fail('ID penilaian tidak ditemukan', 400);
        }

        $data = $this->request->getJSON(true);

        if (!$this->model->find($id)) {
            return $this->failNotFound('Penilaian tidak ditemukan');
        }

        $this->model->update($id, $data);
        return $this->respond([
            'id' => $id,
            'message' => 'Penilaian updated successfully',
            'data' => $this->model->find($id)
        ]);
    }

    /**
     * Delete the designated resource object
     * DELETE /api/penilaian/:id
     */
    public function delete($id = null)
    {
        if (!$id) {
            return $this->fail('ID penilaian tidak ditemukan', 400);
        }

        if (!$this->model->find($id)) {
            return $this->failNotFound('Penilaian tidak ditemukan');
        }

        $this->model->delete($id);
        return $this->respondDeleted(['message' => 'Penilaian deleted successfully']);
    }

    /**
     * Get summary data untuk dashboard (KPI, stats)
     * GET /api/penilaian/summary/dashboard
     */
    public function dashboardSummary()
    {
        // Get latest periode
        $latestPeriode = $this->model
            ->selectMax('periode', 'max_periode')
            ->first();

        $periode = $latestPeriode['max_periode'] ?? date('Y-m');

        // Get all penilaian for latest periode
        $data = $this->model
            ->where('periode', $periode)
            ->findAll();

        $totalSupplier = count($data);
        $gradeA = 0;
        $gradeC = 0;

        foreach ($data as $p) {
            if (isset($p['grade'])) {
                if ($p['grade'] === 'A') $gradeA++;
                elseif ($p['grade'] === 'C') $gradeC++;
            }
        }

        // Count pending (suppliers without current month data)
        $currentPeriode = date('Y-m');
        $allSuppliers = 42; // Total suppliers
        $withCurrentPeriode = count($data);
        $pending = $allSuppliers - $withCurrentPeriode;

        return $this->respond([
            'total_suppliers' => $totalSupplier,
            'grade_a' => $gradeA,
            'grade_c' => $gradeC,
            'pending_input' => max(0, $pending)
        ]);
    }

    /**
     * Get heatmap data untuk master rekap
     * GET /api/penilaian/heatmap?periode=2026-04
     */
    public function heatmapData()
    {
        $periode = $this->request->getVar('periode') ?? date('Y-m');

        $data = $this->model
            ->join('m_supplier', 'm_supplier.id = t_penilaian.supplier_id')
            ->where('periode', $periode)
            ->select('t_penilaian.*, m_supplier.nama_vendor, m_supplier.jenis_bahan')
            ->orderBy('t_penilaian.total_score', 'DESC')
            ->findAll();

        return $this->respond($data);
    }

    /**
     * Get top performers (untuk top 5 chart dashboard)
     * GET /api/penilaian/top-performers?limit=5
     */
    public function topPerformers()
    {
        $limit = $this->request->getVar('limit') ?? 5;

        $data = $this->model
            ->join('m_supplier', 'm_supplier.id = t_penilaian.supplier_id')
            ->select('m_supplier.nama_vendor, t_penilaian.total_score')
            ->orderBy('t_penilaian.total_score', 'DESC')
            ->limit($limit)
            ->findAll();

        return $this->respond($data);
    }
}
