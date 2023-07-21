<?php

namespace Packk\Core\config;

return [
    /**
     * Conexão para o banco da plataforma.
     */
    'packk-legacy-connection' => env('PACKK_CORE_DB_CONNECTION', 'mysql'),

    /**
     * Conexão para o banco de entregas
     */
    'packk-deliveries-connection' => env('PACKK_CORE_DELIVERY_DB_CONNECTION', env('DB_DELIVERIES_CONNECTION', 'delivery')),

    /**
     * Conexão para o banco de ocorrências
     */
    'packk-issues-connection' => env('PACKK_CORE_ISSUES_DB_CONNECTION', env('DB_ISSUES_CONNECTION', 'iss'))
];
