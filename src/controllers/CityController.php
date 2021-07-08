<?php
namespace App\Controllers;

use GuzzleHttp\Client;

class CityController extends controller {

    private $city_info = [];
    private $city_list = [
        1 => 'Tokyo',
        2 => 'Yokohama',
        3 => 'Kyoto',
        4 => 'Osaka',
        5 => 'Sapporo',
        6 => 'Nagoya'
    ];

    public function list($request, $response, $args) {
    
        $body = $response->getBody();
        $body->write(json_encode($this->city_list));

        return $response
        ->withHeader('Content-Type', 'application/json')
        ->withBody($body);
    }

    public function info($request, $response, $args) {
        $city_id = $request->getQueryParams()['city_id'];
        $city_info = [];

        if (!empty($this->city_list[$city_id])) {
            $city_info = $this->get_city_info($this->city_list[$city_id])['city'];
        }

        $timezone = timezone_name_from_abbr("", $city_info['timezone'], 0);
        date_default_timezone_set($timezone);

        $city_info['population'] = number_format ($city_info['population']);
        $city_info['sunrise'] = date('h:i:s A', $city_info['sunrise']);
        $city_info['sunset'] = date('h:i:s A', $city_info['sunset']);


        $body = $response->getBody();
        $body->write(json_encode($city_info));

        return $response
        ->withHeader('Content-Type', 'application/json')
        ->withBody($body);
    }

    public function weather($request, $response, $args) {
        $city_id = $request->getQueryParams()['city_id'];
        $city_info = [];

        if (!empty($this->city_list[$city_id])) {
            $city_info = $this->get_city_info($this->city_list[$city_id])['list'];
        }

        // echo "<PRE>";
        // print_r($city_info);
        // echo "</PRE>";
        // die();

        $weather = [];
        foreach ($city_info as $info) {
            $date = date('M d, Y (D)', $info['dt']);
            $time = date('g:i:s A', $info['dt']);

            $weather[$date][$time] = [
                'main' => $info['main'],
                'weather' => $info['weather'],   
                'clouds' => $info['clouds'],
                'wind' => $info['wind'],         
            ];
        }

        // echo "<PRE>";
        // print_r($weather);
        // echo "</PRE>";
        // die();



        $body = $response->getBody();
        $body->write(json_encode($weather));

        return $response
        ->withHeader('Content-Type', 'application/json')
        ->withBody($body);
    }

    public function venues($request, $response, $args) {
        $lat = $request->getQueryParams()['lat'];
        $lon = $request->getQueryParams()['lon'];

        $url = 'https://api.foursquare.com/v2/venues/search' . 
            '?client_id=' . $this->container->get('settings')['foursquare_client_id'] . 
            '&client_secret=' . $this->container->get('settings')['foursquare_client_secret'] .
            '&v=' . date('Ymd', strtotime('now')) .
            '&ll=' . $lat . ',' . $lon;

        $api_response = $this->makeGETAPICall($url, false, true);

        // echo "<PRE>";
        // print_r($api_response);
        // echo "</PRE>";

        $body = $response->getBody();
        $body->write(json_encode($api_response));

        return $response
        ->withHeader('Content-Type', 'application/json')
        ->withBody($body);
    }


    private function get_city_info($city) {
        if (empty($city)) {
            return [];
        }
        
        $url = 'https://api.openweathermap.org/data/2.5/forecast?q=' . $city . '&appid=' . $this->container->get('settings')['openweathermap_api_key'];
        $api_response = $this->makeGETAPICall($url, false, true);
        
        if (empty($api_response)) {
            return [];
        }
        // echo "<PRE>";
        // print_r($api_response);
        // echo "</PRE>";

        // $this->city_info = $api_response;

        return $api_response;
    }
}