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
 * Update endpoint for Moodiy integration.
 *
 * @package    tool_moodiyregistration
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Disable moodle specific debug messages and any errors in output.
define('NO_DEBUG_DISPLAY', true);

// We need to use the right AJAX has_capability() check.
define('AJAX_SCRIPT', true);

// No need for Moodle cookies here (avoid session locking).
define('NO_MOODLE_COOKIES', true);

// Allow direct access to this endpoint without login requirement.
define('NO_REDIRECT_ON_UPGRADE', true);

require_once('../../../config.php');
require_once($CFG->libdir . '/filelib.php');
use tool_moodiyregistration\api;

// Set the appropriate content type for JSON responses.
header('Content-Type: application/json; charset=utf-8');

// Allow CORS requests from the Laravel application.
header('Access-Control-Allow-Origin: ' . api::get_api_origin());
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Key');

$requestmethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');

// Handle OPTIONS request (preflight).
if ($requestmethod === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests.
if ($requestmethod !== 'POST') {
    http_response_code(405); // Method Not Allowed.
    echo json_encode([
        'status' => 'error',
        'message' => 'Only POST method is allowed',
    ]);
    exit;
}

/**
 * Get header key - reliable cross-server method
 */
function get_all_headers() {
    $headers = [];

    // If getallheaders() is available (Apache), use it.
    if (function_exists('getallheaders')) {
        return getallheaders();
    }

    // Otherwise manually extract headers from $_SERVER.
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) === 'HTTP_') {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$name] = $value;
        } else if ($name === 'CONTENT_TYPE') {
            $headers['Content-Type'] = $value;
        } else if ($name === 'CONTENT_LENGTH') {
            $headers['Content-Length'] = $value;
        } else if ($name === 'AUTHORIZATION') {
            $headers['Authorization'] = $value;
        }
    }

    return $headers;
}

// Get header key.
$headerkey = '';
$headers = get_all_headers();
foreach ($headers as $key => $value) {
    if (strtolower($key) === 'key') {
        $headerkey = $value;
        break;
    }
}

// Get the hmac hashed payload from the POST data.
$input = file_get_contents('php://input');
$postdata = json_decode($input, true);

// If raw JSON parsing fails, try regular POST data.
if (!is_array($postdata)) {
    $postdata = $_POST;
}
if (!is_array($postdata)) {
    $postdata = [];
}

if (!isset($postdata['site_uuid'], $postdata['id']) || !is_scalar($postdata['site_uuid']) || !is_scalar($postdata['id'])) {
    http_response_code(400); // Bad Request.
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid data payload',
    ]);
    exit;
}

$payloaddata = $postdata;
$siteuuid = clean_param((string)$postdata['site_uuid'], PARAM_ALPHANUMEXT);
$recordid = clean_param((string)$postdata['id'], PARAM_INT);
if ($siteuuid === '' || $recordid === '') {
    http_response_code(400); // Bad Request.
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid data payload',
    ]);
    exit;
}

// Check for valid payload.
ksort($payloaddata);
$payload = json_encode($payloaddata);
if ($payload === false) {
    http_response_code(400); // Bad Request.
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid data payload',
    ]);
    exit;
}
$hmackey = hash_hmac('sha256', $payload, (string)$postdata['site_uuid']);

if (!hash_equals($hmackey, $headerkey)) {
    // Invalid HMAC key.
    http_response_code(400); // Bad Request.
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid HMAC key',
    ]);
    exit;
}

if (
    array_key_exists('timestamp', $postdata)
    && !\tool_moodiyregistration\registration::is_fresh_callback_timestamp($postdata['timestamp'])
) {
    http_response_code(400); // Bad Request.
    // Legacy callers may omit `timestamp`; once present, freshness is part of the signed contract.
    // Keep this compatibility branch until every sender in the fleet is known to include `timestamp`.
    echo json_encode(\tool_moodiyregistration\registration::stale_timestamp_error_response());
    exit;
}

// Validate the verification data.
if (isset($postdata['site_uuid']) && isset($postdata['id'])) {
    if ($DB->record_exists('tool_moodiyregistration', ['site_uuid' => $siteuuid])) {
        $response = [
            'status' => 'success',
            'message' => 'ok',
        ];
        echo json_encode($response);

        // Create task to process the update request.
        \tool_moodiyregistration\registration::queue_update_request_task($siteuuid);

        // Trigger a site registration update request event.
        $event = \tool_moodiyregistration\event\update_request::create([
            'context' => \context_system::instance(),
            'objectid' => (int)$recordid,
            'other' => [
                'site_uuid' => $siteuuid,
            ],
        ]);
        $event->trigger();
        exit;
    } else {
        // Invalid verification.
        http_response_code(403); // Forbidden.
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid data',
        ]);
        exit;
    }
}
