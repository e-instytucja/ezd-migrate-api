<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\CasesController;
use App\Http\Controllers\Api\V1\DntasController;
use App\Http\Controllers\Api\V1\DocumentsController;
use App\Http\Controllers\Api\V1\RegistryAssignmentsController;
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

        Route::match(['get', 'post'], '/cases/{caseUid}/registry-assignments', [RegistryAssignmentsController::class, 'caseAssignments'])
            ->where('caseUid', '[a-f0-9]{13}')
            ->name('cases.registry-assignments');


        /*
        |--------------------------------------------------------------------------
        | DNTAS
        |--------------------------------------------------------------------------
        */

        Route::match(['get', 'post'], '/dntas', [DntasController::class, 'list'])
            ->name('dntas.list');

        Route::get('/dntas/statuses', [DntasController::class, 'statuses'])
            ->name('dntas.statuses');

        Route::match(['get', 'post'], '/dntas/{caseUid}', [DntasController::class, 'show'])
            ->where('caseUid', '[a-f0-9]{13}')
            ->name('dntas.show');

//        Route::get('/cases/{caseUid}/attachments', [AttachmentController::class, 'caseAttachments'])
        Route::match(['get', 'post'], '/dntas/{caseUid}/attachments', [AttachmentController::class, 'dntasCaseAttachments'])
            ->where('caseUid', '[a-f0-9]{13}')
            ->name('dntas.attachments');

        Route::match(['get', 'post'], '/dntas/{caseUid}/registry-assignments', [RegistryAssignmentsController::class, 'dntasCaseAssignments'])
            ->where('caseUid', '[a-f0-9]{13}')
            ->name('dntas.registry-assignments');


        /*
        |--------------------------------------------------------------------------
        | Documents
        |--------------------------------------------------------------------------
        */

        Route::match(['get', 'post'], '/documents', [DocumentsController::class, 'list'])
            ->name('documents.list');

        Route::match(['get', 'post'],'/documents/{documentId}', [DocumentsController::class, 'show'])
            ->where('documentId', '(\d+|[a-f0-9]{13})') //@TODO - zmienić wartość z pismo_uid/sprawa_uid na instanceId - będzie zawsze number
            ->name('documents.show');

        Route::match(['get', 'post'], '/documents/{documentId}/attachments', [AttachmentController::class, 'documentAttachments'])
            ->where('documentId', '(\d+|[a-f0-9]{13})') //@TODO - zmienić wartość z pismo_uid/sprawa_uid na instanceId - będzie zawsze number
            ->name('documents.attachments');

        Route::match(['get', 'post'], '/documents/{documentId}/registry-assignments', [RegistryAssignmentsController::class, 'documentAssignments'])
            ->where('documentId', '(\d+|[a-f0-9]{13})')
            ->name('documents.registry-assignments');

        Route::match(['get', 'post'], '/documents/{documentId}/registry-assignments-rpw', [RegistryAssignmentsController::class, 'documentRpwAssignments'])
            ->where('documentId', '(\d+|[a-f0-9]{13})')
            ->name('documents.registry-assignments-rpw');

        Route::match(['get', 'post'], '/documents/statuses', [DocumentsController::class, 'statuses'])
            ->name('documents.statuses');

        Route::match(['get', 'post'], '/documents/types', [DocumentsController::class, 'types'])
            ->name('documents.types');

        Route::match(['get', 'post'], '/documents/process_names', [DocumentsController::class, 'process_names'])
            ->name('documents.process_names');


        /*
        |--------------------------------------------------------------------------
        | Registries
        |--------------------------------------------------------------------------
        */

        Route::match(['get', 'post'], '/registry-assignments', [RegistryAssignmentsController::class, 'list'])
            ->name('registry-assignments.list');

        Route::match(['get', 'post'], '/registry-assignments-rpw', [RegistryAssignmentsController::class, 'listRpw'])
            ->name('registry-assignments-rpw.list');

        Route::match(['get', 'post'], '/registry-assignments/{registryAssignmentId}', [RegistryAssignmentsController::class, 'show'])
            ->where('registryAssignmentId', '\d+')
            ->name('registry-assignments.show');

        Route::match(['get', 'post'], '/registry-assignments-rpw/{registryAssignmentId}', [RegistryAssignmentsController::class, 'showRpw'])
            ->where('registryAssignmentId', '\d+')
            ->name('registry-assignments-rpw.show');

        Route::match(['get', 'post'], '/registries/types', [RegistryAssignmentsController::class, 'types'])
            ->name('registries.types');


        /*
        |--------------------------------------------------------------------------
        | Attachments
        |--------------------------------------------------------------------------
        */

//        Route::get('/attachments/{attachmentUid}', [AttachmentController::class, 'show'])
        Route::match(['get', 'post'], '/attachments/epuap/{zalacznikUid}/{fileId}', [AttachmentController::class, 'showEpuapWithZalacznikUid'])
            ->where('zalacznikUid', '[a-f0-9]{13}')
            ->where('fileId', '[a-zA-Z0-9._-]+')
            ->name('attachments.epuap.show.with_zalacznik_uid');

        Route::match(['get', 'post'], '/attachments/epuap/{fileId}', [AttachmentController::class, 'showEpuap'])
            ->where('fileId', '[a-zA-Z0-9._-]+')
            ->name('attachments.epuap.show');

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
