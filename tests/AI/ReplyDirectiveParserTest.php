<?php

declare(strict_types=1);

namespace Orin\Tests\AI;

use Orin\Services\ReplyDirectiveParser;
use PHPUnit\Framework\TestCase;

final class ReplyDirectiveParserTest extends TestCase
{
    public function testStripsDirectivesFromVisibleText(): void
    {
        $result = (new ReplyDirectiveParser())->parse("Ji bhai, wallet 1250 taka.\n[[LEAD:stage=qualified|phone=01712345678]]");

        self::assertSame('Ji bhai, wallet 1250 taka.', $result->text);
        self::assertSame('qualified', $result->stage);
        self::assertSame('01712345678', $result->fields['phone']);
        self::assertFalse($result->handoff);
    }

    public function testHandoffDirectiveIsExtractedWithReason(): void
    {
        $result = (new ReplyDirectiveParser())->parse("Please hold on a moment.\n[[HANDOFF:refund request]]");

        self::assertTrue($result->handoff);
        self::assertSame('refund request', $result->handoffReason);
        self::assertSame('Please hold on a moment.', $result->text);
    }

    public function testUnknownFieldNamesAreDropped(): void
    {
        $result = (new ReplyDirectiveParser())->parse('ok [[LEAD:stage=won|password=secret]]');

        self::assertSame('won', $result->stage);
        self::assertArrayNotHasKey('password', $result->fields);
        self::assertSame('ok', $result->text);
    }

    public function testPlainReplyIsUntouched(): void
    {
        $result = (new ReplyDirectiveParser())->parse('Nomoskar, ki korte pari?');

        self::assertSame('Nomoskar, ki korte pari?', $result->text);
        self::assertFalse($result->hasAnything());
    }
}
