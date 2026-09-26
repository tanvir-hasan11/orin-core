<?php

declare(strict_types=1);

namespace Orin\Tests\AI;

use Orin\Services\AgentPromptBuilder;
use Orin\Services\VerticalPromptBuilder;
use Orin\Services\VerticalRegistry;
use PHPUnit\Framework\TestCase;

final class AgentPromptBuilderTest extends TestCase
{
    private function builder(): AgentPromptBuilder
    {
        $verticals = new VerticalRegistry([
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
        ]);

        return new AgentPromptBuilder($verticals, new VerticalPromptBuilder($verticals));
    }

    public function testAgentIdentityAndKnowledgeAppearInThePrompt(): void
    {
        $prompt = $this->builder()->build([
            'business_name' => 'Dhaka Leather',
            'profile' => ['business_type' => 'ecommerce', 'tone' => 'friendly', 'delivery_info' => 'Dhaka 60 taka'],
            'agent' => ['name' => 'Rima', 'role_label' => 'Sales Assistant', 'autonomy' => 'semi_auto', 'tone' => 'warm'],
            'skills' => ['capture_lead' => true, 'qualify_lead' => true, 'take_order' => true],
            'knowledge' => [['title' => 'Return policy', 'body' => 'Seven day exchange.']],
            'contact' => ['name' => 'Tanvir', 'phone' => '01712345678'],
            'lead' => ['stage' => 'new'],
            'history' => [['direction' => 'in', 'body' => 'wallet er price koto?']],
        ]);

        self::assertStringContainsString('You are Rima', $prompt);
        self::assertStringContainsString('Dhaka Leather', $prompt);
        self::assertStringContainsString('online shop', $prompt);
        self::assertStringContainsString('Return policy', $prompt);
        self::assertStringContainsString('Customer: wallet er price koto?', $prompt);
        self::assertStringContainsString('[[ORDER:', $prompt);
    }

    public function testDisabledSkillsAreNotAdvertised(): void
    {
        $prompt = $this->builder()->build([
            'business_name' => 'City Clinic',
            'profile' => ['business_type' => 'clinic'],
            'agent' => ['name' => 'Sathi', 'autonomy' => 'semi_auto'],
            'skills' => ['book_appointment' => true, 'take_order' => false],
            'knowledge' => [],
            'contact' => [],
            'lead' => [],
            'history' => [],
        ]);

        self::assertStringContainsString('book an appointment', $prompt);
        self::assertStringContainsString('[[APPOINTMENT:', $prompt);
        self::assertStringNotContainsString('[[ORDER:', $prompt);
    }

    public function testOwnerInstructionsAreIncludedWhenSet(): void
    {
        $prompt = $this->builder()->build([
            'business_name' => 'Shop',
            'profile' => ['business_type' => 'ecommerce'],
            'agent' => ['name' => 'Bot', 'autonomy' => 'full_auto', 'system_instructions' => 'Mention free delivery over 2000 taka.'],
            'skills' => ['answer_from_knowledge' => true],
            'knowledge' => [],
            'contact' => [],
            'lead' => [],
            'history' => [],
        ]);

        self::assertStringContainsString('Mention free delivery over 2000 taka.', $prompt);
    }

    public function testUnknownVerticalFallsBackToGeneric(): void
    {
        $prompt = $this->builder()->build([
            'business_name' => 'Something',
            'profile' => ['business_type' => 'spaceship-repair'],
            'agent' => ['name' => 'Bot'],
            'skills' => [],
            'knowledge' => [],
            'contact' => [],
            'lead' => [],
            'history' => [],
        ]);

        self::assertStringContainsString('Something', $prompt);
        self::assertStringContainsString('MACHINE DIRECTIVES', $prompt);
    }
}
