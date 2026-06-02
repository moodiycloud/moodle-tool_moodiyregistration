# Moodle.org submission notes

Use this file as a copy source when creating the first Moodle plugins directory
record for `tool_moodiyregistration`.

## Plugin metadata

- Name: Moodiy registration
- Component: `tool_moodiyregistration`
- Plugin type: Administration tool (`tool`)
- Release name: `0.1.1`
- Version: `2026060200`
- Maturity: Alpha
- Supported Moodle versions: 4.5, 5.0, 5.1
- License: GNU GPL v3 or later

## Short description

Register a Moodle site with MoodiyCloud and keep its site metadata up to date.

## Full description

Moodiy registration is a Moodle administration tool that registers a Moodle site
with MoodiyCloud and keeps the registration record current. It stores the local
site UUID, exposes verification/update endpoints used by MoodiyCloud, and runs
scheduled tasks that update MoodiyCloud when the site URL or Moodle/plugin state
changes.

This is a MoodiyCloud integration and is separate from Moodle's official site
registration service.

The plugin integrates with MoodiyCloud services at `https://moodiycloud.com` and
`https://api.moodiycloud.com`. It sends the configured contact email address and
aggregated site metadata required to maintain the registration record.

## Links

- Website: `https://moodiycloud.com`
- Source control: `https://github.com/moodiycloud/moodle-tool_moodiyregistration`
- Bug tracker: `https://github.com/moodiycloud/moodle-tool_moodiyregistration/issues`
- Documentation: `https://github.com/moodiycloud/moodle-tool_moodiyregistration#readme`
- Support: `support@moodiycloud.com`

## Reviewer notes

Install the plugin at `admin/tool/moodiyregistration` and run the Moodle upgrade.
Open Site administration > Moodiy registration to start the registration flow.

The external confirmation flow requires MoodiyCloud reviewer access. Temporary
reviewer access or confirmation support can be provided through
`support@moodiycloud.com`; private credentials should be shared through the
Moodle.org approval issue, not in the public repository.

## Release notes

- Replaced Moodle core registration wording with MoodiyCloud-specific form copy.
- Initial alpha release of the MoodiyCloud site registration plugin.
- Metadata-based privacy provider for site registration and MoodiyCloud data exchange.
- Moodle.org release workflow scaffolding.
- Hardened external endpoint origin/header handling for MoodiyCloud integrations.
