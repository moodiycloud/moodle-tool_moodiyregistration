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
 * An adhoc task.
 *
 * @package     tool_moodiyregistration
 * @category    task
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodiyregistration\task;
/**
 * Adhoc task to register internal site postinstallation of plugin.
 */
class internal_site_registration extends \core\task\adhoc_task {
    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG, $DB;

        if (\tool_moodiyregistration\registration::is_registered()) {
            mtrace('Site is already registered, skipping.');
            // Delete the adhoc task record - it is finished.
            $DB->delete_records('task_adhoc', ['id' => $this->get_id()]);
            return;
        }

        if (!empty($CFG->moodiysiteregistrationuuid)) {
            \tool_moodiyregistration\registration::register_internal_site($CFG->moodiysiteregistrationuuid);
        } else {
            mtrace('Moodiy site registration uuid missing, skipping registration.');
        }
    }
}
