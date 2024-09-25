<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AbdmController;



Route::get('/create-abha', [AbdmController::class, 'createAbha']);
Route::get('/send-otp', [AbdmController::class, 'requestOtp']);
Route::post('/verify-otp', [AbdmController::class, 'verifyOtp']);
Route::get('/abha-profile/{abhaId}', [AbdmController::class, 'getProfile']);

// Route::get('/test-env', function () {
//     return [
//         'client_id' => env('ABDM_CLIENT_ID'),
//         'client_secret' => env('ABDM_CLIENT_SECRET'),
//         'base_url' => env('ABDM_BASE_URL'),
//     ];
// });


