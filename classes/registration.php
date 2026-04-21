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
 * Class registration
 *
 * @package    tool_moodiyregistration
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodiyregistration;
use moodle_exception;
use moodle_url;
use context_system;
use stdClass;
use html_writer;
use core_plugin_manager;
use tool_moodiyregistration\api;

/**
 * Methods to use when registering the site at the moodiy sites directory.
 *
 * @package    tool_moodiyregistration
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registration {
    /** @var array Fields used in a site registration form.
     * IMPORTANT: any new fields with non-empty defaults have to be added to CONFIRM_NEW_FIELDS */
    const FORM_FIELDS = ['policyagreed', 'language', 'country_code', 'privacy',
        'admin_email', 'site_name', 'description', 'organisation_type'];

    /** @var string Moodiy API URL */
    const MOODIYURL = 'https://moodiycloud.com';

    /** @var stdClass cached site registration information */
    protected static $registration = null;

    /** @var string Config key storing the last successful automatic update payload hash. */
    const LAST_SUCCESSFUL_UPDATE_HASH = 'lastsuccessfulupdatehash';

    /** @var int Maximum allowed age/skew in seconds for signed callback timestamps. */
    const CALLBACK_FRESHNESS_WINDOW = 900;

    /** @var string Shared stale-timestamp callback message mirrored by core and mixed-version fallbacks. */
    const STALE_TIMESTAMP_MESSAGE = 'Stale timestamp';

    /**
     * Checks if site is registered
     *
     * @return bool
     */
    public static function is_registered() {
        return self::get_registration() ? true : false;
    }

    /**
     * Get the options for organisation type form element to use in registration form.
     *
     * Indexes reference Moodle internal ids and should not be changed.
     *
     * @return array
     */
    public static function get_site_organisation_type_options(): array {
        return [
            'wholeuniversity' => get_string('siteorganisationtype:wholeuniversity', 'hub'),
            'universitydepartment' => get_string('siteorganisationtype:universitydepartment', 'hub'),
            'college' => get_string('siteorganisationtype:college', 'hub'),
            'collegedepartment' => get_string('siteorganisationtype:collegedepartment', 'hub'),
            'highschool' => get_string('siteorganisationtype:highschool', 'hub'),
            'highschooldepartment' => get_string('siteorganisationtype:highschooldepartment', 'hub'),
            'primaryschool' => get_string('siteorganisationtype:primaryschool', 'hub'),
            'independentteacher' => get_string('siteorganisationtype:independentteacher', 'hub'),
            'companyinternal' => get_string('siteorganisationtype:companyinternal', 'hub'),
            'companydepartment' => get_string('siteorganisationtype:companydepartment', 'hub'),
            'commercialcourseprovider' => get_string('siteorganisationtype:commercialcourseprovider', 'hub'),
            'other' => get_string('siteorganisationtype:other', 'hub'),
            'highschooldistrict' => get_string('siteorganisationtype:highschooldistrict', 'hub'),
            'government' => get_string('siteorganisationtype:government', 'hub'),
            'charityornotforprofit' => get_string('siteorganisationtype:charityornotforprofit', 'hub'),
            'charterschool' => get_string('siteorganisationtype:charterschool', 'hub'),
            'schooldistrict' => get_string('siteorganisationtype:schooldistrict', 'hub'),
            'hospital' => get_string('siteorganisationtype:hospital', 'hub'),
        ];
    }

    /**
     * Get site registration
     *
     * @return stdClass|null
     */
    protected static function get_registration() {
        global $DB;

        // For PHPUnit tests, always get fresh data.
        if (PHPUNIT_TEST) {
            self::$registration = null;
        }

        if (self::$registration === null) {
            self::$registration = $DB->get_record_sql('SELECT * FROM {tool_moodiyregistration}') ?: null;
        }

        if (self::$registration && !empty(self::$registration->site_uuid)) {
            return self::$registration;
        }

        return null;
    }

    /**
     * Summary of data that will be sent to the sites directory.
     *
     * @param array $siteinfo result of get_site_info()
     * @return string
     */
    public static function get_stats_summary($siteinfo) {
        $fieldsneedconfirm = \core\hub\registration::get_new_registration_fields();
        $summary = html_writer::tag('p', get_string('sendfollowinginfo_help', 'tool_moodiyregistration')) .
            html_writer::start_tag('ul');

        $mobileservicesenabled = $siteinfo['mobileservicesenabled'] ? get_string('yes') : get_string('no');
        $mobilenotificationsenabled = $siteinfo['mobilenotificationsenabled'] ? get_string('yes') : get_string('no');
        $moodlerelease = $siteinfo['moodlerelease'];
        if (preg_match('/^(\d+\.\d.*?)[\. ]/', $moodlerelease, $matches)) {
            $moodlerelease = $matches[1];
        }
        $pluginusagelinks = [
            'overview' => new moodle_url('/admin/plugins.php'),
            'activities' => new moodle_url('/admin/modules.php'),
            'blocks' => new moodle_url('/admin/blocks.php'),
        ];
        $senddata = [
            'moodlerelease' => get_string('sitereleasenum', 'hub', $moodlerelease),
            'courses' => get_string('coursesnumber', 'hub', $siteinfo['courses']),
            'users' => get_string('usersnumber', 'hub', $siteinfo['users']),
            'activeusers' => get_string('activeusersnumber', 'hub', $siteinfo['activeusers']),
            'enrolments' => get_string('roleassignmentsnumber', 'hub', $siteinfo['enrolments']),
            'posts' => get_string('postsnumber', 'hub', $siteinfo['posts']),
            'questions' => get_string('questionsnumber', 'hub', $siteinfo['questions']),
            'resources' => get_string('resourcesnumber', 'hub', $siteinfo['resources']),
            'badges' => get_string('badgesnumber', 'hub', $siteinfo['badges']),
            'issuedbadges' => get_string('issuedbadgesnumber', 'hub', $siteinfo['issuedbadges']),
            'participantnumberaverage' => get_string(
                'participantnumberaverage',
                'hub',
                format_float($siteinfo['participantnumberaverage'], 2)
            ),
            'activeparticipantnumberaverage' => get_string(
                'activeparticipantnumberaverage',
                'hub',
                format_float($siteinfo['activeparticipantnumberaverage'], 2)
            ),
            'modulenumberaverage' => get_string(
                'modulenumberaverage',
                'hub',
                format_float($siteinfo['modulenumberaverage'], 2)
            ),
            'mobileservicesenabled' => get_string('mobileservicesenabled', 'hub', $mobileservicesenabled),
            'mobilenotificationsenabled' => get_string('mobilenotificationsenabled', 'hub', $mobilenotificationsenabled),
            'registereduserdevices' => get_string('registereduserdevices', 'hub', $siteinfo['registereduserdevices']),
            'registeredactiveuserdevices' => get_string(
                'registeredactiveuserdevices',
                'hub',
                $siteinfo['registeredactiveuserdevices']
            ),
            'analyticsenabledmodels' => get_string('analyticsenabledmodels', 'hub', $siteinfo['analyticsenabledmodels']),
            'analyticspredictions' => get_string('analyticspredictions', 'hub', $siteinfo['analyticspredictions']),
            'analyticsactions' => get_string('analyticsactions', 'hub', $siteinfo['analyticsactions']),
            'analyticsactionsnotuseful' => get_string('analyticsactionsnotuseful', 'hub', $siteinfo['analyticsactionsnotuseful']),
            'dbtype' => get_string('dbtype', 'hub', $siteinfo['dbtype']),
            'coursesnodates' => get_string('coursesnodates', 'hub', $siteinfo['coursesnodates']),
            'sitetheme' => get_string('sitetheme', 'hub', $siteinfo['sitetheme']),
            'primaryauthtype' => get_string('primaryauthtype', 'hub', $siteinfo['primaryauthtype']),
            'pluginusage' => get_string('pluginusagedata', 'hub', $pluginusagelinks),
            'aiusage' => get_string('aiusagestats', 'tool_moodiyregistration', self::get_ai_usage_time_range(true)),
        ];

        foreach ($senddata as $key => $str) {
            $class = in_array($key, $fieldsneedconfirm) ? ' needsconfirmation mark' : '';
            $summary .= html_writer::tag('li', $str, ['class' => 'site' . $key . $class]);
        }
        $summary .= html_writer::end_tag('ul');
        return $summary;
    }

    /**
     * Save registration info locally so it can be retrieved when registration needs to be updated
     *
     * @param stdClass $formdata data from the registration form (see \tool_moodiyregistration\moodiy_registration_form)
     */
    public static function save_site_info($formdata) {
        foreach (self::FORM_FIELDS as $field) {
            set_config('site_' . $field, $formdata->$field, 'tool_moodiyregistration');
        }
    }

    /**
     * When was the registration last updated
     *
     * @return int|null timestamp or null if site is not registered
     */
    public static function get_last_updated() {
        if ($registration = self::get_registration()) {
            return $registration->timemodified;
        }
        return null;
    }
    /**
     * Prepare site information.
     *
     * @param array $defaults default values for inputs in the registration form (if site was never registered before)
     * @return array site info
     */
    public static function get_saved_form_data($defaults = []) {
        $siteinfo = [];
        foreach (self::FORM_FIELDS as $field) {
            $siteinfo[$field] = get_config('tool_moodiyregistration', 'site_' . $field);
            if ($siteinfo[$field] === false) {
                $siteinfo[$field] = array_key_exists($field, $defaults) ? $defaults[$field] : null;
            }
        }
        return $siteinfo;
    }

    /**
     * Calculates and prepares site information for the registration form.
     *
     * @param array $defaults default values for inputs in the registration form (if site was never registered before)
     * @return array site info
     */
    public static function get_site_info($defaults = []) {
        global $CFG, $DB;

        $siteinfo = self::get_saved_form_data($defaults);

        // Statistical data.
        $metadata  = self::get_site_metadata($defaults);
        return array_merge($siteinfo, $metadata);
    }

    /**
     * Prepare data to send to the sites directory
     *
     * This method prepares data to be sent to the sites directory as a part of registration.
     * It collects all the necessary information and formats it correctly.
     *
     * @param stdClass $formdata data from the registration form (see \tool_moodiyregistration\moodiy_registration_form)
     * @return array prepared data
     */
    public static function get_form_data($formdata) {
        global $CFG;
        $siteinfo = self::get_site_metadata();

        $data = [];
        $data['site_url'] = $CFG->wwwroot;
        $data['site_name'] = $formdata->site_name;
        $data['description'] = $formdata->description;
        $data['language'] = $formdata->language;
        $data['country_code'] = $formdata->country_code;
        $data['admin_email'] = $formdata->admin_email;
        $data['site_listing'] = $formdata->privacy;
        $data['organisation_type'] = $formdata->organisation_type;
        $data['timestamp'] = time();
        $data['site_metadata'] = json_encode($siteinfo);
        return $data;
    }

    /**
     * Get site metadata
     *
     * This method collects various statistics and information about the site that will be sent to the sites directory.
     *
     * @param array $defaults default values for inputs in the registration form (if site was never registered before)
     * @return array site metadata
     */
    public static function get_site_metadata($defaults = []) {
        global $CFG, $DB;
        require_once($CFG->libdir . '/badgeslib.php');
        require_once($CFG->dirroot . "/course/lib.php");

        $siteinfo = [];

        // Statistical data.
        $siteinfo['courses'] = $DB->count_records('course') - 1;
        $siteinfo['users'] = $DB->count_records('user', ['deleted' => 0]);
        $siteinfo['activeusers'] = $DB->count_records_select('user', 'deleted = ? AND lastlogin > ?', [0, time() - DAYSECS * 30]);
        $siteinfo['enrolments'] = $DB->count_records('role_assignments');
        $siteinfo['posts'] = $DB->count_records('forum_posts');
        $siteinfo['questions'] = $DB->count_records('question');
        $siteinfo['resources'] = $DB->count_records('resource');
        $siteinfo['badges'] = $DB->count_records_select('badge', 'status <> ' . BADGE_STATUS_ARCHIVED);
        $siteinfo['issuedbadges'] = $DB->count_records('badge_issued');
        $siteinfo['participantnumberaverage'] = average_number_of_participants();
        $siteinfo['activeparticipantnumberaverage'] = average_number_of_participants(true, time() - DAYSECS * 30);
        $siteinfo['modulenumberaverage'] = average_number_of_courses_modules();
        $siteinfo['dbtype'] = $CFG->dbtype;
        $siteinfo['coursesnodates'] = $DB->count_records_select('course', 'enddate = ?', [0]) - 1;
        $siteinfo['sitetheme'] = get_config('core', 'theme');
        $siteinfo['pluginusage'] = json_encode(\core\hub\registration::get_plugin_usage_data());

        // AI usage data.
        $aiusagedata = self::get_ai_usage_data();
        $siteinfo['aiusage'] = !empty($aiusagedata) ? json_encode($aiusagedata) : '';

        // Primary auth type.
        $primaryauthsql = 'SELECT auth, count(auth) as tc FROM {user} GROUP BY auth ORDER BY tc DESC';
        $siteinfo['primaryauthtype'] = $DB->get_field_sql($primaryauthsql, null, IGNORE_MULTIPLE);

        // Version and url.
        $siteinfo['moodlerelease'] = $CFG->release;
        $siteinfo['site_url'] = $CFG->wwwroot;

        // Mobile related information.
        $siteinfo['mobileservicesenabled'] = 0;
        $siteinfo['mobilenotificationsenabled'] = 0;
        $siteinfo['registereduserdevices'] = 0;
        $siteinfo['registeredactiveuserdevices'] = 0;
        if (!empty($CFG->enablewebservices) && !empty($CFG->enablemobilewebservice)) {
            $siteinfo['mobileservicesenabled'] = 1;
            $siteinfo['registereduserdevices'] = $DB->count_records('user_devices');
            $airnotifierextpath = $CFG->dirroot . '/message/output/airnotifier/externallib.php';
            if (file_exists($airnotifierextpath)) { // Maybe some one uninstalled the plugin.
                require_once($airnotifierextpath);
                $siteinfo['mobilenotificationsenabled'] = \message_airnotifier_external::is_system_configured();
                $siteinfo['registeredactiveuserdevices'] = $DB->count_records('message_airnotifier_devices', ['enable' => 1]);
            }
        }

        // Analytics related data follow.
        $siteinfo['analyticsenabledmodels'] = \core_analytics\stats::enabled_models();
        $siteinfo['analyticspredictions'] = \core_analytics\stats::predictions();
        $siteinfo['analyticsactions'] = \core_analytics\stats::actions();
        $siteinfo['analyticsactionsnotuseful'] = \core_analytics\stats::actions_not_useful();

        // IMPORTANT: any new fields in siteinfo have to be added to the constant CONFIRM_NEW_FIELDS.

        return $siteinfo;
    }

    /**
     * Get the API wrapper instance.
     *
     * @return api_wrapper
     */
    protected static function get_api_wrapper() {
        global $CFG;
        // Allow for test injection.
        if (PHPUNIT_TEST && isset($CFG->tool_moodiyregistration_test_api_wrapper)) {
            return $CFG->tool_moodiyregistration_test_api_wrapper;
        }
        return new api_wrapper();
    }

    /**
     * Registers a site with the Moodiy directory.
     *
     * This method will make sure that unconfirmed registration record is created and then redirect to
     * registration script on the sites directory.
     * The sites directory will check that the site is accessible, register it and redirect back
     * to /admin/registration/confirmregistration.php
     *
     * @param \stdClass $data Form data collected from the registration form.
     * @param string $returnurl Where to redirect the user after the Moodiy round-trip completes.
     * @return array|false|null In PHPUnit mode, returns the API response array; returns `false` if the
     *                          remote registration call fails; otherwise redirects (no return).
     * @throws \coding_exception When the site is already registered.
     */
    public static function register($data, $returnurl) {
        global $DB, $SESSION, $CFG;

        if (self::is_registered()) {
            // Caller of this method must make sure that site is not registered.
            throw new \coding_exception('Site already registered');
        }

        $data = self::get_form_data($data);

        $record = self::get_registration(false);
        if (empty($record)) {
            $verificationkey = md5($CFG->wwwroot . microtime(true));
            $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, 'tool_moodiyregistration', 'registration');

            if ($cache->get('verificationkey')) {
                // Delete the old verification key if it exists.
                $cache->delete('verificationkey');
            }
            $cache->set('verificationkey', $verificationkey);
            $data['verification_key'] = $verificationkey;

            try {
                $api = self::get_api_wrapper();
                $response = $api->moodiy_registration($data);

                if (empty($response) || !is_array($response)) {
                    throw new moodle_exception('errorconnect', 'tool_moodiyregistration', '', 'Invalid response from moodiy');
                }
                if (isset($response['data']) && is_array($response['data'])) {
                    $secret = $response['data']['site_uuid'] ?? '';
                }
                // Create a new record in 'tool_moodiyregistration'.
                $record = new stdClass();
                $record->site_uuid = $secret;
                $record->site_url = $data['site_url'];
                $record->timecreated = time();
                $record->timemodified = time();
                $record->id = $DB->insert_record('tool_moodiyregistration', $record);
                self::$registration = true;
                self::remember_automatic_update_payload_hash(self::get_siteinfo());
                // Delete the verification key from cache after successful registration.
                $cache->delete('verificationkey');
                // Trigger a site registration event.
                $event = \tool_moodiyregistration\event\moodiy_registration::create([
                    'context' => context_system::instance(),
                    'objectid' => $record->id,
                    'other' => [
                        'site_uuid' => $record->site_uuid,
                    ],
                ]);
                $event->add_record_snapshot('tool_moodiyregistration', $record);
                $event->trigger();
            } catch (\moodle_exception $e) {
                // If the table does not exist, we will create it later.
                if ($e->getMessage() === 'errorconnect') {
                    throw new moodle_exception('errorconnect', 'tool_moodiyregistration', '', $e->getMessage());
                } else {
                    \core\notification::add(
                        get_string('registrationerror', 'tool_moodiyregistration', $e->getMessage()),
                        \core\output\notification::NOTIFY_ERROR
                    );
                    return false;
                }
            }
            if (PHPUNIT_TEST) {
                // In tests we do not redirect, just return the response.
                return $response;
            }
            redirect(new moodle_url('/admin/tool/moodiyregistration/registrationconfirm.php', [
                'confirm' => self::$registration,
            ]));
        }
    }

    /**
     * Updates site registration when the "Update registration" button is clicked by an admin.
     *
     * @param \stdClass $fomdata Form data submitted by the admin (legacy parameter name kept for BC).
     * @return bool True if the update succeeded, false if there is no current registration to update.
     */
    public static function update_manual($fomdata) {
        global $DB, $CFG;

        if (!$registration = self::get_registration()) {
            return false;
        }

        $data = self::get_form_data($fomdata);
        $data['site_uuid'] = $registration->site_uuid;

        try {
            $api = self::get_api_wrapper();
            $api->update_registration($registration, $data);
            $DB->update_record('tool_moodiyregistration', ['id' => $registration->id, 'timemodified' => time()]);
            self::remember_automatic_update_payload_hash(self::get_siteinfo());
            // Trigger a site registration updated event.
            $event = \tool_moodiyregistration\event\moodiyregistration_updated::create([
                'context' => context_system::instance(),
                'objectid' => $registration->id,
                'other' => [
                    'site_uuid' => $registration->site_uuid,
                ],
            ]);
            $event->add_record_snapshot('tool_moodiyregistration', $registration);
            $event->trigger();
            \core\notification::add(
                get_string('siteregistrationupdated', 'tool_moodiyregistration'),
                \core\output\notification::NOTIFY_SUCCESS
            );
        } catch (moodle_exception $e) {
            if (stripos($e->getMessage(), \tool_moodiyregistration\api::ERROR_REGISTRATION_NONEXISTENT) !== false) {
                self::handle_nonexistent_registration($registration);
                if (PHPUNIT_TEST) {
                    // In tests we do not redirect, just return.
                    return false;
                }
                redirect(
                    new moodle_url('/admin/tool/moodiyregistration/index.php'),
                    get_string('reregistration', 'tool_moodiyregistration'),
                    null,
                    \core\output\notification::NOTIFY_WARNING
                );
                return;
            } else {
                \core\notification::add(
                    get_string('errorregistrationupdate', 'tool_moodiyregistration', $e->getMessage()),
                    \core\output\notification::NOTIFY_ERROR
                );
                return false;
            }
        }
        self::$registration = null;
        return true;
    }

    /**
     * Unregister this site from the Moodiy directory.
     *
     * Sends a DELETE to the Moodiy API and removes the local registration record.
     *
     * @return bool|null `true` when there is no registration to delete or the delete succeeded;
     *                   `false` when the remote unregister call failed; `null` (implicit, via early
     *                   `return;`) when the registration is already gone on the Moodiy side and the
     *                   user is being redirected to the unregistered-already page.
     */
    public static function unregister() {
        global $DB;

        if (!$registration = self::get_registration()) {
            return true;
        }

        // Unregister the site now.
        try {
            $api = self::get_api_wrapper();
            $api->unregister_site($registration);
            $DB->delete_records('tool_moodiyregistration', ['site_uuid' => $registration->site_uuid]);
            // Trigger a site unregistration event.
            $event = \tool_moodiyregistration\event\moodiy_unregistration::create([
                'context' => context_system::instance(),
                'objectid' => $registration->id,
                'other' => [
                    'site_uuid' => $registration->site_uuid,
                ],
            ]);
            $event->add_record_snapshot('tool_moodiyregistration', $registration);
            $event->trigger();
            \core\notification::add(
                get_string('unregister-success', 'tool_moodiyregistration'),
                \core\output\notification::NOTIFY_SUCCESS
            );
            self::$registration = null;
        } catch (moodle_exception $e) {
            if (stripos($e->getMessage(), \tool_moodiyregistration\api::ERROR_REGISTRATION_NONEXISTENT) !== false) {
                self::handle_nonexistent_registration($registration);
                if (PHPUNIT_TEST) {
                    // In tests we do not redirect, just return.
                    return false;
                }
                redirect(
                    new moodle_url('/admin/tool/moodiyregistration/index.php'),
                    get_string('unregistered-already', 'tool_moodiyregistration'),
                    null,
                    \core\output\notification::NOTIFY_WARNING
                );
                return;
            } else {
                \core\notification::add(
                    get_string('unregistrationerror', 'tool_moodiyregistration', $e->getMessage()),
                    \core\output\notification::NOTIFY_ERROR
                );
                return false;
            }
        }

        return true;
    }

    /**
     * Get the time range to use in collected and reporting AI usage data.
     *
     * @param bool $format Use true to format timestamp.
     * @return array
     */
    private static function get_ai_usage_time_range(bool $format = false): array {
        global $DB, $CFG;

        // We will try and use the last time this site was last registered for our 'from' time.
        // Otherwise, default to using one week's worth of data to roughly match the site rego scheduled task.
        $timenow = \core\di::get(\core\clock::class)->time();
        $defaultfrom = $timenow - WEEKSECS;
        $timeto = $timenow;
        $params = [
            'site_url' => $CFG->wwwroot,
        ];
        $lastregistered = $DB->get_field('tool_moodiyregistration', 'timemodified', $params);
        $timefrom = $lastregistered ? (int)$lastregistered : $defaultfrom;

        if ($format) {
            $timefrom = userdate($timefrom);
            $timeto = userdate($timeto);
        }

        return [
            'timefrom' => $timefrom,
            'timeto' => $timeto,
        ];
    }

    /**
     * Get AI usage data.
     *
     * @return array
     */
    public static function get_ai_usage_data(): array {
        global $DB;

        $params = self::get_ai_usage_time_range();

        $sql = "SELECT aar.*
                  FROM {ai_action_register} aar
                 WHERE aar.timecompleted >= :timefrom
                   AND aar.timecompleted <= :timeto";

        $actions = $DB->get_records_sql($sql, $params);

        // Build data for site info reporting.
        $data = [];

        foreach ($actions as $action) {
            $provider = $action->provider;
            $actionname = $action->actionname;

            // Initialise data structure.
            if (!isset($data[$provider][$actionname])) {
                $data[$provider][$actionname] = [
                    'success_count' => 0,
                    'fail_count' => 0,
                    'times' => [],
                    'errors' => [],
                ];
            }

            if ($action->success === '1') {
                $data[$provider][$actionname]['success_count'] += 1;
                // Collect AI processing times for averaging.
                $data[$provider][$actionname]['times'][] = (int)$action->timecompleted - (int)$action->timecreated;
            } else {
                $data[$provider][$actionname]['fail_count'] += 1;
                // Collect errors for determing the predominant one.
                $data[$provider][$actionname]['errors'][] = $action->errorcode;
            }
        }

        // Parse the errors and everage the times, then add them to the data.
        foreach ($data as $p => $provider) {
            foreach ($provider as $a => $actionname) {
                if (isset($data[$p][$a]['errors'])) {
                    // Create an array with the error codes counted.
                    $errors = array_count_values($data[$p][$a]['errors']);
                    if (!empty($errors)) {
                        // Sort values descending and convert to an array of error codes (most predominant will be at start).
                        arsort($errors);
                        $errors = array_keys($errors);
                        $data[$p][$a]['predominant_error'] = $errors[0];
                    }
                    unset($data[$p][$a]['errors']);
                }

                if (isset($data[$p][$a]['times'])) {
                    $count = count($data[$p][$a]['times']);
                    if ($count > 0) {
                        // Average the time to perform the action (seconds).
                        $totaltime = array_sum($data[$p][$a]['times']);
                        $data[$p][$a]['average_time'] = round($totaltime / $count);
                    }
                }
                unset($data[$p][$a]['times']);
            }
        }

        // Include the time range used to help interpret the data.
        if (!empty($data)) {
            $data['time_range'] = $params;
        }

        return $data;
    }

    /**
     * Calculates and prepares site information to send to the moodiy as a part of registration.
     * Metadata should be json encoded.
     *
     * @return array site info
     */
    public static function get_siteinfo() {
        global $CFG;
        $siteinfo = self::get_saved_form_data();
        $siteinfo['site_url'] = $CFG->wwwroot;
        $siteinfo['timestamp'] = time();

        // Statistical data.
        $metadata  = self::get_site_metadata();
        $siteinfo['site_metadata'] = json_encode($metadata);
        return $siteinfo;
    }

    /**
     * Updates the site URL in the registration record.
     *
     * This method checks if the site is registered and if the site URL has changed.
     * If so, it updates the registration record with the new site URL and triggers an event.
     *
     * @return bool|moodle_url Returns true on success, false if not registered, or a moodle_url to redirect to registration page.
     * @throws moodle_exception
     */
    public static function update_registration_siteurl() {
        global $CFG, $DB;

        if (self::is_registered()) {
            $registration = self::get_registration();
            if (strcmp($registration->site_url, $CFG->wwwroot) !== 0) {
                $siteinfo = self::get_siteinfo();
                $siteinfo['site_uuid'] = $registration->site_uuid;
                try {
                    api::update_registration($registration, $siteinfo);

                    // Update the site URL in the registration record.
                    $registration->site_url = $CFG->wwwroot;
                    $registration->timemodified = time();
                    $DB->update_record('tool_moodiyregistration', $registration);
                    self::remember_automatic_update_payload_hash($siteinfo);
                    self::$registration = null;
                    mtrace(get_string('siteregistrationurlupdated', 'tool_moodiyregistration'));

                    // Trigger a site registration updated event.
                    $event = \tool_moodiyregistration\event\moodiyregistration_updated::create([
                        'context' => context_system::instance(),
                        'objectid' => $registration->id,
                        'other' => [
                            'site_uuid' => $registration->site_uuid,
                            'site_url' => $registration->site_url,
                        ],
                    ]);
                    $event->add_record_snapshot('tool_moodiyregistration', $registration);
                    $event->trigger();
                } catch (moodle_exception $e) {
                    mtrace($e->getMessage());
                    return false;
                }
            }
            return true;
        } else {
            return new moodle_url($CFG->wwwroot . '/admin/tool/moodiyregistration/registration.php');
        }
    }

    /**
     * Updates site registration via scheduled task.
     *
     * @param bool $force When true, send the update even if the payload hash matches the last known one.
     * @return bool|null `true` when the update was attempted and succeeded (or was skipped as a no-op);
     *                   `false` when called in PHPUnit and the registration was found to be missing
     *                   on the Moodiy side; `null` (implicit, via early `return;`) when there is no
     *                   current registration to update or when an error occurred during the API call.
     * @throws moodle_exception
     */
    public static function update_registration(bool $force = false) {
        global $DB;

        if (!$registration = self::get_registration()) {
            return;
        }

        $siteinfo = self::get_siteinfo();
        $siteinfo['site_uuid'] = $registration->site_uuid;

        if (self::should_skip_automatic_update($siteinfo, $force)) {
            if (!PHPUNIT_TEST) {
                mtrace('Skipping registration update because the automatic payload is unchanged.');
            }
            return true;
        }

        try {
            $api = self::get_api_wrapper();
            $api->update_registration($registration, $siteinfo);
            $DB->update_record('tool_moodiyregistration', ['id' => $registration->id, 'timemodified' => time()]);
            self::remember_automatic_update_payload_hash($siteinfo);

            self::$registration = null;
            // Trigger a site registration updated event.
            $event = \tool_moodiyregistration\event\moodiyregistration_updated::create([
                'context' => context_system::instance(),
                'objectid' => $registration->id,
                'other' => [
                    'site_uuid' => $registration->site_uuid,
                    'site_url' => $registration->site_url,
                ],
            ]);
            $event->add_record_snapshot('tool_moodiyregistration', $registration);
            $event->trigger();
        } catch (moodle_exception $e) {
            if (stripos($e->getMessage(), \tool_moodiyregistration\api::ERROR_REGISTRATION_NONEXISTENT) !== false) {
                self::handle_nonexistent_registration($registration);
                if (PHPUNIT_TEST) {
                    // In tests we do not redirect, just return the response.
                    return false;
                }
                debugging('Site unregistered from Moodiy side, local registration record deleted.');
                return;
            }
            debugging('Error updating registration: ' . $e->getMessage());
            return;
        }
    }

    /**
     * Registers an internal site with Moodiy.
     *
     * @param string $uuid The UUID of the site.
     * @return bool True on success, false on failure.
     */
    public static function register_internal_site($uuid) {
        global $DB, $CFG;

        $sitedata = self::build_internal_site_info_defaults();
        self::save_site_info($sitedata);

        // Create a new record in 'tool_moodiyregistration'.
        $record = new \stdClass();
        $record->site_uuid = $uuid;
        $record->site_url = $CFG->wwwroot;
        $record->timecreated = time();
        $record->timemodified = time();

        try {
            if ($DB->insert_record('tool_moodiyregistration', $record)) {
                $siteinfo = self::get_siteinfo();
                $siteinfo['site_uuid'] = $record->site_uuid;
                try {
                    // Update registration on Moodiy side.
                    $api = self::get_api_wrapper();
                    $api->update_registration($record, $siteinfo);
                    self::remember_automatic_update_payload_hash($siteinfo);
                } catch (moodle_exception $e) {
                    debugging('Error updating internal site: ' . $e->getMessage());
                    // If update fails, keep the inserted record, moodiy will take care.
                    return false;
                }
                return true;
            }
            return false;
        } catch (\dml_write_exception $e) {
            debugging('Error inserting internal site registration record: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Repair the local internal site registration record for a provided UUID.
     *
     * This recreates the single local tool_moodiyregistration row so renewal
     * and update requests can succeed again. Remote Moodiy sync may still be
     * pending if the immediate API update fails, but the recreated local row
     * lets the regular renewal/update flow retry safely.
     *
     * @param string $uuid The UUID that should be stored locally.
     * @return array<string, mixed> Structured repair result
     */
    public static function repair_internal_site_registration(string $uuid): array {
        global $CFG, $DB;

        $uuid = trim($uuid);
        if ($uuid === '') {
            return [
                'status' => 'error',
                'message' => 'Site UUID is required.',
            ];
        }

        if (!self::is_internal_site()) {
            return [
                'status' => 'error',
                'message' => 'Internal site registration repair is only available for internal hosted sites.',
            ];
        }

        $existingcount = (int) $DB->count_records('tool_moodiyregistration');
        $existingrecord = $existingcount === 1 ? $DB->get_record('tool_moodiyregistration', []) : false;

        if ($existingrecord && $existingrecord->site_uuid === $uuid && ($existingrecord->site_url ?? '') === $CFG->wwwroot) {
            self::$registration = null;

            return [
                'status' => 'ok',
                'message' => 'Internal site registration already matches the requested UUID.',
                'site_uuid' => $uuid,
                'site_url' => $existingrecord->site_url,
                'deleted_records' => 0,
                'recreated' => false,
                'remote_sync_status' => 'unchanged',
            ];
        }

        try {
            $transaction = $DB->start_delegated_transaction();
            [$record, $deletedrecords, $recreated] = self::upsert_internal_site_registration_record($uuid);
            $transaction->allow_commit();
        } catch (\dml_exception $e) {
            debugging('Error repairing internal site registration record: ' . $e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Failed to recreate the local internal site registration record.',
                'site_uuid' => $uuid,
                'deleted_records' => 0,
                'recreated' => false,
                'remote_sync_status' => 'failed',
            ];
        }

        self::$registration = null;

        self::ensure_internal_site_info_defaults();
        $siteinfo = self::get_siteinfo();
        $siteinfo['site_uuid'] = $uuid;
        $remotesynced = false;
        try {
            $api = self::get_api_wrapper();
            $api->update_registration($record, $siteinfo);
            self::remember_automatic_update_payload_hash($siteinfo);
            $remotesynced = true;
        } catch (moodle_exception $e) {
            debugging(
                'Local internal site registration was repaired, but the remote Moodiy sync is pending: ' .
                $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
        self::$registration = null;

        return [
            'status' => 'ok',
            'message' => $remotesynced
                ? 'Internal site registration repaired and synced with Moodiy.'
                : 'Internal site registration repaired locally; remote Moodiy sync is pending.',
            'site_uuid' => $uuid,
            'site_url' => $record->site_url ?? $CFG->wwwroot,
            'deleted_records' => $deletedrecords,
            'recreated' => $recreated,
            'remote_sync_status' => $remotesynced ? 'ok' : 'pending',
        ];
    }

    /**
     * Ensure the local registration table contains a single record for the provided UUID.
     *
     * @param string $uuid Desired site UUID
     * @return array{0:\stdClass,1:int,2:bool} Updated record, deleted record count, recreated flag
     */
    private static function upsert_internal_site_registration_record(string $uuid): array {
        global $CFG, $DB;

        $records = array_values($DB->get_records('tool_moodiyregistration', null, 'id ASC'));
        $existingcount = count($records);
        $deletedrecords = $existingcount > 0 ? max($existingcount - 1, 0) : 0;
        $recreated = true;

        $matchingrecord = null;
        foreach ($records as $record) {
            if ($record->site_uuid === $uuid) {
                $matchingrecord = $record;
                break;
            }
        }

        if ($matchingrecord) {
            $record = $matchingrecord;
        } else if ($records !== []) {
            $record = $records[0];
        } else {
            $record = (object) [
                'site_uuid' => $uuid,
                'site_url' => $CFG->wwwroot,
                'timecreated' => time(),
                'timemodified' => time(),
            ];
            $record->id = $DB->insert_record('tool_moodiyregistration', $record);

            return [$record, 0, true];
        }

        $record->site_uuid = $uuid;
        $record->site_url = $CFG->wwwroot;
        $record->timemodified = time();
        $DB->update_record('tool_moodiyregistration', $record);

        foreach ($records as $existingrecord) {
            if ((int) $existingrecord->id === (int) $record->id) {
                continue;
            }

            $DB->delete_records('tool_moodiyregistration', ['id' => $existingrecord->id]);
        }

        if ($matchingrecord && $deletedrecords === 0 && ($record->site_url ?? '') === $CFG->wwwroot) {
            $recreated = false;
        }

        return [$record, $deletedrecords, $recreated];
    }

    /**
     * Determine whether the current Moodle instance is an internal hosted site.
     *
     * This helper is the shared ADR-007 boundary for the existing production plugins that need
     * to distinguish internal hosted sites without re-reading `auth_maintenance` directly.
     *
     * @return bool True when internal hosted-site config is present
     */
    public static function is_internal_site(): bool {
        global $CFG;

        $forcedpluginsettings = is_array($CFG->forced_plugin_settings ?? null) ? $CFG->forced_plugin_settings : [];

        return array_key_exists('auth_maintenance', $forcedpluginsettings);
    }

    /**
     * Build the exact error payload returned when a signed callback timestamp is stale.
     *
     * @return array
     */
    public static function stale_timestamp_error_response(): array {
        return [
            'status' => 'error',
            'message' => self::STALE_TIMESTAMP_MESSAGE,
        ];
    }

    /**
     * Determine whether a signed callback timestamp is within the allowed freshness window.
     *
     * Legacy callers may omit the timestamp entirely. When a timestamp is present it must be a
     * valid integer epoch value within the configured skew window.
     *
     * @param mixed $timestamp
     * @return bool
     */
    public static function is_fresh_callback_timestamp($timestamp): bool {
        if (!is_scalar($timestamp)) {
            return false;
        }

        $timestampvalue = trim((string)$timestamp);
        if ($timestampvalue === '' || !preg_match('/^\d+$/', $timestampvalue)) {
            return false;
        }

        $epochtime = (int)$timestampvalue;
        if ($epochtime <= 0) {
            return false;
        }

        $timedifference = time() - $epochtime;

        return $timedifference >= 0 && $timedifference <= self::CALLBACK_FRESHNESS_WINDOW;
    }

    /**
     * Build the default registration metadata for an internal hosted site.
     *
     * @return \stdClass Default site info payload
     */
    private static function build_internal_site_info_defaults(): \stdClass {
        global $CFG;

        $admin = get_admin();
        $site = get_site();
        $sitedata = new \stdClass();
        $sitedata->site_name = format_string($site->fullname, true, ['context' => \context_course::instance(SITEID)]);
        $sitedata->description = $site->summary;
        $sitedata->admin_email = $admin->email;
        $sitedata->country_code = $admin->country ?: ($CFG->country ?: '');
        $sitedata->language = explode('_', current_language())[0];
        $sitedata->privacy = 'notdisplayed';
        $sitedata->policyagreed = 0;
        $sitedata->organisation_type = 'donotshare';

        return $sitedata;
    }

    /**
     * Ensure internal hosted sites have saved registration metadata without
     * overwriting any values already chosen by an operator.
     */
    private static function ensure_internal_site_info_defaults(): void {
        $savedinfo = self::get_saved_form_data();
        $defaults = self::build_internal_site_info_defaults();
        $shouldsave = false;
        $mergedinfo = new \stdClass();

        foreach (self::FORM_FIELDS as $field) {
            $value = $savedinfo[$field] ?? null;
            if ($value === null) {
                $value = $defaults->$field;
                $shouldsave = true;
            }

            $mergedinfo->$field = $value;
        }

        if ($shouldsave) {
            self::save_site_info($mergedinfo);
        }
    }

    /**
     * Get the site UUID.
     *
     * This method retrieves the site UUID from the registration record.
     *
     * @return string|null The site UUID or null if not registered.
     */
    public static function get_siteuuid() {
        global $DB;

        $registration = self::get_registration();
        if ($registration) {
            return $registration->site_uuid;
        }
        return null;
    }

    /**
     * Check for Moodiycloud products, if found prevent unregistration.
     *
     * @return bool
     */
    public static function can_unregister(): bool {
        return !get_config('tool_moodiymobile', 'enabled');
    }

    /**
     * Display a warning box with a single continue button.
     *
     * @param string $message The warning message to display.
     * @param string|moodle_url|\single_button $continue The URL or button for the continue action.
     * @param array $displayoptions Optional display options:
     *    2025-2026 MoodiyCloud <support@moodiycloud.com>
     *    2025-2026 MoodiyCloud <support@moodiycloud.com>
     *    2025-2026 MoodiyCloud <support@moodiycloud.com>
     *
     * @return string The HTML output of the warning box.
     * @throws coding_exception If the $continue parameter is invalid.
     */
    public static function warningbox($message, $continue, array $displayoptions = []) {
        global $PAGE;

        $renderer = $PAGE->get_renderer('tool_moodiyregistration');
        $displayoptions['confirmtitle'] = $displayoptions['confirmtitle'] ?? get_string('warning', 'core');
        $displayoptions['continuestr'] = $displayoptions['continuestr'] ?? get_string('continue');

        if ($continue instanceof \single_button) {
            if ($continue->type === \single_button::BUTTON_SECONDARY) {
                $continue->type = \single_button::BUTTON_PRIMARY;
            }
            $continuehtml = $renderer->render($continue);
        } else if (is_string($continue) || $continue instanceof \moodle_url) {
            $url = is_string($continue) ? new \moodle_url($continue) : $continue;
            $continuehtml = $renderer->render(new \single_button(
                $url,
                $displayoptions['continuestr'],
                'post',
                $displayoptions['type'] ?? \single_button::BUTTON_PRIMARY
            ));
        } else {
            throw new \coding_exception('The continue param must be either
             a URL (string/moodle_url) or a single_button instance.');
        }

        return $renderer->warningbox($message, $continuehtml, $displayoptions);
    }

    /**
     * Handle the case when the registration does not exist on Moodiy side anymore.
     *
     * Disables tool_moodiymobile and removes the local registration record so subsequent
     * automatic updates do not keep retrying against a server-side 404.
     *
     * @param \stdClass $registration The local registration record that Moodiy no longer recognises.
     * @return void
     */
    private static function handle_nonexistent_registration(\stdClass $registration): void {
        global $DB;
        // The site is not registered on Moodiy side anymore, delete local record.
        // Disable moodiymobile services.
        set_config('enabled', 0, 'tool_moodiymobile');
        unset_config(self::LAST_SUCCESSFUL_UPDATE_HASH, 'tool_moodiyregistration');

        $DB->delete_records('tool_moodiyregistration', ['site_uuid' => $registration->site_uuid]);
        self::$registration = null;
    }

    /**
     * Queue a deduplicated ad-hoc update request for the provided site UUID.
     *
     * @param string $siteuuid Site UUID requesting a refresh
     * @return bool True when a new task was queued, false when an equivalent task already exists
     */
    public static function queue_update_request_task(string $siteuuid): bool {
        $siteuuid = trim($siteuuid);
        if ($siteuuid === '' || self::has_pending_update_request_task($siteuuid)) {
            return false;
        }

        $task = new \tool_moodiyregistration\task\process_update_request();
        $task->set_custom_data(['site_uuid' => $siteuuid]);
        // Keep the explicit UUID pre-check for a deterministic boolean return value.
        // queue_adhoc_task(..., true) remains the final race-safe deduplication guard.
        \core\task\manager::queue_adhoc_task($task, true);

        return true;
    }

    /**
     * Determine whether an automatic registration update would be a no-op.
     *
     * Compares a hash of the prepared payload against the last successful one to dedupe
     * scheduled-task updates that wouldn't change anything.
     *
     * @param array $siteinfo The current automatic update payload.
     * @param bool $force When true, never skip — always send the update.
     * @return bool True if the update should be skipped, false otherwise.
     */
    private static function should_skip_automatic_update(array $siteinfo, bool $force = false): bool {
        if ($force) {
            return false;
        }

        $savedhash = get_config('tool_moodiyregistration', self::LAST_SUCCESSFUL_UPDATE_HASH);
        if (!is_string($savedhash) || $savedhash === '') {
            return false;
        }

        return hash_equals($savedhash, self::build_automatic_update_payload_hash($siteinfo));
    }

    /**
     * Persist the last successful automatic update payload fingerprint.
     *
     * @param array $siteinfo The current automatic update payload
     */
    private static function remember_automatic_update_payload_hash(array $siteinfo): void {
        set_config(
            self::LAST_SUCCESSFUL_UPDATE_HASH,
            self::build_automatic_update_payload_hash($siteinfo),
            'tool_moodiyregistration'
        );
    }

    /**
     * Build a stable fingerprint for automatic registration updates.
     *
     * @param array $siteinfo The current automatic update payload
     * @return string
     */
    private static function build_automatic_update_payload_hash(array $siteinfo): string {
        $fingerprintpayload = $siteinfo;
        unset($fingerprintpayload['timestamp']);
        unset($fingerprintpayload['site_uuid']);
        ksort($fingerprintpayload);

        return hash('sha256', json_encode($fingerprintpayload));
    }

    /**
     * Determine whether the same update request task is already queued.
     *
     * @param string $siteuuid
     * @return bool
     */
    private static function has_pending_update_request_task(string $siteuuid): bool {
        $tasks = \core\task\manager::get_adhoc_tasks(\tool_moodiyregistration\task\process_update_request::class);

        foreach ($tasks as $task) {
            $customdata = $task->get_custom_data();
            if (($customdata->site_uuid ?? null) === $siteuuid) {
                return true;
            }
        }

        return false;
    }
}
