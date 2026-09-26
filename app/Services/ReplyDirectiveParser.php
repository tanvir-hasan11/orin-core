<?php

declare(strict_types=1);

namespace Orin\Services;

/**
 * The AI cannot call tools directly through every provider we support, so it
 * appends machine directives to its reply instead. This class pulls them out
 * and returns clean text for the customer.
 *
 *   [[LEAD:stage=qualified|phone=01712345678]]
 *   [[HANDOFF:refund request]]
 *
 * Anything not on the whitelist below is dropped, so a model cannot smuggle
 * arbitrary column names into the database.
 */
final class ReplyDirectiveParser
{
    private const PATTERN = '/\[\[(LEAD|HANDOFF)(?::([^\]]*))?\]\]/u';

    /** @var array<int, string> */
    private const ALLOWED_FIELDS = ['phone', 'name', 'email', 'address', 'interest'];

    public function parse(string $reply): ReplyParseResult
    {
        $stage = null;
        $fields = [];
        $handoff = false;
        $reason = null;

        $clean = preg_replace_callback(
            self::PATTERN,
            static function (array $m) use (&$stage, &$fields, &$handoff, &$reason): string {
                $kind = strtoupper((string) $m[1]);
                $payload = trim((string) ($m[2] ?? ''));

                if ($kind === 'HANDOFF') {
                    $handoff = true;
                    $reason = $payload !== '' ? $payload : null;

                    return '';
                }

                foreach (explode('|', $payload) as $pair) {
                    if (!str_contains($pair, '=')) {
                        continue;
                    }
                    [$key, $value] = explode('=', $pair, 2);
                    $key = strtolower(trim($key));
                    $value = trim($value);

                    if ($key === '' || $value === '') {
                        continue;
                    }

                    if ($key === 'stage') {
                        $stage = $value;
                        continue;
                    }

                    if (in_array($key, self::ALLOWED_FIELDS, true)) {
                        $fields[$key] = $value;
                    }
                }

                return '';
            },
            $reply
        );

        $clean = $clean ?? $reply;
        $clean = (string) preg_replace('/[ \t]+\n/', "\n", $clean);
        $clean = (string) preg_replace('/\n{3,}/', "\n\n", $clean);

        return new ReplyParseResult(trim($clean), $stage, $fields, $handoff, $reason);
    }
}
