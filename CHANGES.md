# Changelog

## Unreleased

- retry Core registration when the internal-site local UUID and URL already match
- require an authoritative 2xx acknowledgement before provisioning reports registration success
- replace UUID-based signing with pre-seeded versioned keys and a legacy bootstrap only for existing migrations
- bind update/delete signatures to method, path, raw body digest, timestamp, nonce, UUID, and key version
- bind managed-site acknowledgement proofs to the validated provisioning attempt ID and action
- emit only stable failure categories and an acknowledgement fingerprint in sanitized CLI output

## 0.2.0 - 2026-06-07

- declare support for Moodle 5.2 and 5.3 (supported range Moodle 4.5–5.3)
- raise the minimum required Moodle to 4.5 to match the supported range
- promote plugin maturity from alpha to stable

## 0.1.1 - 2026-06-02

- replace Moodle core registration wording with MoodiyCloud-specific form copy

## 0.1.0 - 2025-06-23

- initial alpha release of the MoodiyCloud site registration plugin
- normalize public licensing and ownership metadata for MoodiyCloud
- replace the invalid privacy null-provider with a metadata-based privacy provider
- expand the README for public distribution and Moodle plugins directory submission
- add Moodle.org release workflow scaffolding
- harden external endpoint origin/header handling for MoodiyCloud integrations
