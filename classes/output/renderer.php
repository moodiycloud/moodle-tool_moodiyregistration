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
 * Renderer for Moodiy registration tool.
 *
 * @package    tool_moodiyregistration
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodiyregistration\output;

/**
 * Renderer for Moodiy registration tool.
 *
 * @package    tool_moodiyregistration
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render the warningbox using a template.
     *
     * @param string $message
     * @param string $continue
     * @param array $displayoptions
     * @return string
     */
    public function warningbox($message, $continue, array $displayoptions = []) {
        $data = [
            'confirmtitle' => $displayoptions['confirmtitle'] ?? get_string('warning', 'core'),
            'message' => $message,
            'continue' => $continue,
        ];
        return $this->render_from_template('tool_moodiyregistration/warningbox', $data);
    }
}
