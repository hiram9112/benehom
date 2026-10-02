<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/N8nWebhookService.php';

final class N8nWebhookServiceTest extends TestCase
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
        $_ENV['N8N_NUMA_USAGE_WEBHOOK_URL'] = 'https://n8n.example.test/numa-usage-alert';
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

    public function testUsuarioVerificadoEnviaSoloIdYNombreConAutenticacion(): void
    {
        $request = null;
        $service = new \N8nWebhookService(
            static function (string $url, array $headers, string $body, int $timeout) use (&$request): int {
                $request = compact('url', 'headers', 'body', 'timeout');

                return 204;
            }
        );

        self::assertTrue($service->notifyVerifiedUser(42, 'Ada'));
        self::assertIsArray($request);
        self::assertSame('https://n8n.example.test/user-registered', $request['url']);
        self::assertSame(3, $request['timeout']);
        self::assertContains('Content-Type: application/json', $request['headers']);
        self::assertContains('X-BeneHom-Webhook-Secret: test-secret', $request['headers']);
        self::assertSame(['user_id' => 42, 'name' => 'Ada'], json_decode($request['body'], true));
    }

    public function testAlertaNumaUsaBodyMinimoYAceptaSoloRespuestas2xx(): void
    {
        $bodies = [];
        $successful = new \N8nWebhookService(
            static function (string $url, array $headers, string $body) use (&$bodies): int {
                $bodies[] = $body;

                return 200;
            }
        );
        $failed = new \N8nWebhookService(static fn (): int => 300);

        self::assertTrue($successful->notifyNumaUsageAlert());
        self::assertSame(['[]'], $bodies);
        self::assertFalse($failed->notifyNumaUsageAlert());
    }

    public function testConfiguracionAusenteNoIniciaElTransporte(): void
    {
        unset(
            $_ENV['N8N_WEBHOOK_SECRET'],
            $_ENV['N8N_USER_REGISTERED_WEBHOOK_URL'],
            $_ENV['N8N_NUMA_USAGE_WEBHOOK_URL']
        );
        $transportCalls = 0;
        $service = new \N8nWebhookService(static function () use (&$transportCalls): int {
            $transportCalls++;

            return 200;
        });

        self::assertFalse($service->notifyNumaUsageAlert());
        self::assertSame(0, $transportCalls);
    }

    public function testUrlSinHttpsNoExponeElSecreto(): void
    {
        $_ENV['N8N_NUMA_USAGE_WEBHOOK_URL'] = 'http://n8n.example.test/numa-usage-alert';
        $transportCalls = 0;
        $service = new \N8nWebhookService(static function () use (&$transportCalls): int {
            $transportCalls++;

            return 200;
        });

        self::assertFalse($service->notifyNumaUsageAlert());
        self::assertSame(0, $transportCalls);
    }

    /** @return list<string> */
    private function managedEnvKeys(): array
    {
        return [
            'N8N_WEBHOOK_SECRET',
            'N8N_USER_REGISTERED_WEBHOOK_URL',
            'N8N_NUMA_USAGE_WEBHOOK_URL',
        ];
    }
}
