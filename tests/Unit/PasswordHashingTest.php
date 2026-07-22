<?php

declare(strict_types=1);

namespace ParagonHostOps\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifies the password hashing approach used by the authentication service
 * and the admin-creation script (password_hash / password_verify).
 */
final class PasswordHashingTest extends TestCase
{
    public function test_hash_and_verify_roundtrip(): void
    {
        $hash = password_hash('Sup3rSecret!pass', PASSWORD_DEFAULT);

        $this->assertNotSame('Sup3rSecret!pass', $hash);
        $this->assertTrue(password_verify('Sup3rSecret!pass', $hash));
        $this->assertFalse(password_verify('wrongpassword', $hash));
    }

    public function test_hashes_are_salted_and_unique(): void
    {
        $a = password_hash('samepassword', PASSWORD_DEFAULT);
        $b = password_hash('samepassword', PASSWORD_DEFAULT);

        $this->assertNotSame($a, $b, 'Each hash must use a unique salt.');
        $this->assertTrue(password_verify('samepassword', $a));
        $this->assertTrue(password_verify('samepassword', $b));
    }

    public function test_verify_against_dummy_hash_is_false(): void
    {
        // The Auth service verifies against a dummy hash for unknown users to
        // reduce timing leaks; verifying anything against it must fail.
        $dummy = '$2y$10$usesomesillystringforcompatibility1234567890abcdefghi';
        $this->assertFalse(password_verify('any', $dummy));
    }
}
