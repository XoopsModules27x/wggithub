<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/fixtures/Helper.php';
require_once dirname(__DIR__) . '/preloads/autoloader.php';

function wggithubCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new AssertionError($message);
    }
}
