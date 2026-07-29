<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/NumaKnowledge.php';

final class NumaVectorSimilarityTest extends TestCase
{
    public function testCalculaSimilitudCoseno(): void
    {
        self::assertSame(1.0, \NumaVectorSimilarity::cosine([1.0, 0.0], [1.0, 0.0]));
        self::assertSame(0.0, \NumaVectorSimilarity::cosine([1.0, 0.0], [0.0, 1.0]));
        self::assertEqualsWithDelta(0.8, \NumaVectorSimilarity::cosine([1.0, 0.0], [0.8, 0.6]), 0.000001);
    }

    public function testVectorCeroDevuelveCero(): void
    {
        self::assertSame(0.0, \NumaVectorSimilarity::cosine([0.0, 0.0], [1.0, 0.0]));
    }

    public function testRechazaDimensionesDistintas(): void
    {
        $this->expectException(InvalidArgumentException::class);

        \NumaVectorSimilarity::cosine([1.0], [1.0, 0.0]);
    }
}
