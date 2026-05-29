<?php

declare(strict_types=1);

namespace Tiime\MonologMasker;

use Tiime\MonologMasker\Masker\Masker;
use Tiime\MonologMasker\Matcher\ChainValueMatcher;
use Tiime\MonologMasker\Matcher\CreditCardMatcher;
use Tiime\MonologMasker\Matcher\KeyListMatcher;
use Tiime\MonologMasker\Matcher\KeyMatcherInterface;
use Tiime\MonologMasker\Matcher\RegexValueMatcher;
use Tiime\MonologMasker\Matcher\SegmentKeyMatcher;
use Tiime\MonologMasker\Matcher\ValueMatcherInterface;
use Tiime\MonologMasker\Processor\MaskingProcessor;
use Tiime\MonologMasker\Strategy\FullMaskStrategy;
use Tiime\MonologMasker\Strategy\MaskStrategyInterface;

/**
 * Fluent factory wiring matchers, strategy and engine together with
 * secure-by-default settings, so the common case is a one-liner:
 *
 *     $logger->pushProcessor(MaskerBuilder::create()->buildProcessor());
 *
 * Defaults: segment-aware key matching (catches `db_password`, `userToken`…),
 * value matching with Luhn-validated card detection, message masking ON and
 * object traversal ON. Each can be overridden; methods return a new instance
 * (the builder is immutable).
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
        private readonly bool $exactKeys = false,
        private readonly bool $maskMessage = true,
        private readonly bool $traverseObjects = true,
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
     * Matches keys by whole-string equality instead of the default segment
     * matching (no compound-key detection, fewer false positives).
     */
    public function matchKeysExactly(): self
    {
        return $this->cloneWith(exactKeys: true);
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
     * Replaces the value matcher entirely (ignores the default patterns and
     * card detection).
     */
    public function withValueMatcher(ValueMatcherInterface $matcher): self
    {
        return $this->cloneWith(valueMatcher: $matcher);
    }

    /**
     * Disables value-based detection; only key-based masking remains.
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

    /**
     * Toggles masking of the log message itself (default: on).
     */
    public function maskMessage(bool $enabled = true): self
    {
        return $this->cloneWith(maskMessage: $enabled);
    }

    /**
     * Toggles recursion into objects found in the context (default: on). When
     * off, objects are left untouched (and serialised as-is by Monolog).
     */
    public function traverseObjects(bool $enabled = true): self
    {
        return $this->cloneWith(traverseObjects: $enabled);
    }

    public function buildMasker(): Masker
    {
        return new Masker(
            $this->keyMatcher ?? $this->defaultKeyMatcher(),
            $this->valueMatchingEnabled ? ($this->valueMatcher ?? $this->defaultValueMatcher()) : null,
            $this->strategy ?? new FullMaskStrategy(),
            $this->maxDepth,
            $this->traverseObjects,
        );
    }

    public function buildProcessor(): MaskingProcessor
    {
        return new MaskingProcessor($this->buildMasker(), $this->maskMessage);
    }

    private function defaultKeyMatcher(): KeyMatcherInterface
    {
        return $this->exactKeys
            ? KeyListMatcher::withDefaults($this->additionalKeys)
            : SegmentKeyMatcher::withDefaults($this->additionalKeys);
    }

    private function defaultValueMatcher(): ValueMatcherInterface
    {
        return new ChainValueMatcher(
            RegexValueMatcher::withDefaults($this->additionalPatterns),
            new CreditCardMatcher(),
        );
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
        ?bool $exactKeys = null,
        ?bool $maskMessage = null,
        ?bool $traverseObjects = null,
    ): self {
        return new self(
            $keyMatcher ?? $this->keyMatcher,
            $valueMatcher ?? $this->valueMatcher,
            $strategy ?? $this->strategy,
            $maxDepth ?? $this->maxDepth,
            $additionalKeys ?? $this->additionalKeys,
            $additionalPatterns ?? $this->additionalPatterns,
            $valueMatchingEnabled ?? $this->valueMatchingEnabled,
            $exactKeys ?? $this->exactKeys,
            $maskMessage ?? $this->maskMessage,
            $traverseObjects ?? $this->traverseObjects,
        );
    }
}
