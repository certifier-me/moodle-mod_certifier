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
 * Backup structure step for the Certifier activity module.
 *
 * @package    mod_certifier
 * @category   backup
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_certifier_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the backup structure.
     *
     * Activity configuration is always backed up. Per-user issuance/audit rows
     * are included only when the Moodle backup includes user information, which
     * matches standard Moodle backup semantics for user-linked activity data.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $certifier = new backup_nested_element('certifier', ['id'], [
            'course', 'name', 'intro', 'introformat', 'groupid',
            'deliverymode', 'triggertype', 'triggercmid', 'requirepassinggrade', 'minimumgrade',
            'customattributemappings', 'timecreated', 'timemodified',
        ]);

        if ($userinfo) {
            $issuances = new backup_nested_element('issuances');
            $issuance = new backup_nested_element('issuance', ['id'], [
                'userid', 'triggercmid', 'triggertype', 'status', 'attempts', 'nextattempt',
                'idempotencykey', 'groupid', 'recipientname', 'recipientemail', 'customattributes',
                'credentialid', 'credentialurl', 'errorcode', 'errormessage',
                'timecreated', 'timemodified', 'timeissued', 'timesent',
            ]);

            $certifier->add_child($issuances);
            $issuances->add_child($issuance);

            $issuance->set_source_table('certifier_issuances', ['certifierid' => backup::VAR_PARENTID]);
            $issuance->annotate_ids('user', 'userid');
            $issuance->annotate_ids('course_module', 'triggercmid');
        }

        $certifier->set_source_table('certifier', ['id' => backup::VAR_ACTIVITYID]);
        $certifier->annotate_ids('course_module', 'triggercmid');
        $certifier->annotate_files('mod_certifier', 'intro', null);

        return $this->prepare_activity_structure($certifier);
    }
}
