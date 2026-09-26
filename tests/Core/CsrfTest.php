<?php

declare(strict_types=1);

namespace Orin\Tests\Core;

use Orin\Core\Csrf;
use Orin\Core\Session;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    private function session(): Session
    {
        $_SESSION = [];

        return new Session('test_session', 3600);
    }

    public function testTokenIsGeneratedAndStable(): void
    {
        $csrf = new Csrf($this->session());

        $token = $csrf->token();

        self::assertNotEmpty($token);
        self::assertSame($token, $csrf->token());
    }

    public function testCheckRejectsWrongToken(): void
    {
        $csrf = new Csrf($this->session());
        $csrf->token();

        self::assertFalse($csrf->check('wrong'));
    }

    public function testCheckAcceptsCorrectToken(): void
    {
        $csrf = new Csrf($this->session());
        $token = $csrf->token();

        self::assertTrue($csrf->check($token));
    }
}
