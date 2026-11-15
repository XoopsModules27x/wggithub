<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Milo\Github\Http;
use XoopsModules\Wggithub\Github\GithubClient;

$reflection = new ReflectionClass(GithubClient::class);
$client = $reflection->newInstanceWithoutConstructor();
$transport = $reflection->getMethod('createHttpClient')->invoke($client);
$transport = (new ReflectionProperty(XoopsModules\Wggithub\Github\HttpClient::class, 'client'))->getValue($transport);
$caFile = ($argv[1] ?? '') === 'configured'
    ? 'vendor/milo/github-api/src/ca-chain.crt'
    : dirname(__DIR__) . '/class/Github/ca-chain.crt';
if ($transport instanceof Http\CurlClient) {
    $options = (new ReflectionProperty(Http\CurlClient::class, 'options'))->getValue($transport);
    wggithubCheck(realpath($options[CURLOPT_CAINFO]) === realpath($caFile), 'cURL must use the selected CA file.');
    wggithubCheck($options[CURLOPT_TIMEOUT] === 30, 'cURL must bound the whole request.');
    wggithubCheck(($options[CURLOPT_SSL_VERIFYPEER] ?? 1) === 1, 'Peer verification must remain enabled.');
} else {
    $options = (new ReflectionProperty(Http\StreamClient::class, 'sslOptions'))->getValue($transport);
    wggithubCheck(realpath($options['cafile']) === realpath($caFile), 'Streams must use the selected CA file.');
    wggithubCheck(($options['verify_peer'] ?? true) === true, 'Peer verification must remain enabled.');
}
wggithubCheck(is_readable(dirname(__DIR__) . '/class/Github/ca-chain.crt'), 'The fallback bundle must ship with the module.');
echo "PASS: transport honors CA selection and keeps verified HTTPS.\n";
