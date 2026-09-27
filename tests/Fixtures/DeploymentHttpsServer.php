<?php

declare(strict_types=1);

// Standalone loopback TLS fixture: no application bootstrap, credentials or DB.
[$script, $root, $oldSha, $newSha, $otherSha] = $argv;
$context = stream_context_create(['ssl' => [
    'local_cert' => $root . '/cert.pem',
    'local_pk' => $root . '/key.pem',
    'verify_peer' => false, // No client certificate is requested; curl verifies ours.
]]);
$server = stream_socket_server('tls://127.0.0.1:0', $errno, $error,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if ($server === false) {
    throw new RuntimeException('Cannot start HTTPS fixture: ' . $error);
}
fwrite(STDOUT, stream_socket_get_name($server, false) . "\n");
fflush(STDOUT);
$changed = false;
$transientFailed = false;

while (true) {
    $client = stream_socket_accept($server, 30);
    if ($client === false) {
        continue;
    }
    stream_set_timeout($client, 5);
    $request = fgets($client);
    if ($request === false) {
        fclose($client);
        continue;
    }
    [$method, $url] = explode(' ', trim($request));
    $headers = [];
    while (($line = fgets($client)) !== false && trim($line) !== '') {
        [$name, $value] = explode(':', $line, 2);
        $headers[strtolower($name)] = trim($value);
    }
    // PHP caches realpath results, whereas the test deliberately changes symlinks.
    clearstatcache(true);
    $public = $root . '/public_html';
    $active = basename(dirname((string) realpath($public)));
    $path = parse_url($url, PHP_URL_PATH);
    file_put_contents($root . '/requests.jsonl', json_encode([
        'method' => $method, 'url' => $url, 'active' => $active, 'headers' => $headers,
    ], JSON_THROW_ON_ERROR) . "\n", FILE_APPEND);

    $status = 200;
    $contentType = 'text/html; charset=UTF-8';
    $extra = [];
    $body = '<html><h1 id="hero-title">BeneHom</h1></html>';
    if ($path === '/blog') {
        $body = '<html><h1 id="blog-title">Blog BeneHom</h1></html>';
    } elseif ($path === '/css/app.min.css') {
        $contentType = 'text/css; charset=UTF-8';
        $body = file_get_contents($public . '/css/app.min.css');
    } elseif ($path !== '/') {
        $status = 404;
    }
    $fault = trim(file_get_contents($root . '/fault'));
    if ($active === substr($newSha, 0, 7)) {
        if (($fault === 'home' && $path === '/') || ($fault === 'blog' && $path === '/blog')) {
            $status = 503;
        } elseif ($fault === 'html' && $path === '/') {
            $body = '<html>Generic error page</html>';
        } elseif ($fault === 'redirect' && $path === '/') {
            $status = 302;
            $extra = ['Location' => '/blog'];
        } elseif ($path === '/css/app.min.css') {
            if ($fault === 'empty-css') {
                $body = '';
            } elseif ($fault === 'css-type') {
                $contentType = 'text/html';
            } elseif ($fault === 'old-css') {
                $body = file_get_contents($root . '/releases/' . substr($oldSha, 0, 7) . '/public/css/app.min.css');
            }
        }
        if ($fault === 'cache-hit') {
            $extra = ['X-LiteSpeed-Cache' => 'hit'];
        } elseif ($fault === 'cache-age') {
            $extra = ['Age' => '60'];
        } elseif ($fault === 'transient' && !$transientFailed) {
            $transientFailed = true;
            $status = 503;
        } elseif (in_array($fault, ['external', 'same-target'], true) && !$changed) {
            $changed = true;
            $target = substr($fault === 'external' ? $otherSha : $newSha, 0, 7);
            symlink('releases/' . $target . '/public', $root . '/admin-link');
            rename($root . '/admin-link', $public);
        } elseif ($fault === 'sha-change') {
            file_put_contents($root . '/releases/' . substr($newSha, 0, 7) . '/RELEASE_SHA', $otherSha . "\n");
        } elseif ($fault === 'term' && !$changed) {
            $changed = true;
            $pid = (int) file_get_contents($root . '/activation-pid');
            if ($pid <= 1 || !posix_kill($pid, SIGTERM)) {
                throw new RuntimeException('Cannot signal the local activation process.');
            }
        }
    }
    if ($fault === 'unavailable') {
        $status = 503;
    }
    $response = "HTTP/1.1 {$status} Fixture\r\nContent-Type: {$contentType}\r\n"
        . 'Content-Length: ' . strlen($body) . "\r\nConnection: close\r\n";
    foreach ($extra as $name => $value) {
        $response .= "{$name}: {$value}\r\n";
    }
    $response .= "\r\n" . $body;
    while ($response !== '') {
        $written = fwrite($client, $response);
        if ($written === false || $written === 0) {
            throw new RuntimeException('Cannot write HTTPS fixture response.');
        }
        $response = substr($response, $written);
    }
    fclose($client);
}
