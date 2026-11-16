<?php

declare(strict_types=1);

namespace XoopsModules\Wggithub\Github;

use Milo\Github\Http;

/** Applies the module's redirect policy around Milo's unmodified transports. */
final class HttpClient extends Http\AbstractClient
{
    public function __construct(private Http\AbstractClient $client)
    {
        $this->client->maxRedirects = 0;
    }

    #[\Override]
    protected function process(Http\Request $request): Http\Response
    {
        $response = $this->client->request($request);
        if (!in_array($response->getCode(), $this->redirectCodes, true) || !$response->hasHeader('Location')) {
            return $response;
        }
        $url = self::resolveUrl($request->getUrl(), $response->getHeader('Location'));
        $source = self::origin($request->getUrl());
        $target = self::origin($url);
        if ($source[0] === 'https' && $target[0] !== 'https') {
            throw new Http\BadResponseException('HTTPS redirects must not downgrade transport.');
        }
        if ($source !== $target) {
            // The inherited loop copies this request's headers into the next request.
            $request->setHeader('Authorization', null);
            $request->setHeader('Cookie', null);
        }

        return new Http\Response($response->getCode(), array_replace($response->getHeaders(), ['location' => $url]), $response->getContent());
    }

    private static function origin(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || preg_match('/[\x00-\x20\x7f]/', $url)
        ) {
            throw new Http\BadResponseException('Invalid HTTP redirect URL.');
        }
        return [$scheme, strtolower($parts['host']), $parts['port'] ?? ($scheme === 'https' ? 443 : 80)];
    }

    private static function resolveUrl(string $base, string $location): string
    {
        $reference = parse_url($location);
        $source = parse_url($base);
        if ($reference === false || $source === false) {
            throw new Http\BadResponseException('Invalid HTTP redirect URL.');
        }
        if (isset($reference['scheme'])) {
            return $location;
        }
        if (str_starts_with($location, '//')) {
            return $source['scheme'] . ':' . $location;
        }
        $path = $reference['path'] ?? '';
        $query = $reference['query'] ?? null;
        if ($path === '') {
            $path = $source['path'] ?? '/';
            $query ??= $source['query'] ?? null;
        } else {
            if (!str_starts_with($path, '/')) {
                $basePath = $source['path'] ?? '/';
                $path = substr($basePath, 0, strrpos($basePath, '/') + 1) . $path;
            }
            $path = self::removeDotSegments($path);
        }
        return $source['scheme'] . '://' . $source['host'] . (isset($source['port']) ? ':' . $source['port'] : '')
            . $path . ($query !== null ? '?' . $query : '')
            . (isset($reference['fragment']) ? '#' . $reference['fragment'] : '');
    }

    /** RFC 3986 section 5.2.4; a trailing "." or ".." segment leaves a directory path. */
    private static function removeDotSegments(string $path): string
    {
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                if (count($segments) > 1) {
                    array_pop($segments);
                }
            } elseif ($segment !== '.') {
                $segments[] = $segment;
            }
        }
        $result = implode('/', $segments);
        if (str_ends_with($path, '/.') || str_ends_with($path, '/..')) {
            $result .= '/';
        }

        return $result === '' ? '/' : $result;
    }
}
