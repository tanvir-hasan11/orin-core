<?php

declare(strict_types=1);

namespace Orin\Services;

/**
 * Composes the system prompt for one customer turn.
 *
 * The layering is what makes each merchant's agent behave like a member of
 * their own staff instead of a generic chatbot:
 *
 *   1. who this agent is
 *   2. the business facts the merchant entered
 *   3. what kind of business this is (vertical rules)
 *   4. what the merchant taught it (knowledge base)
 *   5. what it is allowed to do (skills) and the exact directive syntax
 *   6. the merchant's own extra rules
 *   7. when to bring in a human
 *   8. who the customer is and where the lead stands
 *   9. the conversation so far
 *  10. the machine directives it may append
 */
final class AgentPromptBuilder
{
    public function __construct(
        private VerticalRegistry $verticals,
        private VerticalPromptBuilder $verticalBlock,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function build(array $context): string
    {
        $agent = is_array($context['agent'] ?? null) ? $context['agent'] : [];
        $profile = is_array($context['profile'] ?? null) ? $context['profile'] : [];
        $contact = is_array($context['contact'] ?? null) ? $context['contact'] : [];
        $lead = is_array($context['lead'] ?? null) ? $context['lead'] : [];
        $history = is_array($context['history'] ?? null) ? $context['history'] : [];
        $knowledge = is_array($context['knowledge'] ?? null) ? $context['knowledge'] : [];
        $skills = is_array($context['skills'] ?? null) ? $context['skills'] : [];

        $company = (string) ($context['business_name'] ?? 'this business');
        $type = (string) ($profile['business_type'] ?? 'generic');
        $agentName = (string) ($agent['name'] ?? 'the assistant');
        $roleLabel = (string) ($agent['role_label'] ?? 'assistant');
        $tone = (string) ($agent['tone'] ?? ($profile['tone'] ?? 'friendly'));
        $autonomy = (string) ($agent['autonomy'] ?? 'semi_auto');

        $parts = [];

        $parts[] = implode("\n", [
            'WHO YOU ARE',
            'You are ' . $agentName . ', the ' . $roleLabel . ' for ' . $company . '.',
            'You handle this business messaging all day: you answer questions, qualify people, take orders and bookings, and you know when to bring in a human.',
        ]);

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
        if (!empty($context['catalogue'])) {
            $facts[] = 'Products and prices:' . "\n" . (string) $context['catalogue'];
        }
        if ($facts !== []) {
            $parts[] = 'BUSINESS FACTS' . "\n" . implode("\n", $facts);
        }

        $parts[] = 'YOUR BUSINESS TYPE' . "\n" . $this->verticalBlock->block($type);

        if ($knowledge !== []) {
            $lines = [];
            foreach ($knowledge as $entry) {
                $body = trim((string) ($entry['body'] ?? ''));
                if ($body === '') {
                    continue;
                }
                if (mb_strlen($body) > 900) {
                    $body = mb_substr($body, 0, 900) . '...';
                }
                $lines[] = '- ' . (string) ($entry['title'] ?? '') . ': ' . $body;
            }
            if ($lines !== []) {
                $parts[] = implode("\n", [
                    'WHAT YOU KNOW ABOUT THIS BUSINESS',
                    'Answer from these entries whenever they are relevant. If the answer is not here and not in the business facts, say you will check and raise a handoff.',
                    implode("\n", $lines),
                ]);
            }
        }

        $parts[] = $this->skillsSection($skills);

        $rules = [];
        $rules[] = 'LANGUAGE AND TONE';
        $rules[] = '- Reply in whatever language or mix the customer used. Banglish gets Banglish. Bengali script gets Bengali script. English gets English.';
        $rules[] = '- Sound like a person on WhatsApp, not a call centre. Short sentences. No corporate wording.';
        $rules[] = '- Ask at most one question per message.';
        $rules[] = '- Never mention that you are an AI unless you are asked directly. If asked, say so honestly and offer a human.';
        $rules[] = 'Tone: ' . $tone . '.';
        $rules[] = '';
        $rules[] = 'MONEY RULES - NOT NEGOTIABLE';
        $rules[] = '- Only state a price that appears in the business facts or the knowledge above, exactly as written.';
        $rules[] = '- If a price is not there, say you will confirm it and raise a handoff. Never estimate, never round, never guess.';
        $rules[] = '- Never promise a delivery time or a stock level you were not given.';

        if ($autonomy === 'semi_auto') {
            $rules[] = '- Orders and appointments you record are shown to a human for confirmation. Tell the customer you are noting it down and the team will confirm shortly.';
        }

        if (!empty($agent['system_instructions'])) {
            $rules[] = '';
            $rules[] = 'THE OWNER ALSO ASKED FOR THIS';
            $rules[] = (string) $agent['system_instructions'];
        }

        $parts[] = implode("\n", $rules);

        $escalation = [
            'WHEN TO BRING IN A HUMAN',
            '- The customer asks for a human, is angry, or mentions a refund, a legal matter or the police.',
            '- The customer asks something the business facts and knowledge do not answer.',
            '- You are unsure about anything to do with money.',
        ];
        if (!empty($agent['escalation_rules'])) {
            $escalation[] = '- ' . str_replace("\n", "\n- ", trim((string) $agent['escalation_rules']));
        }
        $parts[] = implode("\n", $escalation);

        $customer = [];
        $customer[] = 'Name: ' . $this->orUnknown((string) ($contact['name'] ?? ''));
        $customer[] = 'Phone: ' . $this->orUnknown((string) ($contact['phone'] ?? ''));
        $customer[] = 'Address: ' . $this->orUnknown((string) ($contact['address'] ?? ''));
        if (!empty($lead['interest'])) {
            $customer[] = 'Interest so far: ' . (string) $lead['interest'];
        }
        if (!empty($lead['stage'])) {
            $customer[] = 'Lead stage: ' . (string) $lead['stage'];
        }
        $customer[] = 'Channel: ' . (string) ($context['channel'] ?? 'whatsapp');
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
                $who = ((string) ($row['direction'] ?? 'in')) === 'in' ? 'Customer' : $agentName;
                $lines[] = $who . ': ' . $body;
            }
            if ($lines !== []) {
                $parts[] = 'CONVERSATION SO FAR' . "\n" . implode("\n", $lines);
            }
        }

