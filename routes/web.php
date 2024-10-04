<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AbdmController;
use App\Http\Controllers\AyushmanController;

//M1
Route::get('/send-otp', [AbdmController::class, 'requestOtp'])->name('request-otp');
Route::get('/enrol/byAadhaar', [AbdmController::class, 'enrollByAadhaar'])->name('enroll-by-aadhaar');
Route::get('/profile/account', [AbdmController::class, 'getAccountProfile'])->name('get-account-profile');
Route::get('/abha-address/suggestions', [AbdmController::class, 'abhaAddressSuggestions'])->name('abha-address-suggestions');
Route::get('/enroll-abha-address', [AbdmController::class, 'enrollAbhaAddress'])->name('enroll-abha-address');


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


