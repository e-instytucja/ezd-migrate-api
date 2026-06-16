<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Response\ApiResponseRenderer;
use App\Source\V1\DTO\Request\KryteriaWyszukiwaniaDokumentow;
use App\Source\V1\DTO\Request\KryteriaWyszukiwaniaSpraw;
use App\Source\V1\Services\Case\CaseService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

abstract class AbstractCaseController extends BaseApiController
{
    public function __construct(
        protected readonly CaseService $caseService,
        ApiResponseRenderer $renderer,
    ) {
        parent::__construct($renderer);
    }

    abstract protected function dntas(): int;

    public function list(Request $request): Response
    {
        $kryteriaWyszukiwania = KryteriaWyszukiwaniaSpraw::fromPayload(
            $request->all(),
            $this->dntas(),
        );

        $result = $this->caseService->getList($kryteriaWyszukiwania);

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
        $requestAll = $request->all();
        $requestAll['filtry']['sprawa_uid'] = $caseUid;
        $kryteriaWyszukiwania = KryteriaWyszukiwaniaSpraw::fromPayload(
            $requestAll,
            $this->dntas(),
        );
        $data = $this->caseService->getCaseDetails($kryteriaWyszukiwania, $this->dntas());

        if ($data === null) {
            return $this->renderNotFound($request, "Case '{$caseUid}' not found.");
        }

        return $this->renderResponse($request, $data);
    }

    public function statuses(Request $request): Response
    {
        $data = $this->caseService->getStatuses($this->dntas());

        if (empty($data)) {
            return $this->renderNotFound($request, 'Case statuses not found.');
        }

        return $this->renderResponse($request, $data, meta: [
            'count' => count($data),
        ]);
    }
}
