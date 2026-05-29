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
 * Admin settings for the Certifier activity module.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new \mod_certifier\admin_setting\apiurl(
        'mod_certifier/apiurl',
        get_string('apiurl', 'certifier'),
        get_string('apiurl_desc', 'certifier'),
        'https://api.certifier.io/v1',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'mod_certifier/apikey',
        get_string('apikey', 'certifier'),
        get_string('apikey_desc', 'certifier'),
        ''
    ));

    $settings->add(new \mod_certifier\admin_setting\apiurl(
        'mod_certifier/issuerportaldomain',
        get_string('issuerportaldomain', 'certifier'),
        get_string('issuerportaldomain_desc', 'certifier'),
        'https://credsverse.com',
        PARAM_URL
    ));
}
