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
 * Cold CLI HTTP-client bootstrap fixture. No database connection or HTTP request.
 *
 * @package    tool_moodiyregistration
 * @category   test
 * @copyright  2026 MoodiyCloud
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
define('ABORT_AFTER_CONFIG', true);
define('PHPUNIT_TEST', false);
require(__DIR__ . '/../../../../../config.php');
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require_once($CFG->libdir . '/moodlelib.php');
$CFG->ostype = PHP_OS_FAMILY === 'Windows' ? 'WINDOWS' : 'UNIX';
require_once(__DIR__ . '/../../classes/api.php');
$preloaded = class_exists('curl', false);
if ($preloaded) {
    fwrite(STDERR, "The cold process unexpectedly preloaded Moodle's HTTP client.\n");
    exit(2);
}
$factory = new ReflectionMethod(\tool_moodiyregistration\api::class, 'get_http_client');
$client = $factory->invoke(null);
$repeat = $factory->invoke(null);
echo json_encode(['preloaded' => $preloaded, 'client' => get_class($client), 'repeat' => get_class($repeat)]) . PHP_EOL;
