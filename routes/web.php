<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AbdmController;
use App\Http\Controllers\AyushmanController;

//M1
//Abha Enroll By Aadhaar
Route::get('/send-otp', [AbdmController::class, 'requestOtp'])->name('request-otp');
Route::get('/enrol/byAadhaar', [AbdmController::class, 'enrollByAadhaar'])->name('enroll-by-aadhaar');
Route::get('/abha-address/suggestions', [AbdmController::class, 'abhaAddressSuggestions'])->name('abha-address-suggestions');
Route::get('/enroll-abha-address', [AbdmController::class, 'enrollAbhaAddress'])->name('enroll-abha-address');
Route::get('/abha-card', [AbdmController::class, 'getAbhaCard'])->name('get-abha-card');

//Abha Enroll By DL
Route::get('/enrol/byDLSendOtp', [AbdmController::class, 'enrollByDLSendOtp'])->name('enrol-by-dl-send-otp');
Route::get('/enrol/byDLVerifyOtp', [AbdmController::class, 'enrollbyDLVerifyOtp'])->name('enrol-by-dl-verify-otp');
Route::get('/enrol/byDL', [AbdmController::class, 'enrollbyDL'])->name('enrol-by-dl');

//Abha Verification
Route::get('/abha-verification-by-abha-number/send-otp', [AbdmController::class, 'abhaVerificationSendOtp'])->name('abha-verification-by-abha-number-send-otp');
Route::get('/abha-verification-by-abha-number/verify-otp', [AbdmController::class, 'abhaVerificationVerifyOtp'])->name('abha-verification-by-abha-number-verify-otp');

Route::get('/abha-verification-by-mobile-number/send-otp', [AbdmController::class, 'abhaVerificationByMobileNumberSendOtp'])->name('abha-verification-by-mobile-number-send-otp');
Route::get('/abha-verification-by-mobile-number/verify-otp', [AbdmController::class, 'abhaVerificationByMobileNumberVerifyOtp'])->name('abha-verification-by-mobile-number-verify-otp');
Route::get('/verify-user', [AbdmController::class, 'verifyUser'])->name('verify-user');
Route::get('/profile/account', [AbdmController::class, 'getAccountProfile'])->name('get-account-profile');
Route::get('/profile/qrcode', [AbdmController::class, 'getProfileQrCode'])->name('get-profile-qr-code');


Route::get('/abha-verification-by-aadhaar-number/send-otp', [AbdmController::class, 'abhaVerificationByAaadhaarNumberSendOtp'])->name('abha-verification-by-aadhaar-number-send-otp');
Route::get('/abha-verification-by-aadhaar-number/verify-otp', [AbdmController::class, 'abhaVerificationByAaadhaarNumberVerifyOtp'])->name('abha-verification-by-aadhaar-number-verify-otp');

//M2
Route::get('/upadate-bridge-url', [AbdmController::class, 'updateBridgeUrl'])->name('update-bridge-url');

//Ayushman
Route::get('/verify-ayushman-card', [AyushmanController::class, 'verifyCard']);





// Route::post('/verify-otp', [AbdmController::class, 'verifyOtp']);
// Route::get('/abha-profile/{abhaId}', [AbdmController::class, 'getProfile']);

// Route::get('/test-env', function () {
//     return [
//         'client_id' => env('ABDM_CLIENT_ID'),
//         'client_secret' => env('ABDM_CLIENT_SECRET'),
//         'base_url' => env('ABDM_BASE_URL'),
//     ];
// });


