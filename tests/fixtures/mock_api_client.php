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
 * Mock Certifier API client for issuer tests.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_certifier\local;

/**
 * Mock Certifier API client for issuer tests.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mock_api_client extends api_client {
    /** @var string[] Ordered API method calls. */
    public $calls = [];
    /** @var api_exception|null Exception to throw from create_credential. */
    public $createexception = null;
    /** @var api_exception|null Exception to throw from issue_credential. */
    public $issueexception = null;
    /** @var api_exception|null Exception to throw from send_credential. */
    public $sendexception = null;

    /**
     * Avoid reading real plugin configuration.
     */
    public function __construct() {
    }

    /**
     * Mock credential creation.
     *
     * @param string $groupid Credential template id.
     * @param string $recipientname Recipient name.
     * @param string $recipientemail Recipient email.
     * @param array $customattributes Custom attributes.
     * @return array
     * @throws api_exception
     */
    public function create_credential(
        string $groupid,
        string $recipientname,
        string $recipientemail,
        array $customattributes = []
    ): array {
        $this->calls[] = 'create';
        if ($this->createexception) {
            throw $this->createexception;
        }
        return ['id' => 'credential-1', 'publicId' => 'public-1'];
    }

    /**
     * Mock credential issue.
     *
     * @param string $credentialid Credential id.
     * @return array
     * @throws api_exception
     */
    public function issue_credential(string $credentialid): array {
        $this->calls[] = 'issue';
        if ($this->issueexception) {
            throw $this->issueexception;
        }
        return ['id' => $credentialid];
    }

    /**
     * Mock credential send.
     *
     * @param string $credentialid Credential id.
     * @return array
     * @throws api_exception
     */
    public function send_credential(string $credentialid): array {
        $this->calls[] = 'send';
        if ($this->sendexception) {
            throw $this->sendexception;
        }
        return ['id' => $credentialid];
    }
}
