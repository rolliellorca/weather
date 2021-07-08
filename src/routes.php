<?php

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

return function (App $app) {
    $container = $app->getContainer();

    $app->get('/', 'HomeController:home')->setName('home');

    $app->get('/city/list', 'CityController:list')->setName('city.list');
    $app->get('/city/info', 'CityController:info')->setName('city.info');
    $app->get('/city/weather', 'CityController:weather')->setName('city.weather');
    $app->get('/city/venues', 'CityController:venues')->setName('city.venues');

    $app->get('/version', 'HomeController:version')->setName('version');
    
};