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

namespace mod_certifier\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the Certifier activity module.
 *
 * @package    mod_certifier
 * @category   privacy
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe personal data stored by this plugin and sent to Certifier.
     *
     * @param collection $collection Metadata collection.
     * @return collection Updated metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('certifier_issuances', [
            'userid' => 'privacy:metadata:certifier_issuances:userid',
            'triggertype' => 'privacy:metadata:certifier_issuances:triggertype',
            'status' => 'privacy:metadata:certifier_issuances:status',
            'groupid' => 'privacy:metadata:certifier_issuances:groupid',
            'recipientname' => 'privacy:metadata:certifier_issuances:recipientname',
            'recipientemail' => 'privacy:metadata:certifier_issuances:recipientemail',
            'customattributes' => 'privacy:metadata:certifier_issuances:customattributes',
            'credentialid' => 'privacy:metadata:certifier_issuances:credentialid',
            'credentialurl' => 'privacy:metadata:certifier_issuances:credentialurl',
            'errorcode' => 'privacy:metadata:certifier_issuances:errorcode',
            'errormessage' => 'privacy:metadata:certifier_issuances:errormessage',
            'timecreated' => 'privacy:metadata:certifier_issuances:timecreated',
            'timemodified' => 'privacy:metadata:certifier_issuances:timemodified',
            'timeissued' => 'privacy:metadata:certifier_issuances:timeissued',
            'timesent' => 'privacy:metadata:certifier_issuances:timesent',
        ], 'privacy:metadata:certifier_issuances');
        $collection->add_external_location_link('certifier', [
            'groupid' => 'privacy:metadata:certifier:groupid',
            'recipientname' => 'privacy:metadata:certifier:recipientname',
            'recipientemail' => 'privacy:metadata:certifier:recipientemail',
            'customattributes' => 'privacy:metadata:certifier:customattributes',
        ], 'privacy:metadata:certifier');
        return $collection;
    }

    /**
     * Get contexts containing Certifier issuance data for a user.
     *
     * @param int $userid User id.
     * @return contextlist Context list.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {certifier} certifier ON certifier.id = cm.instance
                  JOIN {certifier_issuances} issuances ON issuances.certifierid = certifier.id
                 WHERE issuances.userid = :userid";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'certifier',
            'userid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Add users with Certifier issuance data in a context.
     *
     * @param userlist $userlist User list for the context.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $sql = "SELECT issuances.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {certifier} certifier ON certifier.id = cm.instance
                  JOIN {certifier_issuances} issuances ON issuances.certifierid = certifier.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, [
            'cmid' => $context->instanceid,
            'modname' => 'certifier',
        ]);
    }

    /**
     * Export approved Certifier issuance data for a user.
     *
     * @param approved_contextlist $contextlist Approved context list.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $sql = "SELECT issuances.*, ctx.id AS contextid, cm.id AS cmid
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {certifier} certifier ON certifier.id = cm.instance
                  JOIN {certifier_issuances} issuances ON issuances.certifierid = certifier.id
                 WHERE ctx.id {$contextsql}
                   AND issuances.userid = :userid
              ORDER BY ctx.id, issuances.id";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'certifier',
            'userid' => $contextlist->get_user()->id,
        ] + $contextparams;

        $recordsbycontext = [];
        $recordset = $DB->get_recordset_sql($sql, $params);
        foreach ($recordset as $record) {
            $contextid = (int) $record->contextid;
            unset($record->contextid, $record->cmid);
            $recordsbycontext[$contextid][] = self::export_issuance_record($record);
        }
        $recordset->close();

        foreach ($recordsbycontext as $contextid => $records) {
            $context = \context::instance_by_id($contextid);
            $contextdata = helper::get_context_data($context, $contextlist->get_user());
            writer::with_context($context)->export_data([], $contextdata);
            writer::with_context($context)->export_related_data([], 'certifier_issuances', (object) ['issuances' => $records]);
            helper::export_context_files($context, $contextlist->get_user());
        }
    }

    /**
     * Delete all Certifier issuance data in a context.
     *
     * @param \context $context Context to delete from.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('certifier', $context->instanceid);
        if (!$cm) {
            return;
        }
        $DB->delete_records('certifier_issuances', ['certifierid' => $cm->instance]);
    }

    /**
     * Delete Certifier issuance data for a user in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved context list.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('certifier', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $DB->delete_records('certifier_issuances', [
                'certifierid' => $cm->instance,
                'userid' => $contextlist->get_user()->id,
            ]);
        }
    }

    /**
     * Delete Certifier issuance data for approved users in one context.
     *
     * @param approved_userlist $userlist Approved user list.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('certifier', $context->instanceid);
        if (!$cm) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('certifier_issuances', "certifierid = :certifierid AND userid {$usersql}", [
            'certifierid' => $cm->instance,
        ] + $userparams);
    }

    /**
     * Convert an issuance database row into exportable privacy data.
     *
     * @param \stdClass $record Issuance row.
     * @return \stdClass Exportable issuance data.
     */
    private static function export_issuance_record(\stdClass $record): \stdClass {
        return (object) [
            'triggertype' => $record->triggertype,
            'status' => $record->status,
            'groupid' => $record->groupid,
            'recipientname' => $record->recipientname,
            'recipientemail' => $record->recipientemail,
            'customattributes' => $record->customattributes,
            'credentialid' => $record->credentialid,
            'credentialurl' => $record->credentialurl,
            'errorcode' => $record->errorcode,
            'errormessage' => $record->errormessage,
            'timecreated' => empty($record->timecreated) ? null : transform::datetime($record->timecreated),
            'timemodified' => empty($record->timemodified) ? null : transform::datetime($record->timemodified),
            'timeissued' => empty($record->timeissued) ? null : transform::datetime($record->timeissued),
            'timesent' => empty($record->timesent) ? null : transform::datetime($record->timesent),
        ];
    }
}
