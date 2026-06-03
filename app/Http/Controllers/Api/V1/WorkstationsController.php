<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Response\ApiResponseRenderer;
use App\Source\V1\Services\Structure\WorkstationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class WorkstationsController extends BaseApiController
{
    public function __construct(
        private readonly WorkstationService $workstationService,
        ApiResponseRenderer $renderer,
    ) {
        parent::__construct($renderer);
    }

    public function list(Request $request): Response
    {
        $data = $this->workstationService->getWorkstations();

        if (empty($data)) {
            return $this->renderNotFound($request, 'Workstation list not found.');
        }

        return $this->renderResponse($request, $data, meta: [
            'count' => count($data),
        ]);
    }
}
