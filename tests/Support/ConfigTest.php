<?php

declare(strict_types=1);

namespace Orin\Tests\Support;

use Orin\Support\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testDefaultsAreApplied(): void
    {
        $config = new Config([]);

        self::assertSame(2, $config->getInt('missing', 2));
        self::assertSame(0.5, $config->getFloat('missing', 0.5));
        self::assertTrue($config->getBool('missing', true));
    }
}
