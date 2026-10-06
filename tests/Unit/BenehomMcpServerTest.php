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

    public function testInicializaYExponeLaConsultaFinancieraCanonica(): void
    {
        $server = new BenehomMcpServer(
            $this->sessionDirectory,
            ['benehom.test'],
            null,
            static fn (string $token): ?int => $token === 'test-pat' ? 42 : null,
        );
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
        self::assertCount(1, $payload['result']['tools']);
        $tool = $payload['result']['tools'][0];
        self::assertSame('consultar_datos_financieros', $tool['name']);
        self::assertSame(['periodos'], $tool['inputSchema']['required']);
        self::assertFalse($tool['inputSchema']['additionalProperties']);
        self::assertArrayNotHasKey('usuario_id', $tool['inputSchema']['properties']);
        self::assertArrayNotHasKey('user_id', $tool['inputSchema']['properties']);
        self::assertStringContainsString('YYYY-MM', $tool['description']);
        self::assertStringContainsString('no interpreta expresiones temporales', $tool['description']);
        self::assertArrayNotHasKey('outputSchema', $tool);
    }

    public function testEjecutaLaConsultaConElUsuarioAutenticadoInyectado(): void
    {
        $registry = new McpFinancialToolRegistryFake(['tool' => 'consultar_datos_financieros', 'meses' => []]);
        $server = new BenehomMcpServer(
            $this->sessionDirectory,
            ['benehom.test'],
            $registry,
            static fn (string $token): ?int => $token === 'test-pat' ? 42 : null,
        );
        $sessionId = $this->initialise($server);

        $response = $server->handle($this->request([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'consultar_datos_financieros',
                'arguments' => ['periodos' => [['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']]],
            ],
        ], $sessionId));

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(42, $registry->authenticatedUserId);
        self::assertSame('consultar_datos_financieros', $registry->name);
        self::assertSame([['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']], $registry->arguments['periodos']);
        self::assertSame(['tool' => 'consultar_datos_financieros', 'meses' => []], $payload['result']['structuredContent']);
        self::assertFalse($payload['result']['isError']);
    }

    public function testRechazaAuthorizationAusenteSinValidarCredenciales(): void
    {
        $validationCalls = 0;
        $server = new BenehomMcpServer(
            $this->sessionDirectory,
            ['benehom.test'],
            null,
            static function (string $token) use (&$validationCalls): ?int {
                ++$validationCalls;
                return null;
            },
        );

        $response = $server->handle($this->request([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0.0'],
                'capabilities' => new \stdClass(),
            ],
        ], null, null));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Bearer', $response->getHeaderLine('WWW-Authenticate'));
        self::assertSame(0, $validationCalls);
    }

    public function testRechazaBearerMalFormadoSinValidarCredenciales(): void
    {
        $validationCalls = 0;
        $server = new BenehomMcpServer(
            $this->sessionDirectory,
            ['benehom.test'],
            null,
            static function (string $token) use (&$validationCalls): ?int {
                ++$validationCalls;
                return null;
            },
        );

        $response = $server->handle($this->request([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0.0'],
                'capabilities' => new \stdClass(),
            ],
        ], null, 'Basic credencial-invalida'));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Bearer', $response->getHeaderLine('WWW-Authenticate'));
        self::assertSame(0, $validationCalls);
    }

    public function testRechazaBearerDesconocidoDespuesDeValidarlo(): void
    {
        $receivedToken = null;
        $server = new BenehomMcpServer(
            $this->sessionDirectory,
            ['benehom.test'],
            null,
            static function (string $token) use (&$receivedToken): ?int {
                $receivedToken = $token;
                return null;
            },
        );

        $unknownToken = 'bhmcp_' . str_repeat('f', 32) . '_' . str_repeat('a', 64);
        $response = $server->handle($this->request([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-11-25',
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0.0'],
                'capabilities' => new \stdClass(),
            ],
        ], null, 'Bearer ' . $unknownToken));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame($unknownToken, $receivedToken);
    }

    public function testRechazaIdentificadoresDeUsuarioEnLosArgumentos(): void
    {
        $server = new BenehomMcpServer(
            $this->sessionDirectory,
            ['benehom.test'],
            null,
            static fn (string $token): ?int => $token === 'test-pat' ? 42 : null,
        );
        $sessionId = $this->initialise($server);

        $response = $server->handle($this->request([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'consultar_datos_financieros',
                'arguments' => [
                    'periodos' => [['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']],
                    'usuario_id' => 1,
                ],
            ],
        ], $sessionId));

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(-32602, $payload['error']['code']);
        self::assertStringNotContainsString('SQL', $payload['error']['message']);
    }

    private function initialise(BenehomMcpServer $server): string
    {
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
        $sessionId = $initialize->getHeaderLine('Mcp-Session-Id');
        $server->handle($this->request([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ], $sessionId));

        return $sessionId;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(
        array $payload,
        ?string $sessionId = null,
        ?string $authorization = 'Bearer test-pat',
    ): ServerRequest
    {
        $headers = [
            'Accept' => 'application/json, text/event-stream',
            'Content-Type' => 'application/json',
            'Host' => 'benehom.test',
        ];

        if ($authorization !== null) {
            $headers['Authorization'] = $authorization;
        }

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

final class McpFinancialToolRegistryFake implements \NumaFinancialToolRegistryInterface
{
    /** @var array<string, mixed> */
    public array $arguments = [];

    public string $name = '';

    public int $authenticatedUserId = 0;

    /** @param array<string, mixed> $result */
    public function __construct(private readonly array $result)
    {
    }

    public function names(): array
    {
        return ['consultar_datos_financieros'];
    }

    public function get(string $name): \NumaFinancialToolDefinition
    {
        throw new \LogicException('No debe utilizarse en esta prueba.');
    }

    public function validate(string $name, int $authenticatedUserId, array $arguments): array
    {
        throw new \LogicException('No debe utilizarse en esta prueba.');
    }

    public function execute(string $name, int $authenticatedUserId, array $arguments): array
    {
        $this->name = $name;
        $this->authenticatedUserId = $authenticatedUserId;
        $this->arguments = $arguments;

        return $this->result;
    }
}
