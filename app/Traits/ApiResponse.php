<?php

namespace App\Traits;

trait ApiResponse
{
    protected function success($data = null, $message = '', $code = 200)
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function error($message = '', $code = 400, $details = null)
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'details' => $details,
        ], $code);
    }
}
