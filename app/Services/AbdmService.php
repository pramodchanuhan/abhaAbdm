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
        ])->post($this->baseUrl . "enrollment/request/otp", $payload);
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
                ])->post($this->baseUrl . 'enrollment/enrol/byAadhaar', $payload);
        
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
        ])->get($this->baseUrl . 'profile/account');

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
        public function abhaAddressSuggestions($token)
        {
            try {
                $token = $this->getAccessToken();
                $accessToken = $token['accessToken'];
                $response = Http::withHeaders([
                    'Transaction_Id' => $token,
                    'REQUEST-ID' =>  Str::uuid()->toString(), // Generate a unique request ID
                    'TIMESTAMP' => now()->toISOString(), // Current timestamp in UTC
                    'Authorization' => 'Bearer ' . $accessToken,
                ])->get($this->baseUrl . 'enrollment/enrol/suggestion');
                if ($response->successful()) {
                    // Handle successful response
                    return response()->json($response->json());
                } else {
                    return response()->json(['error' => $response->json()], $response->status());
                }
        }
        catch (\Exception $e) {
            // Handle exception
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function enrollAbhaAddress($data){
        try{
        $url = $this->baseUrl . 'enrollment/enrol/abha-address';
        $token = $this->getAccessToken();
        $accessToken = $token['accessToken'];
        $response = Http::withHeaders([
            'REQUEST-ID' => Str::uuid()->toString(), // You can generate a unique ID as needed
            'TIMESTAMP' => now()->toISOString(), // Current timestamp
            'Authorization' => 'Bearer ' . $accessToken,
        ])->post($url, $data);

        // Handle the response
        if ($response->successful()) {
            return response()->json($response->json(), 200);
        } else {
            return response()->json($response->json(), $response->status());
        }
    }
    catch (\Exception $e) {
        // Handle exception
        return response()->json(['error' => $e->getMessage()], 500);
    }
    }


    //m2
    
 
}
