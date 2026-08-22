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
 * Class api for moodiy communication
 *
 * @package    tool_moodiyregistration
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodiyregistration;
use moodle_exception;
use curl;
use stdClass;
use coding_exception;
use moodle_url;

/**
 * Provides methods to communicate with the hub (sites directory) web services.
 *
 * @package    tool_moodiyregistration
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /** @var string The Moodiy API URL */
    const MOODIY_API_URL = 'https://api.moodiycloud.com';
    /** Error message when site registration does not exist. */
    public const ERROR_REGISTRATION_NONEXISTENT = 'site registration does not exist';
    /** Version of the request-signing contract which uses a dedicated secret. */
    public const SIGNATURE_VERSION = '2';
    /** Canonical path covered by update and delete request signatures. */
    public const REGISTRATION_PATH = '/api/site/register';
    /** Plugin config key containing the high-entropy request-signing secret. */
    public const SIGNING_KEY_CONFIG = 'moodiyregistrationsigningkey';
    /** Plugin config key containing the Core-issued signing-key version. */
    public const SIGNING_KEY_VERSION_CONFIG = 'moodiyregistrationsigningkeyversion';
    /** Plugin config key binding a persisted signing key to its site UUID. */
    public const SIGNING_SITE_UUID_CONFIG = 'moodiyregistrationsigningsiteuuid';
    /** Protected CFG key containing the current provisioning request identifier. */
    public const REGISTRATION_ATTEMPT_ID_CONFIG = 'moodiyregistrationattemptid';
    /** Protected CFG key containing the current provisioning action. */
    public const REGISTRATION_ACTION_CONFIG = 'moodiyregistrationaction';
    /** Provisioning actions which Core permits acknowledgement proofs to bind. */
    private const REGISTRATION_ACTIONS = [
        'server_provision_single',
        'server_provision_webdb_pair',
        'server_site_recover',
    ];

    /**
     * Get the API URL for Moodiy.
     *
     * @return string The API URL.
     */
    public static function get_api_base_url(): string {
        global $CFG;

        $apiurl = get_config('tool_moodiyregistration', 'apiurl') ?: ($CFG->moodiy_api_url ?? self::MOODIY_API_URL);
        $apiurl = rtrim((string)$apiurl, '/');
        if (substr($apiurl, -4) === '/api') {
            $apiurl = substr($apiurl, 0, -4);
        }

        return $apiurl;
    }

    /**
     * Get the API URL for Moodiy.
     *
     * @return string The API URL.
     */
    public static function get_apiurl(): string {
        return self::get_api_base_url() . '/api';
    }

    /**
     * Get the allowed web origin for Moodiy callbacks.
     *
     * @return string The API origin.
     */
    public static function get_api_origin(): string {
        $parts = parse_url(self::get_api_base_url());
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return self::MOODIY_API_URL;
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }

    /**
     * Register the site with Moodiy.
     *
     * @param array $params Parameters for registration.
     * @return array Response from the API.
     * @throws moodle_exception If there is an error during the API call.
     */
    public static function moodiy_registration($params = []) {
        $endpoint = self::get_apiurl() . '/site/register';

        $curl = self::get_http_client();
        $header = ['Accept: application/json'];
        $curl->setHeader($header);
        $response = $curl->post($endpoint, $params);

        $response = json_decode($response, true);
        $info = $curl->get_info();
        if ($curl->get_errno()) {
            // Connection error.
            throw new moodle_exception('errorconnect', 'tool_moodiyregistration', '', $curl->error);
        } else if ($response === false) {
            throw new coding_exception('Error calling API: ' . $curl->getError());
        } else if (!self::is_success_http_status((int)$info['http_code']) || empty($response['success'])) {
            $message = $response['message'] ?? 'Error during registration';
            throw new moodle_exception('registrationerror', 'tool_moodiyregistration', '', $message);
        } else {
            return self::accept_acknowledged_response($response, (int)$info['http_code'], null, true);
        }
    }

    /**
     * Update the registration information of the site.
     *
     * @param object $reginfo Registration information.
     * @param array $params Parameters to update.
     * @return array Response from the API.
     * @throws moodle_exception If there is an error during the API call.
     */
    public static function update_registration(object $reginfo, array $params = []) {
        $endpoint = self::get_apiurl() . '/site/register';
        $params['site_uuid'] = (string)$reginfo->site_uuid;
        $params = self::add_registration_attempt_binding($params);
        $timestamp = time();
        $params['timestamp'] = $timestamp;
        $payload = self::encode_canonical_payload($params);
        $credential = self::get_signing_credential((string)$reginfo->site_uuid);
        $credentialrequired = $credential === null;

        $curl = self::get_http_client();
        $header = ['Accept: application/json', 'Content-Type: application/json'];
        if ($credential !== null) {
            $nonce = bin2hex(random_bytes(16));
            $header = array_merge($header, self::build_v2_signature_headers(
                'PUT',
                self::REGISTRATION_PATH,
                $payload,
                (string)$reginfo->site_uuid,
                $credential['secret'],
                $credential['version'],
                $timestamp,
                $nonce
            ));
        } else {
            // Rolling migration only: Core permits this once for rows without a v2 key,
            // then atomically mints the dedicated secret and rejects UUID signing.
            $header[] = 'key: ' . hash_hmac(
                'sha256',
                self::encode_legacy_signature_payload($params),
                (string)$reginfo->site_uuid
            );
        }
        $curl->setHeader($header);
        $response = $curl->put($endpoint, $payload);

        $response = json_decode($response, true);

        $info = $curl->get_info();
        if ($curl->get_errno()) {
            // Connection error.
            throw new moodle_exception('errorconnect', 'tool_moodiyregistration', '', $curl->error);
        } else if ($response === false) {
            throw new coding_exception('Error calling API: ' . $curl->getError());
        } else if (!self::is_success_http_status((int)$info['http_code']) || empty($response['success'])) {
            foreach (self::flatten_error_messages($response) as $error) {
                if (stripos($error, self::ERROR_REGISTRATION_NONEXISTENT) !== false) {
                    // Throw exception to remove registration from moodle.
                    throw new moodle_exception('errorregistrationupdate', 'tool_moodiyregistration', '', $error);
                }
            }
            $message = $response['message'] ?? 'Error during registration update';
            throw new moodle_exception('errorregistrationupdate', 'tool_moodiyregistration', '', $message);
        } else {
            return self::accept_acknowledged_response(
                $response,
                (int)$info['http_code'],
                (string)$reginfo->site_uuid,
                $credentialrequired
            );
        }
    }

    /**
     * Unregister the site from Moodiy.
     *
     * @param object $reginfo Registration information.
     * @return array Response from the API.
     * @throws moodle_exception If there is an error during the API call.
     */
    public static function unregister_site(object $reginfo) {
        $endpoint = self::get_apiurl() . '/site/register';
        $params = [];
        $params['site_uuid'] = $reginfo->site_uuid;
        $timestamp = time();
        $params['timestamp'] = $timestamp;
        $payload = self::encode_canonical_payload($params);
        $credential = self::get_signing_credential((string)$reginfo->site_uuid);

        $curl = self::get_http_client();
        $header = ['Accept: application/json', 'Content-Type: application/json'];
        if ($credential !== null) {
            $nonce = bin2hex(random_bytes(16));
            $header = array_merge($header, self::build_v2_signature_headers(
                'DELETE',
                self::REGISTRATION_PATH,
                $payload,
                (string)$reginfo->site_uuid,
                $credential['secret'],
                $credential['version'],
                $timestamp,
                $nonce
            ));
        } else {
            $header[] = 'key: ' . hash_hmac(
                'sha256',
                self::encode_legacy_signature_payload($params),
                (string)$reginfo->site_uuid
            );
        }
        $curl->setHeader($header);
        $response = $curl->delete($endpoint, [], ['CURLOPT_POSTFIELDS' => $payload]);

        $response = json_decode($response, true);
        $info = $curl->get_info();

        if ($curl->get_errno()) {
            // Connection error.
            throw new moodle_exception('errorconnect', 'tool_moodiyregistration', '', $curl->error);
        } else if ($response === false) {
            throw new coding_exception('Error calling API: ' . $curl->getError());
        } else if (!self::is_success_http_status((int)$info['http_code']) || empty($response['success'])) {
            foreach (self::flatten_error_messages($response) as $error) {
                if (stripos($error, self::ERROR_REGISTRATION_NONEXISTENT) !== false) {
                    // Throw exception to remove registration from moodle.
                    throw new moodle_exception('errorunregister', 'tool_moodiyregistration', '', $error);
                }
            }
            $message = $response['message'] ?? 'Error during un-registration';
            throw new moodle_exception('errorunregister', 'tool_moodiyregistration', '', $message);
        }
        return self::accept_acknowledged_response(
            $response,
            (int)$info['http_code'],
            (string)$reginfo->site_uuid,
            false
        );
    }

    /**
     * Build the version-two request-signing headers.
     *
     * The signature covers the exact raw JSON bytes passed in `$payload`. Callers must send those
     * same bytes without re-encoding them.
     *
     * @param string $method Logical HTTP method.
     * @param string $canonicalpath Canonical request path beginning with a slash.
     * @param string $payload Exact raw JSON request body.
     * @param string $siteuuid Public site identifier.
     * @param string $secret Dedicated high-entropy signing secret.
     * @param int $keyversion Core-issued key version.
     * @param int $timestamp Epoch timestamp.
     * @param string $nonce Unique hexadecimal request nonce.
     * @return string[] HTTP header lines.
     */
    public static function build_v2_signature_headers(
        string $method,
        string $canonicalpath,
        string $payload,
        string $siteuuid,
        string $secret,
        int $keyversion,
        int $timestamp,
        string $nonce
    ): array {
        $canonical = strtoupper($method) . "\n"
            . $canonicalpath . "\n"
            . hash('sha256', $payload) . "\n"
            . $timestamp . "\n"
            . $nonce . "\n"
            . $keyversion;
        $signature = hash_hmac('sha256', $canonical, $secret);

        return [
            'X-Moodiy-Signature-Version: ' . self::SIGNATURE_VERSION,
            'X-Moodiy-Key-Version: ' . $keyversion,
            'X-Moodiy-Timestamp: ' . $timestamp,
            'X-Moodiy-Nonce: ' . $nonce,
            'X-Moodiy-Site-UUID: ' . $siteuuid,
            'X-Moodiy-Signature: ' . $signature,
        ];
    }

    /**
     * Resolve the Moodle HTTP client, allowing transport assertions in PHPUnit.
     *
     * @return curl
     */
    private static function get_http_client(): curl {
        global $CFG;

        if (PHPUNIT_TEST && isset($CFG->tool_moodiyregistration_test_curl)) {
            return $CFG->tool_moodiyregistration_test_curl;
        }

        return new curl();
    }

    /**
     * Encode a stable request body for signing and transport.
     *
     * @param array $params Request payload.
     * @return string Canonical JSON bytes.
     */
    public static function encode_canonical_payload(array $params): string {
        $params = self::sort_payload_recursively($params);
        try {
            $payload = json_encode($params, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new moodle_exception('errorpayloadencoding', 'tool_moodiyregistration', '', $e->getMessage());
        }

        return $payload;
    }

    /**
     * Add the optional protected provisioning-attempt binding to an update payload.
     *
     * The two values are an all-or-none pair. They are inserted before canonical JSON encoding so
     * the exact attempt and action are covered by the body digest and v2 request signature.
     *
     * @param array $params Registration update payload.
     * @return array Payload with an optional validated attempt binding.
     * @throws moodle_exception If the protected configuration is partial or invalid.
     */
    private static function add_registration_attempt_binding(array $params): array {
        global $CFG;

        $attemptid = $CFG->{self::REGISTRATION_ATTEMPT_ID_CONFIG} ?? null;
        $action = $CFG->{self::REGISTRATION_ACTION_CONFIG} ?? null;

        if (($attemptid === null || $attemptid === '') && ($action === null || $action === '')) {
            return $params;
        }

        if (
            !is_string($attemptid)
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,99}$/', $attemptid) !== 1
            || !is_string($action)
            || !in_array($action, self::REGISTRATION_ACTIONS, true)
        ) {
            throw new moodle_exception(
                'errorregistrationupdate',
                'tool_moodiyregistration',
                '',
                'Protected registration attempt binding is incomplete or invalid.'
            );
        }

        $params['registration_attempt_id'] = $attemptid;
        $params['registration_action'] = $action;

        return $params;
    }

    /**
     * Remove a persisted signing credential after local registration removal.
     *
     * Forced `$CFG` credentials remain owned by the protected provisioning include; this method
     * clears only plugin-database config bound to the removed site UUID.
     *
     * @param string $siteuuid Removed site UUID.
     */
    public static function forget_signing_credential(string $siteuuid): void {
        $bounduuid = get_config('tool_moodiyregistration', self::SIGNING_SITE_UUID_CONFIG);
        if (!is_string($bounduuid) || !hash_equals($siteuuid, trim($bounduuid))) {
            return;
        }

        unset_config(self::SIGNING_KEY_CONFIG, 'tool_moodiyregistration');
        unset_config(self::SIGNING_KEY_VERSION_CONFIG, 'tool_moodiyregistration');
        unset_config(self::SIGNING_SITE_UUID_CONFIG, 'tool_moodiyregistration');
    }

    /**
     * Encode the temporary UUID-HMAC payload exactly as the legacy Core middleware expects it.
     *
     * @param array $params Request payload.
     * @return string Legacy signature bytes.
     */
    private static function encode_legacy_signature_payload(array $params): string {
        $params = self::sort_payload_recursively($params);
        $params = array_map(static fn($value) => $value ?? '', $params);
        try {
            return json_encode($params, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new moodle_exception('errorpayloadencoding', 'tool_moodiyregistration', '', $e->getMessage());
        }
    }

    /**
     * Validate an acknowledged response, persist a newly minted credential, and remove secrets.
     *
     * @param array $response Decoded Core response.
     * @param int $httpstatus Response status.
     * @param string|null $siteuuid Site UUID, or null when it is supplied by a registration response.
     * @param bool $credentialrequired Whether this response must mint a credential.
     * @return array Sanitized response with local acknowledgement metadata.
     */
    private static function accept_acknowledged_response(
        array $response,
        int $httpstatus,
        ?string $siteuuid,
        bool $credentialrequired
    ): array {
        if (($response['success'] ?? false) !== true) {
            throw new moodle_exception(
                'errorregistrationupdate',
                'tool_moodiyregistration',
                '',
                'Moodiy did not return a successful registration response.'
            );
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $acknowledgementid = trim((string)($data['acknowledgement_id'] ?? ''));
        if (($data['acknowledged'] ?? false) !== true || $acknowledgementid === '') {
            throw new moodle_exception(
                'errorregistrationupdate',
                'tool_moodiyregistration',
                '',
                'Moodiy did not return a registration acknowledgement.'
            );
        }
        $acknowledgementfingerprint = hash('sha256', $acknowledgementid);
        unset($data['acknowledgement_id'], $response['data']['acknowledgement_id']);
        $acknowledgementid = '';

        $resolveduuid = trim($siteuuid ?? (string)($data['site_uuid'] ?? ''));
        $secret = $data['signing_secret'] ?? null;
        $keyversion = $data['signing_key_version'] ?? null;
        if (!is_numeric($keyversion) || (int)$keyversion <= 0) {
            throw new moodle_exception(
                'errorregistrationupdate',
                'tool_moodiyregistration',
                '',
                'Moodiy returned an invalid signing-key version.'
            );
        }
        $keyversion = (int)$keyversion;
        $currentcredential = self::get_signing_credential($resolveduuid);
        if (is_string($secret) && strlen($secret) >= 32) {
            if ($currentcredential !== null && $keyversion < $currentcredential['version']) {
                throw new moodle_exception(
                    'errorregistrationupdate',
                    'tool_moodiyregistration',
                    '',
                    'Moodiy returned an older signing-key version.'
                );
            }
            if (
                $currentcredential !== null
                && $keyversion === $currentcredential['version']
                && !hash_equals($currentcredential['secret'], $secret)
            ) {
                throw new moodle_exception(
                    'errorregistrationupdate',
                    'tool_moodiyregistration',
                    '',
                    'Moodiy returned conflicting signing material for the active key version.'
                );
            }
            if ($currentcredential === null || $keyversion > $currentcredential['version']) {
                self::persist_signing_credential($resolveduuid, $secret, $keyversion);
            }
        } else if ($credentialrequired && $currentcredential === null) {
            throw new moodle_exception(
                'errorregistrationupdate',
                'tool_moodiyregistration',
                '',
                'Moodiy acknowledged registration but did not provide the required signing credential.'
            );
        } else if ($currentcredential !== null && $keyversion !== $currentcredential['version']) {
            throw new moodle_exception(
                'errorregistrationupdate',
                'tool_moodiyregistration',
                '',
                'Moodiy acknowledged registration with an unexpected signing-key version.'
            );
        }

        $sanitized = [
            'success' => true,
            'data' => [],
            '_moodiy_http_status' => $httpstatus,
            '_moodiy_acknowledgement_fingerprint' => $acknowledgementfingerprint,
            '_moodiy_acknowledged' => true,
            '_moodiy_signing_key_version' => $keyversion,
        ];
        if ($siteuuid === null) {
            // Initial registration needs its new public UUID to create the local row.
            // No other server-returned fields cross this API boundary.
            $sanitized['data']['site_uuid'] = $resolveduuid;
        }

        return $sanitized;
    }

    /**
     * Resolve a signing credential bound to the requested site UUID.
     *
     * @param string $siteuuid Site UUID.
     * @return array{secret:string,version:int}|null
     */
    private static function get_signing_credential(string $siteuuid): ?array {
        global $CFG;

        $storedsecret = get_config(
            'tool_moodiyregistration',
            self::SIGNING_KEY_CONFIG
        );
        $storedkeyversion = get_config(
            'tool_moodiyregistration',
            self::SIGNING_KEY_VERSION_CONFIG
        );
        $storedbounduuid = get_config(
            'tool_moodiyregistration',
            self::SIGNING_SITE_UUID_CONFIG
        );
        $candidates = [
            [
                'secret' => $CFG->{self::SIGNING_KEY_CONFIG} ?? null,
                'version' => $CFG->{self::SIGNING_KEY_VERSION_CONFIG} ?? null,
                'site_uuid' => $CFG->moodiysiteregistrationuuid ?? null,
            ],
            [
                'secret' => $storedsecret,
                'version' => $storedkeyversion,
                'site_uuid' => $storedbounduuid,
            ],
        ];
        $credential = null;
        foreach ($candidates as $candidate) {
            if (
                !is_string($candidate['secret'])
                || strlen($candidate['secret']) < 32
                || !is_numeric($candidate['version'])
                || (int)$candidate['version'] <= 0
                || !is_string($candidate['site_uuid'])
                || !hash_equals($siteuuid, trim($candidate['site_uuid']))
            ) {
                continue;
            }
            if ($credential === null || (int)$candidate['version'] > $credential['version']) {
                $credential = [
                    'secret' => $candidate['secret'],
                    'version' => (int)$candidate['version'],
                ];
            }
        }

        return $credential;
    }

    /**
     * Persist a Core-minted signing credential without placing it in output or logs.
     *
     * @param string $siteuuid Site UUID to bind.
     * @param string $secret High-entropy signing secret.
     * @param int $keyversion Key version.
     */
    private static function persist_signing_credential(string $siteuuid, string $secret, int $keyversion): void {
        global $DB;

        if (
            preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $siteuuid
            ) !== 1
            || strlen($secret) < 32
            || $keyversion <= 0
        ) {
            throw new moodle_exception(
                'errorregistrationupdate',
                'tool_moodiyregistration',
                '',
                'Moodiy returned an invalid signing credential.'
            );
        }

        $transaction = $DB->start_delegated_transaction();
        set_config(self::SIGNING_KEY_CONFIG, $secret, 'tool_moodiyregistration');
        set_config(self::SIGNING_KEY_VERSION_CONFIG, $keyversion, 'tool_moodiyregistration');
        set_config(self::SIGNING_SITE_UUID_CONFIG, $siteuuid, 'tool_moodiyregistration');
        $transaction->allow_commit();
    }

    /**
     * Recursively sort associative payload maps while retaining list order.
     *
     * @param array $payload Payload branch.
     * @return array Sorted payload.
     */
    /**
     * Flatten a Laravel-style validation error bag into a list of message strings.
     *
     * Core answers a 422 with `errors` shaped as `{field: [message, ...]}`, so each
     * entry is an ARRAY, not a string. Passing one straight to stripos() raises a
     * TypeError that escapes the `catch (moodle_exception)` blocks around every
     * caller, which turned a perfectly descriptive validation failure into an
     * opaque `remote_registration_failed` with `remote_http_status: null`.
     *
     * @param array $response Decoded Core response.
     * @return string[] Flat list of message strings.
     */
    private static function flatten_error_messages(array $response): array {
        if (!isset($response['errors']) || !is_array($response['errors'])) {
            return [];
        }

        $messages = [];
        array_walk_recursive($response['errors'], static function ($message) use (&$messages): void {
            if (is_string($message)) {
                $messages[] = $message;
            }
        });

        return $messages;
    }

    private static function sort_payload_recursively(array $payload): array {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::sort_payload_recursively($value);
            }
        }
        if (!array_is_list($payload)) {
            ksort($payload);
        }

        return $payload;
    }

    /**
     * Determine whether an HTTP status represents a successful acknowledgement transport.
     *
     * @param int $status HTTP status.
     * @return bool
     */
    private static function is_success_http_status(int $status): bool {
        return $status >= 200 && $status < 300;
    }
}
