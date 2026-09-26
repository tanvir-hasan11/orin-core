<?php

declare(strict_types=1);

namespace Orin\Services;

/**
 * The agent cannot call tools directly through every provider we support, so it
 * appends machine directives to its reply instead. This class pulls them out
 * and hands back clean text for the customer.
 *
 *   [[LEAD:stage=qualified|phone=01712345678]]
 *   [[ORDER:product=Wallet|qty=1|price=1250]]
 *   [[APPOINTMENT:date=2026-10-02|time=17:00|for=consultation]]
 *   [[FOLLOWUP:in=2d|reason=customer wanted to think]]
 *   [[CATALOGUE:category=wallets]]
 *   [[LOCATION:area=Chattogram]]
 *   [[HANDOFF:refund request]]
 *
 * Every key is checked against a whitelist, so a model cannot smuggle an
 * arbitrary column name into the database.
 */
final class ReplyDirectiveParser
{
    private const PATTERN = '/\[\[(LEAD|HANDOFF|ORDER|APPOINTMENT|FOLLOWUP|CATALOGUE|LOCATION)(?::([^\]]*))?\]\]/u';

    /** @var array<int, string> */
    private const ALLOWED_FIELDS = ['phone', 'name', 'email', 'address', 'interest'];

    /** @var array<string, array<int, string>> */
    private const ALLOWED_ACTION_KEYS = [
        'order' => ['product', 'qty', 'price', 'note'],
        'appointment' => ['date', 'time', 'for', 'note'],
        'followup' => ['in', 'reason'],
        'catalogue' => ['category'],
        'location' => ['area'],
    ];

    public function parse(string $reply): ReplyParseResult
    {
        $stage = null;
        $fields = [];
        $handoff = false;
        $handoffReason = null;
        $actions = [];

        $clean = preg_replace_callback(
            self::PATTERN,
            static function (array $m) use (&$stage, &$fields, &$handoff, &$handoffReason, &$actions): string {
                $kind = strtoupper((string) $m[1]);
                $payload = trim((string) ($m[2] ?? ''));

                if ($kind === 'HANDOFF') {
                    $handoff = true;
                    $handoffReason = $payload !== '' ? mb_substr($payload, 0, 240) : null;

                    return '';
                }

                if ($kind === 'LEAD') {
                    foreach (self::pairs($payload) as $key => $value) {
                        if ($key === 'stage') {
                            $stage = mb_substr($value, 0, 32);
                            continue;
                        }
                        if (in_array($key, self::ALLOWED_FIELDS, true)) {
                            $fields[$key] = mb_substr($value, 0, 250);
                        }
                    }

                    return '';
                }

                $actionKey = strtolower($kind);
                $allowed = self::ALLOWED_ACTION_KEYS[$actionKey] ?? [];
                $pairs = [];

                foreach (self::pairs($payload) as $key => $value) {
                    if (in_array($key, $allowed, true)) {
                        $pairs[$key] = mb_substr($value, 0, 250);
                    }
                }

                if ($pairs !== []) {
                    $actions[] = ['kind' => $actionKey, 'payload' => $pairs];
                }

                return '';
            },
            $reply
        );

        $clean = $clean ?? $reply;
        $clean = (string) preg_replace('/[ \t]+\n/', "\n", $clean);
        $clean = (string) preg_replace('/\n{3,}/', "\n\n", $clean);

        return new ReplyParseResult(trim($clean), $stage, $fields, $handoff, $handoffReason, $actions);
    }

    /**
     * "a=1|b=2" into an ordered, cleaned map. First value wins per key.
     *
     * @return array<string, string>
     */
    private static function pairs(string $payload): array
    {
        $pairs = [];

        foreach (explode('|', $payload) as $pair) {
            if (!str_contains($pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);
            $key = strtolower(trim($key));
            $value = trim($value);

            if ($key === '' || $value === '' || array_key_exists($key, $pairs)) {
                continue;
            }

            $pairs[$key] = $value;
        }

        return $pairs;
    }
}
