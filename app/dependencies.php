<?php

// database
$db = (object) $app->config('db');
$options = [PDO::ATTR_CASE => PDO::CASE_LOWER];
dibi::connect([
    'driver' => 'pdo',
    'dsn'=> $db->dsn,
    'user' => $db->username,
    'pass' => $db->password,
    'formatDate' => "'Y-m-d'",
    'formatDateTime' => "'Y-m-d H-i-s'",
    'result' => [
        'formatDate' => "Y-m-d",
        'formatDateTime' => "Y-m-d H-i-s"
    ],
    'options' => $options,
    'resource' => $db->platform === 'oracle'
        ? new URD\lib\Oci8($db->dsn, $db->username, $db->password, $options)
        : null,
], $db->database);
