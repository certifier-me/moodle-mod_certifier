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
 * Restore structure step for the Certifier activity module.
 *
 * @package    mod_certifier
 * @category   backup
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_certifier_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define restore paths.
     *
     * Activity configuration is always restored. Per-user issuance/audit rows
     * are restored only when the backup included user information.
     *
     * @return restore_path_element[]
     */
    protected function define_structure() {
        $paths = [new restore_path_element('certifier', '/activity/certifier')];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('certifier_issuance', '/activity/certifier/issuances/issuance');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore one Certifier activity instance.
     *
     * @param array $data Restored activity data.
     */
    protected function process_certifier($data) {
        global $DB;
        $data = (object) $data;
        $data->course = $this->get_courseid();
        if (!empty($data->triggercmid)) {
            $data->triggercmid = $this->get_mappingid('course_module', $data->triggercmid, 0);
        }
        $data->timecreated = time();
        $data->timemodified = time();
        unset($data->id);
        $newid = $DB->insert_record('certifier', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restore one backed-up issuance/audit row.
     *
     * Rows are restored only when user information was included in the backup.
     * Any in-flight processing state is frozen so restoring a course never
     * resumes external Certifier API calls automatically.
     *
     * @param array $data Restored issuance data.
     */
    protected function process_certifier_issuance($data) {
        global $DB;
        $data = (object) $data;
        $data->certifierid = $this->get_new_parentid('certifier');
        $data->course = $this->get_courseid();
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        if (empty($data->userid)) {
            return;
        }
        if (!empty($data->triggercmid)) {
            $data->triggercmid = $this->get_mappingid('course_module', $data->triggercmid, 0);
        }
        $data->idempotencykey = $this->build_restored_idempotency_key($data);
        $this->freeze_inflight_issuance_state($data);
        unset($data->id);
        $DB->insert_record('certifier_issuances', $data);
    }

    /**
     * Build the restored row's idempotency key.
     *
     * Restored activities become distinct Moodle instances in a distinct course,
     * so their per-user issuance rows need fresh idempotency keys derived from
     * the remapped identifiers instead of reusing the original site's key.
     *
     * @param stdClass $data Restored issuance row.
     * @return string
     */
    private function build_restored_idempotency_key(stdClass $data): string {
        $parts = [
            'mod_certifier',
            $data->certifierid,
            $data->course,
            $data->groupid,
            $data->userid,
            $data->triggertype,
            $data->triggercmid,
        ];
        return hash('sha256', implode(':', $parts));
    }

    /**
     * Prevent restored queue rows from resuming external processing.
     *
     * Terminal history rows are preserved as-is. Rows that previously needed
     * local retry/processing are converted into a permanent failure marker with
     * an explanatory message so restore never triggers duplicate remote calls.
     *
     * @param stdClass $data Restored issuance row.
     */
    private function freeze_inflight_issuance_state(stdClass $data): void {
        $activestatuses = [
            \mod_certifier\local\issuer::STATUS_QUEUED,
            \mod_certifier\local\issuer::STATUS_PROCESSING,
            \mod_certifier\local\issuer::STATUS_FAILED_RETRYABLE,
        ];
        if (!in_array((string) ($data->status ?? ''), $activestatuses, true)) {
            return;
        }

        $data->status = \mod_certifier\local\issuer::STATUS_FAILED_PERMANENT;
        $data->nextattempt = 0;
        $data->errorcode = 'restoredbackup';

        $message = get_string('restorefrozenissuance', 'certifier');
        $existingmessage = trim((string) ($data->errormessage ?? ''));
        $data->errormessage = $existingmessage === ''
            ? $message
            : shorten_text($existingmessage . ' ' . $message, 1000);
    }

    /**
     * Restore related files.
     */
    protected function after_execute() {
        $this->add_related_files('mod_certifier', 'intro', null);
    }
}
