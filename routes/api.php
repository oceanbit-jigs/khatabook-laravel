<?php

use App\Constants\ControllerMethods;
use App\Constants\ControllerPaths;
use App\Constants\EndPoints;
use App\Constants\Middlewares;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\BusinessUserController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\ArtisanCommandController;
use Google\Api\Endpoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/**
 * ========================================================================
 * Users Services (Register, Login)
 * ========================================================================
 */
Route::post(EndPoints::user_register, ControllerPaths::UserController . ControllerMethods::register);
Route::post(EndPoints::user_login, ControllerPaths::UserController . ControllerMethods::login);

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

/**
 *
 *  unauthorised:api = Here "auth" is middleware define
 *                     in 'Kernel.php' with Class 'Authenticate.php'
 */
Route::group([Middlewares::MIDDLEWARE => Middlewares::AUTH . ':api'], function () {

    Route::post(EndPoints::user_logout, ControllerPaths::UserController . ControllerMethods::logout);

    //Business Api's
    Route::get('businesses', [BusinessController::class, 'index']);
    Route::post('businesses', [BusinessController::class, 'store']);
    Route::get('businesses/{id}', [BusinessController::class, 'show']);
    Route::put('businesses/{id}', [BusinessController::class, 'update']);
    Route::delete('businesses/{id}', [BusinessController::class, 'destroy']);

    //Customers Api's
    Route::get('customers', [CustomerController::class, 'index']);
    Route::post('customers', [CustomerController::class, 'store']);
    Route::get('customers/{id}', [CustomerController::class, 'show']);
    Route::put('customers/{id}', [CustomerController::class, 'update']);
    Route::delete('customers/{id}', [CustomerController::class, 'destroy']);

    //Busineuser Api's
    Route::get('business-users', [BusinessUserController::class, 'index']);
    Route::post('business-users', [BusinessUserController::class, 'store']);
    Route::put('business-users/{id}', [BusinessUserController::class, 'update']);
    Route::delete('business-users/{id}', [BusinessUserController::class, 'destroy']);

    //Transaction Api's
    Route::get('transactions', [TransactionController::class, 'index']);
    Route::post('transactions', [TransactionController::class, 'store']);
    Route::get('transactions/{id}', [TransactionController::class, 'show']);
    Route::put('transactions/{id}', [TransactionController::class, 'update']);
    Route::delete('transactions/{id}', [TransactionController::class, 'destroy']);

 });


/**
 * Called When Unauthorised user Access services. Called From Middleware UnauthorisedUser.php -> redirectTo()
 */
Route::get(EndPoints::unauthorised, ControllerPaths::UserController . ControllerMethods::unauthorised)->name(EndPoints::unauthorised);

/**
 * Called When Non Admin user Access Admin's services. Called From Middleware AdminAccess.php -> handle()
 */
Route::get(EndPoints::adminaccess, ControllerPaths::UserController . ControllerMethods::adminaccess)->name(EndPoints::adminaccess);

/**
 * Called When Un-Active user Access Active User services. Called From Middleware ActiveUserAccess.php -> handle()
 */
Route::get(EndPoints::activeaccess, ControllerPaths::UserController . ControllerMethods::activeaccess)->name(EndPoints::activeaccess);

/**
 *  For Email Changes in ".env" File Must Follow below Steps
 *          First Check "APP_URL" in ".env" file is with port if not then add port number example -> "http://localhost:8000"
 *          1. terminate server if already started
 *          2. run command "php artisan config:cache"  For Clear Caches
 *          3. then run command "php artisan serve"  to start Server with update ".env" file config
 * */

Route::group(['middleware' => ['web']], function () {
    //routes here
    Route::get(EndPoints::password_reset, ControllerPaths::UserController . ControllerMethods::resetPassword)->name('password.reset');
    //  Route::get(EndPoints::password_reset . '/{token}', ControllerPaths::UserController . ControllerMethods::resetPassword)->name('password.reset');
});
