<?php

declare(strict_types=1);

namespace Orin\Tests\AI;

use Orin\Services\FollowUpService;
use PHPUnit\Framework\TestCase;

final class FollowUpServiceTest extends TestCase
{
    private FollowUpService $service;

    protected function setUp(): void
    {
        $this->service = new FollowUpService(new FakeDatabase());
    }

    public function testAcceptsMinuteHourDayAndWeekSuffixes(): void
    {
        foreach (['30m', '3h', '2d', '1w'] as $delay) {
            self::assertNotNull($this->service->parseDelay($delay), $delay . ' should parse');
        }
    }

    public function testRejectsNonsense(): void
    {
        foreach (['', 'soon', '2', 'd2', '0d', '-3h', '999w'] as $delay) {
            self::assertNull($this->service->parseDelay($delay), $delay . ' should be rejected');
        }
    }
}

final class FakeDatabase extends \Orin\Core\Database
{
    public function __construct()
    {
        parent::__construct([]);
    }
}
