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
 * Provisioning CLI output and exit-code tests.
 *
 * @package     tool_moodiyregistration
 * @category    test
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \tool_moodiyregistration\cli_registration_result
 */
final class cli_registration_result_test extends \advanced_testcase {
    /**
     * Set up isolated config for each result-contract test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test the exact authoritative success shape exits zero.
     * @covers ::is_acknowledged_success
     * @covers ::exit_code
     */
    public function test_only_complete_core_acknowledgement_exits_zero(): void {
        $result = $this->acknowledged_result();

        $this->assertTrue(cli_registration_result::is_acknowledged_success($result));
        $this->assertSame(0, cli_registration_result::exit_code($result));
    }

    /**
     * Test local success, legacy unchanged, and incomplete acknowledgements fail closed.
     *
     * @dataProvider incomplete_result_provider
     * @param array $changes Fields to replace in the valid fixture.
     * @covers ::is_acknowledged_success
     * @covers ::exit_code
     */
    public function test_incomplete_or_unacknowledged_results_exit_nonzero(array $changes): void {
        $result = array_replace($this->acknowledged_result(), $changes);

        $this->assertFalse(cli_registration_result::is_acknowledged_success($result));
        $this->assertSame(1, cli_registration_result::exit_code($result));
    }

    /**
     * Provide every independently required success field.
     *
     * @return array<string,array{0:array}>
     */
    public static function incomplete_result_provider(): array {
        return [
            'local repair failed' => [['status' => 'error']],
            'remote pending' => [['remote_sync_status' => 'pending']],
            'legacy unchanged is not success' => [['remote_sync_status' => 'unchanged']],
            'acknowledged flag absent' => [['remote_acknowledged' => false]],
            'non-2xx status' => [['remote_http_status' => 503]],
            'acknowledgement fingerprint absent' => [['acknowledgement_fingerprint' => '']],
            'site UUID malformed' => [['site_uuid' => 'not-a-uuid']],
            'key version absent' => [['signing_key_version' => null]],
            'key version invalid' => [['signing_key_version' => 0]],
        ];
    }

    /**
     * Test stdout is the exact six-field proof allowlist and never contains sensitive values.
     * @covers ::sanitize
     * @covers ::encode
     */
    public function test_sanitize_removes_credentials_and_redacts_secret_values(): void {
        $secret = 'cli-output-must-not-contain-this-secret';
        set_config(api::SIGNING_KEY_CONFIG, $secret, 'tool_moodiyregistration');
        $result = $this->acknowledged_result() + [
            'acknowledgement_id' => 'ack-123',
            'signing_secret' => $secret,
            'site_url' => 'https://private-site.example',
            'admin_email' => 'private-admin@example.com',
            'remote_sync_error' => 'Transport failed for ' . $secret,
            'raw_response' => 'raw-core-response-sentinel',
            'registration_attempt_id' => 'provision.attempt:stdout-sentinel',
            'registration_action' => 'server_provision_single',
            'remote_detail' => 'Core acknowledgement ack-123 for 123e4567-e89b-42d3-a456-426614174000, '
                . 'private-admin@example.com, and https://private-site.example was rejected.',
            'nested' => [
                'nonce' => '001122',
                'signature' => 'aabbcc',
                'safe' => 'visible',
            ],
        ];

        $sanitized = cli_registration_result::sanitize($result);
        $encoded = cli_registration_result::encode($result);

        $this->assertSame([
            'remote_sync_status' => 'ok',
            'remote_acknowledged' => true,
            'remote_http_status' => 200,
            'acknowledgement_fingerprint' => hash('sha256', 'ack-123'),
            'signing_key_version' => 1,
            'site_uuid_fingerprint' => hash('sha256', '123e4567-e89b-42d3-a456-426614174000'),
        ], $sanitized);
        $this->assertSame($sanitized, json_decode($encoded, true, 512, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($secret, (string)$encoded);
        $this->assertStringNotContainsString('ack-123', (string)$encoded);
        $this->assertStringNotContainsString('raw-core-response-sentinel', (string)$encoded);
        $this->assertStringNotContainsString('123e4567-e89b-42d3-a456-426614174000', (string)$encoded);
        $this->assertStringNotContainsString('private-admin@example.com', (string)$encoded);
        $this->assertStringNotContainsString('https://private-site.example', (string)$encoded);
        $this->assertStringNotContainsString('provision.attempt:stdout-sentinel', (string)$encoded);
        $this->assertStringNotContainsString('server_provision_single', (string)$encoded);
    }

    /**
     * Test failures add only stable allowlisted error codes to the six proof fields.
     * @covers ::sanitize
     */
    public function test_sanitize_failure_allows_only_stable_error_codes(): void {
        $result = [
            'status' => 'ok',
            'message' => 'private-admin@example.com received raw-core-response-sentinel',
            'site_uuid' => '123e4567-e89b-42d3-a456-426614174000',
            'remote_sync_status' => 'pending',
            'remote_acknowledged' => false,
            'remote_sync_error_code' => 'transport_error',
            'error_code' => "invalid code\nprivate-admin@example.com",
            'remote_sync_error' => 'raw-core-response-sentinel',
        ];

        $this->assertSame([
            'remote_sync_status' => 'pending',
            'remote_acknowledged' => false,
            'remote_http_status' => null,
            'acknowledgement_fingerprint' => null,
            'signing_key_version' => null,
            'site_uuid_fingerprint' => hash('sha256', '123e4567-e89b-42d3-a456-426614174000'),
            'remote_sync_error_code' => 'transport_error',
        ], cli_registration_result::sanitize($result));
    }

    /**
     * Build the complete callback-visible success contract.
     *
     * @return array
     */
    private function acknowledged_result(): array {
        return [
            'status' => 'ok',
            'remote_sync_status' => 'ok',
            'remote_acknowledged' => true,
            'remote_http_status' => 200,
            'acknowledgement_fingerprint' => hash('sha256', 'ack-123'),
            'site_uuid' => '123e4567-e89b-42d3-a456-426614174000',
            'signing_key_version' => 1,
        ];
    }
}
