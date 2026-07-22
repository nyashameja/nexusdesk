<?php

declare(strict_types=1);

namespace ParagonHostOps\Tests\Unit;

use ParagonHostOps\Core\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function test_token_is_generated_and_stable_within_session(): void
    {
        $a = Csrf::token();
        $b = Csrf::token();

        $this->assertNotSame('', $a);
        $this->assertSame($a, $b, 'Token should be stable within a session.');
        $this->assertSame(64, strlen($a), 'Token should be 32 random bytes hex-encoded.');
    }

    public function test_valid_token_passes(): void
    {
        $token = Csrf::token();
        $this->assertTrue(Csrf::validate($token));
    }

    public function test_invalid_token_fails(): void
    {
        Csrf::token();
        $this->assertFalse(Csrf::validate('wrong'));
        $this->assertFalse(Csrf::validate(''));
        $this->assertFalse(Csrf::validate(null));
    }

    public function test_validation_fails_without_session_token(): void
    {
        $_SESSION = [];
        $this->assertFalse(Csrf::validate('anything'));
    }
}
