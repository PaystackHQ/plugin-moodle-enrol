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
    const LAST4_MIN_LENGTH = 20;

    /** @var string|null 'test' or 'live' for settings tied to a mode. */
    protected $mode;

    public function __construct($name, $visiblename, $description, $mode = null) {
        $this->mode = $mode;
        parent::__construct($name, $visiblename, $description, '');
    }

    /** Admin search must never match the stored secret. */
    public function is_related($query) {
        return strpos(strtolower($this->name), $query) !== false
            || strpos(\core_text::strtolower($this->visiblename), $query) !== false
            || strpos(\core_text::strtolower($this->description), $query) !== false;
    }

    public function output_html($data, $query = '') {
        $stored = (string) $this->get_setting();
        $description = $this->description;
        if ($stored !== '') {
            $last4 = $this->last4($stored);
            $note = $last4 === null ?
                get_string('secret_saved', 'enrol_paystack') :
                get_string('secret_saved_last4', 'enrol_paystack', s($last4));
            $id = 'id_enrol_paystack_remove_' . $this->name;
            $remove = '<div class="form-check mt-2"><input type="checkbox" class="form-check-input" id="' . $id .
                '" name="enrol_paystack_remove_' . $this->name . '" value="1"> <label class="form-check-label" for="' .
                $id . '">' . get_string('secret_remove', 'enrol_paystack') . '</label></div>';
            $this->description = $description . ' ' . $note . $remove;
        }
        $html = parent::output_html('', $query);
        $this->description = $description;
        return $html;
    }

    public function write_setting($data) {
        $removal = $this->handle_removal();
        if ($removal !== null) {
            return $removal;
        }
        $data = trim($data);
        $stored = (string) $this->get_setting();
        if ($data === '') {
            if ($stored !== '') {
                return '';
            }
            return $this->config_write($this->name, '') ? '' : get_string('errorsetting', 'admin');
        }
        if ($data === $stored && $this->is_current($data)) {
            return '';
        }
        $validated = $this->validate($data);
        if ($validated !== true) {
            return $validated;
        }
        if (!$this->config_write($this->name, $data)) {
            return get_string('errorsetting', 'admin');
        }
        $this->after_write($data);
        return '';
    }

    /**
     * Whether re-entering the stored value can skip validation. Off by default so a retyped value is
     * still checked against rules that depend on other settings (e.g. a restricted key now being stored).
     *
     * @param string $data
     * @return bool
     */
    protected function is_current($data) {
        return false;
    }

    /**
     * Runs once the value has been written.
     *
     * @param string $data
     */
    protected function after_write($data) {
    }

    /**
     * Clears the value when the "Remove the saved key" box is ticked, whatever else was typed.
     *
     * @return string|null Error message, '' on success, null when removal was not requested.
     */
    protected function handle_removal() {
        if (!optional_param('enrol_paystack_remove_' . $this->name, 0, PARAM_BOOL)) {
            return null;
        }
        if (!$this->config_write($this->name, '')) {
            return get_string('errorsetting', 'admin');
        }
        $this->on_removed();
        return '';
    }

    protected function on_removed() {
    }

    protected function last4($key) {
        return strlen($key) >= self::LAST4_MIN_LENGTH ? substr($key, -4) : null;
    }
}
