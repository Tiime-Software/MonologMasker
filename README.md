<p align="center">
  <img src="https://assets.tiime.fr/auth0-universal-login/apps/logo_tiime.svg" height="40px"  alt="logo Tiime"><br>
Monolog Masker
</p>

![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg?style=flat-square)
![Monolog Version](https://img.shields.io/badge/Monolog-3.x-blue.svg?style=flat-square)
![License](https://img.shields.io/badge/License-MIT-green.svg?style=flat-square)

**A lightweight, zero-dependency Monolog processor to keep sensitive data and secrets out of your logs.**

---

## 🛑 The Problem
When logging requests, exceptions, or context arrays, it's easy to accidentally leak sensitive information like passwords, API keys, or Personally Identifiable Information (PII) into your log files or monitoring systems (Datadog, Sentry, ELK). This can lead to security breaches and GDPR compliance issues.

## ✅ The Solution
`monolog-masker` provides a simple Processor for Monolog that recursively intercepts and masks sensitive keys before they are ever written to your logs.

## 📦 Installation

You can install the package via composer:

```bash
composer require tiime/monolog-masker
```

## 🚀 Quick start

Push the processor onto your logger. With the defaults you are protected in one line:

```php
use Monolog\Logger;
use Tiime\MonologMasker\MaskerBuilder;

$logger = new Logger('app');
// Push FIRST so Monolog runs it LAST — see "Processor ordering" below.
$logger->pushProcessor(MaskerBuilder::create()->buildProcessor());

$logger->info('payment', [
    'db_password' => 'super-secret',
    'card_number' => '4242 4242 4242 4242',
    'amount'      => 1000,
]);
// context becomes:
// ['db_password' => '████████', 'card_number' => '████████', 'amount' => 1000]
```

The processor walks `message`, `context` and `extra` and masks, **secure by default**:

- **Sensitive keys** — values whose key contains a known sensitive *segment*
  (`password`, `token`, `api_key`, `authorization`, …). Matching is segment-aware,
  so compound names like `db_password`, `userToken` or `x-api-key` are caught too
  (but not `tokenizer`). The whole sub-tree under a sensitive key is collapsed.
- **Sensitive values** — tokens that *look* like a secret or PII (email, IBAN, JWT,
  `Bearer …`, AWS/Google keys, PEM blocks, and **Luhn-validated** card numbers),
  wherever they appear — including the log **message**. Only the matched
  sub-string is masked, so surrounding text is preserved.
- **Objects** in the context are traversed too (`JsonSerializable`, `Stringable`,
  or public properties), so secrets carried by DTOs don't slip through unmasked.

## ⚙️ Configuration

Everything is wired through the fluent, immutable `MaskerBuilder`:

```php
use Tiime\MonologMasker\MaskerBuilder;
use Tiime\MonologMasker\Strategy\PartialMaskStrategy;

$processor = MaskerBuilder::create()
    ->withSensitiveKeys(['x-internal-token'])   // add to the default key list
    ->withValuePatterns(['fr_phone' => '/\b0[1-9](?:\d{2}){4}\b/'])
    ->withStrategy(new PartialMaskStrategy(visible: 4)) // keep last 4 chars: "███1234"
    ->maxDepth(20)
    ->buildProcessor();

$logger->pushProcessor($processor);
```

Other knobs:

- `withKeyMatcher()` / `withValueMatcher()` — replace detection entirely with
  your own `KeyMatcherInterface` / `ValueMatcherInterface`.
- `withoutValueMatching()` — key-based masking only (skip value detection).
- `withoutValuePatterns(['email', 'iban'])` — drop specific default value
  patterns by name (keeps the others and card detection).
- `matchKeysExactly()` — whole-string key matching instead of segment-aware
  (no compound-key detection, fewer false positives).
- `maskMessage(false)` — stop masking the log message.
- `traverseObjects(false)` — leave objects untouched.

### Processor ordering

Monolog runs processors in **reverse** of their push order. To also mask the
`extra` data added by other processors (e.g. `WebProcessor`), push the masker
**first** so it runs **last**.

### Depth limit

Recursion is bounded by `maxDepth` (default 16); anything deeper — and any object
cycle — is replaced with `[TRUNCATED]` rather than traversed.

### JSON inside a string value

Some logs carry a serialized payload as a *string* — typically the raw body of a
POST request. Declare those keys with `withJsonKeys()` and the processor decodes
the JSON, masks it recursively (same key + value rules), then re-encodes it:

```php
$processor = MaskerBuilder::create()
    ->withJsonKeys(['body', 'request_body'])
    ->buildProcessor();

$logger->info('request', ['body' => '{"username":"alice","password":"hunter2"}']);
// context becomes:
// ['body' => '{"username":"alice","password":"████████"}']
```

- **Opt-in**: with no JSON keys declared the behaviour is unchanged — such a
  string stays an opaque leaf.
- A *decodable* JSON value takes precedence over a sensitive-key match, so a key
  that is both sensitive and declared JSON is looked *into* rather than collapsed.
  A non-decodable value (or a JSON scalar) falls back to the normal rules.
- Re-encoding normalises formatting (whitespace, escaping; an empty `{}` comes
  back as `[]`) — the masked payload is semantically equal, not byte-identical.

### Masking strategies

| Strategy | Result | When |
|----------|--------|------|
| `FullMaskStrategy` (default) | `████████` | Zero leakage — recommended |
| `PartialMaskStrategy(visible: N)` | `███1234` | Keep a tail for debugging/correlation |

Both are configurable (placeholder, mask character, number of visible chars), and
you can implement `MaskStrategyInterface` for anything else.

## 🎼 Symfony integration

Register the processor with the MonologBundle via the `monolog.processor` tag.

> **Heads-up:** `MaskerBuilder` is **immutable** (`with*()` returns a new instance),
> so Symfony's `calls:` cannot configure it. Use the builder as a factory object
> (defaults) or a small factory class (custom config).

**Defaults — one service:**

```yaml
# config/services.yaml
services:
    monolog_masker.builder:
        class: Tiime\MonologMasker\MaskerBuilder
        factory: ['Tiime\MonologMasker\MaskerBuilder', 'create']

    Tiime\MonologMasker\Processor\MaskingProcessor:
        factory: ['@monolog_masker.builder', 'buildProcessor']
        tags:
            # low priority so it runs LAST and also masks `extra` from other processors
            - { name: monolog.processor, priority: -100 }
```

**Custom config — via a factory class:**

```php
// src/Logging/MaskingProcessorFactory.php
namespace App\Logging;

use Tiime\MonologMasker\MaskerBuilder;
use Tiime\MonologMasker\Processor\MaskingProcessor;
use Tiime\MonologMasker\Strategy\PartialMaskStrategy;

final class MaskingProcessorFactory
{
    public static function create(): MaskingProcessor
    {
        return MaskerBuilder::create()
            ->withSensitiveKeys(['x-internal-token'])
            ->withStrategy(new PartialMaskStrategy(visible: 4))
            ->buildProcessor();
    }
}
```

```yaml
# config/services.yaml
services:
    Tiime\MonologMasker\Processor\MaskingProcessor:
        factory: ['App\Logging\MaskingProcessorFactory', 'create']
        tags:
            - { name: monolog.processor, priority: -100 }
```

Scope it to a handler or channel if needed:
`- { name: monolog.processor, handler: main }` (or `channel: app`).

## 🧱 Architecture

The masking engine is decoupled from Monolog so it can be tested and reused on
its own:

- `Masker` — recursive, immutable engine (never mutates its input; traverses
  arrays and objects; bounded by a max depth that also guards against cycles;
  optionally decodes/masks/re-encodes JSON held in string values via JSON keys).
- `MaskingProcessor` — thin Monolog adapter (`ProcessorInterface`).
- `Matcher\*` — pluggable detection: keys (`KeyListMatcher`, `SegmentKeyMatcher`)
  and values (`RegexValueMatcher`, `CreditCardMatcher`, `ChainValueMatcher`).
- `Strategy\*` — pluggable masking (`FullMaskStrategy`, `PartialMaskStrategy`).
- `MaskerBuilder` — fluent factory tying it all together with secure defaults.

## ✅ Development

This package follows the Tiime conventions (Docker + `make`):

```bash
make install    # install dependencies
make validate   # cs-check + phpstan (max) + coverage + infection + proofs — run before any commit
make test       # unit tests only
make coverage   # unit tests + 100% line/method coverage gate (pcov)
make infection  # mutation testing (MSI 100%)
make proofs     # property-based tests (black-box)
```

The suite is held to **100% line & method coverage** *and* **100% MSI** (mutation score,
via [Infection](https://infection.github.io/)). Both are enforced as hard gates in CI, so a
weak test that executes code without actually asserting its behaviour fails the build.

On top of the example-based unit tests, the library's **invariants** are checked with
**property-based tests** ([innmind/black-box](https://github.com/Innmind/BlackBox)) — idempotence,
no-leak, structure preservation, depth bounds, etc. — over hundreds of randomly generated,
auto-shrinking inputs. These live in `tests/Property` (suite `property`, run via `make proofs`);
coverage and mutation gates stay scoped to the deterministic `unit` suite.

## 📄 License

MIT — see [LICENSE](LICENSE).