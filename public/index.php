<?php

use Dotenv\Dotenv;

session_start();
require '../vendor/autoload.php';
require '../src/routes.php';

$dotenv = Dotenv::createUnsafeImmutable('../', '.env');
$dotenv->load();

$router->run( $router->routes );