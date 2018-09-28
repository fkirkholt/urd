<?php

use \Phpmig\Adapter;
use \URD\models\Database as DB;
use \Pimple\Container;
use \URD\models\Schema;

$container = new Container();

$container['db'] = function($container) {
    $dbh = DB::get()->conn->getDriver()->getResource();
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $dbh;
};

$container['phpmig.sets'] = function($container) {

    $sets = [];

    chdir ('schemas');
    $schemas = glob('*', GLOB_ONLYDIR);
    foreach ($schemas as $schema_name) {
        $schema = new Schema($schema_name);
        $db_name = $schema->get_db_name();
        $db = DB::get($db_name);
        $dbh = $db->conn->getDriver()->getResource();
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($db->platform === 'oracle') {
            $adapter = new Adapter\PDO\SqlOci($dbh, 'migration');
        } else {
            $adapter = new Adapter\PDO\Sql($dbh, 'migration');
        }

        $sets[$schema_name] = [
            'adapter' => $adapter,
            'migrations_path' => __DIR__ . '/schemas/' . $schema_name . '/migrations'
        ];
    }

    $sets[''] = $sets['urd'];

    return $sets;
};

return $container;
