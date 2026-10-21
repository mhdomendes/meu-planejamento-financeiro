
<?php

use App\Http\Controllers\Api\MonthlyPlanController;
use Illuminate\Support\Facades\Route;

Route::post('/monthly-plans', [MonthlyPlanController::class, 'store']);

Route::get('/monthly-plans/{month}', [MonthlyPlanController::class, 'show']);
