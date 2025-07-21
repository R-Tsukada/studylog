<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// SPA用のフォールバックルート - Vue Routerが全てのルーティングを処理
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
