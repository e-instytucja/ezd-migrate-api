<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Response\ApiResponseRenderer;
use App\Source\V1\Services\Document\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DocumentsController extends BaseApiController
{
    public function __construct(
        private readonly DocumentService $service,
        ApiResponseRenderer $renderer
    ) {
        parent::__construct($renderer);
    }

    public function show(Request $request, int $id): JsonResponse
    {
//        $data = $this->service->getDocument($id);
//
//        if ($data === null) {
//            return response()->json([
//                'error'   => 'not_found',
//                'message' => "Document #{$id} not found.",
//            ], 404);
//        }
//
//        return response()->json(['data' => $data]);
        return response()->json([
            'error' => 'not_implemented',
            'message' => 'DocumentsController::show is not implemented yet.',
        ], 501);
    }
}
