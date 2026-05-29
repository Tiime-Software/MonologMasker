<?php

declare(strict_types=1);

namespace Tiime\MonologMasker;

use Tiime\MonologMasker\Masker\Masker;
use Tiime\MonologMasker\Matcher\KeyListMatcher;
use Tiime\MonologMasker\Matcher\KeyMatcherInterface;
use Tiime\MonologMasker\Matcher\RegexValueMatcher;
use Tiime\MonologMasker\Matcher\ValueMatcherInterface;
use Tiime\MonologMasker\Processor\MaskingProcessor;
use Tiime\MonologMasker\Strategy\FullMaskStrategy;
use Tiime\MonologMasker\Strategy\MaskStrategyInterface;

/**
 * Fluent factory wiring matchers, strategy and engine together with sensible
 * defaults, so the common case is a one-liner:
 *
 *     $logger->pushProcessor(MaskerBuilder::create()->buildProcessor());
 *
 * Every default can be overridden. Methods return a new instance — the builder
 * is immutable.
 */
final class MaskerBuilder
{
    /**
     * @param list<string>          $additionalKeys
     * @param array<string, string> $additionalPatterns
     */
    private function __construct(
        private readonly ?KeyMatcherInterface $keyMatcher = null,
        private readonly ?ValueMatcherInterface $valueMatcher = null,
        private readonly ?MaskStrategyInterface $strategy = null,
        private readonly int $maxDepth = 16,
        private readonly array $additionalKeys = [],
        private readonly array $additionalPatterns = [],
        private readonly bool $valueMatchingEnabled = true,
    ) {
    }

    public static function create(): self
    {
        return new self();
    }

    /**
     * Adds key names to the default sensitive-key list.
     *
     * @param list<string> $keys
     */
    public function withSensitiveKeys(array $keys): self
    {
        return $this->cloneWith(additionalKeys: [...$this->additionalKeys, ...$keys]);
    }

    /**
     * Replaces the key matcher entirely (ignores the default key list).
     */
    public function withKeyMatcher(KeyMatcherInterface $matcher): self
    {
        return $this->cloneWith(keyMatcher: $matcher);
    }

    /**
     * Adds value patterns to the default pattern list.
     *
     * @param array<string, string> $patterns map of name => PCRE pattern
     */
    public function withValuePatterns(array $patterns): self
    {
        return $this->cloneWith(additionalPatterns: [...$this->additionalPatterns, ...$patterns]);
    }

    /**
     * Replaces the value matcher entirely (ignores the default patterns).
     */
    public function withValueMatcher(ValueMatcherInterface $matcher): self
    {
        return $this->cloneWith(valueMatcher: $matcher);
    }

    /**
     * Disables value-based (regex) detection; only key-based masking remains.
     */
    public function withoutValueMatching(): self
    {
        return $this->cloneWith(valueMatchingEnabled: false);
    }

    public function withStrategy(MaskStrategyInterface $strategy): self
    {
        return $this->cloneWith(strategy: $strategy);
    }

    public function maxDepth(int $maxDepth): self
    {
        return $this->cloneWith(maxDepth: $maxDepth);
    }

    public function buildMasker(): Masker
    {
        $keyMatcher = $this->keyMatcher ?? KeyListMatcher::withDefaults($this->additionalKeys);

        $valueMatcher = null;
        if ($this->valueMatchingEnabled) {
            $valueMatcher = $this->valueMatcher ?? RegexValueMatcher::withDefaults($this->additionalPatterns);
        }

        return new Masker(
            $keyMatcher,
            $valueMatcher,
            $this->strategy ?? new FullMaskStrategy(),
            $this->maxDepth,
        );
    }

    public function buildProcessor(): MaskingProcessor
    {
        return new MaskingProcessor($this->buildMasker());
    }

    /**
     * @param list<string>|null          $additionalKeys
     * @param array<string, string>|null $additionalPatterns
     */
    private function cloneWith(
        ?KeyMatcherInterface $keyMatcher = null,
        ?ValueMatcherInterface $valueMatcher = null,
        ?MaskStrategyInterface $strategy = null,
        ?int $maxDepth = null,
        ?array $additionalKeys = null,
        ?array $additionalPatterns = null,
        ?bool $valueMatchingEnabled = null,
    ): self {
        return new self(
            $keyMatcher ?? $this->keyMatcher,
            $valueMatcher ?? $this->valueMatcher,
            $strategy ?? $this->strategy,
            $maxDepth ?? $this->maxDepth,
            $additionalKeys ?? $this->additionalKeys,
            $additionalPatterns ?? $this->additionalPatterns,
            $valueMatchingEnabled ?? $this->valueMatchingEnabled,
        );
    }
}
