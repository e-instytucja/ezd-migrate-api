<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Response\ApiResponseRenderer;
use App\Source\V1\DTO\Request\KryteriaWyszukiwania;
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
        $payload = $request->json()->all();
        $kryteriaWyszukiwania = KryteriaWyszukiwania::fromPayload($payload);

        $result = $this->caseService->getList($kryteriaWyszukiwania);

//        if (empty($result['data'])) {
//            return $this->renderNotFound($request, 'Case list not found.');
//        }

        return $this->renderResponse($request, $result['data'], meta: [
            'page'     => $kryteriaWyszukiwania->paginacja->page,
            'limit'    => $kryteriaWyszukiwania->paginacja->limit,
            'count'    => $result['count'],
            'has_prev' => $kryteriaWyszukiwania->paginacja->page > 1,
            'has_next' => count($result['data']) >= $kryteriaWyszukiwania->paginacja->limit,
        ]);
    }

    public function show(Request $request, string $caseUid): Response
    {
        $data = $this->caseService->getCaseDetails($caseUid);

        if ($data === null) {
            return $this->renderNotFound($request, "Case '{$caseUid}' not found.");
        }

        return $this->renderResponse($request, $data);
    }

    public function statuses(Request $request): Response
    {
        $data = $this->caseService->getStatuses();

        if (empty($data)) {
            return $this->renderNotFound($request, 'Case statuses not found.');
        }

        return $this->renderResponse($request, $data, meta: [
            'count' => count($data),
        ]);
    }
}
