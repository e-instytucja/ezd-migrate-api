<?php

namespace App\Http\Controllers\Api\V1;

use App\Source\V1\Services\Attachment\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AttachmentController
{
    public function __construct(private readonly AttachmentService $service) {}

    public function show(string $token): JsonResponse
    {
        $data = $this->service->getAttachmentContent($token);

        if ($data === null) {
            return response()->json([
                'error'   => 'not_found',
                'message' => "file not found.",
            ], 404);
        }

        return response()->json(['data' => $data]);
    }

    public function details(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uids' => ['required', 'array'],
            'uids.*' => ['string'],
        ]);

        $data = $this->service->getAttachmentDetails(
            $validated['uids']
        );

        return response()->json([
            'data' => $data,
        ]);
    }


}