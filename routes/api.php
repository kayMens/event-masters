<?php

use Illuminate\Http\Request;

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
Route::group(['prefix' => 'v1/oauth'], function(){
    Route::post('login', 'Api\UserController@login');
    Route::post('register', 'Api\UserController@register');
    Route::post('activate', 'Api\UserController@activate');
    Route::post('forgot', 'Api\UserController@forgot');
    Route::post('reset', 'Api\UserController@reset');
});
Route::group(['prefix' => 'v1'], function(){
    Route::get('vendor', 'Api\VendorController@vendor');
    Route::get('vendor/{vendor}/logo', 'Api\VendorController@logo');
    Route::get('vendor/{vendor}/header', 'Api\VendorController@header');
});

Route::group(['middleware' => ['auth:api'], 'prefix' => 'v1'], function(){
    Route::get('oauth/logout', 'Api\UserController@logout');
    Route::get('user', 'Api\UserController@user');    
    Route::post('user/password', 'Api\UserController@updatePassword');
    Route::post('user/update', 'Api\UserController@updateUser');

    Route::post('vendor/update', 'Api\VendorController@update');
    Route::post('vendor/{vendor}/logo', 'Api\VendorController@updateLogo');
    Route::post('vendor/{vendor}/header', 'Api\VendorController@updateHeader');
    Route::get('vendor/account', 'Api\VendorController@account');

    Route::get('event', 'Api\EventController@event');
    Route::get('quote', 'Api\EventController@quote');
});
