<?php

final class MailWatchApi
{
    public const ERROR_CONTRACT = 'mailwatch.api.error.v1';

    public static function isAuthorized(): bool
    {
        $receivedKey = $_SERVER['HTTP_X_MAILWATCH_API_KEY'] ?? null;
        if (
            !is_string($receivedKey)
            || !defined('API_KEY')
            || !is_string(API_KEY)
            || '' === API_KEY
        ) {
            return false;
        }

        return hash_equals(API_KEY, $receivedKey);
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function respond(int $status, array $body): never
    {
        http_response_code($status);
        echo json_encode($body, JSON_THROW_ON_ERROR);
        exit;
    }

    public static function error(int $status, string $code, string $message): never
    {
        self::respond($status, [
            'contract' => self::ERROR_CONTRACT,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
    }
}
