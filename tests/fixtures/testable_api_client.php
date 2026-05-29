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
 * Test double for the Certifier API client transport.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_certifier\local;

/**
 * Test double for api_client transport.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_api_client extends api_client {
    /** @var array[] Queued transport responses. */
    public $responses = [];
    /** @var array[] Captured requests. */
    public $requests = [];

    /**
     * Create a test client with explicit config.
     */
    public function __construct() {
        parent::__construct('https://api.certifier.test', 'test-api-key');
    }

    /**
     * Queue one transport response.
     *
     * @param array $response Transport response.
     */
    public function queue_response(array $response): void {
        $this->responses[] = $response;
    }

    /**
     * Capture the request and return a queued response.
     *
     * @param string $method HTTP method.
     * @param string $url Absolute request URL.
     * @param array $headers HTTP headers.
     * @param string|null $payload JSON request payload.
     * @return array{errno:int,error:string,status:int,body:string|false}
     */
    protected function execute_http_request(string $method, string $url, array $headers, ?string $payload = null): array {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'payload' => $payload,
        ];
        if (!$this->responses) {
            throw new \coding_exception('No queued API response for test');
        }
        return array_shift($this->responses);
    }
}
