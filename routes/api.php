<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CasesController;
use App\Http\Controllers\Api\V1\DocumentsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 – EZD Integration
|--------------------------------------------------------------------------
|
| Endpointy są dostępne pod prefiksem /api/v1/ (prefix /api dodaje Laravel).
|

*/

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function () {

        Route::get(
            '/cases',
            [CasesController::class, 'list']
        )
            ->name('cases.list');

        Route::get(
            '/cases/{id}',
            [CasesController::class, 'show']
        )
            ->whereNumber('id')
            ->name('cases.show');

        Route::get(
            '/documents',
            [DocumentsController::class, 'list']
        )
            ->name('cases.list');

        Route::get(
            '/documents/{id}',
            [DocumentsController::class, 'show']
        )
            ->whereNumber('id')
            ->name('documents.show');
    });
