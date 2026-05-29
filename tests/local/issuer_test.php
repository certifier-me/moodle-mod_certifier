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
 * Tests for Certifier issuance processing.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_certifier\local;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/mock_api_client.php');

/**
 * Tests for Certifier issuance processing.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_certifier\local\issuer
 */
final class issuer_test extends \advanced_testcase {
    /**
     * Reset the API client factory after each test.
     */
    protected function tearDown(): void {
        issuer::set_client_factory(null);
        parent::tearDown();
    }

    /**
     * Use a fixed API client for issuer processing.
     *
     * @param mock_api_client $client Client to return from the factory.
     */
    private function use_client(mock_api_client $client): void {
        issuer::set_client_factory(static function () use ($client): api_client {
            return $client;
        });
    }

    /**
     * Queueing an issuance creates one audit/queue row.
     */
    public function test_queue_issuance_creates_single_row(): void {
        global $DB;
        $this->resetAfterTest(true);
        [$course, $user, $instance] = $this->create_fixture();

        $issuanceid = issuer::queue_issuance($instance, (int) $user->id, constants::TRIGGER_COURSE_COMPLETION);

        $issuance = $DB->get_record('certifier_issuances', ['id' => $issuanceid], '*', MUST_EXIST);
        $this->assertEquals($instance->id, $issuance->certifierid);
        $this->assertEquals($course->id, $issuance->course);
        $this->assertEquals($user->id, $issuance->userid);
        $this->assertEquals(issuer::STATUS_QUEUED, $issuance->status);
    }

    /**
     * Queueing the same issuance twice returns the existing row.
     */
    public function test_queue_issuance_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest(true);
        [, $user, $instance] = $this->create_fixture();

        $firstid = issuer::queue_issuance($instance, (int) $user->id, constants::TRIGGER_COURSE_COMPLETION);
        $secondid = issuer::queue_issuance($instance, (int) $user->id, constants::TRIGGER_COURSE_COMPLETION);

