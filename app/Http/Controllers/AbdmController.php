<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AbdmService;
use Exception;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use League\CommonMark\Node\Block\Document;

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
            //947292841782 //882260556552 //846741677520
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
            // Check if txnId is present in the response
            if (empty($response['txnId'])) {
                return response()->json(['error' => 'OTP not found.'], 404);
            }

            // Access txnId and message
            $txnId = $response['txnId'] ?? null;
            $message = $response['message'] ?? null;
            if (preg_match('/\*\*\*\*\*(\d{4})/', $message, $matches)) {
                $lastFourDigits = $matches[1]; // The first capturing group contains the last four digits
            } else {
                $lastFourDigits = null; // Handle case where no match is found
            }
            // Create a new User record
            $save = User::updateOrCreate(
                // Condition to check if the user exists (based on aadhaar_number)
                ['aadhaar_number' => $aadhaarNumber],
                [
                    'txnId' => $txnId,
                    'mobile' => $lastFourDigits,
                ]
            );

            // Return the response if the record is saved successfully
            return $save
                ? response()->json($response)
                : response()->json(['error' => 'Failed to save user.'], 500);
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
            //$otp = $request->otp;
            $otp = '408322';
            //  $mobilenumber = $request->mobilenumber;
            $mobilenumber = '9027956097';
            $lastFourDigits = substr($mobilenumber, -4);
            $userdetail = User::where('mobile', 'like', '%' . $lastFourDigits)->first();
            if (!$userdetail) {
                return response()->json(['error' => 'OTP not found.'], 404);
            } else {
                $aadhaarNumber = $userdetail->aadhaar_number;
                session()->put('txnId', $userdetail->txnId);
                $userdetail->delete();
                $publicKeyPath = storage_path('keys/abdm_public_key.pem');
                $encryptedAadhaar = $this->encryptAadhaar($aadhaarNumber, $publicKeyPath);

                // $otp = '145356';
                // $mobilenumber = '9454076698';
                // Prepare the necessary data
                $currentTimestamp = now()->toISOString();
                $encryptedAadhaar = $this->encryptOtp($otp, $publicKeyPath);
                $encryptedOtp = $encryptedAadhaar; // Replace with the encrypted OTP

                // Construct the request payload
                $payload = [
                    'authData' => [
                        'authMethods' => ['otp'],
                        'otp' => [
                            'timeStamp' => $currentTimestamp,
                            'txnId' => session()->get('txnId'),
                            'otpValue' => $encryptedOtp,
                            'mobile' => $mobilenumber,
                        ],
                    ],
                    'consent' => [
                        'code' => 'abha-enrollment',
                        'version' => '1.4',
                    ],
                ];
                $data = $this->abdmService->enrollByAadhaar($payload);
                //$data = json_decode($data->getContent(), true);

                if (!empty($data['tokens'])) {
                    session()->put('enrollByAadhaarToken', $data['tokens']['token']);
                    session()->put('enrollByAadhaartxnId', $data['txnId']);
                    //    $patientArray = [
                    //     'message' => $data['message'],
                    //     'txnId' => $data['txnId'],
                    //     'mobile' => $data['mobile'],
                    //     'phrAddress' => $data['phrAddress'],
                    //     'ABHANumber' => $data['ABHANumber']
                    // ];
                    //return $patientArray;
                    return response()->json($data);
                } else {
                    return response()->json(['error' => 'OTP not found.'], 404);
                }
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getAccountProfile(Request $request)
    {
        try {
            $xToken = session()->get('enrollByAadhaarToken');
            $response = $this->abdmService->getAccountProfile($xToken);
            return response()->json($response);
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getProfileQrCode()
    {
        try {
            $xToken = session()->get('enrollByAadhaarToken');
            $response = $this->abdmService->getProfileQrCode($xToken);
            return response()->json(
                $response,
                200, // HTTP status code
                [], // Additional headers
                JSON_PRETTY_PRINT // Pretty print the JSON response
            );
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    public function abhaAddressSuggestions(Request $request)
    {
        try {
            $xToken = session()->get('enrollByAadhaartxnId');
            $response = $this->abdmService->abhaAddressSuggestions($xToken);
            return response()->json(
                $response,
                200, // HTTP status code
                [], // Additional headers
                JSON_PRETTY_PRINT // Pretty print the JSON response
            );
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function enrollAbhaAddress(Request $request)
    {
        try {
            $xToken = session()->get('enrollByAadhaartxnId');
            // Validate the incoming request if needed
            // $request->validate([
            //     'txnId' => 'required|string',
            //     'abhaAddress' => 'required|string',
            // ]);

            // Prepare the request data
            $data = [
                'txnId' => $xToken,
                'abhaAddress' => 'pramod_kumar100610',
                'preferred' => 1,
            ];

            $response = $this->abdmService->enrollAbhaAddress($data);
            return response()->json($response);
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getAbhaCard(Request $request)
    {
        try {
            $xToken = session()->get('enrollByAadhaarToken');
            $response = $this->abdmService->getAbhaCard($xToken);
            return response()->json($response);
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    //enroll by DL
    public function encryptMobileNumber($mobileNumber, $publicKeyPath, $encryptType = null)
    {
        try {
            // Load the public key from the provided path
            $publicKey = file_get_contents($publicKeyPath);
            if ($publicKey === false) {
                throw new Exception("Unable to load public key from path: $publicKeyPath");
            }

            // Extract public key resource
            $publicKeyResource = openssl_get_publickey($publicKey);
            if (!$publicKeyResource) {
                throw new Exception("Invalid public key.");
            }

            // Validate mobile number (ensure it is 10 digits)
            if (!preg_match('/^\d{10}$/', $mobileNumber)) {
                throw new Exception("Invalid mobile number. It must be 10 digits.");
            }

            // Encrypt the mobile number
            $encryptedMobile = null;
            $success = openssl_public_encrypt($mobileNumber, $encryptedMobile, $publicKeyResource, OPENSSL_PKCS1_OAEP_PADDING);
            // Free the public key resource
            openssl_free_key($publicKeyResource);

            // Handle encryption success/failure
            if (!$success) {
                throw new Exception("Encryption failed.");
            }

            // Return the encrypted mobile number, base64 encoded
            return base64_encode($encryptedMobile);
        } catch (\Exception $e) {
            // Log the error and return a 500 error response
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function enrollByDLSendOtp(Request $request)
    {
        try {
            // Validate the incoming request if needed
            // $request->validate([
            //     'mobileNumber' => 'required|string|min:10|max:10',
            // ]);
            //  $mobileNumber = 'your-encrypted-mobile-number'; // Replace with actual encrypted mobile number
            $mobileNumber = '9027956097'; // Replace with actual encrypted mobile number
            $publicKeyPath = storage_path('keys/abdm_public_key.pem');
            $encryptedMobileNumber = $this->encryptMobilenumber($mobileNumber, $publicKeyPath);
            $response = $this->abdmService->enrollByDLSendOtp($encryptedMobileNumber);
            if (!empty($response['txnId'])) {
                session()->put('enrollByDLSendOtpTxnId', $response['txnId']);
                return response()->json($response);
            } else {
                return response()->json(['error' => $response['message']], 500);
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function enrollByDLVerifyOtp(Request $request)
    {
        try {
            // $request->validate([
            //     'otp' => 'required|string',
            // ]);
            $otp = '055142';
            $enrollByDLSendOtpTxnId = session()->get('enrollByDLSendOtpTxnId');
            $publicKeyPath = storage_path('keys/abdm_public_key.pem');
            $encryptedOtp = $this->encryptOtp($otp, $publicKeyPath);
            $response = $this->abdmService->enrollByDLVerifyOtp($encryptedOtp, $enrollByDLSendOtpTxnId);
            //return response()->json($response);
            if (!empty($response['txnId'])) {
                session()->put('enrollByDLVerifyOtpTxnId', $response['txnId']);
                return response()->json($response);
            } else {
                return response()->json(['error' => $response['message']], 500);
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function enrollbyDL(Request $request)
    {
        try {
            $request->validate([
                'documentId' => 'required|string',
                'firstName' => 'required|string',
                'middleName' => 'some time|nullable|string',
                'lastName' => 'required|string',
                'dob' => 'required|date',
                'gender' => 'required|string',
                'frontSidePhoto' => 'required|image|max:2048', // Ensure it's an image and set a size limit (in KB)
                'backSidePhoto' => 'required|image|max:2048',
                'address' => 'required|string',
                'state' => 'required|string',
                'district' => 'required|string',
                'pincode' => 'required|string',
            ]);

            $frontPhotoBase64 = base64_encode(file_get_contents($request->file('frontSidePhoto')->path()));
            $backPhotoBase64 = base64_encode(file_get_contents($request->file('backSidePhoto')->path()));

            $txnId = session()->get('enrollByDLVerifyOtpTxnId');
            $payload = [
                "txnId" => $txnId,
                "documentType" => "DRIVING_LICENCE",
                "documentId" => 'UP13 20240014937',  // DL number
                "firstName" => 'PRAMOD',     // First Name
                "middleName" => '',   // Middle Name
                "lastName" => 'KUMAR',       // Last Name
                "dob" => '1991-06-10',                  // DOB
                "gender" => 'M',            // Gender
                "frontSidePhoto" =>  $frontPhotoBase64,  // Base64 front photo
                "backSidePhoto" => $backPhotoBase64,  // Base64 back photo
                "address" => 'NAVI NAGAR DAVKORA ANUPSHAHR BULANDSHAHR UTTAR PRADESH 202394',          // Address
                "state" => 'Uttar Pradesh',              // State
                "district" => 'Bulandshahr',        // District
                "pinCode" => '202394',          // Pincode
                "consent" => [
                    "code" => "abha-enrollment",
                    "version" => "1.4"
                ]
            ];
            $response = $this->abdmService->enrollbyDL($payload);
            return response()->json($response);
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function encryptAbhaNumber($abhaNumber, $publicKeyPath)
    {
        try {
            // Load the public key from the provided path
            $publicKey = file_get_contents($publicKeyPath);
            if ($publicKey === false) {
                throw new Exception("Unable to load public key from path: $publicKeyPath");
            }

            // Extract public key resource
            $publicKeyResource = openssl_get_publickey($publicKey);
            if (!$publicKeyResource) {
                throw new Exception("Invalid public key.");
            }

            // Validate ABHA number (must be 17 characters including hyphens)
            if (!preg_match('/^\d{2}-\d{4}-\d{4}-\d{4}$/', $abhaNumber)) {
                throw new Exception("Invalid ABHA number. It must be in the format 'XX-XXXX-XXXX-XXXX'.");
            }

            // Encrypt the ABHA number (keeping the hyphens)
            $encryptedAbha = null;
            $success = openssl_public_encrypt($abhaNumber, $encryptedAbha, $publicKeyResource, OPENSSL_PKCS1_OAEP_PADDING);

            // Free the public key resource
            openssl_free_key($publicKeyResource);

            // Handle encryption success/failure
            if (!$success) {
                throw new Exception("Encryption failed.");
            }

            // Return the encrypted ABHA number, base64 encoded (for API request)
            return base64_encode($encryptedAbha);
        } catch (\Exception $e) {
            // Log the error and return a 500 error response
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }




    public function abhaVerificationSendOtp(Request $request)
    {
        try {
            // $request->validate([
            //     'abhaNumber' => 'required|string',
            // ]);

            $abhaNumber = '91-5662-8037-6633';
            $publicKeyPath = storage_path('keys/abdm_public_key.pem');
            $encryptedAbhaNumber = $this->encryptAbhaNumber($abhaNumber, $publicKeyPath);
            $response = $this->abdmService->abhaVerificationSendOtp($encryptedAbhaNumber);
            if (!empty($response['txnId'])) {
                session()->put('abhaVerificationSendOtpTxnId', $response['txnId']);
                return response()->json($response);
            } else {
                return response()->json(['error' => $response['message']], 500);
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function abhaVerificationVerifyOtp(Request $request)
    {
        try {
            // $request->validate([
            //     'otpValue' => 'required|string', // Ensure OTP value is not empty
            // ]);
            $otp = '301967';
            $txnId = session()->get('abhaVerificationSendOtpTxnId');
            $publicKeyPath = storage_path('keys/abdm_public_key.pem');
            $encryptedOtp = $this->encryptOtp($otp, $publicKeyPath);
            $response = $this->abdmService->abhaVerificationVerifyOtp($encryptedOtp, $txnId);
            //return response()->json($response);
            return response()->json(
                $response,
                200, // HTTP status code
                [], // Additional headers
                JSON_PRETTY_PRINT // Pretty print the JSON response
            );
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function abhaVerificationByMobileNumberSendOtp(Request $request)
    {
        try {
            // $request->validate([
            //     'mobileNumber' => 'required|string',
            // ]);
            $mobileNumber = '9027956097';
            $publicKeyPath = storage_path('keys/abdm_public_key.pem');
            $encryptedMobileNumber = $this->encryptMobilenumber($mobileNumber, $publicKeyPath);
            $response = $this->abdmService->abhaVerificationByMobileNumberSendOtp($encryptedMobileNumber);
            if (!empty($response['txnId'])) {
                session()->put('abhaVerificationByMobileNumberSendOtpTxnId', $response['txnId']);
                return response()->json($response);
            } else {
                return response()->json(['error' => $response['message']], 500);
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function abhaVerificationByMobileNumberVerifyOtp(Request $request)
    {
        try {
            // $request->validate([
            //     'otpValue' => 'required|string', // Ensure the encrypted OTP is provided
            // ]);
            $otp = '446256';
            $txnId = session()->get('abhaVerificationByMobileNumberSendOtpTxnId');
            $publicKeyPath = storage_path('keys/abdm_public_key.pem');
            $encryptedOtp = $this->encryptOtp($otp, $publicKeyPath);
            $response = $this->abdmService->abhaVerificationByMobileNumberVerifyOtp($encryptedOtp, $txnId);
            if (!empty($response['txnId'])) {
                session()->put('abhaVerificationByMobileNumberVerifyOtpTxnId', $response['txnId']);
                session()->put('abhaVerificationByMobileNumberVerifyOtpJwtToken', $response['token']);
                return response()->json($response);
            } else {
                return response()->json(['error' => $response['message']], 500);
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function verifyUser(Request $request)
    {
        try {
            // $request->validate([
            //     'abhaNumber' => 'required|string', // Ensure the encrypted OTP is provided
            // ]);
            $abhaNumber = '91-5662-8037-6633';
            $txnId = session()->get('abhaVerificationByMobileNumberVerifyOtpTxnId');
            $jwtToken = session()->get('abhaVerificationByMobileNumberVerifyOtpJwtToken');
            $response = $this->abdmService->verifyUser($abhaNumber, $txnId, $jwtToken);
            if (!empty($response['token'])) {
                session()->put('enrollByAadhaarToken', $response['token']);
                return response()->json($response);
            } else {
                return response()->json(['error' => $response['message']], 500);
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function abhaVerificationByAaadhaarNumberSendOtp()
    {
        try {
            // $request->validate([
            //     'aadhaarNumber' => 'required|string',
            // ]);
            $aadhaarNumber = '527613815535';
            $publicKeyPath = storage_path('keys/abdm_public_key.pem');
            $encryptedAadhaarNumber = $this->encryptAadhaar($aadhaarNumber, $publicKeyPath);
            $response = $this->abdmService->abhaVerificationByAaadhaarNumberSendOtp($encryptedAadhaarNumber);
            if (!empty($response['txnId'])) {
                session()->put('abhaVerificationByAaadhaarNumberSendOtpTxnId', $response['txnId']);
                return response()->json($response);
            } else {
                return response()->json(['error' => $response['message']], 500);
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function abhaVerificationByAaadhaarNumberVerifyOtp()
    {
        try {
            // $request->validate([
            //     'otpValue' => 'required|string', // Ensure the encrypted OTP is provided
            // ]);
            $otp = '319300';
            $txnId = session()->get('abhaVerificationByAaadhaarNumberSendOtpTxnId');
            $publicKeyPath = storage_path('keys/abdm_public_key.pem');
            $encryptedOtp = $this->encryptOtp($otp, $publicKeyPath);
            $response = $this->abdmService->abhaVerificationByAaadhaarNumberVerifyOtp($txnId, $encryptedOtp,);
            return response()->json(
                $response,
                200, // HTTP status code
                [], // Additional headers
                JSON_PRETTY_PRINT // Pretty print the JSON response
            );
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function abhaSearchByMobileNumber(Request $request)
    {
        try {
            // $request->validate([
            //     'mobileNumber' => 'required|digits:10',
            // ]);
            $mobileNumber = '9027956097';
            $publicKeyPath = storage_path('keys/abdm_public_key.pem');
            $encryptedMobilenumber = $this->encryptMobilenumber($mobileNumber, $publicKeyPath);
            $response = $this->abdmService->abhaSearchByMobileNumber($encryptedMobilenumber);
            if (!empty($response['txnId'])) {
                session()->put('abhaSearchTxnId', $response['txnId']);
                return response()->json($response);
            } else {
                return response()->json($response);
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function encryptAbhaAddress($abhaAddress, $publicKeyPath)
    {
        try {
            // Step 1: Load the public key from the provided path
            $publicKey = file_get_contents($publicKeyPath);
            if ($publicKey === false) {
                throw new Exception("Unable to load public key from path: $publicKeyPath");
            }

            // Step 2: Extract public key resource
            $publicKeyResource = openssl_get_publickey($publicKey);
            if (!$publicKeyResource) {
                throw new Exception("Invalid public key.");
            }

            // Step 3: Validate ABHA address (must be in the format 'username@sbx')
            if (!preg_match('/^[a-zA-Z0-9._%+-]+@sbx$/', $abhaAddress)) {
                throw new Exception("Invalid ABHA address. It must be in the format 'username@sbx'.");
            }

            // Step 4: Encrypt the ABHA address
            $encryptedAbha = null;
            $success = openssl_public_encrypt($abhaAddress, $encryptedAbha, $publicKeyResource, OPENSSL_PKCS1_OAEP_PADDING);

            // Free the public key resource
            openssl_free_key($publicKeyResource);

            // Step 5: Handle encryption success/failure
            if (!$success) {
                throw new Exception("Encryption failed.");
            }

            // Step 6: Return the encrypted ABHA address, base64 encoded (for API request)
            return base64_encode($encryptedAbha);
        } catch (\Exception $e) {
            // Log the error and return a 500 error response
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function abhaSearchByAbhaAddress()
    {
        try {
            // $request->validate([
            //     'abhaAddress' => 'required|string', 
            // ]);
            $abhaaddress = 'pramod_kumar100610@sbx';
            $response = $this->abdmService->abhaSearchByAbhaAddress($abhaaddress);
            if (!empty($response['abhaAddress'])) {
                session()->put('abhaSearchByAbhaAddress', $response['abhaAddress']);
                return response()->json($response);
            } else {
                return response()->json($response);
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function abhaSearchByAbhaAddressSendOtp()
    {
        try {
            // $request->validate([
            //     'abhaAddress' => 'required|string', 
            // ]);
            //$abhaSearchByAbhaAddress = session()->get('abhaSearchByAbhaAddress');
            $abhaSearchByAbhaAddress = 'pramod_kumar100610@sbx';
            $publicKeyPath = storage_path('keys/abdm_public_key.pem');
            $encryptedAbhaddress = $this->encryptAbhaAddress($abhaSearchByAbhaAddress, $publicKeyPath);
            $response = $this->abdmService->abhaSearchByAbhaAddressSendOtp($encryptedAbhaddress);
            if (!empty($response['txnId'])) {
                session()->put('abhaSearchByAbhaAddressSendOtpTxnId', $response['txnId']);
                return response()->json($response);
            } else {
                return response()->json($response);
            }
            return response()->json($response);
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function abhaSearchByAbhaAddressVerifyOtp()
    {
        try {
            // $request->validate([
            //     'otp' => 'required|string',
            // ]);
            $otp = '953845';
            $abhaSearchByAbhaAddressSendOtpTxnId = session()->get('abhaSearchByAbhaAddressSendOtpTxnId');
            $publicKeyPath = storage_path('keys/abdm_public_key.pem');
            $encryptedOtp = $this->encryptOtp($otp, $publicKeyPath);
            $response = $this->abdmService->abhaSearchByAbhaAddressVerifyOtp($encryptedOtp, $abhaSearchByAbhaAddressSendOtpTxnId);
            if (!empty($response['tokens'])) {
                session()->put('abhaSearchByAbhaAddressVerifyOtpToken', $response['tokens']['token']);
                return response()->json($response);
            } else {
                return response()->json($response);
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function abhaSearchByAbhaAddressGetProfile(Request $request)
    {
        try {
            $abhaSearchByAbhaAddressVerifyOtpToken = session()->get('abhaSearchByAbhaAddressVerifyOtpToken');
            if (!$abhaSearchByAbhaAddressVerifyOtpToken) {
                return response()->json(['error' => 'Missing OTP token'], 400);
            }
            $response = $this->abdmService->abhaSearchByAbhaAddressGetProfile($abhaSearchByAbhaAddressVerifyOtpToken);
            if ($response->successful()) {
                return $response->json();
            } else {
                return response()->json(['error' => 'Unable to fetch profile'], $response->status());
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function abhaSearchByAbhaAddressGetQrCode(Request $request)
    {
        try {
            $abhaSearchByAbhaAddressVerifyOtpToken = session()->get('abhaSearchByAbhaAddressVerifyOtpToken');
            if (!$abhaSearchByAbhaAddressVerifyOtpToken) {
                return response()->json(['error' => 'Missing OTP token'], 400);
            }
            $response = $this->abdmService->abhaSearchByAbhaAddressGetQrCode($abhaSearchByAbhaAddressVerifyOtpToken);
            if ($response->successful()) {
                return $response->json();
            } else {
                return response()->json(['error' => 'Unable to fetch profile'], $response->status());
            }
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function generateToken(Request $request)
    {
        // $request->validate([
        //     'abhaNumber' => 'required|string',
        //     'abhaAddress' => 'required|string',
        //     'name' => 'required|string',
        //     'gender' => 'required|string',
        //     'yearOfBirth' => 'required|integer',
        //     'hipId' => 'required|string',
        //     'cmId' => 'required|string',
        // ]);
        // Replace these with actual values
        $abhaNumber = '91566280376633';
        $abhaAddress = 'pramod_kumar100610@sbx';
        $name = 'Pramod Kumar';
        $gender = 'M';
        $yearOfBirth = '1991';
        $hipId = '7143141087-7733';
        $cmId = 'sbx'; // This is the token you provided

        return $response = $this->abdmService->generateToken($abhaNumber, $abhaAddress, $name, $gender, $yearOfBirth, $hipId, $cmId);
        if (!empty($response['tokens'])) {
            session()->put('abhaSearchByAbhaAddressVerifyOtpToken', $response['tokens']['token']);
            return response()->json($response);
        } else {
            return response()->json($response);
        }
    }




    //M2
    public function updateBridgeUrl(Request $request)
    {
        try {
        //     $request->validate([
        //'url' => 'required|string',
        $url = 'https://dev.abdm.gov.in/api/hiecm/gateway/v3/bridge/url';
        // ]);
        $data = [
            'url' => $url,
            'X-CM-ID' => 'sbx',
        ];
        $response = $this->abdmService->updateBridgeUrl($data);
        return response()->json($response);
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateServiceUrl(Request $request)
    {
        try {
        //     $request->validate([
        //'url' => 'required|string',
        $url = env('SES_API_BASE_URL') . '/v1/bridges/MutipleHRPAddUpdateServices';
        $token = env('SES_API_BEARER_TOKEN');
    
        // Request payload
        $data = [
            "facilityId" => "IN0911597591",
            "facilityName" => "Pramod",
            "HRP" => [
                [
                    "bridgeId" => "test1",
                    "hipName" => "Pramod",
                    "type" => "HIP",
                    "active" => true
                ]
            ]
        ];
        $response = $this->abdmService->updateServiceUrl($data, $url);
        return response()->json($response);
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
