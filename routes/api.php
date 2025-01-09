<?php

use App\Http\Controllers\GeoDataController;
use Illuminate\Support\Facades\Route;

Route::get('search/airports', [GeoDataController::class, 'getAirports']);
