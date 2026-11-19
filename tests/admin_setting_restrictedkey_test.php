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

namespace enrol_paystack;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/paystack/lib.php');
require_once(__DIR__ . '/fixtures/testable_admin_setting_restrictedkey.php');

/**
 * Tests for the restricted key admin setting and key resolution.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_paystack\admin_setting_restrictedkey
 */
final class admin_setting_restrictedkey_test extends \advanced_testcase {
    private const TESTRK = 'rk_test_' . 'abcdefghijklmnopqrstuvwxyz0123';
    private const LIVERK = 'rk_live_' . 'abcdefghijklmnopqrstuvwxyz0123';
    private const TESTSK = 'sk_test_' . 'abcdefghijklmnopqrstuvwxyz0123';

    /** @var array Calls received by the validator stub. */
    private $calls;

    /** @var array Verdict the validator stub returns. */
    private $verdict;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->calls = array();
        $this->verdict = $this->verdict('ok');
    }

    protected function tearDown(): void {
        unset($_POST['s_enrol_paystack_test_restrictedkey'], $_POST['enrol_paystack_remove_test_restrictedkey'],
            $_POST['enrol_paystack_remove_test_webhooksecret']);
        parent::tearDown();
    }

    private function verdict($code, array $extra = array()) {
        return $extra + array(
            'code' => $code,
            'scopes' => array(),
            'sensitive_scopes' => array(),
            'reason' => null,
            'public_key' => $code === 'ok' ? 'pk_test_publicpublic' : null,
            'probes' => '',
        );
    }

    private function rk($mode = 'test') {
        $test = $this;
        $setting = new testable_admin_setting_restrictedkey("enrol_paystack/{$mode}_restrictedkey", 'rk', 'desc', $mode);
        $setting->validator = function ($key, array $options) use ($test) {
            $test->calls[] = $key;
            return $test->verdict;
        };
        return $setting;
    }


    private function cfg($name) {
        return get_config('enrol_paystack', $name);
    }

    public function test_good_key_is_stored(): void {
        set_config('test_secretkey', self::TESTSK, 'enrol_paystack');
        $this->assertSame('', $this->rk()->write_setting(self::TESTRK));
        $this->assertSame(self::TESTRK, $this->cfg('test_restrictedkey'));
        $this->assertSame('pk_test_publicpublic', $this->cfg('test_rkpublickey'));
        $this->assertSame(\enrol_paystack_plugin::rk_fingerprint(self::TESTRK), $this->cfg('test_rkchecked'));
        $this->assertSame('', $this->cfg('test_secretkey'));
        $this->assertCount(1, $this->calls);
    }

    public function test_same_key_is_not_probed_again(): void {
        $this->assertSame('', $this->rk()->write_setting(self::TESTRK));
        $this->assertSame('', $this->rk()->write_setting(self::TESTRK));
        $this->assertCount(1, $this->calls);
    }

    public function test_stored_key_without_matching_check_is_probed(): void {
        set_config('test_restrictedkey', self::TESTRK, 'enrol_paystack');
        $this->assertSame('', $this->rk()->write_setting(self::TESTRK));
        $this->assertCount(1, $this->calls);

        set_config('test_rkchecked', 'stale', 'enrol_paystack');
        $this->assertSame('', $this->rk()->write_setting(self::TESTRK));
        $this->assertCount(2, $this->calls);
    }

    public function test_wrong_mode_is_refused(): void {
        $message = $this->rk('test')->write_setting(self::LIVERK);
        $this->assertStringContainsString('Live restricted key', $message);
        $this->assertStringNotContainsString('Test restricted key', $message);
        $this->assertStringNotContainsString(self::LIVERK, $message);
        $this->assertEmpty($this->cfg('test_restrictedkey'));
        $this->assertCount(0, $this->calls);
    }

    public function test_secret_key_in_restricted_field_is_refused(): void {
        $message = $this->rk()->write_setting(self::TESTSK);
        $this->assertSame(get_string('rk_not_secret', 'enrol_paystack') . ' ' .
            get_string('rk_not_saved', 'enrol_paystack'), $message);
        $this->assertEmpty($this->cfg('test_restrictedkey'));
        $this->assertCount(0, $this->calls);
    }

    public function test_bad_shape_is_refused(): void {
        $message = $this->rk()->write_setting('not a key');
        $this->assertStringContainsString(get_string('rk_invalid_shape', 'enrol_paystack'), $message);
        $this->assertEmpty($this->cfg('test_restrictedkey'));
        $this->assertCount(0, $this->calls);
    }

    /**
     * @dataProvider refused_verdicts
     */
    public function test_refused_verdicts_store_nothing(array $verdict, $expected): void {
        set_config('test_restrictedkey', 'rk_test_' . 'previouspreviouspreviousprev', 'enrol_paystack');
        set_config('test_rkpublickey', 'pk_test_previous', 'enrol_paystack');
        $this->verdict = $verdict;
        $message = $this->rk()->write_setting(self::TESTRK);
        $this->assertNotSame('', $message);
        $this->assertStringNotContainsString(self::TESTRK, $message);
        $this->assertStringContainsString($expected, $message);
        $this->assertStringContainsString(get_string('rk_not_saved', 'enrol_paystack'), $message);
        $this->assertSame('rk_test_' . 'previouspreviouspreviousprev', $this->cfg('test_restrictedkey'));
        $this->assertSame('pk_test_previous', $this->cfg('test_rkpublickey'));
    }

    public static function refused_verdicts(): array {
        $base = array('scopes' => array(), 'sensitive_scopes' => array(), 'reason' => null,
            'public_key' => null, 'probes' => '');
        return array(
            'invalid_key' => array(array('code' => 'invalid_key') + $base, 'recognise'),
            'missing_scope' => array(array('code' => 'missing_scope', 'scopes' => array('transactions:read'),
                'sensitive_scopes' => array('transfers')) + $base, 'Transactions: read'),
            'sensitive_scope' => array(array('code' => 'sensitive_scope', 'sensitive_scopes' => array('transactions:write',
                'disputes_refunds')) + $base, 'Transactions: write, Disputes and refunds'),
            'blocked' => array(array('code' => 'unverifiable', 'reason' => 'blocked') + $base, 'IP whitelist'),
            'transient' => array(array('code' => 'unverifiable', 'reason' => 'transient') + $base, 'Try saving again'),
            'account' => array(array('code' => 'account') + $base, 'test key'),
        );
    }

    public function test_missing_scope_also_names_sensitive_scopes(): void {
        $this->verdict = $this->verdict('missing_scope', array('scopes' => array('account:read'),
            'sensitive_scopes' => array('transfers')));
        $message = $this->rk()->write_setting(self::TESTRK);
        $this->assertStringContainsString('Account: read', $message);
        $this->assertStringContainsString('Transfers', $message);
    }

    public function test_blank_keeps_stored_key(): void {
        $this->rk()->write_setting(self::TESTRK);
        $this->assertSame('', $this->rk()->write_setting(''));
        $this->assertSame('', $this->rk()->write_setting('   '));
        $this->assertSame(self::TESTRK, $this->cfg('test_restrictedkey'));
        $this->assertCount(1, $this->calls);
    }

    public function test_blank_with_nothing_stored_writes_blank(): void {
        $this->assertSame('', $this->rk()->write_setting(''));
        $this->assertCount(0, $this->calls);
        $this->assertEmpty($this->cfg('test_restrictedkey'));
    }

    public function test_key_is_trimmed(): void {
        $this->assertSame('', $this->rk()->write_setting("  " . self::TESTRK . "\n"));
        $this->assertSame(self::TESTRK, $this->cfg('test_restrictedkey'));
        $this->assertSame(array(self::TESTRK), $this->calls);
    }

    public function test_config_log_masks_the_key(): void {
        global $DB;
        $this->rk()->write_setting(self::TESTRK);
        $rows = $DB->get_records('config_log', array('plugin' => 'enrol_paystack', 'name' => 'test_restrictedkey'), 'id DESC');
        $row = reset($rows);
        $this->assertSame('********', $row->value);
    }

    public function test_output_html_never_contains_the_key(): void {
        $this->rk()->write_setting(self::TESTRK);
        $setting = $this->rk();
        $html = $setting->output_html(self::TESTRK);
        $this->assertStringNotContainsString(self::TESTRK, $html);
        $this->assertStringContainsString(substr(self::TESTRK, -4), $html);
    }







    public function test_remove_checkbox_clears_restricted_key_and_companions(): void {
        $this->rk()->write_setting(self::TESTRK);
        $_POST['enrol_paystack_remove_test_restrictedkey'] = '1';
        $this->assertSame('', $this->rk()->write_setting(self::TESTRK));
        $this->assertCount(1, $this->calls);
        $this->assertEmpty($this->cfg('test_restrictedkey'));
        $this->assertEmpty($this->cfg('test_rkpublickey'));
        $this->assertEmpty($this->cfg('test_rkchecked'));
    }


    public function test_remove_checkbox_clears_webhook_secret(): void {
        set_config('test_webhooksecret', 'whsec_abcdefghijklmnop1234', 'enrol_paystack');
        $setting = new admin_setting_webhooksecret('enrol_paystack/test_webhooksecret', 'w', 'desc', '');
        $_POST['enrol_paystack_remove_test_webhooksecret'] = '1';
        $this->assertSame('', $setting->write_setting('whsec_zzzzzzzzzzzzzzzzzzzz'));
        $this->assertEmpty($this->cfg('test_webhooksecret'));
    }

    public function test_output_html_offers_removal_only_when_saved(): void {
        $html = $this->rk()->output_html('');
        $this->assertStringNotContainsString('enrol_paystack_remove_test_restrictedkey', $html);
        $this->rk()->write_setting(self::TESTRK);
        $html = $this->rk()->output_html('');
        $this->assertStringContainsString('name="enrol_paystack_remove_test_restrictedkey"', $html);
        $this->assertStringContainsString(get_string('secret_remove', 'enrol_paystack'), $html);
    }

    public function test_output_html_escapes_last4(): void {
        set_config('test_webhooksecret', 'whsec_abcdefghijklmnop<b>', 'enrol_paystack');
        $setting = new admin_setting_webhooksecret('enrol_paystack/test_webhooksecret', 'w', 'desc', '');
        $html = $setting->output_html('');
        $this->assertStringNotContainsString('p<b>', $html);
        $this->assertStringContainsString('p&lt;b&gt;', $html);
    }

    public function test_search_never_matches_the_stored_value(): void {
        $this->rk()->write_setting(self::TESTRK);
        $setting = $this->rk();
        $this->assertFalse($setting->is_related(strtolower(substr(self::TESTRK, 8, 12))));
        $this->assertTrue($setting->is_related('restrictedkey'));
        $this->assertTrue($setting->is_related('desc'));
    }

    public function test_set_restricted_key_logs_secret_key_clearance_masked(): void {
        global $DB;
        set_config('test_secretkey', self::TESTSK, 'enrol_paystack');
        $plugin = new \enrol_paystack_plugin();
        $plugin->set_restricted_key('test', self::TESTRK, 'pk_test_publicpublic');
        $this->assertSame('', $this->cfg('test_secretkey'));
        $this->assertSame('pk_test_publicpublic', $this->cfg('test_rkpublickey'));
        $rows = $DB->get_records('config_log', array('plugin' => 'enrol_paystack', 'name' => 'test_secretkey'), 'id DESC');
        $row = reset($rows);
        $this->assertSame('********', $row->oldvalue);
        $this->assertSame('', $row->value);
        $this->assertStringNotContainsString(self::TESTSK, json_encode($rows));
    }

    public function test_is_rk_checked(): void {
        $plugin = new \enrol_paystack_plugin();
        $this->assertFalse($plugin->is_rk_checked('test'));
        set_config('test_restrictedkey', self::TESTRK, 'enrol_paystack');
        $this->assertFalse($plugin->is_rk_checked('test'));
        set_config('test_rkchecked', \enrol_paystack_plugin::rk_fingerprint(self::TESTRK), 'enrol_paystack');
        $this->assertTrue($plugin->is_rk_checked('test'));
    }

    public function test_plugin_key_resolution(): void {
        set_config('test_secretkey', 'sk_test_plain', 'enrol_paystack');
        set_config('test_publickey', 'pk_test_plain', 'enrol_paystack');
        set_config('live_secretkey', 'sk_live_plain', 'enrol_paystack');
        set_config('live_publickey', 'pk_live_plain', 'enrol_paystack');

        set_config('mode', 0, 'enrol_paystack');
        $plugin = new \enrol_paystack_plugin();
        $this->assertSame('sk_test_plain', $plugin->get_secretkey());
        $this->assertSame('pk_test_plain', $plugin->get_publickey());

        set_config('test_restrictedkey', self::TESTRK, 'enrol_paystack');
        set_config('test_rkpublickey', 'pk_test_fromrk', 'enrol_paystack');
        $plugin = new \enrol_paystack_plugin();
        // Stored but unchecked: unusable, with no fallback to the secret key.
        $this->assertSame('', $plugin->get_secretkey());
        $this->assertSame('', $plugin->get_publickey());

        set_config('test_rkchecked', \enrol_paystack_plugin::rk_fingerprint(self::TESTRK), 'enrol_paystack');
        $plugin = new \enrol_paystack_plugin();
        $this->assertSame(self::TESTRK, $plugin->get_secretkey());
        $this->assertSame('pk_test_fromrk', $plugin->get_publickey());

        set_config('mode', 1, 'enrol_paystack');
        $plugin = new \enrol_paystack_plugin();
        $this->assertSame('sk_live_plain', $plugin->get_secretkey());
        $this->assertSame('pk_live_plain', $plugin->get_publickey());

        set_config('live_restrictedkey', ' ' . self::LIVERK . ' ', 'enrol_paystack');
        set_config('live_rkpublickey', 'pk_live_fromrk', 'enrol_paystack');
        set_config('live_rkchecked', \enrol_paystack_plugin::rk_fingerprint(self::LIVERK), 'enrol_paystack');
        $plugin = new \enrol_paystack_plugin();
        $this->assertSame(self::LIVERK, $plugin->get_secretkey());
        $this->assertSame('pk_live_fromrk', $plugin->get_publickey());
    }

    public function test_restricted_key_in_legacy_secret_slot_is_never_used(): void {
        set_config('test_secretkey', self::TESTRK, 'enrol_paystack');
        set_config('test_publickey', 'pk_test_plain', 'enrol_paystack');
        $plugin = new \enrol_paystack_plugin();
        $this->assertSame('', $plugin->get_secretkey());
        $this->assertFalse($plugin->has_usable_key());
        $warnings = $plugin->get_key_warnings();
        $this->assertContains(get_string('warning_rkinsecretfield', 'enrol_paystack', 'test'), $warnings);
        $this->assertContains(get_string('warning_nokey', 'enrol_paystack', 'test'), $warnings);
        $this->assertNotContains(get_string('warning_legacysecret', 'enrol_paystack', 'test'), $warnings);
    }

    public function test_removing_restricted_key_never_revives_legacy_secret_key(): void {
        set_config('test_restrictedkey', self::TESTRK, 'enrol_paystack');
        set_config('test_secretkey', self::TESTSK, 'enrol_paystack');
        $plugin = new \enrol_paystack_plugin();
        $plugin->clear_restricted_key('test');
        set_config('test_restrictedkey', '', 'enrol_paystack');
        $this->assertSame('', $this->cfg('test_secretkey'));
        $this->assertSame('', (new \enrol_paystack_plugin())->get_secretkey());
    }

    public function test_key_warnings_for_legacy_secret_keys(): void {
        set_config('test_webhooksecret', 'whsec_abcdefghijklmnop1234', 'enrol_paystack');
        set_config('test_secretkey', self::TESTSK, 'enrol_paystack');
        set_config('test_publickey', 'pk_test_plain', 'enrol_paystack');
        $warnings = (new \enrol_paystack_plugin())->get_key_warnings();
        $this->assertSame(array(get_string('warning_legacysecret', 'enrol_paystack', 'test')), $warnings);

        // Still reminded while a restricted key set outside the settings page sits beside the legacy key.
        set_config('test_restrictedkey', self::TESTRK, 'enrol_paystack');
        set_config('test_rkpublickey', 'pk_test_fromrk', 'enrol_paystack');
        set_config('test_rkchecked', \enrol_paystack_plugin::rk_fingerprint(self::TESTRK), 'enrol_paystack');
        $warnings = (new \enrol_paystack_plugin())->get_key_warnings();
        $this->assertSame(array(get_string('warning_legacysecret', 'enrol_paystack', 'test')), $warnings);

        // A legacy key only in the inactive mode is still flagged for that mode.
        set_config('test_secretkey', '', 'enrol_paystack');
        set_config('live_secretkey', 'sk_live_' . 'abcdefghijklmnopqrstuvwxyz0123', 'enrol_paystack');
        $warnings = (new \enrol_paystack_plugin())->get_key_warnings();
        $this->assertSame(array(get_string('warning_legacysecret', 'enrol_paystack', 'live')), $warnings);
    }

    public function test_key_warnings_when_nothing_is_saved(): void {
        $warnings = (new \enrol_paystack_plugin())->get_key_warnings();
        $this->assertSame(array(get_string('warning_nokey', 'enrol_paystack', 'test')), $warnings);
    }
}
