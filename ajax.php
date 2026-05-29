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
 * Form-side AJAX endpoint for mod_certifier.
 *
 * Returns Certifier credential template (group) and custom-attribute options as
 * JSON so the activity edit form can load them asynchronously instead of
 * blocking page render on a synchronous API call.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

require_sesskey();
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);
if (
    !has_capability('mod/certifier:addinstance', $context)
        && !has_capability('moodle/course:manageactivities', $context)
) {
    throw new required_capability_exception($context, 'mod/certifier:addinstance', 'nopermissions', '');
}

header('Content-Type: application/json; charset=utf-8');

$client = new \mod_certifier\local\api_client();
if (!$client->is_configured()) {
    echo json_encode([
        'configured' => false,
        'message' => get_string('apiclientnotconfiguredform', 'certifier'),
        'data' => [],
    ]);
    exit;
}

try {
    switch ($action) {
        case 'groups':
            $data = $client->get_groups();
            break;
        case 'attributes':
            $data = $client->get_custom_attributes();
            break;
        default:
            throw new invalid_parameter_exception('Unknown action: ' . $action);
    }
    echo json_encode([
        'configured' => true,
        'data' => $data,
    ]);
} catch (\moodle_exception $exception) {
    // Keep the detail in the server log; show the teacher only a generic message.
    debugging('Certifier options fetch failed: ' . $exception->getMessage(), DEBUG_DEVELOPER);
    echo json_encode([
        'configured' => true,
        'error' => get_string('apifetchfailed', 'certifier'),
        'data' => [],
    ]);
}
