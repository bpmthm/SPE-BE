<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Cross-Origin Resource Sharing (CORS) Configuration
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
 */
class Cors extends BaseConfig
{
    /**
     * The default CORS configuration.
     */
    public array $default = [
        'allowedOrigins' => [
            'http://localhost:3000',
            'http://localhost:8080',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:8080',
            'http://localhost',
        ],

        'allowedOriginsPatterns' => [],

        'supportsCredentials' => true,

        'allowedHeaders' => ['*'],

        'exposedHeaders' => [],

        'allowedMethods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

        'maxAge' => 7200,
    ];
}
