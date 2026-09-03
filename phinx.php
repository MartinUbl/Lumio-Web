<?php

$host = getenv('MARIADB_HOST') ?: 'mysql';
$database = getenv('MARIADB_DATABASE') ?: 'lumio_db';
$user = getenv('MARIADB_USER') ?: 'root';
$password = getenv('MARIADB_PASSWORD') ?: getenv('MYSQL_ROOT_PASSWORD') ?: '';

return [
    'paths' => [
        'migrations' => __DIR__ . '/db/migrations',
        'seeds' => __DIR__ . '/db/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => getenv('PHINX_ENV') ?: 'default',
        'default' => [
            'adapter' => 'mysql',
            'host' => $host,
            'name' => $database,
            'user' => $user,
            'pass' => $password,
            'port' => (int) (getenv('MARIADB_PORT') ?: 3306),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_czech_ci',
        ],
    ],
    'version_order' => 'creation',
];
