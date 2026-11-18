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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Admin setting for the Paystack webhook signing secret.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paystack;

defined('MOODLE_INTERNAL') || die();

class admin_setting_webhooksecret extends admin_setting_secret
{
    public function validate($data) {
        if (webhook_signature::is_secret($data)) {
            return true;
        }
        return get_string('webhooksecret_invalid', 'enrol_paystack');
    }
}
