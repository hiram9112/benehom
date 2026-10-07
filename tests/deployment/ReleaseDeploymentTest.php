<?php

declare(strict_types=1);

namespace Tests\Deployment;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Local functional CD tests: real Bash, symlinks, flock, signals and curl/TLS.
 * The only HTTP server is a PHP fixture on 127.0.0.1. No SSH or DB is used.
 * Run: vendor/bin/phpunit tests/deployment
 */
final class ReleaseDeploymentTest extends TestCase
{
    private const OLD_SHA = '578b831000000000000000000000000000000000';
    private const NEW_SHA = 'abcdef0111111111111111111111111111111111';
    private const OTHER_SHA = '1234567222222222222222222222222222222222';

    private string $root = '';
    private string $public;
    private string $oldTarget = 'releases/578b831/public';
    private string $source;
    /** @var array<string, string> */
    private array $env;
    /** @var resource|null */
    private $server = null;
    /** @var array<int, resource> */
    private array $serverPipes = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::assertTrue(extension_loaded('openssl'), 'The local HTTPS fixture requires OpenSSL.');
        self::assertTrue(function_exists('posix_kill'), 'Signal tests require POSIX.');
        $this->root = sys_get_temp_dir() . '/benehom-cd-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root, 0700));
        mkdir($this->root . '/releases');
        touch($this->root . '/.env.v2');
        file_put_contents($this->root . '/fault', '');
        foreach ([self::OLD_SHA, self::NEW_SHA, self::OTHER_SHA] as $sha) {
            $release = $this->root . '/releases/' . substr($sha, 0, 7);
            mkdir($release . '/public/css', 0755, true);
            file_put_contents($release . '/public/index.php', "<?php // fixture\n");
            file_put_contents($release . '/public/css/app.min.css', '/* ' . $sha . ' */ body{margin:0}');
            file_put_contents($release . '/RELEASE_SHA', $sha . "\n");
            symlink('../../.env.v2', $release . '/.env');
            if ($sha !== self::OLD_SHA) {
                mkdir($release . '/app/controllers', 0755, true);
                file_put_contents($release . '/app/controllers/McpController.php', "<?php // fixture\n");
            }
        }
        $this->public = $this->root . '/public_html';
        symlink($this->oldTarget, $this->public);
        $this->env = array_merge(getenv(), [
            'CURL_CA_BUNDLE' => $this->root . '/cert.pem',
            'NO_PROXY' => '127.0.0.1',
            'TMPDIR' => $this->root,
        ]);
        $result = $this->runProcess(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes',
            '-keyout', $this->root . '/key.pem', '-out', $this->root . '/cert.pem', '-days', '1',
            '-subj', '/CN=127.0.0.1', '-addext', 'subjectAltName=IP:127.0.0.1']);
        self::assertSame(0, $result['code'], $result['stderr']);

        $this->server = proc_open([PHP_BINARY, BASE_PATH . '/tests/Fixtures/DeploymentHttpsServer.php',
            $this->root, self::OLD_SHA, self::NEW_SHA, self::OTHER_SHA], [
                0 => ['pipe', 'r'], 1 => ['pipe', 'w'],
                2 => ['file', $this->root . '/server.log', 'a'],
            ], $this->serverPipes, BASE_PATH);
        self::assertIsResource($this->server);
        stream_set_timeout($this->serverPipes[1], 5);
        $address = trim((string) fgets($this->serverPipes[1]));
        self::assertMatchesRegularExpression('/^127\.0\.0\.1:[0-9]+$/', $address,
            (string) file_get_contents($this->root . '/server.log'));

        // Fail before executing any Bash if production paths cannot be replaced.
        $source = file_get_contents(BASE_PATH . '/scripts/prepare-remote-release.sh');
        $source = str_replace("DOMAIN_ROOT='/home/u124104782/domains/benehom.es'",
            'DOMAIN_ROOT=' . escapeshellarg($this->root), $source, $rootCount);
        $this->source = str_replace("SITE_URL='https://benehom.es'",
            "SITE_URL='https://{$address}'", $source, $urlCount);
        self::assertSame(1, $rootCount);
        self::assertSame(1, $urlCount);
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            foreach ($this->serverPipes as $pipe) {
                fclose($pipe);
            }
            proc_close($this->server);
        }
        if ($this->root !== '' && is_dir($this->root)) {
            $this->removeTree($this->root);
        }
        parent::tearDown();
    }

    public function testSuccessAndCacheBypass(): void
    {
        $result = $this->runScript();
        self::assertSame(0, $result['code'], $result['stderr']);
        self::assertSame('releases/abcdef0/public', readlink($this->public));
        $requests = $this->requests();
        self::assertSame(['/', '/blog', '/css/app.min.css', '/mcp'], array_map(
            static fn (array $request): string => parse_url($request['url'], PHP_URL_PATH), $requests));
        foreach (array_slice($requests, 0, 3) as $request) {
            self::assertSame('GET', $request['method']);
            self::assertSame('abcdef0', $request['active']);
            parse_str(parse_url($request['url'], PHP_URL_QUERY), $query);
            self::assertStringStartsWith(self::NEW_SHA, $query['__cd']);
            self::assertStringContainsString('no-cache', $request['headers']['cache-control']);
        }

        $mcp = $requests[3];
        self::assertSame('POST', $mcp['method']);
        self::assertSame('abcdef0', $mcp['active']);
        self::assertSame('application/json', $mcp['headers']['content-type']);
        self::assertArrayNotHasKey('authorization', $mcp['headers']);
        self::assertSame([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => [],
                'clientInfo' => ['name' => 'benehom-deployment-smoke', 'version' => '1.0.0'],
            ],
        ], json_decode($mcp['body'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function testSmokeCommandSuccessAndFailure(): void
    {
        unlink($this->public);
        symlink('releases/abcdef0/public', $this->public);
        self::assertSame(0, $this->runScript('smoke')['code']);
        $this->fault('html');
        self::assertNotSame(0, $this->runScript('smoke')['code']);
    }

    /** @return array<string, array{string}> */
    public static function httpFailures(): array
    {
        $cases = [];
        foreach (['home', 'blog', 'html', 'redirect', 'empty-css', 'css-type', 'old-css', 'cache-hit', 'cache-age',
            'mcp-auth-header', 'mcp-cache-header', 'mcp-body'] as $fault) {
            $cases[$fault] = [$fault];
        }
        return $cases;
    }

    #[DataProvider('httpFailures')]
    public function testHttpFailuresRestoreStableRelease(string $fault): void
    {
        $this->fault($fault);
        $this->assertRollback($this->runScript());
    }

    public function testMcpFailureRestoresPreMcpReleaseWithoutRequiringItsEndpoint(): void
    {
        $this->fault('mcp-status');
        $result = $this->runScript();
        $this->assertRollback($result);

        $previousReleaseRequests = array_values(array_filter(
            $this->requests(),
            static fn (array $request): bool => $request['active'] === '578b831',
        ));
        self::assertSame(['/', '/blog', '/css/app.min.css'], array_map(
            static fn (array $request): string => parse_url($request['url'], PHP_URL_PATH),
            $previousReleaseRequests,
        ));
    }

    public function testTransientFailureIsRetried(): void
    {
        $this->fault('transient');
        $result = $this->runScript();
        self::assertSame(0, $result['code'], $result['stderr']);
        $home = array_values(array_filter($this->requests(),
            static fn (array $request): bool => parse_url($request['url'], PHP_URL_PATH) === '/'));
        self::assertCount(2, $home);
        self::assertNotSame($home[0]['url'], $home[1]['url']);
    }

    public function testAbsolutePreviousTargetIsRestoredExactly(): void
    {
        unlink($this->public);
        $this->oldTarget = $this->root . '/releases/578b831/public';
        symlink($this->oldTarget, $this->public);
        $this->fault('blog');
        $this->assertRollback($this->runScript());
    }

    public function testActiveShaChangeTriggersRollback(): void
    {
        $this->fault('sha-change');
        $this->assertRollback($this->runScript());
    }

    /** @return array<string, array{string, string}> */
    public static function externalChanges(): array
    {
        return ['another release' => ['external', '1234567'], 'same target, new inode' => ['same-target', 'abcdef0']];
    }

    #[DataProvider('externalChanges')]
    public function testExternalSymlinkChangesAreNotOverwritten(string $fault, string $expected): void
    {
        $this->fault($fault);
        $result = $this->runScript();
        self::assertNotSame(0, $result['code']);
        self::assertSame('releases/' . $expected . '/public', readlink($this->public));
        self::assertStringContainsString('Manual intervention required', $result['stderr']);
    }

    public function testUnavailableRollbackIsExplicit(): void
    {
        $this->fault('unavailable');
        $result = $this->runScript();
        self::assertNotSame(0, $result['code']);
        self::assertSame($this->oldTarget, readlink($this->public));
        self::assertStringContainsString('Manual intervention required', $result['stderr']);
    }

    public function testFailedRestoreIsExplicit(): void
    {
        $this->fault('home');
        $result = $this->runScript(hook: <<<'BASH'
mv() {
    if [[ "$*" == *'/previous '* ]]; then return 74; fi
    command mv "$@"
}
BASH);
        self::assertSame(1, $result['code'], $result['stderr']);
        self::assertStringContainsString('Manual intervention required', $result['stderr']);
        self::assertSame('releases/abcdef0/public', readlink($this->public));
    }

    public function testActivationFailureRetainsPreviousLinkAndError(): void
    {
        $result = $this->runScript(hook: 'mv() { return 73; }');
        self::assertSame(73, $result['code'], $result['stderr']);
        self::assertSame($this->oldTarget, readlink($this->public));
        self::assertSame([], $this->requests());
    }

    public function testCleanupFailurePreservesOriginalError(): void
    {
        $result = $this->runScript(hook: <<<'BASH'
mv() { return 73; }
rm() { command rm "$@"; return 77; }
BASH);
        self::assertSame(73, $result['code'], $result['stderr']);
    }

    public function testTermAfterActivationRollsBack(): void
    {
        $this->fault('term');
        $result = $this->runScript(hook: <<<'BASH'
mv() {
    command mv "$@"
    printf '%s' "$BASHPID" > "$DOMAIN_ROOT/activation-pid"
}
BASH);
        self::assertSame(143, $result['code'], $result['stderr']);
        $this->assertRollback($result);
    }

    public function testClosedErrorPipeDoesNotInterruptRecovery(): void
    {
        $this->fault('home');
        $result = $this->runScript(closeErrorPipe: true);
        self::assertNotSame(0, $result['code'], $result['stdout']);
        self::assertSame($this->oldTarget, readlink($this->public));
    }

    /** @return array<string, array{string}> */
    public static function missingReleases(): array
    {
        return ['prepared release' => ['abcdef0'], 'previous release' => ['578b831']];
    }

    #[DataProvider('missingReleases')]
    public function testMissingPreparedOrPreviousReleaseStopsBeforeSwitch(string $short): void
    {
        unlink($this->root . '/releases/' . $short . '/.env');
        $result = $this->runScript();
        self::assertNotSame(0, $result['code']);
        self::assertSame($this->oldTarget, readlink($this->public));
        self::assertSame([], $this->requests());
    }

    public function testConcurrentActivationIsRejected(): void
    {
        $handle = fopen($this->root . '/releases', 'r');
        self::assertIsResource($handle);
        try {
            self::assertTrue(flock($handle, LOCK_EX | LOCK_NB));
            $result = $this->runScript();
        } finally {
            fclose($handle);
        }
        self::assertNotSame(0, $result['code']);
        self::assertSame($this->oldTarget, readlink($this->public));
        self::assertSame([], $this->requests());
    }

    /** @return array<string, array{string, int, int}> */
    public static function mainStates(): array
    {
        return [
            'current, propagate SSH failure' => [self::NEW_SHA, 0, 27],
            'superseded commit' => [self::OLD_SHA, 0, 1],
            'remote ref unavailable' => [self::NEW_SHA, 8, 8],
        ];
    }

    #[DataProvider('mainStates')]
    public function testCurrentStaleAndUnavailableMain(string $remote, int $gitStatus, int $expected): void
    {
        $workflow = file_get_contents(BASE_PATH . '/.github/workflows/ci.yml');
        $step = explode("      - name: Activate release with smoke tests and automatic rollback\n", $workflow);
        self::assertCount(2, $step);
        $run = explode("        run: |\n", $step[1], 2);
        self::assertCount(2, $run);
        $block = explode("\n      - name:", $run[1], 2)[0];
        $command = implode("\n", array_map(static fn (string $line): string => substr($line, 10), explode("\n", $block)));
        // Execute the actual workflow guard, stubbing only the two transports.
        $hooks = "git() { printf '%s\\trefs/heads/main\\n' '{$remote}'; return {$gitStatus}; }\n"
            . "ssh() { printf 'SSH_ACTIVATE_CALLED\\n'; return 27; }\n";
        $result = $this->runProcess(['bash', '-c', $hooks . $command], env: [
            'EXPECTED_SHA' => self::NEW_SHA, 'RELEASE_SHORT' => 'abcdef0',
        ]);
        self::assertSame($expected, $result['code'], $result['stderr']);
        self::assertSame($remote === self::NEW_SHA && $gitStatus === 0,
            str_contains($result['stdout'], 'SSH_ACTIVATE_CALLED'));
    }

    private function fault(string $fault): void
    {
        file_put_contents($this->root . '/fault', $fault);
    }

    /** @return list<array{method:string, url:string, active:string, headers:array<string,string>, body:string}> */
    private function requests(): array
    {
        $path = $this->root . '/requests.jsonl';
        if (!is_file($path)) {
            return [];
        }
        return array_map(static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    }

    /** @param array{code:int, stdout:string, stderr:string} $result */
    private function assertRollback(array $result): void
    {
        self::assertNotSame(0, $result['code'], $result['stdout']);
        self::assertSame($this->oldTarget, readlink($this->public));
        self::assertStringContainsString('Rollback verified', $result['stderr']);
        self::assertContains('578b831', array_column($this->requests(), 'active'));
    }

    /** @return array{code:int, stdout:string, stderr:string} */
    private function runScript(string $mode = 'activate', string $hook = '', bool $closeErrorPipe = false): array
    {
        $source = preg_replace('/set -Eeuo pipefail/', "set -Eeuo pipefail\n" . $hook, $this->source, 1);
        $result = $this->runProcess(['bash', '-s', '--', $mode, 'abcdef0', self::NEW_SHA],
            $source, closeErrorPipe: $closeErrorPipe);
        clearstatcache(true);
        self::assertSame([], glob($this->root . '/.activation.*'), $result['stderr']);
        self::assertSame([], glob($this->root . '/tmp.*'), $result['stderr']);
        self::assertDirectoryExists($this->root . '/releases/abcdef0', 'The failed release must be retained.');
        self::assertSame(self::OLD_SHA . "\n", file_get_contents($this->root . '/releases/578b831/RELEASE_SHA'));
        self::assertSame('', file_get_contents($this->root . '/server.log'), 'HTTPS fixture error');
        return $result;
    }

    /**
     * Drain both pipes while the child runs, retaining its exit code and bounding hangs.
     * setsid lets timeout cleanup kill the entire local Bash/curl process group.
     * @param list<string> $command
     * @param array<string, string> $env
     * @return array{code:int, stdout:string, stderr:string}
     */
    private function runProcess(array $command, string $input = '', array $env = [], bool $closeErrorPipe = false): array
    {
        $process = proc_open(array_merge(['setsid'], $command), [
            0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
        ], $pipes, BASE_PATH, array_merge($this->env, $env));
        self::assertIsResource($process);
        $pid = proc_get_status($process)['pid'];
        $output = [1 => '', 2 => ''];
        try {
            if ($closeErrorPipe) {
                fclose($pipes[2]);
                unset($pipes[2]);
            }
            if ($input !== '') {
                self::assertSame(strlen($input), fwrite($pipes[0], $input));
            }
            fclose($pipes[0]);
            unset($pipes[0]);
            foreach ($pipes as $pipe) {
                stream_set_blocking($pipe, false);
            }
            $deadline = microtime(true) + 90;
            do {
                foreach ($pipes as $index => $pipe) {
                    $output[$index] .= stream_get_contents($pipe);
                }
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                if (microtime(true) > $deadline) {
                    self::fail('Local CD subprocess exceeded 90 seconds: ' . $output[2]);
                }
                usleep(10000);
            } while (true);
            foreach ($pipes as $index => $pipe) {
                $output[$index] .= stream_get_contents($pipe);
            }
            return ['code' => $status['exitcode'], 'stdout' => $output[1], 'stderr' => $output[2]];
        } finally {
            // Also remove any descendants if an assertion or a timeout interrupted the test.
            posix_kill(-$pid, SIGKILL);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
    }

    private function removeTree(string $path): void
    {
        if (is_link($path) || !is_dir($path)) {
            unlink($path);
            return;
        }
        foreach (scandir($path) as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->removeTree($path . '/' . $entry);
            }
        }
        rmdir($path);
    }
}
