#!/usr/bin/env php
<?php

chdir(dirname(__DIR__)); // set directory to root
require __DIR__ . '/../vendor/autoload.php';


// convert all the command line arguments into a URL
$argv = $GLOBALS['argv'];
array_shift($GLOBALS['argv']);
$pathInfo = '/' . implode('/', $argv);


$config = require __DIR__ . '/../app/config/config.default.php';

if (file_exists(__DIR__ . '/../app/config/config.php')) {
    $local_config = include __DIR__ . '/../app/config/config.php';
    $config = array_replace_recursive($config, $local_config);
}


// Create our app instance
$app = new Slim\Slim($config);

// Set up the environment so that Slim can route
$app->environment = Slim\Environment::mock([
    'PATH_INFO'   => $pathInfo
]);


// CLI-compatible not found error handler
$app->notFound(function () use ($app) {
    $url = $app->environment['PATH_INFO'];
    echo "Error: Cannot route to $url";
    $app->stop();
});

// Format errors for CLI
$app->error(function (\Exception $e) use ($app) {
    echo $e;
    $app->stop();
});

// Set up dependencies
require __DIR__ . '/../app/dependencies.php';

// routes - as per normal - no HTML though!
$app->get('/hello/:name', function ($name) {
    echo "Hello, $name\n";
});

$dir = getcwd();
chdir(__DIR__ . '/../schemas');
$schemas = glob('*');
foreach ($schemas as $schema) {
    if (file_exists($schema . '/routes.php')) {
        $app->group('/' . $schema, function() use ($app, $schema) {
            include $schema . '/routes.php';
        });
    }
}
chdir($dir);

// run!
$app->run();
