<?php
// This file is part of Moodle - http://moodle.org/
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
 * Tests for Certifier module callbacks.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_certifier;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

use mod_certifier\local\constants;

/**
 * Tests for Certifier module callbacks.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::certifier_add_instance
 * @covers     ::certifier_update_instance
 * @covers     ::certifier_delete_instance
 * @covers     ::certifier_supports
 */
final class lib_test extends \advanced_testcase {
    /**
     * Add instance stores normalised custom attribute mappings.
     */
    public function test_add_instance_normalises_custom_attribute_mappings(): void {
        global $DB;
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->instance_data($course->id);
        $instance->customattributemappings = [
            'learner_email' => 'user_email',
            'course_name' => 'course_fullname',
            'empty_source' => '',
            '' => 'user_fullname',
        ];

        $id = \certifier_add_instance($instance);

        $record = $DB->get_record('certifier', ['id' => $id], '*', MUST_EXIST);
        $this->assertEquals([
            'learner_email' => 'user_email',
            'course_name' => 'course_fullname',
        ], json_decode($record->customattributemappings, true));
    }

    /**
     * Update instance stores normalised custom attribute mappings.
     */
    public function test_update_instance_normalises_custom_attribute_mappings(): void {
        global $DB;
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $id = \certifier_add_instance($this->instance_data($course->id));
        $update = $this->instance_data($course->id);
        $update->instance = $id;
        $update->name = 'Updated Certifier';
        $update->customattributemappings = json_encode([
            'username' => 'user_username',
            'blank' => '',
        ]);

        $this->assertTrue(\certifier_update_instance($update));

        $record = $DB->get_record('certifier', ['id' => $id], '*', MUST_EXIST);
        $this->assertEquals('Updated Certifier', $record->name);
        $this->assertEquals(['username' => 'user_username'], json_decode($record->customattributemappings, true));
    }

    /**
     * Deleting an instance deletes related issuance rows.
     */
    public function test_delete_instance_deletes_issuances(): void {
        global $DB;
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $id = \certifier_add_instance($this->instance_data($course->id));
        $this->insert_issuance($id, $course->id, $user->id);

        $this->assertTrue(\certifier_delete_instance($id));

        $this->assertFalse($DB->record_exists('certifier', ['id' => $id]));
        $this->assertFalse($DB->record_exists('certifier_issuances', ['certifierid' => $id]));
    }

    /**
     * Module feature declarations match expected activity behaviour.
     */
    public function test_supports_expected_features(): void {
        $this->assertTrue(\certifier_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(\certifier_supports(FEATURE_SHOW_DESCRIPTION));
        $this->assertTrue(\certifier_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(\certifier_supports(FEATURE_COMPLETION_TRACKS_VIEWS));
        $this->assertFalse(\certifier_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertFalse(\certifier_supports(FEATURE_GRADE_OUTCOMES));
        $this->assertNull(\certifier_supports('unknown_feature'));
    }

    /**
     * Build a default activity instance record.
     *
     * @param int $courseid Course id.
     * @return \stdClass
     */
    private function instance_data(int $courseid): \stdClass {
        return (object) [
            'course' => $courseid,
            'name' => 'Certifier credential',
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'groupid' => 'group-1',
            'deliverymode' => constants::DELIVERY_CREATE_ISSUE_SEND,
            'triggertype' => constants::TRIGGER_COURSE_COMPLETION,
            'triggercmid' => 0,
            'requirepassinggrade' => 0,
            'minimumgrade' => 0,
            'customattributemappings' => [],
        ];
    }

    /**
     * Insert an issuance row for deletion tests.
     *
     * @param int $certifierid Certifier instance id.
     * @param int $courseid Course id.
     * @param int $userid User id.
     */
    private function insert_issuance(int $certifierid, int $courseid, int $userid): void {
        global $DB;
        $now = time();
        $DB->insert_record('certifier_issuances', (object) [
            'certifierid' => $certifierid,
            'course' => $courseid,
            'userid' => $userid,
            'triggercmid' => 0,
            'triggertype' => constants::TRIGGER_COURSE_COMPLETION,
            'status' => 'queued',
            'attempts' => 0,
            'nextattempt' => $now,
            'idempotencykey' => sha1($certifierid . ':' . $userid . ':' . $now),
            'groupid' => 'group-1',
            'recipientname' => '',
            'recipientemail' => '',
            'customattributes' => '{}',
            'credentialid' => '',
            'credentialurl' => '',
            'errorcode' => '',
            'errormessage' => '',
            'timecreated' => $now,
            'timemodified' => $now,
            'timeissued' => 0,
            'timesent' => 0,
        ]);
    }
}
