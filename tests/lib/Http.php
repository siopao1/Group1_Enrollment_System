<?php

declare(strict_types=1);

/**
 * A tiny HTTP client for the system test, built on cURL. Each instance is one
 * "browser": it keeps its own cookies (session) between requests and does not
 * follow redirects unless asked, so tests can check 302 responses.
 */
final class Http
{
    private CurlHandle $curl;

    public function __construct(private string $base)
    {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_COOKIEFILE, ''); // keep cookies in memory
    }

    public function get(string $path, array $options = []): HttpResponse
    {
        return $this->request('GET', $path, $options);
    }

    public function post(string $path, array $options = []): HttpResponse
    {
        return $this->request('POST', $path, $options);
    }

    /**
     * $options: query (array), headers (string[]), json (mixed), form (array),
     *           raw (string body), follow (bool: follow redirects)
     */
    public function request(string $method, string $path, array $options = []): HttpResponse
    {
        $url = $this->base . $path;
        if (!empty($options['query'])) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($options['query'], '', '&', PHP_QUERY_RFC3986);
        }

        $headers = $options['headers'] ?? [];
        $body = null;
        if (array_key_exists('json', $options)) {
            $body = json_encode($options['json'], JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
        } elseif (array_key_exists('form', $options)) {
            $body = http_build_query($options['form']);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        } elseif (array_key_exists('raw', $options)) {
            $body = $options['raw'];
        }
        $headers[] = 'Accept: ' . (str_contains($path, '/api/') ? 'application/json' : 'text/html');

        $received = [];
        curl_setopt_array($this->curl, [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPGET        => true,           // reset any previous body
            // GET and POST use cURL's own handling, so a redirect after a POST
            // is followed with a GET, like a browser does.
            CURLOPT_CUSTOMREQUEST  => in_array($method, ['GET', 'POST'], true) ? null : $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => (bool) ($options['follow'] ?? false),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$received): int {
                if (str_starts_with($line, 'HTTP/')) {
                    $received = [];                    // a new response (after a redirect)
                } elseif (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $received[strtolower(trim($name))][] = trim($value);
                }
                return strlen($line);
            },
        ]);
        if ($method === 'POST') {
            curl_setopt($this->curl, CURLOPT_POST, true);
        }
        if ($body !== null || $method === 'POST') {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $body ?? '');
        }

        $content = curl_exec($this->curl);
        $status = (int) curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);

        return new HttpResponse($content === false ? 0 : $status, $received, $content === false ? '' : $content);
    }

    /** Reads a hidden form field (e.g. the CSRF token) from a page. */
    public function formToken(string $path, string $field): string
    {
        $html = $this->get($path)->body;
        if (preg_match('/name="' . preg_quote($field, '/') . '"\s+value="([^"]+)"/', $html, $m) !== 1) {
            throw new RuntimeException("No \"$field\" field on $path");
        }
        return html_entity_decode($m[1]);
    }

    /** The current value of a cookie, or null. */
    public function cookie(string $name): ?string
    {
        foreach (curl_getinfo($this->curl, CURLINFO_COOKIELIST) as $line) {
            $parts = explode("\t", $line);
            if (($parts[5] ?? null) === $name) {
                return $parts[6] ?? '';
            }
        }
        return null;
    }
}

final class HttpResponse
{
    /** @param array<string, string[]> $headers lower-case name => values */
    public function __construct(public int $status, public array $headers, public string $body)
    {
    }

    public function header(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? [];
        return $values === [] ? null : end($values);
    }

    public function json(): array
    {
        return json_decode($this->body, true) ?? [];
    }
}
