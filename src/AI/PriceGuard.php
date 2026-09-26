<?php

declare(strict_types=1);

namespace Orin\AI;

/**
 * Anti-hallucination Price Guard.
 *
 * Every currency amount the AI says out loud must exist in the merchant's
 * catalog. This class scans an outbound reply for currency figures and
 * reports which ones cannot be traced back to a known price.
 *
 * Why this matters here: a Bangladeshi merchant doing cash-on-delivery
 * eats the loss when a customer is quoted a price the AI invented. This
 * guard makes that impossible to ship.
 */
final class PriceGuard
{
    /** Matches ৳1,250 / Tk 1250 / BDT 1,250 / 1250 taka / 1250tk */
    private const CURRENCY_PATTERN =
        '/(?:৳|tk\.?|bdt|taka)\s*([0-9][0-9,]*(?:\.[0-9]{1,2})?)|([0-9][0-9,]*(?:\.[0-9]{1,2})?)\s*(?:৳|tk\.?|bdt|taka)/iu';

    /**
     * @param float[] $knownPrices Every price the merchant has authorised,
     *                             in taka. Derived from the catalog plus any
     *                             delivery charges and discounts in play.
     */
    public function __construct(private readonly array $knownPrices)
    {
    }

    /**
     * Scan a reply and classify every currency figure it contains.
     *
     * @return PriceGuardResult
     */
    public function inspect(string $reply): PriceGuardResult
    {
        preg_match_all(self::CURRENCY_PATTERN, $reply, $matches, PREG_SET_ORDER);

        $mentioned = [];
        $unverified = [];

        foreach ($matches as $match) {
            $raw = $match[1] !== '' ? $match[1] : ($match[2] ?? '');
            if ($raw === '') {
                continue;
            }

            $amount = (float) str_replace(',', '', $raw);
            $mentioned[] = $amount;

            if (!$this->isKnown($amount)) {
                $unverified[] = $amount;
            }
        }

        return new PriceGuardResult(
            mentioned:  array_values(array_unique($mentioned)),
            unverified: array_values(array_unique($unverified)),
        );
    }

    /** A quoted amount is acceptable when it matches a known price to the taka. */
    private function isKnown(float $amount): bool
    {
        foreach ($this->knownPrices as $known) {
            if (abs($known - $amount) < 0.01) {
                return true;
            }
        }
        return false;
    }

    /**
     * Last-resort remediation: strip currency figures we could not verify.
     *
     * The caller should prefer regenerating the reply with the verified
     * prices injected into the prompt. This method exists so a bad reply
     * never reaches a customer in full.
     */
    public function redact(string $reply): string
    {
        return (string) preg_replace_callback(
            self::CURRENCY_PATTERN,
            function (array $m): string {
                $raw = $m[1] !== '' ? $m[1] : ($m[2] ?? '');
                $amount = (float) str_replace(',', '', $raw);
                return $this->isKnown($amount) ? $m[0] : '[price on request]';
            },
            $reply
        );
    }
}
