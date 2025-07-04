<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\CharterFlightController;
use App\Http\Controllers\GeoDataController;
use App\Http\Controllers\Nemo\FlightSearchController as NemoFlightSearchController;
use App\Http\Controllers\TFusion\FlightBookController;
use App\Http\Controllers\TFusion\FlightProcessController;
use App\Http\Controllers\TFusion\FlightSearchController as TFusionSearchController;
use App\Http\Controllers\XMLAgency\FlightSearchController as XMLAgencyFlightSearchController;
use App\Http\Controllers\XMLAgency\FlightBookController as XMLAgencyFlightBookController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\UserController;
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

Route::group(['prefix' => 'nemo'], function () {
    Route::get('search/flights', [NemoFlightSearchController::class, 'search']);
});

Route::group(['prefix' => 'tfusion'], function () {
    Route::get('search/flights', [TFusionSearchController::class, 'search']);
    Route::get('search/test', [TFusionSearchController::class, 'searchTest']);
    Route::post('process/flights', [FlightProcessController::class, 'processDetails']);
    Route::post('bookings/process', [FlightBookController::class, 'processBooking']);
});

Route::group(['prefix' => 'xmlagency'], function () {
    Route::get('search/flights', [XMLAgencyFlightSearchController::class, 'search']);
    Route::post('bookings/process', [XMLAgencyFlightBookController::class, 'processBooking']);
});

Route::group(['prefix' => 'bookings'], function () {
    Route::post('stripe/checkout', [BookController::class, 'createStripePaymentIntent']);
    Route::post('start', [BookController::class, 'startBooking']);
    Route::get('{book_id}/details', [BookController::class, 'details']);
});

Route::group(['prefix' => 'user'], function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::post('/password/request-reset', [PasswordResetController::class, 'requestReset']);
    Route::post('/password/verify-code', [PasswordResetController::class, 'verifyCode']);
    Route::post('/password/reset', [PasswordResetController::class, 'resetPassword']);
});

Route::group(['middleware' => 'auth:sanctum'], function () {
    Route::get('/user', [UserController::class, 'show']);
    Route::post('/user', [UserController::class, 'update']);

    Route::get('bookings', [BookController::class, 'getBookings']);
});

// Charter Flights API Routes
Route::prefix('charter-flights')->group(function () {
    Route::get('/departure-cities', [CharterFlightController::class, 'getDepartureCities']);
    Route::get('/destination-cities', [CharterFlightController::class, 'getDestinationCities']);
    Route::get('/available-dates', [CharterFlightController::class, 'getAvailableDates']);
    Route::get('/available-flights', [CharterFlightController::class, 'getAvailableFlights']);
});
