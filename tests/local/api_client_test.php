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
 * Tests for the Certifier API client.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_certifier\local;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/testable_api_client.php');

/**
 * Tests for the Certifier API client.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_certifier\local\api_client
 */
final class api_client_test extends \advanced_testcase {
    /**
     * Valid API URLs are accepted.
     */
    public function test_is_valid_api_url_accepts_http_and_https(): void {
        $this->assertTrue(api_client::is_valid_api_url('https://api.certifier.test'));
        $this->assertTrue(api_client::is_valid_api_url('http://localhost:8080/api'));
    }

    /**
     * Invalid API URLs are rejected.
     */
    public function test_is_valid_api_url_rejects_invalid_urls(): void {
        $this->assertFalse(api_client::is_valid_api_url(''));
        $this->assertFalse(api_client::is_valid_api_url('not-a-url'));
        $this->assertFalse(api_client::is_valid_api_url('ftp://api.certifier.test'));
    }

    /**
     * Creating a credential builds the expected request payload and headers.
     */
    public function test_create_credential_builds_expected_request(): void {
        $client = new testable_api_client();
        $client->queue_response([
            'errno' => 0,
            'error' => '',
            'status' => 201,
            'body' => json_encode(['id' => 'credential-1']),
        ]);

        $result = $client->create_credential('group-1', 'Ada Lovelace', 'ada@example.test', [
            'custom.course' => 'Course One',
        ]);

        $this->assertEquals(['id' => 'credential-1'], $result);
        $this->assertCount(1, $client->requests);
        $request = $client->requests[0];
        $this->assertEquals('POST', $request['method']);
        $this->assertEquals('https://api.certifier.test/credentials', $request['url']);
        $this->assertContains('Accept: application/json', $request['headers']);
        $this->assertContains('Authorization: Bearer test-api-key', $request['headers']);
        $this->assertContains('Certifier-Version: 2022-10-26', $request['headers']);
        $this->assertContains('Content-Type: application/json', $request['headers']);

        $payload = json_decode((string) $request['payload'], true);
        $this->assertEquals([
            'groupId' => 'group-1',
            'recipient' => [
                'id' => null,
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.test',
            ],
            'customAttributes' => [
                'custom.course' => 'Course One',
            ],
        ], $payload);
    }

    /**
     * Issue and send requests target the expected credential endpoints.
     */
    public function test_issue_and_send_encode_credential_id_in_path(): void {
        $client = new testable_api_client();
        $client->queue_response([
            'errno' => 0,
            'error' => '',
            'status' => 200,
            'body' => json_encode(['id' => 'credential/1']),
        ]);
        $client->queue_response([
            'errno' => 0,
            'error' => '',
            'status' => 200,
            'body' => json_encode(['id' => 'credential/1']),
        ]);

        $client->issue_credential('credential/1');
        $client->send_credential('credential/1');

        $this->assertCount(2, $client->requests);
        $this->assertEquals('https://api.certifier.test/credentials/credential%2F1/issue', $client->requests[0]['url']);
        $this->assertEquals('https://api.certifier.test/credentials/credential%2F1/send', $client->requests[1]['url']);
        $this->assertEquals(['deliveryMethod' => 'email'], json_decode((string) $client->requests[1]['payload'], true));
    }

    /**
     * Group fetch follows pagination links and normalises entries.
     */
    public function test_get_groups_follows_pagination(): void {
        $client = new testable_api_client();
        $client->queue_response([
            'errno' => 0,
            'error' => '',
            'status' => 200,
            'body' => json_encode([
                'data' => [
                    ['id' => 'group-1', 'name' => 'Group One'],
                ],
                'pagination' => ['next' => 'cursor-2'],
            ]),
        ]);
        $client->queue_response([
            'errno' => 0,
            'error' => '',
            'status' => 200,
            'body' => json_encode([
                'data' => [
                    ['id' => 'group-2'],
                ],
                'pagination' => ['next' => null],
            ]),
        ]);

        $groups = $client->get_groups();

        $this->assertEquals([
            ['id' => 'group-1', 'name' => 'Group One'],
            ['id' => 'group-2', 'name' => 'group-2'],
        ], $groups);
        $this->assertEquals('https://api.certifier.test/groups', $client->requests[0]['url']);
        $this->assertEquals('https://api.certifier.test/groups?cursor=cursor-2', $client->requests[1]['url']);
    }

    /**
     * Custom attribute fetch excludes default attributes and malformed rows.
     */
    public function test_get_custom_attributes_filters_default_attributes(): void {
        $client = new testable_api_client();
        $client->queue_response([
            'errno' => 0,
            'error' => '',
            'status' => 200,
            'body' => json_encode([
                ['tag' => 'course', 'name' => 'Course name'],
                ['tag' => 'default', 'name' => 'Default attribute', 'isDefault' => true],
                ['name' => 'Missing tag'],
                'not-an-array',
            ]),
        ]);

        $attributes = $client->get_custom_attributes();

        $this->assertEquals([
            ['tag' => 'course', 'name' => 'Course name'],
        ], $attributes);
    }

    /**
     * Invalid JSON responses are rejected.
     */
    public function test_invalid_json_response_throws_apiinvalidresponse(): void {
        $client = new testable_api_client();
        $client->queue_response([
            'errno' => 0,
            'error' => '',
            'status' => 200,
            'body' => '{invalid json',
        ]);

        try {
            $client->get_custom_attributes();
            $this->fail('Expected api_exception was not thrown');
        } catch (api_exception $exception) {
            $this->assertEquals('apiinvalidresponse', $exception->errorcode);
            $this->assertEquals(200, $exception->httpstatus);
            $this->assertFalse($exception->retryable);
        }
    }

    /**
     * Retryable HTTP statuses produce retryable API exceptions.
     */
    public function test_retryable_http_status_throws_retryable_exception(): void {
        $client = new testable_api_client();
        $client->queue_response([
            'errno' => 0,
            'error' => '',
            'status' => 503,
            'body' => '{"error":"temporary"}',
        ]);

        try {
            $client->issue_credential('credential-1');
            $this->fail('Expected api_exception was not thrown');
        } catch (api_exception $exception) {
            $this->assertEquals('apiunexpectedstatus', $exception->errorcode);
            $this->assertEquals(503, $exception->httpstatus);
            $this->assertTrue($exception->retryable);
        }
    }

    /**
     * Non-retryable HTTP statuses produce permanent API exceptions.
     */
    public function test_non_retryable_http_status_throws_permanent_exception(): void {
        $client = new testable_api_client();
        $client->queue_response([
            'errno' => 0,
            'error' => '',
            'status' => 400,
            'body' => '{"error":"bad request"}',
        ]);

        try {
            $client->send_credential('credential-1');
            $this->fail('Expected api_exception was not thrown');
        } catch (api_exception $exception) {
            $this->assertEquals('apiunexpectedstatus', $exception->errorcode);
            $this->assertEquals(400, $exception->httpstatus);
            $this->assertFalse($exception->retryable);
        }
    }
}
