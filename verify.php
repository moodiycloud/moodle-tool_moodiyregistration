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
 * Verification endpoint for Moodiy integration.
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

// Define the expected verification key
// In a production environment, this should be stored securely,
// for example in the Moodle config file or database.
$cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, 'tool_moodiyregistration', 'registration');
$expectedverificationkey = $cache->get('verificationkey');
if (!is_string($expectedverificationkey)) {
    $expectedverificationkey = '';
}

// Get the verification key from the POST data.
$input = file_get_contents('php://input');
$postdata = json_decode($input, true);

// If raw JSON parsing fails, try regular POST data.
if (!is_array($postdata)) {
    $postdata = $_POST;
}
if (!is_array($postdata)) {
    $postdata = [];
}

// Extract verification key.
$verificationkey = clean_param((string)($postdata['verification_key'] ?? ''), PARAM_ALPHANUMEXT);

// Validate the verification key.
if (!empty($verificationkey) && hash_equals($expectedverificationkey, $verificationkey)) {
    // Valid verification key.
    $response = [
        'status' => 'success',
        'message' => 'Verification successful',
        'site' => [
            'name' => $SITE->fullname,
            'url' => $CFG->wwwroot,
            'version' => $CFG->version,
        ],
    ];

    echo json_encode($response);
    exit;
}

// Invalid verification key.
http_response_code(403); // Forbidden.
echo json_encode([
    'status' => 'error',
    'message' => 'Invalid verification key',
]);
