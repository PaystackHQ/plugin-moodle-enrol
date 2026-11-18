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
 * Admin setting for a Paystack restricted key, checked against Paystack before it is saved.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paystack;

defined('MOODLE_INTERNAL') || die();

class admin_setting_restrictedkey extends admin_setting_secret
{
    /** @var string 'test' or 'live'. */
    private $mode;

    public function __construct($name, $visiblename, $description, $mode) {
        $this->mode = $mode;
        parent::__construct($name, $visiblename, $description, '');
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

        if (!restricted_key::is_rk($data)) {
            if (preg_match('/^(sk|pk)_/', $data)) {
                return $this->refuse(get_string('rk_put_secret_below', 'enrol_paystack'));
            }
            return $this->refuse(get_string('rk_invalid_shape', 'enrol_paystack'));
        }

        $keymode = restricted_key::key_mode($data);
        if ($keymode !== $this->mode) {
            return $this->refuse(get_string('rk_wrong_mode', 'enrol_paystack', (object) array(
                'key' => self::mode_name($keymode),
                'field' => get_string($keymode . '_restrictedkey', 'enrol_paystack'),
            )));
        }

        $plugin = enrol_get_plugin('paystack');
        if ($data === $stored && $plugin->is_rk_checked($this->mode)) {
            return '';
        }

        $verdict = $this->run_validator($data, array(
            'curl_options' => http::curl_options(restricted_key::BASE_URL, 5, 3),
        ));
        $code = isset($verdict['code']) ? $verdict['code'] : 'unverifiable';

        if ($code !== 'ok') {
            return $this->refuse($this->verdict_message($verdict));
        }

        if (!$this->config_write($this->name, $data)) {
            return get_string('errorsetting', 'admin');
        }
        $plugin->set_restricted_key($this->mode, $data, $verdict['public_key']);
        return '';
    }

    protected function run_validator($key, array $options) {
        return restricted_key::validate($key, $options);
    }

    protected function on_removed() {
        enrol_get_plugin('paystack')->clear_restricted_key($this->mode);
    }

    private function refuse($message) {
        return $message . ' ' . get_string('rk_not_saved', 'enrol_paystack');
    }

    private static function mode_name($mode) {
        return get_string('rk_mode_' . $mode, 'enrol_paystack');
    }

    private function verdict_message($verdict) {
        $code = $verdict['code'];
        $reason = isset($verdict['reason']) ? $verdict['reason'] : null;
        switch ($code) {
            case 'invalid_key':
                return get_string('rk_invalid_key', 'enrol_paystack');
            case 'missing_scope':
                $message = get_string('rk_missing_scope', 'enrol_paystack',
                    self::scope_names(isset($verdict['scopes']) ? $verdict['scopes'] : array()));
                if (!empty($verdict['sensitive_scopes'])) {
                    $message .= ' ' . get_string('rk_sensitive_scope', 'enrol_paystack',
                        self::scope_names($verdict['sensitive_scopes']));
                }
                return $message;
            case 'sensitive_scope':
                return get_string('rk_sensitive_scope', 'enrol_paystack',
                    self::scope_names(isset($verdict['sensitive_scopes']) ? $verdict['sensitive_scopes'] : array()));
            case 'account':
                return get_string('rk_account', 'enrol_paystack', self::mode_name($this->mode));
            default:
                return get_string($reason === 'blocked' ? 'rk_blocked' : 'rk_transient', 'enrol_paystack');
        }
    }

    private static function scope_names(array $scopes) {
        $names = array();
        foreach ($scopes as $scope) {
            $scope = (string) $scope;
            $names[] = get_string_manager()->string_exists('rk_scope_' . $scope, 'enrol_paystack') ?
                get_string('rk_scope_' . $scope, 'enrol_paystack') : s($scope);
        }
        return implode(', ', $names);
    }
}
