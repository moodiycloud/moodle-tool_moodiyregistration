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
 * Class site_registration_form
 *
 * @package    tool_moodiyregistration
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodiyregistration;
defined('MOODLE_INTERNAL') || die();

use context_course;
use stdClass;

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * The Moodiy site registration form.
 *
 * @package    tool_moodiyregistration
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodiy_registration_form extends \moodleform {
    /**
     * Form definition
     */
    public function definition() {
        global $CFG;

        $strrequired = get_string('required');
        $mform = & $this->_form;
        $admin = get_admin();
        $site = get_site();

        $siteinfo = \tool_moodiyregistration\registration::get_site_info([
            'site_name' => format_string($site->fullname, true, ['context' => context_course::instance(SITEID)]),
            'description' => $site->summary,
            'admin_email' => $admin->email,
            'country_code' => $admin->country ?: $CFG->country,
            'language' => explode('_', current_language())[0],
            'emailalert' => 0,
            'policyagreed' => 0,
            'organisation_type' => '',
        ]);

        // Fields that need to be highlighted.
        $highlightfields = \core\hub\registration::get_new_registration_fields();

        $mform->addElement('header', 'moodle', get_string('registrationinfo', 'tool_moodiyregistration'));
        $mform->addElement('static', 'registrationnotice', '', get_string('registrationnotice', 'tool_moodiyregistration'));

        $mform->addElement(
            'text',
            'site_name',
            get_string('organisationname', 'tool_moodiyregistration'),
            ['class' => 'registration_textfield', 'maxlength' => 255]
        );
        $mform->setType('site_name', PARAM_TEXT);
        $mform->addHelpButton('site_name', 'organisationname', 'tool_moodiyregistration');

        $mform->addElement(
            'text',
            'admin_email',
            get_string('siteemail', 'tool_moodiyregistration'),
            ['class' => 'registration_textfield']
        );
        $mform->addRule('admin_email', $strrequired, 'required', null, 'client');
        $mform->setType('admin_email', PARAM_EMAIL);
        $mform->addHelpButton('admin_email', 'siteemail', 'tool_moodiyregistration');

        $organisationtypes = \tool_moodiyregistration\registration::get_site_organisation_type_options();
        \core_collator::asort($organisationtypes);
        // Prepend the empty/default value here. We are not using array_merge to preserve keys.
        $organisationtypes = [
            'donotshare' => get_string('siteorganisationtype:donotshare', 'tool_moodiyregistration'),
        ] + $organisationtypes;
        $mform->addElement(
            'select',
            'organisation_type',
            get_string('siteorganisationtype', 'tool_moodiyregistration'),
            $organisationtypes
        );
        $mform->setType('organisation_type', PARAM_ALPHANUM);
        $mform->addHelpButton('organisation_type', 'siteorganisationtype', 'tool_moodiyregistration');

        $mform->addElement(
            'select',
            'privacy',
            get_string('siteprivacy', 'tool_moodiyregistration'),
            \tool_moodiyregistration\registration::get_site_privacy_options()
        );
        $mform->setType('privacy', PARAM_ALPHA);
        $mform->addHelpButton('privacy', 'siteprivacy', 'tool_moodiyregistration');
        unset($options);

        $mform->addElement(
            'textarea',
            'description',
            get_string('sitedesc', 'tool_moodiyregistration'),
            ['rows' => 3, 'cols' => 41]
        );
        $mform->setType('description', PARAM_TEXT);
        $mform->addHelpButton('description', 'sitedesc', 'tool_moodiyregistration');

        $languages = get_string_manager()->get_list_of_languages();
        \core_collator::asort($languages);
        $mform->addElement('select', 'language', get_string('sitelang', 'tool_moodiyregistration'), $languages);
        $mform->setType('language', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('language', 'sitelang', 'tool_moodiyregistration');

        $countries = ['' => ''] + get_string_manager()->get_list_of_countries();
        $mform->addElement('select', 'country_code', get_string('sitecountry', 'tool_moodiyregistration'), $countries);
        $mform->setType('country_code', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('country_code', 'sitecountry', 'tool_moodiyregistration');
        $mform->addRule('country_code', $strrequired, 'required', null, 'client');

        $mform->addElement(
            'checkbox',
            'policyagreed',
            get_string('policyagreed', 'tool_moodiyregistration'),
            get_string(
                'policyagreeddesc',
                'tool_moodiyregistration',
                \tool_moodiyregistration\registration::MOODIYURL . '/privacy-policy'
            )
        );
        $mform->addRule('policyagreed', $strrequired, 'required', null, 'client');

        $mform->addElement('static', 'urlstring', get_string('siteurl', 'tool_moodiyregistration'), $siteinfo['site_url']);
        $mform->addHelpButton('urlstring', 'siteurl', 'tool_moodiyregistration');

        $mform->addElement('header', 'sitestats', get_string('sendfollowinginfo', 'tool_moodiyregistration'));
        $mform->setExpanded('sitestats', !empty($highlightfields));

        // Display statistic that are going to be retrieve by the sites directory.
        $mform->addElement(
            'static',
            'siteinfosummary',
            get_string('sendfollowinginfo', 'tool_moodiyregistration'),
            \tool_moodiyregistration\registration::get_stats_summary($siteinfo)
        );

        // Check if it's a first registration or update.
        if (registration::is_registered()) {
            $buttonlabel = get_string('updatesiteregistration', 'tool_moodiyregistration');
            $mform->addElement('hidden', 'update', true);
            $mform->setType('update', PARAM_BOOL);
        } else {
            $buttonlabel = get_string('registerbutton', 'tool_moodiyregistration');
        }

        $this->add_action_buttons(false, $buttonlabel);

        $mform->addElement('hidden', 'returnurl');
        $mform->setType('returnurl', PARAM_LOCALURL);

        // Set data. Always require to check policyagreed even if it was checked earlier.
        $this->set_data(['policyagreed' => 0] + $siteinfo);
    }

    /**
     * Validation of the form data
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *    2025-2026 MoodiyCloud <support@moodiycloud.com>
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }

    /**
     * Returns the form data
     *
     * @return stdClass
     */
    public function get_data() {
        if ($data = parent::get_data()) {
            if (debugging('', DEBUG_DEVELOPER)) {
                // Display debugging message for developers who added fields to the form
                // and forgot to add them to registration::FORM_FIELDS.
                $keys = array_diff(
                    array_keys((array)$data),
                    ['returnurl', 'mform_isexpanded_id_sitestats', 'submitbutton', 'update']
                );
                if ($extrafields = array_diff($keys, registration::FORM_FIELDS)) {
                    debugging('Found extra fields in the form results: ' . join(', ', $extrafields), DEBUG_DEVELOPER);
                }
                if ($missingfields = array_diff(registration::FORM_FIELDS, $keys)) {
                    debugging('Some fields are missing in the form results: ' . join(', ', $missingfields), DEBUG_DEVELOPER);
                }
            }
        }
        return $data;
    }
}
