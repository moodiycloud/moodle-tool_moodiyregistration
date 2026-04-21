# Moodiy registration

`tool_moodiyregistration` is a Moodle admin tool plugin that registers a Moodle
site with MoodiyCloud and keeps the registration record up to date.

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

## External service and privacy

This plugin integrates with MoodiyCloud services at `https://moodiycloud.com`
and `https://api.moodiycloud.com`.

The plugin stores a local registration record and site registration settings. It
also sends registration data to MoodiyCloud, including the configured contact
email address and aggregated site metadata required to maintain the site record.

See the plugin privacy provider and language strings for the current metadata
declaration.

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
