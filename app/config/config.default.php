<?php

date_default_timezone_set('Europe/Oslo');

return [
    'debug' => false,
    'single_sign_on' => false,
    'ldap' => [
        'server' => '',
        'user_prefix' => ''
    ],
    'db' => [
        'dsn' => 'mysql:host=127.0.0.1;dbname=urd;charset=utf8',
        'database' => 'urd',
        'username' => 'urd',
        'password' => 'urd',
        'platform' => 'mysql',
    ],
    // 'session_save_path' => '/tmp',
    'session_timeout' => 180,
    'mail' => [
        'host' => '',
        'port' => '',
        'username' => '',
        'password' => '',
        'from_address' => '',
        'from_name' => '',
        'send_errors' => false,
        'error_recipients' => ['<address>' => '<name>'],
    ],
    // Defualt organization for user logged in via ldap
    // and that are not registered in the database
    'default_roles' => [],
    'fileroot' => '',
];
