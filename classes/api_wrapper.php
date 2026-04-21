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

namespace tool_moodiyregistration;
/**
 * Class api_wrapper
 *
 * @package    tool_moodiyregistration
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Wrapper for API calls to make testing easier.
 */
class api_wrapper {
    /**
     * Instance proxy for the static api::moodiy_registration call.
     *
     * @param array $params Registration request payload sent to the Moodiy API.
     * @return mixed Decoded response from api::moodiy_registration().
     */
    public function moodiy_registration($params) {
        return api::moodiy_registration($params);
    }

    /**
     * Instance proxy for the static api::update_registration call.
     *
     * @param object $reginfo Existing registration record (carries site_uuid used for HMAC auth).
     * @param array $data Updated site info payload to send.
     * @return mixed Decoded response from api::update_registration().
     */
    public function update_registration($reginfo, $data) {
        return api::update_registration($reginfo, $data);
    }

    /**
     * Instance proxy for the static api::unregister_site call.
     *
     * @param object $reginfo Existing registration record (carries site_uuid used for HMAC auth).
     * @return mixed Decoded response from api::unregister_site().
     */
    public function unregister_site($reginfo) {
        return api::unregister_site($reginfo);
    }
}
