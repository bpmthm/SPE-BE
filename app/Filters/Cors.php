<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class Cors implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, X-API-KEY, Access-Control-Request-Method');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE, PATCH');
        
        // Kalo browser cuma ngecek jalan (Preflight / OPTIONS), langsung kasih izin dan stop di sini
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method == "OPTIONS") {
            die();
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Gak perlu ngapa-ngapain di after
    }
}