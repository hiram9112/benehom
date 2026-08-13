<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\FakeNumaEmbeddingProvider;

final class FakeNumaEmbeddingProviderTest extends TestCase
{
    public function testGeneraVectoresDeterministasSinTransporteExterno(): void
    {
        $provider = new FakeNumaEmbeddingProvider(4);

        $first = $provider->embed('Texto publico de BeneHom');
        $second = $provider->embed('Texto publico de BeneHom');
        $other = $provider->embed('Otro texto publico de BeneHom');

        self::assertSame($first, $second);
        self::assertNotSame($first, $other);
        self::assertCount(4, $first);
        self::assertSame(3, $provider->calls);
        self::assertSame(['document', 'document', 'document'], $provider->tasks);
        self::assertSame([
            'Texto publico de BeneHom',
            'Texto publico de BeneHom',
            'Otro texto publico de BeneHom',
        ], $provider->texts);
    }

    public function testPermiteVectoresExplicitosParaBusquedaSemantica(): void
    {
        $provider = FakeNumaEmbeddingProvider::withVectors([
            'consulta documental' => [1.0, 0.0],
        ], 2);

        self::assertSame([1.0, 0.0], $provider->embed('consulta documental'));
        self::assertCount(2, $provider->embed('otra consulta'));
    }

    public function testDistingueEntreEmbeddingsDeDocumentoYConsulta(): void
    {
        $provider = new FakeNumaEmbeddingProvider(2);

        $provider->embedDocument('Documento publico');
        $provider->embedQuery('Consulta documental');

        self::assertSame(['document', 'query'], $provider->tasks);
    }
}