        $this->assertEquals($firstid, $secondid);
        $this->assertEquals(1, $DB->count_records('certifier_issuances', [
            'certifierid' => $instance->id,
            'userid' => $user->id,
        ]));
    }

    /**
     * Create-only delivery creates a draft credential without notifying the learner.
     */
    public function test_create_only_does_not_notify(): void {
        global $DB;
        $this->resetAfterTest(true);
        [, $user, $instance] = $this->create_fixture(constants::DELIVERY_CREATE);
        $client = new mock_api_client();
        $this->use_client($client);
        $issuanceid = issuer::queue_issuance($instance, (int) $user->id, constants::TRIGGER_COURSE_COMPLETION);
        $sink = $this->redirectMessages();

        issuer::process_issuance($issuanceid);

        $messages = $sink->get_messages();
        $sink->close();
        $issuance = $DB->get_record('certifier_issuances', ['id' => $issuanceid], '*', MUST_EXIST);
        $this->assertEquals(issuer::STATUS_CREATED, $issuance->status);
        // The URL is stored as soon as the draft exists; view.php gates display until issued.
        $this->assertStringEndsWith('/credentials/public-1', $issuance->credentialurl);
        $this->assertCount(0, $messages);
        $this->assertEquals(['create'], $client->calls);
    }

    /**
     * Learner-facing credential URLs honour the configured issuer portal domain once issued.
     */
    public function test_issue_uses_custom_issuer_portal_domain(): void {
        global $DB;
        $this->resetAfterTest(true);
        set_config('issuerportaldomain', 'https://credentials.example.test', 'mod_certifier');
        [, $user, $instance] = $this->create_fixture(constants::DELIVERY_CREATE_ISSUE);
        $client = new mock_api_client();
        $this->use_client($client);
        $issuanceid = issuer::queue_issuance($instance, (int) $user->id, constants::TRIGGER_COURSE_COMPLETION);

        issuer::process_issuance($issuanceid);

        $issuance = $DB->get_record('certifier_issuances', ['id' => $issuanceid], '*', MUST_EXIST);
        $this->assertEquals('https://credentials.example.test/credentials/public-1', $issuance->credentialurl);
    }

    /**
     * Create+issue delivery notifies the learner once.
     */
    public function test_create_issue_notifies_once(): void {
        global $DB;
        $this->resetAfterTest(true);
        [$course, $user, $instance] = $this->create_fixture(constants::DELIVERY_CREATE_ISSUE);
        $client = new mock_api_client();
        $this->use_client($client);
        $issuanceid = issuer::queue_issuance($instance, (int) $user->id, constants::TRIGGER_COURSE_COMPLETION);
        $sink = $this->redirectMessages();

        issuer::process_issuance($issuanceid);

        $messages = $sink->get_messages();
        $sink->close();
        $issuance = $DB->get_record('certifier_issuances', ['id' => $issuanceid], '*', MUST_EXIST);
        $this->assertEquals(issuer::STATUS_ISSUED, $issuance->status);
        $this->assertCount(1, $messages);
        $this->assertEquals('Your credential for ' . $course->fullname . ' has been issued.', $messages[0]->fullmessage);
        $this->assertStringEndsWith('/mod/certifier/view.php?id=' . $instance->cmid, $messages[0]->contexturl);
        $this->assertEquals(['create', 'issue'], $client->calls);
    }

    /**
     * Create+issue+send delivery sends one Moodle notification, not two.
     */
    public function test_create_issue_send_notifies_once(): void {
        global $DB;
        $this->resetAfterTest(true);
        [, $user, $instance] = $this->create_fixture(constants::DELIVERY_CREATE_ISSUE_SEND);
        $client = new mock_api_client();
        $this->use_client($client);
        $issuanceid = issuer::queue_issuance($instance, (int) $user->id, constants::TRIGGER_COURSE_COMPLETION);
        $sink = $this->redirectMessages();

        issuer::process_issuance($issuanceid);

        $messages = $sink->get_messages();
        $sink->close();
        $issuance = $DB->get_record('certifier_issuances', ['id' => $issuanceid], '*', MUST_EXIST);
        $this->assertEquals(issuer::STATUS_SENT, $issuance->status);
        $this->assertCount(1, $messages);
        $this->assertEquals(['create', 'issue', 'send'], $client->calls);
    }

    /**
     * Re-processing an already-issued row does not notify again.
     */
    public function test_already_issued_does_not_notify_again(): void {
        global $DB;
        $this->resetAfterTest(true);
        [, $user, $instance] = $this->create_fixture(constants::DELIVERY_CREATE_ISSUE);
        $issuanceid = issuer::queue_issuance($instance, (int) $user->id, constants::TRIGGER_COURSE_COMPLETION);
        $issuance = $DB->get_record('certifier_issuances', ['id' => $issuanceid], '*', MUST_EXIST);
        $issuance->credentialid = 'credential-existing';
        $issuance->status = issuer::STATUS_QUEUED;
        $issuance->timeissued = time() - 60;
        $DB->update_record('certifier_issuances', $issuance);
        $client = new mock_api_client();
        $this->use_client($client);
        $sink = $this->redirectMessages();

        issuer::process_issuance($issuanceid);

        $messages = $sink->get_messages();
        $sink->close();
        $issuance = $DB->get_record('certifier_issuances', ['id' => $issuanceid], '*', MUST_EXIST);
        $this->assertEquals(issuer::STATUS_ISSUED, $issuance->status);
        $this->assertCount(0, $messages);
        $this->assertEquals([], $client->calls);
    }

    /**
     * A credential created in one run keeps its learner URL when issue succeeds in a later run.
     */
    public function test_credential_url_survives_create_and_issue_in_separate_runs(): void {
        global $DB;
        $this->resetAfterTest(true);
        set_config('issuerportaldomain', 'https://credentials.example.test', 'mod_certifier');
        [, $user, $instance] = $this->create_fixture(constants::DELIVERY_CREATE_ISSUE);
        $client = new mock_api_client();
        $client->issueexception = new api_exception('apirequestfailed', 'Temporary failure', 500, '', true);
        $this->use_client($client);
        $issuanceid = issuer::queue_issuance($instance, (int) $user->id, constants::TRIGGER_COURSE_COMPLETION);

        issuer::process_issuance($issuanceid);

        $issuance = $DB->get_record('certifier_issuances', ['id' => $issuanceid], '*', MUST_EXIST);
        $this->assertEquals(issuer::STATUS_FAILED_RETRYABLE, $issuance->status);
        $this->assertEquals('credential-1', $issuance->credentialid);
        $this->assertEquals('https://credentials.example.test/credentials/public-1', $issuance->credentialurl);

        $client->issueexception = null;
        $issuance->nextattempt = time() - 1;
        $DB->update_record('certifier_issuances', $issuance);

        issuer::process_issuance($issuanceid);

        $issuance = $DB->get_record('certifier_issuances', ['id' => $issuanceid], '*', MUST_EXIST);
        $this->assertEquals(issuer::STATUS_ISSUED, $issuance->status);
        $this->assertEquals('https://credentials.example.test/credentials/public-1', $issuance->credentialurl);
        $this->assertEquals(['create', 'issue', 'issue'], $client->calls);
    }

    /**
     * Retryable API failures are marked for another attempt.
     */
    public function test_retryable_api_failure_marks_retryable(): void {
        global $DB;
        $this->resetAfterTest(true);
        [, $user, $instance] = $this->create_fixture();
        $client = new mock_api_client();
        $client->createexception = new api_exception('apirequestfailed', 'Temporary failure', 500, '', true);
        $this->use_client($client);
        $issuanceid = issuer::queue_issuance($instance, (int) $user->id, constants::TRIGGER_COURSE_COMPLETION);
        $before = time();

        issuer::process_issuance($issuanceid);

        $issuance = $DB->get_record('certifier_issuances', ['id' => $issuanceid], '*', MUST_EXIST);
        $this->assertEquals(issuer::STATUS_FAILED_RETRYABLE, $issuance->status);
        $this->assertGreaterThan($before, (int) $issuance->nextattempt);
    }

    /**
     * Non-retryable API failures are marked permanent.
     */
    public function test_permanent_api_failure_marks_permanent(): void {
        global $DB;
        $this->resetAfterTest(true);
        [, $user, $instance] = $this->create_fixture();
        $client = new mock_api_client();
        $client->createexception = new api_exception('apiunexpectedstatus', 'Bad request', 400, '', false);
        $this->use_client($client);
        $issuanceid = issuer::queue_issuance($instance, (int) $user->id, constants::TRIGGER_COURSE_COMPLETION);

        issuer::process_issuance($issuanceid);

        $issuance = $DB->get_record('certifier_issuances', ['id' => $issuanceid], '*', MUST_EXIST);
        $this->assertEquals(issuer::STATUS_FAILED_PERMANENT, $issuance->status);
        $this->assertEquals(0, (int) $issuance->nextattempt);
    }

    /**
     * Create the Moodle records required for issuer tests.
     *
     * @param string $deliverymode Delivery mode.
     * @return array{0:\stdClass,1:\stdClass,2:\stdClass}
     */
    private function create_fixture(string $deliverymode = constants::DELIVERY_CREATE_ISSUE_SEND): array {
        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Course One',
            'shortname' => 'C1',
            'idnumber' => 'COURSE-1',
        ]);
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'email' => 'ada@example.test',
        ]);
        $instance = $this->getDataGenerator()->create_module('certifier', [
            'course' => $course->id,
            'name' => 'Course credential',
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'groupid' => 'group-1',
            'deliverymode' => $deliverymode,
            'triggertype' => constants::TRIGGER_COURSE_COMPLETION,
            'triggercmid' => 0,
            'requirepassinggrade' => 0,
            'minimumgrade' => 0,
            'customattributemappings' => json_encode(['course' => 'course_fullname']),
        ]);
        return [$course, $user, $instance];
    }
}
