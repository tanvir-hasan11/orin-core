<?php

declare(strict_types=1);

namespace Orin\Tests\AI;

use Orin\AI\BanglishPrompt;
use PHPUnit\Framework\TestCase;

final class BanglishPromptTest extends TestCase
{
    public function testDetectsBanglish(): void
    {
        $banglish = new BanglishPrompt();

        self::assertTrue($banglish->detect('tumi kivabe acho bhai'));
        self::assertFalse($banglish->detect('how are you doing today'));
    }
}
