<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/controllers/NumaController.php';

final class NumaUsoFake extends \NumaUso
{
    public bool $reverted = false;

    public function __construct(
        private readonly array $usage = [
            'daily_used' => 0,
            'daily_limit' => 5,
            'daily_remaining' => 5,
            'monthly_used' => 0,
            'monthly_limit' => 20,
            'monthly_remaining' => 20,
        ],
        private readonly ?string $limitCode = null,
    ) {
    }

    public function estado(int $usuarioId): array
    {
        return $this->usage;
    }

    public function reservar(int $usuarioId): string
    {
        if ($this->limitCode !== null) {
            throw new \NumaUsoLimiteAlcanzado($this->limitCode);
        }

        return '00000000-0000-4000-8000-000000000000';
    }

    public function revertir(string $reservaId): bool
    {
        $this->reverted = true;

        return true;
    }
}

final class NumaControllerTest extends TestCase
{
    private string $originalMethod = 'GET';
    private array $postBackup = [];
    private array $sessionBackup = [];
    private array $serverBackup = [];
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->postBackup = is_array($_POST ?? null) ? $_POST : [];
        $this->sessionBackup = is_array($_SESSION ?? null) ? $_SESSION : [];
        $this->serverBackup = $_SERVER;
        $this->envBackup = $_ENV;

        $_POST = [];
        $_SESSION = [
            'usuario_id' => 123,
            'csrf_token' => 'csrf-token',
        ];
        $_ENV['NUMA_ENABLED'] = 'false';
        $_ENV['NUMA_MAX_MESSAGE_LENGTH'] = '300';
        $_ENV['NUMA_DAILY_LIMIT'] = '5';
        $_ENV['NUMA_MONTHLY_LIMIT'] = '20';
        $_ENV['NUMA_RESERVATION_TTL_SECONDS'] = '120';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_SERVER['REQUEST_METHOD'] = $this->originalMethod;
        $_POST = $this->postBackup;
        $_SESSION = $this->sessionBackup;
        $_ENV = $this->envBackup;

