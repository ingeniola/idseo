<?php

use App\Http\Controllers\DataForSeoPostbackController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhooks/dataforseo/{type}', DataForSeoPostbackController::class)
    ->name('webhooks.dataforseo.postback');