        $parts[] = $this->directivesSection($type, $skills);

        return implode("\n\n", $parts);
    }

    /** @param array<string, bool> $skills */
    private function skillsSection(array $skills): string
    {
        $lines = [];

        foreach (AgentService::SKILLS as $skill => $meta) {
            if (($skills[$skill] ?? false) !== true) {
                continue;
            }
            $lines[] = '- ' . $meta['label'] . ': ' . $meta['detail'];
        }

        $lines[] = '- Hand the thread to a human: this is always available to you.';

        return 'WHAT YOU CAN DO' . "\n" . implode("\n", $lines);
    }

    /** @param array<string, bool> $skills */
    private function directivesSection(string $type, array $skills): string
    {
        $stages = implode(', ', array_keys($this->verticals->stages($type)));

        $lines = [];
        $lines[] = 'MACHINE DIRECTIVES - the customer must never see these lines';
        $lines[] = 'Write your reply to the customer first. Then, only if something actually changed or you need the system to do something, add directive lines at the very end, one per line.';
        $lines[] = '';

        if (($skills['qualify_lead'] ?? false) === true) {
            $lines[] = '[[LEAD:stage=<one of: ' . $stages . '>]]';
        }
        if (($skills['capture_lead'] ?? false) === true) {
            $lines[] = '[[LEAD:phone=01XXXXXXXXX]]  and also allowed: name, email, address, interest';
            $lines[] = 'You may combine them: [[LEAD:stage=qualified|phone=01712345678|address=House 12, Road 4]]';
        }
        if (($skills['take_order'] ?? false) === true) {
            $lines[] = '[[ORDER:product=Premium Leather Wallet|qty=1|price=1250|note=call before delivery]]';
            $lines[] = 'Only raise an ORDER after you have product, quantity, name, phone and a complete address.';
        }
        if (($skills['book_appointment'] ?? false) === true) {
            $lines[] = '[[APPOINTMENT:date=2026-10-02|time=17:00|for=consultation|note=first visit]]';
        }
        if (($skills['schedule_followup'] ?? false) === true) {
            $lines[] = '[[FOLLOWUP:in=2d|reason=customer wanted to think about it]]  - in accepts 30m, 3h, 2d, 1w';
        }
        if (($skills['share_catalogue'] ?? false) === true) {
            $lines[] = '[[CATALOGUE:category=wallets]]  - add this when the customer asks to see what you have';
        }
        if (($skills['check_delivery_charge'] ?? false) === true) {
            $lines[] = '[[LOCATION:area=Chattogram]]  - add this when the customer gives you their area';
        }

        $lines[] = '[[HANDOFF:short reason]]  - when you need a human';
        $lines[] = '';
        $lines[] = 'If nothing changed, add no directive lines at all. Never invent a directive that is not listed above.';

        return implode("\n", $lines);
    }

    private function orUnknown(string $value): string
    {
        $value = trim($value);

        return $value === '' ? 'unknown' : $value;
    }
}
