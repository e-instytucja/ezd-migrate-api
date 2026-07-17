<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Response\ApiResponseRenderer;
use App\Source\V1\Support\MaterializedViews\MaterializedViewsMode;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class MaterializedViewsController extends BaseApiController
{
    public function __construct(
        private readonly MaterializedViewsMode $materializedViewsMode,
        ApiResponseRenderer $renderer,
    ) {
        parent::__construct($renderer);
    }

    public function show(Request $request): Response
    {
        return $this->renderResponse($request, $this->materializedViewsMode->status());
    }

    public function update(Request $request): Response
    {
        try {
            $enabled = MaterializedViewsMode::parseEnabled($request->input('enabled'));
            $this->materializedViewsMode->set($enabled);
        } catch (InvalidArgumentException $e) {
            return $this->renderUnprocessable($request, $e->getMessage());
        } catch (RuntimeException $e) {
            return $this->renderUnprocessable($request, $e->getMessage());
        } catch (Throwable $e) {
            return $this->renderServerError($request, $e->getMessage());
        }

        return $this->renderResponse(
            $request,
            $this->materializedViewsMode->status(),
            message: 'Zaktualizowano USE_MATERIALIZED_VIEWS w .env',
        );
    }
}
