<?php

declare(strict_types=1);

namespace App\Clients;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WebMagicClient
{
    protected PendingRequest $client;
    protected CookieJar $cookieJar;

    public function __construct()
    {
        $this->cookieJar = new CookieJar();

        $this->client = Http::timeout(30)
            ->withOptions(['cookies' => $this->cookieJar])
            ->retry(2, 100);
    }

    public static function get(): PendingRequest
    {
        return (new static())->getClient();
    }

    public function getClient(): PendingRequest
    {
        return $this->client;
    }

    public function getCookieJar(): CookieJar
    {
        return $this->cookieJar;
    }
}
