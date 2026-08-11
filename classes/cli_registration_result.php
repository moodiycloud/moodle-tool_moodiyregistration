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
 * Defines the fail-closed provisioning CLI result contract.
 *
 * @package    tool_moodiyregistration
 * @copyright  2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cli_registration_result {
    /**
     * Determine whether Core has authoritatively acknowledged registration.
     *
     * @param array $result Registration repair result.
     * @return bool
     */
    public static function is_acknowledged_success(array $result): bool {
        $httpstatus = $result['remote_http_status'] ?? null;
        $acknowledgementfingerprint = strtolower(trim((string)(
            $result['acknowledgement_fingerprint'] ?? ''
        )));
        $siteuuid = (string)($result['site_uuid'] ?? '');
        $signingkeyversion = $result['signing_key_version'] ?? null;

        return ($result['status'] ?? null) === 'ok'
            && ($result['remote_sync_status'] ?? null) === 'ok'
            && ($result['remote_acknowledged'] ?? false) === true
            && is_numeric($httpstatus)
            && (int)$httpstatus >= 200
            && (int)$httpstatus < 300
            && preg_match('/^[a-f0-9]{64}$/', $acknowledgementfingerprint) === 1
            && preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $siteuuid
            ) === 1
            && is_numeric($signingkeyversion)
            && (int)$signingkeyversion > 0;
    }

    /**
     * Return the process exit code for a registration result.
     *
     * @param array $result Registration repair result.
     * @return int Zero only for an acknowledged success.
     */
    public static function exit_code(array $result): int {
        return self::is_acknowledged_success($result) ? 0 : 1;
    }

    /**
     * Build the explicit, non-secret provisioning proof returned on stdout.
     *
     * An allowlist is intentionally used instead of recursive redaction. Core response bodies,
     * local record details, URLs, UUIDs, email addresses, and signing material therefore cannot
     * be exposed when a future caller adds them to the internal result.
     *
     * @param array $result Registration repair result.
     * @return array Sanitized result.
     */
    public static function sanitize(array $result): array {
        $acknowledgementfingerprint = strtolower(trim((string)(
            $result['acknowledgement_fingerprint'] ?? ''
        )));
        if (preg_match('/^[a-f0-9]{64}$/', $acknowledgementfingerprint) !== 1) {
            $acknowledgementfingerprint = null;
        }

        $siteuuid = (string)($result['site_uuid'] ?? '');
        $siteuuidfingerprint = $siteuuid === '' ? null : hash('sha256', $siteuuid);
        $httpstatus = $result['remote_http_status'] ?? null;
        $signingkeyversion = $result['signing_key_version'] ?? null;
        $remotesyncstatus = (string)($result['remote_sync_status'] ?? 'failed');
        if (!in_array($remotesyncstatus, ['ok', 'pending', 'failed'], true)) {
            $remotesyncstatus = 'failed';
        }

        $sanitized = [
            'remote_sync_status' => $remotesyncstatus,
            'remote_acknowledged' => ($result['remote_acknowledged'] ?? false) === true,
            'remote_http_status' => is_numeric($httpstatus) ? (int)$httpstatus : null,
            'acknowledgement_fingerprint' => $acknowledgementfingerprint,
            'signing_key_version' => is_numeric($signingkeyversion) ? (int)$signingkeyversion : null,
            'site_uuid_fingerprint' => $siteuuidfingerprint,
        ];

        foreach (['error_code', 'remote_sync_error_code'] as $errorfield) {
            $errorcode = strtolower(trim((string)($result[$errorfield] ?? '')));
            if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $errorcode) === 1) {
                $sanitized[$errorfield] = $errorcode;
            }
        }

        return $sanitized;
    }

    /**
     * Encode a sanitized result for the CLI's single stdout line.
     *
     * @param array $result Registration repair result.
     * @return string Safe JSON output.
     */
    public static function encode(array $result): string {
        $encoded = json_encode(self::sanitize($result));
        if ($encoded !== false) {
            return $encoded;
        }

        return '{"remote_sync_status":"failed","remote_acknowledged":false,'
            . '"remote_http_status":null,"acknowledgement_fingerprint":null,'
            . '"signing_key_version":null,"site_uuid_fingerprint":null,'
            . '"error_code":"result_encoding_failed"}';
    }
}
