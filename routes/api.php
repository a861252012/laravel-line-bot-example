<?php

use App\Http\Controllers\LineBotController;
use App\Http\Middleware\VerifyLineSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('webhook', [LineBotController::class, 'receive'])
    ->middleware(VerifyLineSignature::class);
