<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AbdmService
{
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl;

    public function __construct()
    {
        $this->clientId = env('ABDM_CLIENT_ID');
        $this->clientSecret = env('ABDM_CLIENT_SECRET');
        $this->baseUrl = env('ABDM_BASE_URL');
    }

    public function getAccessToken($clientId = null, $clientSecret = null, $grantType = 'client_credentials', $baseUrl = null)
    {
        
        // Use provided parameters or fall back to class properties
       $clientId = $clientId ?? $this->clientId;
        $clientSecret = $clientSecret ?? $this->clientSecret;
        $baseUrl = $baseUrl ?? $this->baseUrl;

        try {
            $payload = [
                'clientId' => $clientId,
                'clientSecret' =>  $clientSecret,
                'grantType' => $grantType,
            ];

            // Send the POST request with headers
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'REQUEST-ID' => Str::uuid()->toString(), // Generate a unique request ID
                'TIMESTAMP' => now()->toIso8601String(), // Generate current timestamp in ISO8601 format
                'X-CM-ID' => 'sbx', // Set custom header
            ])->post('https://dev.abdm.gov.in/api/hiecm/gateway/v3/sessions', $payload);

            // Check for a successful response
            if ($response->successful()) {
                // Return the successful response data
                return $response->json();
            } else {
                return response()->json(['error' => 'Request failed', 'message' => $response->body()], $response->status());
            }
        } catch (\Throwable $th) {
            // Log the actual exception message for debugging
            throw new \Exception('An error occurred while requesting the access token: ' . $th->getMessage());
        }
    }

    public function requestOtp($payload)
    {
        try {
            // Get the access token
        $token = $this->getAccessToken();
        $accessToken = $token['accessToken'];
        $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z'); // With milliseconds ('.v' for milliseconds)
        $requestId = Str::uuid()->toString();
        $baseUrlWithSlash = rtrim($this->baseUrl, '/') . '/';
    
        // Send the POST request with headers
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'TIMESTAMP' => $timestamp,
            'REQUEST-ID' => $requestId,
            'Authorization' => "Bearer {$accessToken}",
        ])->post("https://abhasbx.abdm.gov.in/abha/api/v3/enrollment/request/otp", $payload);
        // Handle the response
        if ($response->successful()) {
            return $response->json();
        } else {
            // Log error details for debugging
            return [
                'error' => true,
                'message' => $response->body(),
                'status' => $response->status(),
            ];
        }
        } catch (\Throwable $th) {
            logger($th->getMessage());
        }
        
    }

    public function enrollByAadhaar($payload)
    {
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'TIMESTAMP' => now()->toISOString(), // Current timestamp in UTC
                    'REQUEST-ID' => Str::uuid()->toString(), // Generate a unique request ID
                    'Authorization' => 'Bearer ' . $accessToken,
                ])->post('https://abhasbx.abdm.gov.in/abha/api/v3/enrollment/enrol/byAadhaar', $payload);
        
                // Check for a successful response
                if ($response->successful()) {
                    // Handle successful response
                    return response()->json($response->json());
                } else {
                    // Handle error response
                    return response()->json(['error' => $response->json()], $response->status());
                }
            } catch (\Exception $e) {
                // Handle exception
                return response()->json(['error' => $e->getMessage()], 500);
            }
           
        } catch (\Throwable $th) {
            logger($th->getMessage());
        }
    }

    public function getAccountProfile($xToken){
        // Send the GET request
    try {
        $token = $this->getAccessToken();
        $accessToken = $token['accessToken'];
        $response = Http::withHeaders([
            'X-token' => 'Bearer '.$xToken,
            'REQUEST-ID' =>  Str::uuid()->toString(), // Generate a unique request ID
            'TIMESTAMP' => now()->toISOString(), // Current timestamp in UTC
            'Authorization' => 'Bearer ' . $accessToken,
        ])->get('https://abhasbx.abdm.gov.in/abha/api/v3/profile/account');

        // Check for a successful response
        if ($response->successful()) {
            // Handle successful response
            return response()->json($response->json());
        } else {
            // Handle error response
            return response()->json(['error' => $response->json()], $response->status());
        }
    } catch (\Exception $e) {
        // Handle exception
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }
    // public function createAbhaId($data)
    // {
    //     $token = $this->getAccessToken();
    //     $response = Http::withToken($token)->post($this->baseUrl . 'abha/create', $data);

    //     return $response->json();
    // }

    // public function verifyOtp($otp, $transactionId)
    // {
    //     $token = $this->getAccessToken()['access_token'];

    //     $response = Http::withToken($token)->post($this->baseUrl . '/v1/abha/verifyOtp', [
    //         'otp' => $otp,
    //         'transactionId' => $transactionId,
    //     ]);

    //     return $response->json();
    // }

    // public function getAbhaProfile($abhaId)
    // {
    //     $token = $this->getAccessToken()['access_token'];

    //     $response = Http::withToken($token)->get($this->baseUrl . '/v1/abha/profile/' . $abhaId);

    //     return $response->json();
    // }
}
