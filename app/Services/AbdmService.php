<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use Exception;
use Illuminate\Container\Attributes\Log;
use PhpParser\Node\Stmt\Catch_;

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
                    return $response->json();
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

    public function getAccountProfile($xToken)
    {
        // Send the GET request
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $response = Http::withHeaders([
                'X-token' => 'Bearer ' . $xToken,
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

    public function getProfileQrCode($xToken)
    {
        // Send the GET request
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $response = Http::withHeaders([
                'X-token' => 'Bearer ' . $xToken,
                'REQUEST-ID' =>  Str::uuid()->toString(), // Generate a unique request ID
                'TIMESTAMP' => now()->toISOString(), // Current timestamp in UTC
                'Authorization' => 'Bearer ' . $accessToken,
            ])->get($this->baseUrl . 'profile/account/qrCode');
            if ($response->successful()) {
                return $response->json();
            } else {
                return response()->json(['error' => $response->json()], $response->status());
            }
        } catch (\Exception $e) {
            // Handle exception
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function abhaAddressSuggestions($transactionId)
    {
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z'); // With milliseconds ('.v' for milliseconds)
            $requestId = Str::uuid()->toString();
            $response = Http::withHeaders([
                'Transaction_Id' => $transactionId,
                'REQUEST-ID' => $requestId, // Generate a unique request ID
                'TIMESTAMP' => $timestamp, // Current timestamp in UTC
                'Authorization' => 'Bearer ' . $accessToken,
            ])->get($this->baseUrl . 'enrollment/enrol/suggestion');
            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json(['error' => $response->json()], $response->status());
            }
        } catch (\Exception $e) {
            // Handle exception
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function enrollAbhaAddress($data)
    {
        try {
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
        } catch (\Exception $e) {
            // Handle exception
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getAbhaCard($xToken)
    {
        // Send the GET request
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $response = Http::withHeaders([
                'X-token' => 'Bearer ' . $xToken,
                'REQUEST-ID' =>  Str::uuid()->toString(), // Generate a unique request ID
                'TIMESTAMP' => now()->toISOString(), // Current timestamp in UTC
                'Authorization' => 'Bearer ' . $accessToken,
            ])->get($this->baseUrl . 'profile/account/abha-card');

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

    public function enrollByDLSendOtp($mobileNumber)
    {
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z'); // With milliseconds ('.v' for milliseconds)
            $requestId = Str::uuid()->toString();
            $response = Http::withHeaders([
                'REQUEST-ID' => $requestId,  // Unique request ID
                'TIMESTAMP' => $timestamp,  // Generates the current timestamp in ISO 8601 format
                'Authorization' => 'Bearer ' . $accessToken,  // Authorization header with Bearer token
            ])->post($this->baseUrl . 'enrollment/request/otp', [
                'scope' => ['abha-enrol', 'mobile-verify', 'dl-flow'],  // Array of scopes
                'loginHint' => 'mobile',  // Login hint
                'loginId' => $mobileNumber,  // Encrypted mobile number
                'otpSystem' => 'abdm'  // OTP system to use
            ]);
            if ($response->successful()) {
                return $response->json();
            } else {
                // Handle error
                return response()->json(['error' => 'OTP request failed'], 400);
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function enrollByDLVerifyOtp($encryptedOtp, $enrollByDLSendOtpTxnId)
    {
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z'); // With milliseconds ('.v' for milliseconds)
            $requestId = Str::uuid()->toString();
            // Define the API endpoint
            $url = $this->baseUrl . 'enrollment/auth/byAbdm';

            // Define the request headers
            $headers = [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
                'REQUEST-ID' =>  $requestId,  // Generates a UUID for request ID
                'TIMESTAMP' => $timestamp,  // Generate timestamp in required format
            ];

            // Define the request payload
            $payload = [
                "scope" => [
                    "abha-enrol",
                    "mobile-verify",
                    "dl-flow"
                ],
                "authData" => [
                    "authMethods" => [
                        "otp"
                    ],
                    "otp" => [
                        "timeStamp" => now()->format('Y-m-d H:i:s.v'),  // Current timestamp
                        "txnId" => $enrollByDLSendOtpTxnId,
                        "otpValue" => $encryptedOtp  // Use the encrypted OTP here
                    ]
                ]
            ];

            // Send the POST request to the ABDM API
            $response = Http::withHeaders($headers)->post($url, $payload);

            // Handle the response (assuming it's JSON)
            if ($response->successful()) {
                return $response->json();  // Return the response as JSON
            } else {
                return response()->json(['error' => 'Authentication failed', 'details' => $response->body()], $response->status());
            }
        } catch (\Exception $e) {
            // Log the exception and return an error response
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function enrollbyDL($payload)
    {
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z'); // With milliseconds ('.v' for milliseconds)
            $requestId = Str::uuid()->toString();
            $url = $this->baseUrl . 'enrollment/enrol/byDocument';

            // Define the request headers
            $headers = [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
                'REQUEST-ID' => $requestId,  // Generates a UUID for request ID
                'TIMESTAMP' => $timestamp,  // Generate timestamp in required format
            ];

            // Define the request payload


            // Send the POST request to the ABDM API
            $response = Http::withHeaders($headers)->post($url, $payload);

            // Handle the response (assuming it's JSON)
            if ($response->successful()) {
                return $response->json();  // Return the response as JSON
            } else {
                return response()->json(['error' => 'Enrollment failed', 'details' => $response->body()], $response->status());
            }
        } catch (\Exception $e) {
            // Log the exception and return an error response
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function abhaVerificationSendOtp($encryptedAbhaNumber)
    {
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z'); // With milliseconds
            $requestId = Str::uuid()->toString();
            $url = $this->baseUrl . "profile/login/request/otp";

            // Prepare the request body
            $requestBody = [
                "scope" => [
                    "abha-login",
                    "aadhaar-verify"
                ],
                "loginHint" => "abha-number",
                "loginId" => $encryptedAbhaNumber,
                "otpSystem" => "aadhaar"
            ];

            // Log the request body to troubleshoot
            logger()->info('Sending OTP Request Body:', $requestBody);

            // Send the POST request
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'REQUEST-ID'    => $requestId,
                'TIMESTAMP'     => $timestamp,
                'Content-Type'  => 'application/json'
            ])->post($url, $requestBody);

            // Check for a successful response
            if ($response->successful()) {
                return $response->json();
            } else {
                // Log the response for debugging
                logger()->error('OTP Request Failed:', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new Exception("Failed to request OTP. Status: " . $response->status());
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function abhaVerificationVerifyOtp($encryptedOtp, $txnId)
    {
        try {
            // Validate the incoming request

            // Prepare the API request
            $token = $this->getAccessToken(); // Obtain the access token
            $accessToken = $token['accessToken'];
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z'); // Current timestamp
            $requestId = Str::uuid()->toString(); // Generate a unique request ID

            $url = $this->baseUrl . "profile/login/verify"; // API endpoint

            // Prepare the request body
            $requestBody = [
                "scope" => [
                    "abha-login",
                    "aadhaar-verify"
                ],
                "authData" => [
                    "authMethods" => [
                        "otp"
                    ],
                    "otp" => [
                        "txnId" => $txnId,
                        "otpValue" => $encryptedOtp,
                    ]
                ]
            ];

            // Send the POST request
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'REQUEST-ID' => $requestId,
                'TIMESTAMP' => $timestamp,
                'Content-Type' => 'application/json',
            ])->post($url, $requestBody);
            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                throw new Exception("Failed to verify ABHA login. Status: " . $response->status());
            }
        } catch (\Exception $e) {
            logger($e->getMessage()); // Log the error
            return response()->json(['error' => $e->getMessage()], 500); // Return error response
        }
    }

    public function abhaVerificationByMobileNumberSendOtp($encryptedMobileNumber)
    {
        try {
            // Prepare the API request
            $token = $this->getAccessToken(); // Obtain the access token
            $accessToken = $token['accessToken'];
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z'); // Current timestamp
            $requestId = Str::uuid()->toString(); // Generate a unique request ID
            $url = $this->baseUrl . "profile/login/request/otp"; // API endpoint
            // Prepare the request body
            $requestBody = [
                "scope" => [
                    "abha-login",
                    "mobile-verify"
                ],
                "loginHint" => "mobile",
                "loginId" => $encryptedMobileNumber,
                "otpSystem" => "abdm"
            ];

            // Send the POST request
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'REQUEST-ID' => $requestId,
                'TIMESTAMP' => $timestamp,
                'Content-Type' => 'application/json',
            ])->post($url, $requestBody);

            // Check for a successful response
            if ($response->successful()) {
                return $response->json(); // Return the response as JSON
            } else {
                throw new Exception("Failed to request OTP. Status: " . $response->status());
            }
        } catch (\Exception $e) {
            logger($e->getMessage()); // Log the error
            return response()->json(['error' => $e->getMessage()], 500); // Return error response
        }
    }

    public function abhaVerificationByMobileNumberVerifyOtp($encryptedOtp, $txnId)
    {
        try {
            // Prepare the API request
            $token = $this->getAccessToken(); // Obtain the access token
            $accessToken = $token['accessToken'];
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z'); // Current timestamp
            $requestId = Str::uuid()->toString(); // Generate a unique request ID

            $url = $this->baseUrl . "profile/login/verify"; // API endpoint

            // Prepare the request body
            $requestBody = [
                "scope" => [
                    "abha-login",
                    "mobile-verify"
                ],
                "authData" => [
                    "authMethods" => [
                        "otp"
                    ],
                    "otp" => [
                        "txnId" => $txnId,
                        "otpValue" => $encryptedOtp, // Encrypted OTP
                    ]
                ]
            ];

            // Send the POST request
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'REQUEST-ID' => $requestId,
                'TIMESTAMP' => $timestamp,
                'Content-Type' => 'application/json',
            ])->post($url, $requestBody);

            // Check for a successful response
            if ($response->successful()) {
                return $response->json(); // Return the response as JSON
            } else {
                throw new Exception("Failed to verify OTP. Status: " . $response->status());
            }
        } catch (\Exception $e) {
            logger($e->getMessage()); // Log the error
            return response()->json(['error' => $e->getMessage()], 500); // Return error response
        }
    }

    public function verifyUser($abhaNumber, $txnId, $jwtToken)
    {
        try {

            // Prepare the API request
            $token = $this->getAccessToken(); // Obtain the access token
            $accessToken = $token['accessToken'];
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z'); // Current timestamp
            $requestId = Str::uuid()->toString(); // Generate a unique request ID

            $url = $this->baseUrl . "profile/login/verify/user"; // API endpoint

            // Prepare the request body
            $requestBody = [
                "ABHANumber" => $abhaNumber,
                "txnId" => $txnId,
            ];

            // Send the POST request
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'T-token' => 'Bearer ' . $jwtToken,
                'REQUEST-ID' => $requestId,
                'TIMESTAMP' => $timestamp,
                'Content-Type' => 'application/json',
            ])->post($url, $requestBody);

            // Check for a successful response
            if ($response->successful()) {
                return $response->json(); // // Return the response as JSON
            } else {
                throw new Exception("Failed to verify user. Status: " . $response->status());
            }
        } catch (\Exception $e) {
            logger($e->getMessage()); // Log the error
            return response()->json(['error' => $e->getMessage()], 500); // Return error response
        }
    }


    public function abhaVerificationByAaadhaarNumberSendOtp($encryptedAadhaarNumber)
    {
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z');
            $requestId = Str::uuid()->toString(); // Generate a unique request ID
            $response = Http::withHeaders([
                'REQUEST-ID' => $requestId, // Generate unique IDs or use UUID
                'TIMESTAMP' => $timestamp, // Generate current timestamp
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . 'profile/login/request/otp', [
                'scope' => ['abha-login', 'aadhaar-verify'],
                'loginHint' => 'aadhaar',
                'loginId' => $encryptedAadhaarNumber,
                'otpSystem' => 'aadhaar'
            ]);
            // Check the response status and handle accordingly
            if ($response->successful()) {
                // Handle successful response
                return $response->json(); //
            } else {
                // Handle error response
                return response()->json(['error' => $response->json()], $response->status());
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500); // Return error response
        }
    }

    public function abhaVerificationByAaadhaarNumberVerifyOtp($txnId, $encryptedOtp)
    {
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z');
            $requestId = Str::uuid()->toString();
            $response = Http::withHeaders([
                'REQUEST-ID' => $requestId, // Generate unique ID
                'TIMESTAMP' => $timestamp, // Current timestamp in ISO format
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . 'profile/login/verify', [
                'scope' => ['abha-login', 'aadhaar-verify'],
                'authData' => [
                    'authMethods' => ['otp'],
                    'otp' => [
                        'txnId' => $txnId,
                        'otpValue' => $encryptedOtp,
                    ],
                ]
            ]);

            // Check for a successful response
            if ($response->successful()) {
                return $response->json();
            } else {
                // Return error response if the request failed
                return response()->json(['error' => $response->json()], $response->status());
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function abhaSearchByMobileNumber($encryptedMobilenumber)
    {
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z');
            $requestId = Str::uuid()->toString();  // Replace with the actual token

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
                'REQUEST-ID' => $requestId, // You can generate this dynamically
                'TIMESTAMP' => $timestamp,
            ])->post($this->baseUrl . 'profile/account/abha/search', [
                'scope' => ['search-abha'],
                'mobile' => $encryptedMobilenumber,
            ]);
            if ($response->successful()) {
                return $response->json();
            } else {
                // Return error response if the request failed
                return response()->json(['error' => $response->json()], $response->status());
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function abhaSearchByAbhaAddress($abhaAddress)
    {
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z');
            $requestId = Str::uuid()->toString();
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
                'REQUEST-ID' => $requestId, // You can generate this dynamically
                'TIMESTAMP' =>  $timestamp,  // Current timestamp in ISO 8601 format
            ])->post($this->baseUrl . 'phr/web/login/abha/search', [
                'abhaAddress' => $abhaAddress,
            ]);
            if ($response->successful()) {
                return $response->json();
            } else {
                // Return error response if the request failed
                return response()->json(['error' => $response->json()], $response->status());
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function abhaSearchByAbhaAddressSendOtp($encryptedAbhaAddress)
    {
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z');
            $requestId = Str::uuid()->toString();

            $payload = [
                "scope" => [
                    "abha-address-login",
                    "mobile-verify"
                ],
                "loginHint" => "abha-address",
                "loginId" => $encryptedAbhaAddress,
                "otpSystem" => "abdm"
            ];

            // Send POST request to ABDM API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'REQUEST-ID'    =>  $requestId, // Generate a unique request ID
                'TIMESTAMP'     => $timestamp,  // Add current timestamp
                'Content-Type'  => 'application/json',
            ])->post('https://abhasbx.abdm.gov.in/abha/api/v3/phr/web/login/abha/request/otp', $payload);

            // Debugging the response
            if ($response->successful()) {
                return $response->json();
            } else {
                logger("ABDM OTP request failed. Status: " . $response->status());
                logger("Response Body: " . $response->body()); // Log the full response body for debugging
                return ['error' => 'Failed to request OTP', 'status_code' => $response->status()];
            }
        } catch (\Exception $e) {
            // Log and return error
            logger($e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function abhaSearchByAbhaAddressVerifyOtp($encryptedOtp, $abhaSearchByAbhaAddressSendOtpTxnId)
    {
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z');
            $requestId = Str::uuid()->toString();
            $payload = [
                "scope" => [
                    "abha-address-login",
                    "mobile-verify"
                ],
                "authData" => [
                    "authMethods" => ["otp"],
                    "otp" => [
                        "txnId" => $abhaSearchByAbhaAddressSendOtpTxnId,   // Transaction ID from the request
                        "otpValue" => $encryptedOtp // OTP entered by the user
                    ]
                ]
            ];

            // Make the POST request to the ABDM API for OTP verification
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'REQUEST-ID'    => $requestId,
                'TIMESTAMP'     => $timestamp,
                'Content-Type'  => 'application/json',
            ])->post('https://abhasbx.abdm.gov.in/abha/api/v3/phr/web/login/abha/verify', $payload);
            if ($response->successful()) {
                return $response->json();
            } else {
                logger('ABDM OTP verification failed: ' . $response->body());
                return response()->json(['error' => 'OTP verification failed'], $response->status());
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500); // Return error response
        }
    }

    public function abhaSearchByAbhaAddressGetProfile($abhaSearchByAbhaAddressVerifyOtpToken)
    {
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z');
            $requestId = Str::uuid()->toString();
            $url = 'https://abhasbx.abdm.gov.in/abha/api/v3/phr/web/login/profile/abha-profile';

            // Make the GET request with headers
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'X-token' => 'Bearer ' . $abhaSearchByAbhaAddressVerifyOtpToken,
                'REQUEST-ID' => $requestId,
                'TIMESTAMP' => $timestamp,
            ])->get($url);
            if ($response->successful()) {
                return $response; // Return the response directly
            } else {
                return $response; // Return the response object to handle in the controller
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => 'An error occurred'], 500);
        }
    }

    // public function abhaSearchByAbhaAddressGetQrCode($abhaSearchByAbhaAddressVerifyOtpToken)
    // {
    //     try {
    //         $token = $this->getAccessToken(); // Ensure you have a method to retrieve the access token
    //         $accessToken = $token['accessToken'];
    //         $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z');
    //         $requestId = Str::uuid()->toString(); // Generate a unique request ID
    //         $url = 'https://abhasbx.abdm.gov.in/abha/api/v3/phr/web/login/profile/abha/phr-card';

    //         // Make the GET request with headers
    //         $response = Http::withHeaders([
    //             'Authorization' => 'Bearer ' . $accessToken,
    //             'X-token' => 'Bearer ' . $abhaSearchByAbhaAddressVerifyOtpToken,
    //             'REQUEST-ID' => $requestId,
    //             'TIMESTAMP' => $timestamp,
    //         ])->get($url);
    //         // Log the response for debugging
    //         logger('Response Status: ' . $response->status());
    //         logger('Response Body: ' . $response->body());
    //         // Handle the response
    //         if ($response->successful()) {
    //             return $response->json(); // Return the response in JSON format
    //         } else {
    //             // Handle the error appropriately
    //             return response()->json(['error' => 'Unable to fetch profile'], $response->status());
    //         }
    //     } catch (\Exception $e) {
    //         // Handle any exceptions that may occur
    //         return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
    //     }
    // }
    public function abhaSearchByAbhaAddressGetQrCode($abhaSearchByAbhaAddressVerifyOtpToken)
    {
        try {
            $token = $this->getAccessToken(); // Ensure you have a method to retrieve the access token
            $accessToken = $token['accessToken'];
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s.v\Z');
            $requestId = Str::uuid()->toString(); // Generate a unique request ID
            $url = 'https://abhasbx.abdm.gov.in/abha/api/v3/phr/web/login/profile/abha/phr-card';

            // Make the GET request with headers
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'X-token' => 'Bearer ' . $abhaSearchByAbhaAddressVerifyOtpToken,
                'REQUEST-ID' => $requestId,
                'TIMESTAMP' => $timestamp,
            ])->get($url);

            // Log the response for debugging purposes
            logger('Response Status: ' . $response->status());
            logger('Response Body: ' . $response->body());

            // Check for null response
            if ($response === null) {
                return response()->json(['error' => 'No response from server'], 500);
            }

            // Handle the response
            if ($response->successful()) {
                return $response->json(); // Return the response in JSON format
            } else {
                // Handle the error appropriately
                return response()->json(['error' => 'Unable to fetch profile', 'status' => $response->status()], $response->status());
            }
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }



    //m2

    public function updateBridgeUrl($data)
    {
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $timestamp = now()->format('Y-m-d\TH:i:s.v\Z');
            $response = Http::withHeaders([
                'REQUEST-ID' => Str::uuid()->toString(), // You can generate a unique ID as needed
                'TIMESTAMP' =>  $timestamp, // Current timestamp
                'X-CM-ID' => $data['X-CM-ID'], // Replace with actual X-CM-ID
                'Authorization' => 'Bearer ' . $accessToken, // Add the full token here
                'Content-Type' => 'application/json'
            ])->patch('https://dev.abdm.gov.in/api/hiecm/gateway/v3/bridge/url', [
                'url' => $data['url'],
            ]);

            // Check the response
            if ($response->successful()) {
                $data = $response->json();
                return response()->json($data, 200);
            } else {
                // Handle error response
                return response()->json($response->json(), $response->status());
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateServiceUrl($data,$url)
    {
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $response = Http::withToken($accessToken)
                ->post($url, $data);
            if ($response->successful()) {
                return $response->json();
            } else {
                // Failed - handle error
                return response()->json(['error' => 'Failed to send data'], 500);
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function generateToken($abhaNumber, $abhaAddress, $name, $gender, $yearOfBirth, $hipId, $cmId)
    {
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $timestamp = now()->format('Y-m-d\TH:i:s.v\Z');
            $requestId = Str::uuid()->toString(); // Generate a unique request ID
            $response = Http::withHeaders([
                'REQUEST-ID' => $requestId, // or generate dynamically
                'TIMESTAMP' =>  $timestamp, // Laravel helper to generate timestamp
                'X-HIP-ID' => $hipId,
                'X-CM-ID' => $cmId,
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json'
            ])->post('https://dev.abdm.gov.in/api/hiecm/v3/token/generate-token', [
                'abhaNumber' => $abhaNumber,
                'abhaAddress' => $abhaAddress,
                'name' => $name,
                'gender' => $gender,
                'yearOfBirth' => $yearOfBirth,
            ]);

            // Check response and handle errors
            if ($response->successful()) {
                return $response->json(); // Successful response as JSON
            } else {
                // Log error and return the status and error message
                logger($response->body());
                return response()->json([
                    'message' => 'Token generation failed',
                    'error' => $response->body()
                ], $response->status());
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function verifyCard($abhaNumber)
    {
        try {
            $token = $this->getAccessToken();
            $accessToken = $token['accessToken'];
            $client = new Client([
                'base_uri' => 'https://abhasbx.abdm.gov.in/abha/api/v3',
            ]);

            $headers = [
                'Authorization' => 'Bearer ' . $accessToken, // Fetch access token if needed
                'Content-Type' => 'application/json',
                'API-KEY' => 'SBXID_008125',
            ];

            $response = $client->post('/verifyCard', [
                'headers' => $headers,
                'json' => $abhaNumber['abha_number'], // This will send the card details in JSON format
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            // Handle exception if API call fails
            return ['error' => $e->getMessage()];
        }
    }
}
