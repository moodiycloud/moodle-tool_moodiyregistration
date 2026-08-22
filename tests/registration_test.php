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
 * Unit tests for Moodiy Registration.
 *
 * @package     tool_moodiyregistration
 * @category    test
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \tool_moodiyregistration\registration
 */

namespace tool_moodiyregistration;

use tool_moodiyregistration\registration;
use tool_moodiyregistration\api;

/**
 * Unit tests for registration functionality.
 */
final class registration_test extends \advanced_testcase {
    /**
     * @var \stdClass Admin user for tests.
     */
    public $admin;
    /**
     * Set up tests.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        // Create an admin user for tests that require permissions.
        $uniquename = 'testadmin_' . uniqid();
        $this->admin = $this->getDataGenerator()->create_user([
            'username' => $uniquename,
            'email' => $uniquename . '@example.com',
            'country' => 'AU',
        ]);
        $this->setAdminUser($this->admin);

        // Set a test API URL.
        set_config('apiurl', 'https://test-api.moodiycloud.com', 'tool_moodiyregistration');
    }


    /**
     * A pasted screenshot in the Moodle site summary must not cost the site its
     * registration. Core rejects a description over 60,000 bytes or containing an
     * embedded data: URI image, and that refusal is fatal, observed on production
     * at 727 KB, 287 KB and 185 KB, each dominated by one base64 PNG.
     * @covers ::sanitize_description
     */
    public function test_description_with_embedded_data_uri_image_is_stripped(): void {
        $method = new \ReflectionMethod(registration::class, 'sanitize_description');
        $method->setAccessible(true);

        $description = '<p><img src="data:image/png;base64,' . str_repeat('A', 200000) . '" alt="logo"></p>'
            . '<p>Real prose that must survive.</p>';

        $clean = $method->invoke(null, $description);

        $this->assertStringContainsString('Real prose that must survive.', $clean);
        $this->assertStringNotContainsString('data:image/png', $clean);
        $this->assertLessThanOrEqual(registration::DESCRIPTION_MAX_BYTES, strlen($clean));
    }

    /**
     * An oversized description is cut on a character boundary: a raw substr can
     * split a multibyte sequence and make the whole payload fail to JSON-encode.
     * @covers ::sanitize_description
     */
    public function test_oversized_description_is_truncated_on_a_character_boundary(): void {
        $method = new \ReflectionMethod(registration::class, 'sanitize_description');
        $method->setAccessible(true);

        // Two-byte characters: an odd byte cap lands mid-character without mb_strcut.
        $clean = $method->invoke(null, str_repeat('é', registration::DESCRIPTION_MAX_BYTES));

        $this->assertLessThanOrEqual(registration::DESCRIPTION_MAX_BYTES, strlen($clean));
        $this->assertTrue(mb_check_encoding($clean, 'UTF-8'), 'truncation must not split a multibyte character');
        $this->assertNotFalse(json_encode(['description' => $clean]));
    }

    /**
     * If a malformed tag survives stripping, drop the description rather than send
     * something Core will refuse: losing optional metadata beats never registering.
     * @covers ::sanitize_description
     */
    public function test_a_description_core_would_still_refuse_is_dropped(): void {
        $method = new \ReflectionMethod(registration::class, 'sanitize_description');
        $method->setAccessible(true);

        // No closing bracket, so the strip pattern cannot match the tag.
        $this->assertSame('', $method->invoke(null, '<img alt="x" src="data:image/png;base64,AAAA'));
    }

    /**
     * An ordinary description must pass through untouched.
     * @covers ::sanitize_description
     */
    public function test_an_ordinary_description_is_unchanged(): void {
        $method = new \ReflectionMethod(registration::class, 'sanitize_description');
        $method->setAccessible(true);

        $description = '<p>A normal site summary with an <img src="https://example.com/logo.png"> image.</p>';

        $this->assertSame($description, $method->invoke(null, $description));
        $this->assertNull($method->invoke(null, null));
        $this->assertSame('', $method->invoke(null, ''));
    }

    /**
     * Test site registration functionality.
     * @covers ::register
     */
    public function test_site_registration(): void {
        global $DB, $CFG;

        // Check that site is not registered initially.
        $this->assertFalse(registration::is_registered());

        // Create test registration data.
        $data = new \stdClass();
        $data->site_name = 'Test Moodle Site';
        $data->description = 'Test site description';
        $data->admin_email = 'admin@example.com';
        $data->country_code = 'AU';
        $data->language = 'en';
        $data->privacy = 'notdisplayed';
        $data->organisation_type = 'university';
        $data->policyagreed = 1;
        $data->site_url = $CFG->wwwroot;

        // Mock the verification key.
        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, 'tool_moodiyregistration', 'registration');
        $verificationkey = md5($CFG->wwwroot . microtime(true));
        $cache->set('verificationkey', $verificationkey);

