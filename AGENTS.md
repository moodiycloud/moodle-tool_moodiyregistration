# Repository Guidelines

## Project Structure & Module Organization
`tool_moodiyregistration` is a Moodle admin tool plugin (installed at `admin/tool/moodiyregistration` inside a Moodle codebase).

- Root entry points: `index.php`, `verify.php`, `updateddata.php`, `registrationconfirm.php`, `settings.php`.
- Core domain logic: `classes/` (`registration.php`, `api.php`, `api_wrapper.php`).
- Background jobs and events: `classes/task/`, `classes/event/`.
- Database schema and lifecycle hooks: `db/install.xml`, `db/install.php`, `db/uninstall.php`, `db/tasks.php`.
- UI and language resources: `templates/`, `lang/en/tool_moodiyregistration.php`.
- Automated tests: `tests/registration_test.php`, `tests/events_test.php`.

## Build, Test, and Development Commands
Run commands from Moodle root (not from this plugin directory):

- `php admin/cli/upgrade.php` installs or upgrades plugin changes.
- `php vendor/bin/phpunit --testsuite tool_moodiyregistration_testsuite` runs all plugin tests.
- `php vendor/bin/phpunit admin/tool/moodiyregistration/tests/registration_test.php` runs one test file.
- `php vendor/bin/phpunit --filter test_site_registration admin/tool/moodiyregistration/tests/registration_test.php` runs one test method.
- `php admin/cli/cron.php` executes scheduled tasks, useful when validating code in `classes/task/`.

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
