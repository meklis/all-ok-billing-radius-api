<?php
return [
    'BASE' => [
        'logDir' => '/var/log/radius-api',
    ],
    'DATABASE' => [
        'db' => [
            'host' => Env()['MYSQL_HOST'],
            'login' => Env()['MYSQL_USER'],
            'pass' => Env()['MYSQL_PASSWORD'],
            'use' => Env()['MYSQL_DATABASE'],
        ]
    ],
    'API_TRUSTED_IPS' => require  __DIR__ . '/api.trusted_ips.php',
    'RADIUS' => extraConf('radius'),
];
