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
 * External service definitions for the Certifier activity module.
 *
 * @package    mod_certifier
 * @category   external
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_certifier_get_groups' => [
        'classname' => 'mod_certifier\\external\\get_groups',
        'classpath' => '',
        'methodname' => 'execute',
        'description' => 'Fetch Certifier credential template options for the activity edit form.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/certifier:addinstance, moodle/course:manageactivities',
    ],
    'mod_certifier_get_custom_attributes' => [
        'classname' => 'mod_certifier\\external\\get_custom_attributes',
        'classpath' => '',
        'methodname' => 'execute',
        'description' => 'Fetch Certifier custom attribute options for the activity edit form.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/certifier:addinstance, moodle/course:manageactivities',
    ],
];
