<?php

return [
    'app' => [
        'screens' => [
            'tracking' => 'TRACKING',
            'evaluation' => 'EVALUATION',
            'news' => 'NEWS'
        ]
    ],
    'dynamic' => [
        'domain' => env('DEFAULT_DOMAIN_ID', 1),
    ],
    'user_master' => [
        'force_domain' => false
    ]
];
