<?php

/**
 * Access control for the JSON API endpoints in public/api/.
 *
 * Call requireApiRole([...roles]) at the top of an endpoint, before any
 * database work. It answers with a JSON error and stops the script when:
 *   401 — nobody is signed in
 *   419 — a request that changes data (POST, PUT, PATCH, DELETE) has no valid
 *         X-CSRF-Token header
 *   403 — the signed-in user's role is not allowed to do this
 *
 * The browser's buttons are hidden by role too, but that is only for
 * convenience: this check is the one that actually protects the data.
 */

require_once __DIR__ . '/Auth.php';

function apiDeny(string $message, int $statusCode): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'data'    => null,
        'message' => $message,
        'errors'  => null,
        'meta'    => null,
    ]);
    exit;
}

function requireApiRole(array $allowedRoles): array
{
    Auth::start();
    $user = Auth::user();

    if ($user === null) {
        apiDeny('Please sign in to continue.', 401);
    }

    $changesData = !in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true);
    if ($changesData && !Auth::verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        apiDeny('Invalid or missing CSRF token. Refresh the page and try again.', 419);
    }

    if (!in_array($user['role'], $allowedRoles, true)) {
        apiDeny('You do not have permission to do this.', 403);
    }

    // Done with the session for this request; release its lock so the
    // browser's other API calls are not forced to wait for this one.
    session_write_close();

    return $user;
}
