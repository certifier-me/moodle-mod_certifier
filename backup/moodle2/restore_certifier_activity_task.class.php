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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/certifier/backup/moodle2/restore_certifier_stepslib.php');

/**
 * Restore task for the Certifier activity module.
 *
 * @package    mod_certifier
 * @category   backup
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_certifier_activity_task extends restore_activity_task {
    /**
     * Define restore settings.
     */
    protected function define_my_settings() {
    }

    /**
     * Define restore steps.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_certifier_activity_structure_step('certifier_structure', 'certifier.xml'));
    }

    /**
     * Define decoded content rules.
     *
     * @return array
     */
    public static function define_decode_contents() {
        return [];
    }

    /**
     * Define decoded link rules.
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [];
    }
}