        parent::tearDown();
    }

    public function testStatusDevuelveDisponibleYUsoReal(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $response = $this->invoke('status');

        self::assertTrue($response['ok']);
        self::assertSame([
            'available' => false,
            'usage' => [
                'daily_used' => 0,
                'daily_limit' => 5,
                'daily_remaining' => 5,
                'monthly_used' => 0,
                'monthly_limit' => 20,
                'monthly_remaining' => 20,
            ],
        ], $response['data']);
    }

    public function testChatConJsonValidoYCsrfPorCabeceraDevuelveNumaNoDisponible(): void
    {
        $this->configureJsonPost();

        $response = $this->invoke('chat', '{"message":"¿Cómo añado un movimiento?"}');

        self::assertFalse($response['ok']);
        self::assertSame(503, $response['_status']);
        self::assertSame('NUMA_NOT_AVAILABLE', $response['error']['code']);
    }

    public function testChatRechazaCsrfInvalido(): void
    {
        $this->configureJsonPost('otro-token');

        $response = $this->invoke('chat', '{"message":"¿Cómo añado un movimiento?"}');

        self::assertFalse($response['ok']);
        self::assertSame(403, $response['_status']);
        self::assertSame('NUMA_INVALID_CSRF', $response['error']['code']);
    }

    public function testChatRechazaContentTypeAusente(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'csrf-token';
        unset($_SERVER['CONTENT_TYPE'], $_SERVER['HTTP_CONTENT_TYPE']);

        $response = $this->invoke('chat', '{"message":"Hola"}');

        self::assertFalse($response['ok']);
        self::assertSame(400, $response['_status']);
        self::assertSame('NUMA_INVALID_MESSAGE', $response['error']['code']);
    }

    public function testChatRechazaFormularioTradicional(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = [
            '_csrf' => 'csrf-token',
            'message' => 'Hola desde formulario',
        ];

        $response = $this->invoke('chat');

        self::assertFalse($response['ok']);
        self::assertSame(400, $response['_status']);
        self::assertSame('NUMA_INVALID_MESSAGE', $response['error']['code']);
    }

    public function testChatRechazaCuerpoVacio(): void
    {
        $this->configureJsonPost();

        $response = $this->invoke('chat', '');

        self::assertFalse($response['ok']);
        self::assertSame(400, $response['_status']);
        self::assertSame('NUMA_INVALID_MESSAGE', $response['error']['code']);
    }

    public function testChatRechazaMensajeVacio(): void
    {
        $this->configureJsonPost();

        $response = $this->invoke('chat', '{"message":"   "}');

        self::assertFalse($response['ok']);
        self::assertSame(400, $response['_status']);
        self::assertSame('NUMA_INVALID_MESSAGE', $response['error']['code']);
    }

    public function testChatRechazaMensajeDemasiadoLargo(): void
    {
        $this->configureJsonPost();

        $response = $this->invoke('chat', '{"message":"' . str_repeat('a', 301) . '"}');

        self::assertFalse($response['ok']);
        self::assertSame(422, $response['_status']);
        self::assertSame('NUMA_MESSAGE_TOO_LONG', $response['error']['code']);
    }

    public function testChatRechazaParametrosInternosDelCliente(): void
    {
        $this->configureJsonPost();

        $response = $this->invoke('chat', '{"message":"¿Cómo añado un movimiento?","usuario_id":999}');

        self::assertFalse($response['ok']);
        self::assertSame(400, $response['_status']);
        self::assertSame('NUMA_INVALID_MESSAGE', $response['error']['code']);
    }

    public function testChatRechazaJsonMalFormado(): void
    {
        $this->configureJsonPost();

        $response = $this->invoke('chat', '{');

        self::assertFalse($response['ok']);
        self::assertSame(400, $response['_status']);
        self::assertSame('NUMA_INVALID_MESSAGE', $response['error']['code']);
    }

    public function testChatRechazaJsonQueNoDevuelveArray(): void
    {
        $this->configureJsonPost();

        $response = $this->invoke('chat', '"hola"');

        self::assertFalse($response['ok']);
        self::assertSame(400, $response['_status']);
        self::assertSame('NUMA_INVALID_MESSAGE', $response['error']['code']);
    }

    public function testChatRechazaJsonNoAsociativo(): void
    {
        $this->configureJsonPost();

        $response = $this->invoke('chat', '[{"message":"Hola"}]');

        self::assertFalse($response['ok']);
        self::assertSame(400, $response['_status']);
        self::assertSame('NUMA_INVALID_MESSAGE', $response['error']['code']);
    }

    public function testChatRechazaMensajeInexistente(): void
    {
        $this->configureJsonPost();

        $response = $this->invoke('chat', '{"otro":"valor"}');

        self::assertFalse($response['ok']);
        self::assertSame(400, $response['_status']);
        self::assertSame('NUMA_INVALID_MESSAGE', $response['error']['code']);
    }

    public function testChatRechazaMensajeNoString(): void
    {
        $this->configureJsonPost();

        $response = $this->invoke('chat', '{"message":123}');

        self::assertFalse($response['ok']);
        self::assertSame(400, $response['_status']);
        self::assertSame('NUMA_INVALID_MESSAGE', $response['error']['code']);
    }

    public function testChatAceptaCsrfDelMecanismoActual(): void
    {
        $this->configureJsonPost(null);
        $_POST = ['_csrf' => 'csrf-token'];

        $response = $this->invoke('chat', '{"message":"Hola"}');

        self::assertFalse($response['ok']);
        self::assertSame(503, $response['_status']);
        self::assertSame('NUMA_NOT_AVAILABLE', $response['error']['code']);
    }

    public function testChatActivoRechazaLimiteDiario(): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';
        $this->configureJsonPost();

        $response = $this->invoke(
            'chat',
            '{"message":"¿Cómo añado un movimiento?"}',
            new NumaUsoFake(limitCode: 'NUMA_DAILY_LIMIT_REACHED')
        );

        self::assertFalse($response['ok']);
        self::assertSame(429, $response['_status']);
        self::assertSame('NUMA_DAILY_LIMIT_REACHED', $response['error']['code']);
    }

    public function testChatActivoRechazaLimiteMensual(): void
    {
        $_ENV['NUMA_ENABLED'] = 'true';
        $this->configureJsonPost();

        $response = $this->invoke(
            'chat',
            '{"message":"¿Cómo añado un movimiento?"}',
            new NumaUsoFake(limitCode: 'NUMA_MONTHLY_LIMIT_REACHED')
        );

        self::assertFalse($response['ok']);
        self::assertSame(429, $response['_status']);
        self::assertSame('NUMA_MONTHLY_LIMIT_REACHED', $response['error']['code']);
    }

    public function testChatRechazaDatosSoloMediantePost(): void
    {
        $this->configureJsonPost();
        $_POST = [
            '_csrf' => 'csrf-token',
            'message' => 'Hola desde formulario',
        ];

        $response = $this->invoke('chat', '');

        self::assertFalse($response['ok']);
        self::assertSame(400, $response['_status']);
        self::assertSame('NUMA_INVALID_MESSAGE', $response['error']['code']);
    }

    public function testStatusDevuelveContadoresDelRepositorio(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $usage = [
            'daily_used' => 2,
            'daily_limit' => 5,
            'daily_remaining' => 2,
            'monthly_used' => 8,
            'monthly_limit' => 20,
            'monthly_remaining' => 11,
        ];

        $response = $this->invoke('status', '', new NumaUsoFake($usage));

        self::assertArrayHasKey('usage', $response['data']);
        self::assertSame($usage, $response['data']['usage']);
    }

    private function invoke(string $method, string $rawBody = '', ?NumaUsoFake $numaUso = null): array
    {
        http_response_code(200);
        $numaUso ??= new NumaUsoFake();

        $controller = new class($rawBody, $numaUso) extends \NumaController {
            public function __construct(private readonly string $body, private readonly NumaUsoFake $fakeNumaUso)
            {
            }

            protected function numaUso(): \NumaUso
            {
                return $this->fakeNumaUso;
            }

            protected function rawBody(): string
            {
                return $this->body;
            }
        };

        ob_start();
        $controller->{$method}();
        $output = (string) ob_get_clean();

        $decoded = json_decode($output, true);

        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => ['code' => 'INVALID_TEST_RESPONSE'], 'raw' => $output, '_status' => http_response_code()];
        }

        $decoded['_status'] = http_response_code();

        return $decoded;
    }

    private function configureJsonPost(?string $csrfToken = 'csrf-token'): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        unset($_SERVER['HTTP_CONTENT_TYPE'], $_SERVER['HTTP_X_CSRF_TOKEN']);

        if ($csrfToken !== null) {
            $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrfToken;
        }
    }
}
