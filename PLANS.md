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
