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
 * This file defines tasks performed by the tool.
 *
 * @package    tool_moodiyregistration
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// List of tasks.
$tasks = [
    [   // Task to update site url with Moodiy.
        'classname' => 'tool_moodiyregistration\task\siteurl_update_task',
        'blocking' => 0,
        'minute' => 0,
        'hour' => 0,
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
    [
        'classname' => 'tool_moodiyregistration\task\upgrade_monitor_task',
        'blocking' => 0,
        'minute' => 0,
        'hour' => 0,
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
];
