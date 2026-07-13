<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function test_required_and_email_rules(): void
    {
        $validator = Validator::make(
            ['email' => 'not-an-email', 'name' => ''],
            ['email' => 'required|email', 'name' => 'required']
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors());
        $this->assertArrayHasKey('name', $validator->errors());
    }

    public function test_passes_with_valid_data(): void
    {
        $validator = Validator::make(
            ['email' => 'jane@acme.com', 'subject' => 'Hello there'],
            ['email' => 'required|email', 'subject' => 'required|max:255']
        );

        $this->assertTrue($validator->passes());
        $this->assertSame(['email' => 'jane@acme.com', 'subject' => 'Hello there'], $validator->validated());
    }

    public function test_min_max_and_in_rules(): void
    {
        $validator = Validator::make(
            ['password' => 'short', 'priority' => 'unknown'],
            ['password' => 'min:8', 'priority' => 'in:low,medium,high']
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors());
        $this->assertArrayHasKey('priority', $validator->errors());
    }

    public function test_confirmed_rule(): void
    {
        $ok = Validator::make(
            ['password' => 'secret12', 'password_confirmation' => 'secret12'],
            ['password' => 'confirmed']
        );
        $this->assertTrue($ok->passes());

        $bad = Validator::make(
            ['password' => 'secret12', 'password_confirmation' => 'different'],
            ['password' => 'confirmed']
        );
        $this->assertTrue($bad->fails());
    }
}
