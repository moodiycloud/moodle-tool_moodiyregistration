# Moodiy registration

`tool_moodiyregistration` is a Moodle admin tool plugin that registers a Moodle
site with MoodiyCloud and keeps the registration record up to date.

This is a MoodiyCloud integration. It is separate from Moodle's official site
registration service.

## What the plugin does

- registers the Moodle site with MoodiyCloud
- stores the local registration record used to identify the site
- keeps the registration payload updated when the site URL or core/plugin state changes
- exposes verification and update endpoints used by MoodiyCloud during registration and maintenance flows

## Supported Moodle versions

Current plugin metadata declares support for:

- Moodle `4.5`
- Moodle `5.0`
- Moodle `5.1`
- Moodle `5.2`
- Moodle `5.3`

## Installation

### Installing via uploaded ZIP file

1. Log in to your Moodle site as an admin and go to _Site administration > Plugins > Install plugins_.
2. Upload the ZIP file containing the plugin code.
3. Check the validation report and finish the installation.

### Installing manually

Copy this repository into:

```text
{your/moodle/dirroot}/admin/tool/moodiyregistration
```

Then complete the installation from _Site administration > Notifications_ or with:

```bash
php admin/cli/upgrade.php
```

## Configuration and usage

- Open _Site administration > Moodiy registration_.
- Complete the registration form and confirm the registration flow with MoodiyCloud.
- After registration, Moodle will keep the site record updated using scheduled tasks.

### Managed-site request signing

Managed provisioning may pre-seed the dedicated registration signing credential in a protected,
non-web-served Moodle configuration include:

```php
$CFG->moodiysiteregistrationuuid = '<core-issued-uuid>';
$CFG->moodiyregistrationsigningkey = '<core-issued-high-entropy-secret>';
$CFG->moodiyregistrationsigningkeyversion = 1;
$CFG->moodiyregistrationattemptid = '<current-external-request-id>';
$CFG->moodiyregistrationaction = 'server_provision_single';
```

The plugin signs update and delete requests with that credential. Signatures bind the HTTP method,
canonical API path, exact JSON body digest, timestamp, nonce, site UUID, and key version. Existing
installations without a dedicated key retain a bounded legacy bootstrap path so Core can mint the
first versioned key; newly provisioned managed sites should always be pre-seeded.

The attempt ID and action are an optional all-or-none pair for ordinary updates and are required by
managed provisioning/recovery. Attempt IDs must match
`^[A-Za-z0-9][A-Za-z0-9._:-]{0,99}$`; actions must be `server_provision_single`,
`server_provision_webdb_pair`, or `server_site_recover`. Both values are inserted into the exact
signed JSON update body so Core can bind an acknowledgement to one immutable provisioning attempt.

Do not print, commit, or place the signing key in task arguments. Keep the configuration include
outside the web/code root with site-owner-only permissions.

The internal-site repair CLI prints only `remote_sync_status`, `remote_acknowledged`,
`remote_http_status`, `acknowledgement_fingerprint`, `signing_key_version`, and
`site_uuid_fingerprint` on success. UUID and acknowledgement fingerprints are lowercase SHA-256;
the raw UUID, acknowledgement token, signing material, response body, site URL, and admin email are
never included. The raw provisioning attempt ID and action are also omitted. A failed result may
additionally contain only a stable `error_code` or
`remote_sync_error_code`.

## Moodle plugins directory submission

Suggested short description:

```text
Register a Moodle site with MoodiyCloud and keep its site metadata up to date.
```

Suggested full description:

```text
Moodiy registration is a Moodle administration tool that registers a Moodle site
with MoodiyCloud and keeps the registration record current. It stores the local
site UUID, exposes verification/update endpoints used by MoodiyCloud, and runs
scheduled tasks that update MoodiyCloud when the site URL or Moodle/plugin state
changes.
```

Recommended submission values:

- Plugin type: `tool`
- Component: `tool_moodiyregistration`
- Website URL: `https://moodiycloud.com`
- Source control URL: `https://github.com/moodiycloud/moodle-tool_moodiyregistration`
- Bug tracker URL: `https://github.com/moodiycloud/moodle-tool_moodiyregistration/issues`
- Documentation URL: `https://github.com/moodiycloud/moodle-tool_moodiyregistration#readme`
- License: GNU GPL v3 or later
- Supported Moodle versions: Moodle 4.5, 5.0, and 5.1

## External service and privacy

This plugin integrates with MoodiyCloud services at `https://moodiycloud.com`
and `https://api.moodiycloud.com`.

It does not register the site with Moodle's official site registration service.

The plugin stores a local registration record and site registration settings. It
also sends registration data to MoodiyCloud, including the configured contact
email address and aggregated site metadata required to maintain the site record.

See the plugin privacy provider and language strings for the current metadata
declaration.

The plugin requires access to MoodiyCloud to complete the external registration
confirmation flow. Moodle.org reviewers can request temporary reviewer access or
confirmation support via `support@moodiycloud.com`; any private test credentials
should be shared through the Moodle.org approval issue rather than committed to
this repository.

## Issue tracker and support

- Source code: `https://github.com/moodiycloud/moodle-tool_moodiyregistration`
- Issue tracker: `https://github.com/moodiycloud/moodle-tool_moodiyregistration/issues`
- Support: `support@moodiycloud.com`

## Release notes

Release notes for future tagged versions are tracked in `CHANGES.md`.

## License

2025-2026 MoodiyCloud <support@moodiycloud.com>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program. If not, see <https://www.gnu.org/licenses/>.
