<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Response\ApiResponseRenderer;
use App\Source\V1\DTO\Request\KryteriaWyszukiwaniaDokumentow;
use App\Source\V1\Services\Document\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DocumentsController extends BaseApiController
{
    public function __construct(
        private readonly DocumentService $service,
        ApiResponseRenderer $renderer
    ) {
        parent::__construct($renderer);
    }

    

    public function list(Request $request): Response
    {
        $kryteriaWyszukiwania = KryteriaWyszukiwaniaDokumentow::fromPayload(
            $request->all()
        );

        $result = $this->service->getList($kryteriaWyszukiwania);

        return $this->renderResponse($request, $result['data'], meta: [
            'page'     => $kryteriaWyszukiwania->paginacja->page,
            'limit'    => $kryteriaWyszukiwania->paginacja->limit,
            'count'    => $result['count'],
            'has_prev' => $kryteriaWyszukiwania->paginacja->page > 1,
            'has_next' => count($result['data']) >= $kryteriaWyszukiwania->paginacja->limit,
        ]);
    }

    public function show(Request $request, int $id): Response
    {
    //    $data = $this->service->getDocument($id);

    //    if ($data === null) {
    //        return $this->renderNotFound($request, "Document #{$id} not found.");
    //    }

    //    return $this->renderResponse($request, $data);

        return $this->renderError(
            $request,
            'not_implemented',
            'DocumentsController::show is not implemented yet.',
            501,
        );
    }

    public function statuses(Request $request): Response
    {
        $data = $this->service->getStatuses();

        if (empty($data)) {
            return $this->renderNotFound($request, 'Case statuses not found.');
        }

        return $this->renderResponse($request, $data, meta: [
            'count' => count($data),
        ]);
    }

    public function types(Request $request): Response
    {
        $data = $this->service->getTypes();

        if (empty($data)) {
            return $this->renderNotFound($request, 'Nie znaleziono statusów dokumentów i pism.');
        }

        return $this->renderResponse($request, $data, meta: [
            'count' => count($data),
        ]);
    }

    public function process_names(Request $request): Response
    {
        $kryteriaWyszukiwania = KryteriaWyszukiwaniaDokumentow::fromPayload(
            $request->all()
        );

        $data = $this->service->getProcessNames($kryteriaWyszukiwania);

        if (empty($data)) {
            return $this->renderNotFound($request, 'Nie znaleziono nazw procesów dokumentów i pism.');
        }

        return $this->renderResponse($request, $data, meta: [
            'count' => count($data),
        ]);
    }


}
