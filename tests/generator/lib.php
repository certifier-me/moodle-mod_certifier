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
 * Certifier module data generator.
 *
 * @package    mod_certifier
 * @category   test
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Certifier module data generator class.
 *
 * @package    mod_certifier
 * @category   test
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_certifier_generator extends testing_module_generator {
    /**
     * Create a Certifier activity instance.
     *
     * @param stdClass|array|null $record Instance data.
     * @param array|null $options Module creation options.
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (array) $record + [
            'name' => 'Certifier credential',
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'groupid' => 'group-1',
            'deliverymode' => \mod_certifier\local\constants::DELIVERY_CREATE_ISSUE_SEND,
            'triggertype' => \mod_certifier\local\constants::TRIGGER_COURSE_COMPLETION,
            'triggercmid' => 0,
            'requirepassinggrade' => 0,
            'minimumgrade' => 0,
            'customattributemappings' => [],
        ];

        return parent::create_instance($record, (array) $options);
    }
}
