<?php

declare(strict_types=1);

use Milo\Github\Api;
use XoopsModules\Wggithub\Github\GithubClient;

require dirname(__DIR__) . '/preloads/autoloader.php';

// Loading the real subclass catches incompatible declarations before any API call.
$client = new ReflectionClass(GithubClient::class);
if (!$client->isSubclassOf(Api::class)
    || !$client->hasMethod('_get')
    || !$client->getMethod('_get')->isPublic()
    || $client->getMethod('get')->getDeclaringClass()->getName() !== Api::class
) {
    throw new RuntimeException('The module request helper must coexist with Milo Api::get().');
}

echo "PASS: GithubClient loads with Milo and exposes the module _get() helper.\n";
