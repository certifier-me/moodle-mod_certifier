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
 * Tests for Certifier event observers.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_certifier;

use mod_certifier\local\constants;
use mod_certifier\local\issuer;

/**
 * Tests for Certifier event observers.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_certifier\observer
 */
final class observer_test extends \advanced_testcase {
    /**
     * Course completion queues matching Certifier instances.
     */
    public function test_course_completed_queues_matching_instances(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user] = $this->create_course_and_user();
        $courseinstance = $this->create_certifier_instance($course, [
            'triggertype' => constants::TRIGGER_COURSE_COMPLETION,
            'triggercmid' => 0,
        ]);
        $this->create_certifier_instance($course, [
            'triggertype' => constants::TRIGGER_ACTIVITY_COMPLETION,
            'triggercmid' => 12345,
        ]);

        $event = \core\event\course_completed::create([
            'objectid' => 1,
            'relateduserid' => $user->id,
            'context' => \context_course::instance($course->id),
            'courseid' => $course->id,
            'other' => ['relateduserid' => $user->id],
        ]);

        observer::course_completed($event);

        $this->assertEquals(1, $DB->count_records('certifier_issuances'));
        $issuance = $DB->get_record('certifier_issuances', [], '*', MUST_EXIST);
        $this->assertEquals($courseinstance->id, $issuance->certifierid);
        $this->assertEquals(constants::TRIGGER_COURSE_COMPLETION, $issuance->triggertype);
        $this->assertEquals(issuer::STATUS_QUEUED, $issuance->status);
    }

    /**
     * Activity completion queues only the instance configured for that module.
     */
    public function test_activity_completion_queues_only_matching_trigger_activity(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user] = $this->create_course_and_user();
        $cm1 = $this->create_trigger_activity($course, 'Assignment 1');
        $cm2 = $this->create_trigger_activity($course, 'Assignment 2');
        $instance1 = $this->create_certifier_instance($course, [
            'triggertype' => constants::TRIGGER_ACTIVITY_COMPLETION,
            'triggercmid' => $cm1->id,
        ]);
        $this->create_certifier_instance($course, [
            'triggertype' => constants::TRIGGER_ACTIVITY_COMPLETION,
            'triggercmid' => $cm2->id,
        ]);

        $event = $this->create_activity_completion_event($cm1, (int) $user->id, COMPLETION_COMPLETE_PASS);

        observer::course_module_completion_updated($event);

        $this->assertEquals(1, $DB->count_records('certifier_issuances'));
        $issuance = $DB->get_record('certifier_issuances', [], '*', MUST_EXIST);
        $this->assertEquals($instance1->id, $issuance->certifierid);
        $this->assertEquals($cm1->id, $issuance->triggercmid);
    }

    /**
     * Incomplete and failed completion states do not queue issuance.
     */
    public function test_activity_completion_ignores_incomplete_and_failed_states(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user] = $this->create_course_and_user();
        $cm = $this->create_trigger_activity($course, 'Assignment');
        $this->create_certifier_instance($course, [
            'triggertype' => constants::TRIGGER_ACTIVITY_COMPLETION,
            'triggercmid' => $cm->id,
        ]);

        observer::course_module_completion_updated(
            $this->create_activity_completion_event($cm, (int) $user->id, COMPLETION_INCOMPLETE)
        );
        observer::course_module_completion_updated(
            $this->create_activity_completion_event($cm, (int) $user->id, COMPLETION_COMPLETE_FAIL)
        );

        $this->assertEquals(0, $DB->count_records('certifier_issuances'));
    }

    /**
     * A missing grade does not satisfy a passing-grade requirement.
     */
    public function test_activity_completion_requires_grade_when_passing_grade_is_enabled(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user] = $this->create_course_and_user();
        $cm = $this->create_trigger_activity($course, 'Assignment');
        $this->create_certifier_instance($course, [
            'triggertype' => constants::TRIGGER_ACTIVITY_COMPLETION,
            'triggercmid' => $cm->id,
            'requirepassinggrade' => 1,
            'minimumgrade' => 80,
        ]);

        observer::course_module_completion_updated(
            $this->create_activity_completion_event($cm, (int) $user->id, COMPLETION_COMPLETE_PASS)
        );

        $this->assertEquals(0, $DB->count_records('certifier_issuances'));
    }

    /**
     * Grade item pass thresholds satisfy the passing-grade requirement.
     */
    public function test_activity_completion_accepts_gradepass_threshold(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user] = $this->create_course_and_user();
        $cm = $this->create_trigger_activity($course, 'Assignment');
        $instance = $this->create_certifier_instance($course, [
            'triggertype' => constants::TRIGGER_ACTIVITY_COMPLETION,
            'triggercmid' => $cm->id,
            'requirepassinggrade' => 1,
            'minimumgrade' => 0,
        ]);
        $this->set_activity_grade($cm, (int) $user->id, 70, 70);

        observer::course_module_completion_updated(
            $this->create_activity_completion_event($cm, (int) $user->id, COMPLETION_COMPLETE_PASS)
        );

        $issuance = $DB->get_record('certifier_issuances', [], '*', MUST_EXIST);
        $this->assertEquals($instance->id, $issuance->certifierid);
        $this->assertEquals(constants::TRIGGER_ACTIVITY_COMPLETION, $issuance->triggertype);
    }

    /**
     * Minimum grade percentages gate issuance when no gradepass is configured.
     */
    public function test_activity_completion_uses_minimum_grade_percentage(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$course, $user] = $this->create_course_and_user();
        $cm = $this->create_trigger_activity($course, 'Assignment');
        $passinginstance = $this->create_certifier_instance($course, [
            'name' => 'Passing credential',
            'triggertype' => constants::TRIGGER_ACTIVITY_COMPLETION,
            'triggercmid' => $cm->id,
            'requirepassinggrade' => 1,
            'minimumgrade' => 80,
        ]);
        $this->create_certifier_instance($course, [
            'name' => 'Failing credential',
            'triggertype' => constants::TRIGGER_ACTIVITY_COMPLETION,
            'triggercmid' => $cm->id,
            'requirepassinggrade' => 1,
            'minimumgrade' => 81,
        ]);
        $this->set_activity_grade($cm, (int) $user->id, 80, 0);

        observer::course_module_completion_updated(
            $this->create_activity_completion_event($cm, (int) $user->id, COMPLETION_COMPLETE_PASS)
        );

        $this->assertEquals(1, $DB->count_records('certifier_issuances'));
        $issuance = $DB->get_record('certifier_issuances', [], '*', MUST_EXIST);
        $this->assertEquals($passinginstance->id, $issuance->certifierid);
    }

    /**
     * Create a test course and enrolled user.
     *
     * @return array{0:\stdClass,1:\stdClass}
     */
    private function create_course_and_user(): array {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user(['email' => 'learner@example.test']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        return [$course, $user];
    }

    /**
     * Create a graded activity that can act as a trigger.
     *
     * @param \stdClass $course Course record.
     * @param string $name Activity name.
     * @return \stdClass Course module record.
     */
    private function create_trigger_activity(\stdClass $course, string $name): \stdClass {
        $activity = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'name' => $name,
            'grade' => 100,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        return get_coursemodule_from_id('assign', $activity->cmid, 0, false, MUST_EXIST);
    }

    /**
     * Create a Certifier instance.
     *
     * @param \stdClass $course Course record.
     * @param array $record Instance overrides.
     * @return \stdClass
     */
    private function create_certifier_instance(\stdClass $course, array $record): \stdClass {
        return $this->getDataGenerator()->create_module('certifier', $record + [
            'course' => $course->id,
            'name' => 'Certifier credential',
            'groupid' => 'group-1',
            'deliverymode' => constants::DELIVERY_CREATE,
            'triggertype' => constants::TRIGGER_COURSE_COMPLETION,
            'triggercmid' => 0,
            'requirepassinggrade' => 0,
            'minimumgrade' => 0,
            'customattributemappings' => json_encode([]),
        ]);
    }

    /**
     * Create an activity completion event.
     *
     * @param \stdClass $cm Course module record.
     * @param int $userid User id.
     * @param int $completionstate Completion state.
     * @return \core\event\course_module_completion_updated
     */
    private function create_activity_completion_event(
        \stdClass $cm,
        int $userid,
        int $completionstate
    ): \core\event\course_module_completion_updated {
        global $DB;

        $record = $DB->get_record('course_modules_completion', [
            'coursemoduleid' => $cm->id,
            'userid' => $userid,
        ], '*', IGNORE_MISSING);
        if ($record) {
            $record->completionstate = $completionstate;
            $record->overrideby = null;
            $record->timemodified = time();
            $DB->update_record('course_modules_completion', $record);
            $recordid = (int) $record->id;
        } else {
            $recordid = $DB->insert_record('course_modules_completion', (object) [
                'coursemoduleid' => $cm->id,
                'userid' => $userid,
                'completionstate' => $completionstate,
                'viewed' => 0,
                'overrideby' => null,
                'timemodified' => time(),
            ]);
        }

        return \core\event\course_module_completion_updated::create([
            'objectid' => $recordid,
            'context' => \context_module::instance($cm->id),
            'relateduserid' => $userid,
            'other' => [
                'relateduserid' => $userid,
                'overrideby' => null,
                'completionstate' => $completionstate,
            ],
        ]);
    }

    /**
     * Set a grade on the trigger activity.
     *
     * @param \stdClass $cm Course module record.
     * @param int $userid User id.
     * @param float $rawgrade Raw grade.
     * @param float $gradepass Grade pass threshold.
     */
    private function set_activity_grade(\stdClass $cm, int $userid, float $rawgrade, float $gradepass): void {
        global $CFG;

        require_once($CFG->libdir . '/gradelib.php');

        grade_update('mod/' . $cm->modname, $cm->course, 'mod', $cm->modname, $cm->instance, 0, [
            'userid' => $userid,
            'rawgrade' => $rawgrade,
        ]);

        $gradeitem = \grade_item::fetch([
            'courseid' => $cm->course,
            'itemtype' => 'mod',
            'itemmodule' => $cm->modname,
            'iteminstance' => $cm->instance,
            'itemnumber' => 0,
        ]);
        if ($gradeitem) {
            $gradeitem->gradepass = $gradepass;
            $gradeitem->update();
        }
    }
}
