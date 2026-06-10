<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use Firebase\JWT\JWT;

class Auth extends BaseController
{
    public function generateToken()
    {
        // Nanti logic aslinya: validasi kiriman dari Chitose Portal
        // Anggap aja ini simulasi role PCH yang berhasil masuk
        $role = $this->request->getVar('role') ?? 'PCH';
        $nik = $this->request->getVar('nik') ?? '123456';

        $key = env('JWT_SECRET_KEY');
        if (empty($key)) {
            throw new \RuntimeException('JWT_SECRET_KEY is not configured in .env');
        }
        
        $payload = [
            'iss'  => 'ChitosePortal',
            'aud'  => 'SPE_System',
            'iat'  => time(),             // Waktu token dibuat
            'exp'  => time() + (60 * 60 * 8), // Token hangus dalam 8 Jam (jam kerja)
            'role' => $role,
            'nik'  => $nik
        ];

        // Cetak tiket VIP-nya
        $token = JWT::encode($payload, $key, 'HS256');

        return $this->response->setJSON([
            'status' => true,
            'message' => 'Login SSO Berhasil',
            'token' => $token
        ]);
    }
}