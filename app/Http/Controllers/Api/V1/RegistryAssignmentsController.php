<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Response\ApiResponseRenderer;
use App\Source\V1\DTO\Request\KryteriaPrzypisanRejestrow;
use App\Source\V1\DTO\Request\KryteriaPrzypisanRejestrowRpw;
use App\Source\V1\Services\Registry\RegistryAssignmentRpwService;
use App\Source\V1\Services\Registry\RegistryAssignmentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RegistryAssignmentsController extends BaseApiController
{
    public function __construct(
        private readonly RegistryAssignmentService $service,
        private readonly RegistryAssignmentRpwService $rpwService,
        ApiResponseRenderer $renderer,
    ) {
        parent::__construct($renderer);
    }

    public function list(Request $request): Response
    {
        try {
            $result = $this->service->getGlobalList($request->all());
        } catch (Exception $e) {
            return $this->renderGlobalListError($request, $e);
        }

        $kryteria = KryteriaPrzypisanRejestrow::fromGlobalPayload($request->all());

        return $this->renderResponse($request, $result['data'], meta: [
            'page' => $kryteria->paginacja->page,
            'limit' => $kryteria->paginacja->limit,
            'count' => $result['count'],
            'has_prev' => $kryteria->paginacja->page > 1,
            'has_next' => count($result['data']) >= $kryteria->paginacja->limit,
        ]);
    }

    public function listRpw(Request $request): Response
    {
        try {
            $result = $this->rpwService->getGlobalList($request->all());
        } catch (Exception $e) {
            return $this->renderGlobalListError($request, $e);
        }

        $kryteria = KryteriaPrzypisanRejestrowRpw::fromGlobalPayload($request->all());

        return $this->renderResponse($request, $result['data'], meta: [
            'page' => $kryteria->paginacja->page,
            'limit' => $kryteria->paginacja->limit,
            'count' => $result['count'],
            'has_prev' => $kryteria->paginacja->page > 1,
            'has_next' => count($result['data']) >= $kryteria->paginacja->limit,
        ]);
    }

    public function show(Request $request, string $registryAssignmentId): Response
    {
        $data = $this->service->getById((int) $registryAssignmentId);

        if ($data === null) {
            return $this->renderNotFound(
                $request,
                'Przypisanie rejestru #' . $registryAssignmentId . ' nie zostało znalezione.',
            );
        }

        return $this->renderResponse($request, $data);
    }

    public function showRpw(Request $request, string $registryAssignmentId): Response
    {
        $data = $this->rpwService->getById((int) $registryAssignmentId);

        if ($data === null) {
            return $this->renderNotFound(
                $request,
                'Przypisanie RPW #' . $registryAssignmentId . ' nie zostało znalezione.',
            );
        }

        return $this->renderResponse($request, $data);
    }

    public function caseAssignments(Request $request, string $caseUid): Response
    {
        return $this->renderAssignments($request, $this->service->getByCaseUid($caseUid, $request->all()));
    }

    public function dntasCaseAssignments(Request $request, string $caseUid): Response
    {
        return $this->renderAssignments($request, $this->service->getByCaseUid($caseUid, $request->all()));
    }

    public function documentAssignments(Request $request, string $documentId): Response
    {
        try {
            $data = $this->service->getByDocumentId($documentId, $request->all());
        } catch (Exception $e) {
            return $this->renderNotFound($request, $e->getMessage());
        }

        return $this->renderAssignments($request, $data);
    }

    public function documentRpwAssignments(Request $request, string $documentId): Response
    {
        try {
            $data = $this->rpwService->getByDocumentId($documentId, $request->all());
        } catch (Exception $e) {
            return $this->renderNotFound($request, $e->getMessage());
        }

        return $this->renderAssignments($request, $data);
    }

    public function types(Request $request): Response
    {
        $data = $this->service->getRegistryTypes();

        return $this->renderResponse($request, $data, meta: [
            'count' => count($data),
        ]);
    }

    /**
     * @param array<int, mixed> $data
     */
    private function renderAssignments(Request $request, array $data): Response
    {
        return $this->renderResponse($request, $data, meta: [
            'count' => count($data),
        ]);
    }

    private function renderGlobalListError(Request $request, Exception $e): Response
    {
        if (str_contains($e->getMessage(), '[err_10_appendWorkstationScope]')) {
            return $this->renderError($request, 'configuration_error', $e->getMessage(), 400);
        }

        if ($e instanceof \InvalidArgumentException) {
            return $this->renderUnprocessable($request, $e->getMessage());
        }

        throw $e;
    }
}
