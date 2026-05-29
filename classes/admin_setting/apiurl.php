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

namespace mod_certifier\admin_setting;

use mod_certifier\local\api_client;

/**
 * Admin URL setting that defensively rejects non-http(s) or malformed URLs.
 *
 * Used for both the Certifier API base URL and the learner-facing issuer
 * portal domain.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apiurl extends \admin_setting_configtext {
    /**
     * Validate the submitted URL.
     *
     * @param string $data Submitted value.
     * @return string|true True on success, error string otherwise.
     */
    public function validate($data) {
        $parent = parent::validate($data);
        if ($parent !== true) {
            return $parent;
        }
        if ($data === '') {
            return true;
        }
        if (!api_client::is_valid_api_url($data)) {
            return get_string('url_invalid', 'certifier');
        }
        return true;
    }
}
