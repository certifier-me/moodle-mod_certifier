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
 * Shared trigger and delivery-mode constants for the Certifier activity module.
 *
 * Centralising these strings ensures the form, observer, issuer, schema defaults,
 * and language layer agree on the canonical values.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class constants {
    /** Create the credential as draft. */
    public const DELIVERY_CREATE = 'create';
    /** Create and issue the credential. */
    public const DELIVERY_CREATE_ISSUE = 'create_issue';
    /** Create, issue, and send the credential. */
    public const DELIVERY_CREATE_ISSUE_SEND = 'create_issue_send';

    /** Trigger issuance on course completion. */
    public const TRIGGER_COURSE_COMPLETION = 'course_completion';
    /** Trigger issuance on configured activity completion. */
    public const TRIGGER_ACTIVITY_COMPLETION = 'activity_completion';

    /** Default learner-facing issuer portal URL. */
    public const DEFAULT_ISSUER_PORTAL_DOMAIN = 'https://credsverse.com';

    /**
     * All allowed delivery mode values.
     *
     * @return string[]
     */
    public static function delivery_modes(): array {
        return [
            self::DELIVERY_CREATE,
            self::DELIVERY_CREATE_ISSUE,
            self::DELIVERY_CREATE_ISSUE_SEND,
        ];
    }

    /**
     * All allowed trigger type values.
     *
     * @return string[]
     */
    public static function trigger_types(): array {
        return [
            self::TRIGGER_COURSE_COMPLETION,
            self::TRIGGER_ACTIVITY_COMPLETION,
        ];
    }

    /**
     * Get the learner-facing issuer portal base URL.
     *
     * @return string
     */
    public static function issuer_portal_domain(): string {
        $domain = trim((string) get_config('mod_certifier', 'issuerportaldomain'));
        if ($domain === '' || !api_client::is_valid_api_url($domain)) {
            $domain = self::DEFAULT_ISSUER_PORTAL_DOMAIN;
        }
        return rtrim($domain, '/');
    }

    /**
     * Build the public learner-facing credential URL from a Certifier credential UUID.
     *
     * @param string $credentialuuid Certifier credential UUID/id.
     * @return string Public credential URL, or empty string when no UUID is known.
     */
    public static function build_credential_url(string $credentialuuid): string {
        $credentialuuid = trim($credentialuuid);
        if ($credentialuuid === '') {
            return '';
        }
        return self::issuer_portal_domain() . '/credentials/' . rawurlencode($credentialuuid);
    }
}
