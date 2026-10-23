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

use moodle_exception;

/**
 * A Moodiy answer that refused the request, carrying the HTTP status it arrived with.
 *
 * A refusal is not a transport failure: Core was reached and said no. Callers that
 * report a registration outcome to automation keep the status so an operator can
 * tell a 404 from the exact-operation fence apart from an unreachable Core, which
 * used to collapse into the same `remote_registration_failed` with no status at all.
 *
 * @package    tool_moodiyregistration
 * @copyright  2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remote_registration_exception extends moodle_exception {
    /** @var string Core's `error_code` for a registration that was deleted (moodiycloud/moodiy#1355). */
    public const CORE_REGISTRATION_DELETED = 'site_registration_deleted';

    /** @var int HTTP status Core answered with. */
    private int $httpstatus;

    /** @var string Core's top-level machine-readable `error_code`, or '' when Core sent none. */
    private string $coreerrorcode;

    /**
     * Constructor.
     *
     * @param string $errorcode Plugin language string identifying the failed call.
     * @param int $httpstatus HTTP status Core answered with.
     * @param string $message Sanitised message for the exception text.
     * @param string $coreerrorcode Core's top-level `error_code`, or '' when absent.
     */
    public function __construct(string $errorcode, int $httpstatus, string $message, string $coreerrorcode = '') {
        parent::__construct($errorcode, 'tool_moodiyregistration', '', $message);
        $this->httpstatus = $httpstatus;
        $this->coreerrorcode = $coreerrorcode;
    }

    /**
     * Core's top-level `error_code`, or '' when Core sent none.
     *
     * @return string
     */
    public function get_core_error_code(): string {
        return $this->coreerrorcode;
    }

    /**
     * Whether Core said this registration was deleted, the one answer that may erase it.
     *
     * Only the explicit code counts, never the message text: until vidyamantra/core#1990 Core
     * also sent "Site registration does not exist" for refusals that were only retryable, and
     * 338 sites erased their signing key that way in October 2026 (moodiycloud/moodiy#1355).
     *
     * @return bool
     */
    public function is_registration_deleted(): bool {
        return in_array($this->httpstatus, [404, 410], true)
            && $this->coreerrorcode === self::CORE_REGISTRATION_DELETED;
    }

    /**
     * Whether Core held the request to be sent again later (409): nothing was applied.
     *
     * @return bool
     */
    public function is_held(): bool {
        return $this->httpstatus === 409;
    }

    /**
     * HTTP status Core answered with.
     *
     * @return int
     */
    public function get_http_status(): int {
        return $this->httpstatus;
    }
}
