<?php

declare(strict_types=1);

namespace Orin\Tests\AI;

use Orin\Services\VerticalPromptBuilder;
use Orin\Services\VerticalRegistry;
use PHPUnit\Framework\TestCase;

final class VerticalPromptBuilderTest extends TestCase
{
    private function builder(): VerticalPromptBuilder
    {
        $verticals = [
            'ecommerce' => [
                'label' => 'online shop',
                'goal' => 'turn the chat into a confirmed order',
                'instructions' => 'Quote only listed prices.',
                'lead_fields' => ['phone', 'address'],
                'first_question' => 'Which product?',
                'stages' => ['new' => 'New', 'qualified' => 'Qualified', 'won' => 'Won', 'lost' => 'Lost'],
            ],
            'clinic' => [
                'label' => 'clinic',
                'goal' => 'book an appointment',
                'instructions' => 'Never give a diagnosis.',
                'lead_fields' => ['phone'],
                'first_question' => 'Which day?',
                'stages' => ['new' => 'New', 'won' => 'Booked'],
            ],
        ];

        return new VerticalPromptBuilder(new VerticalRegistry($verticals));
    }

    public function testUsesTheMerchantVerticalNotAGenericPrompt(): void
    {
        $prompt = $this->builder()->build([
            'business_name' => 'Dhaka Leather',
            'profile' => ['business_type' => 'ecommerce', 'tone' => 'friendly', 'delivery_info' => 'Dhaka 60 taka'],
            'contact' => ['name' => 'Tanvir', 'phone' => '01712345678'],
            'lead' => ['stage' => 'new'],
            'history' => [['direction' => 'in', 'body' => 'wallet er price koto?']],
        ]);

        self::assertStringContainsString('Dhaka Leather', $prompt);
        self::assertStringContainsString('online shop', $prompt);
        self::assertStringContainsString('Quote only listed prices.', $prompt);
        self::assertStringContainsString('Dhaka 60 taka', $prompt);
        self::assertStringContainsString('Customer: wallet er price koto?', $prompt);
        self::assertStringContainsString('[[LEAD:stage=', $prompt);
    }

    public function testClinicVerticalDoesNotLeakEcommerceRules(): void
    {
        $prompt = $this->builder()->build([
            'business_name' => 'City Clinic',
            'profile' => ['business_type' => 'clinic'],
            'contact' => [],
            'lead' => [],
            'history' => [],
        ]);

        self::assertStringContainsString('book an appointment', $prompt);
        self::assertStringContainsString('Never give a diagnosis.', $prompt);
        self::assertStringNotContainsString('Quote only listed prices.', $prompt);
    }

    public function testUnknownVerticalFallsBackToGeneric(): void
    {
        $prompt = $this->builder()->build([
            'business_name' => 'Something',
            'profile' => ['business_type' => 'spaceship-repair'],
            'contact' => [],
            'lead' => [],
            'history' => [],
        ]);

        self::assertStringContainsString('Something', $prompt);
        self::assertStringContainsString('MACHINE DIRECTIVES', $prompt);
    }
}
