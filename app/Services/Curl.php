<?php

namespace App\Services;

class Curl
{
    private $curlHandler;
    protected $baseUrl;
    protected $user;
    protected $pass;

    public function __construct($baseUrl = '/', $user = null, $pass = null)
    {
        $this->baseUrl = $baseUrl;
        $this->user = $user;
        $this->pass = $pass;
    }

    public function execute($url, $payload = null, $method = 'POST')
    {
        $this->curlHandler = curl_init($this->baseUrl . $url);

        curl_setopt($this->curlHandler, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($this->curlHandler, CURLOPT_RETURNTRANSFER, true);

        if ($payload) {
            curl_setopt($this->curlHandler, CURLOPT_POSTFIELDS, $payload);
        }

        $this->addAuth();

        return curl_exec($this->curlHandler);
    }

    public function get($url)
    {
        return $this->execute($url, null, 'GET');
    }

    public function post($url, $payload)
    {
        return $this->execute($url, $payload);
    }

    public function getLastStatusCode()
    {
        return curl_getinfo($this->curlHandler, CURLINFO_HTTP_CODE);
    }

    public function getLastTransactionTime()
    {
        return (int) ceil(curl_getinfo($this->curlHandler, CURLINFO_TOTAL_TIME));
    }

    private function addAuth()
    {
        if ($this->user && $this->pass) {
            curl_setopt($this->curlHandler, CURLOPT_USERPWD, sprintf('%s:%s', $this->user, $this->pass));
        }
    }
}
