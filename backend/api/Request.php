<?php

declare(strict_types=1);

/**
 * Reads the parts of the HTTP request the API needs: method, path,
 * query-string values, headers and the JSON body.
 */
final class Request
{
    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    /**
     * The path after "/api/", e.g. "students/2026-00125".
     * Works whether the app runs at the server root or in a subfolder.
     */
    public static function apiPath(): string
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

        if (preg_match('#/api(?:/(.*))?$#', $path, $match) !== 1) {
            return '';
        }

        return trim(rawurldecode($match[1] ?? ''), '/');
    }

    /** The URL prefix up to and including "/api", e.g. "/api". */
    public static function apiBase(): string
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $position = strpos($path, '/api');

        return $position === false ? '/api' : substr($path, 0, $position) . '/api';
    }

    /** A query-string value, trimmed. Arrays (e.g. ?status[]=x) are ignored. */
    public static function query(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;

        // Apache can hide the Authorization header; public/api/.htaccess passes it on as this.
        if ($value === null && strcasecmp($name, 'Authorization') === 0) {
            $value = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        }

        return is_string($value) ? trim($value) : null;
    }

    /**
     * Decodes the JSON request body into an associative array.
     * 415 when the body is not declared as JSON, 400 when it is not valid JSON.
     */
    public static function json(): array
    {
        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        if (stripos($contentType, 'application/json') !== 0) {
            Response::error('Content-Type must be application/json.', 415);
        }

        $raw = (string) file_get_contents('php://input');

        try {
            $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            Response::error('Request body must be valid JSON.', 400);
        }

        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            Response::error('Request body must be a JSON object.', 400);
        }

        return $data;
    }
}
