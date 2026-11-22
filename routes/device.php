<?php

use App\Http\Controllers\IclockController;
use Illuminate\Support\Facades\Route;

/*
| Attendance machines (ZKTeco ADMS push). Loaded without the "web" group:
| no session, cookies or CSRF — see IclockController.
*/

Route::middleware('throttle:240,1')->prefix('iclock')->group(function () {
    Route::match(['get', 'post'], '/cdata', [IclockController::class, 'cdata']);
    Route::match(['get', 'post'], '/cdata.aspx', [IclockController::class, 'cdata']);
    Route::get('/getrequest', [IclockController::class, 'getrequest']);
    Route::get('/getrequest.aspx', [IclockController::class, 'getrequest']);
    Route::post('/devicecmd', [IclockController::class, 'devicecmd']);
    Route::post('/devicecmd.aspx', [IclockController::class, 'devicecmd']);
});
