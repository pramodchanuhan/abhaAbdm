<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AbdmService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AbdmController extends Controller
{
    protected $abdmService;

    public function __construct(AbdmService $abdmService)
    {
        $this->abdmService = $abdmService;
    }


    public function encryptAadhaar($aadhaarNumber, $publicKeyPath)
    {
        try {
            $publicKey = file_get_contents($publicKeyPath);
            if ($publicKey === false) {
                throw new Exception("Unable to load public key from path: $publicKeyPath");
            }
            $publicKeyResource = openssl_get_publickey($publicKey);
            if (!$publicKeyResource) {
                throw new Exception("Invalid public key.");
            }
            if (!preg_match('/^\d{12}$/', $aadhaarNumber)) {
                throw new Exception("Invalid Aadhaar number. It must be 12 digits.");
            }
            $encryptedAadhaar = null;
            $success = openssl_public_encrypt($aadhaarNumber, $encryptedAadhaar, $publicKeyResource, OPENSSL_PKCS1_OAEP_PADDING);
            openssl_free_key($publicKeyResource);
            if (!$success) {
                throw new Exception("Encryption failed.");
            }
            return base64_encode($encryptedAadhaar);
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    public function requestOtp()
    {
        try {
            //947292841782 //882260556552
            $aadhaarNumber = '527613815535';
            $publicKeyPath = storage_path('keys/abdm_public_key.pem');
            $encryptedAadhaar = $this->encryptAadhaar($aadhaarNumber, $publicKeyPath);
            $payload = [
                'txnId' => '',  // Transaction ID (can be an empty string if not required)
                'scope' => ['abha-enrol'],
                'loginHint' => 'aadhaar',
                'loginId' => $encryptedAadhaar,  // Pass the encrypted Aadhaar number
                'otpSystem' => 'aadhaar',
            ];
            return $response = $this->abdmService->requestOtp($payload);
            User::create([
                'aadhaar_number' => $aadhaarNumber,
                'txn_id' => $response->txnId,
            ]);
            return response()->json($response);
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function encryptOtp($otp, $publicKeyPath)
    {
        try {
            $publicKey = file_get_contents($publicKeyPath);
            if ($publicKey === false) {
                throw new Exception("Unable to load public key from path: $publicKeyPath");
            }
            $publicKeyResource = openssl_get_publickey($publicKey);
            if (!$publicKeyResource) {
                throw new Exception("Invalid public key.");
            }
            if (!preg_match('/^\d{6}$/', $otp)) {
                throw new Exception("Invalid OTP number. It must be 6 digits.");
            }
            $encryptedAadhaar = null;
            $success = openssl_public_encrypt($otp, $encryptedAadhaar, $publicKeyResource, OPENSSL_PKCS1_OAEP_PADDING);
            openssl_free_key($publicKeyResource);
            if (!$success) {
                throw new Exception("Encryption failed.");
            }
            return base64_encode($encryptedAadhaar);
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function enrollByAadhaar(Request $request)
    {
        try {
            $userdetail = User::where('otp', $request->otp)->first();
            if (!$userdetail) {
                return response()->json(['error' => 'OTP not found.'], 404);
            } else {
                $aadhaarNumber = $userdetail->aadhaar_number;
                $publicKeyPath = storage_path('keys/abdm_public_key.pem');
                $encryptedAadhaar = $this->encryptAadhaar($aadhaarNumber, $publicKeyPath);
                $xToken = $userdetail->txn_id;
                $otp = '145356';
                $mobilenumber = '9454076698';
                // Prepare the necessary data
                $currentTimestamp = now()->toISOString(); // Get the current timestamp in ISO 8601 format
                $txnId = $xToken; // Generate a new transaction ID

                $publicKeyPath = storage_path('keys/abdm_public_key.pem');
                $encryptedAadhaar = $this->encryptOtp($otp, $publicKeyPath);
                $encryptedOtp = $encryptedAadhaar; // Replace with the encrypted OTP
                $mobileNumber = $mobilenumber; // Replace with the actual mobile number

                // Construct the request payload
                $payload = [
                    'authData' => [
                        'authMethods' => ['otp'],
                        'otp' => [
                            'timeStamp' => $currentTimestamp,
                            'txnId' => $txnId,
                            'otpValue' => $encryptedOtp,
                            'mobile' => $mobileNumber,
                        ],
                    ],
                    'consent' => [
                        'code' => 'abha-enrollment',
                        'version' => '1.4',
                    ],
                ];
                $response = $this->abdmService->enrollByAadhaar($payload);
                return response()->json($response);
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getAccountProfile(Request $request)
    {
        //return $token = $this->requestOtp(); 
        try {
            $token = $this->requestOtp(); // This should return a JsonResponse
            $data = json_decode($token->getContent(), true);
            if (isset($data['txnId'])) {
                $xToken =  $data['txnId'];
                $response = $this->abdmService->getAccountProfile($xToken);
                return response()->json($response);
            } else {
                // Handle the case where txnId does not exist or JSON decoding failed
                // You could throw an exception, return an error message, etc.
                return response()->json(['error' => 'Transaction ID not found.'], 404);
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }




    // public function verifyOtp(Request $request)
    // {
    //     $otp = $request->otp;
    //     $transactionId = $request->transactionId;

    //     $response = $this->abdmService->verifyOtp($otp, $transactionId);

    //     return response()->json($response);
    // }

    // public function getProfile($abhaId)
    // {
    //     $response = $this->abdmService->getAbhaProfile($abhaId);

    //     return response()->json($response);
    // }
}
