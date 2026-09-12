<?php

declare(strict_types=1);

namespace Tests\Integration;

require_once APP_PATH . '/services/GeminiNumaProvider.php';
require_once APP_PATH . '/services/NumaService.php';

final class NumaFinancialFunctionCallingTest extends IntegrationTestCase
{
    private ?string $enabledBefore = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enabledBefore = $_ENV['NUMA_ENABLED'] ?? null;
        $_ENV['NUMA_ENABLED'] = 'true';
    }

    protected function tearDown(): void
    {
        if ($this->enabledBefore === null) {
            unset($_ENV['NUMA_ENABLED']);
        } else {
            $_ENV['NUMA_ENABLED'] = $this->enabledBefore;
        }
        parent::tearDown();
    }

    public function testFunctionCallingDeclaraYEjecutaLaConsultaCanonica(): void
    {
        $user = $this->crearUsuario('numa-canonical-function@example.test');
        $userId = (int) $user['id'];
        $statement = $this->db->prepare('INSERT INTO gastos (usuario_id, tipo, categoria, cantidad, fecha) VALUES (:usuario_id, :tipo, :categoria, :cantidad, :fecha)');
        $statement->execute([
            ':usuario_id' => $userId,
            ':tipo' => 'esencial',
            ':categoria' => 'electricidad',
            ':cantidad' => '42.50',
            ':fecha' => '2026-07-03',
        ]);

        $requests = [];
        $responses = [
            $this->textResponse(json_encode([
                'intent' => 'datos_usuario',
                'allowed' => true,
                'reason' => 'user_data',
                'needs_clarification' => false,
                'knowledge_query' => null,
            ], JSON_THROW_ON_ERROR)),
            $this->functionCallResponse('financial-call', [
                'periodos' => [['tipo' => 'mes', 'mes' => '2026-07']],
                'selectores' => [['categoria' => 'electricidad']],
            ]),
            $this->textResponse('He consultado los datos solicitados.'),
        ];
        $index = 0;
        $providerFactory = function (?\NumaProviderConsumptionInterface $consumption) use (&$requests, &$responses, &$index): \NumaProviderInterface {
            return new \GeminiNumaProvider(
                'test-key',
                'gemini-test-model',
                transport: function (string $url, array $headers, string $body) use (&$requests, &$responses, &$index): array {
                    $requests[] = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

                    return ['status' => 200, 'body' => json_encode($responses[$index++], JSON_THROW_ON_ERROR)];
                },
                consumption: $consumption,
            );
        };
        $service = new \NumaService(
            new \NumaUso($this->db),
            new \NumaLocalScopeClassifier(),
            $providerFactory,
            static fn (): array => [],
            new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db)),
            new class implements \NumaGlobalAvailabilityInterface {
                public function assertAvailable(): void
                {
                }
            },
            new \NumaPeriodResolver(new \DateTimeImmutable('2026-08-12', new \DateTimeZone('Europe/Madrid'))),
        );

        $result = $service->answer($userId, '¿Cuánto pagué de electricidad en julio?');

        self::assertSame('He consultado los datos solicitados.', $result->toArray()['message']);
        self::assertSame('consultar_datos_financieros', $requests[1]['tools'][0]['functionDeclarations'][0]['name']);
        self::assertSame(['periodos'], $requests[1]['tools'][0]['functionDeclarations'][0]['parameters']['required']);
        self::assertSame('ANY', $requests[1]['toolConfig']['functionCallingConfig']['mode']);
        self::assertSame('financial-call', $requests[2]['contents'][2]['parts'][0]['functionResponse']['id']);
        self::assertSame('consultar_datos_financieros', $requests[2]['contents'][2]['parts'][0]['functionResponse']['name']);
        self::assertSame('42.50', $requests[2]['contents'][2]['parts'][0]['functionResponse']['response']['result']['meses'][0]['gastos']['importe']);
    }

    public function testFunctionCallingEmparejaLlamadasCanonicasParalelasConSusIds(): void
    {
        $user = $this->crearUsuario('numa-canonical-parallel@example.test');
        $userId = (int) $user['id'];
        $statement = $this->db->prepare('INSERT INTO gastos (usuario_id, tipo, categoria, cantidad, fecha) VALUES (:usuario_id, :tipo, :categoria, :cantidad, :fecha)');
        $statement->execute([
            ':usuario_id' => $userId,
            ':tipo' => 'esencial',
            ':categoria' => 'electricidad',
            ':cantidad' => '10.00',
            ':fecha' => '2026-07-03',
        ]);
        $statement->execute([
            ':usuario_id' => $userId,
            ':tipo' => 'flexible',
            ':categoria' => 'comida_domicilio',
            ':cantidad' => '20.00',
            ':fecha' => '2026-07-04',
        ]);

        $requests = [];
        $responses = [
            $this->classificationResponse(),
            $this->functionCallsResponse([
                ['id' => 'electricidad', 'args' => [
                    'periodos' => [['tipo' => 'mes', 'mes' => '2026-07']],
                    'selectores' => [['categoria' => 'electricidad']],
                ]],
                ['id' => 'domicilio', 'args' => [
                    'periodos' => [['tipo' => 'mes', 'mes' => '2026-07']],
                    'selectores' => [['categoria' => 'comida_domicilio']],
                ]],
            ]),
            $this->textResponse('He consultado ambas categorías.'),
        ];
        $index = 0;
        $providerFactory = function (?\NumaProviderConsumptionInterface $consumption) use (&$requests, &$responses, &$index): \NumaProviderInterface {
            return new \GeminiNumaProvider(
                'test-key',
                'gemini-test-model',
                transport: function (string $url, array $headers, string $body) use (&$requests, &$responses, &$index): array {
                    $requests[] = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

                    return ['status' => 200, 'body' => json_encode($responses[$index++], JSON_THROW_ON_ERROR)];
                },
                consumption: $consumption,
            );
        };
        $service = new \NumaService(
            new \NumaUso($this->db),
            new \NumaLocalScopeClassifier(),
            $providerFactory,
            static fn (): array => [],
            new \NumaFinancialToolRegistry(new \NumaFinancialToolExecutor($this->db)),
            new class implements \NumaGlobalAvailabilityInterface {
                public function assertAvailable(): void
                {
                }
            },
            new \NumaPeriodResolver(new \DateTimeImmutable('2026-08-12', new \DateTimeZone('Europe/Madrid'))),
        );

        self::assertSame('He consultado ambas categorías.', $service->answer($userId, 'Compara electricidad y comida a domicilio de julio.')->toArray()['message']);
        $parts = $requests[2]['contents'];
        self::assertSame('electricidad', $parts[1]['parts'][0]['functionCall']['id']);
        self::assertSame('domicilio', $parts[1]['parts'][1]['functionCall']['id']);
        self::assertSame('electricidad', $parts[2]['parts'][0]['functionResponse']['id']);
        self::assertSame('domicilio', $parts[2]['parts'][1]['functionResponse']['id']);
        self::assertSame('10.00', $parts[2]['parts'][0]['functionResponse']['response']['result']['meses'][0]['gastos']['importe']);
        self::assertSame('20.00', $parts[2]['parts'][1]['functionResponse']['response']['result']['meses'][0]['gastos']['importe']);
    }

    /** @return array<string, mixed> */
    private function classificationResponse(): array
    {
        return $this->textResponse(json_encode([
            'intent' => 'datos_usuario',
            'allowed' => true,
            'reason' => 'user_data',
            'needs_clarification' => false,
            'knowledge_query' => null,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    private function functionCallResponse(string $id, array $arguments): array
    {
        return [
            'candidates' => [[
                'content' => ['role' => 'model', 'parts' => [['functionCall' => [
                    'id' => $id,
                    'name' => 'consultar_datos_financieros',
                    'args' => $arguments,
                ]]]],
                'finishReason' => 'STOP',
            ]],
        ];
    }

    /** @param list<array{id:string,args:array<string,mixed>}> $calls @return array<string, mixed> */
    private function functionCallsResponse(array $calls): array
    {
        return [
            'candidates' => [[
                'content' => [
                    'role' => 'model',
                    'parts' => array_map(static fn (array $call): array => ['functionCall' => [
                        'id' => $call['id'],
                        'name' => 'consultar_datos_financieros',
                        'args' => $call['args'],
                    ]], $calls),
                ],
                'finishReason' => 'STOP',
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function textResponse(string $text): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $text]]], 'finishReason' => 'STOP']]];
    }
}
