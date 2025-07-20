<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\CharterFlightController;
use App\Http\Controllers\GetBookingsController;
use App\Http\Controllers\GeoDataController;
use App\Http\Controllers\TFusion\FlightBookController;
use App\Http\Controllers\TFusion\FlightProcessController as TFusionFlightProcessController;
use App\Http\Controllers\TFusion\FlightSearchController as TFusionSearchController;
use App\Http\Controllers\XMLAgency\FlightSearchController as XMLAgencyFlightSearchController;
use App\Http\Controllers\XMLAgency\FlightProcessController as XMLAgencyFlightProcessController;
use App\Http\Controllers\XMLAgency\FlightBookController as XMLAgencyFlightBookController;
use App\Http\Controllers\Nemo\FlightSearchController as NemoFlightSearchController;
use App\Http\Controllers\Nemo\FlightProcessController as NemoFlightProcessController;
use App\Http\Controllers\Nemo\FlightBookController as NemoFlightBookController;
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

Route::group(['prefix' => 'tfusion'], function () {
    Route::get('search/flights', [TFusionSearchController::class, 'search']);
    Route::post('process/flights', [TFusionFlightProcessController::class, 'processDetails']);
    Route::post('bookings/process', [FlightBookController::class, 'processBooking']);
});

Route::group(['prefix' => 'xmlagency'], function () {
    Route::get('search/flights', [XMLAgencyFlightSearchController::class, 'search']);
    Route::post('process/flights', [XMLAgencyFlightProcessController::class, 'processDetails']);
    Route::post('bookings/process', [XMLAgencyFlightBookController::class, 'processBooking']);
});

Route::group(['prefix' => 'nemo'], function () {
    Route::get('search/flights', [NemoFlightSearchController::class, 'search']);
    Route::post('process/flights', [NemoFlightProcessController::class, 'processDetails']);
    Route::post('bookings/process', [NemoFlightBookController::class, 'processBooking']);
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

    Route::get('bookings', GetBookingsController::class);
});

// Charter Flights API Routes
Route::prefix('charter-flights')->group(function () {
    Route::get('/departure-cities', [CharterFlightController::class, 'getDepartureCities']);
    Route::get('/destination-cities', [CharterFlightController::class, 'getDestinationCities']);
    Route::get('/available-dates', [CharterFlightController::class, 'getAvailableDates']);
    Route::get('/available-flights', [CharterFlightController::class, 'getAvailableFlights']);
});
