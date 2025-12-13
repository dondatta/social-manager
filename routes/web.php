<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\VerifyInstagramSignature;

Route::get('/', function () {
    return view('welcome');
});

Route::match(['get', 'post'], '/webhooks/instagram', [WebhookController::class, 'handle'])
    ->middleware(VerifyInstagramSignature::class);
