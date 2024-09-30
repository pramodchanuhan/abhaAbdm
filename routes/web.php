<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AbdmController;

//M1
Route::get('/send-otp', [AbdmController::class, 'requestOtp']);

Route::get('/enrol/byAadhaar', [AbdmController::class, 'enrollByAadhaar']);

Route::get('profile/account', [AbdmController::class, 'getAccountProfile']);




// Route::post('/verify-otp', [AbdmController::class, 'verifyOtp']);
// Route::get('/abha-profile/{abhaId}', [AbdmController::class, 'getProfile']);

// Route::get('/test-env', function () {
//     return [
//         'client_id' => env('ABDM_CLIENT_ID'),
//         'client_secret' => env('ABDM_CLIENT_SECRET'),
//         'base_url' => env('ABDM_BASE_URL'),
//     ];
// });


