<?php

declare(strict_types=1);

namespace XoopsModules\Wggithub\Github;

/*
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
/**
 * @copyright    XOOPS Project https://xoops.org/
 * @license      GNU GPL 2 or later (http://www.gnu.org/licenses/gpl-2.0.html)
 * @since
 * @author       Goffy - XOOPS Development Team
 */

use Milo\Github\{
    Api,
    Http,
    OAuth
};
use XoopsModules\Wggithub\{
    Helper,
    Constants
};

/**
 * Class GithubClient
 */
class GithubClient extends Api
{
    /**
     * @var string
     */
    public const BASE_URL = 'https://api.github.com/';

    /**
     * @var string
     */
    public $userAuth = 'myusername';

    /**
     * @var string
     */
    public $tokenAuth = 'mytoken';

    /**
     * @var object
     */
    private $helper = null;

    /**
     * Constructor
     *
     * @param Http\IClient|null $client
     */
    public function __construct(?Http\IClient $client = null)
    {
        parent::__construct($client ?? $this->createHttpClient());
        $this->helper = \XoopsModules\Wggithub\Helper::getInstance();
        $this->getSetting();
        // An empty token would still send "Authorization: token " and GitHub answers 401; go anonymous instead.
        if ('' !== \trim((string)$this->tokenAuth)) {
            $this->setToken(new OAuth\Token($this->tokenAuth, 'bearer', ['repo', 'user', 'public_repo']));
        }
    }

    /**
     * @static function &getInstance
     *
     * @param null
     * @return GitHubClient of Api
     */
    public static function getInstance()
    {
        static $instance = false;
        if (!$instance) {
            $instance = new self();
        }

        return $instance;
    }

    public function testApi1($url) {

        $logsHandler = $this->helper->getHandler('Logs');
        $logsHandler->updateTableLogs(Constants::LOG_TYPE_REQUEST, '', 'START Test API1');

        $url = static::BASE_URL . $url;
        $logsHandler->updateTableLogs(Constants::LOG_TYPE_REQUEST, $url, 'URL');
        $data = $this->_get($url);

        $logsHandler->updateTableLogs(Constants::LOG_TYPE_REQUEST, $url, 'Read data successful');
        $logsHandler->updateTableLogs(Constants::LOG_TYPE_REQUEST, '', 'END Test API1');

        return $data;
    }

    public function testApi2($url) {
        $api = new Api($this->createHttpClient());

        $token = new OAuth\Token('{myKey}', 'bearer', ['repo', 'user', 'public_repo']);
        $api->setToken($token);
        $response = $api->get(static::BASE_URL . $url);

        $data = $api->decode($response);

        /*
        $api = new Api;

        $request = $api->createRequest('GET', $url, [], [], '');
        $response = $api->request($request);
        $data = (array)$api->decode($response);
        */

        return $data;
    }

    /**
     * Get repositories of given user
     *
     * @return array|bool
     */
    public function testUserRepositories()
    {
        $logsHandler = $this->helper->getHandler('Logs');
        $logsHandler->updateTableLogs(Constants::LOG_TYPE_REQUEST, '', 'START Test UserRepositories');

        $url = static::BASE_URL . 'users/' . \rawurlencode($this->userAuth) . '/repos?per_page=1&page=1';
        $logsHandler->updateTableLogs(Constants::LOG_TYPE_REQUEST, $url, 'URL');

        $data = $this->_get($url);

        $logsHandler->updateTableLogs(Constants::LOG_TYPE_REQUEST, $url, 'Read data successful');
        $logsHandler->updateTableLogs(Constants::LOG_TYPE_REQUEST, '', 'END Test UserRepositories');

        return $data;
    }

    /**
     * Get repositories of given user
     *
     * @param     $username
     * @param int $per_page
     * @param int $page
     * @return array|bool
     */
    public function getUserRepositories($username, $per_page = 100, $page = 1)
    {
        $url = static::BASE_URL . 'users/' . \rawurlencode($username) . '/repos?per_page=' . $per_page . '&page=' . $page;

        return $this->_get($url);
    }

