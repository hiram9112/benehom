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

    public function testConstruyeUnSoloHostDesdeGlobalsSinPuerto(): void
    {
        $request = $this->requestFromGlobals('benehom.test');

        self::assertSame(['benehom.test'], $request->getHeader('Host'));
        self::assertSame('benehom.test', $request->getHeaderLine('Host'));
    }

    public function testPeticionDesdeGlobalsSinAuthorizationAlcanzaElMiddlewarePat(): void
    {
        $validationCalls = 0;
        $server = new BenehomMcpServer(
            $this->sessionDirectory,
            ['localhost'],
            null,
            static function (string $token) use (&$validationCalls): ?int {
                ++$validationCalls;
                return null;
            },
        );

        $request = $this->requestFromGlobals('localhost');
        $response = $server->handle($request);

        self::assertSame(['localhost'], $request->getHeader('Host'));
        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Bearer', $response->getHeaderLine('WWW-Authenticate'));
        self::assertSame(0, $validationCalls);
    }

    public function testPeticionDesdeGlobalsConHostNoPermitidoSigueDevolviendo403(): void
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

        $response = $server->handle($this->requestFromGlobals('not-allowed.test'));

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('Forbidden: Invalid Host header.', (string) $response->getBody());
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

    public function testRechazaArgumentosIncompletosSinEjecutarLaTool(): void
    {
        $registry = new McpFinancialToolRegistryFake(['meses' => []]);
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
                'arguments' => [],
            ],
        ], $sessionId));

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(-32602, $payload['error']['code']);
        self::assertSame(0, $registry->executeCalls);
    }

    public function testRechazaToolsDesconocidasSinExponerDetallesInternos(): void
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
                'name' => 'tool_desconocida',
                'arguments' => [],
            ],
        ], $sessionId));

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(-32602, $payload['error']['code']);
        self::assertStringNotContainsString('SQL', $payload['error']['message']);
        self::assertStringNotContainsString('stack', strtolower($payload['error']['message']));
    }

    public function testSaneaUnErrorDeBaseDeDatosDeLaTool(): void
    {
        $registry = new McpFinancialToolRegistryFake(new \PDOException(
            'SQLSTATE[HY000] [2006] MySQL server has gone away; SELECT * FROM usuarios WHERE id = 987',
            2006,
        ));
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
        $serialized = json_encode($payload, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['result']['isError']);
        self::assertStringContainsString('No hemos podido procesar la consulta financiera.', $serialized);
        self::assertStringNotContainsString('SQLSTATE', $serialized);
        self::assertStringNotContainsString('SELECT', $serialized);
        self::assertStringNotContainsString('usuarios', $serialized);
        self::assertStringNotContainsString('987', $serialized);
    }

    public function testSaneaRespuestaYLogsAnteUnErrorInesperadoDeLaTool(): void
    {
        $completePat = 'bhmcp_' . str_repeat('a', 32) . '_' . str_repeat('b', 64);
        $hash = '$2y$10$abcdefghijklmnopqrstuuuuuuuuuuuuuuuuuuuuuuuuuuuuuuu';
        $sensitiveDetails = implode(' | ', [
            $completePat,
            $hash,
            'SELECT secret_hash FROM mcp_personal_access_tokens',
            'Stack trace: #0 /var/www/html/benehom/app/Mcp/McpFinancialDataToolAdapter.php(33)',
            'Authorization: Bearer credencial-supersecreta',
            'usuario_id=987 token_id=654 other-user@example.test',
        ]);
        $registry = new McpFinancialToolRegistryFake(new \RuntimeException($sensitiveDetails));
        $server = new BenehomMcpServer(
            $this->sessionDirectory,
            ['benehom.test'],
            $registry,
            static fn (string $token): ?int => $token === 'test-pat' ? 42 : null,
        );
        $sessionId = $this->initialise($server);

        $logPath = sys_get_temp_dir() . '/benehom-mcp-error-' . bin2hex(random_bytes(8)) . '.log';
        $previousLogErrors = ini_get('log_errors');
        $previousErrorLog = ini_get('error_log');
        ini_set('log_errors', '1');
        ini_set('error_log', $logPath);

        try {
            $response = $server->handle($this->request([
                'jsonrpc' => '2.0',
                'id' => 3,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'consultar_datos_financieros',
                    'arguments' => ['periodos' => [['mes_inicio' => '2026-07', 'mes_fin' => '2026-07']]],
                ],
            ], $sessionId));
            $logged = is_file($logPath) ? (string) file_get_contents($logPath) : '';
        } finally {
            ini_set('log_errors', (string) $previousLogErrors);
            ini_set('error_log', (string) $previousErrorLog);

            if (is_file($logPath)) {
                unlink($logPath);
            }
        }

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['result']['isError']);
        self::assertStringContainsString(
            'No hemos podido procesar la consulta financiera.',
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        foreach ([
            $completePat,
            $hash,
            'SELECT',
            'secret_hash',
            'Stack trace',
            '/var/www/html/benehom',
            'Authorization',
            'credencial-supersecreta',
            'usuario_id',
            'token_id',
            '987',
            '654',
            'other-user@example.test',
        ] as $sensitiveValue) {
            self::assertStringNotContainsString($sensitiveValue, json_encode($payload, JSON_THROW_ON_ERROR));
            self::assertStringNotContainsString($sensitiveValue, $logged);
        }

        self::assertSame('', $logged, 'MCP usa el NullLogger del SDK y no debe derivar errores capturados al error_log de PHP.');
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

    private function requestFromGlobals(string $host): \Psr\Http\Message\ServerRequestInterface
    {
        $server = $_SERVER;
        $get = $_GET;
        $post = $_POST;
        $cookie = $_COOKIE;
        $files = $_FILES;

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'POST',
                'HTTP_HOST' => $host,
                'SERVER_NAME' => $host,
                'SERVER_PROTOCOL' => 'HTTP/1.1',
                'REQUEST_URI' => '/mcp',
                'QUERY_STRING' => '',
            ];
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];
            $_FILES = [];

            return BenehomMcpServer::createRequestFromGlobals();
        } finally {
            $_SERVER = $server;
            $_GET = $get;
            $_POST = $post;
            $_COOKIE = $cookie;
            $_FILES = $files;
        }
    }
}

final class McpFinancialToolRegistryFake implements \NumaFinancialToolRegistryInterface
{
    /** @var array<string, mixed> */
    public array $arguments = [];

    public string $name = '';

    public int $authenticatedUserId = 0;

    public int $executeCalls = 0;

    /** @param array<string, mixed>|\Throwable $result */
    public function __construct(private readonly array|\Throwable $result)
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
        ++$this->executeCalls;
        $this->name = $name;
        $this->authenticatedUserId = $authenticatedUserId;
        $this->arguments = $arguments;

        if ($this->result instanceof \Throwable) {
            throw $this->result;
        }

        return $this->result;
    }
}
