<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/bootstrap.php';

// Run with the web server's PHP configuration; no credentials or database needed.
$reflection = new ReflectionClass(XoopsModules\Wggithub\Github\GithubClient::class);
$client = $reflection->newInstanceWithoutConstructor();
$api = new Milo\Github\Api($reflection->getMethod('createHttpClient')->invoke($client));
$response = $api->get('/rate_limit');
if ($response->getCode() !== 200 || !isset($api->decode($response)->resources)) {
    throw new AssertionError('Expected a verified HTTPS response from the GitHub API.');
}

echo "PASS: GitHub HTTPS request succeeds with certificate verification enabled.\n";
