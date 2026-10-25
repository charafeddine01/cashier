<?php
declare(strict_types=1);

namespace OmniPOS\Core;

class Response
{
    public static function success(mixed $data = null, int $statusCode = 200, string $message = 'Success'): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');

        echo json_encode([
            'success'   => true,
            'message'   => $message,
            'data'      => $data,
            'error'     => null,
            'code'      => $statusCode,
            'timestamp' => time(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(string $message, int $statusCode = 400, mixed $errorDetails = null, string $errorCode = 'BAD_REQUEST'): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');

        echo json_encode([
            'success'     => false,
            'message'     => $message,
            'data'        => null,
            'error'       => [
                'code'    => $errorCode,
                'message' => $message,
                'details' => $errorDetails,
            ],
            'code'        => $statusCode,
            'timestamp'   => time(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
