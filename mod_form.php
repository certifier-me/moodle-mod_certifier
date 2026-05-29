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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use mod_certifier\local\constants;

/**
 * Module instance settings form for Certifier activities.
 *
 * Credential template (group) and custom attribute options are loaded
 * asynchronously by an AMD module after the form renders, so opening the
 * form never blocks on a synchronous Certifier API call.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_certifier_mod_form extends moodleform_mod {
    /**
     * Define the module settings form.
     */
    public function definition() {
        global $PAGE;
        $mform = $this->_form;
        $course = $this->get_course();

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('certifiername', 'certifier'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->setDefault('name', get_string('defaultactivityname', 'certifier', format_string($course->fullname)));
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'certifiername', 'certifier');
        $this->standard_intro_elements();

        $mform->addElement('header', 'certifiercredential', get_string('credentialsettings', 'certifier'));

        // The actual persisted value lives in this hidden field. An AMD module
        // renders a visible `<select>` populated via AJAX after the form loads
        // and writes the chosen id back into the hidden field on change.
        //
        // We deliberately do NOT use a `select` element here, because
        // HTML_QuickForm_select only accepts values that were registered in
        // its options array at form construction time. Values appended by JS
        // would be dropped silently on submit.
        $mform->addElement('hidden', 'groupid', '');
        $mform->setType('groupid', PARAM_TEXT);

        $mform->addElement(
            'static',
            'group_loader',
            get_string('groupid', 'certifier'),
            \html_writer::div(
                get_string('loading', 'core'),
                '',
                ['data-region' => 'certifier-group-loader']
            )
        );
        $mform->addHelpButton('group_loader', 'groupid', 'certifier');

        $mform->addElement('select', 'deliverymode', get_string('deliverymode', 'certifier'), [
            constants::DELIVERY_CREATE => get_string('deliverymode_create', 'certifier'),
            constants::DELIVERY_CREATE_ISSUE => get_string('deliverymode_create_issue', 'certifier'),
            constants::DELIVERY_CREATE_ISSUE_SEND => get_string('deliverymode_create_issue_send', 'certifier'),
        ]);
        $mform->setDefault('deliverymode', constants::DELIVERY_CREATE_ISSUE_SEND);
        $mform->setType('deliverymode', PARAM_ALPHANUMEXT);

        $mform->addElement('header', 'triggersettings', get_string('triggersettings', 'certifier'));
        $mform->addElement('select', 'triggertype', get_string('triggertype', 'certifier'), [
            constants::TRIGGER_COURSE_COMPLETION => get_string('trigger_course_completion', 'certifier'),
            constants::TRIGGER_ACTIVITY_COMPLETION => get_string('trigger_activity_completion', 'certifier'),
        ]);
        $mform->setDefault('triggertype', constants::TRIGGER_COURSE_COMPLETION);
        $mform->setType('triggertype', PARAM_ALPHANUMEXT);
        $mform->addElement('select', 'triggercmid', get_string('triggercmid', 'certifier'), $this->get_activity_options($course));
        $mform->setType('triggercmid', PARAM_INT);
        $mform->hideIf('triggercmid', 'triggertype', 'neq', constants::TRIGGER_ACTIVITY_COMPLETION);
        $mform->addElement(
            'advcheckbox',
            'requirepassinggrade',
            get_string('requirepassinggrade', 'certifier'),
            get_string('requirepassinggrade_label', 'certifier'),
            null,
            [0, 1]
        );
        $mform->setType('requirepassinggrade', PARAM_INT);
        $mform->setDefault('requirepassinggrade', 0);
        $mform->hideIf('requirepassinggrade', 'triggertype', 'neq', constants::TRIGGER_ACTIVITY_COMPLETION);
        $mform->addElement('text', 'minimumgrade', get_string('minimumgrade', 'certifier'), ['size' => '6']);
        $mform->setType('minimumgrade', PARAM_INT);
        $mform->setDefault('minimumgrade', 0);
        $mform->hideIf('minimumgrade', 'triggertype', 'neq', constants::TRIGGER_ACTIVITY_COMPLETION);
        $mform->hideIf('minimumgrade', 'requirepassinggrade', 'notchecked');
        $mform->addHelpButton('minimumgrade', 'minimumgrade', 'certifier');

        $mform->addElement('header', 'customattributesettings', get_string('customattributesettings', 'certifier'));
        $mform->addElement(
            'static',
            'customattributes_loader',
            '',
            \html_writer::div(
                get_string('mappingnotice', 'certifier'),
                '',
                ['data-region' => 'certifier-customattributes-loader']
            )
        );
        // Hidden JSON payload populated by the AMD module from per-attribute selects
        // built after the API responds. lib.php decodes JSON at the save boundary.
        $mform->addElement('hidden', 'customattributemappings', '{}');
        $mform->setType('customattributemappings', PARAM_RAW);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();

        $PAGE->requires->js_call_amd('mod_certifier/form_options', 'init', [
            (int) $course->id,
            \sesskey(),
        ]);
    }

    /**
     * Validate submitted module settings.
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $course = $this->get_course();

        if (trim((string) ($data['groupid'] ?? '')) === '') {
            $errors['group_loader'] = get_string('groupid_required', 'certifier');
        }

        $deliverymode = $data['deliverymode'] ?? '';
        if (!in_array($deliverymode, constants::delivery_modes(), true)) {
            $errors['deliverymode'] = get_string('deliverymode_error', 'certifier');
        }

        $triggertype = $data['triggertype'] ?? '';
        if (!in_array($triggertype, constants::trigger_types(), true)) {
            $errors['triggertype'] = get_string('triggertype_error', 'certifier');
        }

        if ($triggertype === constants::TRIGGER_ACTIVITY_COMPLETION) {
            $triggercmid = (int) ($data['triggercmid'] ?? 0);
            if ($triggercmid <= 0) {
                $errors['triggercmid'] = get_string('required');
            } else {
                $cm = $this->find_course_module_in_course($course, $triggercmid);
                if (!$cm) {
                    $errors['triggercmid'] = get_string('triggercmid_notincourse', 'certifier');
                } else if ((int) $cm->completion === COMPLETION_TRACKING_NONE) {
                    $errors['triggercmid'] = get_string('triggercmid_nocompletion', 'certifier');
                } else if (!empty($data['requirepassinggrade'])) {
                    if (!$this->activity_supports_grading($cm) && empty($data['minimumgrade'])) {
                        $errors['requirepassinggrade'] = get_string('requirepassinggrade_nograde', 'certifier');
                    }
                }
            }
        }

        $minimumgrade = isset($data['minimumgrade']) ? (int) $data['minimumgrade'] : 0;
        if ($minimumgrade < 0 || $minimumgrade > 100) {
            $errors['minimumgrade'] = get_string('minimumgrade_error', 'certifier');
        }
        return $errors;
    }

    /**
     * Look up a course module that must belong to the current course.
     *
     * @param \stdClass $course Course record.
     * @param int $cmid Course module id.
     * @return \cm_info|null
     */
    private function find_course_module_in_course(\stdClass $course, int $cmid): ?\cm_info {
        $modinfo = get_fast_modinfo($course);
        return $modinfo->cms[$cmid] ?? null;
    }

    /**
     * Check whether the activity exposes a grade item that we can read.
     *
     * @param \cm_info $cm Course module info.
     * @return bool
     */
    private function activity_supports_grading(\cm_info $cm): bool {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $grades = grade_get_grades((int) $cm->course, 'mod', $cm->modname, $cm->instance);
        return $grades && !empty($grades->items);
    }

    /**
     * Prepare stored custom attribute mappings for form display.
     *
     * @param stdClass|array $defaultvalues Stored module data.
     */
    public function set_data($defaultvalues) {
        $data = (object) $defaultvalues;
        // The persisted column is JSON; the hidden form field is the same JSON
        // string so the AMD module can rehydrate the per-attribute selects on edit.
        if (isset($data->customattributemappings)) {
            if (is_array($data->customattributemappings)) {
                $data->customattributemappings = json_encode($data->customattributemappings);
            } else if (!is_string($data->customattributemappings) || $data->customattributemappings === '') {
                $data->customattributemappings = '{}';
            }
        } else {
            $data->customattributemappings = '{}';
        }
        parent::set_data($data);
    }

    /**
     * Get course activities that can trigger issuance.
     *
     * @param stdClass $course Course record.
     * @return array
     */
    private function get_activity_options(stdClass $course): array {
        $options = [0 => get_string('selectactivity', 'certifier')];
        $modinfo = get_fast_modinfo($course);
        foreach ($modinfo->cms as $cm) {
            if ($cm->modname === 'certifier' || !$cm->visible) {
                continue;
            }
            $options[$cm->id] = $cm->get_formatted_name();
        }
        return $options;
    }
}
