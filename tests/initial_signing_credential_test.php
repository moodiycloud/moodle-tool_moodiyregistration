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
 * Initial credential installation regression coverage using the real Moodle DB and locks.
 *
 * @package    tool_moodiyregistration
 * @copyright  2026 MoodiyCloud <support@moodiycloud.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \tool_moodiyregistration\initial_signing_credential
 */
final class initial_signing_credential_test extends \advanced_testcase {
    /**
     * @var array Exact private input with synthetic test credential.
     */
    private array $input;

    /**
     * Set up an internal hosted tenant.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest(true);
        $CFG->forced_plugin_settings = ['auth_maintenance' => []];
        $CFG->wwwroot = 'https://initial.example.com';
        $CFG->moodiysiteregistrationuuid = '11111111-1111-4111-8111-111111111111';
        unset($CFG->{api::SIGNING_KEY_CONFIG}, $CFG->{api::SIGNING_KEY_VERSION_CONFIG});
        $this->input = [
            'domain' => 'initial.example.com',
            'username' => $CFG->dbname,
            'site_uuid' => $CFG->moodiysiteregistrationuuid,
            'registration_signing_secret' => str_repeat('s', 64),
            'registration_signing_key_version' => 1,
        ];
    }

    /**
     * Initial delivery is durable and exact replay preserves it.
     */
    public function test_installs_and_replays_exact_credential(): void {
        initial_signing_credential::install($this->input);
        initial_signing_credential::install($this->input);
        $this->assertSame(
            $this->input['registration_signing_secret'],
            get_config('tool_moodiyregistration', api::SIGNING_KEY_CONFIG)
        );
        $this->assertSame('1', get_config('tool_moodiyregistration', api::SIGNING_KEY_VERSION_CONFIG));
        $this->assertSame($this->input['site_uuid'], get_config('tool_moodiyregistration', api::SIGNING_SITE_UUID_CONFIG));
    }

    /**
     * Input cannot select another runtime or inject unrecognised material.
     * @dataProvider invalid_input_provider
     * @param string $field Input field.
     * @param mixed $value Invalid value.
     */
    public function test_rejects_invalid_input_before_any_write(string $field, $value): void {
        $input = $this->input;
        $input[$field] = $value;
        try {
            initial_signing_credential::install($input);
            $this->fail('Invalid identity accepted.');
        } catch (\invalid_parameter_exception $exception) {
            $this->assertStringNotContainsString(str_repeat('s', 64), $exception->getMessage());
            $this->assertFalse(get_config('tool_moodiyregistration', api::SIGNING_KEY_CONFIG));
        }
    }

    /**
     * Provide invalid shape and identity cases.
     * @return array
     */
    public static function invalid_input_provider(): array {
        return [
            ['domain', 'other.example.com'], ['username', 'otherdatabase'],
            ['site_uuid', '22222222-2222-4222-8222-222222222222'],
            ['registration_signing_secret', str_repeat('s', 63)],
            ['registration_signing_secret', [str_repeat('s', 64)]],
            ['registration_signing_key_version', '1'], ['registration_signing_key_version', 0],
            ['registration_signing_key_version', 1.0], ['extra', 'private'],
        ];
    }

    /**
     * No rotation, downgrade, foreign UUID or incomplete key may be overwritten.
     * @dataProvider conflict_provider
     * @param string $config Conflicting stored field.
     * @param mixed $value Existing value.
     */
    public function test_preserves_conflicting_stored_credentials(string $config, $value): void {
        initial_signing_credential::install($this->input);
        set_config($config, $value, 'tool_moodiyregistration');
        $before = get_config('tool_moodiyregistration');
        try {
            initial_signing_credential::install($this->input);
            $this->fail('Conflicting credential accepted.');
        } catch (\invalid_parameter_exception $exception) {
            $this->assertEquals($before, get_config('tool_moodiyregistration'));
        }
    }

    /**
     * Provide conflicting credential cases.
     * @return array
     */
    public static function conflict_provider(): array {
        return [
            [api::SIGNING_KEY_CONFIG, str_repeat('x', 64)],
            [api::SIGNING_KEY_VERSION_CONFIG, 2], [api::SIGNING_KEY_VERSION_CONFIG, 0],
            [api::SIGNING_SITE_UUID_CONFIG, '22222222-2222-4222-8222-222222222222'],
            [api::SIGNING_KEY_CONFIG, null], [api::SIGNING_KEY_VERSION_CONFIG, null],
        ];
    }

