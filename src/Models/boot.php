<?php

use Illuminate\Database\Capsule\Manager as Capsule;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

var_dump($_ENV);

$capsule = new Capsule;

$capsule->addConnection([
    'driver' => 'mysql',
    'host' => $_ENV['DATABASE_HOST'],
    'database' => $_ENV['DATABASE_NAME'],
    'username' => $_ENV['DATABASE_USER'],
    'password' => $_ENV['DATABASE_PASS'],
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
]);

// Make this Capsule instance available globally via static methods... (optional)
$capsule->setAsGlobal();

// Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
$capsule->bootEloquent();