    /**
     * Get repositories of given organisation
     *
     * @param     $org
     * @param int $per_page
     * @param int $page
     * @return array|bool
     */
    public function getOrgRepositories($org, $per_page = 100, $page = 1)
    {
        $url = static::BASE_URL . 'orgs/' . \rawurlencode($org) . '/repos?per_page=' . $per_page . '&page=' . $page;

        return $this->_get($url);
    }

    /**
     * Get the readme content for a repository by its username and repository name.
     *
     * @param string $username   the user who owns the repository
     * @param string $repository the name of the repository
     * @return array|bool
     */
    public function getReadme($username, $repository)
    {
        $url = static::BASE_URL . 'repos/' . \rawurlencode($username) . '/' . \rawurlencode($repository) . '/readme';

        return $this->_get($url, true);
    }

    /**
     * Get all releases
     *
     * @param string $username   the user who owns the repository
     * @param string $repository the name of the repository
     * @return array|bool
     */
    public function getReleases($username, $repository)
    {
        $url = static::BASE_URL . 'repos/' . $username . '/' . $repository . '/releases';

        return $this->_get($url, true);
    }

    /**
     * Get latest release
     *
     * @param string $username   the user who owns the repository
     * @param string $repository the name of the repository
     * @param bool   $prerelease
     * @return array|bool
     */
    public function getLatestRelease($username, $repository, $prerelease = false)
    {
        //function currently not used
        if ($prerelease) {
            $url = static::BASE_URL . 'repos/' . $username . '/' . $repository . '/releases';
        } else {
            $url = static::BASE_URL . 'repos/' . $username . '/' . $repository . '/releases/latest';
        }
        $result = $this->_get($url);

        if ($prerelease) {
            if (\is_array($result)) {
                return $result[0];
            } else {
                return [];
            }
        }

        return $result;
    }
    
    /**
     * Get content of repository
     *
     * @param  $username
     * @param  $repository
     * @return array|bool
     */
    public function getRepositoryContent($username, $repository)
    {
        $url = static::BASE_URL . 'repos/'.rawurlencode($username).'/'.rawurlencode($repository).'/contents';

        return $this->_get($url);
    }

