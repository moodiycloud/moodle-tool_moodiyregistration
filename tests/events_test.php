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
 * Unit tests for Moodiy Registration events.
 *
 * @package     tool_moodiyregistration
 * @category    test
 * @copyright   2025-2026 MoodiyCloud <support@moodiycloud.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_moodiyregistration;

use tool_moodiyregistration\event\moodiy_registration;
use tool_moodiyregistration\event\moodiy_unregistration;
use tool_moodiyregistration\event\moodiyregistration_updated;
use tool_moodiyregistration\event\update_request;

/**
 * Unit tests for event functionality.
 */
class events_test extends \advanced_testcase {
    /**
     * Set up tests.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Data provider for event tests.
     *
     * @return array Array of test cases
     */
    public static function event_test_cases(): array {
        return [
            'registration' => [
                'eventclass' => moodiy_registration::class,
                'crud' => 'c',
                'edulevel' => \core\event\base::LEVEL_OTHER,
                'needssnapshot' => true,
                'expectedclass' => '\tool_moodiyregistration\event\moodiy_registration',
            ],
            'unregistration' => [
                'eventclass' => moodiy_unregistration::class,
                'crud' => 'd',
                'edulevel' => \core\event\base::LEVEL_OTHER,
                'needssnapshot' => true,
                'expectedclass' => '\tool_moodiyregistration\event\moodiy_unregistration',
            ],
            'registration_updated' => [
                'eventclass' => moodiyregistration_updated::class,
                'crud' => 'u',
                'edulevel' => \core\event\base::LEVEL_OTHER,
                'needssnapshot' => true,
                'expectedclass' => '\tool_moodiyregistration\event\moodiyregistration_updated',
            ],
            'update_request' => [
                'eventclass' => update_request::class,
                'crud' => 'r',
                'edulevel' => \core\event\base::LEVEL_OTHER,
                'needssnapshot' => false,
                'expectedclass' => '\tool_moodiyregistration\event\update_request',
            ],
        ];
    }

    /**
     * Test all moodiy registration events.
     *
     * @dataProvider event_test_cases
     * @param string $eventclass The event class to test
     * @param string $crud The expected CRUD value
     * @param int $edulevel The expected education level
     * @param bool $needssnapshot Whether the event needs a record snapshot
     * @param string $expectedclass The expected class of the triggered event
     * @covers ::moodiy_registration
     * @covers ::moodiy_unregistration
     * @covers ::moodiyregistration_updated
     * @covers ::update_request
     */
    public function test_registration_events(
        string $eventclass,
        string $crud,
        int $edulevel,
        bool $needssnapshot,
        string $expectedclass
    ): void {
        global $DB;

        // Create a test record with a unique site_uuid to avoid conflicts.
        $record = new \stdClass();
        $record->site_uuid = 'test-uuid-' . uniqid();
        $record->site_url = 'https://example.moodle.org';
        $record->timecreated = time();
        $record->timemodified = time();
        $recordid = $DB->insert_record('tool_moodiyregistration', $record);

        // Create the event.
        $event = $eventclass::create([
            'context' => \context_system::instance(),
            'objectid' => $recordid,
            'other' => [
                'site_uuid' => $record->site_uuid,
            ],
        ]);

        // Add record snapshot if needed.
        if ($needssnapshot) {
            $record->id = $recordid;
            $event->add_record_snapshot('tool_moodiyregistration', $record);
        }

        // Test event properties.
        $this->assertEquals('tool_moodiyregistration', $event->objecttable);
        $this->assertEquals($recordid, $event->objectid);
        $this->assertEquals($crud, $event->crud);
        $this->assertEquals($edulevel, $event->edulevel);

        // Trigger the event and capture it.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();

        // Check that the event was triggered.
        $this->assertCount(1, $events);
        $triggeredevent = reset($events);
        $this->assertInstanceOf($expectedclass, $triggeredevent);
        $this->assertEquals($recordid, $triggeredevent->objectid);
        $this->assertEquals($crud, $triggeredevent->crud);

        // Delete the record to ensure clean state for next test.
        $DB->delete_records('tool_moodiyregistration', ['id' => $recordid]);
    }

    /**
     * Clean up after tests.
     */
    public function tearDown(): void {
        // Clean up the database.
        global $DB;
        $DB->delete_records('tool_moodiyregistration');

        parent::tearDown();
    }
}
