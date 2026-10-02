<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/controllers/AuthController.php';
require_once APP_PATH . '/controllers/VerificacionController.php';

final class EmailVerificationTest extends IntegrationTestCase
{
    /** @var array<string, string|null> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->managedEnvKeys() as $key) {
            $this->envBackup[$key] = $_ENV[$key] ?? null;
        }

        $_ENV['N8N_WEBHOOK_SECRET'] = 'test-secret';
        $_ENV['N8N_USER_REGISTERED_WEBHOOK_URL'] = 'https://n8n.example.test/user-registered';
    }

    protected function tearDown(): void
    {
        foreach ($this->managedEnvKeys() as $key) {
            if ($this->envBackup[$key] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $this->envBackup[$key];
            }
        }

        parent::tearDown();
    }

    public function testTokenVerificacionValidoSeRecupera(): void
    {
        $usuario = $this->crearUsuario('verify-valido.integration@example.test');
        $token = 'token-verificacion-valido';
        $tokenHash = hash('sha256', $token);

        self::assertTrue(\Usuario::guardarTokenVerificacion($usuario['id'], $tokenHash, '2099-01-01 10:00:00'));

        $recuperado = \Usuario::obtenerUsuarioPorTokenVerificacion($tokenHash);

        self::assertIsArray($recuperado);
        self::assertSame($usuario['id'], $recuperado['id']);
        self::assertSame($tokenHash, $recuperado['email_verification_token_hash']);
        self::assertNotSame($token, $recuperado['email_verification_token_hash']);
    }

    public function testTokenVerificacionExpiradoNoSeRecupera(): void
    {
        $usuario = $this->crearUsuario('verify-expirado.integration@example.test');
        $tokenHash = hash('sha256', 'token-verificacion-expirado');

        self::assertTrue(\Usuario::guardarTokenVerificacion($usuario['id'], $tokenHash, '2000-01-01 10:00:00'));

        self::assertFalse(\Usuario::obtenerUsuarioPorTokenVerificacion($tokenHash));
    }

    public function testTokenVerificacionInvalidoNoSeRecupera(): void
    {
        self::assertFalse(\Usuario::obtenerUsuarioPorTokenVerificacion(hash('sha256', str_repeat('a', 64))));
    }

    public function testMarcarEmailVerificadoActivaCuentaYLimpiaToken(): void
    {
        $usuario = $this->crearUsuario('verify-marcar.integration@example.test');
        $tokenHash = hash('sha256', 'token-verificacion-marcar');

        self::assertTrue(\Usuario::guardarTokenVerificacion($usuario['id'], $tokenHash, '2099-01-01 10:00:00'));
        self::assertTrue(\Usuario::marcarEmailVerificado($usuario['id']));

        $actualizado = \Usuario::obtenerUsuario('verify-marcar.integration@example.test');

        self::assertIsArray($actualizado);
        self::assertNotEmpty($actualizado['email_verificado_en']);
        self::assertNull($actualizado['email_verification_token_hash']);
        self::assertNull($actualizado['email_verification_expires_at']);
        self::assertFalse(\Usuario::obtenerUsuarioPorTokenVerificacion($tokenHash));
    }

    public function testLoginQuedaBloqueadoMientrasEmailNoEsteVerificado(): void
    {
        $usuario = $this->crearUsuario('verify-login.integration@example.test', 'Password-verificada-123');
        $auth = new class extends \AuthController {
            public function puedeIniciarSesion(array $user): bool
            {
                return $this->emailVerificadoParaLogin($user);
            }
        };

        self::assertTrue(password_verify('Password-verificada-123', $usuario['password']));
        self::assertNull($usuario['email_verificado_en']);
        self::assertFalse($auth->puedeIniciarSesion($usuario));

        self::assertTrue(\Usuario::marcarEmailVerificado($usuario['id']));

        $verificado = \Usuario::obtenerUsuario('verify-login.integration@example.test');

        self::assertIsArray($verificado);
        self::assertTrue($auth->puedeIniciarSesion($verificado));
    }

    public function testVerificacionCorrectaNotificaConElPayloadPrevisto(): void
    {
        $usuario = $this->crearUsuario('verify-webhook.integration@example.test');
        $token = 'token-verificacion-webhook';
        $requests = [];
        self::assertTrue(\Usuario::guardarTokenVerificacion(
            $usuario['id'],
            hash('sha256', $token),
            '2099-01-01 10:00:00'
        ));

        $resultado = $this->verificationController($requests)->procesarToken($token);

        self::assertSame('verified', $resultado);
        self::assertCount(1, $requests);
        self::assertSame(
            ['user_id' => (int) $usuario['id'], 'name' => 'Usuario test'],
            json_decode($requests[0], true)
        );
    }

    public function testVerificacionInvalidaNoNotifica(): void
    {
        $requests = [];

        $resultado = $this->verificationController($requests)->procesarToken('token-invalido');

        self::assertSame('invalid', $resultado);
        self::assertSame([], $requests);
    }

    public function testFalloAlPersistirLaVerificacionNoNotifica(): void
    {
        $usuario = $this->crearUsuario('verify-failed.integration@example.test');
        $token = 'token-verificacion-failed';
        $requests = [];
        self::assertTrue(\Usuario::guardarTokenVerificacion(
            $usuario['id'],
            hash('sha256', $token),
            '2099-01-01 10:00:00'
        ));

        $resultado = $this->verificationController($requests, failPersistence: true)->procesarToken($token);

        self::assertSame('failed', $resultado);
        self::assertSame([], $requests);
    }

    public function testFalloDeN8nNoRevierteLaVerificacion(): void
    {
        $usuario = $this->crearUsuario('verify-n8n-failed.integration@example.test');
        $token = 'token-verificacion-n8n-failed';
        $requests = [];
        self::assertTrue(\Usuario::guardarTokenVerificacion(
            $usuario['id'],
            hash('sha256', $token),
            '2099-01-01 10:00:00'
        ));

        $resultado = $this->verificationController($requests, failWebhook: true)->procesarToken($token);
        $actualizado = \Usuario::obtenerUsuario('verify-n8n-failed.integration@example.test');

        self::assertSame('verified', $resultado);
        self::assertCount(1, $requests);
        self::assertIsArray($actualizado);
        self::assertNotEmpty($actualizado['email_verificado_en']);
    }

    /**
     * @param list<string> $requests
     * @return object{procesarToken: callable(string): string}
     */
    private function verificationController(
        array &$requests,
        bool $failPersistence = false,
        bool $failWebhook = false,
    ): object {
        $webhooks = new \N8nWebhookService(
            static function (string $url, array $headers, string $body) use (&$requests, $failWebhook): int {
                $requests[] = $body;

                if ($failWebhook) {
                    throw new \RuntimeException('Fallo n8n simulado.');
                }

                return 204;
            }
        );

        return new class($webhooks, $failPersistence) extends \VerificacionController {
            public function __construct(
                private readonly \N8nWebhookService $webhooks,
                private readonly bool $failPersistence,
            ) {
            }

            public function procesarToken(string $token): string
            {
                return $this->verificarToken($token);
            }

            protected function confirmarEmailVerificado(int $userId): bool
            {
                return !$this->failPersistence && parent::confirmarEmailVerificado($userId);
            }

            protected function n8nWebhooks(): \N8nWebhookService
            {
                return $this->webhooks;
            }
        };
    }

    /** @return list<string> */
    private function managedEnvKeys(): array
    {
        return [
            'N8N_WEBHOOK_SECRET',
            'N8N_USER_REGISTERED_WEBHOOK_URL',
        ];
    }
}
