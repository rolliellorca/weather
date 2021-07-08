<?php

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once '../vendor/autoload.php';

$settings = require_once __DIR__ . '/settings.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use GuzzleHttp\Client;


$app = new \Slim\App($settings);

// Set up dependencies
$dependencies = require __DIR__ . '/dependencies.php';
$dependencies($app);

// // Register middleware
// foreach (glob(__DIR__.'/middleware/*.php') as $filename) {
//   require $filename;
// }
// $middleware = require __DIR__ . '/middleware.php';
// $middleware($app);

// Register routes
$routes = require_once __DIR__ . '/routes.php';
$routes($app);

$app->run();
