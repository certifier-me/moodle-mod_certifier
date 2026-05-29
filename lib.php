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
 * Core callbacks for the Certifier activity module.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_certifier\local\issuer;

/**
 * Declare supported Moodle module features.
 *
 * @param string $feature Feature constant.
 * @return bool|null True/false for known features, null otherwise.
 */
function certifier_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GRADE_OUTCOMES:
            return false;
        default:
            return null;
    }
}

/**
 * Add a Certifier activity instance.
 *
 * @param stdClass $certifier Submitted module data.
 * @param moodleform_mod|null $mform Module form.
 * @return int New instance id.
 */
function certifier_add_instance($certifier, $mform = null) {
    global $DB;
    certifier_normalise_config($certifier);
    $certifier->timecreated = time();
    $certifier->timemodified = time();
    return $DB->insert_record('certifier', $certifier);
}

/**
 * Update a Certifier activity instance.
 *
 * @param stdClass $certifier Submitted module data.
 * @param moodleform_mod|null $mform Module form.
 * @return bool
 */
function certifier_update_instance($certifier, $mform = null) {
    global $DB;
    certifier_normalise_config($certifier);
    $certifier->id = $certifier->instance;
    $certifier->timemodified = time();
    return $DB->update_record('certifier', $certifier);
}

/**
 * Normalise submitted Certifier configuration before persistence.
 *
 * The form submits `customattributemappings` as an associative array shaped like
 * `customattributemappings[tag] = source`. This boundary is the only place we
 * convert that nested form value to/from the persisted JSON string.
 *
 * @param stdClass $instance Submitted module data.
 */
function certifier_normalise_config(stdClass $instance): void {
    $raw = $instance->customattributemappings ?? null;
    $candidate = [];
    if (is_array($raw)) {
        $candidate = $raw;
    } else if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $candidate = $decoded;
        }
    }
    $mappings = [];
    foreach ($candidate as $tag => $source) {
        $tag = clean_param((string) $tag, PARAM_TEXT);
        $source = clean_param((string) $source, PARAM_ALPHANUMEXT);
        if ($tag !== '' && $source !== '') {
            $mappings[$tag] = $source;
        }
    }
    $instance->customattributemappings = json_encode($mappings);
}

/**
 * Delete a Certifier activity instance and related issuance rows.
 *
 * @param int $id Activity instance id.
 * @return bool
 */
function certifier_delete_instance($id) {
    global $DB;
    if (!$DB->record_exists('certifier', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('certifier_issuances', ['certifierid' => $id]);
    $DB->delete_records('certifier', ['id' => $id]);
    return true;
}

/**
 * Provide cached course module information.
 *
 * @param stdClass $coursemodule Course module record.
 * @return cached_cm_info|null
 */
function certifier_get_coursemodule_info($coursemodule) {
    global $DB;
    $certifier = $DB->get_record('certifier', ['id' => $coursemodule->instance], 'id, name, intro, introformat', IGNORE_MISSING);
    if (!$certifier) {
        return null;
    }
    $info = new cached_cm_info();
    $info->name = $certifier->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('certifier', $certifier, $coursemodule->id, false);
    }
    return $info;
}

/**
 * Translate a stored issuance status code into a learner-friendly label.
 *
 * @param string $status Stored issuance status code.
 * @return string
 */
function certifier_issuance_status_label(string $status): string {
    $map = [
        issuer::STATUS_QUEUED => 'status_queued',
        issuer::STATUS_PROCESSING => 'status_processing',
        issuer::STATUS_CREATED => 'status_created',
        issuer::STATUS_ISSUED => 'status_issued',
        issuer::STATUS_SENT => 'status_sent',
        issuer::STATUS_SEND_SKIPPED => 'status_send_skipped',
        issuer::STATUS_FAILED_RETRYABLE => 'status_failed_retryable',
        issuer::STATUS_FAILED_PERMANENT => 'status_failed_permanent',
    ];
    $key = $map[$status] ?? null;
    return $key ? get_string($key, 'certifier') : get_string('status_unknown', 'certifier');
}

/**
 * Get a learner-facing status description string key for an issuance state.
 *
 * @param string $status Stored issuance status code.
 * @return string
 */
function certifier_issuance_status_description_key(string $status): string {
    $map = [
        issuer::STATUS_QUEUED => 'statusdesc_queued',
        issuer::STATUS_PROCESSING => 'statusdesc_processing',
        issuer::STATUS_CREATED => 'statusdesc_created',
        issuer::STATUS_ISSUED => 'statusdesc_issued',
        issuer::STATUS_SENT => 'statusdesc_sent',
        issuer::STATUS_SEND_SKIPPED => 'statusdesc_send_skipped',
        issuer::STATUS_FAILED_RETRYABLE => 'statusdesc_failed_retryable',
        issuer::STATUS_FAILED_PERMANENT => 'statusdesc_failed_permanent',
    ];
    return $map[$status] ?? 'statusdesc_unknown';
}

/**
 * Get Bootstrap badge classes for one issuance state.
 *
 * @param string $status Stored issuance status code.
 * @return string
 */
function certifier_issuance_status_badge_class(string $status): string {
    $map = [
        issuer::STATUS_QUEUED => 'badge badge-warning',
        issuer::STATUS_PROCESSING => 'badge badge-info',
        issuer::STATUS_CREATED => 'badge badge-secondary',
        issuer::STATUS_ISSUED => 'badge badge-success',
        issuer::STATUS_SENT => 'badge badge-success',
        issuer::STATUS_SEND_SKIPPED => 'badge badge-secondary',
        issuer::STATUS_FAILED_RETRYABLE => 'badge badge-warning',
        issuer::STATUS_FAILED_PERMANENT => 'badge badge-danger',
    ];
    return $map[$status] ?? 'badge badge-light';
}

/**
 * Whether a learner-facing credential link should be shown for one issuance state.
 *
 * @param string $status Stored issuance status code.
 * @return bool
 */
function certifier_issuance_has_learner_link(string $status): bool {
    return in_array($status, [issuer::STATUS_ISSUED, issuer::STATUS_SENT, issuer::STATUS_SEND_SKIPPED], true);
}
