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

use mod_certifier\local\constants;
use mod_certifier\local\issuer;

/**
 * Event observers for automatic Certifier issuance.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Queue Certifier issuances when a course is completed.
     *
     * @param \core\event\course_completed $event Course completed event.
     */
    public static function course_completed(\core\event\course_completed $event): void {
        global $DB;
        $userid = (int) $event->relateduserid;
        if ($userid <= 0) {
            return;
        }
        $instances = $DB->get_records('certifier', [
            'course' => $event->courseid,
            'triggertype' => constants::TRIGGER_COURSE_COMPLETION,
        ]);
        foreach ($instances as $instance) {
            issuer::queue_issuance($instance, $userid, constants::TRIGGER_COURSE_COMPLETION, 0);
        }
    }

    /**
     * Queue Certifier issuances when the configured activity is completed.
     *
     * @param \core\event\course_module_completion_updated $event Completion updated event.
     */
    public static function course_module_completion_updated(\core\event\course_module_completion_updated $event): void {
        global $DB;
        if (!self::event_is_complete($event)) {
            return;
        }
        $userid = (int) $event->relateduserid;
        if ($userid <= 0) {
            return;
        }
        $instances = $DB->get_records('certifier', [
            'course' => $event->courseid,
            'triggertype' => constants::TRIGGER_ACTIVITY_COMPLETION,
            'triggercmid' => $event->contextinstanceid,
        ]);
        foreach ($instances as $instance) {
            if (!self::passes_grade_requirement($instance, $userid)) {
                continue;
            }
            issuer::queue_issuance(
                $instance,
                $userid,
                constants::TRIGGER_ACTIVITY_COMPLETION,
                (int) $event->contextinstanceid
            );
        }
    }

    /**
     * Check whether a completion event represents a completed state.
     *
     * Failed completions (`COMPLETION_COMPLETE_FAIL`) are intentionally
     * excluded so a learner who failed an activity is not credentialed.
     *
     * @param \core\event\course_module_completion_updated $event Completion updated event.
     * @return bool
     */
    private static function event_is_complete(\core\event\course_module_completion_updated $event): bool {
        $state = (int) ($event->other['completionstate'] ?? 0);
        return in_array($state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
    }

    /**
     * Check the configured grade requirement.
     *
     * @param \stdClass $instance Certifier activity instance.
     * @param int $userid User id.
     * @return bool
     */
    private static function passes_grade_requirement(\stdClass $instance, int $userid): bool {
        global $CFG;
        if (empty($instance->requirepassinggrade)) {
            return true;
        }
        require_once($CFG->libdir . '/gradelib.php');
        $cm = get_coursemodule_from_id(null, (int) $instance->triggercmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return false;
        }
        $grades = grade_get_grades((int) $instance->course, 'mod', $cm->modname, $cm->instance, $userid);
        $gradeitem = $grades && !empty($grades->items) ? reset($grades->items) : null;
        if (!$gradeitem || empty($gradeitem->grades[$userid])) {
            return false;
        }
        $grade = $gradeitem->grades[$userid];
        if ($grade->grade === null || $grade->grade === '') {
            return false;
        }
        $usergrade = (float) $grade->grade;
        $gradepass = (float) ($gradeitem->gradepass ?? 0);
        if ($gradepass > 0 && $usergrade >= $gradepass) {
            return true;
        }
        $min = (int) $instance->minimumgrade;
        if ($min <= 0) {
            return false;
        }
        $grademax = (float) ($gradeitem->grademax ?? 0);
        return $grademax > 0 && (($usergrade / $grademax) * 100) >= $min;
    }
}
