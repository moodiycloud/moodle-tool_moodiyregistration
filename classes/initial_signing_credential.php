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

// Repository AGENTS.md requires this guard on domain classes.
// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Installs the first Core credential through the authenticated host provisioning channel.
 *
 * @package    tool_moodiyregistration
 * @copyright  2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class initial_signing_credential {
    /**
     * Persist an initial credential, accepting an exact retry after an uncertain result.
     *
     * This is deliberately separate from signed remote rotation. It cannot replace any
     * existing credential or registration identity. The caller must then perform the
     * ordinary signed registration and obtain Core's acknowledgement.
     *
     * @param array $input Exact private provisioning payload.
     */
    public static function install(array $input): void {
        global $CFG, $DB;

        $keys = array_keys($input);
        sort($keys);
        if (
            !defined('CLI_SCRIPT') || !CLI_SCRIPT || !registration::is_internal_site()
            || $keys !== ['domain', 'registration_signing_key_version', 'registration_signing_secret', 'site_uuid', 'username']
            || !is_string($input['domain']) || !is_string($input['username'])
            || !is_string($input['site_uuid']) || !is_string($input['registration_signing_secret'])
            || !is_int($input['registration_signing_key_version']) || $input['registration_signing_key_version'] < 1
            || preg_match('/^[A-Za-z0-9]{64}$/D', $input['registration_signing_secret']) !== 1
            || preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
                $input['site_uuid']
            ) !== 1
            || !hash_equals(rtrim($CFG->wwwroot, '/'), 'https://' . $input['domain'])
            || !hash_equals($CFG->dbname, $input['username'])
            || !hash_equals((string)($CFG->moodiysiteregistrationuuid ?? ''), $input['site_uuid'])
        ) {
            throw new \invalid_parameter_exception('Initial registration identity is invalid.');
        }

        $lock = \core\lock\lock_config::get_lock_factory('tool_moodiyregistration')->get_lock('initial-signing-credential', 10);
        if (!$lock) {
            throw new \moodle_exception('errorregistrationupdate', 'tool_moodiyregistration');
        }
        try {
            $uuid = $input['site_uuid'];
            $secret = $input['registration_signing_secret'];
            $version = $input['registration_signing_key_version'];
            // Inspect the raw sources: selecting only the highest valid key hides partial
            // or foreign identity material, which initial delivery must never overwrite.
            $cfgsecret = $CFG->{api::SIGNING_KEY_CONFIG} ?? false;
            $cfgversion = $CFG->{api::SIGNING_KEY_VERSION_CONFIG} ?? false;
            if ($cfgsecret !== false || $cfgversion !== false) {
                self::require_exact_credential($cfgsecret, $cfgversion, $CFG->moodiysiteregistrationuuid, $input);
            }
            $storedsecret = get_config('tool_moodiyregistration', api::SIGNING_KEY_CONFIG);
            $storedversion = get_config('tool_moodiyregistration', api::SIGNING_KEY_VERSION_CONFIG);
            $storeduuid = get_config('tool_moodiyregistration', api::SIGNING_SITE_UUID_CONFIG);
            if ($storedsecret !== false || $storedversion !== false || $storeduuid !== false) {
                self::require_exact_credential($storedsecret, $storedversion, $storeduuid, $input);
            }
            foreach ($DB->get_fieldset_select('tool_moodiyregistration', 'site_uuid', '') as $registereduuid) {
                if (!is_string($registereduuid) || !hash_equals($uuid, $registereduuid)) {
                    throw new \invalid_parameter_exception('Existing registration identity conflicts.');
                }
            }
            // Both sources were checked above. An exact existing credential is already
            // installed. Never rewrite it on replay: a normal signed callback can rotate
            // the stored key independently after our read, and we must not put the old
            // version back. Initial delivery writes only when neither source has a key.
            if ($cfgsecret !== false || $storedsecret !== false) {
                return;
            }
            $transaction = $DB->start_delegated_transaction();
            set_config(api::SIGNING_KEY_CONFIG, $secret, 'tool_moodiyregistration');
            set_config(api::SIGNING_KEY_VERSION_CONFIG, $version, 'tool_moodiyregistration');
            set_config(api::SIGNING_SITE_UUID_CONFIG, $uuid, 'tool_moodiyregistration');
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
    }

    /**
     * Refuse partial credentials, replacement, rotation and downgrade on this channel.
     *
     * @param mixed $secret Existing secret.
     * @param mixed $version Existing version.
     * @param mixed $uuid Existing bound UUID.
     * @param array $input Requested credential.
     */
    private static function require_exact_credential($secret, $version, $uuid, array $input): void {
        if (
            !is_string($secret) || !hash_equals($input['registration_signing_secret'], $secret)
            || (!is_int($version) && !is_string($version))
            || (string)$version !== (string)$input['registration_signing_key_version']
            || !is_string($uuid) || !hash_equals($input['site_uuid'], $uuid)
        ) {
            throw new \invalid_parameter_exception('Existing registration credential conflicts.');
        }
    }
}
