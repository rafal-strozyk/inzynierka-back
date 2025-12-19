<?php

use Illuminate\Support\Facades\Route;

/*
Route::get('/', function () {
    return view('welcome');
});
 */
Route::get('{any}', function(Request $request) {
    return view('ftontend.index');
})->where('any', '^(?!assets|api|admin|storage).*')