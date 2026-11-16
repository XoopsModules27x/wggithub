<?php

declare(strict_types=1);

namespace XoopsModules\Wggithub;

// Replace only XOOPS settings/log storage; client and database tests use real libraries.
class Helper
{
    public static array $handlers = [];

    public static function getInstance(): self
    {
        return new self();
    }

    public function getHandler(string $name): object
    {
        return self::$handlers[$name];
    }
}
