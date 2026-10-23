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
 * Request-signing and acknowledgement contract tests.
 *
 * @package     tool_moodiyregistration
 * @category    test
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \tool_moodiyregistration\api
 */
final class api_test extends \advanced_testcase {
    /**
     * Set up an isolated config transaction for each test.
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest(true);
        unset(
            $CFG->{api::REGISTRATION_ATTEMPT_ID_CONFIG},
            $CFG->{api::REGISTRATION_ACTION_CONFIG}
        );
    }

    /**
     * A fresh CLI caller has no PHPUnit-preloaded global curl class.
     *
     * @covers ::get_http_client
     */
    public function test_http_client_loads_moodle_library_in_cold_process(): void {
        $process = proc_open([
            PHP_BINARY,
            __DIR__ . '/fixtures/http_client_cold.php',
        ], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $stderr);
        $this->assertSame(
            ['preloaded' => false, 'client' => 'curl', 'repeat' => 'curl'],
            json_decode($stdout, true, 16, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Policy reads reuse the protected destination key, even after a foreign DB import.
     *
     * @covers ::usage_policy
     */
    public function test_usage_policy_signs_exact_body_without_mutating_credentials(): void {
        global $CFG;

        $siteuuid = 'destination-site-uuid';
        $secret = str_repeat('d', 64);
        $CFG->moodiysiteregistrationuuid = $siteuuid;
        $CFG->{api::SIGNING_KEY_CONFIG} = $secret;
        $CFG->{api::SIGNING_KEY_VERSION_CONFIG} = 3;
        $CFG->moodiy_api_url = 'https://core.example';
        set_config('apiurl', 'https://foreign-import.example/api', 'tool_moodiyregistration');
        set_config(api::SIGNING_KEY_CONFIG, str_repeat('f', 64), 'tool_moodiyregistration');
        set_config(api::SIGNING_KEY_VERSION_CONFIG, 99, 'tool_moodiyregistration');
        set_config(api::SIGNING_SITE_UUID_CONFIG, 'foreign-import-uuid', 'tool_moodiyregistration');
        $headers = [];
        $body = '';
        $curl = $this->getMockBuilder(\curl::class)->disableOriginalConstructor()
            ->onlyMethods(['setHeader', 'post', 'get_info', 'get_errno'])->getMock();
        $curl->method('setHeader')->willReturnCallback(function (array $value) use (&$headers): void {
            $headers = $value;
        });
        $curl->expects($this->once())->method('post')->willReturnCallback(function (
            string $url,
            string $payload,
            array $options
        ) use (
            &$body,
            $siteuuid
): string {
            $this->assertSame('https://core.example/api/site/usage-policy', $url);
            $this->assertTrue($options['CURLOPT_SSL_VERIFYPEER']);
            $this->assertSame(2, $options['CURLOPT_SSL_VERIFYHOST']);
            $this->assertSame(false, $options['CURLOPT_FOLLOWLOCATION']);
            $this->assertSame(5, $options['CURLOPT_CONNECTTIMEOUT']);
            $this->assertSame(15, $options['CURLOPT_TIMEOUT']);
            $body = $payload;

            return json_encode(['success' => true, 'data' => ['site_uuid' => $siteuuid,
                'policy_version' => 'essential-v1', 'activity_floor' => 0, 'managed_support_email' => 'support@example.test']]);
        });
        $curl->method('get_info')->willReturn(['http_code' => 200]);
        $curl->method('get_errno')->willReturn(0);
        $CFG->tool_moodiyregistration_test_curl = $curl;

        $policy = api::usage_policy();

        $named = $this->headers_by_name($headers);
        $decoded = json_decode($body, true);
        $canonical = "POST\n/api/site/usage-policy\n" . hash('sha256', $body) . "\n"
            . $named['X-Moodiy-Timestamp'] . "\n" . $named['X-Moodiy-Nonce'] . "\n3";
        $this->assertSame($siteuuid, $decoded['site_uuid']);
        $this->assertSame('3', (string)$named['X-Moodiy-Key-Version']);
        $this->assertSame(hash_hmac('sha256', $canonical, $secret), $named['X-Moodiy-Signature']);
        $this->assertSame('essential-v1', $policy['policy_version']);
        $this->assertSame(str_repeat('f', 64), get_config('tool_moodiyregistration', api::SIGNING_KEY_CONFIG));
        $this->assertSame('foreign-import-uuid', get_config('tool_moodiyregistration', api::SIGNING_SITE_UUID_CONFIG));
    }

    /**
     * A missing protected URL uses the built-in first-party origin, never imported config.
     *
     * @covers ::usage_policy
     */
    public function test_usage_policy_protected_identity_uses_default_origin_when_url_is_absent(): void {
        global $CFG;
        $siteuuid = 'protected-default-core-site';
        $CFG->moodiysiteregistrationuuid = $siteuuid;
        $CFG->{api::SIGNING_KEY_CONFIG} = str_repeat('s', 64);
        $CFG->{api::SIGNING_KEY_VERSION_CONFIG} = 1;
        unset($CFG->moodiy_api_url);
        set_config('apiurl', 'https://foreign-import.example/api', 'tool_moodiyregistration');
        $curl = $this->getMockBuilder(\curl::class)->disableOriginalConstructor()
            ->onlyMethods(['setHeader', 'post', 'get_info', 'get_errno'])->getMock();
        $curl->expects($this->once())->method('post')
            ->with(rtrim(api::MOODIY_API_URL, '/') . '/api/site/usage-policy', $this->anything(), $this->anything())
            ->willReturn(json_encode(['success' => true, 'data' => ['site_uuid' => $siteuuid]]));
        $curl->method('get_info')->willReturn(['http_code' => 200]);
        $curl->method('get_errno')->willReturn(0);
        $CFG->tool_moodiyregistration_test_curl = $curl;
        $this->assertSame($siteuuid, api::usage_policy()['site_uuid']);
    }

    /**
     * A policy response needs authenticated HTTPS even with a valid request signature.
     *
     * @covers ::usage_policy
     */
    public function test_usage_policy_refuses_plaintext_protected_core_url(): void {
        global $CFG;
        $CFG->moodiysiteregistrationuuid = 'protected-plaintext-site';
        $CFG->{api::SIGNING_KEY_CONFIG} = str_repeat('s', 64);
        $CFG->{api::SIGNING_KEY_VERSION_CONFIG} = 1;
        $CFG->moodiy_api_url = 'http://core.example';
        $curl = $this->getMockBuilder(\curl::class)->disableOriginalConstructor()->onlyMethods(['setHeader', 'post'])->getMock();
        $curl->expects($this->never())->method('post');
        $CFG->tool_moodiyregistration_test_curl = $curl;
        $this->expectException(\RuntimeException::class);
        api::usage_policy();
    }

    /**
     * An unprotected registration retains its deliberate plugin URL precedence.
     *
     * @covers ::usage_policy
     */
    public function test_usage_policy_preserves_url_precedence_without_protected_identity(): void {
        global $CFG, $DB;
        unset($CFG->moodiysiteregistrationuuid, $CFG->{api::SIGNING_KEY_CONFIG}, $CFG->{api::SIGNING_KEY_VERSION_CONFIG});
        $siteuuid = 'ordinary-registered-uuid';
        $DB->insert_record('tool_moodiyregistration', (object)['site_uuid' => $siteuuid]);
        $CFG->moodiy_api_url = 'https://default-core.example';
        set_config('apiurl', 'https://registered-core.example/api', 'tool_moodiyregistration');
        set_config(api::SIGNING_KEY_CONFIG, str_repeat('s', 64), 'tool_moodiyregistration');
        set_config(api::SIGNING_KEY_VERSION_CONFIG, 1, 'tool_moodiyregistration');
        set_config(api::SIGNING_SITE_UUID_CONFIG, $siteuuid, 'tool_moodiyregistration');
        $curl = $this->getMockBuilder(\curl::class)->disableOriginalConstructor()
            ->onlyMethods(['setHeader', 'post', 'get_info', 'get_errno'])->getMock();
        $curl->expects($this->once())->method('post')
            ->with('https://registered-core.example/api/site/usage-policy', $this->anything(), $this->anything())
            ->willReturn(json_encode(['success' => true, 'data' => ['site_uuid' => $siteuuid]]));
        $curl->method('get_info')->willReturn(['http_code' => 200]);
        $curl->method('get_errno')->willReturn(0);
        $CFG->tool_moodiyregistration_test_curl = $curl;
        $this->assertSame($siteuuid, api::usage_policy()['site_uuid']);
    }

    /**
     * A policy read must never fall back to public UUID signing or bootstrap.
     *
     * @covers ::usage_policy
     */
    public function test_usage_policy_requires_a_bound_v2_credential(): void {
        global $CFG;

        $CFG->moodiysiteregistrationuuid = 'destination-site-uuid';
        unset($CFG->{api::SIGNING_KEY_CONFIG}, $CFG->{api::SIGNING_KEY_VERSION_CONFIG});
        $this->expectException(\RuntimeException::class);
        api::usage_policy();
    }

    /**
     * Missing/foreign responses must not become a current policy.
     *
     * @covers ::usage_policy
     */
    public function test_usage_policy_rejects_unavailable_or_foreign_response(): void {
        global $CFG;

        $CFG->moodiysiteregistrationuuid = 'destination-site-uuid';
        $CFG->{api::SIGNING_KEY_CONFIG} = str_repeat('d', 64);
        $CFG->{api::SIGNING_KEY_VERSION_CONFIG} = 1;
        foreach ([[503, '{}'], [200, 'broken-json'], [200, '{"success":true,"data":{"site_uuid":"foreign"}}']] as $case) {
            $curl = $this->getMockBuilder(\curl::class)->disableOriginalConstructor()
                ->onlyMethods(['setHeader', 'post', 'get_info', 'get_errno'])->getMock();
            $curl->method('post')->willReturn($case[1]);
            $curl->method('get_info')->willReturn(['http_code' => $case[0]]);
            $curl->method('get_errno')->willReturn(0);
            $CFG->tool_moodiyregistration_test_curl = $curl;
            try {
                api::usage_policy();
                $this->fail('An unavailable/foreign policy must be rejected.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('unavailable', $exception->getMessage());
            }
        }
    }

    /**
     * Core answers a 422 with `errors` shaped {field: [message, ...]}, so each entry
     * is an ARRAY. Passing one to stripos() raised a TypeError that escaped every
     * `catch (moodle_exception)` around the callers, turning a descriptive
     * validation failure into an opaque remote_registration_failed.
     * @covers ::flatten_error_messages
     */
    public function test_nested_validation_error_bag_is_flattened_to_strings(): void {
        $method = new \ReflectionMethod(api::class, 'flatten_error_messages');
        $method->setAccessible(true);

        $messages = $method->invoke(null, [
            'success' => false,
            'message' => 'The description field is too large. (and 1 more error)',
            'errors' => [
                'description' => [
                    'The description field is too large.',
                    'The description field may not contain embedded data URI images.',
                ],
            ],
        ]);

        $this->assertSame([
            'The description field is too large.',
            'The description field may not contain embedded data URI images.',
        ], $messages);
    }

    /**
     * A response with no error bag, or a non-array one, must yield an empty list
     * rather than raising.
     * @covers ::flatten_error_messages
     */
    public function test_absent_or_malformed_error_bag_yields_no_messages(): void {
        $method = new \ReflectionMethod(api::class, 'flatten_error_messages');
        $method->setAccessible(true);

        $this->assertSame([], $method->invoke(null, ['success' => false]));
        $this->assertSame([], $method->invoke(null, ['errors' => 'not-an-array']));
        $this->assertSame(['flat'], $method->invoke(null, ['errors' => ['field' => 'flat']]));
    }

    /**
     * Test canonical JSON is stable while list order remains meaningful.
     * @covers ::encode_canonical_payload
     */
    public function test_encode_canonical_payload_sorts_maps_recursively(): void {
        $payload = api::encode_canonical_payload([
            'z' => ['b' => 2, 'a' => 1],
            'list' => [['z' => 2, 'a' => 1], 'second'],
            'a' => 'first',
        ]);

        $this->assertSame(
            '{"a":"first","list":[{"a":1,"z":2},"second"],"z":{"a":1,"b":2}}',
            $payload
        );
    }

    /**
     * Test v2 signing binds every replay-sensitive request component.
     * @covers ::build_v2_signature_headers
     */
    public function test_v2_signature_binds_method_path_raw_body_timestamp_nonce_and_key_version(): void {
        $payload = '{"site_uuid":"site-123","timestamp":1700000000}';
        $secret = 'high-entropy-registration-secret-0123456789';
        $nonce = '00112233445566778899aabbccddeeff';
        $canonical = "PUT\n/api/site/register\n"
            . hash('sha256', $payload)
            . "\n1700000000\n{$nonce}\n7";
        $expectedsignature = hash_hmac('sha256', $canonical, $secret);

        $headers = api::build_v2_signature_headers(
            'put',
            '/api/site/register',
            $payload,
            'site-123',
            $secret,
            7,
            1700000000,
            $nonce
        );

        $this->assertSame([
            'X-Moodiy-Signature-Version: 2',
            'X-Moodiy-Key-Version: 7',
            'X-Moodiy-Timestamp: 1700000000',
            'X-Moodiy-Nonce: ' . $nonce,
            'X-Moodiy-Site-UUID: site-123',
            'X-Moodiy-Signature: ' . $expectedsignature,
        ], $headers);
        $this->assertStringNotContainsString($secret, implode("\n", $headers));

        $changedbodyheaders = api::build_v2_signature_headers(
            'put',
            '/api/site/register',
            $payload . ' ',
            'site-123',
            $secret,
            7,
            1700000000,
            $nonce
        );
        $this->assertNotSame(end($headers), end($changedbodyheaders));
    }

    /**
     * Test a bootstrap response stores its key and removes it from returned data.
     * @covers ::accept_acknowledged_response
     * @covers ::get_signing_credential
     * @covers ::persist_signing_credential
     */
    public function test_acknowledged_bootstrap_persists_bound_credential_and_sanitizes_response(): void {
        $secret = 'bootstrap-high-entropy-secret-0123456789';
        $response = $this->invoke_private_static_method('accept_acknowledged_response', [
            'success' => true,
            'data' => [
                'site_uuid' => '01234567-89ab-4cde-8fab-0123456789ab',
                'signing_secret' => $secret,
                'signing_key_version' => 3,
                'acknowledgement_id' => 'ack-bootstrap',
                'acknowledged' => true,
            ],
        ], 201, null, true);

        $this->assertArrayNotHasKey('signing_secret', $response['data']);
        $this->assertSame(
            hash('sha256', 'ack-bootstrap'),
            $response['_moodiy_acknowledgement_fingerprint']
        );
        $this->assertSame(201, $response['_moodiy_http_status']);
        $this->assertSame($secret, get_config('tool_moodiyregistration', api::SIGNING_KEY_CONFIG));
        $this->assertSame('3', (string)get_config(
            'tool_moodiyregistration',
            api::SIGNING_KEY_VERSION_CONFIG
        ));
        $this->assertSame('01234567-89ab-4cde-8fab-0123456789ab', get_config(
            'tool_moodiyregistration',
            api::SIGNING_SITE_UUID_CONFIG
        ));

        $this->assertSame([
            'secret' => $secret,
            'version' => 3,
        ], $this->invoke_private_static_method(
            'get_signing_credential',
            '01234567-89ab-4cde-8fab-0123456789ab'
        ));
        $this->assertNull($this->invoke_private_static_method('get_signing_credential', 'another-site'));
    }

    /**
     * Test a persisted rotation supersedes an older protected pre-seed without allowing downgrade.
     * @covers ::get_signing_credential
     * @covers ::accept_acknowledged_response
     */
    public function test_newer_persisted_rotation_supersedes_preseed_and_rejects_downgrade(): void {
        global $CFG;

        $siteuuid = 'site-key-rotation';
        $preseedsecret = 'preseed-signing-secret-012345678901';
        $rotatedsecret = 'rotated-signing-secret-012345678901';
        $CFG->{api::SIGNING_KEY_CONFIG} = $preseedsecret;
        $CFG->{api::SIGNING_KEY_VERSION_CONFIG} = 1;
        $CFG->moodiysiteregistrationuuid = $siteuuid;
        set_config(api::SIGNING_KEY_CONFIG, $rotatedsecret, 'tool_moodiyregistration');
        set_config(api::SIGNING_KEY_VERSION_CONFIG, 2, 'tool_moodiyregistration');
        set_config(api::SIGNING_SITE_UUID_CONFIG, $siteuuid, 'tool_moodiyregistration');

        $this->assertSame([
            'secret' => $rotatedsecret,
            'version' => 2,
        ], $this->invoke_private_static_method('get_signing_credential', $siteuuid));

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('unexpected signing-key version');
        $this->invoke_private_static_method('accept_acknowledged_response', [
            'success' => true,
            'data' => [
                'acknowledgement_id' => 'ack-downgrade',
                'acknowledged' => true,
                'signing_key_version' => 1,
            ],
        ], 200, $siteuuid, false);
    }

    /**
     * Test removing a registration clears only the credential bound to that UUID.
     * @covers ::forget_signing_credential
     */
    public function test_forget_signing_credential_clears_only_matching_persisted_key(): void {
        $siteuuid = '23456789-abcd-4efa-8bcd-23456789abcd';
        set_config(api::SIGNING_KEY_CONFIG, 'forget-signing-secret-012345678901', 'tool_moodiyregistration');
        set_config(api::SIGNING_KEY_VERSION_CONFIG, 2, 'tool_moodiyregistration');
        set_config(api::SIGNING_SITE_UUID_CONFIG, $siteuuid, 'tool_moodiyregistration');

        api::forget_signing_credential('3456789a-bcde-4fab-8cde-3456789abcde');
        $this->assertNotFalse(get_config('tool_moodiyregistration', api::SIGNING_KEY_CONFIG));

        api::forget_signing_credential($siteuuid);
        $this->assertFalse(get_config('tool_moodiyregistration', api::SIGNING_KEY_CONFIG));
        $this->assertFalse(get_config('tool_moodiyregistration', api::SIGNING_KEY_VERSION_CONFIG));
        $this->assertFalse(get_config('tool_moodiyregistration', api::SIGNING_SITE_UUID_CONFIG));
    }

    /**
     * Test transport success without an authoritative acknowledgement is rejected.
     * @covers ::accept_acknowledged_response
     */
    public function test_success_response_without_acknowledgement_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('Moodiy did not return a registration acknowledgement.');

        $this->invoke_private_static_method('accept_acknowledged_response', [
            'success' => true,
            'data' => [],
        ], 200, 'site-missing-ack', false);
    }

    /**
     * Test acknowledgement fields cannot override a false top-level Core result.
     * @covers ::accept_acknowledged_response
     */
    public function test_false_top_level_success_with_acknowledgement_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('Moodiy did not return a successful registration response.');

        $this->invoke_private_static_method('accept_acknowledged_response', [
            'success' => false,
            'data' => [
                'acknowledgement_id' => 'ack-must-not-override-failure',
                'acknowledged' => true,
                'signing_key_version' => 1,
            ],
        ], 200, 'site-failed-response', false);
    }

    /**
     * Test the attempt binding is optional but accepts Core's exact maximum safe identifier.
     * @covers ::add_registration_attempt_binding
     */
    public function test_registration_attempt_binding_is_optional_and_accepts_safe_maximum(): void {
        global $CFG;

        $params = ['site_name' => 'Attempt binding'];
        $this->assertSame(
            $params,
            $this->invoke_private_static_method('add_registration_attempt_binding', $params)
        );

        $attemptid = 'A' . str_repeat('z', 99);
        $CFG->{api::REGISTRATION_ATTEMPT_ID_CONFIG} = $attemptid;
        $CFG->{api::REGISTRATION_ACTION_CONFIG} = 'server_site_recover';
        $bound = $this->invoke_private_static_method('add_registration_attempt_binding', $params);

        $this->assertSame($attemptid, $bound['registration_attempt_id']);
        $this->assertSame('server_site_recover', $bound['registration_action']);
    }

    /**
     * Test partial, unsafe, overlong, and unknown attempt bindings fail before transport.
     *
     * @dataProvider invalid_registration_attempt_binding_provider
     * @param mixed $attemptid Protected attempt config value, or null when omitted.
     * @param mixed $action Protected action config value, or null when omitted.
     * @covers ::add_registration_attempt_binding
     */
    public function test_invalid_registration_attempt_binding_is_rejected($attemptid, $action): void {
        global $CFG;

        if ($attemptid !== null) {
            $CFG->{api::REGISTRATION_ATTEMPT_ID_CONFIG} = $attemptid;
        }
        if ($action !== null) {
            $CFG->{api::REGISTRATION_ACTION_CONFIG} = $action;
        }

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('Protected registration attempt binding is incomplete or invalid.');
        $this->invoke_private_static_method('add_registration_attempt_binding', []);
    }

    /**
     * Provide invalid protected provisioning-attempt bindings.
     *
     * @return array<string,array{0:mixed,1:mixed}>
     */
    public static function invalid_registration_attempt_binding_provider(): array {
        return [
            'attempt only' => ['attempt-123', null],
            'action only' => [null, 'server_provision_single'],
            'unsafe attempt whitespace' => ['attempt id sentinel', 'server_provision_single'],
            'unsafe first character' => ['-attempt-sentinel', 'server_provision_single'],
            'attempt over 100 chars' => [str_repeat('a', 101), 'server_provision_single'],
            'attempt not a string' => [12345, 'server_provision_single'],
            'unknown action' => ['attempt-123', 'server_provision_cluster'],
        ];
    }

    /**
     * Test a pre-seeded new site uses v2 on its first update and signs the exact raw JSON bytes.
     * @covers ::update_registration
     * @covers ::get_http_client
     */
    public function test_preseeded_first_update_transmits_the_exact_raw_json_body_with_v2(): void {
        global $CFG;

        $siteuuid = 'site-transport-update';
        $secret = 'transport-update-secret-0123456789';
        $attemptid = 'provision.attempt:sentinel-001';
        $action = 'server_provision_single';
        $CFG->{api::SIGNING_KEY_CONFIG} = $secret;
        $CFG->{api::SIGNING_KEY_VERSION_CONFIG} = 4;
        $CFG->moodiysiteregistrationuuid = $siteuuid;
        $CFG->{api::REGISTRATION_ATTEMPT_ID_CONFIG} = $attemptid;
        $CFG->{api::REGISTRATION_ACTION_CONFIG} = $action;
        $capturedheaders = [];
        $capturedbody = '';

        $curl = $this->getMockBuilder(\curl::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setHeader', 'put', 'get_info', 'get_errno'])
            ->getMock();
        $curl->expects($this->once())->method('setHeader')
            ->willReturnCallback(function (array $headers) use (&$capturedheaders): void {
                $capturedheaders = $headers;
            });
        $curl->expects($this->once())->method('put')
            ->with($this->equalTo('https://core.example/api/site/register'), $this->isType('string'))
            ->willReturnCallback(function (
                string $url,
                string $body
            ) use (
                &$capturedbody,
                $siteuuid,
                $secret,
                $attemptid,
                $action
            ): string {
                $capturedbody = $body;

                return json_encode([
                    'success' => true,
                    'raw_response' => 'raw-core-response-sentinel',
                    'data' => [
                        'site_uuid' => $siteuuid,
                        'site_url' => 'https://private-update.example',
                        'admin_email' => 'private-update@example.com',
                        'signing_secret' => $secret,
                        'acknowledged' => true,
                        'acknowledgement_id' => 'ack-update-transport',
                        'signing_key_version' => 4,
                        'registration_attempt_id' => $attemptid,
                        'registration_action' => $action,
                    ],
                ]);
            });
        $curl->method('get_info')->willReturn(['http_code' => 200]);
        $curl->method('get_errno')->willReturn(0);
        $CFG->tool_moodiyregistration_test_curl = $curl;
        $CFG->moodiy_api_url = 'https://core.example';

        $response = api::update_registration((object)['site_uuid' => $siteuuid], [
            'site_name' => 'Transport Test',
        ]);

        $headers = $this->headers_by_name($capturedheaders);
        $decodedbody = json_decode($capturedbody, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($siteuuid, $decodedbody['site_uuid']);
        $this->assertSame($attemptid, $decodedbody['registration_attempt_id']);
        $this->assertSame($action, $decodedbody['registration_action']);
        $this->assertSame((string)$decodedbody['timestamp'], $headers['X-Moodiy-Timestamp']);
        $this->assertSame('/api/site/register', api::REGISTRATION_PATH);
        $this->assertArrayNotHasKey('key', $headers);
        $canonical = "PUT\n/api/site/register\n"
            . hash('sha256', $capturedbody) . "\n"
            . $headers['X-Moodiy-Timestamp'] . "\n"
            . $headers['X-Moodiy-Nonce'] . "\n4";
        $this->assertSame(hash_hmac('sha256', $canonical, $secret), $headers['X-Moodiy-Signature']);
        $encodedresponse = json_encode($response, JSON_THROW_ON_ERROR);
        $this->assertSame(
            hash('sha256', 'ack-update-transport'),
            $response['_moodiy_acknowledgement_fingerprint']
        );
        $this->assertStringNotContainsString('ack-update-transport', $encodedresponse);
        $this->assertStringNotContainsString($siteuuid, $encodedresponse);
        $this->assertStringNotContainsString('private-update@example.com', $encodedresponse);
        $this->assertStringNotContainsString('private-update.example', $encodedresponse);
        $this->assertStringNotContainsString($secret, $encodedresponse);
        $this->assertStringNotContainsString('raw-core-response-sentinel', $encodedresponse);
        $this->assertStringNotContainsString($attemptid, $encodedresponse);
        $this->assertStringNotContainsString($action, $encodedresponse);
    }

    /**
     * Provider of Core refusals and what the plugin reads from them (moodiycloud/moodiy#1355).
     *
     * @return array
     */
    public static function core_refusal_provider(): array {
        $text = [
            'success' => false,
            'message' => 'Site registration update failed',
            'errors' => ['error' => 'Site registration does not exist'],
        ];
        $hold = [
            'success' => false,
            'message' => 'Site registration update failed',
            'errors' => ['error' => 'Site registration is on hold while another change to this site settles. Retry later.'],
        ];

        return [
            'deleted' => [404, $text + ['error_code' => 'site_registration_deleted'], 'site_registration_deleted', true, false],
            'text without a code (older Core)' => [404, $text, '', false, false],
            'hold' => [409, $hold + ['error_code' => 'registration_lifecycle_busy'], 'registration_lifecycle_busy', false, true],
            'text on a hold' => [
                409,
                $text + ['error_code' => 'registration_migration_hold'],
                'registration_migration_hold',
                false,
                true,
            ],
        ];
    }

    /**
     * A refused update carries Core's top-level error_code; only the deleted code may erase.
     * @covers ::update_registration
     * @dataProvider core_refusal_provider
     * @param int $status HTTP status Core answers with.
     * @param array $body Core's response body.
     * @param string $code Expected error_code read by the plugin.
     * @param bool $deleted Whether the refusal says the registration was deleted.
     * @param bool $held Whether the refusal is a hold.
     */
    public function test_update_refusal_carries_core_error_code(
        int $status,
        array $body,
        string $code,
        bool $deleted,
        bool $held
    ): void {
        global $CFG;

        $siteuuid = 'site-transport-refusal';
        $CFG->{api::SIGNING_KEY_CONFIG} = 'transport-refusal-secret-0123456789';
        $CFG->{api::SIGNING_KEY_VERSION_CONFIG} = 4;
        $CFG->moodiysiteregistrationuuid = $siteuuid;
        $curl = $this->getMockBuilder(\curl::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setHeader', 'put', 'get_info', 'get_errno'])
            ->getMock();
        $curl->method('put')->willReturn(json_encode($body));
        $curl->method('get_info')->willReturn(['http_code' => $status]);
        $curl->method('get_errno')->willReturn(0);
        $CFG->tool_moodiyregistration_test_curl = $curl;
        $CFG->moodiy_api_url = 'https://core.example';

        try {
            api::update_registration((object)['site_uuid' => $siteuuid], ['site_name' => 'Refusal Test']);
            $this->fail('A refused update must throw.');
        } catch (remote_registration_exception $e) {
            $this->assertSame($status, $e->get_http_status());
            $this->assertSame($code, $e->get_core_error_code());
            $this->assertSame($deleted, $e->is_registration_deleted());
            $this->assertSame($held, $e->is_held());
            $this->assertStringContainsString($body['errors']['error'], $e->getMessage());
        }
    }

    /**
     * Test the one-time UUID bootstrap retains the legacy signature bytes and stores the v2 key.
     * @covers ::update_registration
     * @covers ::encode_legacy_signature_payload
     */
    public function test_legacy_bootstrap_is_rolling_compatible_and_persists_returned_v2_key(): void {
        global $CFG;

        $siteuuid = '12345678-9abc-4def-8abc-123456789abc';
        $secret = 'minted-v2-secret-01234567890123456789';
        $capturedheaders = [];
        $capturedbody = '';
        $curl = $this->getMockBuilder(\curl::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setHeader', 'put', 'get_info', 'get_errno'])
            ->getMock();
        $curl->expects($this->once())->method('setHeader')
            ->willReturnCallback(function (array $headers) use (&$capturedheaders): void {
                $capturedheaders = $headers;
            });
        $curl->expects($this->once())->method('put')
            ->willReturnCallback(function (string $url, string $body) use (&$capturedbody, $secret): string {
                $capturedbody = $body;

                return json_encode([
                    'success' => true,
                    'data' => [
                        'signing_secret' => $secret,
                        'signing_key_version' => 1,
                        'acknowledged' => true,
                        'acknowledgement_id' => 'ack-legacy-bootstrap',
                    ],
                ]);
            });
        $curl->method('get_info')->willReturn(['http_code' => 200]);
        $curl->method('get_errno')->willReturn(0);
        $CFG->tool_moodiyregistration_test_curl = $curl;
        $CFG->moodiy_api_url = 'https://core.example';

        $response = api::update_registration((object)['site_uuid' => $siteuuid], [
            'description' => null,
            'site_name' => 'Bootstrap Test',
        ]);

        $headers = $this->headers_by_name($capturedheaders);
        $middlewarepayload = json_decode($capturedbody, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('registration_attempt_id', $middlewarepayload);
        $this->assertArrayNotHasKey('registration_action', $middlewarepayload);
        $middlewarepayload['timestamp'] = (int)$middlewarepayload['timestamp'];
        ksort($middlewarepayload);
        $middlewarepayload = array_map(static fn($value) => $value ?? '', $middlewarepayload);
        $legacybytes = json_encode($middlewarepayload, JSON_THROW_ON_ERROR);
        $this->assertSame(hash_hmac('sha256', $legacybytes, $siteuuid), $headers['key']);
        $this->assertArrayNotHasKey('X-Moodiy-Signature', $headers);
        $this->assertArrayNotHasKey('signing_secret', $response['data']);
        $this->assertStringNotContainsString('ack-legacy-bootstrap', json_encode($response, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($siteuuid, json_encode($response, JSON_THROW_ON_ERROR));
        $this->assertSame($secret, get_config('tool_moodiyregistration', api::SIGNING_KEY_CONFIG));
        $this->assertSame('1', (string)get_config(
            'tool_moodiyregistration',
            api::SIGNING_KEY_VERSION_CONFIG
        ));
    }

    /**
     * Test delete passes the signed raw JSON body through Moodle curl's POSTFIELDS option.
     * @covers ::unregister_site
     * @covers ::get_http_client
     */
    public function test_delete_transmits_the_exact_raw_json_body_covered_by_v2_signature(): void {
        global $CFG;

        $siteuuid = 'site-transport-delete';
        $secret = 'transport-delete-secret-0123456789';
        set_config(api::SIGNING_KEY_CONFIG, $secret, 'tool_moodiyregistration');
        set_config(api::SIGNING_KEY_VERSION_CONFIG, 9, 'tool_moodiyregistration');
        set_config(api::SIGNING_SITE_UUID_CONFIG, $siteuuid, 'tool_moodiyregistration');
        $capturedheaders = [];
        $capturedbody = '';

        $curl = $this->getMockBuilder(\curl::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setHeader', 'delete', 'get_info', 'get_errno'])
            ->getMock();
        $curl->expects($this->once())->method('setHeader')
            ->willReturnCallback(function (array $headers) use (&$capturedheaders): void {
                $capturedheaders = $headers;
            });
        $curl->expects($this->once())->method('delete')
            ->willReturnCallback(function (
                string $url,
                array $params,
                array $options
            ) use (
                &$capturedbody,
                $siteuuid
            ): string {
                $this->assertSame('https://core.example/api/site/register', $url);
                $this->assertSame([], $params);
                $this->assertArrayHasKey('CURLOPT_POSTFIELDS', $options);
                $this->assertIsString($options['CURLOPT_POSTFIELDS']);
                $capturedbody = $options['CURLOPT_POSTFIELDS'];

                return json_encode([
                    'success' => true,
                    'data' => [
                        'site_uuid' => $siteuuid,
                        'acknowledged' => true,
                        'acknowledgement_id' => 'ack-delete-transport',
                        'signing_key_version' => 9,
                    ],
                ]);
            });
        $curl->method('get_info')->willReturn(['http_code' => 204]);
        $curl->method('get_errno')->willReturn(0);
        $CFG->tool_moodiyregistration_test_curl = $curl;
        $CFG->moodiy_api_url = 'https://core.example';

        $response = api::unregister_site((object)['site_uuid' => $siteuuid]);

        $headers = $this->headers_by_name($capturedheaders);
        $canonical = "DELETE\n/api/site/register\n"
            . hash('sha256', $capturedbody) . "\n"
            . $headers['X-Moodiy-Timestamp'] . "\n"
            . $headers['X-Moodiy-Nonce'] . "\n9";
        $this->assertSame(hash_hmac('sha256', $canonical, $secret), $headers['X-Moodiy-Signature']);
        $this->assertSame(
            hash('sha256', 'ack-delete-transport'),
            $response['_moodiy_acknowledgement_fingerprint']
        );
        $this->assertStringNotContainsString('ack-delete-transport', json_encode($response, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($siteuuid, json_encode($response, JSON_THROW_ON_ERROR));
    }

    /**
     * Invoke a private static API helper for focused contract tests.
     *
     * @param string $method Method name.
     * @param mixed ...$arguments Method arguments.
     * @return mixed
     */
    private function invoke_private_static_method(string $method, ...$arguments) {
        $reflection = new \ReflectionMethod(api::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $arguments);
    }

    /**
     * Convert curl header lines into a name/value map.
     *
     * @param string[] $headers Header lines.
     * @return array<string,string>
     */
    private function headers_by_name(array $headers): array {
        $mapped = [];
        foreach ($headers as $header) {
            [$name, $value] = array_pad(explode(':', $header, 2), 2, '');
            $mapped[$name] = trim($value);
        }

        return $mapped;
    }
}
