<?php

declare(strict_types=1);

namespace ParagonHostOps\Tests\Unit;

use ParagonHostOps\Validators\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function test_required_fields_fail_when_empty(): void
    {
        $v = (new Validator(['email' => '', 'password' => '']))
            ->required('email', 'Email')
            ->required('password', 'Password');

        $this->assertTrue($v->fails());
        $this->assertNotNull($v->firstError());
    }

    public function test_invalid_email_is_rejected(): void
    {
        $v = (new Validator(['email' => 'not-an-email']))->email('email', 'Email');
        $this->assertTrue($v->fails());
    }

    public function test_valid_input_passes(): void
    {
        $v = (new Validator(['email' => 'user@example.com', 'password' => 'secret123']))
            ->required('email', 'Email')
            ->email('email', 'Email')
            ->required('password', 'Password')
            ->min('password', 'Password', 6);

        $this->assertTrue($v->passes());
        $this->assertNull($v->firstError());
    }

    public function test_max_length_is_enforced(): void
    {
        $v = (new Validator(['name' => str_repeat('a', 20)]))->max('name', 'Name', 10);
        $this->assertTrue($v->fails());
    }

    public function test_in_rule_rejects_values_outside_the_allow_list(): void
    {
        $allowed = ['individual', 'company'];
        $this->assertTrue((new Validator(['type' => 'robot']))->in('type', 'Type', $allowed)->fails());
        $this->assertTrue((new Validator(['type' => 'company']))->in('type', 'Type', $allowed)->passes());
        // Empty is allowed (optional); required() guards presence separately.
        $this->assertTrue((new Validator(['type' => '']))->in('type', 'Type', $allowed)->passes());
    }

    public function test_date_rule_validates_iso_format(): void
    {
        $this->assertTrue((new Validator(['d' => '2026-07-22']))->date('d', 'Date')->passes());
        $this->assertTrue((new Validator(['d' => '22/07/2026']))->date('d', 'Date')->fails());
        $this->assertTrue((new Validator(['d' => '2026-13-01']))->date('d', 'Date')->fails());
        $this->assertTrue((new Validator(['d' => '']))->date('d', 'Date')->passes());
    }

    public function test_numeric_rule(): void
    {
        $this->assertTrue((new Validator(['n' => '12.50']))->numeric('n', 'N')->passes());
        $this->assertTrue((new Validator(['n' => 'abc']))->numeric('n', 'N')->fails());
        $this->assertTrue((new Validator(['n' => '']))->numeric('n', 'N')->passes());
    }
}
