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

namespace mod_certifier;

use backup;
use backup_controller;
use backup_setting;
use mod_certifier\local\constants;
use mod_certifier\local\issuer;
use restore_controller;
use restore_dbops;
use stdClass;

/**
 * Backup and restore tests for the Certifier activity module.
 *
 * @package    mod_certifier
 * @category   test
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Load backup/restore libraries once for this test class.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
    }

    /**
     * User-data backups restore issuance history and remap module references.
     */
    public function test_backup_restore_with_user_data_restores_issuance_and_freezes_inflight_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $user, $watchedcm, $instance, $issuance] = $this->create_fixture_with_issuance(issuer::STATUS_QUEUED);

        $newcourseid = $this->backup_and_restore_course($course, true);

        $restoredinstance = $DB->get_record('certifier', ['course' => $newcourseid], '*', MUST_EXIST);
        $restoredwatchedpage = $DB->get_record('page', ['course' => $newcourseid, 'name' => 'Watched activity'], '*', MUST_EXIST);
        $restoredwatchedcm = get_coursemodule_from_instance('page', $restoredwatchedpage->id, $newcourseid, false, MUST_EXIST);

        $this->assertEquals(constants::TRIGGER_ACTIVITY_COMPLETION, $restoredinstance->triggertype);
        $this->assertEquals($restoredwatchedcm->id, (int) $restoredinstance->triggercmid);
        $this->assertEquals($instance->groupid, $restoredinstance->groupid);
        $this->assertEquals($instance->customattributemappings, $restoredinstance->customattributemappings);

        $restoredissuance = $DB->get_record('certifier_issuances', [
            'certifierid' => $restoredinstance->id,
            'userid' => $user->id,
        ], '*', MUST_EXIST);

        $this->assertEquals($restoredinstance->id, (int) $restoredissuance->certifierid);
        $this->assertEquals($newcourseid, (int) $restoredissuance->course);
        $this->assertEquals($user->id, (int) $restoredissuance->userid);
        $this->assertEquals($restoredwatchedcm->id, (int) $restoredissuance->triggercmid);
        $this->assertEquals($issuance->triggertype, $restoredissuance->triggertype);
        $this->assertEquals($issuance->attempts, (int) $restoredissuance->attempts);
        $expectedidempotencykey = hash('sha256', implode(':', [
            'mod_certifier',
            $restoredinstance->id,
            $newcourseid,
            $restoredissuance->groupid,
            $user->id,
            $restoredissuance->triggertype,
            $restoredwatchedcm->id,
        ]));
        $this->assertEquals($expectedidempotencykey, $restoredissuance->idempotencykey);
        $this->assertNotEquals($issuance->idempotencykey, $restoredissuance->idempotencykey);
        $this->assertEquals($issuance->groupid, $restoredissuance->groupid);
        $this->assertEquals($issuance->recipientname, $restoredissuance->recipientname);
        $this->assertEquals($issuance->recipientemail, $restoredissuance->recipientemail);
        $this->assertEquals($issuance->customattributes, $restoredissuance->customattributes);
        $this->assertEquals($issuance->timecreated, (int) $restoredissuance->timecreated);
        $this->assertEquals($issuance->timemodified, (int) $restoredissuance->timemodified);

        $this->assertEquals(issuer::STATUS_FAILED_PERMANENT, $restoredissuance->status);
        $this->assertEquals(0, (int) $restoredissuance->nextattempt);
        $this->assertEquals('restoredbackup', $restoredissuance->errorcode);
        $this->assertStringContainsString(
            get_string('restorefrozenissuance', 'certifier'),
            $restoredissuance->errormessage
        );

        $this->assertEquals(1, $DB->count_records('certifier_issuances', ['certifierid' => $instance->id]));
        $this->assertEquals($watchedcm->id, (int) $issuance->triggercmid);
    }

    /**
     * Terminal issuance history is preserved when restoring with user data.
     */
    public function test_backup_restore_with_user_data_preserves_terminal_issuance_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $user, , $instance, $issuance] = $this->create_fixture_with_issuance(issuer::STATUS_SENT);

        $newcourseid = $this->backup_and_restore_course($course, true);

        $restoredinstance = $DB->get_record('certifier', ['course' => $newcourseid], '*', MUST_EXIST);
        $restoredissuance = $DB->get_record('certifier_issuances', [
            'certifierid' => $restoredinstance->id,
            'userid' => $user->id,
        ], '*', MUST_EXIST);

        $this->assertEquals(issuer::STATUS_SENT, $restoredissuance->status);
        $this->assertEquals(0, (int) $restoredissuance->nextattempt);
        $this->assertEquals($issuance->credentialid, $restoredissuance->credentialid);
        $this->assertEquals($issuance->credentialurl, $restoredissuance->credentialurl);
        $this->assertEquals($issuance->errorcode, $restoredissuance->errorcode);
        $this->assertEquals($issuance->errormessage, $restoredissuance->errormessage);
        $this->assertEquals($issuance->timeissued, (int) $restoredissuance->timeissued);
        $this->assertEquals($issuance->timesent, (int) $restoredissuance->timesent);
        $this->assertNotEquals($issuance->idempotencykey, $restoredissuance->idempotencykey);
    }

    /**
     * Non-user-data backups restore only activity configuration.
     */
    public function test_backup_restore_without_user_data_excludes_issuance_history(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course] = $this->create_fixture_with_issuance(issuer::STATUS_SENT);

        $newcourseid = $this->backup_and_restore_course($course, false);

        $restoredinstance = $DB->get_record('certifier', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertFalse($DB->record_exists('certifier_issuances', ['certifierid' => $restoredinstance->id]));
    }

    /**
     * Create a course fixture with one watched activity, one Certifier activity, and one issuance row.
     *
     * @param string $status Issuance status to create.
     * @return array{0:stdClass,1:stdClass,2:stdClass,3:stdClass,4:stdClass}
     */
    private function create_fixture_with_issuance(string $status): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Backup course',
            'shortname' => 'BACKUP101',
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', [
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'email' => 'ada@example.test',
        ]);
        $watchedactivity = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Watched activity',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $instance = $this->getDataGenerator()->create_module('certifier', [
            'course' => $course->id,
            'name' => 'Course credential',
            'groupid' => 'group-backup',
            'deliverymode' => constants::DELIVERY_CREATE_ISSUE_SEND,
            'triggertype' => constants::TRIGGER_ACTIVITY_COMPLETION,
            'triggercmid' => $watchedactivity->cmid,
            'customattributemappings' => json_encode(['course' => 'course_fullname']),
        ]);

        $now = time() - 300;
        $nextattempt = $status === issuer::STATUS_QUEUED ? $now + 120 : 0;
        $timeissued = in_array($status, [issuer::STATUS_ISSUED, issuer::STATUS_SENT], true) ? $now + 60 : 0;
        $timesent = $status === issuer::STATUS_SENT ? $now + 90 : 0;
        $credentialid = $timeissued ? 'credential-123' : '';
        $credentialurl = $timeissued ? 'https://credentials.example.test/credentials/public-123' : '';
        $errorcode = $status === issuer::STATUS_QUEUED ? 'temporary' : '';
        $errormessage = $status === issuer::STATUS_QUEUED ? 'Queued before backup.' : '';

        $issuance = (object) [
            'certifierid' => $instance->id,
            'course' => $course->id,
            'userid' => $user->id,
            'triggercmid' => $watchedactivity->cmid,
            'triggertype' => constants::TRIGGER_ACTIVITY_COMPLETION,
            'status' => $status,
            'attempts' => 2,
            'nextattempt' => $nextattempt,
            'idempotencykey' => hash('sha256', 'backup:' . $instance->id . ':' . $user->id . ':' . $status),
            'groupid' => 'group-backup',
            'recipientname' => 'Ada Lovelace',
            'recipientemail' => 'ada@example.test',
            'customattributes' => json_encode(['custom.course' => 'Backup course']),
            'credentialid' => $credentialid,
            'credentialurl' => $credentialurl,
            'errorcode' => $errorcode,
            'errormessage' => $errormessage,
            'timecreated' => $now,
            'timemodified' => $now + 30,
            'timeissued' => $timeissued,
            'timesent' => $timesent,
        ];
        $issuance->id = $DB->insert_record('certifier_issuances', $issuance);

        $watchedcm = get_coursemodule_from_id('page', $watchedactivity->cmid, $course->id, false, MUST_EXIST);
        return [$course, $user, $watchedcm, $instance, $issuance];
    }

    /**
     * Back up one course and restore it into a newly created course.
     *
     * @param stdClass $course Source course.
     * @param bool $userdata Whether to include user data.
     * @return int Restored course id.
     */
    private function backup_and_restore_course(stdClass $course, bool $userdata): int {
        global $CFG, $USER;

        $CFG->backup_file_logger_level = backup::LOG_NONE;

        $backupcontroller = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $backupcontroller->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $backupcontroller->get_plan()->get_setting('users')->set_value($userdata);

        $backupid = $backupcontroller->get_backupid();
        $backupcontroller->execute_plan();
        $backupcontroller->destroy();

        $newcourseid = restore_dbops::create_new_course(
            $course->fullname,
            $course->shortname . '_restored',
            $course->category
        );

        $restorecontroller = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $restorecontroller->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $restorecontroller->get_plan()->get_setting('users')->set_value($userdata);

        $this->assertTrue($restorecontroller->execute_precheck());
        $restorecontroller->execute_plan();
        $restorecontroller->destroy();

        return $newcourseid;
    }
}