        // Create a mock for the api class to prevent actual API calls.
        $apiwrapper = $this->createMock(\tool_moodiyregistration\api_wrapper::class);

        // Configure the mock.
        $apiwrapper->method('moodiy_registration')
            ->willReturn([
                'success' => true,
                'data' => [
                    'id' => 12345,
                    'site_uuid' => 'test-uuid-123456789',
                ],
            ]);

        // Set the mock for tests.
        $CFG->tool_moodiyregistration_test_api_wrapper = $apiwrapper;

        // Save site info.
        registration::save_site_info($data);

        // Perform the registration.
        $response = registration::register($data, '/admin/tool/moodiyregistration/index.php');

        // Mocked response to be returned.
        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
        $this->assertEquals(12345, $response['data']['id']);

        // Check that site is now registered.
        $record = $DB->get_record('tool_moodiyregistration', []);
        $this->assertNotEmpty($record);
        $this->assertEquals('test-uuid-123456789', $record->site_uuid);
        $this->assertEquals($CFG->wwwroot, $record->site_url);
        $this->assertNotSame('', (string) get_config('tool_moodiyregistration', registration::LAST_SUCCESSFUL_UPDATE_HASH));
    }

    /**
     * Test site unregistration functionality.
     * @covers ::unregister
     */
    public function test_site_unregistration(): void {
        global $DB, $CFG;

        // Insert a test record to simulate a registered site.
        $record = new \stdClass();
        $record->site_uuid = 'test-uuid-123456789';
        $record->site_url = 'https://example.moodle.org';
        $record->timecreated = time();
        $record->timemodified = time();
        $DB->insert_record('tool_moodiyregistration', $record);

        // Verify site is registered.
        $this->assertTrue(registration::is_registered());

        // Create a mock for the api class.
        $apiwrapper = $this->createMock(\tool_moodiyregistration\api_wrapper::class);
        $apiwrapper->expects($this->once())->method('unregister_site')->willReturn([
            'success' => true,
            'message' => 'Site unregistered successfully',
        ]);

        // Set the mock for tests.
        $CFG->tool_moodiyregistration_test_api_wrapper = $apiwrapper;

        // Unregister the site.
        $result = registration::unregister();
        // Check the result.
        $this->assertTrue($result);

        // Verify site is no longer registered.
        $this->assertFalse(registration::is_registered());
        $this->assertEquals(0, $DB->count_records('tool_moodiyregistration'));
    }
    /**
     * Test updating site registration.
     * @covers ::update_manual
     */
    public function test_update_manual(): void {
        global $DB, $CFG;

        // Insert a test record to simulate a registered site.
        $record = new \stdClass();
        $record->site_uuid = 'test-uuid-123456789';
        $record->site_url = 'https://example.moodle.org';
        $record->timecreated = time();
        $record->timemodified = time() - 86400; // 1 day ago.
        $recordid = $DB->insert_record('tool_moodiyregistration', $record);

        // Create updated registration data.
        $data = new \stdClass();
        $data->site_name = 'Updated Test Moodle Site';
        $data->description = 'Updated site description';
        $data->admin_email = 'updated_admin@example.com';
        $data->country_code = 'US';
        $data->language = 'en';
        $data->privacy = 'notdisplayed';
        $data->organisation_type = 'university';
        $data->policyagreed = 1;

        // Create a mock for the api class.
        $apiwrapper = $this->createMock(\tool_moodiyregistration\api_wrapper::class);
        $apiwrapper->method('update_registration')->willReturn($this->acknowledged_response('ack-recreate'));

        // Set the mock for tests.
        $CFG->tool_moodiyregistration_test_api_wrapper = $apiwrapper;

        // Update the registration.
        $result = registration::update_manual($data);

        // Check the result.
        $this->assertTrue($result);
        $this->assertNotSame('', (string) get_config('tool_moodiyregistration', registration::LAST_SUCCESSFUL_UPDATE_HASH));

        // Verify the record was updated.
        $updated = $DB->get_record('tool_moodiyregistration', ['id' => $recordid]);
        $this->assertGreaterThan($record->timemodified, $updated->timemodified);
    }

    /**
     * Test updating site registration.
     * @covers ::update_registration
     */
    public function test_update_registration(): void {
        global $DB, $CFG;

        // Insert a test record to simulate a registered site.
        $record = new \stdClass();
        $record->site_uuid = 'test-uuid-123456789';
        $record->site_url = 'https://example.moodle.org';
        $record->timecreated = time();
        $record->timemodified = time() - 86400; // 1 day ago.
        $recordid = $DB->insert_record('tool_moodiyregistration', $record);

        // Create a mock for the api class.
        $apiwrapper = $this->createMock(\tool_moodiyregistration\api_wrapper::class);
        $apiwrapper->method('update_registration')->willReturn($this->acknowledged_response('ack-recreate'));

        // Set the mock for tests.
        $CFG->tool_moodiyregistration_test_api_wrapper = $apiwrapper;

        // Update the registration.
        $result = registration::update_registration();

        // Verify the record was updated.
        $updated = $DB->get_record('tool_moodiyregistration', ['id' => $recordid]);
        $this->assertGreaterThanOrEqual($record->timemodified, $updated->timemodified);
    }

    /**
     * Test duplicate automatic registration updates are skipped when nothing changed.
     * @covers ::update_registration
     */
    public function test_update_registration_skips_unchanged_payload(): void {
        global $DB, $CFG;

        $record = (object) [
            'site_uuid' => 'test-uuid-duplicate-skip',
            'site_url' => 'https://example.moodle.org',
            'timecreated' => time(),
            'timemodified' => time() - 86400,
        ];
        $recordid = $DB->insert_record('tool_moodiyregistration', $record);

        $apiwrapper = $this->createMock(\tool_moodiyregistration\api_wrapper::class);
        $apiwrapper->expects($this->once())
            ->method('update_registration')
            ->willReturn([
                'success' => true,
                'message' => 'Site registration updated successfully',
            ]);
        $CFG->tool_moodiyregistration_test_api_wrapper = $apiwrapper;

        registration::update_registration();
        $firstupdate = $DB->get_record('tool_moodiyregistration', ['id' => $recordid]);
        $savedhash = get_config('tool_moodiyregistration', registration::LAST_SUCCESSFUL_UPDATE_HASH);
        $this->assertNotSame('', (string) $savedhash);

        registration::update_registration();
        $secondupdate = $DB->get_record('tool_moodiyregistration', ['id' => $recordid]);

        $this->assertEquals($firstupdate->timemodified, $secondupdate->timemodified);
        $this->assertSame($savedhash, get_config('tool_moodiyregistration', registration::LAST_SUCCESSFUL_UPDATE_HASH));
    }

    /**
     * Test automatic update payload hashes ignore volatile timestamp and UUID fields.
     * @covers ::build_automatic_update_payload_hash
     */
    public function test_build_automatic_update_payload_hash_ignores_timestamp_and_site_uuid(): void {
        $siteinfo = [
            'site_name' => 'Stable Moodle Site',
            'language' => 'en',
            'timestamp' => 1700000000,
            'site_uuid' => 'uuid-one',
        ];
        $changedvolatilefields = [
            'site_name' => 'Stable Moodle Site',
            'language' => 'en',
            'timestamp' => 1800000000,
            'site_uuid' => 'uuid-two',
        ];

        $this->assertSame(
            $this->invoke_private_static_method('build_automatic_update_payload_hash', $siteinfo),
            $this->invoke_private_static_method('build_automatic_update_payload_hash', $changedvolatilefields)
        );
    }

    /**
     * Test update requests are de-duplicated while an equivalent ad-hoc task is already queued.
     * @covers ::queue_update_request_task
     */
    public function test_queue_update_request_task_deduplicates_by_site_uuid(): void {
        $this->assertTrue(registration::queue_update_request_task('queued-uuid-123'));
        $this->assertFalse(registration::queue_update_request_task('queued-uuid-123'));
        $this->assertTrue(registration::queue_update_request_task('queued-uuid-456'));

        $tasks = \core\task\manager::get_adhoc_tasks(\tool_moodiyregistration\task\process_update_request::class);

        $this->assertCount(2, $tasks);
        $customdata = array_map(
            static fn($task) => $task->get_custom_data()->site_uuid ?? null,
            $tasks
        );
        sort($customdata);

        $this->assertSame(['queued-uuid-123', 'queued-uuid-456'], $customdata);
    }

    /**
     * Test empty update request UUIDs are rejected before task queueing.
     * @covers ::queue_update_request_task
     */
    public function test_queue_update_request_task_rejects_empty_site_uuid(): void {
        $this->assertFalse(registration::queue_update_request_task(''));
        $this->assertFalse(registration::queue_update_request_task('   '));

        $tasks = \core\task\manager::get_adhoc_tasks(\tool_moodiyregistration\task\process_update_request::class);
        $this->assertCount(0, $tasks);
    }

    /**
     * Test forced update requests still call Moodiy even when the automatic payload hash is unchanged.
     * @covers ::update_registration
     */
    public function test_update_registration_force_bypasses_unchanged_payload_skip(): void {
        global $DB, $CFG;

        $record = (object) [
            'site_uuid' => 'test-uuid-force-refresh',
            'site_url' => 'https://example.moodle.org',
            'timecreated' => time(),
            'timemodified' => time() - 86400,
        ];
        $recordid = $DB->insert_record('tool_moodiyregistration', $record);

        $apiwrapper = $this->createMock(\tool_moodiyregistration\api_wrapper::class);
        $apiwrapper->expects($this->exactly(2))
            ->method('update_registration')
            ->willReturn([
                'success' => true,
                'message' => 'Site registration updated successfully',
            ]);
        $CFG->tool_moodiyregistration_test_api_wrapper = $apiwrapper;

        registration::update_registration();
        $firstupdate = $DB->get_record('tool_moodiyregistration', ['id' => $recordid]);

        sleep(1);
        registration::update_registration(true);
        $secondupdate = $DB->get_record('tool_moodiyregistration', ['id' => $recordid]);

        $this->assertGreaterThan($firstupdate->timemodified, $secondupdate->timemodified);
    }

    /**
     * Test getting site information.
     * @covers ::get_site_info
     */
    public function test_get_site_info(): void {
        global $CFG;
        // Get site info.
        $siteinfo = registration::get_site_info();

        // Check that essential fields are present.
        $this->assertArrayHasKey('site_name', $siteinfo);
        $this->assertArrayHasKey('site_url', $siteinfo);
        $this->assertArrayHasKey('moodlerelease', $siteinfo);
        $this->assertArrayHasKey('language', $siteinfo);
        $this->assertArrayHasKey('country_code', $siteinfo);

        // Verify site URL is correct.
        $this->assertEquals($CFG->wwwroot, $siteinfo['site_url']);

        $siteinfo = registration::get_site_info([
            'site_name' => 'Test site',
            'description' => 'Test description',
            'admin_email' => 'admin@example.com',
            'country_code' => 'US',
            'language' => 'en',
        ]);
        // Check that the provided data is included in the site info.
        $this->assertEquals('Test site', $siteinfo['site_name']);
        $this->assertEquals('Test description', $siteinfo['description']);
        $this->assertEquals('admin@example.com', $siteinfo['admin_email']);
        $this->assertEquals('US', $siteinfo['country_code']);
        $this->assertEquals('en', $siteinfo['language']);
    }

    /**
     * Test getting site metadata.
     * @covers ::get_site_metadata
     */
    public function test_get_site_metadata(): void {
        global $CFG;
        // Create some courses with end dates.
        $generator = $this->getDataGenerator();
        $generator->create_course(['enddate' => time() + 1000]);
        $generator->create_course(['enddate' => time() + 1000]);

        $generator->create_course(); // Course with no end date.
        $siteinfo = registration::get_site_metadata();

        $this->assertEquals(3, $siteinfo['courses']);
        $this->assertEquals($CFG->dbtype, $siteinfo['dbtype']);
        $this->assertEquals('manual', $siteinfo['primaryauthtype']);
        $this->assertEquals(1, $siteinfo['coursesnodates']);
    }

    /**
     * Test can_unregister method.
     * @covers ::can_unregister
     */
    public function test_can_unregister_when_mobile_disabled(): void {
        // Simulate mobile plugin disabled.
        set_config('enabled', 0, 'tool_moodiymobile');
        $this->assertTrue(registration::can_unregister());
    }

    /**
     * Test can_unregister method when mobile plugin is enabled.
     * @covers ::can_unregister
     */
    public function test_can_unregister_when_mobile_enabled(): void {
        // Simulate mobile plugin enabled.
        set_config('enabled', 1, 'tool_moodiymobile');
        $this->assertFalse(registration::can_unregister());
    }

    /**
     * Test updating site registration when registration does not exist on external site.
     * @covers ::update_registration
     */
    public function test_update_registration_for_deleted_registration(): void {
        global $DB, $CFG;

        // Insert a test record to simulate a registered site.
        $record = new \stdClass();
        $record->site_uuid = 'test-uuid-123456789';
        $record->site_url = 'https://example.moodle.org';
        $record->timecreated = time();
        $record->timemodified = time() - 86400; // 1 day ago.
        $recordid = $DB->insert_record('tool_moodiyregistration', $record);

        // Create a mock for the api class to simulate "registration does not exist".
        $apiwrapper = $this->createMock(\tool_moodiyregistration\api_wrapper::class);
        $apiwrapper->method('update_registration')->will(
            $this->throwException(new \moodle_exception(\tool_moodiyregistration\api::ERROR_REGISTRATION_NONEXISTENT))
        );

        // Set the mock for tests.
        $CFG->tool_moodiyregistration_test_api_wrapper = $apiwrapper;
        set_config(registration::LAST_SUCCESSFUL_UPDATE_HASH, 'existing-hash', 'tool_moodiyregistration');

        // Update the registration.
        $result = registration::update_registration();

        // The result should be false (update failed).
        $this->assertFalse($result);
        $this->assertEquals(0, get_config('tool_moodiymobile', 'enabled'));

        // The local registration should be deleted for data integrity.
        $deleted = !$DB->record_exists('tool_moodiyregistration', ['id' => $recordid]);
        $this->assertTrue($deleted, 'Local registration should be deleted if not found on external site.');
        $this->assertFalse(get_config('tool_moodiyregistration', registration::LAST_SUCCESSFUL_UPDATE_HASH));
    }

    /**
     * Test site unregistration when registration does not exist on external site.
     * @covers ::unregister
     */
    public function test_site_unregistration_for_deleted_registration(): void {
        global $DB, $CFG;

        // Insert a test record to simulate a registered site.
        $record = new \stdClass();
        $record->site_uuid = 'test-uuid-123456789';
        $record->site_url = 'https://example.moodle.org';
        $record->timecreated = time();
        $record->timemodified = time();
        $DB->insert_record('tool_moodiyregistration', $record);

        // Verify site is registered.
        $this->assertTrue(registration::is_registered());

        // Create a mock for the api class.
        $apiwrapper = $this->createMock(\tool_moodiyregistration\api_wrapper::class);
        $apiwrapper->expects($this->once())->method('unregister_site')->will(
            $this->throwException(new \moodle_exception(\tool_moodiyregistration\api::ERROR_REGISTRATION_NONEXISTENT))
        );

        // Set the mock for tests.
        $CFG->tool_moodiyregistration_test_api_wrapper = $apiwrapper;

        // Unregister the site.
        $result = registration::unregister();
        // Check the result.
        $this->assertFalse($result);
        $this->assertEquals(0, get_config('tool_moodiymobile', 'enabled'));

        // Verify site is no longer registered.
        $this->assertFalse(registration::is_registered());
        $this->assertEquals(0, $DB->count_records('tool_moodiyregistration'));
    }

    /**
     * Test repairing an internal site registration recreates the local record.
     * @covers ::repair_internal_site_registration
     */
    public function test_repair_internal_site_registration_recreates_local_record(): void {
        global $DB, $CFG;

        $this->mark_as_internal_site();

        $oldrecord = (object) [
            'site_uuid' => 'old-uuid-123456',
            'site_url' => 'https://example.moodle.org',
            'timecreated' => time() - 100,
            'timemodified' => time() - 100,
        ];
        $DB->insert_record('tool_moodiyregistration', $oldrecord);

        $apiwrapper = $this->createMock(\tool_moodiyregistration\api_wrapper::class);
        $apiwrapper->method('update_registration')->willReturn($this->acknowledged_response('ack-recreate'));
        $CFG->tool_moodiyregistration_test_api_wrapper = $apiwrapper;

        $result = registration::repair_internal_site_registration('11111111-1111-4111-8111-111111111111');

        $this->assertSame('ok', $result['status']);
        $this->assertSame('11111111-1111-4111-8111-111111111111', $result['site_uuid']);
        // A single stale row is reused in place, so repair does not report an extra deletion here.
        $this->assertSame(0, $result['deleted_records']);
        $this->assertTrue($result['recreated']);
        $this->assertSame('ok', $result['remote_sync_status']);
        $this->assertTrue($result['remote_acknowledged']);
        $this->assertSame(200, $result['remote_http_status']);
        $this->assertSame(hash('sha256', 'ack-recreate'), $result['acknowledgement_fingerprint']);
        $this->assertSame(1, $result['signing_key_version']);
        $this->assertSame(
            hash('sha256', '11111111-1111-4111-8111-111111111111'),
            $result['site_uuid_fingerprint']
        );
        // No error to report when the sync landed.
        $this->assertNull($result['remote_sync_error_code']);

        $records = $DB->get_records('tool_moodiyregistration');
        $this->assertCount(1, $records);

        $record = reset($records);
        $this->assertSame('11111111-1111-4111-8111-111111111111', $record->site_uuid);
        $this->assertSame($CFG->wwwroot, $record->site_url);
    }

    /**
     * Test repairing an internal site registration preserves a pending local repair when remote sync fails.
     * @covers ::repair_internal_site_registration
     */
    public function test_repair_internal_site_registration_returns_pending_when_remote_sync_fails(): void {
        global $DB, $CFG;

        $this->mark_as_internal_site();

        $apiwrapper = $this->createMock(\tool_moodiyregistration\api_wrapper::class);
        $apiwrapper->method('update_registration')->will(
            $this->throwException(new \moodle_exception(
                'errorconnect',
                'tool_moodiyregistration',
                '',
                'raw-secret raw-response private-admin@example.com 123e4567-e89b-42d3-a456-426614174000'
            ))
        );
        $CFG->tool_moodiyregistration_test_api_wrapper = $apiwrapper;

        $result = registration::repair_internal_site_registration('22222222-2222-4222-8222-222222222222');

        // The repair helper logs a developer-mode debugging() message when the remote
        // sync is deferred so dev/staging cron output surfaces the situation. The test
        // must assert that here, otherwise Moodle's PHPUnit harness fails the test
        // with "Unexpected debugging() call detected".
        $this->assertDebuggingCalled(
            'Local internal site registration was repaired, but remote sync is pending. Error code: '
                . 'transport_error',
            DEBUG_DEVELOPER
        );

        $this->assertSame('ok', $result['status']);
        $this->assertSame('pending', $result['remote_sync_status']);
        $this->assertFalse($result['remote_acknowledged']);
        $this->assertNull($result['remote_http_status']);
        $this->assertNull($result['acknowledgement_fingerprint']);
        $this->assertTrue($result['recreated']);

        // The reason must LEAVE this function. It used to exist only inside a
        // debugging() call, which is silent on a provisioned box
        // (DEBUG_DEVELOPER off), so the caller had a status word and no way to
        // say why Moodiy was not reached.
        // See https://github.com/moodiycloud/moodiy/issues/1107.
        $this->assertSame('transport_error', $result['remote_sync_error_code']);

        $record = $DB->get_record('tool_moodiyregistration', [
            'site_uuid' => '22222222-2222-4222-8222-222222222222',
        ]);
        $this->assertNotFalse($record);
        $this->assertSame('22222222-2222-4222-8222-222222222222', $record->site_uuid);
    }

    /**
     * Test an exact local match retries Core after the previous remote call failed.
     * @covers ::repair_internal_site_registration
     */
    public function test_matching_local_registration_retries_pending_remote_sync_until_acknowledged(): void {
        global $DB, $CFG;

        $this->mark_as_internal_site();
        $calls = 0;
        $apiwrapper = $this->createMock(\tool_moodiyregistration\api_wrapper::class);
        $apiwrapper->expects($this->exactly(3))
            ->method('update_registration')
            ->willReturnCallback(function () use (&$calls): array {
                $calls++;
                if ($calls <= 2) {
                    throw new \moodle_exception('Remote API unavailable');
                }

                return $this->acknowledged_response('ack-retry');
            });
        $CFG->tool_moodiyregistration_test_api_wrapper = $apiwrapper;

        $first = registration::repair_internal_site_registration('33333333-3333-4333-8333-333333333333');
        $this->assertDebuggingCalled(
            'Local internal site registration was repaired, but remote sync is pending. Error code: '
                . 'remote_registration_failed',
            DEBUG_DEVELOPER
        );
        $recordid = (int)$DB->get_field('tool_moodiyregistration', 'id', [
            'site_uuid' => '33333333-3333-4333-8333-333333333333',
        ]);

        $second = registration::repair_internal_site_registration('33333333-3333-4333-8333-333333333333');
        $this->assertDebuggingCalled(
            'Local internal site registration was repaired, but remote sync is pending. Error code: '
                . 'remote_registration_failed',
            DEBUG_DEVELOPER
        );
        $third = registration::repair_internal_site_registration('33333333-3333-4333-8333-333333333333');

        $this->assertSame('pending', $first['remote_sync_status']);
        $this->assertSame('pending', $second['remote_sync_status']);
        $this->assertSame('ok', $third['remote_sync_status']);
        $this->assertSame(1, cli_registration_result::exit_code($first));
        $this->assertSame(1, cli_registration_result::exit_code($second));
        $this->assertSame(0, cli_registration_result::exit_code($third));
        $this->assertFalse($second['recreated']);
        $this->assertFalse($third['recreated']);
        $this->assertSame(hash('sha256', 'ack-retry'), $third['acknowledgement_fingerprint']);
        $this->assertSame($recordid, (int)$DB->get_field('tool_moodiyregistration', 'id', [
            'site_uuid' => '33333333-3333-4333-8333-333333333333',
        ]));
        $this->assertSame(1, $DB->count_records('tool_moodiyregistration'));
    }

    /**
     * Test a nominal success without Core acknowledgement remains pending.
     * @covers ::repair_internal_site_registration
     */
    public function test_remote_success_without_acknowledgement_remains_pending(): void {
        global $CFG;

        $this->mark_as_internal_site();
        $apiwrapper = $this->createMock(\tool_moodiyregistration\api_wrapper::class);
        $apiwrapper->expects($this->once())
            ->method('update_registration')
            ->willReturn(['success' => true]);
        $CFG->tool_moodiyregistration_test_api_wrapper = $apiwrapper;

        $result = registration::repair_internal_site_registration('44444444-4444-4444-8444-444444444444');

        $this->assertDebuggingCalled(
            'Local internal site registration was repaired, but remote sync is pending. Error code: '
                . 'missing_acknowledgement',
            DEBUG_DEVELOPER
        );
        $this->assertSame('ok', $result['status']);
        $this->assertSame('pending', $result['remote_sync_status']);
        $this->assertFalse($result['remote_acknowledged']);
        $this->assertSame('missing_acknowledgement', $result['remote_sync_error_code']);
    }

    /**
     * Test an invalid protected attempt binding stays pending without logging or printing its value.
     * @covers ::repair_internal_site_registration
     * @covers ::classify_remote_sync_error
     */
    public function test_invalid_attempt_binding_is_pending_and_redacted(): void {
        global $CFG, $DB;

        $this->mark_as_internal_site();
        $attemptsentinel = 'invalid attempt private-admin@example.com';
        $siteuuid = '99999999-9999-4999-8999-999999999999';
        $CFG->{api::REGISTRATION_ATTEMPT_ID_CONFIG} = $attemptsentinel;
        $CFG->{api::REGISTRATION_ACTION_CONFIG} = 'server_provision_single';

        $result = registration::repair_internal_site_registration($siteuuid);

        $this->assertDebuggingCalled(
            'Local internal site registration was repaired, but remote sync is pending. Error code: '
                . 'invalid_attempt_binding',
            DEBUG_DEVELOPER
        );
        $this->assertSame('pending', $result['remote_sync_status']);
        $this->assertSame('invalid_attempt_binding', $result['remote_sync_error_code']);
        $this->assertSame(1, cli_registration_result::exit_code($result));
        $this->assertSame(1, $DB->count_records('tool_moodiyregistration'));

        $encoded = cli_registration_result::encode($result + [
            'registration_attempt_id' => $attemptsentinel,
            'registration_action' => 'server_provision_single',
        ]);
        $this->assertStringNotContainsString($attemptsentinel, $encoded);
        $this->assertStringNotContainsString($siteuuid, $encoded);
        $this->assertStringNotContainsString('server_provision_single', $encoded);
    }

    /**
     * Test repairing an internal site registration preserves saved registration metadata.
     * @covers ::repair_internal_site_registration
     */
    public function test_repair_internal_site_registration_preserves_saved_site_info(): void {
        global $CFG;

        $this->mark_as_internal_site();

        $data = (object) [
            'site_name' => 'Custom Site Name',
            'description' => 'Custom site description',
            'admin_email' => 'owner@example.com',
            'country_code' => 'IN',
            'language' => 'fr',
            'privacy' => 'displayed',
            'organisation_type' => 'school',
            'policyagreed' => 1,
        ];
        registration::save_site_info($data);

        $apiwrapper = $this->createMock(\tool_moodiyregistration\api_wrapper::class);
        $apiwrapper->method('update_registration')->willReturn($this->acknowledged_response('ack-preserve'));
        $CFG->tool_moodiyregistration_test_api_wrapper = $apiwrapper;

        registration::repair_internal_site_registration('55555555-5555-4555-8555-555555555555');

        $this->assertSame('Custom Site Name', get_config('tool_moodiyregistration', 'site_site_name'));
        $this->assertSame('Custom site description', get_config('tool_moodiyregistration', 'site_description'));
        $this->assertSame('owner@example.com', get_config('tool_moodiyregistration', 'site_admin_email'));
        $this->assertSame('school', get_config('tool_moodiyregistration', 'site_organisation_type'));
    }

    /**
     * Test repairing an internal site registration is rejected for non-internal sites.
     * @covers ::repair_internal_site_registration
     */
    public function test_repair_internal_site_registration_rejects_non_internal_sites(): void {
        global $DB;

        $existing = (object) [
            'site_uuid' => 'existing-uuid-123456',
            'site_url' => 'https://example.moodle.org',
            'timecreated' => time() - 100,
            'timemodified' => time() - 100,
        ];
        $DB->insert_record('tool_moodiyregistration', $existing);

        $result = registration::repair_internal_site_registration('66666666-6666-4666-8666-666666666666');

        $this->assertSame('error', $result['status']);
        $this->assertSame(
            'Internal site registration repair is only available for internal hosted sites.',
            $result['message']
        );
        $this->assertSame(1, $DB->count_records('tool_moodiyregistration'));
        $record = $DB->get_record('tool_moodiyregistration', []);
        $this->assertNotFalse($record);
        $this->assertSame('existing-uuid-123456', $record->site_uuid);
    }

    /**
     * Test internal repair rejects a non-canonical UUID before local or remote work.
     * @covers ::repair_internal_site_registration
     * @covers ::is_canonical_site_uuid
     */
    public function test_repair_internal_site_registration_rejects_invalid_uuid(): void {
        global $DB, $CFG;

        $this->mark_as_internal_site();
        $apiwrapper = $this->createMock(\tool_moodiyregistration\api_wrapper::class);
        $apiwrapper->expects($this->never())->method('update_registration');
        $CFG->tool_moodiyregistration_test_api_wrapper = $apiwrapper;

        $result = registration::repair_internal_site_registration('not-a-site-uuid');

        $this->assertSame('error', $result['status']);
        $this->assertSame('invalid_site_uuid', $result['error_code']);
        $this->assertSame(0, $DB->count_records('tool_moodiyregistration'));
    }

    /**
     * Test internal-site detection tolerates missing forced plugin settings.
     * @covers ::is_internal_site
     */
    public function test_is_internal_site_handles_missing_and_present_forced_plugin_settings(): void {
        global $CFG;

        unset($CFG->forced_plugin_settings);
        $this->assertFalse(registration::is_internal_site());

        $CFG->forced_plugin_settings = [];
        $this->assertFalse(registration::is_internal_site());

        $this->mark_as_internal_site();
        $this->assertTrue(registration::is_internal_site());
    }

    /**
     * Test signed callback timestamps are accepted only within the freshness window.
     * @covers ::is_fresh_callback_timestamp
     */
    public function test_is_fresh_callback_timestamp_enforces_window(): void {
        $this->assertTrue(registration::is_fresh_callback_timestamp(time()));
        $this->assertTrue(registration::is_fresh_callback_timestamp((string) time()));
        $this->assertFalse(registration::is_fresh_callback_timestamp(time() - registration::CALLBACK_FRESHNESS_WINDOW - 1));
        $this->assertFalse(registration::is_fresh_callback_timestamp(time() + 1));
        $this->assertFalse(registration::is_fresh_callback_timestamp('-1'));
        $this->assertFalse(registration::is_fresh_callback_timestamp('not-a-timestamp'));
        $this->assertFalse(registration::is_fresh_callback_timestamp(null));
    }

    /**
     * Test the stale timestamp error response contract used by the legacy callback endpoints.
     * @covers ::stale_timestamp_error_response
     */
    public function test_stale_timestamp_error_response_matches_callback_contract(): void {
        $this->assertSame([
            'status' => 'error',
            'message' => registration::STALE_TIMESTAMP_MESSAGE,
        ], registration::stale_timestamp_error_response());
    }

    /**
     * Mark the fixture as an internal hosted site.
     */
    private function mark_as_internal_site(): void {
        global $CFG;

        $CFG->forced_plugin_settings = ['auth_maintenance' => []];
    }

    /**
     * Build the sanitized acknowledgement shape returned by the production API wrapper.
     *
     * @param string $acknowledgementid Opaque acknowledgement identifier.
     * @return array
     */
    private function acknowledged_response(string $acknowledgementid): array {
        return [
            'success' => true,
            'data' => [
            ],
            '_moodiy_http_status' => 200,
            '_moodiy_acknowledgement_fingerprint' => hash('sha256', $acknowledgementid),
            '_moodiy_acknowledged' => true,
            '_moodiy_signing_key_version' => 1,
        ];
    }

    /**
     * Invoke a private static registration helper in a focused unit test.
     *
     * @param string $method
     * @param mixed ...$arguments
     * @return mixed
     */
    private function invoke_private_static_method(string $method, ...$arguments) {
        $reflection = new \ReflectionMethod(registration::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $arguments);
    }
}
