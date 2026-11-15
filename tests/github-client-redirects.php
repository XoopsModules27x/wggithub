<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Milo\Github\Http;
use XoopsModules\Wggithub\Github\HttpClient;

function redirectClient(string $location): array
{
    $inner = new class($location) extends Http\AbstractClient {
        public array $requests = [];
        public function __construct(private string $location) {}
        protected function process(Http\Request $request): Http\Response
        {
            $this->requests[] = $request;
            return count($this->requests) === 1
                ? new Http\Response(302, ['Location' => $this->location], '')
                : new Http\Response(200, [], 'ok');
        }
    };
    return [new HttpClient($inner), $inner];
}

foreach ([
    '/next' => 'https://api.github.com/next',
    '../next?q=1#part' => 'https://api.github.com/next?q=1#part',
    'child/.?q=1' => 'https://api.github.com/repos/child/?q=1',
    '?page=2' => 'https://api.github.com/repos/list?page=2',
    '//other.test/next' => 'https://other.test/next',
    'https://other.test/next' => 'https://other.test/next',
] as $location => $expected) {
    [$client, $inner] = redirectClient($location);
    $client->request(new Http\Request('GET', 'https://api.github.com/repos/list'));
    wggithubCheck($inner->requests[1]->getUrl() === $expected, 'Incorrect redirect for ' . $location);
}
foreach (['https://other.test/next' => false, 'https://api.github.com:443/next' => true] as $location => $keep) {
    [$client, $inner] = redirectClient($location);
    $client->request(new Http\Request('GET', 'https://api.github.com/repos', ['Authorization' => 'token fixture', 'Cookie' => 'session=fixture']));
    wggithubCheck($inner->requests[1]->hasHeader('Authorization') === $keep, 'Authorization must respect the origin boundary.');
    wggithubCheck($inner->requests[1]->hasHeader('Cookie') === $keep, 'Cookies must respect the origin boundary.');
}
foreach (['http://api.github.com/next', 'file:///etc/passwd', 'https://user@api.github.com/next'] as $location) {
    [$client, $inner] = redirectClient($location);
    try {
        $client->request(new Http\Request('GET', 'https://api.github.com/repos'));
        throw new AssertionError('Unsafe redirects must be rejected.');
    } catch (Http\BadResponseException $error) {
        wggithubCheck(count($inner->requests) === 1, 'The unsafe request must never be sent.');
    }
}
echo "PASS: module resolves redirects, protects credentials and rejects unsafe targets.\n";
