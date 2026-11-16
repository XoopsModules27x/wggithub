<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use XoopsModules\Wggithub\Helper;
use XoopsModules\Wggithub\RepositoriesHandler;

wggithubCheck((bool) getenv('WGGITHUB_TEST_DB_HOST'), 'Set WGGITHUB_TEST_DB_HOST to run the disposable database integration test.');
define('XOOPS_ROOT_PATH', getenv('WGGITHUB_XOOPS_ROOT') ?: dirname(__DIR__, 3));
define('XOOPS_TRUST_PATH', XOOPS_ROOT_PATH . '/xoops_lib');
define('XOOPS_URL', 'https://example.test');
define('XOOPS_CONF_CENSOR', 4);
$tempPath = sys_get_temp_dir() . '/wggithub-test-' . bin2hex(random_bytes(6));
define('XOOPS_VAR_PATH', $tempPath);
mkdir($tempPath . '/configs/textsanitizer', 0700, true);
$GLOBALS['xoopsConfig'] = ['censor_enable' => 0];
require_once XOOPS_ROOT_PATH . '/class/xoopsload.php';
require_once XOOPS_ROOT_PATH . '/kernel/object.php';
require_once XOOPS_ROOT_PATH . '/class/criteria.php';
require_once XOOPS_ROOT_PATH . '/class/model/xoopsmodel.php';
require_once XOOPS_ROOT_PATH . '/class/database/mysqldatabase.php';
require_once XOOPS_ROOT_PATH . '/class/logger/xoopslogger.php';
require_once XOOPS_ROOT_PATH . '/class/module.textsanitizer.php';
require_once dirname(__DIR__) . '/include/update.php';
function xoops_load(string $name): bool
{
    return XoopsLoad::load($name);
}
function xoops_getHandler(string $name): object
{
    return new class {
        public function getConfigsByCat(int $category): array { return ['censor_enable' => 0]; }
    };
}
class XoopsDatabaseFactory
{
    public static function getDatabaseConnection(): XoopsDatabase
    {
        return $GLOBALS['xoopsDB'];
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$connection = new mysqli(
    getenv('WGGITHUB_TEST_DB_HOST'),
    getenv('WGGITHUB_TEST_DB_USER') ?: 'root',
    getenv('WGGITHUB_TEST_DB_PASS') ?: '',
    '',
    (int) (getenv('WGGITHUB_TEST_DB_PORT') ?: 3306)
);
$schema = 'wggithub_test_' . bin2hex(random_bytes(6));
$connection->query("CREATE DATABASE `$schema`");
try {
    $connection->select_db($schema);
    $connection->set_charset('utf8mb4');
    $db = new XoopsMySQLDatabaseProxy();
    $db->conn = $connection;
    $db->setPrefix('test');
    // XOOPS 2.5.11 queryF() calls the logger unguarded.
    $logger = XoopsLogger::getInstance();
    $db->setLogger($logger);
    $GLOBALS['xoopsDB'] = $db;
    preg_match_all('/CREATE TABLE `([^`]+)`\s*\(.*?\) ENGINE=InnoDB;/s', file_get_contents(dirname(__DIR__) . '/sql/mysql.sql'), $tables, PREG_SET_ORDER);
    foreach ($tables as $table) {
        $connection->query(str_replace('CREATE TABLE `' . $table[1] . '`', 'CREATE TABLE `' . $db->prefix($table[1]) . '`', $table[0]));
    }
    $repo = [
        'node_id' => "N'ODE_1", 'fork' => false, 'name' => 'repo',
        'full_name' => 'owner/repo', 'html_url' => 'https://github.com/owner/repo',
        'created_at' => '2026-01-01T00:00:00Z', 'updated_at' => '2026-01-01T00:00:00Z',
        'pushed_at' => '2026-01-01T00:00:00Z',
    ];
    $handler = new RepositoriesHandler($db);
    Helper::$handlers['Repositories'] = $handler;
    wggithubCheck($handler->updateTableRepositories('owner', [$repo], false) === 1, 'First import must succeed.');
    $result = $connection->query('SELECT repo_id FROM test_wggithub_repositories');
    wggithubCheck($result instanceof mysqli_result, 'Repository ID must be readable.');
    $repoId = (int) $result->fetch_assoc()['repo_id'];
    $connection->query("UPDATE test_wggithub_repositories SET repo_approved=1 WHERE repo_id=$repoId");
    $connection->query("INSERT INTO test_wggithub_readmes (rm_repoid, rm_content) VALUES ($repoId, 'keep this readme')");

    // Replay the interleaving: this importer read before the winning insert committed.
    $racingHandler = new class($db) extends RepositoriesHandler {
        public bool $staleRead = true;
        public function &getAll(?CriteriaElement $criteria = null, $fields = null, $asObject = true, $id_as_key = true)
        {
            if ($this->staleRead) {
                $this->staleRead = false;
                $empty = [];
                return $empty;
            }
            $objects = parent::getAll($criteria, $fields, $asObject, $id_as_key);
            return $objects;
        }
    };
    Helper::$handlers['Repositories'] = $racingHandler;
    $relatedUpdates = new class {
        public int $calls = 0;
        public function updateReadmes(...$args): void { ++$this->calls; }
        public function updateReleases(...$args): void { ++$this->calls; }
    };
    Helper::$handlers['Readmes'] = Helper::$handlers['Releases'] = $relatedUpdates;
    wggithubCheck($racingHandler->updateTableRepositories('owner', [$repo], true) === 1, 'Losing importer must treat the existing node as successful.');
    $racingHandler->staleRead = true;
    mysqli_report(MYSQLI_REPORT_OFF);
    wggithubCheck($racingHandler->updateTableRepositories('owner', [$repo], true) === 1, 'Conflict recovery must also support non-throwing MySQLi mode.');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $result = $connection->query('SELECT repo_id, repo_approved FROM test_wggithub_repositories');
    wggithubCheck($result instanceof mysqli_result && $result->num_rows === 1, 'Concurrent imports must leave one repository.');
    wggithubCheck($relatedUpdates->calls === 0, 'Only the winning importer may update related tables.');
    $row = $result->fetch_assoc();
    wggithubCheck((int) $row['repo_id'] === $repoId && (int) $row['repo_approved'] === 1, 'Conflict recovery must preserve IDs and approval.');
    $result = $connection->query('SELECT rm_repoid, rm_content FROM test_wggithub_readmes');
    wggithubCheck($result instanceof mysqli_result && $result->num_rows === 1, 'Existing related data must remain intact.');
    wggithubCheck($result->fetch_assoc()['rm_content'] === 'keep this readme', 'Readme content must be preserved.');

    $module = new class {
        public array $errors = [];
        public function setErrors(string $message): void { $this->errors[] = $message; }
    };
    foreach (['manual-a', 'manual-b'] as $name) {
        $manual = $handler->create();
        $manual->setVar('repo_nodeid', '');
        $manual->setVar('repo_name', $name);
        wggithubCheck((bool) $handler->insert($manual), 'Repositories without a node ID must save.');
    }
    $result = $connection->query('SELECT COUNT(*) AS total FROM test_wggithub_repositories WHERE repo_nodeid IS NULL');
    wggithubCheck((int) $result->fetch_assoc()['total'] === 2, 'Blank node IDs must be stored as NULL.');
    $manual = $handler->get($manual->getVar('repo_id'));
    $manual->setVar('repo_nodeid', 'MANUAL_NODE');
    $handler->insert($manual);
    $manual = $handler->get($manual->getVar('repo_id'));
    wggithubCheck('MANUAL_NODE' === $manual->getVar('repo_nodeid'), 'A node ID must be settable on a manual entry.');
    $manual->setVar('repo_nodeid', '');
    $manual->setVar('repo_name', 'manual-b2');
    $handler->insert($manual);
    $result = $connection->query("SELECT repo_nodeid, repo_name FROM test_wggithub_repositories WHERE repo_id = {$manual->getVar('repo_id')}");
    $row = $result->fetch_assoc();
    wggithubCheck(null === $row['repo_nodeid'] && 'manual-b2' === $row['repo_name'], 'Clearing a node ID must store NULL and keep other edits.');
    $manual->setVar('repo_nodeid', 'RETRY_NODE');
    $handler->insert($manual);
    $manual = $handler->get($manual->getVar('repo_id'));
    $manual->setVar('repo_nodeid', '');
    $manual->setVar('repo_name', 'manual-retried');
    mysqli_report(MYSQLI_REPORT_OFF);
    $connection->query('RENAME TABLE test_wggithub_repositories TO test_wggithub_repositories_off');
    wggithubCheck(!$handler->insert($manual), 'A failed save must report failure.');
    $connection->query('RENAME TABLE test_wggithub_repositories_off TO test_wggithub_repositories');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    wggithubCheck((bool) $handler->insert($manual), 'Retrying a failed save must succeed.');
    $result = $connection->query("SELECT repo_nodeid, repo_name FROM test_wggithub_repositories WHERE repo_id = {$manual->getVar('repo_id')}");
    $row = $result->fetch_assoc();
    wggithubCheck(null === $row['repo_nodeid'] && 'manual-retried' === $row['repo_name'], 'A retry must write every pending edit, including the NULL node ID.');
    wggithubCheck(wggithub_check_repository_nodeids($module), 'NULL node IDs must pass the upgrade preflight.');
    $connection->query("DELETE FROM test_wggithub_repositories WHERE repo_name LIKE 'manual-%'");
    wggithubCheck(wggithub_check_repository_nodeids($module), 'Clean data must pass the upgrade preflight.');
    $connection->begin_transaction();
    $connection->query("INSERT INTO test_wggithub_repositories (repo_nodeid) VALUES ('CaseNode'), ('casenode')");
    wggithubCheck(wggithub_check_repository_nodeids($module), 'Opaque GitHub IDs must be case-sensitive.');
    $connection->rollback();
    $schemaFile = $tempPath . '/schema.yml';
    $migrateHelper = new XoopsModules\Wggithub\Common\MigrateHelper(dirname(__DIR__) . '/sql/mysql.sql', $schemaFile);
    wggithubCheck($migrateHelper->createSchemaFromSqlfile(), 'The upgrade must generate its target schema.');
    $definitions = Xmf\Yaml::read($schemaFile);
    wggithubCheck($definitions['wggithub_repositories']['keys']['repo_nodeid']['unique'] === true, 'The upgrade schema must include the node ID unique key.');
    $connection->query('ALTER TABLE test_wggithub_repositories DROP INDEX repo_nodeid');
    $nodeId = $db->quote($repo['node_id']);
    $connection->query("INSERT INTO test_wggithub_repositories (repo_nodeid) VALUES ($nodeId)");
    wggithubCheck(!wggithub_check_repository_nodeids($module) && $module->errors !== [], 'Upgrade must reject legacy duplicates with an explanation.');
    $result = $connection->query('SELECT COUNT(*) AS total FROM test_wggithub_repositories');
    wggithubCheck($result instanceof mysqli_result && (int) $result->fetch_assoc()['total'] === 2, 'Failed preflight must not delete duplicates.');
    $connection->query('DELETE FROM test_wggithub_repositories WHERE repo_id <> ' . $repoId);
    $connection->query('ALTER TABLE test_wggithub_repositories MODIFY repo_nodeid VARCHAR(255) CHARACTER SET utf8mb4');
    $oversizedId = $db->quote(str_repeat('é', 200));
    $connection->query("INSERT INTO test_wggithub_repositories (repo_nodeid) VALUES ($oversizedId)");
    wggithubCheck(!wggithub_check_repository_nodeids($module), 'Upgrade must reject legacy IDs that would be truncated by byte limits.');
    $connection->query('DELETE FROM test_wggithub_repositories WHERE repo_id <> ' . $repoId);
    $connection->query("ALTER TABLE test_wggithub_repositories MODIFY repo_nodeid VARCHAR(255) NOT NULL DEFAULT ''");
    $connection->query("INSERT INTO test_wggithub_repositories (repo_name) VALUES ('legacy-a'), ('legacy-b')");
    wggithubCheck(wggithub_check_repository_nodeids($module), 'Blank legacy node IDs are not duplicates.');
    wggithubCheck(wggithub_normalize_repository_nodeids($db), 'Upgrade must convert blank node IDs to NULL.');
    $connection->query('ALTER TABLE test_wggithub_repositories ADD UNIQUE KEY repo_nodeid (repo_nodeid)');
    $result = $connection->query('SELECT COUNT(*) AS total FROM test_wggithub_repositories WHERE repo_nodeid IS NULL');
    wggithubCheck((int) $result->fetch_assoc()['total'] === 2, 'Normalized blank node IDs must coexist under the unique key.');
    echo "PASS: duplicate imports preserve canonical IDs, approval and readmes; upgrade refuses destructive deduplication.\n";
} finally {
    // The only schema dropped is the random schema this process just created.
    $connection->query("DROP DATABASE `$schema`");
    $connection->close();
    if (is_file($tempPath . '/schema.yml')) {
        unlink($tempPath . '/schema.yml');
    }
    if (is_file($tempPath . '/configs/textsanitizer/config.php')) {
        unlink($tempPath . '/configs/textsanitizer/config.php');
    }
    if (is_file($tempPath . '/configs/textsanitizer/config.censor.php')) {
        unlink($tempPath . '/configs/textsanitizer/config.censor.php');
    }
    rmdir($tempPath . '/configs/textsanitizer');
    rmdir($tempPath . '/configs');
    rmdir($tempPath);
}
