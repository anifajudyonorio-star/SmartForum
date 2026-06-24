<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MyFirstController;

Route::get('/', function () {
    return view('welcome');
});
