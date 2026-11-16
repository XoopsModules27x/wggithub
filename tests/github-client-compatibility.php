<?php

declare(strict_types=1);

use Milo\Github\Api;
use Milo\Github\Http;
use XoopsModules\Wggithub\Github\GithubClient;
use XoopsModules\Wggithub\Helper;

require_once __DIR__ . '/bootstrap.php';

// Loading the real subclass catches incompatible declarations before any API call.
$client = new ReflectionClass(GithubClient::class);
if (!$client->isSubclassOf(Api::class)
    || !$client->hasMethod('_get')
    || !$client->getMethod('_get')->isPublic()
    || $client->getMethod('get')->getDeclaringClass()->getName() !== Api::class
) {
    throw new AssertionError('The module request helper must coexist with Milo Api::get().');
}

Helper::$handlers['Settings'] = new class {
    public function getPrimarySetting(): array
    {
        return ['user' => 'test-user', 'token' => 'test-token'];
    }
};
Helper::$handlers['Logs'] = new class {
    public function updateTableLogs(...$args): void {}
};
$transport = new class extends Http\AbstractClient {
    public int $code = 200;
    public string $body = '{"ok":true}';
    public array $requests = [];

    protected function process(Http\Request $request): Http\Response
    {
        $this->requests[] = $request;
        return new Http\Response($this->code, ['Content-Type' => 'application/json'], $this->body);
    }
};
$instance = new GithubClient($transport);
wggithubCheck($instance->get('/user')->getCode() === 200, 'Inherited get() must use an initialized transport.');
wggithubCheck($transport->requests[0]->getHeader('Authorization') === 'token test-token', 'Inherited requests must authenticate.');
$instance->setToken(new Milo\Github\OAuth\Token('override-token', 'bearer', ['repo']));
wggithubCheck($instance->_get('/user') === ['ok' => true], 'Module helper must keep decoded responses.');
wggithubCheck($transport->requests[1]->getHeader('Authorization') === 'token override-token', 'Module helper must honor the token already configured on the client.');
// List endpoints (orgs/x/repos) return arrays of objects; handlers index them as arrays.
$transport->body = '[{"node_id":"R_1","fork":false,"owner":{"login":"o"}}]';
$repos = $instance->getOrgRepositories('o');
wggithubCheck(is_array($repos[0]) && $repos[0]['node_id'] === 'R_1' && $repos[0]['owner']['login'] === 'o', 'Nested API objects must decode to arrays.');
$transport->code = 404;
wggithubCheck($instance->getReadme('owner', 'repo') === false, 'Optional missing readmes must still be skipped.');

echo "PASS: GithubClient loads with Milo and exposes the module _get() helper.\n";
