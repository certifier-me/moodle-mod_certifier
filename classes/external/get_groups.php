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
 * External API for loading Certifier credential template options.
 *
 * @package    mod_certifier
 * @category   external
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_certifier\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use mod_certifier\local\api_client;

/**
 * External API for loading Certifier credential template options.
 *
 * @package    mod_certifier
 * @category   external
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_groups extends external_api {
    /**
     * Describe the service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    /**
     * Fetch credential template options for a course activity form.
     *
     * @param int $courseid Course id.
     * @return array
     */
    public static function execute(int $courseid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        require_login($course);
        $context = \context_course::instance($course->id);
        self::validate_context($context);
        if (
            !has_capability('mod/certifier:addinstance', $context)
                && !has_capability('moodle/course:manageactivities', $context)
        ) {
            throw new \required_capability_exception($context, 'mod/certifier:addinstance', 'nopermissions', '');
        }

        $client = new api_client();
        if (!$client->is_configured()) {
            return [
                'configured' => false,
                'message' => get_string('apiclientnotconfiguredform', 'certifier'),
                'error' => '',
                'data' => [],
            ];
        }

        try {
            $data = [];
            foreach ($client->get_groups() as $group) {
                $data[] = [
                    'id' => (string) $group['id'],
                    'name' => (string) $group['name'],
                ];
            }
            return [
                'configured' => true,
                'message' => '',
                'error' => '',
                'data' => $data,
            ];
        } catch (\moodle_exception $exception) {
            debugging('Certifier groups fetch failed: ' . $exception->getMessage(), DEBUG_DEVELOPER);
            return [
                'configured' => true,
                'message' => '',
                'error' => get_string('apifetchfailed', 'certifier'),
                'data' => [],
            ];
        }
    }

    /**
     * Describe the service return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'configured' => new external_value(PARAM_BOOL, 'Whether the Certifier API client is configured'),
            'message' => new external_value(PARAM_TEXT, 'Message to show when options cannot be loaded'),
            'error' => new external_value(PARAM_TEXT, 'Error message to show when the API request fails'),
            'data' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_TEXT, 'Credential template id'),
                'name' => new external_value(PARAM_TEXT, 'Display name'),
            ])),
        ]);
    }
}
