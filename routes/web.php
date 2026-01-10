<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Test połączenia z bazą danych (LOCAL DEV)
|--------------------------------------------------------------------------
*/
Route::get('/db-test', function () {
    return response()->json([
        'database' => DB::connection()->getDatabaseName(),
        'status' => 'OK',
    ]);
});

/*
|--------------------------------------------------------------------------
| Frontend SPA (catch-all)
|--------------------------------------------------------------------------
| Wszystkie trasy frontendowe obsługiwane przez JS
| Wykluczamy assets, api, admin, storage
*/
Route::get('{any}', function (Request $request) {
    return view('frontend.index');
})->where('any', '^(?!assets|api|admin|storage).*');
