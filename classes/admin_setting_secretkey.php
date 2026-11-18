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
 * Admin setting for a Paystack secret key.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paystack;

defined('MOODLE_INTERNAL') || die();

class admin_setting_secretkey extends admin_setting_secret
{
    /** @var string 'test' or 'live'. */
    private $mode;

    public function __construct($name, $visiblename, $description, $mode) {
        $this->mode = $mode;
        parent::__construct($name, $visiblename, $description, '');
    }

    public function validate($data) {
        if (!preg_match('/^[\x21-\x7e]+\z/', $data)) {
            return get_string('sk_invalid', 'enrol_paystack');
        }
        if (restricted_key::is_rk($data) || strpos($data, 'rk_') === 0) {
            return get_string('sk_put_rk_above', 'enrol_paystack');
        }
        return true;
    }

    public function write_setting($data) {
        $data = trim($data);
        if ($data !== '' && !empty(admin_setting_restrictedkey::$saved_this_request[$this->mode])) {
            return get_string('sk_one_key_per_mode', 'enrol_paystack',
                get_string('rk_mode_' . $this->mode, 'enrol_paystack'));
        }
        $result = parent::write_setting($data);
        if ($data !== '' && $result === '') {
            foreach (array('restrictedkey', 'rkpublickey', 'rkchecked') as $suffix) {
                set_config($this->mode . '_' . $suffix, '', 'enrol_paystack');
            }
        }
        return $result;
    }
}
