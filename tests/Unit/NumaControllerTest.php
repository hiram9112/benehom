<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/controllers/NumaController.php';

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

    public function testStatusDevuelveDisponibleYUsoNulo(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $response = $this->invoke('status');

        self::assertTrue($response['ok']);
        self::assertSame(['available' => false, 'usage' => null], $response['data']);
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

    public function testStatusNoDevuelveContadoresInventados(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $response = $this->invoke('status');

        self::assertArrayHasKey('usage', $response['data']);
        self::assertNull($response['data']['usage']);
    }

    private function invoke(string $method, string $rawBody = ''): array
    {
        http_response_code(200);

        $controller = new class($rawBody) extends \NumaController {
            public function __construct(private readonly string $body)
            {
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
