<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// API Routes
$routes->group('api', ['namespace' => 'App\Controllers\Api'], static function ($routes) {
    
    // 1. Pintu darurat buat preflight OPTIONS biar gak 404
    $routes->options('(:any)', static function() {
        return response()->setStatusCode(200);
    });

    // 2. Custom endpoints (HARUS di atas resource)
    $routes->get('penilaian/summary/dashboard', 'Penilaian::dashboardSummary');
    $routes->get('penilaian/heatmap/data', 'Penilaian::heatmapData');
    $routes->get('penilaian/top-performers', 'Penilaian::topPerformers');
    $routes->post('penilaian/upload-ppic', 'Penilaian::uploadPpic');
    $routes->post('penilaian/upsert', 'Penilaian::upsert');

    // 3. Resource routes
    $routes->resource('supplier', ['controller' => 'Supplier', 'except' => ['new', 'edit']]);
    $routes->resource('penilaian', ['controller' => 'Penilaian', 'except' => ['new', 'edit']]);
});