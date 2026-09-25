<?php

namespace Shared;

/**
 * Helper chuan hoa response JSON tra ve cho client.
 */
class Response
{
    public static function json($response, $data, int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $status < 400,
            'data' => $data,
        ]);
        return $response;
    }

    public static function error($response, string $message, int $status = 400)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $message,
        ]);
        return $response;
    }
}
