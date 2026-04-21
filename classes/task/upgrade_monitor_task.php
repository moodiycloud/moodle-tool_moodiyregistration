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
 * A scheduled task.
 *
 * @package     tool_moodiyregistration
 * @category    task
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodiyregistration\task;
/**
 * Scheduled task to monitor for version changes
 */
class upgrade_monitor_task extends \core\task\scheduled_task {
    /**
     * Get task name.
     */
    public function get_name() {
        return get_string('upgrade_monitor_task', 'tool_moodiyregistration');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG, $DB;

        if (!\tool_moodiyregistration\registration::is_registered()) {
            mtrace('Site is not registered, skipping update on upgrade.');
            return;
        }

        mtrace('Checking for Moodle version changes...');

        // Check core version.
        $savedcore = get_config('tool_moodiyregistration', 'core_version');
        $currentcore = $CFG->version;

        $versionchanged = false;

        if (empty($savedcore) || $savedcore != $currentcore) {
            mtrace("Core version changed: $savedcore → $currentcore");
            set_config('core_version', $currentcore, 'tool_moodiyregistration');
            $versionchanged = true;
        }

        // Check plugin versions.
        $plugins = \core_plugin_manager::instance()->get_plugins();
        $savedversions = get_config('tool_moodiyregistration', 'plugin_versions');
        $savedversions = !empty($savedversions) ? json_decode($savedversions, true) : [];
        $currentversions = [];

        foreach ($plugins as $plugintype => $plugintypeinstances) {
            foreach ($plugintypeinstances as $pluginname => $plugininfo) {
                $component = $plugintype . '_' . $pluginname;
                if ($plugininfo->is_installed_and_upgraded()) {
                    $currentversions[$component] = $plugininfo->versiondisk;

                    if (!isset($savedversions[$component]) || $savedversions[$component] != $plugininfo->versiondisk) {
                        mtrace("Plugin version changed: $component");
                        $versionchanged = true;
                    }
                }
            }
        }

        // Save current versions.
        set_config('plugin_versions', json_encode($currentversions), 'tool_moodiyregistration');

        // If any version changed, trigger registration update.
        if ($versionchanged) {
            mtrace('Updating registration due to version changes');
            \tool_moodiyregistration\registration::update_registration();
        } else {
            mtrace('No version changes detected');
        }
    }
}
