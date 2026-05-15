<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Source\V1\Services\Case\CaseService;
use Illuminate\Http\JsonResponse;

class CasesController extends Controller
{
    public function __construct(private readonly CaseService $caseService) {}

    public function list(): JsonResponse
    {
        $data = $this->caseService->getList();

        if ($data === null) {
            return response()->json([
                'error'   => 'not_found',
                'message' => "Case list not found.",
            ], 404);
        }

        return response()->json(['data' => $data]);
    }

    public function show(int $id): JsonResponse
    {
        $data = $this->caseService->getCaseDetails(
            $id
        );

        if ($data === null) {
            return response()->json([
                'error'   => 'not_found',
                'message' => "Case list not found.",
            ], 404);
        }

        return response()->json(['data' => $data]);
    }
}
