<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'app');

Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api).*$');
