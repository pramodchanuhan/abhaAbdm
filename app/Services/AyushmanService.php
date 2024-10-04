<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;

class AyushmanService
{
    protected $baseUrl;
    protected $apiKey;
    protected $secret;

    public function __construct()
    {
        $this->baseUrl = config('services.ayushman.api_url');
        $this->apiKey = config('services.ayushman.api_key');
        $this->secret = config('services.ayushman.secret');
    }

    public function verifyCard($abhaNumber)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/verifyCard', [
            'abha_number' => $abhaNumber,
        ]);

        return $response->json();
    }

    private function getAccessToken()
    {
        $response = Http::post($this->baseUrl . '/auth/token', [
            'api_key' => $this->apiKey,
            'secret' => $this->secret,
        ]);
        return $response->json()['access_token'];
    }
}
