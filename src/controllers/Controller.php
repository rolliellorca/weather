<?php
namespace App\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Stream\Stream;

class Controller
{
    protected $container;
    public $client;
    public $debug = true;
    public $message;
    public $memcache;
    public $settings;
    public $ip_address;

    public function __construct($container)
    {
        $this->container = $container;
        $this->settings = $this->container->get('settings');

        $this->ip_address = $this->get_ip();
        // // $this->ip_address = '98.114.220.90';

        $this->client = new Client([
            // Base URI is used with relative requests
            'base_uri' => $this->settings['API_URL'],
            // You can set any number of default request options.
            'timeout'  => 180.0,
            'headers' => [
                'Accept' => 'application/json; charset=utf-8',
                // 'IVERSE_API_KEY' => $this->settings['PUBLIC_API_KEY'],
                // 'IVERSE_CLIENT_IP' => $this->ip_address,
                // 'IVERSE_VERIFY_CODE' => sha1($this->ip_address . $this->settings['PUBLIC_API_SECRET'])
            ]
        ]);

    }

    public function __get($property)
    {
        if ($this->container->{$property}) {
            return $this->container->{$property};
        }
    }

    //////////////////////////////////////////////////////////////////////////////
    // Global Functions
    //////////////////////////////////////////////////////////////////////////////
    public function get_ip()
    {
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $real_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } elseif (isset($_SERVER["HTTP_CLIENT_IP"])) {
            $real_ip = $_SERVER["HTTP_CLIENT_IP"];
        } else {
            $real_ip = $_SERVER["REMOTE_ADDR"];
        }

