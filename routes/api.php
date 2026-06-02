<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\CasesController;
use App\Http\Controllers\Api\V1\DocumentsController;
use App\Http\Controllers\Api\V1\WorkstationsController;
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

        /*
        |--------------------------------------------------------------------------
        | Cases
        |--------------------------------------------------------------------------
        */

        Route::match(['get', 'post'], '/cases', [CasesController::class, 'list'])
//        Route::get('/cases', [CasesController::class, 'list'])
            ->name('cases.list');

        Route::get('/cases/statuses', [CasesController::class, 'statuses'])
            ->name('cases.statuses');

//        Route::get('/cases/{caseUid}', [CasesController::class, 'show'])
        Route::match(['get', 'post'], '/cases/{caseUid}', [CasesController::class, 'show'])
            ->where('caseUid', '[a-f0-9]{13}')
            ->name('cases.show');

//        Route::get('/cases/{caseUid}/attachments', [AttachmentController::class, 'caseAttachments'])
        Route::match(['get', 'post'], '/cases/{caseUid}/attachments', [AttachmentController::class, 'caseAttachments'])
            ->where('caseUid', '[a-f0-9]{13}')
            ->name('cases.attachments');


        /*
        |--------------------------------------------------------------------------
        | Documents
        |--------------------------------------------------------------------------
        */

        Route::match(['get', 'post'], '/documents', [CasesController::class, 'list'])
//        Route::get('/documents', [DocumentsController::class, 'list'])
            ->name('documents.list');

        Route::get('/documents/{documentId}', [DocumentsController::class, 'show'])
            ->whereNumber('documentId')
            ->name('documents.show');

        Route::get('/documents/{documentId}/attachments', [AttachmentController::class, 'documentAttachments'])
            ->whereNumber('documentId')
            ->name('documents.attachments');


        /*
        |--------------------------------------------------------------------------
        | Attachments
        |--------------------------------------------------------------------------
        */

//        Route::get('/attachments/{attachmentUid}', [AttachmentController::class, 'show'])
        Route::match(['get', 'post'], '/attachments/{attachmentUid}', [AttachmentController::class, 'show'])
            ->where('attachmentUid', '[a-f0-9]{13}')
            ->name('attachments.show');


        /*
        |--------------------------------------------------------------------------
        | Workstations
        |--------------------------------------------------------------------------
        */

        Route::get('/workstations', [WorkstationsController::class, 'list'])
            ->name('workstations.list');
    });
