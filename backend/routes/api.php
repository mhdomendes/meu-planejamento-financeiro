<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FundController;
use App\Http\Controllers\Api\MonthlyPlanController;
use App\Http\Controllers\Api\RecurringIncomeController;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [CategoryController::class, 'index']);
Route::put('/categories/distribution', [CategoryController::class, 'updateDistribution']);

Route::post('/monthly-plans', [MonthlyPlanController::class, 'store']);
Route::post('/monthly-plans/ensure', [MonthlyPlanController::class, 'ensure']);
Route::get('/monthly-plans/{month}', [MonthlyPlanController::class, 'show']);
Route::patch('/monthly-plans/{plan}', [MonthlyPlanController::class, 'update']);
Route::get('/recurring-income', [RecurringIncomeController::class, 'show']);
Route::put('/recurring-income', [RecurringIncomeController::class, 'update']);

Route::get('/expenses', [ExpenseController::class, 'index']);
Route::post('/expenses', [ExpenseController::class, 'store']);
Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);

Route::get('/funds', [FundController::class, 'index']);
Route::post('/funds', [FundController::class, 'storeAccount']);
Route::post('/funds/{account}/movements', [FundController::class, 'storeMovement']);
Route::post('/goals', [FundController::class, 'storeGoal']);
Route::get('/goals/{goal}/image', [FundController::class, 'showGoalImage']);
Route::patch('/goals/{goal}', [FundController::class, 'updateGoal']);
Route::delete('/goals/{goal}', [FundController::class, 'destroyGoal']);

Route::get('/teste-json', function () {
    return response()->json([
        'message' => 'Teste de resposta JSON',
        'data' => [
            'id' => 1,
            'name' => 'Exemplo',
            'created_at' => now(),
        ],
    ]);
});
