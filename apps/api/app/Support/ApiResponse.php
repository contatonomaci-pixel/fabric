<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function data(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'request_id' => request()->attributes->get('request_id')], $status);
    }

    public static function error(string $message, string $code, int $status, array $errors = []): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code, 'errors' => (object) $errors, 'request_id' => request()->attributes->get('request_id')], $status);
    }
}
