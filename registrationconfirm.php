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
 * The administrator is redirect to this page to confirm that the
 * site has been registered on Moodiy. It is an administration page. The administrator
 * should be using the same browser during all the registration process.
 *
 * @package    tool_moodiyregistration
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$error = optional_param('error', '', PARAM_ALPHANUM);

admin_externalpage_setup('moodiyregistration');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('registrationconfirmed', 'tool_moodiyregistration'), 3, 'main');

// Display notification message.
echo $OUTPUT->notification(get_string('registrationconfirmedon', 'tool_moodiyregistration'), 'notifysuccess');

// Display continue button.
$returnurl = !empty($SESSION->registrationredirect) ? clean_param($SESSION->registrationredirect, PARAM_LOCALURL) : null;
unset($SESSION->registrationredirect);
$continueurl = new moodle_url($returnurl ?: '/admin/tool/moodiyregistration/index.php');
$continuebutton = $OUTPUT->render(new single_button($continueurl, get_string('continue')));
$continuebutton = html_writer::tag('div', $continuebutton, ['class' => 'mdl-align']);
echo $continuebutton;

echo $OUTPUT->footer();
