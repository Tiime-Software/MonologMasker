# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- Object traversal in the masking engine (`JsonSerializable`, `Stringable`, public
  properties), with object-cycle protection — secrets carried by objects in the
  context are no longer leaked.
- Log **message** masking in `MaskingProcessor` (on by default).
- `SegmentKeyMatcher`: segment-aware key matching that catches compound/nested
  keys (`db_password`, `userToken`, `x-api-key`) without false positives like
  `tokenizer`.
- `CreditCardMatcher`: Luhn-validated card detection (replaces the broad
  digit-run regex, removing false positives on timestamps/identifiers).
- `ChainValueMatcher` to compose value matchers.
- `ValueMatcherInterface::redact()` for sub-string masking (only the matched
  token is masked, surrounding text is preserved).
- Integer values are now scanned by the value matcher.
- Builder options: `matchKeysExactly()`, `maskMessage()`, `traverseObjects()`.
- Default value patterns for AWS access keys, Google API keys and PEM private-key
  blocks.

### Changed
- **BREAKING**: minimum PHP version is now **8.2** (was 8.1).
- **BREAKING (defaults)**: secure-by-default — segment key matching, message
  masking and object traversal are now on by default; value matching masks only
  the matched sub-string instead of the whole value. Use `matchKeysExactly()`,
  `maskMessage(false)` and `traverseObjects(false)` to opt out.
- `ValueMatcherInterface` gained the `redact()` method (affects custom
  implementations).

### Security
- Closes leak vectors found in audit: objects in context (H1), unmasked log
  message (H2), unmatched non-string scalars (M1), compound key names (M2).
