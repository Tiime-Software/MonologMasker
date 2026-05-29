<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Matcher;

use Tiime\MonologMasker\Strategy\MaskStrategyInterface;

/**
 * Detects credit-card numbers with far fewer false positives than a plain
 * digit-run regex: a candidate is only treated as a card when it has a valid
 * length (13–19 digits) AND passes the Luhn checksum. This keeps timestamps,
 * identifiers and counters out of the redaction.
 */
final class CreditCardMatcher implements ValueMatcherInterface
{
    private const CANDIDATE = '/\b(?:\d[ \-]?){12,18}\d\b/';

    public function matches(string $value): bool
    {
        preg_match_all(self::CANDIDATE, $value, $matches);

        foreach ($matches[0] as $candidate) {
            if ($this->isCardNumber($candidate)) {
                return true;
            }
        }

        return false;
    }

    public function redact(string $value, MaskStrategyInterface $strategy): string
    {
        if ('' === $value) {
            return $value;
        }

        return preg_replace_callback(
            self::CANDIDATE,
            fn (array $matches): string => $this->isCardNumber($matches[0])
                ? $strategy->mask($matches[0])
                : $matches[0],
            $value,
        ) ?? $value;
    }

    private function isCardNumber(string $candidate): bool
    {
        // The candidate regex already constrains the run to 13–19 digits (only
        // spaces/dashes as separators), so we just strip those and check Luhn.
        return $this->passesLuhn(str_replace([' ', '-'], '', $candidate));
    }

    private function passesLuhn(string $digits): bool
    {
        $sum = 0;
        $double = false;

        for ($i = \strlen($digits) - 1; $i >= 0; --$i) {
            $digit = (int) $digits[$i];

            if ($double) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $double = !$double;
        }

        return 0 === $sum % 10;
    }
}
