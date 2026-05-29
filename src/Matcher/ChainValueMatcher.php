<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Matcher;

use Tiime\MonologMasker\Strategy\MaskStrategyInterface;

/**
 * Combines several value matchers: {@see matches()} is their logical OR and
 * {@see redact()} applies each one in turn, so a value is cleaned by every
 * matcher in the chain.
 */
final class ChainValueMatcher implements ValueMatcherInterface
{
    /**
     * @var list<ValueMatcherInterface>
     */
    private readonly array $matchers;

    public function __construct(ValueMatcherInterface ...$matchers)
    {
        $this->matchers = array_values($matchers);
    }

    public function matches(string $value): bool
    {
        foreach ($this->matchers as $matcher) {
            if ($matcher->matches($value)) {
                return true;
            }
        }

        return false;
    }

    public function redact(string $value, MaskStrategyInterface $strategy): string
    {
        foreach ($this->matchers as $matcher) {
            $value = $matcher->redact($value, $strategy);
        }

        return $value;
    }
}
