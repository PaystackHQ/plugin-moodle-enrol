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
 * Base class for secret admin settings that are never echoed back.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paystack;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

abstract class admin_setting_secret extends \admin_setting_configpasswordunmask
{
    public function output_html($data, $query = '') {
        $stored = (string) $this->get_setting();
        $description = $this->description;
        if ($stored !== '') {
            $last4 = restricted_key::last4($stored);
            $note = $last4 === null ?
                get_string('secret_saved', 'enrol_paystack') :
                get_string('secret_saved_last4', 'enrol_paystack', $last4);
            $this->description = $description . ' ' . $note;
        }
        $html = parent::output_html('', $query);
        $this->description = $description;
        return $html;
    }

    public function write_setting($data) {
        $data = trim($data);
        $stored = (string) $this->get_setting();
        if ($data === '' && $stored !== '') {
            return '';
        }
        if ($data !== '') {
            $validated = $this->validate($data);
            if ($validated !== true) {
                return $validated;
            }
        }
        return $this->config_write($this->name, $data) ? '' : get_string('errorsetting', 'admin');
    }
}