    /**
     * A conflicting static credential cannot be hidden by a valid DB credential.
     */
    public function test_static_credential_conflict_is_not_overwritten(): void {
        global $CFG;
        $CFG->{api::SIGNING_KEY_CONFIG} = str_repeat('x', 64);
        $CFG->{api::SIGNING_KEY_VERSION_CONFIG} = 2;
        $this->expectException(\invalid_parameter_exception::class);
        initial_signing_credential::install($this->input);
    }

    /**
     * A static partial credential also fails closed.
     */
    public function test_static_partial_credential_is_not_overwritten(): void {
        global $CFG;
        $CFG->{api::SIGNING_KEY_VERSION_CONFIG} = 1;
        $this->expectException(\invalid_parameter_exception::class);
        initial_signing_credential::install($this->input);
    }

    /**
     * The next real API update uses the installed key, with no legacy bootstrap header.
     */
    public function test_first_remote_update_is_signed_with_the_installed_key(): void {
        global $CFG;
        initial_signing_credential::install($this->input);
        $headers = [];
        $body = '';
        $curl = $this->getMockBuilder(\curl::class)->disableOriginalConstructor()
            ->onlyMethods(['setHeader', 'put', 'get_info', 'get_errno'])->getMock();
        $curl->expects($this->once())->method('setHeader')->willReturnCallback(function (array $values) use (&$headers): void {
            foreach ($values as $value) {
                [$name, $content] = explode(': ', $value, 2);
                $headers[$name] = $content;
            }
        });
        $curl->expects($this->once())->method('put')->with('https://core.example/api/site/register', $this->isType('string'))
            ->willReturnCallback(function (string $url, string $payload) use (&$body): string {
                $body = $payload;
                return json_encode(['success' => true, 'data' => [
                    'acknowledged' => true, 'acknowledgement_id' => 'initial-key-ack', 'signing_key_version' => 1,
                ]], JSON_THROW_ON_ERROR);
            });
        $curl->method('get_info')->willReturn(['http_code' => 200]);
        $curl->method('get_errno')->willReturn(0);
        $CFG->tool_moodiyregistration_test_curl = $curl;
        $CFG->moodiy_api_url = 'https://core.example';
        $response = api::update_registration(
            (object)['site_uuid' => $this->input['site_uuid']],
            ['site_name' => 'Initial delivery']
        );
        $canonical = "PUT\n/api/site/register\n" . hash('sha256', $body) . "\n"
            . $headers['X-Moodiy-Timestamp'] . "\n" . $headers['X-Moodiy-Nonce'] . "\n1";
        $this->assertSame('2', $headers['X-Moodiy-Signature-Version']);
        $this->assertSame(
            hash_hmac('sha256', $canonical, $this->input['registration_signing_secret']),
            $headers['X-Moodiy-Signature']
        );
        $this->assertArrayNotHasKey('key', $headers);
        $this->assertStringNotContainsString($this->input['registration_signing_secret'], $body . json_encode($response));
        $this->assertTrue($response['_moodiy_acknowledged']);
    }

    /**
     * Public or customer-managed sites cannot receive an internal initial credential.
     */
    public function test_external_site_is_refused(): void {
        global $CFG;
        unset($CFG->forced_plugin_settings);
        $this->expectException(\invalid_parameter_exception::class);
        initial_signing_credential::install($this->input);
    }

    /**
     * Initial installation cannot replace a registration row bound to another site.
     */
    public function test_foreign_local_registration_is_preserved(): void {
        global $DB;
        $DB->insert_record('tool_moodiyregistration', (object)[
            'site_uuid' => '22222222-2222-4222-8222-222222222222', 'site_url' => 'https://foreign.example.com',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->expectException(\invalid_parameter_exception::class);
        initial_signing_credential::install($this->input);
    }

    /**
     * Installation alone does not fabricate acknowledgement after Core refuses.
     */
    public function test_remote_refusal_keeps_key_for_a_later_signed_retry(): void {
        global $CFG;
        initial_signing_credential::install($this->input);
        $wrapper = $this->createMock(api_wrapper::class);
        $wrapper->method('update_registration')->willThrowException(new \RuntimeException('private server failure'));
        $CFG->tool_moodiyregistration_test_api_wrapper = $wrapper;
        $result = registration::repair_internal_site_registration($this->input['site_uuid']);
        $this->assertDebuggingCalled();
        $this->assertSame(1, cli_registration_result::exit_code($result));
        $this->assertStringNotContainsString('private server failure', cli_registration_result::encode($result));
        initial_signing_credential::install($this->input);
        $this->assertSame(
            $this->input['registration_signing_secret'],
            get_config('tool_moodiyregistration', api::SIGNING_KEY_CONFIG)
        );
    }
}
