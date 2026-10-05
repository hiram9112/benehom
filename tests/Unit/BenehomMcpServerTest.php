<?php

declare(strict_types=1);

namespace Tests\Unit;

use Hiram9112\Benehom\Mcp\BenehomMcpServer;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;

final class BenehomMcpServerTest extends TestCase
{
    private string $sessionDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessionDirectory = sys_get_temp_dir() . '/benehom-mcp-test-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->sessionDirectory . '/*') ?: [] as $session) {
            unlink($session);
        }

        if (is_dir($this->sessionDirectory)) {
            rmdir($this->sessionDirectory);
        }

        parent::tearDown();
    }

    public function testInicializaYListaToolsVacia(): void
    {
        $server = new BenehomMcpServer($this->sessionDirectory, ['benehom.test']);
        $initialize = $server->handle($this->request([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0.0'],
                'capabilities' => new \stdClass(),
            ],
        ]));

        self::assertSame(200, $initialize->getStatusCode());
        self::assertSame('application/json', $initialize->getHeaderLine('Content-Type'));
        $sessionId = $initialize->getHeaderLine('Mcp-Session-Id');
        self::assertNotSame('', $sessionId);

        $initialized = $server->handle($this->request([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ], $sessionId));
        self::assertSame(202, $initialized->getStatusCode());

        $tools = $server->handle($this->request([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
            'params' => new \stdClass(),
        ], $sessionId));

        self::assertSame(200, $tools->getStatusCode());
        $payload = json_decode((string) $tools->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([], $payload['result']['tools']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(array $payload, ?string $sessionId = null): ServerRequest
    {
        $headers = [
            'Accept' => 'application/json, text/event-stream',
            'Content-Type' => 'application/json',
            'Host' => 'benehom.test',
        ];

        if ($sessionId !== null) {
            $headers['Mcp-Session-Id'] = $sessionId;
            $headers['Mcp-Protocol-Version'] = '2025-11-25';
        }

        return new ServerRequest(
            'POST',
            'https://benehom.test/mcp',
            $headers,
            Stream::create(json_encode($payload, JSON_THROW_ON_ERROR)),
        );
    }
}
