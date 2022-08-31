<?php

use Illuminate\Support\Facades\Route;
use LiveIntent\LaravelResourceSearch\Tests\Fixtures\App\Http\Controllers\PostController;

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

// Route::prefix('api')->middleware('api')->group()
Route::get('posts', [PostController::class, 'index']);
Route::post('posts/search', [PostController::class, 'search']);
// Route::apiResource('posts', UserController::class);
