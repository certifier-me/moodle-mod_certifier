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
 * Certifier API client.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_client {
    /** Certifier API version header value. */
    private const API_VERSION = '2022-10-26';
    /** @var string Certifier API base URL. */
    private $apiurl;
    /** @var string Certifier API key. */
    private $apikey;

    /**
     * Create an API client.
     *
     * @param string|null $apiurl Optional API base URL override.
     * @param string|null $apikey Optional API key override.
     */
    public function __construct(?string $apiurl = null, ?string $apikey = null) {
        $this->apiurl = rtrim($apiurl ?? (string) get_config('mod_certifier', 'apiurl'), '/');
        $this->apikey = $apikey ?? (string) get_config('mod_certifier', 'apikey');
    }

    /**
     * Check whether the API client has proper configuration to make requests.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return $this->apiurl !== '' && $this->apikey !== '' && self::is_valid_api_url($this->apiurl);
    }

    /**
     * Defensive check that an API base URL is well-formed and uses http(s).
     *
     * Admin settings already use PARAM_URL; this is a second-line check at the
     * client boundary so a malformed override or stale config still fails
     * loudly instead of silently calling a wrong URL.
     *
     * @param string $url Candidate URL.
     * @return bool
     */
    public static function is_valid_api_url(string $url): bool {
        if ($url === '') {
            return false;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true);
    }

    /**
     * Fetch available Certifier credential template (group) entries.
     *
     * @return array List of `['id' => string, 'name' => string]` rows.
     */
    public function get_groups(): array {
        $groups = [];
        $cursor = null;
        do {
            $response = $this->request('GET', '/groups', $cursor ? ['cursor' => $cursor] : []);
            if (!isset($response['data']) || !is_array($response['data'])) {
                throw new api_exception('apiunexpectedshape', 'groups: missing data array', 0, '', false);
            }
            foreach ($response['data'] as $group) {
                if (!empty($group['id'])) {
                    $groups[] = [
                        'id' => (string) $group['id'],
                        'name' => (string) ($group['name'] ?? $group['id']),
                    ];
                }
            }
            $cursor = $response['pagination']['next'] ?? null;
        } while ($cursor);
        return $groups;
    }

    /**
     * Fetch custom attributes that can be mapped by teachers.
     *
     * @return array
     */
    public function get_custom_attributes(): array {
        $response = $this->request('GET', '/attributes');
        $attributes = [];
        foreach ($response as $attribute) {
            if (!is_array($attribute)) {
                continue;
            }
            if (!empty($attribute['isDefault']) || empty($attribute['tag'])) {
                continue;
            }
            $attributes[] = [
                'tag' => (string) $attribute['tag'],
                'name' => (string) ($attribute['name'] ?? $attribute['tag']),
            ];
        }
        return $attributes;
    }

    /**
     * Create a draft credential.
     *
     * @param string $groupid Credential template (group) id.
     * @param string $recipientname Recipient name.
     * @param string $recipientemail Recipient email.
     * @param array $customattributes Custom attributes.
     * @return array
     */
    public function create_credential(
        string $groupid,
        string $recipientname,
        string $recipientemail,
        array $customattributes = []
    ): array {
        return $this->request('POST', '/credentials', [], [
            'groupId' => $groupid,
            'recipient' => ['id' => null, 'name' => $recipientname, 'email' => $recipientemail],
            'customAttributes' => (object) $customattributes,
        ]);
    }

    /**
     * Issue a credential.
     *
     * @param string $credentialid Credential id.
     * @return array
     */
    public function issue_credential(string $credentialid): array {
        return $this->request('POST', '/credentials/' . rawurlencode($credentialid) . '/issue');
    }

    /**
     * Send a credential (by email).
     *
     * @param string $credentialid Credential id.
     * @return array
     */
    public function send_credential(string $credentialid): array {
        return $this->request('POST', '/credentials/' . rawurlencode($credentialid) . '/send', [], [
            'deliveryMethod' => 'email',
        ]);
    }

    /**
     * Send a HTTP request to Certifier.
     *
     * @param string $method HTTP method.
     * @param string $path API path.
     * @param array $params Query parameters.
     * @param array|null $body Request body.
     * @return array
     */
    private function request(string $method, string $path, array $params = [], ?array $body = null): array {
        if (!self::is_valid_api_url($this->apiurl) || $this->apikey === '') {
            throw new api_exception('apiclientnotconfigured', '', 0, '', false);
        }

        $url = $this->apiurl . $path;
        if ($params) {
            $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apikey,
            'Certifier-Version: ' . self::API_VERSION,
        ];

        $payload = null;
        if ($method === 'POST') {
            $payload = json_encode($body ?? [], JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        }

        $response = $this->execute_http_request($method, $url, $headers, $payload);
        $errno = (int) ($response['errno'] ?? 0);
        $error = (string) ($response['error'] ?? '');
        $status = (int) ($response['status'] ?? 0);
        $raw = $response['body'] ?? false;

        if ($errno) {
            throw new api_exception('apirequestfailed', $error, 0, '', true);
        }
        if ($raw === false) {
            throw new api_exception('apirequestfailed', 'Empty cURL response', 0, '', true);
        }

        $body = (string) $raw;
        if ($status < 200 || $status >= 300) {
            throw new api_exception(
                'apiunexpectedstatus',
                $status . ': ' . shorten_text($body, 1000),
                $status,
                $body,
                api_exception::is_retryable_status($status)
            );
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            $jsonerror = json_last_error_msg();
            throw new api_exception(
                'apiinvalidresponse',
                $jsonerror !== '' ? $jsonerror : 'json decode failed',
                $status,
                $body,
                false
            );
        }
        return $decoded;
    }

    /**
     * Execute one prepared HTTP request.
     *
     * Isolated as a protected method so tests can stub transport.
     *
     * @param string $method HTTP method.
     * @param string $url Absolute request URL.
     * @param array $headers HTTP headers.
     * @param string|null $payload JSON payload for POST requests.
     * @return array{errno:int,error:string,status:int,body:string|false}
     */
    protected function execute_http_request(string $method, string $url, array $headers, ?string $payload = null): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $curl->setHeader($headers);
        $options = [
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_FOLLOWLOCATION' => 0,
        ];

        if ($method === 'POST') {
            $raw = $curl->post($url, $payload ?? '', $options);
        } else {
            $raw = $curl->get($url, [], $options);
        }

        $errno = (int) $curl->get_errno();
        $info = $curl->get_info();
        return [
            'errno' => $errno,
            'error' => (string) $curl->error,
            'status' => (int) ($info['http_code'] ?? 0),
            'body' => $errno ? false : $raw,
        ];
    }
}
