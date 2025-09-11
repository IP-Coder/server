<?php

namespace App\Support;

trait ApiResponses
{
    protected function ok(string $code, string $message, array $data = [], int $http = 200)
    {
        return response()->json([
            'ok'      => true,
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ], $http);
    }

    protected function fail(string $code, string $message, array $details = [], int $http = 422)
    {
        return response()->json([
            'ok'      => false,
            'code'    => $code,
            'message' => $message,
            'details' => $details,
        ], $http);
    }
}
