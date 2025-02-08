<?php

use App\Http\Controllers\FlightBookController;
use App\Http\Controllers\FlightSearchController;
use App\Http\Controllers\GeoDataController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\VisaController;
use Illuminate\Support\Facades\Route;

Route::get('nationalities', [GeoDataController::class, 'getNationality']);
Route::get('search/airports', [GeoDataController::class, 'getAirports']);

Route::get('/tours/all', [TourController::class, 'getAllTours']);
Route::get('/tours', [TourController::class, 'getTours']);
Route::get('/tours/{tour}/details', [TourController::class, 'getTourDetails']);

Route::get('/visas/all', [VisaController::class, 'getAllVisas']);
Route::get('/visas', [VisaController::class, 'getVisas']);
Route::get('/visas/{visa}/details', [VisaController::class, 'getVisaDetails']);


Route::get('search/tfusion/flights', [FlightSearchController::class, 'search']);
Route::post('book/tfusion', [FlightBookController::class, 'book']);
Route::get('book/{book_id}/check', [FlightBookController::class, 'check']);
Route::get('book/{book_id}/details', [FlightBookController::class, 'details']);
