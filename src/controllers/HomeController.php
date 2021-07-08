<?php
namespace App\Controllers;

use GuzzleHttp\Client;

class HomeController extends controller {

    public function home($request, $response, $args) {
        return $this->view->render($response, 'index.html');
    }

    public function version() {
        echo json_encode([
            "version" => "0.1"
        ]);
    }

}