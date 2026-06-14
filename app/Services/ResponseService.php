<?php

namespace App\Services;

class ResponseService
{
    /**
     * Return a success response.
     *
     * @param  mixed  $data
     * @return \Illuminate\Http\JsonResponse
     */
    public static function success($data = null, string $message = 'Success', int $statusCode = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Return an error response.
     *
     * @param  mixed  $errors
     * @return \Illuminate\Http\JsonResponse
     */
    public static function error(string $message = 'Error', int $statusCode = 400, $errors = null)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }

    /**
     * Return a not found response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public static function notFound(string $message = 'Resource not found')
    {
        return response()->json([
            'status' => 'not found',
            'message' => $message,
        ], 404);
    }
}
