<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Recreate the local internal site registration record for a provided UUID.
 *
 * @package     tool_moodiyregistration
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'site-uuid' => null,
        'help' => false,
    ],
    [
        'h' => 'help',
    ],
);

if (($options['help'] ?? false) || !empty($unrecognized)) {
    fwrite(STDOUT, \tool_moodiyregistration\cli_registration_result::encode([
        'remote_sync_status' => 'failed',
        'error_code' => 'invalid_cli_arguments',
    ]) . PHP_EOL);
    exit(1);
}

$siteuuid = trim((string) ($options['site-uuid'] ?? ''));
if ($siteuuid === '') {
    fwrite(STDOUT, \tool_moodiyregistration\cli_registration_result::encode([
        'remote_sync_status' => 'failed',
        'error_code' => 'missing_site_uuid',
    ]) . PHP_EOL);
    exit(1);
}

try {
    $result = \tool_moodiyregistration\registration::repair_internal_site_registration($siteuuid);
} catch (\Throwable) {
    $result = [
        'status' => 'error',
        'error_code' => 'unexpected_registration_error',
        'message' => 'Internal site registration could not be completed.',
    ];
}

$exitcode = \tool_moodiyregistration\cli_registration_result::exit_code($result);
$encoded = \tool_moodiyregistration\cli_registration_result::encode($result);

fwrite(STDOUT, $encoded . PHP_EOL);

// Local-row repair is not provisioning success. Exit zero only when Core returned
// a 2xx acknowledgement with a non-empty identifier for this exact update.
exit($exitcode);
