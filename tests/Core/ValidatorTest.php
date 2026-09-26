<?php

declare(strict_types=1);

namespace Orin\Tests\Core;

use Orin\Core\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredRuleFailsOnBlank(): void
    {
        $validator = new Validator(['email' => ''], ['email' => 'required|email']);

        self::assertFalse($validator->validate());
        self::assertArrayHasKey('email', $validator->errors());
    }

    public function testEmailRulePassesOnValid(): void
    {
        $validator = new Validator(['email' => 'a@b.com'], ['email' => 'required|email']);

        self::assertTrue($validator->validate());
    }

    public function testSameRuleComparesFields(): void
    {
        $validator = new Validator(
            ['password' => 'secret123', 'password_confirmation' => 'nope'],
            ['password' => 'required|same:password_confirmation']
        );

        self::assertFalse($validator->validate());
    }
}
