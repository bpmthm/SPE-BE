<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// API Routes
$routes->group('api', static function ($routes) {
    // Supplier Resource
    $routes->resource('supplier', [
        'controller' => 'Api\Supplier',
        'except'     => ['new', 'edit']
    ]);
});
