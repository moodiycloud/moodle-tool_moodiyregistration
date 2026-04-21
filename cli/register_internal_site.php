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
    fwrite(STDOUT, json_encode([
        'status' => 'error',
        'message' => 'Usage: php admin/tool/moodiyregistration/cli/register_internal_site.php --site-uuid=<uuid>',
    ]) . PHP_EOL);
    exit(1);
}

$siteuuid = trim((string) ($options['site-uuid'] ?? ''));
if ($siteuuid === '') {
    fwrite(STDOUT, json_encode([
        'status' => 'error',
        'message' => 'Missing required --site-uuid option.',
    ]) . PHP_EOL);
    exit(1);
}

try {
    $result = \tool_moodiyregistration\registration::repair_internal_site_registration($siteuuid);
} catch (\Throwable $exception) {
    $result = [
        'status' => 'error',
        'message' => $exception->getMessage(),
    ];
}

$encoded = json_encode($result);
if ($encoded === false) {
    $encoded = json_encode([
        'status' => 'error',
        'message' => 'Failed to encode repair result as JSON.',
    ]);
}

fwrite(STDOUT, $encoded . PHP_EOL);
exit(($result['status'] ?? null) === 'ok' ? 0 : 1);
