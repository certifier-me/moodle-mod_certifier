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
 * Structured exception type raised by the Certifier API client.
 *
 * Carries HTTP status, raw response body, and an explicit retryability flag so
 * issuer logic can branch on properties instead of parsing HTTP status out of
 * exception messages.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_exception extends \moodle_exception {
    /** @var int HTTP status code (0 when no response was received). */
    public $httpstatus;
    /** @var string Raw response body (truncated). */
    public $responsebody;
    /** @var bool Whether the caller may safely retry. */
    public $retryable;

    /**
     * Build a structured API exception.
     *
     * @param string $errorcode Moodle language-string key.
     * @param string $debuginfo Short debug info appended to the message.
     * @param int $httpstatus HTTP status (0 for transport errors).
     * @param string $responsebody Raw response body.
     * @param bool $retryable Whether the failure is safe to retry.
     */
    public function __construct(
        string $errorcode,
        string $debuginfo = '',
        int $httpstatus = 0,
        string $responsebody = '',
        bool $retryable = false
    ) {
        parent::__construct($errorcode, 'certifier', '', $httpstatus ?: null, $debuginfo);
        $this->httpstatus = $httpstatus;
        $this->responsebody = $responsebody;
        $this->retryable = $retryable;
    }

    /**
     * Decide whether a given HTTP status is safe to retry.
     *
     * Retry 408 (request timeout), 429 (rate limited), and 5xx responses.
     *
     * @param int $status HTTP status code.
     * @return bool
     */
    public static function is_retryable_status(int $status): bool {
        if ($status === 408 || $status === 429) {
            return true;
        }
        return $status >= 500 && $status < 600;
    }
}
