<?php

use Slim\App;

return function (App $app) {
    $container = $app->getContainer();

    $container['view'] = function($container) {
        $view = new \Slim\Views\Twig('views', [
            'cache' => false
        ]);
    
        $view->addExtension(new \Slim\Views\TwigExtension(
            $container->router,
            $container->request->getUri(),
        ));
        
        return $view;
    };

    $container['auth'] = function () {
        return new \App\Auth\Auth;
    };

    include_once __dir__.'/controllers/Controller.php';
    foreach (glob(__dir__ . '/controllers/*.php') as $filename) {
        $path = pathinfo($filename);

        if ($path['filename'] == 'controller') {
            continue;
        }
        
        include_once $filename;
        $container[$path['filename']] = function ($container) use ($path) {
            $class = '\App\Controllers\\' . $path['filename'];
            return new $class($container);
        };
    }

};