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

namespace tool_moodiyregistration\privacy;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

/**
 * Privacy provider for tool_moodiyregistration.
 *
 * The plugin stores site-level registration information and exchanges registration
 * payloads with MoodiyCloud, but it does not map that data to Moodle privacy contexts.
 *
 * @package     tool_moodiyregistration
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the data stored by the plugin.
     *
     * @param collection $collection The metadata collection to update.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('tool_moodiyregistration', [
            'site_uuid' => 'privacy:metadata:tool_moodiyregistration:site_uuid',
            'site_url' => 'privacy:metadata:tool_moodiyregistration:site_url',
            'timecreated' => 'privacy:metadata:tool_moodiyregistration:timecreated',
            'timemodified' => 'privacy:metadata:tool_moodiyregistration:timemodified',
        ], 'privacy:metadata:tool_moodiyregistration');

        $collection->add_database_table('config_plugins', [
            'site_admin_email' => 'privacy:metadata:config:site_admin_email',
            'site_site_name' => 'privacy:metadata:config:site_site_name',
            'site_description' => 'privacy:metadata:config:site_description',
            'site_language' => 'privacy:metadata:config:site_language',
            'site_country_code' => 'privacy:metadata:config:site_country_code',
            'site_organisation_type' => 'privacy:metadata:config:site_organisation_type',
            'site_privacy' => 'privacy:metadata:config:site_privacy',
            'plugin_versions' => 'privacy:metadata:config:plugin_versions',
            'site_policyagreed' => 'privacy:metadata:config:site_policyagreed',
        ], 'privacy:metadata:config');

        $collection->add_external_location_link('moodiycloud', [
            'admin_email' => 'privacy:metadata:moodiycloud:admin_email',
            'site_url' => 'privacy:metadata:moodiycloud:site_url',
            'site_name' => 'privacy:metadata:moodiycloud:site_name',
            'description' => 'privacy:metadata:moodiycloud:description',
            'language' => 'privacy:metadata:moodiycloud:language',
            'country_code' => 'privacy:metadata:moodiycloud:country_code',
            'site_listing' => 'privacy:metadata:moodiycloud:site_listing',
            'organisation_type' => 'privacy:metadata:moodiycloud:organisation_type',
            'site_metadata' => 'privacy:metadata:moodiycloud:site_metadata',
        ], 'privacy:metadata:moodiycloud');

        return $collection;
    }

    /**
     * The plugin does not currently map its site-level registration data to Moodle contexts.
     *
     * @param int $userid The user to search.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    /**
     * The plugin does not currently map its site-level registration data to Moodle contexts.
     *
     * @param userlist $userlist The userlist containing the users with data in this context.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
    }

    /**
     * The plugin does not currently export context-linked user data.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
    }

    /**
     * The plugin does not currently store context-linked user data.
     *
     * @param \context $context The specific context to delete data for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
    }

    /**
     * The plugin does not currently store context-linked user data.
     *
     * @param approved_userlist $userlist The approved context and user information to delete.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
    }

    /**
     * The plugin does not currently store context-linked user data.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
    }
}
