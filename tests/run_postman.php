<?php
// Runs the Postman collection without Postman (a minimal stand-in for its Runner): executes each request in order,
// substitutes {{variables}}, stores tokens/studentId like the collection's
// scripts do, and compares the status code with the one the tests expect.
// Usage: php tests/run_postman.php [docs/postman/Group1_Enrollment_API.postman_collection.json]

$c = json_decode(file_get_contents($argv[1] ?? __DIR__ . "/../docs/postman/Group1_Enrollment_API.postman_collection.json"), true, 512, JSON_THROW_ON_ERROR);
$vars = [];
foreach ($c['variable'] as $v) {
    $vars[$v['key']] = $v['value'];
}
$sub = function (string $s) use (&$vars): string {
    return preg_replace_callback('/\{\{(\w+)\}\}/', fn ($m) => $vars[$m[1]] ?? $m[0], $s);
};

$pass = 0;
$fail = 0;
foreach ($c['item'] as $folder) {
    echo "== {$folder['name']}\n";
    foreach ($folder['item'] as $item) {
        $r = $item['request'];
        $scripts = [];
        foreach ($item['event'] as $e) {
            $scripts[$e['listen']] = implode("\n", $e['script']['exec']);
        }
        if (isset($scripts['prerequest']) && str_contains($scripts['prerequest'], 'studentId')) {
            $vars['studentId'] = '2099-' . random_int(10000, 99999);
        }
        preg_match('/have\.status\((\d+)\)/', $scripts['test'], $m);
        $expected = (int) $m[1];

        $url = $sub($r['url']['raw']);
        [$base, $query] = array_pad(explode('?', $url, 2), 2, '');
        $url = $base . ($query !== "" ? "?" . str_replace([" ", "–"], ["%20", "%E2%80%93"], $query) : "");

        $headers = [];
        foreach ($r['header'] as $h) {
            $headers[] = $h['key'] . ': ' . $sub($h['value']);
        }
        if ($r['auth']['type'] === 'bearer') {
            $headers[] = 'Authorization: Bearer ' . $sub($r['auth']['bearer'][0]['value']);
        }
        $opts = ['http' => ['method' => $r['method'], 'header' => implode("\r\n", $headers), 'ignore_errors' => true]];
        if (isset($r['body'])) {
            $opts['http']['content'] = $sub($r['body']['raw']);
        }

        $body = file_get_contents($url, false, stream_context_create($opts));
        preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0], $sm);
        $status = (int) $sm[1];
        $json = json_decode((string) $body, true);

        $notes = [];
        if (str_contains($scripts['test'], 'text/csv')) {
            $ct = preg_grep('/^Content-Type:/i', $http_response_header);
            $notes[] = $ct ? trim(reset($ct)) : 'NO CONTENT-TYPE';
        } elseif ($status !== 204 && !is_array($json)) {
            $notes[] = 'NOT JSON';
        }
        if ($status === 204 && $body !== '') {
            $notes[] = '204 HAS BODY';
        }
        if (str_starts_with($item['name'], 'Login – ') && $status === 200) {
            $vars[$json['data']['user']['role'] . 'Token'] = $json['data']['token'];
        }
        if (str_contains($scripts['test'], 'Location')) {
            $loc = preg_grep('/^Location:/i', $http_response_header);
            $notes[] = $loc ? trim(reset($loc)) : 'NO LOCATION';
        }
        if (str_contains($scripts['test'], 'Allow')) {
            $allow = preg_grep('/^Allow:/i', $http_response_header);
            $notes[] = $allow ? trim(reset($allow)) : 'NO ALLOW';
        }
        if ($status === 422 && is_array($json['errors'] ?? null)) {
            $notes[] = 'errors: ' . implode(',', array_keys($json['errors']));
        }

        $ok = $status === $expected && !in_array('NOT JSON', $notes, true) && !in_array('204 HAS BODY', $notes, true);
        $ok ? $pass++ : $fail++;
        printf("  %s %-52s expected %d got %d %s\n", $ok ? 'PASS' : 'FAIL', $item['name'], $expected, $status, implode(' | ', $notes));
    }
}
echo "\n{$pass} passed, {$fail} failed\n";
