<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Source\V1\Services\Document\DocumentService;
use Illuminate\Http\JsonResponse;

class DocumentsController extends Controller
{
    public function __construct(private readonly DocumentService $service) {}

    public function show(int $id): JsonResponse
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
    }
}
