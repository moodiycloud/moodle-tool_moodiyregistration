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
 * Adhoc task to process update requests from Moodiy.
 */
class process_update_request extends \core\task\adhoc_task {
    /**
     * Execute the task.
     */
    public function execute() {
        \tool_moodiyregistration\registration::update_registration(true);
        if (\tool_moodiyregistration\registration::last_update_was_held()) {
            // Moodiy held the update (409, moodiycloud/moodiy#1355). This task's own row hides
            // a new one from queue_update_request_task(), and failing it would retry after
            // Moodle's one-minute fail delay, inside the hold. Queue the successor directly,
            // delayed by the hold; this task then completes.
            $retry = new self();
            $retry->set_custom_data($this->get_custom_data());
            $retry->set_next_run_time(time() + \tool_moodiyregistration\registration::HOLD_RETRY_SECONDS);
            \core\task\manager::queue_adhoc_task($retry);
        }
    }
}
