<?php

declare(strict_types=1);

/**
 * Sends every API response in the same JSON envelope:
 *   { "success", "data", "message", "errors", "meta" }
 * so clients always know where to look, and the HTTP status code and the
 * "success" flag always tell the same story.
 */
final class Response
{
    public static function json(int $status, array $body): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            $body,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }

    public static function success(string $message, mixed $data = null, int $status = 200, ?array $meta = null): never
    {
        self::json($status, [
            'success' => true,
            'data'    => $data,
            'message' => $message,
            'errors'  => null,
            'meta'    => $meta,
        ]);
    }

    /**
     * @param array<string, string[]>|null $errors field name => list of messages
     */
    public static function error(string $message, int $status, ?array $errors = null): never
    {
        self::json($status, [
            'success' => false,
            'data'    => null,
            'message' => $message,
            'errors'  => $errors ?: null,
            'meta'    => null,
        ]);
    }

    /** 204 No Content: success with no body at all (used by DELETE and logout). */
    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }
}