    /**
     * Get github content
     *
     * @param      $url
     * @param bool $skipError
     * @return array|bool
     */
    public function _get($url, $skipError = false)
    {
        $error = false;
        $errMsg = '';

        $logsHandler = $this->helper->getHandler('Logs');
        $logsHandler->updateTableLogs(Constants::LOG_TYPE_REQUEST, $url, 'START');
        $response = $this->get($url);
        $code = $response->getCode();
        if (\in_array($code, [200, 201], true)) {
            $logsHandler->updateTableLogs(Constants::LOG_TYPE_REQUEST, $url, 'OK');
        } else {
            if ($skipError) {
                $logsHandler->updateTableLogs(Constants::LOG_TYPE_ERROR, $errMsg, 'Skipped');
                return false;
            } else {
                $error = true;
                $errMsg = $response->getContent();
                $logsHandler->updateTableLogs(Constants::LOG_TYPE_ERROR, $errMsg, 'ERROR ' . $code);
            }
        }
        if ($error) {
            //catch common errors
            switch ($code) {
                case 401:
                    $message = \_MA_WGGITHUB_READGH_ERROR_API_401;
                    break;
                case 403:
                    /*if (\strpos($errMsg, 'API rate limit exceeded') > 0) {$GLOBALS['xoopsTpl']->assign('apiexceed', true);}*/
                    $message = \_MA_WGGITHUB_READGH_ERROR_API_403;
                    break;
                case 404:
                    $message = \_MA_WGGITHUB_READGH_ERROR_API_404;
                    break;
                case 405:
                    $message = \_MA_WGGITHUB_READGH_ERROR_API_405;
                    break;
                case 0:
                default:
                    $message = \_MA_WGGITHUB_READGH_ERROR_API . '(' .$code . ' - ' .  $errMsg . ')';
                    break;
            }
            \redirect_header('index.php?op=api_error&amp;message='. $message . '&amp;url='. $url, 5, $message);
            //throw new \RuntimeException('"' . $message . '"');
        } else {
            // Milo decodes to stdClass; the handlers index nested items as arrays, so decode associatively.
            $data = (array)\json_decode($response->getContent(), true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
        }

        return $data;
    }

    /**
     * Use PHP's CA configuration, with a shipped Mozilla bundle as the fallback.
     */
    private function createHttpClient(): Http\IClient
    {
        $fallbackCaFile = __DIR__ . '/ca-chain.crt';
        if (\extension_loaded('curl')) {
            $caFile = \ini_get('curl.cainfo') ?: \ini_get('openssl.cafile') ?: $fallbackCaFile;
            return new HttpClient(new Http\CurlClient([\CURLOPT_CAINFO => $caFile, \CURLOPT_TIMEOUT => 30]));
        }

        $caFile = \ini_get('openssl.cafile') ?: $fallbackCaFile;
        $client = new Http\StreamClient(['cafile' => $caFile]);
        // An empty Expect header suppresses cURL's default, but is invalid on the wire.
        $client->onRequest(static function (Http\Request $request): void {
            $request->setHeader('Expect', null);
        });

        return new HttpClient($client);
    }

    /**
     * Execute update of repositories and all related tables
     * @param string $dirName
     * @return bool
     */
    public function executeUpdate($dirName = '')
    {
        $helper = Helper::getInstance();
        $directoriesHandler = $helper->getHandler('Directories');
        $repositoriesHandler = $helper->getHandler('Repositories');
        $releasesHandler = $helper->getHandler('Releases');
        $readmesHandler = $helper->getHandler('Readmes');
        $logsHandler = $helper->getHandler('Logs');

        $logsHandler->updateTableLogs(Constants::LOG_TYPE_UPDATE_START, '', 'OK');
        $crDirectories = new \CriteriaCompo();
        if ('' !== $dirName) {
            $crDirectories->add(new \Criteria('dir_name', $dirName));
        } else {
            $crDirectories->add(new \Criteria('dir_autoupdate', 1));
        }
        $crDirectories->add(new \Criteria('dir_online', 1));
        $directoriesAll = $directoriesHandler->getAll($crDirectories);
        // Get All Directories
        $directories = [];
        foreach (\array_keys($directoriesAll) as $i) {
            $directories[$i] = $directoriesAll[$i]->getValuesDirectories();
            $dirName = $directoriesAll[$i]->getVar('dir_name');
            $dirContent = $directoriesAll[$i]->getVar('dir_content');
            $repos = [];
            for ($j = 1; $j <= 9; $j++) {
                $repos[$j] = [];
                if (Constants::DIRECTORY_TYPE_ORG == $directoriesAll[$i]->getVar('dir_type')) {
                    $repos = $this->getOrgRepositories($dirName, 100, $j);
                } else {
                    $repos = $this->getUserRepositories($dirName, 100, $j);
                }
                if (false === $repos) {
                    return false;
                    break 1;
                }
                if (\count($repos) > 0) {
                    $repositoriesHandler->updateTableRepositories($dirName, $repos, true, $dirContent);
                } else {
                    break 1;
                }
                if (\count($repos) < 100) {
                    break 1;
                }
            }
        }
        unset($directories);

        $releasesHandler->updateRepoReleases();
        $readmesHandler->updateRepoReadme();

        $logsHandler->updateTableLogs(Constants::LOG_TYPE_UPDATE_END, '', 'OK');

        return true;
    }

    /**
     * Get primary setting
     *
     * @return bool|array
     */
    private function getSetting()
    {
        $settingsHandler = $this->helper->getHandler('Settings');
        $setting = $settingsHandler->getPrimarySetting();

        if (0 == \count($setting)) {
            \redirect_header(\XOOPS_URL . '/index.php', 3, \_AM_WGGITHUB_THEREARENT_SETTINGS);
        }
        $this->userAuth = $setting['user'];
        $this->tokenAuth = $setting['token'];

        return true;
    }
}
