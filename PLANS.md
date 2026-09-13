# Registration Retry and Request-Signing Plan

## Scope

- Fix internal-site repair so a matching local UUID/URL is still sent to Core.
- Report success only after Core acknowledges the update; keep local repair state on remote failure.
- Replace UUID-as-HMAC-secret for newly provisioned sites with a versioned, high-entropy signing key delivered through the protected site configuration.
- Bind signatures to the logical method, canonical API path, canonical body digest, timestamp, nonce, and key version.
- Bind managed provisioning acknowledgements to a validated attempt ID and action carried inside the signed update body.
- Keep an explicit legacy fallback only for already-provisioned sites which do not yet have a v2 key.
- Add focused PHPUnit coverage and run repository CI-equivalent checks.

## Cross-repository contract

- Core owns key generation/storage, nonce replay protection, acknowledgement creation, and v2/legacy transition policy.
- Semaphore delivers the key and key version to the protected `config.premium.php` include without logging them, and requires acknowledged CLI JSON before completing provisioning.
- Cloud relays only non-secret acknowledgement facts.
- This repository never writes the signing key, nonce, or full signature to logs or CLI output.

## Progress

- [x] Read repository instructions and current retry/signing implementation.
- [x] Create isolated worktree from current `origin/main`.
- [x] Finalize exact v2 request/response names with Core/Semaphore/Cloud owners.
- [x] Implement retry and v2 signing/acknowledgement behavior.
- [x] Add exact retry, signing, and CLI-output tests.
- [x] Run focused PHPUnit and CI-equivalent checks.
- [x] Review diff and report integration/deployment requirements.
- [x] Add and verify signed provisioning-attempt/action binding from protected configuration.

## Validation notes

- Source plugin only; `kts_moodle` is intentionally not edited.
- No operational credentials or live data will be mutated by this worktree.
- Moodle 4.5.12+, PHP 8.3.32, MySQL 8.0.40: 62 PHPUnit tests passed with 292 assertions.
- PHP lint, Moodle CodeSniffer with zero warnings, PHPCompatibility 8.1+, PHPDoc, validation,
  savepoints, and Mustache lint passed. Mustache reported the repository tool's known HTML-validator
  integration warning while ESLint completed successfully.
- Deploy Core's v2 verifier/key schema before the source plugin. Newly provisioned managed sites must
  receive the protected pre-seeded key/version; legacy bootstrap is for existing null-key rows only.


## 2026-09-13 — Initial Essential key delivery for #1097

- Proven cause: fresh Essential registration receives403/legacy_bootstrap_rejected while its first signing key is absent; Premium already receives the key during provisioning. Keep the historical global compatibility control off.
- Implemented exact internal tenant validation, raw CFG/stored-key conflict checks, a Moodle lock plus transaction, immutable initial-key replay, private stdin CLI and existing signed callback/acknowledgement.
- New22 tests/43 assertions pass on real Moodle5.1.5+, PHP8.4.25 and isolated MySQL8.4.11. Coverage includes actual API v2 HMAC bytes, uncertain remote failure with retained key, invalid input, partial/foreign/static conflicts and external-site refusal.
- Complete plugin suite91 tests/363 assertions passes; existing PHPUnit docblock-metadata deprecations remain. New file Moodle PHPCS passes.
- Next: review/current-head CI, merge source plugin, let its normal CI propagate into shared runtime, then verify paired Automator/Core deployment and fresh DEV registration. No direct runtime edits or live credential mutation performed by this worktree.
