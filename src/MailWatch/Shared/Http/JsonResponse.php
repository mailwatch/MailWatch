<?php

declare(strict_types=1);

namespace MailWatch\Shared\Http;

final class JsonResponse
{
    public const ERROR_CONTRACT = 'mailwatch.api.error.v1';

    /**
     * @param array<string, mixed> $body
     * @param list<string>         $headers
     */
    public static function send(int $status, array $body, array $headers = []): never
    {
        header('Content-Type: application/json; charset=UTF-8');
        foreach ($headers as $header) {
            header($header);
        }

        http_response_code($status);
        echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @param list<string> $headers
     */
    public static function empty(int $status, array $headers = []): never
    {
        header('Content-Type: application/json; charset=UTF-8');
        foreach ($headers as $header) {
            header($header);
        }

        http_response_code($status);
        exit;
    }

    /**
     * @param list<string> $headers
     */
    public static function error(int $status, string $code, string $message, array $headers = []): never
    {
        self::send($status, [
            'contract' => self::ERROR_CONTRACT,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $headers);
    }
}
