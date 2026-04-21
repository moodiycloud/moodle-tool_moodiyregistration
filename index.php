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
 * Version details.
 *
 * @package    tool_moodiyregistration
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * This page displays the moodiy registration form.
 * It also handles update operation by web service.
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('moodiyregistration');

$unregistration = optional_param('unregistration', false, PARAM_BOOL);
$confirm = optional_param('confirm', false, PARAM_BOOL);
$vefiy = optional_param('action', '', PARAM_TEXT);
$rtnurl = optional_param('returnurl', '', PARAM_LOCALURL);

if ($unregistration && \tool_moodiyregistration\registration::is_registered()) {
    if ($confirm) {
        require_sesskey();
        \tool_moodiyregistration\registration::unregister();

        if (!\tool_moodiyregistration\registration::is_registered()) {
            redirect(new moodle_url('/admin/tool/moodiyregistration/index.php'));
        }
    }

    echo $OUTPUT->header();
    if (\tool_moodiyregistration\registration::can_unregister()) {
        echo $OUTPUT->confirm(
            get_string('registerwithmoodiyremove', 'tool_moodiyregistration'),
            new moodle_url('/admin/tool/moodiyregistration/index.php', ['unregistration' => 1, 'confirm' => 1]),
            new moodle_url('/admin/tool/moodiyregistration/index.php'),
        );
    } else {
        echo \tool_moodiyregistration\registration::warningbox(
            get_string('unregistration-warning', 'tool_moodiyregistration'),
            new moodle_url('/admin/tool/moodiyregistration/index.php'),
        );
    }

    echo $OUTPUT->footer();
    exit;
}
$isinitialregistration = \tool_moodiyregistration\registration::is_registered();
$returnurl = $rtnurl ?: '/admin/tool/moodiyregistration/index.php';

$siteregistrationform = new \tool_moodiyregistration\moodiy_registration_form();
$siteregistrationform->set_data(['returnurl' => $returnurl]);
if ($fromdata = $siteregistrationform->get_data()) {
    // Save the settings.
    \tool_moodiyregistration\registration::save_site_info($fromdata);

    if (\tool_moodiyregistration\registration::is_registered()) {
        if (\tool_moodiyregistration\registration::update_manual($fromdata)) {
            redirect(new moodle_url($returnurl));
        }
        redirect(new moodle_url('/admin/tool/moodiyregistration/index.php', ['returnurl' => $returnurl]));
    } else {
        \tool_moodiyregistration\registration::register($fromdata, $returnurl);
        // This method will redirect away.
    }
}

// OUTPUT SECTION.
echo $OUTPUT->header();

// Current status of registration.
$notificationtype = \core\output\notification::NOTIFY_ERROR;
if (\tool_moodiyregistration\registration::is_registered()) {
    $lastupdated = \tool_moodiyregistration\registration::get_last_updated();
    if ($lastupdated == 0) {
        $registrationmessage = get_string('pleaserefreshregistrationunknown', 'tool_moodiyregistration');
    } else {
        $lastupdated = userdate($lastupdated, get_string('strftimedate', 'langconfig'));
        $registrationmessage = get_string('pleaserefreshregistration', 'tool_moodiyregistration', $lastupdated);
        $notificationtype = \core\output\notification::NOTIFY_INFO;
    }
    echo $OUTPUT->notification($registrationmessage, $notificationtype);
} else if (!$isinitialregistration) {
    $registrationmessage = get_string('registrationwarning', 'tool_moodiyregistration');
    echo $OUTPUT->notification($registrationmessage, $notificationtype);
}

// Heading.
if (\tool_moodiyregistration\registration::is_registered()) {
    // Display site-uuid.
    $siteuuid = \tool_moodiyregistration\registration::get_siteuuid();
    $summary = html_writer::tag('summary', get_string('viewsiteuuid', 'tool_moodiyregistration'));
    $uuidcontent = html_writer::tag('div', get_string('siteuuid', 'tool_moodiyregistration', $siteuuid));
    echo html_writer::tag('details', $summary . $uuidcontent, ['class' => 'alert alert-info']);

    echo $OUTPUT->heading(get_string('registerwithmoodiyupdate', 'tool_moodiyregistration'));
} else if ($isinitialregistration) {
    echo $OUTPUT->heading(get_string('registerwithmoodiycomplete', 'tool_moodiyregistration'));
} else {
    echo $OUTPUT->heading(get_string('registerwithmoodiy', 'tool_moodiyregistration'));
}

$siteregistrationform->display();

if (\tool_moodiyregistration\registration::is_registered()) {
    // Unregister link.
    $unregisterhuburl = new moodle_url("/admin/tool/moodiyregistration/index.php", ['unregistration' => 1]);
    echo html_writer::div(
        html_writer::link($unregisterhuburl, get_string('unregister', 'tool_moodiyregistration')),
        'unregister mt-2'
    );
} else if ($isinitialregistration) {
    echo html_writer::div(
        html_writer::link(new moodle_url($returnurl), get_string('skipregistration', 'tool_moodiyregistration')),
        'skipregistration mt-2'
    );
}


echo $OUTPUT->footer();
