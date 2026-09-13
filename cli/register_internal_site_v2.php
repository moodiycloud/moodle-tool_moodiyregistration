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
 * Receive an initial registration credential exclusively through private stdin.
 *
 * @package    tool_moodiyregistration
 * @copyright  2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Bootstrap is inside the protected output/exception boundary deliberately.
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
define('CLI_SCRIPT', true);
// Buffer bootstrap/debugging output too: only the allowlisted proof may leave stdout.
ob_start();
$result = ['remote_sync_status' => 'failed', 'error_code' => 'initial_credential_delivery_failed'];
try {
    if ($argc !== 1) {
        throw new \RuntimeException('Invalid CLI arguments.');
    }
    $raw = stream_get_contents(STDIN, 4097);
    if ($raw === false || strlen($raw) > 4096) {
        throw new \RuntimeException('Invalid input size.');
    }
    $input = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    unset($raw);
    if (!is_array($input)) {
        throw new \RuntimeException('Invalid input.');
    }
    require_once(__DIR__ . '/../../../../config.php');
    \tool_moodiyregistration\initial_signing_credential::install($input);
    $uuid = $input['site_uuid'];
    unset($input);
    $result = \tool_moodiyregistration\registration::repair_internal_site_registration($uuid);
} catch (\Throwable) {
    $result = ['remote_sync_status' => 'failed', 'error_code' => 'initial_credential_delivery_failed'];
    // No raw exception or input is written to stdout, stderr or Moodle debugging.
}
while (ob_get_level() > 0) {
    ob_end_clean();
}
// Bootstrap itself may have failed, so load the standalone result allowlist directly.
require_once(__DIR__ . '/../classes/cli_registration_result.php');
fwrite(STDOUT, \tool_moodiyregistration\cli_registration_result::encode($result) . PHP_EOL);
exit(\tool_moodiyregistration\cli_registration_result::exit_code($result));
