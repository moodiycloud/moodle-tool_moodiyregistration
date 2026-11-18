# tool_moodiyregistration architecture

Moved from `CLAUDE.md` and `AGENTS.md` (18 Nov 2026). `AGENTS.md` keeps the rules and commands and routes here.

### Core Classes (`classes/`)

- **`registration.php`** — Central class. All methods are static. Manages the full lifecycle: `register()`, `unregister()`, `update_manual()`, `update_registration()`, `is_registered()`, `get_site_info()`, `get_site_metadata()`. Collects comprehensive site data (courses, users, plugins, AI stats, etc.) for transmission to Moodiy.

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

## GitHub Actions CI

GitHub Actions CI uses the standalone official `moodle-plugin-ci` recipe in
`.github/workflows/ci.yml`. Each matrix case installs Moodle and the plugin from
source, then runs PHP lint, PHPCS/PHPDoc with zero allowed warnings, validation,
savepoints, Mustache, Grunt, PHPUnit and Behat. PHP Mess Detector is informational.
Stable 5.3 has explicit PHP 8.3/8.4 cases on PostgreSQL 17 and MariaDB 11.4;
moving main remains separate PostgreSQL development coverage. CI records exact
Moodle/plugin commits and installed version fields before checks. Publishing is
handled separately by `.github/workflows/moodle-release.yml`.

## Project Structure & Module Organization
`tool_moodiyregistration` is a Moodle admin tool plugin (installed at `admin/tool/moodiyregistration` inside a Moodle codebase).

- Root entry points: `index.php`, `verify.php`, `updateddata.php`, `registrationconfirm.php`, `settings.php`.
- Core domain logic: `classes/` (`registration.php`, `api.php`, `api_wrapper.php`).
- Background jobs and events: `classes/task/`, `classes/event/`.
- Database schema and lifecycle hooks: `db/install.xml`, `db/install.php`, `db/uninstall.php`, `db/tasks.php`.
- UI and language resources: `templates/`, `lang/en/tool_moodiyregistration.php`.
- Automated tests: `tests/registration_test.php`, `tests/events_test.php`.
