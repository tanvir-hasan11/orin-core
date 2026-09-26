<?php

declare(strict_types=1);

namespace Orin\Services;

/**
 * Composes the system prompt for one customer turn.
 *
 * The shape of the prompt is what makes ORIN behave like a shop assistant
 * instead of a chatbot: business facts first, then the vertical rules, then
 * the price/stock discipline, then the transcript.
 */
final class VerticalPromptBuilder
{
    public function __construct(private VerticalRegistry $verticals)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function build(array $context): string
    {
        $profile = is_array($context['profile'] ?? null) ? $context['profile'] : [];
        $contact = is_array($context['contact'] ?? null) ? $context['contact'] : [];
        $lead = is_array($context['lead'] ?? null) ? $context['lead'] : [];
        $history = is_array($context['history'] ?? null) ? $context['history'] : [];

        $type = (string) ($profile['business_type'] ?? 'generic');
        $vertical = $this->verticals->get($type);
        $company = (string) ($context['business_name'] ?? 'this business');

        $parts = [];
        $parts[] = sprintf('You are the messaging assistant for %s, a %s.', $company, (string) $vertical['label']);
        $parts[] = 'YOUR JOB' . "\n" . 'Your job is to ' . (string) $vertical['goal'] . '.';

        $facts = [];
        if (!empty($profile['description'])) {
            $facts[] = 'About the business: ' . (string) $profile['description'];
        }
        if (!empty($profile['working_hours'])) {
            $facts[] = 'Working hours: ' . (string) $profile['working_hours'];
        }
        if (!empty($profile['delivery_info'])) {
            $facts[] = 'Delivery: ' . (string) $profile['delivery_info'];
        }
        if (!empty($profile['service_area'])) {
            $facts[] = 'Service area: ' . (string) $profile['service_area'];
        }
        if (!empty($context['catalog'])) {
            $facts[] = 'Products and prices:' . "\n" . (string) $context['catalog'];
        }
        if ($facts !== []) {
            $parts[] = 'BUSINESS FACTS' . "\n" . implode("\n", $facts);
        }

        $parts[] = 'HOW TO RUN THIS CONVERSATION' . "\n" . (string) $vertical['instructions'];

        $parts[] = implode("\n", [
            'LANGUAGE AND TONE',
            '- Reply in whatever language or mix the customer used. Banglish gets Banglish. Bengali script gets Bengali script. English gets English.',
            '- Sound like a person on WhatsApp, not a call centre. Short sentences. No corporate wording.',
            '- Ask at most one question per message.',
            '- Never mention that you are an AI unless you are asked directly.',
            'Tone: ' . (string) ($profile['tone'] ?? 'friendly') . '.',
        ]);

        $parts[] = implode("\n", [
            'MONEY RULES - NOT NEGOTIABLE',
            '- Only state a price that appears in the business facts above, exactly as written.',
            '- If a price is not there, say you will confirm it and raise a handoff. Never estimate, never round, never guess.',
            '- Never promise a delivery time or a stock level you were not given.',
        ]);

        $customer = [];
        $customer[] = 'Name: ' . ((string) ($contact['name'] ?? '') !== '' ? (string) $contact['name'] : 'unknown');
        $customer[] = 'Phone: ' . ((string) ($contact['phone'] ?? '') !== '' ? (string) $contact['phone'] : 'unknown');
        $customer[] = 'Address: ' . ((string) ($contact['address'] ?? '') !== '' ? (string) $contact['address'] : 'unknown');
        if (!empty($lead['interest'])) {
            $customer[] = 'Interest: ' . (string) $lead['interest'];
        }
        if (!empty($lead['stage'])) {
            $customer[] = 'Lead stage: ' . (string) $lead['stage'];
        }
        $parts[] = 'CUSTOMER' . "\n" . implode("\n", $customer);

        if ($history !== []) {
            $lines = [];
            foreach ($history as $row) {
                $body = trim((string) ($row['body'] ?? ''));
                if ($body === '') {
                    continue;
                }
                if (mb_strlen($body) > 600) {
                    $body = mb_substr($body, 0, 600) . '...';
                }
                $who = ((string) ($row['direction'] ?? 'in')) === 'in' ? 'Customer' : 'Assistant';
                $lines[] = $who . ': ' . $body;
            }
            if ($lines !== []) {
                $parts[] = 'CONVERSATION SO FAR' . "\n" . implode("\n", $lines);
            }
        }

        $stages = implode(', ', array_keys($this->verticals->stages($type)));
        $parts[] = implode("\n", [
            'MACHINE DIRECTIVES - the customer must never see these lines',
            'Write your reply first. Then, only if something changed, add ONE final line:',
            '  [[LEAD:stage=<one of: ' . $stages . '>]]',
            '  [[LEAD:phone=01XXXXXXXXX]]  (also allowed: name, email, address, interest)',
            '  [[HANDOFF:short reason]]  when the customer asks for a human, is angry, or asks something you cannot answer.',
            'If nothing changed, add no directive line at all.',
        ]);

        return implode("\n\n", $parts);
    }
}
