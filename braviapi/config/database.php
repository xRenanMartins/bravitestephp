<?php

use Illuminate\Support\Str;


$redis = [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    'default' => [
        'host' => env('REDIS_HOST', 'localhost'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_DATABASE', 0),
    ],
    'map' => [
        'host' => env('REDIS_HOST_MAP', 'localhost'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_DATABASE', 0),
    ],
    'customer' => [
        'host' => env('REDIS_HOST_CUSTOMER', 'localhost'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_DATABASE', 0),
        'prefix' =>  env('REDIS_CUSTOMER_PREFIX', 'apiminiapp_cache'),
    ],
    'store' => [
        'host' => env('REDIS_HOST_STORE', 'localhost'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_DATABASE', 0),
    ],
];



return [

    
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'write' => [
                'host' => env('DB_AURORA_HOST_W', '127.0.0.1')
            ],
            'read' => [
                'host' => [
                    env('DB_AURORA_HOST_R', '127.0.0.1')
                ]
            ],
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_AURORA_USER', 'laravel'),
            'password' => env('DB_AURORA_PASS', 'laravel'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'sticky' => true,
            'engine' => null,
            'modes' => [
                'STRICT_TRANS_TABLES',
                'NO_ZERO_IN_DATE',
                'NO_ZERO_DATE',
                'NO_ENGINE_SUBSTITUTION'
            ],
            'options' => [
                \PDO::ATTR_PERSISTENT => true
            ]
        ],

        'legacy' => [
            'driver' => 'mysql',
            'write' => [
                'host' => env('DB_AURORA_HOST_W', '127.0.0.1')
            ],
            'read' => [
                'host' => [
                    env('DB_AURORA_HOST_R', '127.0.0.1')
                ]
            ],
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_AURORA_USER', 'laravel'),
            'password' => env('DB_AURORA_PASS', 'laravel'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'sticky' => true,
            'engine' => null,
            'modes' => [
                'STRICT_TRANS_TABLES',
                'NO_ZERO_IN_DATE',
                'NO_ZERO_DATE',
                'NO_ENGINE_SUBSTITUTION'
            ],
            'options' => [
                \PDO::ATTR_PERSISTENT => true
            ]
        ],

        'packk-core' => [
            'driver' => 'mysql',
            'write' => [
                'host' => env('DB_AURORA_HOST_W', '127.0.0.1')
            ],
            'read' => [
                'host' => [
                    env('DB_AURORA_HOST_R', '127.0.0.1')
                ]
            ],
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_AURORA_USER', 'laravel'),
            'password' => env('DB_AURORA_PASS', 'laravel'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'sticky' => true,
            'engine' => null,
            'modes' => [
                'STRICT_TRANS_TABLES',
                'NO_ZERO_IN_DATE',
                'NO_ZERO_DATE',
                'NO_ENGINE_SUBSTITUTION'
            ],
            'options' => [
                \PDO::ATTR_PERSISTENT => true
            ]
        ],

        'lake' => [
            'driver' => 'mysql',
            'write' => [
                'host' => env('DB_LAKE_HOST', '127.0.0.1')
            ],
            'read' => [
                'host' => [
                    env('DB_LAKE_HOST', '127.0.0.1')
                ]
            ],
            'port' => env('DB_LAKE_PORT', '3306'),
            'database' => env('DB_LAKE_DATABASE', 'laravel'),
            'username' => env('DB_LAKE_USER', 'laravel'),
            'password' => env('DB_LAKE_PASS', 'laravel'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'sticky' => true,
            'engine' => null,
            'modes' => [
                'STRICT_TRANS_TABLES',
                'NO_ZERO_IN_DATE',
                'NO_ZERO_DATE',
                'NO_ENGINE_SUBSTITUTION'
            ],
            'options' => [
                \PDO::ATTR_PERSISTENT => true
            ]
        ],

        'utils' => [
            'driver' => 'pgsql',
            'host' => env('DB_POSTGRES_HOST', 'localhost'),
            'port' => '5432',
            'database' => 'utils',
            'username' => env('DB_POSTGRES_USERNAME', 'postgres'),
            'password' => env('DB_POSTGRES_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],

        'utils_2' => [
            'driver' => 'mysql',
            'write' => [
                'host' => env('DB_AURORA_HOST_W', '127.0.0.1')
            ],
            'read' => [
                'host' => [
                    env('DB_AURORA_HOST_R', '127.0.0.1')
                ]
            ],
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE_UTILS', 'utils'),
            'username' => env('DB_AURORA_USER', 'laravel'),
            'password' => env('DB_AURORA_PASS', 'laravel'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'sticky' => true,
            'engine' => null,
            'modes' => [
                'STRICT_TRANS_TABLES',
                'NO_ZERO_IN_DATE',
                'NO_ZERO_DATE',
                'NO_ENGINE_SUBSTITUTION'
            ],
        ],

        'mongodb' => [
            'driver' => 'mongodb',
            'dsn' => env('DB_MONGO_DNS', '127.0.0.1:27017'),
            'database' => env('DB_MONGO_DATABASE', 'shopkeeper'),
        ],

        'mongodb_shopkeeper' => [
            'driver' => 'mongodb',
            'dsn' => env('DB_MONGO_DNS', '127.0.0.1:27017'),
            'database' => env('DB_MONGO_DATABASE', 'shopkeeper'),
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

        'iss' => [
            'driver' => 'mysql',
            'write' => [
                'host' => env('DB_ISSUES_HOST_W', '127.0.0.1')
            ],
            'read' => [
                'host' => [
                    env('DB_ISSUES_HOST_R', '127.0.0.1')
                ]
            ],
            'port' => env('DB_ISSUES_PORT', '3306'),
            'database' => env('DB_ISSUES_DATABASE', 'laravel'),
            'username' => env('DB_ISSUES_USER', 'laravel'),
            'password' => env('DB_ISSUES_PASS', 'laravel'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'sticky' => true,
            'engine' => null,
            'modes' => [
                'STRICT_TRANS_TABLES',
                'NO_ZERO_IN_DATE',
                'NO_ZERO_DATE',
                'NO_ENGINE_SUBSTITUTION'
            ],
            'options' => [
                \PDO::ATTR_PERSISTENT => true
            ]
        ],

        'delivery' => [
            'driver' => 'mysql',
            'read' => [
                'host' => [
                    env('DB_DELIVERY_HOST_W', env('DB_AURORA_HOST_W', '127.0.0.1'))
                ],
            ],
            'write' => [
                'host' => [
                    env('DB_DELIVERY_HOST_R', env('DB_AURORA_HOST_R', '127.0.0.1')),
                ],
            ],
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DELIVERY_DATABASE', 'msdlv'),
            'username' => env('DB_DELIVERY_USER', env('DB_AURORA_USER', 'laravel')),
            'password' => env('DB_DELIVERY_PASS', env('DB_AURORA_PASS', 'laravel')),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'sticky' => true,
            'options' => [
                \PDO::ATTR_PERSISTENT => true
            ]
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'predis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'packk-core' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
