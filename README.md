# 🛡️ Monolog Masker

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
$logger->pushProcessor(MaskerBuilder::create()->buildProcessor());

$logger->info('payment', [
    'card_number' => '4242 4242 4242 4242',
    'token'       => 'super-secret',
    'amount'      => 1000,
]);
// context becomes:
// ['card_number' => '████████', 'token' => '████████', 'amount' => 1000]
```

The processor walks `context` and `extra` recursively and masks two things:

- **Sensitive keys** — values whose key matches a known sensitive name
  (`password`, `token`, `api_key`, `authorization`, …). The whole sub-tree under
  a sensitive key is collapsed, so nested secrets cannot slip through.
- **Sensitive values** — leaf strings that *look* like a secret or PII (email,
  credit card, IBAN, JWT, `Bearer …`, Stripe-style keys), regardless of their key.

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
- `withoutValueMatching()` — key-based masking only (skip the regex pass).

### Masking strategies

| Strategy | Result | When |
|----------|--------|------|
| `FullMaskStrategy` (default) | `████████` | Zero leakage — recommended |
| `PartialMaskStrategy(visible: N)` | `███1234` | Keep a tail for debugging/correlation |

Both are configurable (placeholder, mask character, number of visible chars), and
you can implement `MaskStrategyInterface` for anything else.

## 🧱 Architecture

The masking engine is decoupled from Monolog so it can be tested and reused on
its own:

- `Masker` — recursive, immutable engine (never mutates its input; bounded by a
  configurable max depth that also guards against self-referential arrays).
- `MaskingProcessor` — thin Monolog adapter (`ProcessorInterface`).
- `Matcher\*` — pluggable key/value detection (`KeyListMatcher`, `RegexValueMatcher`).
- `Strategy\*` — pluggable masking (`FullMaskStrategy`, `PartialMaskStrategy`).
- `MaskerBuilder` — fluent factory tying it all together with sane defaults.

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