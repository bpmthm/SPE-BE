<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JwtFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Pintu darurat untuk OPTIONS preflight request
        if ($request->getMethod(true) === 'OPTIONS') {
            return;
        }

        $header = $request->getServer('HTTP_AUTHORIZATION');
        
        $token = null;
        if ($header && preg_match('/Bearer\s(\S+)/i', $header, $matches)) {
            $token = $matches[1];
        }

        // Kalau nggak bawa token, langsung tendang
        if (!$token) {
            return \Config\Services::response()
                ->setJSON(['status' => false, 'message' => 'Akses Ditolak: Token tidak ditemukan!'])
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
        }

        try {
            // Secret Key ini HARUS SAMA kayak yang dipake pas bikin token (diambil dari .env)
            $key = env('JWT_SECRET_KEY');
            if (empty($key)) {
                throw new \RuntimeException('JWT_SECRET_KEY is not configured in .env');
            }
            
            // Verifikasi token
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            
            // (Opsional) Lempar data role/user ke request biar bisa dipake di Controller
            $request->user = $decoded;
            
        } catch (Exception $e) {
            // Kalau token kadaluarsa atau dipalsuin
            return \Config\Services::response()
                ->setJSON(['status' => false, 'message' => 'Token Tidak Valid / Expired!'])
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Kosongin aja
    }
}