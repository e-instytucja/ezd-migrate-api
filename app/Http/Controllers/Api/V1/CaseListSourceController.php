<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Response\ApiResponseRenderer;
use App\Source\V1\Support\CaseListSource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class CaseListSourceController extends BaseApiController
{
    public function __construct(
        private readonly CaseListSource $caseListSource,
        ApiResponseRenderer $renderer,
    ) {
        parent::__construct($renderer);
    }

    public function show(Request $request): Response
    {
        return $this->renderResponse($request, $this->caseListSource->status());
    }

    public function update(Request $request): Response
    {
        $source = $request->input('source');

        if (!is_string($source) || $source === '') {
            return $this->renderUnprocessable($request, 'Wymagane pole source (legacy|mv).');
        }

        try {
            $this->caseListSource->set($source);
        } catch (InvalidArgumentException $e) {
            return $this->renderUnprocessable($request, $e->getMessage());
        } catch (RuntimeException $e) {
            return $this->renderUnprocessable($request, $e->getMessage());
        } catch (Throwable $e) {
            return $this->renderServerError($request, $e->getMessage());
        }

        return $this->renderResponse(
            $request,
            $this->caseListSource->status(),
            message: 'Zaktualizowano CASE_LIST_SOURCE w .env',
        );
    }
}
