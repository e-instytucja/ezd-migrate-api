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
        $limit  = max(1, min(200, (int) $request->query('limit', '50')));
        $page   = max(1, (int) $request->query('page', '1'));
        $offset = ($page - 1) * $limit;

        $result = $this->caseService->getList($offset, $limit);

        if (empty($result['data'])) {
            return $this->renderNotFound($request, 'Case list not found.');
        }

        return $this->renderResponse($request, $result['data'], meta: [
            'page'     => $page,
            'limit'    => $limit,
            'count'    => $result['count'],
            'has_prev' => $page > 1,
            'has_next' => count($result['data']) >= $limit*$page,
        ]);
    }

    public function show(Request $request, string $caseUid): Response
    {
        $data = $this->caseService->getCaseDetails($caseUid);

        if ($data === null) {
            return $this->renderNotFound($request, "Case '{$id}' not found.");
        }

        return $this->renderResponse($request, $data);
    }
}
