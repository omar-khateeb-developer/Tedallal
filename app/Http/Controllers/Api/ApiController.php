<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class ApiController
{
    protected function success(JsonResource|array $data, int $status = 200): JsonResponse
    {
        $payload = $data instanceof JsonResource
            ? $data->response()->getData(true)
            : $data;

        return response()->json([
            'success' => true,
            'data' => $payload['data'] ?? $payload,
        ], $status);
    }

    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
