# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Moodle admin tool plugin (`tool_moodiyregistration`) that handles site registration and verification between Moodle instances and the Moodiy external service. Installed at `{moodle}/admin/tool/moodiyregistration/`. Supports Moodle 5.0-5.01, currently at v0.1.0 (alpha).

## Testing

This plugin runs inside a Moodle installation. Tests require a configured Moodle environment with PHPUnit initialized.

```bash
# Run all plugin tests (from Moodle root)
php vendor/bin/phpunit --testsuite tool_moodiyregistration_testsuite

# Run a specific test file
php vendor/bin/phpunit admin/tool/moodiyregistration/tests/registration_test.php

# Run a single test method
php vendor/bin/phpunit --filter test_site_registration admin/tool/moodiyregistration/tests/registration_test.php
```

Always tee test output to temp files: `php vendor/bin/phpunit ... 2>&1 | tee /tmp/test_output.txt`

## Local CI

Run the workflow-equivalent checks from the meta-repo before pushing:

```bash
cd ../..
make -C moodle_plugins pre-pr PLUGIN=moodle-tool_moodiyregistration
make -C moodle_plugins lint-only PLUGIN=moodle-tool_moodiyregistration
moodle_plugins/scripts/summarize.sh moodle-tool_moodiyregistration
```

`make pre-pr` mirrors this plugin's current GitHub workflow. `make lint-only`
is the broader repo-level PHPCS sweep when you only need coding-style feedback.

Plan, prerequisites, and per-step explanation: [`moodle-plugin-quality-toolkit.md`](../moodle-plugin-quality-toolkit.md).
## GitHub Actions CI

GitHub Actions CI uses Catalyst's reusable Moodle workflow
(`.github/workflows/ci.yml`). `phplint`, `phpcs`, `phpdoc`, `validate`,
`savepoints`, `mustache`, and `phpunit` run with
`codechecker_max_warnings: 0`. `behat`, `grunt`, and the reusable workflow's
`release` job remain disabled. Publishing is handled separately by
`.github/workflows/moodle-release.yml`.

## Architecture

### Core Classes (`classes/`)

- **`registration.php`** — Central class (~900 lines). All methods are static. Manages the full lifecycle: `register()`, `unregister()`, `update_manual()`, `update_registration()`, `is_registered()`, `get_site_info()`, `get_site_metadata()`. Collects comprehensive site data (courses, users, plugins, AI stats, etc.) for transmission to Moodiy.

- **`api.php`** — HTTP communication with Moodiy backend. Three endpoints:
  - `POST /api/site/register` — initial registration
  - `PUT /api/site/register/` — update (HMAC-SHA256 auth using site_uuid as secret)
  - `DELETE /api/site/register/` — unregister (HMAC-SHA256 auth)

- **`api_wrapper.php`** — Thin wrapper around `api.php` for dependency injection in tests.

- **`moodiy_registration_form.php`** — Moodle form definition for the registration UI.

### Testing Pattern

Tests mock the API layer via `$CFG->tool_moodiyregistration_test_api_wrapper`. Set this to a PHPUnit mock of `api_wrapper` to prevent real API calls. All test classes extend `\advanced_testcase` and call `$this->resetAfterTest(true)`.

### Scheduled Tasks (`classes/task/`)

- **`siteurl_update_task`** — Daily, detects site URL changes and updates Moodiy.
- **`upgrade_monitor_task`** — Daily, detects Moodle/plugin version changes, triggers registration update.
- **`internal_site_registration`** — Ad-hoc task for auto-registering MoodiyCloud-hosted sites (triggered during install if UUID is configured).
- **`process_update_request`** — Ad-hoc task processing external update requests.

### Events (`classes/event/`)

Four events: `moodiy_registration` (create), `moodiyregistration_updated` (update), `moodiy_unregistration` (delete), `update_request` (read). All target table `tool_moodiyregistration`.

### Database

Single table `tool_moodiyregistration` with columns: `id`, `site_uuid` (unique), `site_url`, `timecreated`, `timemodified`. Schema in `db/install.xml`.

### Entry Points

- `index.php` — Main admin page (registration form, unregister action)
- `registrationconfirm.php` — Post-registration confirmation
- `verify.php` — Verification callback endpoint from Moodiy
- `updateddata.php` — Receives data updates from Moodiy
- `settings.php` — Registers admin menu entry under Site Administration

### Key Constants in `registration.php`

- `MOODIYURL` = `https://moodiycloud.com`
- `MOODIY_API_URL` = `https://api.moodiycloud.com`
- `FORM_FIELDS` — Array of tracked form field names
- API URL overridable via `$CFG->moodiy_api_url` or plugin config `apiurl`

### Integration with Other Moodiy Plugins

- **`tool_moodiymobile`** — If enabled, blocks unregistration (`can_unregister()` returns false).
- Registration collects mobile service status and device counts when moodiymobile is present.
