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

namespace mod_certifier\task;

use mod_certifier\local\issuer;

/**
 * Ad hoc task that processes one Certifier issuance row.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issue_credential_task extends \core\task\adhoc_task {
    /**
     * Execute the queued issuance task.
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        issuer::process_issuance((int) $data->issuanceid);
    }
}
