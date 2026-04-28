<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// API Routes
$routes->group('api', ['namespace' => 'App\Controllers\Api'], static function ($routes) {
    // Custom endpoints untuk dashboard & heatmap (must come before resource routes)
    $routes->get('penilaian/summary/dashboard', 'Penilaian::dashboardSummary');
    $routes->get('penilaian/heatmap/data', 'Penilaian::heatmapData');
    $routes->get('penilaian/top-performers', 'Penilaian::topPerformers');
    
    $routes->post('penilaian/upload-ppic', 'Api\Penilaian::uploadPpic');
    $routes->post('penilaian/upsert', 'Api\Penilaian::upsert');

    // Supplier Resource
    $routes->resource('supplier', [
        'controller' => 'Supplier',
        'except'     => ['new', 'edit']
    ]);

    // Penilaian Resource
    $routes->resource('penilaian', [
        'controller' => 'Penilaian',
        'except'     => ['new', 'edit']
    ]);
});