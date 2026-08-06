<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Response\ApiResponseRenderer;
use App\Source\V1\Support\Database\EzdDatabasePrivilegesGuard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class DatabasePrivilegesController extends BaseApiController
{
    public function __construct(
        private readonly EzdDatabasePrivilegesGuard $privilegesGuard,
        ApiResponseRenderer $renderer,
    ) {
        parent::__construct($renderer);
    }

    public function show(Request $request): Response
    {
        return $this->renderResponse($request, $this->privilegesGuard->audit());
    }
}
