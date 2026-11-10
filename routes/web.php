<?php

use Illuminate\Support\Facades\Route;

// CargoTrace frontend shell. All protected data is fetched through the existing Sanctum API.
Route::view('/', 'cargotrace.app')->name('cargotrace.home');
