# Repository Guidelines

Canonical agent instructions for `tool_moodiyregistration`. `CLAUDE.md` imports this file; task detail lives in the docs routed at the end. This repository is public: keep internal hosts, credentials and internal decisions out of it.

## Overview

Moodle admin tool plugin (`tool_moodiyregistration`) that handles site registration and verification between Moodle instances and the Moodiy external service. Installed at `{moodle}/admin/tool/moodiyregistration/`. Supports Moodle 4.5–5.3 (`supported = [405, 503]`, requires 4.5); `version.php` holds the current release.

Layout: entry points `index.php`, `verify.php`, `updateddata.php`, `registrationconfirm.php`, `settings.php`; logic in `classes/` (`registration.php`, `api.php`, `api_wrapper.php`, `task/`, `event/`); schema and lifecycle in `db/`; `templates/`; `lang/en/`; `cli/`; `tests/`. File-by-file map: `docs/architecture.md`.

## Rules agents otherwise get wrong

- Never call the real Moodiy API from tests: mock it via `$CFG->tool_moodiyregistration_test_api_wrapper` (a PHPUnit mock of `api_wrapper`).
- `PUT`/`DELETE /api/site/register/` use HMAC-SHA256 with the site UUID as the secret.
- **`tool_moodiymobile`** — If enabled, blocks unregistration (`can_unregister()` returns false).

## Build, Test, and Development Commands
Run commands from Moodle root (not from this plugin directory):

- `php admin/cli/upgrade.php` installs or upgrades plugin changes.
- `php vendor/bin/phpunit --testsuite tool_moodiyregistration_testsuite` runs all plugin tests.
- `php vendor/bin/phpunit admin/tool/moodiyregistration/tests/registration_test.php` runs one test file.
- `php vendor/bin/phpunit --filter test_site_registration admin/tool/moodiyregistration/tests/registration_test.php` runs one test method.
- `php admin/cli/cron.php` executes scheduled tasks, useful when validating code in `classes/task/`.

Always tee test output to temp files: `php vendor/bin/phpunit ... 2>&1 | tee /tmp/test_output.txt`

Local CI: run the workflow-equivalent checks from the meta-repo before pushing (plan and prerequisites: `../moodle-plugin-quality-toolkit.md`):

```bash
make -C moodle_plugins pre-pr PLUGIN=moodle-tool_moodiyregistration
make -C moodle_plugins lint-only PLUGIN=moodle-tool_moodiyregistration
moodle_plugins/scripts/summarize.sh moodle-tool_moodiyregistration
```

`make pre-pr` mirrors this plugin's current GitHub workflow. `make lint-only`
is the broader repo-level PHPCS sweep when you only need coding-style feedback.

GitHub Actions (`.github/workflows/ci.yml`) runs the standalone `moodle-plugin-ci` recipe with zero allowed PHPCS/PHPDoc warnings; publishing is handled separately by `.github/workflows/moodle-release.yml`. Matrix detail: `docs/architecture.md` → GitHub Actions CI.

## Coding Style & Naming Conventions
- Follow Moodle PHP conventions: 4-space indentation, PHPDoc blocks, and `defined('MOODLE_INTERNAL') || die();` guards.
- Keep code in the `tool_moodiyregistration` namespace.
- Follow existing naming patterns: task/event classes use lowercase with underscores (for example `siteurl_update_task`), and tests use `*_test.php` with `test_*` methods.
- Prefer Moodle APIs (`get_config`, `$DB`, events, tasks, cache) over custom helpers when equivalent APIs exist.

## Testing Guidelines
- Use Moodle PHPUnit (`\advanced_testcase`) for unit/integration coverage.
- Reset state in tests with `$this->resetAfterTest(true)` to isolate DB and config changes.
- Mock external API calls via `$CFG->tool_moodiyregistration_test_api_wrapper` instead of calling real endpoints.
- No hard coverage threshold is defined; every bug fix or behavior change should include a targeted test update.

## Commit & Pull Request Guidelines
- Existing history favors short imperative subjects, often with issue refs, e.g. `Fix string for 5.1` or `Remove local registration ... (#15)`.
- Keep commits scoped to one logical change.
- PRs should include: summary, rationale, exact test commands run, and linked issue/PR references.
- Add screenshots only for UI/template changes (for example updates to `templates/warningbox.mustache`).

## When you… | Read first

| When you… | Read first |
| --- | --- |
| Change registration, the API client, tasks, events, the table, entry points or constants | `docs/architecture.md` |
| Change retries or request signing | `PLANS.md` |
| Touch the CLI registration bootstrap (`cli/`) | `codex-usage-policy-http-bootstrap-1343.md` |
| Prepare a release or the Moodle plugins directory submission | `CHANGES.md`; `MOODLE_ORG_SUBMISSION.md`; `README.md` |
| Run or debug the local CI runner | `../AGENTS.md` (meta `moodle_plugins/`) → Local CI Runner; `../moodle-plugin-quality-toolkit.md` |
