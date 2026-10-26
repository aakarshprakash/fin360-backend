<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

/*
 * Named 'login' route — required by Laravel's auth internals even in API-only apps.
 * Returns a JSON 401 instead of crashing with "Route [login] not defined."
 */
Route::get('/login', function () {
    return response()->json([
        'message' => 'Unauthenticated. Please login via POST /api/login with your email and password.',
    ], 401);
})->name('login');

