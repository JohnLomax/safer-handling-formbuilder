<?php

use App\Http\Controllers\Api\TrainingMatrixController;
use Illuminate\Support\Facades\Route;

Route::get('/training-matrix', [TrainingMatrixController::class, 'index']);
