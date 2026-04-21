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
 * Plugin strings are defined here.
 *
 * @package     tool_moodiyregistration
 * @category    string
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aiusagestats'] = 'AI usage stats ({$a->timefrom} - {$a->timeto})';
$string['errorpayloadencoding'] = 'Failed to encode the registration payload before sending to Moodiy. Error: {$a}';
$string['errorregistrationupdate'] = 'An error occurred during registration update . Error: {$a}';
$string['errorunregister'] = 'There was an error while unregistering your site. Error: {$a}';
$string['eventmoodiyregistration'] = 'Site registered with MoodiyCloud';
$string['eventmoodiyregistration_desc'] = 'User {$a->userid} has registered the site with id {$a->objectid} with MoodiyCloud.';
$string['eventmoodiyregistrationupdated'] = 'Site registration updated with MoodiyCloud';
$string['eventmoodiyregistrationupdated_desc'] = 'User {$a->userid} has updated the site registration with id {$a->objectid} with MoodiyCloud.';
$string['eventmoodiyunregistration'] = 'Site unregistered from MoodiyCloud';
$string['eventmoodiyunregistration_desc'] = 'User {$a->userid} has unregistered the site with id {$a->objectid}.';
$string['organisationname'] = 'Organisation name';
$string['organisationname_help'] = 'The organisation name is shown in the site listing if you choose to have your site listed publicly.';
$string['pleaserefreshregistration'] = 'Your site is registered. Registration last updated {$a}.<br />Your registration will be kept up to date. You can also manually update your registration at any time.';
$string['pleaserefreshregistrationunknown'] = 'Your site has been registered but the registration date is unknown. Please update your registration using the \'Update registration\' button.';
$string['pluginname'] = 'Moodiy registration';
$string['privacy:metadata:config'] = 'The plugin stores site registration settings in Moodle configuration before and after the site is registered with MoodiyCloud.';
$string['privacy:metadata:config:site_admin_email'] = 'The contact email address sent to MoodiyCloud for the registered site.';
$string['privacy:metadata:config:site_country_code'] = 'The country code reported for the registered site.';
$string['privacy:metadata:config:site_description'] = 'The site description sent to MoodiyCloud during registration and updates.';
$string['privacy:metadata:config:site_language'] = 'The primary language reported for the registered site.';
$string['privacy:metadata:config:site_site_name'] = 'The site name sent to MoodiyCloud during registration and updates.';
$string['privacy:metadata:config:site_organisation_type'] = 'The organisation type reported for the registered site.';
$string['privacy:metadata:config:plugin_versions'] = 'A JSON list of installed plugin versions used for registration update detection.';
$string['privacy:metadata:config:site_policyagreed'] = 'A flag indicating whether the site administrator agreed to the registration policy.';
$string['privacy:metadata:config:site_privacy'] = 'The site listing preference selected during registration.';
$string['privacy:metadata:moodiycloud'] = 'In order to register the site and keep the registration up to date, the plugin exchanges site registration data with MoodiyCloud.';
$string['privacy:metadata:moodiycloud:admin_email'] = 'The site contact email address shared with MoodiyCloud.';
$string['privacy:metadata:moodiycloud:country_code'] = 'The site country code shared with MoodiyCloud.';
$string['privacy:metadata:moodiycloud:description'] = 'The site description shared with MoodiyCloud.';
$string['privacy:metadata:moodiycloud:language'] = 'The site language shared with MoodiyCloud.';
$string['privacy:metadata:moodiycloud:organisation_type'] = 'The organisation type shared with MoodiyCloud.';
$string['privacy:metadata:moodiycloud:site_listing'] = 'The site listing preference shared with MoodiyCloud.';
$string['privacy:metadata:moodiycloud:site_metadata'] = 'Aggregated site statistics and registration metadata shared with MoodiyCloud.';
$string['privacy:metadata:moodiycloud:site_name'] = 'The site name shared with MoodiyCloud.';
$string['privacy:metadata:moodiycloud:site_url'] = 'The site URL shared with MoodiyCloud.';
$string['privacy:metadata:tool_moodiyregistration'] = 'The plugin stores the local registration record used to link this Moodle site with MoodiyCloud.';
$string['privacy:metadata:tool_moodiyregistration:site_url'] = 'The site URL stored for the registered site.';
$string['privacy:metadata:tool_moodiyregistration:site_uuid'] = 'The unique site identifier assigned by MoodiyCloud.';
$string['privacy:metadata:tool_moodiyregistration:timecreated'] = 'The time the local registration record was created.';
$string['privacy:metadata:tool_moodiyregistration:timemodified'] = 'The time the local registration record was last updated.';
$string['registerwithmoodiy'] = 'Register your site with MoodiyCloud';
$string['registerwithmoodiycomplete'] = 'Complete your site registration.';
$string['registerwithmoodiyremove'] = 'You are about to unregister your site, which will result in the loss of access to all Moodiycloud services and products. However, you may re-register at any time. Are you sure you want to proceed?';
$string['registerwithmoodiyupdate'] = 'Update your site registration.';
$string['registrationconfirmed'] = 'Site registration confirmed';
$string['registrationconfirmedon'] = 'Thank you for registering your site. Registration information will be kept up to date by the scheduled task.';
$string['registrationconfirmerror'] = 'There was an error confirming the registration. Please try again later.';
$string['registrationerror'] = 'An error occurred while attempting to register the site: {$a}';
$string['registrationerrorinvalidurl'] = 'The URL provided is invalid. Please check the URL and try again.';
$string['registrationwarning'] = 'Don\'t miss out on important updates and security alerts.';
$string['reregistration'] = 'Site registration does not exist. Please re-register your site with MoodiyCloud.';
$string['sendfollowinginfo_help'] = 'The following information will be sent to MoodiyCloud each time your site registration is updated. The information contributes to overall statistics only and will not be made public on any site listing.';
$string['siteregistrationupdated'] = 'Site registration updated';
$string['siteregistrationurlupdated'] = 'Site url updated with MoodiyCloud.';
$string['siteuuid'] = 'Site UUID: {$a}';
$string['skipregistration'] = 'Skip registration';
$string['tasksiteurlupdate'] = 'Site URL update with MoodiyCloud';
$string['unregister'] = 'Unregister';
$string['unregister-success'] = 'Site unregistered successfully from MoodiyCloud.';
$string['unregistered-already'] = 'Site registration does not exist on MoodiyCloud. Your site may have already been unregistered.';
$string['unregistration-warning'] = 'You cannot unregister while any of your MoodiyCloud products are active. Please turn off or disable MoodiyCloud products before attempting again.';
$string['unregistrationerror'] = 'An error occurred while attempting to unregister the site: {$a}';
$string['updaterequest'] = 'Update request from MoodiyCloud';
$string['updaterequest_desc'] = 'The external user requested for registration with MoodiyCloud with registration id \'{$a}\'.';
$string['upgrade_monitor_task'] = 'Monitor core/plugins upgrade and update registration on MoodiyCloud';
$string['viewsiteuuid'] = 'Click to view site UUID';
