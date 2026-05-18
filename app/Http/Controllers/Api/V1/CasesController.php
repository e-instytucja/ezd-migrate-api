<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Response\ApiResponseRenderer;
use App\Source\V1\Services\Case\CaseService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CasesController extends BaseApiController
{
    public function __construct(
        private readonly CaseService $caseService,
        ApiResponseRenderer $renderer,
    ) {
        parent::__construct($renderer);
    }

    public function list(Request $request): Response
    {
        $data = $this->caseService->getList();

        if (empty($data)) {
            return $this->renderNotFound($request, 'Case list not found.');
        }

        return $this->renderResponse($request, $data);
    }

    public function show(Request $request, int $id): Response
    {
        $data = $this->caseService->getCaseDetails($id);

        if ($data === null) {
            return $this->renderNotFound($request, "Case '{$id}' not found.");
        }

        return $this->renderResponse($request, $data);
    }
}
