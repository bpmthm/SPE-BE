<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// API Routes
$routes->group('api', ['namespace' => 'App\Controllers\Api'], static function ($routes) {
    
    // 1. OPTIONS preflight
    $routes->options('(:any)', static function() {
        return response()->setStatusCode(200);
    });

    // 2. Public auth endpoints
    $routes->get('auth/generate-token', 'Auth::generateToken');
    $routes->post('auth/generate-token', 'Auth::generateToken');

    // 3. Protected endpoints guarded by JwtFilter
    $routes->group('', ['filter' => 'jwt'], static function ($routes) {
        // Custom endpoints
        $routes->get('penilaian/summary/dashboard', 'Penilaian::dashboardSummary');
        $routes->get('penilaian/heatmap/data', 'Penilaian::heatmapData');
        $routes->get('penilaian/top-performers', 'Penilaian::topPerformers');
        $routes->get('penilaian/evaluasi/detail', 'Penilaian::getDetailEvaluasi');
        $routes->post('penilaian/upload-ppic', 'Penilaian::uploadPpic');
        $routes->post('penilaian/upsert', 'Penilaian::upsert');
        $routes->post('penilaian/export-excel', 'Penilaian::exportExcel');

        // Custom Supplier endpoints
        $routes->get('supplier/all', 'Supplier::allSuppliers');
        $routes->get('supplier/get-qty', 'Supplier::getQtySap');
        $routes->get('supplier/search-sap', 'Supplier::searchSap');
        $routes->post('supplier/sync', 'Supplier::sync');
        $routes->post('supplier/toggle-status/(:num)', 'Supplier::toggleStatus/$1');
        $routes->get('supplier', 'Supplier::index');
        $routes->post('qcdaily', 'QcDaily::create');

        // Resource routes
        $routes->resource('supplier', ['controller' => 'Supplier', 'except' => ['new', 'edit']]);
        $routes->resource('penilaian', ['controller' => 'Penilaian', 'except' => ['new', 'edit']]);
        $routes->resource('qc-daily', ['controller' => 'QcDaily', 'only' => ['index', 'create']]);
        $routes->get('sap/materials', 'QcDaily::searchMaterials');
    });
});