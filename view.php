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
 * Activity view page for the Certifier activity module.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/certifier/lib.php');

use mod_certifier\local\constants;
use mod_certifier\local\issuer;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('certifier', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$certifier = $DB->get_record('certifier', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/certifier:view', $context);

$PAGE->set_url('/mod/certifier/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($certifier->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

echo $OUTPUT->header();
echo html_writer::start_div('mod-certifier-view');

$introhtml = trim((string) format_module_intro('certifier', $certifier, $cm->id));
if ($introhtml !== '') {
    echo html_writer::start_div('card mb-4');
    echo html_writer::div($introhtml, 'card-body');
    echo html_writer::end_div();
}

// Latest issuance for the current learner. One row per user/activity is the v1
// invariant, but ordering by id descending keeps this correct if manual
// re-issuance/history support is added later.
$issuance = $DB->get_record_sql(
    "SELECT *
       FROM {certifier_issuances}
      WHERE certifierid = :certifierid
        AND userid = :userid
   ORDER BY id DESC",
    ['certifierid' => $certifier->id, 'userid' => $USER->id],
    IGNORE_MULTIPLE
);

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::tag('h2', get_string('issuancestatus', 'certifier'), ['class' => 'h3 card-title mb-3']);
if ($issuance) {
    $status = (string) $issuance->status;
    $statuslabel = certifier_issuance_status_label($status);
    $credentialurl = trim((string) $issuance->credentialurl);
    $showcredentiallink = certifier_issuance_has_learner_link($status) && $credentialurl !== '';

    echo html_writer::start_div('d-flex flex-wrap justify-content-between align-items-center mb-3');
    echo html_writer::tag('span', $statuslabel, ['class' => certifier_issuance_status_badge_class($status)]);
    if ($showcredentiallink) {
        echo html_writer::link(
            $credentialurl,
            get_string('viewcredential', 'certifier'),
            [
                'class' => 'btn btn-primary mt-2 mt-sm-0',
                'rel' => 'noopener noreferrer',
                'target' => '_blank',
            ]
        );
    }
    echo html_writer::end_div();

    echo html_writer::div(
        get_string(certifier_issuance_status_description_key($status), 'certifier'),
        'text-muted mb-3'
    );

    if ($showcredentiallink) {
        $label = html_writer::tag('span', get_string('credentiallink', 'certifier') . ': ', ['class' => 'font-weight-bold']);
        $link = html_writer::link(
            $credentialurl,
            s($credentialurl),
            ['class' => 'text-break', 'rel' => 'noopener noreferrer', 'target' => '_blank']
        );
        echo html_writer::div($label . $link, 'small text-muted mb-3');
    } else if ($status === issuer::STATUS_CREATED) {
        echo html_writer::div(get_string('credentiallinkavailableafterissue', 'certifier'), 'alert alert-light mb-3');
    } else if (in_array($status, [issuer::STATUS_QUEUED, issuer::STATUS_PROCESSING], true)) {
        echo html_writer::div(get_string('credentiallinkpending', 'certifier'), 'alert alert-light mb-3');
    }

    $table = new html_table();
    $table->attributes['class'] = 'table table-sm mb-0';
    $table->data = [[get_string('credentiallastupdated', 'certifier'), userdate((int) $issuance->timemodified)]];
    if (!empty($issuance->timeissued)) {
        $table->data[] = [get_string('credentialissuedat', 'certifier'), userdate((int) $issuance->timeissued)];
    }
    if (!empty($issuance->timesent)) {
        $table->data[] = [get_string('credentialsentat', 'certifier'), userdate((int) $issuance->timesent)];
    }
    echo html_writer::table($table);

    if (has_capability('mod/certifier:manage', $context) && !empty($issuance->errormessage)) {
        echo html_writer::div(s($issuance->errormessage), 'alert alert-warning mt-3 mb-0');
    }
} else {
    echo html_writer::div(get_string('statusplaceholder', 'certifier'), 'alert alert-info mb-0');
}
echo html_writer::end_div();
echo html_writer::end_div();

if (has_capability('mod/certifier:manage', $context)) {
    $deliverylabels = [
        constants::DELIVERY_CREATE => get_string('deliverymode_create', 'certifier'),
        constants::DELIVERY_CREATE_ISSUE => get_string('deliverymode_create_issue', 'certifier'),
        constants::DELIVERY_CREATE_ISSUE_SEND => get_string('deliverymode_create_issue_send', 'certifier'),
    ];
    $triggerdetail = $certifier->triggertype === constants::TRIGGER_ACTIVITY_COMPLETION
        ? get_string('trigger_activity_completion', 'certifier') . ': ' . (int) $certifier->triggercmid
        : get_string('trigger_course_completion', 'certifier');

    $custommappings = [];
    if (!empty($certifier->customattributemappings) && is_string($certifier->customattributemappings)) {
        $decoded = json_decode($certifier->customattributemappings, true);
        if (is_array($decoded)) {
            $custommappings = $decoded;
        }
    }
    $custommappingtext = empty($custommappings)
        ? get_string('none')
        : s(json_encode($custommappings, JSON_PRETTY_PRINT));

    echo html_writer::start_div('card');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h2', get_string('configurationsummary', 'certifier'), ['class' => 'h4 card-title mb-3']);
    $table = new html_table();
    $table->attributes['class'] = 'table table-sm mb-0 certifier-config-summary';
    $table->data = [
        [get_string('groupid', 'certifier'), s($certifier->groupid)],
        [get_string('deliverymode', 'certifier'), $deliverylabels[$certifier->deliverymode] ?? s($certifier->deliverymode)],
        [get_string('triggertype', 'certifier'), $triggerdetail],
        [get_string('customattributesettings', 'certifier'), html_writer::tag('pre', $custommappingtext, ['class' => 'mb-0'])],
    ];
    echo html_writer::table($table);
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo $OUTPUT->footer();