        return $real_ip;
    }

    public function updateSessionDocument($jwt)
    {
        $url = '/temp_documents/' . $jwt;
        $_SESSION['document'] = $this->makeGETAPICall($url, false);

        return $_SESSION['document'];
    }

    public function calculateCost($jwt, $update_document=false, $include_coupon=false)
    {
        $document = $this->updateSessionDocument($jwt);

        if ($document) {
            $total_pages = 0;
            if (isset($document['temp_new_product_files'][0])) {
                $total_pages = $document['temp_new_product_files'][0]['page_count'];
            }

            $page_cost = $document['temp_new_product'][0]['page_cost'];
            $languages_count = sizeof($document['temp_new_product_target_languages']);

            if ($total_pages == 0 || $total_pages == 1 || $languages_count == 0) {
                $total_price = 0;
            } else {
                $total_price = ($total_pages * $languages_count * $page_cost) - ($page_cost * 2);
            }

            // TODO need to refactor using the SESSION coupon value here
            if ($include_coupon) {
                if (isset($_SESSION['coupon'])) {
                    if ($_SESSION['coupon']['free_purchase'] == 1) {
                        $total_price = 0;
                    } elseif ($_SESSION['coupon']['cost_off'] > 0) {
                        $total_price -= $_SESSION['coupon']['cost_off'];
                    }
                }
            }

            if ($total_price < 0 && !$_SESSION['iverse_token']) {
                $total_price = 0;
            }

            return $total_price;
        }

        return false;
    }

    //////////////////////////////////////////////////////////////////////////////
    // SESSIONS
    //////////////////////////////////////////////////////////////////////////////
    public function clearSession()
    {
        unset($_SESSION['email']);
        unset($_SESSION['jwt']);

        if (!empty($_SESSION['iverse_token'])) {
            unset($_SESSION['library_user_id']);
            unset($_SESSION['auth_email']);
            unset($_SESSION['auth_library_id']);
            unset($_SESSION['iverse_token']);
        }
    }

    public function clearCoupon()
    {
        unset($_SESSION['coupon']);
    }

    //////////////////////////////////////////////////////////////////////////////
    // API FUNCTIONALITY
    //////////////////////////////////////////////////////////////////////////////
    public function makeGETAPICall($url=null, $cache=true, $full_response=false)
    {
        if (!$url) return false;

        $cache_result = $key = null;

        if ($cache) {
            $key = md5($url); // Memcached unique keyword
            $cache_result = $this->memcache->get($key); // Memcached object
        }

        if ($cache_result) {
            return $cache_result;
        } else {
            if ($cache) { // only need the error if we want it
                // error_log(print_r('Cache miss for '.$url, true));
            }
            // Make the API call
            $api_response = $this->client->request(
                'GET',
                $url,
                [
                'http_errors' => false
                ]
            );

            if ($api_response->getStatusCode() == 200) { // success
                // $body = json_decode($api_response->getBody(), true);
                $body_raw = '';
                while(!$api_response->getBody()->eof()) {
                    $body_raw .= $api_response->getBody()->read(1024);
                } // Because the login API call was longer than 2M it needs to be read as a stream
                $body = json_decode($body_raw, true);

                if ($full_response) {
                    if ($body === null) { // This means there is an error in the JSON
                        $body = $body_raw;
                    }
                    return $body;
                }

                if ($body['status'] == 'ok') {
                    if ($cache) {
                        $this->memcache->set($key, $body['results'], $this->settings['CACHE_TIME']);
                    }

                    return $body['results'];
                }
            } else {
                error_log(print_r('API error with status code ' . $api_response->getStatusCode() . ' from URL ' . $url, true));
                error_log(print_r(json_decode($api_response->getBody(), true), true));
            }
        }

        return false;
    }
    public function makeVanillaGETAPICall($url, $cache=true)
    {
        if (!$url) return false;

        $client = new Client();
        $cache_result = null;

        if ($cache) {
            $key = md5($url); // Memcached unique keyword
            $cache_result = $this->memcache->get($key); // Memcached object
        }

        if ($cache_result) {
            return $cache_result;
        } else {
            if ($cache) { // only need the error if we want it
                // error_log(print_r('Cache miss for '.$url, true));
            }

            // Make the API call
            $api_response = $client->request(
                'GET',
                $url,
                [
                    'http_errors' => false
                ]
            );

            if ($api_response->getStatusCode() == 200) { // success
                $body = '';
                while(!$api_response->getBody()->eof()) {
                    $body .= $api_response->getBody()->read(1024);
                } // Because the login API call was longer than 2M it needs to be read as a stream

                if ($cache) {
                    $this->memcache->set($key, $body['results'], $this->settings['CACHE_TIME']);
                }

                return $body;
            } else {
                error_log(print_r('API error with status code ' . $api_response->getStatusCode() . ' from URL ' . $url, true));
                error_log(print_r(json_decode($api_response->getBody(), true), true));
            }
        }

        return false;
    }

    public function makePOSTAPICall($url=null, $params=null, $cache=true, $full_response=false)
    {
        if (!$url) return false;

        $cache_result = null;

        if ($cache) {
            $key_params='';
            foreach ($params as $key => $value) {
                $key_params .= "$key:$value/";
            }

            $key = md5($url.$key_params); // Memcached unique keyword
            $cache_result = $this->memcache->get($key); // Memcached object
        }

        if ($cache_result) {
            return $cache_result;
        } else {
            if ($cache) { // only need the error if we want it
                // error_log(print_r('Cache miss for '.$url.$key_params, true));
            }

            // Make the API call
            $api_response = $this->client->request(
                'POST',
                $url,
                [
                    'form_params' => $params,
                    'http_errors' => false
                ]
            );

            if ($api_response->getStatusCode() == 200) { // success
                $body_raw = '';
                while(!$api_response->getBody()->eof()) {
                    $body_raw .= $api_response->getBody()->read(1024);
                } // Because the login API call was longer than 2M it needs to be read as a stream

                $body = json_decode($body_raw, true);

                if ($full_response) {
                    if ($body === null) { // This means there is an error in the JSON
                        $body = $body_raw;
                    }

                    return $body;
                }

                if (isset($body['status']) && $body['status'] == 'ok') {
                    if ($cache) {
                        $this->memcache->set($key, $body['results'], $this->settings['CACHE_TIME']);
                    }

                    return $body['results'];
                }
            } else {
                error_log(print_r('API error with status code ' . $api_response->getStatusCode() . ' from URL ' . $url, true));
                error_log(print_r(json_decode($api_response->getBody(), true), true));
            }
        }

        return false;
    }
    public function makeVanillaPOSTAPICall($url=null, $params=null, $cache=true)
    {
        if (!$url) return false;

        $client = new Client([
            // You can set any number of default request options.
            'timeout'  => 180.0,
            'headers' => [
                'Accept' => 'application/json; charset=utf-8',
                'IVERSE_API_KEY' => $this->settings['PUBLIC_API_KEY'],
                'IVERSE_CLIENT_IP' => $this->ip_address,
                'IVERSE_VERIFY_CODE' => sha1($this->ip_address . $this->settings['PUBLIC_API_SECRET'])
            ]
        ]);
        $cache_result = null;

        if ($cache) {
            $key_params='';
            foreach ($params as $key => $value) {
                $key_params .= "$key:$value/";
            }

            $key = md5($url.$key_params); // Memcached unique keyword
            $cache_result = $this->memcache->get($key); // Memcached object
        }

        if ($cache_result) {
            return $cache_result;
        } else {
            if ($cache) { // only need the error if we want it
                // error_log(print_r('Cache miss for '.$url.$key_params, true));
            }

            // Make the API call
            $api_response = $client->request(
                'POST',
                $url,
                [
                    'form_params' => $params,
                    'http_errors' => false
                ]
            );

            if ($api_response->getStatusCode() == 200) { // success
                $body = '';
                while(!$api_response->getBody()->eof()) {
                    $body .= $api_response->getBody()->read(1024);
                } // Because the login API call was longer than 2M it needs to be read as a stream

                if ($cache) {
                    $this->memcache->set($key, $body['results'], $this->settings['CACHE_TIME']);
                }

                return $body;
            } else {
                error_log(print_r('API error with status code ' . $api_response->getStatusCode() . ' from URL ' . $url, true));
                error_log(print_r(json_decode($api_response->getBody(), true), true));
            }
        }

        return false;
    }

    public function makePOSTAPICallWithFile($url=null, $params=null, $file=null, $cache=true, $full_response=false)
    {
        if (!$url) return false;
            $cache_result = null;

        if ($cache) {
            $key_params='';
            foreach ($params as $key => $value) {
                $key_params .= "$key:$value/";
            }

            $key = md5($url.$key_params); // Memcached unique keyword
            $cache_result = $this->memcache->get($key); // Memcached object
        }

        if ($cache_result) {
            return $cache_result;
        } else {
            if ($cache) { // only need the error if we want it
                // error_log(print_r('Cache miss for '.$url.$key_params, true));
            }

            $send_params = [];

            foreach ($params as $name => $content) {
                $send_params[] = [
                    'name' => $name,
                    'contents' => $content
                ];
            }

            $send_params[] = [
                'name'     => 'file',
                'contents' => fopen($file['tmp_name'], 'r'),
                'filename' => $file['name'],
            ];

            // Make the API call
            $api_response = $this->client->request(
                'POST',
                $url,
                [
                    'multipart' => $send_params,
                    'http_errors' => false
                ]
            );

            if ($api_response->getStatusCode() == 200) { // success
                $body_raw = '';
                while(!$api_response->getBody()->eof()) {
                    $body_raw .= $api_response->getBody()->read(1024);
                } // Because the login API call was longer than 2M it needs to be read as a stream

                $body = json_decode($body_raw, true);

                if ($full_response) {
                    if ($body === null) { // This means there is an error in the JSON
                        $body = $body_raw;
                    }

                    return $body;
                }

                if (isset($body['status']) && $body['status'] == 'ok') {
                    if ($cache) {
                        $this->memcache->set($key, $body['results'], $this->settings['CACHE_TIME']);
                    }

                    return $body['results'];
                }
            } else {
                error_log(print_r('API error with status code ' . $api_response->getStatusCode() . ' from URL ' . $url, true));
                error_log(print_r(json_decode($api_response->getBody(), true), true));
            }
        }

        return false;
    }

////////////////////////////////////////////////////////////////////////////////
}
