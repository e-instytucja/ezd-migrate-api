<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Response\ApiResponseRenderer;
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

    public function show(Request $request, int $id): Response
    {
//        $data = $this->service->getDocument($id);
//
//        if ($data === null) {
//            return $this->renderNotFound($request, "Document #{$id} not found.");
//        }
//
//        return $this->renderResponse($request, $data);

        return $this->renderError(
            $request,
            'not_implemented',
            'DocumentsController::show is not implemented yet.',
            501,
        );
    }
}
