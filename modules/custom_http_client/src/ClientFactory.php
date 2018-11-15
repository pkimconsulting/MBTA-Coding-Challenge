<?php
namespace Drupal\custom_http_client;

use GuzzleHttp\Client;

class ClientFactory {

    /**
     * Return a configured Client object.
     */
    public function get() {
        $config = [
            'base_uri' => 'https://example.com',
        ];

        $client = new Client($config);

        return $client;
    }
}
