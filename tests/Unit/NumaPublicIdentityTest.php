<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once APP_PATH . '/services/NumaPublicIdentity.php';

final class NumaPublicIdentityTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_COOKIE[\NumaPublicIdentity::COOKIE_NAME], $_ENV['NUMA_PUBLIC_HASH_KEY'], $_SERVER['HTTPS']);
        parent::tearDown();
    }

    public function testUsesOnlyAValidOpaqueCookieAndDerivesAnHmacHash(): void
    {
        $_ENV['NUMA_PUBLIC_HASH_KEY'] = 'test-public-hash-key';
        $_COOKIE[\NumaPublicIdentity::COOKIE_NAME] = str_repeat('a', 64);

        $identity = new \NumaPublicIdentity();

        self::assertSame(str_repeat('a', 64), $identity->token());
        self::assertSame(hash_hmac('sha256', str_repeat('a', 64), 'test-public-hash-key'), $identity->visitorHash());
        self::assertNotSame($identity->token(), $identity->visitorHash());
    }

    public function testInvalidCookieIsReplacedWithAFreshOpaqueToken(): void
    {
        $_ENV['NUMA_PUBLIC_HASH_KEY'] = 'test-public-hash-key';
        $_COOKIE[\NumaPublicIdentity::COOKIE_NAME] = 'invalid';

        $token = (new \NumaPublicIdentity())->token();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        self::assertNotSame('invalid', $token);
    }

    public function testHashKeyIsRequiredBeforePseudonymization(): void
    {
        $_COOKIE[\NumaPublicIdentity::COOKIE_NAME] = str_repeat('b', 64);

        $this->expectException(\RuntimeException::class);
        (new \NumaPublicIdentity())->visitorHash();
    }
}
