<?php

namespace App\Http\Controllers;

use App\Services\AbdmService;
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

    public function createAbha(Request $request)
    {
        $data = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'gender' => 'M',
            'dob' => '1990-01-01',
            'mobile' => '9027956097',
        ];

        $response = $this->abdmService->createAbhaId($data);

        return response()->json($response);
    }

    public function encryptAadhaar($aadhaarNumber, $publicKeyPath)
{
    $publicKey = file_get_contents($publicKeyPath);
    openssl_public_encrypt($aadhaarNumber, $encryptedAadhaar, $publicKey);
    return base64_encode($encryptedAadhaar);
}

    public function requestOtp($txnId, $encryptedAadhaar=null)
    {
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
        $response = $this->abdmService->requestOtp($payload);
        return response()->json($response);
        
    }

    public function verifyOtp(Request $request)
    {
        $otp = $request->otp;
        $transactionId = $request->transactionId;

        $response = $this->abdmService->verifyOtp($otp, $transactionId);

        return response()->json($response);
    }

    public function getProfile($abhaId)
    {
        $response = $this->abdmService->getAbhaProfile($abhaId);

        return response()->json($response);
    }
}
