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

namespace mod_certifier\local;

/**
 * Queues and processes Certifier credential issuance.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issuer {
    /** Issuance is queued for processing. */
    public const STATUS_QUEUED = 'queued';
    /** Issuance is currently being processed. */
    public const STATUS_PROCESSING = 'processing';
    /** Credential has been created in Certifier. */
    public const STATUS_CREATED = 'created';
    /** Credential has been issued in Certifier. */
    public const STATUS_ISSUED = 'issued';
    /** Credential has been sent by Certifier. */
    public const STATUS_SENT = 'sent';
    /** Sending was skipped because no recipient email is available. */
    public const STATUS_SEND_SKIPPED = 'send_skipped';
    /** Processing failed but may be retried. */
    public const STATUS_FAILED_RETRYABLE = 'failed_retryable';
    /** Processing failed permanently. */
    public const STATUS_FAILED_PERMANENT = 'failed_permanent';
    /** Maximum retry attempts for transient failures. */
    private const MAX_ATTEMPTS = 5;
    /** @var callable|null Factory used to create Certifier API clients. */
    private static $clientfactory = null;

    /**
     * Set the factory used to create Certifier API clients.
     *
     * @param callable|null $factory Factory returning an api_client, or null for the default factory.
     */
    public static function set_client_factory(?callable $factory): void {
        self::$clientfactory = $factory;
    }

    /**
     * Get the Certifier API client.
     *
     * @return api_client
     */
    private static function get_client(): api_client {
        if (self::$clientfactory === null) {
            return new api_client();
        }
        $client = call_user_func(self::$clientfactory);
        if (!$client instanceof api_client) {
            throw new \coding_exception('Certifier API client factory must return an api_client');
        }
        return $client;
    }

    /**
     * Queue credential issuance for a user.
     *
     * @param \stdClass $instance Certifier activity instance.
     * @param int $userid User id.
     * @param string $trigger Trigger type.
     * @param int $triggercmid Triggering course module id.
     * @return int Issuance row id.
     */
    public static function queue_issuance(\stdClass $instance, int $userid, string $trigger, int $triggercmid = 0): int {
        global $DB;
        if ($userid <= 0) {
            throw new \invalid_parameter_exception('Certifier queue_issuance requires a positive userid');
        }
        $groupid = trim((string) ($instance->groupid ?? ''));
        if ($groupid === '') {
            throw new \invalid_parameter_exception('Certifier queue_issuance requires a credential template (group) id');
        }
        if (!in_array($trigger, constants::trigger_types(), true)) {
            throw new \invalid_parameter_exception('Unknown Certifier trigger: ' . $trigger);
        }

        $key = self::idempotency_key($instance, $userid, $trigger, $triggercmid);
        $existing = $DB->get_record('certifier_issuances', ['idempotencykey' => $key], 'id', IGNORE_MISSING);
        if ($existing) {
            return (int) $existing->id;
        }
        $now = time();
        $record = (object) [
            'certifierid' => $instance->id,
            'course' => $instance->course,
            'userid' => $userid,
            'triggercmid' => $triggercmid,
            'triggertype' => $trigger,
            'status' => self::STATUS_QUEUED,
            'attempts' => 0,
            'nextattempt' => $now,
            'idempotencykey' => $key,
            'groupid' => $groupid,
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
        ];
        try {
            $issuanceid = $DB->insert_record('certifier_issuances', $record);
        } catch (\dml_write_exception $exception) {
            $existing = $DB->get_record('certifier_issuances', ['idempotencykey' => $key], 'id', IGNORE_MISSING);
            if ($existing) {
                return (int) $existing->id;
            }
            throw $exception;
        }
        self::queue_task($issuanceid);
        return $issuanceid;
    }

    /**
     * Process a queued issuance row.
     *
     * @param int $issuanceid Issuance row id.
     */
    public static function process_issuance(int $issuanceid): void {
        global $DB;
        $issuance = $DB->get_record('certifier_issuances', ['id' => $issuanceid], '*', IGNORE_MISSING);
        if (!$issuance) {
            return;
        }
        if (!in_array($issuance->status, [self::STATUS_QUEUED, self::STATUS_FAILED_RETRYABLE], true)) {
            return;
        }
        if ((int) $issuance->nextattempt > time()) {
            self::queue_task($issuanceid, (int) $issuance->nextattempt);
            return;
        }
        if (!self::claim_issuance($issuance)) {
            // Another worker is already processing this row.
            return;
        }

        $instance = $DB->get_record('certifier', ['id' => $issuance->certifierid], '*', IGNORE_MISSING);
        $user = $DB->get_record('user', ['id' => $issuance->userid, 'deleted' => 0], '*', IGNORE_MISSING);
        $course = $DB->get_record('course', ['id' => $issuance->course], '*', IGNORE_MISSING);
        if (!$instance || !$user || !$course) {
            self::mark_permanent_failure(
                $issuance,
                'missingrecord',
                'Required Moodle record missing for issuance ' . $issuance->id
            );
            return;
        }

        $snapshot = self::build_recipient_snapshot($instance, $user, $course);
        $issuance->recipientname = $snapshot['name'];
        $issuance->recipientemail = $snapshot['email'];
        $issuance->customattributes = json_encode($snapshot['customAttributes']);
        $issuance->timemodified = time();
        $DB->update_record('certifier_issuances', $issuance);

        try {
            $client = self::get_client();
            if ($issuance->credentialid === '') {
                $credential = $client->create_credential(
                    (string) $instance->groupid,
                    $snapshot['name'],
                    $snapshot['email'],
                    $snapshot['customAttributes']
                );
                $credentialid = (string) ($credential['id'] ?? '');
                if ($credentialid === '') {
                    throw new api_exception(
                        'apimissingcredentialid',
                        'Create response did not include a credential id',
                        0,
                        '',
                        false
                    );
                }
                $issuance->credentialid = $credentialid;
                // Persist the learner URL now, while publicId is available on the
                // create response; issue/send may run in a later retry task where
                // it is gone. The view page hides the link until the credential is issued.
                $publicid = (string) ($credential['publicId'] ?? '');
                if ($issuance->credentialurl === '' && $publicid !== '') {
                    $issuance->credentialurl = constants::build_credential_url($publicid);
                }
                $issuance->status = self::STATUS_CREATED;
                $issuance->errorcode = '';
                $issuance->errormessage = '';
                $issuance->timemodified = time();
                $DB->update_record('certifier_issuances', $issuance);
            } else {
                $issuance->status = self::STATUS_CREATED;
                $issuance->errorcode = '';
                $issuance->errormessage = '';
            }

            if (
                in_array(
                    $instance->deliverymode,
                    [constants::DELIVERY_CREATE_ISSUE, constants::DELIVERY_CREATE_ISSUE_SEND],
                    true
                )
                && $issuance->credentialid !== '' && empty($issuance->timeissued)
            ) {
                $client->issue_credential($issuance->credentialid);
                $issuance->status = self::STATUS_ISSUED;
                $issuance->timeissued = time();
                $issuance->timemodified = time();
                $DB->update_record('certifier_issuances', $issuance);
                self::send_issued_notification($instance, $user, $course);
            } else if (!empty($issuance->timeissued)) {
                $issuance->status = self::STATUS_ISSUED;
            }
            if ($instance->deliverymode === constants::DELIVERY_CREATE_ISSUE_SEND) {
                if ($snapshot['email'] === '') {
                    $issuance->status = self::STATUS_SEND_SKIPPED;
                } else if ($issuance->credentialid !== '' && empty($issuance->timesent)) {
                    $client->send_credential($issuance->credentialid);
                    $issuance->status = self::STATUS_SENT;
                    $issuance->timesent = time();
                } else if (!empty($issuance->timesent)) {
                    $issuance->status = self::STATUS_SENT;
                }
            }
            $issuance->timemodified = time();
            $DB->update_record('certifier_issuances', $issuance);
        } catch (api_exception $exception) {
            self::mark_failed($issuance, $exception, $exception->retryable);
        } catch (\moodle_exception $exception) {
            self::mark_failed($issuance, $exception, false);
        }
    }

    /**
     * Send the learner a Moodle notification after successful credential issue.
     *
     * @param \stdClass $instance Certifier activity instance.
     * @param \stdClass $user Recipient user record.
     * @param \stdClass $course Course record.
     */
    private static function send_issued_notification(\stdClass $instance, \stdClass $user, \stdClass $course): void {
        $cm = get_coursemodule_from_instance('certifier', (int) $instance->id, (int) $course->id, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $message = new \core\message\message();
        $message->component = 'mod_certifier';
        $message->name = 'credentialissued';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $coursename = format_string($course->fullname);
        $message->subject = get_string('credentialissuedsubject', 'certifier');
        $message->fullmessage = get_string('credentialissuedbody', 'certifier', $coursename);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = text_to_html($message->fullmessage, false, false, true);
        $message->smallmessage = get_string('credentialissuedsmall', 'certifier', $coursename);
        $message->notification = 1;
        $message->contexturl = (new \moodle_url('/mod/certifier/view.php', ['id' => $cm->id]))->out(false);
        $message->contexturlname = format_string($instance->name);

        try {
            message_send($message);
        } catch (\moodle_exception $exception) {
            debugging('Failed to send Certifier credential notification: ' . $exception->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Atomically claim an issuance row for processing.
     *
     * Prevents two ad hoc workers from making duplicate API calls for the same
     * row. Idempotency key already blocks duplicate rows, but not concurrent
     * processing of a single row.
     *
     * @param \stdClass $issuance Issuance row (mutated to reflect PROCESSING state).
     * @return bool True when this worker successfully claimed the row.
     */
    private static function claim_issuance(\stdClass $issuance): bool {
        global $DB;
        $now = time();
        $claimed = $DB->execute(
            "UPDATE {certifier_issuances}
                SET status = :newstatus, attempts = attempts + 1, timemodified = :now
              WHERE id = :id
                AND status IN (:queued, :retryable)",
            [
                'newstatus' => self::STATUS_PROCESSING,
                'now' => $now,
                'id' => $issuance->id,
                'queued' => self::STATUS_QUEUED,
                'retryable' => self::STATUS_FAILED_RETRYABLE,
            ]
        );
        if (!$claimed) {
            return false;
        }
        $fresh = $DB->get_record('certifier_issuances', ['id' => $issuance->id], 'status,attempts,timemodified', IGNORE_MISSING);
        if (!$fresh || $fresh->status !== self::STATUS_PROCESSING) {
            return false;
        }
        $issuance->status = $fresh->status;
        $issuance->attempts = (int) $fresh->attempts;
        $issuance->timemodified = (int) $fresh->timemodified;
        return true;
    }

    /**
     * Mark an issuance as failed and queue retry when appropriate.
     *
     * @param \stdClass $issuance Issuance row.
     * @param \moodle_exception $exception Failure exception.
     * @param bool $retryable Whether the failure may be retried.
     */
    private static function mark_failed(\stdClass $issuance, \moodle_exception $exception, bool $retryable): void {
        global $DB;
        $shouldretry = $retryable && (int) $issuance->attempts < self::MAX_ATTEMPTS;
        $delay = min(3600, 60 * (2 ** max(0, ((int) $issuance->attempts) - 1)));
        $issuance->status = $shouldretry ? self::STATUS_FAILED_RETRYABLE : self::STATUS_FAILED_PERMANENT;
        $issuance->nextattempt = $shouldretry ? time() + $delay : 0;
        $issuance->errorcode = $exception->errorcode ?? 'error';
        $issuance->errormessage = shorten_text($exception->getMessage(), 1000);
        $issuance->timemodified = time();
        $DB->update_record('certifier_issuances', $issuance);
        if ($shouldretry) {
            self::queue_task((int) $issuance->id, (int) $issuance->nextattempt);
        }
    }

    /**
     * Mark an issuance as permanently failed without re-queueing.
     *
     * Used for state we know cannot be recovered by a retry (missing user,
     * activity, or course record).
     *
     * @param \stdClass $issuance Issuance row.
     * @param string $errorcode Short error code.
     * @param string $message Diagnostic message.
     */
    private static function mark_permanent_failure(\stdClass $issuance, string $errorcode, string $message): void {
        global $DB;
        $issuance->status = self::STATUS_FAILED_PERMANENT;
        $issuance->nextattempt = 0;
        $issuance->errorcode = $errorcode;
        $issuance->errormessage = shorten_text($message, 1000);
        $issuance->timemodified = time();
        $DB->update_record('certifier_issuances', $issuance);
    }

    /**
     * Queue an ad hoc task to process an issuance.
     *
     * @param int $issuanceid Issuance row id.
     * @param int|null $nextruntime Optional next run time.
     */
    private static function queue_task(int $issuanceid, ?int $nextruntime = null): void {
        $task = new \mod_certifier\task\issue_credential_task();
        $task->set_custom_data(['issuanceid' => $issuanceid]);
        if ($nextruntime !== null) {
            $task->set_next_run_time($nextruntime);
        }
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Build an idempotency key for one configured issuance.
     *
     * Multiple Certifier activity instances in one course may legitimately
     * issue multiple credentials, which the activity-instance id already
     * supports. Including the credential template (group) id additionally
     * means that on a future eligibility event a different template will
     * produce a new issuance; on its own, changing the template does not
     * enqueue anything because no eligibility event has fired.
     *
     * @param \stdClass $instance Certifier activity instance.
     * @param int $userid User id.
     * @param string $trigger Trigger type.
     * @param int $triggercmid Triggering course module id.
     * @return string
     */
    private static function idempotency_key(\stdClass $instance, int $userid, string $trigger, int $triggercmid): string {
        $parts = [
            'mod_certifier',
            $instance->id,
            $instance->course,
            $instance->groupid,
            $userid,
            $trigger,
            $triggercmid,
        ];
        return hash('sha256', implode(':', $parts));
    }

    /**
     * Build a recipient data snapshot from Moodle records.
     *
     * Recipient name is always Moodle `fullname($user)` and recipient email
     * is always `$user->email`; there is no per-instance mapping override.
     *
     * @param \stdClass $instance Certifier activity instance.
     * @param \stdClass $user User record.
     * @param \stdClass $course Course record.
     * @return array
     */
    private static function build_recipient_snapshot(\stdClass $instance, \stdClass $user, \stdClass $course): array {
        $customattributes = [];
        $mappings = json_decode($instance->customattributemappings ?? '{}', true);
        if (is_array($mappings)) {
            foreach ($mappings as $tag => $source) {
                $value = self::mapped_value((string) $source, $user, $course);
                if ($value !== '') {
                    // The Certifier API expects custom attribute keys to be prefixed with `custom.`.
                    $customattributes['custom.' . $tag] = $value;
                }
            }
        }
        return [
            'name' => fullname($user),
            'email' => (string) ($user->email ?? ''),
            'customAttributes' => $customattributes,
        ];
    }

    /**
     * Map a configured custom attribute value.
     *
     * @param string $source Mapping source.
     * @param \stdClass $user User record.
     * @param \stdClass $course Course record.
     * @return string
     */
    private static function mapped_value(string $source, \stdClass $user, \stdClass $course): string {
        switch ($source) {
            case 'user_fullname':
                return fullname($user);
            case 'user_firstname':
                return (string) $user->firstname;
            case 'user_lastname':
                return (string) $user->lastname;
            case 'user_email':
                return (string) $user->email;
            case 'user_username':
                return (string) $user->username;
            case 'course_fullname':
                return (string) $course->fullname;
            case 'course_shortname':
                return (string) $course->shortname;
            case 'course_idnumber':
                return (string) $course->idnumber;
            default:
                return '';
        }
    }
}
