<?php

namespace App\Helpers;

class ApiResponse
{
    /**
     * Success response with optional pagination.
     */
    public static function success($data = [], $message = '', $status = 200, $pagination = null)
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($pagination) {
            $response = array_merge($response, [
                'current_page' => $pagination->currentPage(),
                'last_page'    => $pagination->lastPage(),
                'per_page'     => $pagination->perPage(),
                'total'        => $pagination->total(),
            ]);
            $response['data'] = $pagination->items();
        } else {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    /**
     * Error response
     */
    public static function error($error = '', $status = 400, $data = [])
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if (!empty($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }
}
