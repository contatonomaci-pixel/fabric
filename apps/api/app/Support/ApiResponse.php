<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function page(LengthAwarePaginator $page): JsonResponse
    {
        return response()->json(['data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()], 'request_id' => request()->attributes->get('request_id')]);
    }

    public static function data(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'request_id' => request()->attributes->get('request_id')], $status);
    }

    public static function error(string $message, string $code, int $status, array $errors = []): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code, 'errors' => (object) $errors, 'request_id' => request()->attributes->get('request_id')], $status);
    }
}
