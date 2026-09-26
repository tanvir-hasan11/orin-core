<?php

declare(strict_types=1);

namespace Orin\Tests\AI;

use Orin\Services\ReplyDirectiveParser;
use PHPUnit\Framework\TestCase;

final class ReplyDirectiveParserTest extends TestCase
{
    public function testStripsLeadDirectivesFromVisibleText(): void
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

    public function testUnknownLeadFieldsAreDropped(): void
    {
        $result = (new ReplyDirectiveParser())->parse('ok [[LEAD:stage=won|password=secret]]');

        self::assertSame('won', $result->stage);
        self::assertArrayNotHasKey('password', $result->fields);
        self::assertSame('ok', $result->text);
    }

    public function testOrderDirectiveBecomesAnAction(): void
    {
        $result = (new ReplyDirectiveParser())->parse("Noted bhai.\n[[ORDER:product=Premium Leather Wallet|qty=1|price=1250]]");

        self::assertSame('Noted bhai.', $result->text);
        self::assertCount(1, $result->actions);
        self::assertSame('order', $result->actions[0]['kind']);
        self::assertSame('Premium Leather Wallet', $result->actions[0]['payload']['product']);
        self::assertSame('1', $result->actions[0]['payload']['qty']);
    }

    public function testUnknownActionKeysAreDropped(): void
    {
        $result = (new ReplyDirectiveParser())->parse('ok [[ORDER:product=Wallet|merchant_id=99]]');

        self::assertCount(1, $result->actions);
        self::assertArrayNotHasKey('merchant_id', $result->actions[0]['payload']);
    }

    public function testFollowUpDelayIsParsed(): void
    {
        $result = (new ReplyDirectiveParser())->parse('Thik ache. [[FOLLOWUP:in=2d|reason=customer wanted to think]]');

        self::assertCount(1, $result->actions);
        self::assertSame('followup', $result->actions[0]['kind']);
        self::assertSame('2d', $result->actions[0]['payload']['in']);
    }

    public function testPlainReplyIsUntouched(): void
    {
        $result = (new ReplyDirectiveParser())->parse('Nomoskar, ki korte pari?');

        self::assertSame('Nomoskar, ki korte pari?', $result->text);
        self::assertFalse($result->hasAnything());
    }
}
