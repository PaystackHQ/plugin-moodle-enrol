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

/**
 * Tests for the restricted key validator.
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_paystack\restricted_key
 */
final class restricted_key_test extends \basic_testcase {
    const KEY = 'rk_test_' . 'abcdefghijklmnopqrstuvwx';
    const DENIED = 'This restricted key is not permitted to perform this action';
    const GONE = 'Sorry, this feature is no longer available.';
    const MOR = 'This feature is not available to merchant of record integrations.';

    private function check($cond, $name) {
        $this->assertTrue((bool) $cond, $name);
    }

    private function eq($actual, $expected, $name) {
        $this->assertSame($expected, $actual, $name);
    }

    private static function sens() {
        $sens = array();
        foreach (restricted_key::PROBES as $p) {
            if (!empty($p['sensitive'])) {
                $sens[$p['scope']] = $p;
            }
        }
        return $sens;
    }

    /** Stub: overrides keyed by probe path; default is a key with only the required scopes. */
    private static function probe($overrides = array(), $key = self::KEY, &$calls = null) {
        $calls = array();
        $stub = function ($method, $url, $headers, $body) use ($overrides, &$calls) {
            $path = substr($url, strlen('https://api.paystack.co/'));
            $calls[] = $path;
            if (isset($overrides[$path])) {
                return $overrides[$path];
            }
            if ($path === 'transaction?perPage=1') {
                return array('status' => 200, 'body' => array('status' => true));
            }
            if ($path === 'ident') {
                return array('status' => 200, 'body' => array('data' => array('public_key' => 'pk_test_abc123')));
            }
            return array('status' => 403, 'body' => array('message' => self::DENIED));
        };
        return restricted_key::validate($key, array('request' => $stub));
    }

    private static function r($status, $message = null) {
        return array('status' => $status, 'body' => $message === null ? null : array('message' => $message));
    }

    public function test_ok() {
        $v = self::probe();
        $this->eq($v['code'], 'ok', 'ok verdict');
        $this->eq($v['public_key'], 'pk_test_abc123', 'ok public key');
        $this->eq($v['reason'], null, 'ok reason');
        $this->eq(count(restricted_key::PROBES), 14, 'probe count');
        $this->eq(count(self::sens()), 12, 'sensitive count');
    }

    public function test_baseline() {
        $v = self::probe(array('transaction?perPage=1' => self::r(403, self::DENIED)), self::KEY, $calls);
        $this->eq($v['code'], 'missing_scope', 'absent baseline missing_scope');
        $this->eq($v['scopes'], array('transactions:read'), 'absent baseline scopes');
        $this->eq(count($calls), 14, 'absent baseline still probes others');
        $v = self::probe(array('transaction?perPage=1' => self::r(500)), self::KEY, $calls);
        $this->eq($v['code'], 'unverifiable', 'indeterminate baseline');
        $this->eq($v['reason'], 'transient', 'baseline 500 transient');
        $this->eq(count($calls), 1, 'indeterminate baseline stops');
        $v = self::probe(array('transaction?perPage=1' => self::r(403, 'IP not allowed')), self::KEY, $calls);
        $this->eq($v['reason'], 'blocked', 'baseline non-scope 403 blocked');
        $this->eq(count($calls), 1, 'blocked stops');
    }

    public function test_401_rules() {
        $v = self::probe(array('transaction?perPage=1' => self::r(401)), self::KEY, $calls);
        $this->eq($v['code'], 'invalid_key', '401 baseline invalid_key');
        $this->eq(count($calls), 1, '401 baseline stops');
        $v = self::probe(array('refund?perPage=1' => self::r(401)));
        $this->eq($v['code'], 'sensitive_scope', '401 sensitive after present baseline = present');
        $this->eq($v['sensitive_scopes'], array('disputes_refunds'), '401 sensitive scope name');
        $v = self::probe(array('ident' => self::r(401)));
        $this->eq($v['code'], 'unverifiable', '401 on required after present baseline indeterminate');
        $v = self::probe(array('transaction?perPage=1' => self::r(403, self::DENIED), 'refund?perPage=1' => self::r(401)));
        $this->eq($v['code'], 'missing_scope', '401 sensitive after absent baseline not present');
        $this->eq($v['sensitive_scopes'], array(), '401 after absent baseline no sensitive');
        $v = self::probe(array('transaction?perPage=1' => self::r(403, self::DENIED), 'bulkcharge' => self::r(200)));
        $this->eq($v['sensitive_scopes'], array('bulk_charges'), 'missing and sensitive both reported');
    }

    public function test_each_sensitive_probe() {
        foreach (self::sens() as $scope => $p) {
            if ($scope === 'transactions:write') { continue; }
            foreach (array(200, 400) as $st) {
                $v = self::probe(array($p['path'] => self::r($st)));
                $this->eq($v['code'] . ':' . implode(',', $v['sensitive_scopes']), "sensitive_scope:$scope", "$scope present on $st");
            }
            foreach (array(404, 429, 500, 502, 0, 301, 302) as $st) {
                $v = self::probe(array($p['path'] => self::r($st)));
                $this->eq($v['code'], 'unverifiable', "$scope status $st unverifiable");
                $this->eq($v['reason'], 'transient', "$scope status $st transient");
            }
            $v = self::probe(array($p['path'] => self::r(403, 'Forbidden by IP')));
            $this->eq($v['code'], 'unverifiable', "$scope other 403 unverifiable");
            $this->eq($v['reason'], 'transient', "$scope other 403 not blocked (baseline fine)");
    }
    }

    public function test_granted_on() {
        $granted = array(
            'subaccounts' => array('subaccount', array(self::MOR)),
            'webhooks' => array('integration/webhooks/events', array('Webhook endpoint management is not available for this integration')),
            'preauthorizations' => array('preauthorization', array('Merchant is not eligible for Preauthorization. Please contact support.', self::MOR)),
        );
        foreach ($granted as $scope => $g) {
            foreach ($g[1] as $msg) {
                foreach (array('array', 'string') as $form) {
                    $resp = $form === 'array' ? self::r(403, $msg) : array('status' => 403, 'body' => $msg);
                    $v = self::probe(array($g[0] => $resp));
                    $this->eq($v['sensitive_scopes'], array($scope), "$scope granted_on $form");
                }
            }
            foreach (array('array', 'string') as $form) {
                $msg = $g[1][0] . ' ';
                $resp = $form === 'array' ? self::r(403, $msg) : array('status' => 403, 'body' => $msg);
                $v = self::probe(array($g[0] => $resp));
                $this->eq($v['code'], 'unverifiable', "$scope near-miss $form");
            }
    }
    $v = self::probe(array('transfer/config' => self::r(403, self::MOR)));
    $this->eq($v['code'], 'unverifiable', 'granted_on not shared across probes');
    $v = self::probe(array('subaccount' => self::r(404, self::MOR)));
    $this->eq($v['code'], 'unverifiable', 'granted_on message needs 403');
    }

    public function test_write_probe() {
        $v = self::probe(array('transaction/check_authorization' => self::r(404, self::GONE)));
        $this->eq($v['code'] . ':' . implode(',', $v['sensitive_scopes']), 'sensitive_scope:transactions:write', 'write 404 exact');
        $v = self::probe(array('transaction/check_authorization' => array('status' => 404, 'body' => self::GONE)));
        $this->eq($v['sensitive_scopes'], array('transactions:write'), 'write 404 exact string body');
        $v = self::probe(array('transaction/check_authorization' => self::r(404, 'Route not found')));
        $this->eq($v['code'], 'unverifiable', 'write 404 other message');
        $v = self::probe(array('transaction/check_authorization' => self::r(404)));
        $this->eq($v['code'], 'unverifiable', 'write 404 no message');
        $v = self::probe(array('transaction/check_authorization' => self::r(200)));
        $this->eq($v['code'], 'unverifiable', 'write 200 not present');
        $v = self::probe(array('transaction/check_authorization' => self::r(400)));
        $this->eq($v['code'], 'unverifiable', 'write 400 not present');
    }

    public function test_scope_message_near_miss_vs_exact() {
        $v = self::probe(array('ident' => self::r(403, self::DENIED)));
        $this->eq($v['code'] . ':' . implode(',', $v['scopes']), 'missing_scope:account:read', 'ident absent');
        $v = self::probe(array('ident' => array('status' => 403, 'body' => self::DENIED)));
        $this->eq($v['scopes'], array('account:read'), 'absent string body');
        foreach (array(self::DENIED . '.', strtolower(self::DENIED), ' ' . self::DENIED) as $near) {
            $v = self::probe(array('ident' => self::r(403, $near)));
            $this->eq($v['code'], 'unverifiable', 'near-miss array: ' . $near);
            $v = self::probe(array('ident' => array('status' => 403, 'body' => $near)));
            $this->eq($v['code'], 'unverifiable', 'near-miss string: ' . $near);
    }
    $v = self::probe(array('refund?perPage=1' => array('status' => 403, 'body' => array('message' => array(self::DENIED)))));
    $this->eq($v['code'], 'unverifiable', 'non-string message');
    }

    public function test_precedence() {
        $v = self::probe(array('ident' => self::r(403, self::DENIED), 'refund?perPage=1' => self::r(200), 'bulkcharge' => self::r(500)));
        $this->eq($v['code'], 'missing_scope', 'missing beats indeterminate');
        $this->eq($v['sensitive_scopes'], array('disputes_refunds'), 'missing carries sensitive');
        $v = self::probe(array('refund?perPage=1' => self::r(200), 'bulkcharge' => self::r(500)));
        $this->eq($v['code'], 'sensitive_scope', 'sensitive beats indeterminate');
        $v = self::probe(array('settlement/instant/profile' => self::r(200)));
        $this->eq($v['sensitive_scopes'], array('payouts'), 'only last sensitive present');
        $v = self::probe(array('refund?perPage=1' => self::r(200), 'settlement/instant/profile' => self::r(200), 'transaction/check_authorization' => self::r(404, self::GONE)));
        $this->eq($v['sensitive_scopes'], array('transactions:write', 'disputes_refunds', 'payouts'), 'table order');
        $v = self::probe(array('transaction?perPage=1' => self::r(401), 'ident' => self::r(403, self::DENIED)));
        $this->eq($v['code'], 'invalid_key', 'invalid beats everything');
    }

    public function test_account_verdict() {
        foreach (array(
            'missing pk' => array('data' => array()),
            'no data' => array('status' => true),
            'wrong mode' => array('data' => array('public_key' => 'pk_live_abc')),
            'non-pk' => array('data' => array('public_key' => 'sk_test_abc')),
            'non-string' => array('data' => array('public_key' => 123)),
            'trailing newline' => array('data' => array('public_key' => "pk_test_abc\n")),
        ) as $name => $body) {
            $v = self::probe(array('ident' => array('status' => 200, 'body' => $body)));
            $this->eq($v['code'], 'account', "account: $name");
            $this->eq($v['public_key'], null, "account no pk: $name");
    }
    $v = self::probe(array('ident' => array('status' => 200, 'body' => 'not json')));
    $this->eq($v['code'], 'account', 'account: raw string body');
    $v = self::probe(array(), 'rk_live_' . 'abcdefghijklmnopqrstuvwx');
    $this->eq($v['code'], 'account', 'account: test pk with live rk');
    }

    public function test_transport_throwing() {
        $v = restricted_key::validate(self::KEY, array('request' => function () { throw new \RuntimeException('boom ' . self::KEY); }));
        $this->eq($v['code'], 'unverifiable', 'throwing requester');
        $this->eq($v['reason'], 'transient', 'throwing requester transient');
        $this->eq(substr_count(json_encode($v), self::KEY), 0, 'throw no key leak');
        $v = restricted_key::validate(self::KEY, array('request' => function () { return null; }));
        $this->eq($v['reason'], 'transient', 'null response status 0');
        $v = self::probe(array('transaction?perPage=1' => self::r(0)));
        $this->eq($v['code'], 'unverifiable', 'baseline status 0');
    }

    public function test_summary_and_leakage() {
        $v = self::probe();
        $this->check(strpos($v['probes'], 'transactions:read:200,account:read:200,transactions:write:403') === 0, 'probes summary');
        foreach (array(self::probe(), self::probe(array('ident' => self::r(403, self::DENIED))), self::probe(array('transaction?perPage=1' => self::r(401))), self::probe(array('refund?perPage=1' => self::r(200)))) as $i => $v) {
            $this->check(strpos(json_encode($v), self::KEY) === false, "no key in verdict $i");
            $this->check(strpos(json_encode($v), 'abcdefghij') === false, "no key slice in verdict $i");
    }
    }

    public function test_options() {
        $seen = null;
        restricted_key::validate(self::KEY, array('base_url' => 'https://example.test/api', 'request' => function ($m, $u) use (&$seen) {
            $seen = $u;
            return array('status' => 401, 'body' => null);
        }));
        $this->eq($seen, 'https://example.test/api/transaction?perPage=1', 'base_url option');
        $hdr = null;
        $meth = array();
        restricted_key::validate(self::KEY, array('request' => function ($m, $u, $h, $b) use (&$hdr, &$meth) {
            $hdr = $h;
            $meth[$u] = array($m, $b);
            return array('status' => 200, 'body' => null);
        }));
        $this->eq($hdr['Authorization'], 'Bearer ' . self::KEY, 'bearer header');
        $this->eq($meth['https://api.paystack.co/transaction/check_authorization'], array('POST', '{}'), 'write probe POST {}');

        $this->eq(restricted_key::CONCURRENCY, 5, 'concurrency constant');
    }

    public function test_shape_matrix() {
        $rk16 = 'rk_live_' . str_repeat('a', 16);
        $this->check(restricted_key::is_rk($rk16), 'rk min length');
        $this->check(!restricted_key::is_rk('rk_live_' . str_repeat('a', 15)), 'rk too short');
        $this->check(restricted_key::is_rk('rk_test_' . str_repeat('a', 248)), 'rk 256 ok');
        $this->check(!restricted_key::is_rk('rk_test_' . str_repeat('a', 249)), 'rk 257 rejected');
        $this->check(!restricted_key::is_rk($rk16 . "\n"), 'rk trailing newline');
        $this->check(!restricted_key::is_rk($rk16 . ' '), 'rk trailing space');
        $this->check(!restricted_key::is_rk(' ' . $rk16), 'rk leading space');
        $this->check(!restricted_key::is_rk('rk_test_' . str_repeat('a', 8) . ' ' . str_repeat('a', 8)), 'rk inner space');
        $this->check(!restricted_key::is_rk('rk_test_' . str_repeat('a', 15) . "\u{00e9}"), 'rk unicode');
        $this->check(!restricted_key::is_rk('rk_prod_' . str_repeat('a', 16)), 'rk bad mode');
        $this->check(!restricted_key::is_rk('sk_test_' . str_repeat('a', 16)), 'sk not rk');
        $this->check(!restricted_key::is_rk(null) && !restricted_key::is_rk(123) && !restricted_key::is_rk(array()), 'rk non-string');
        $this->check(restricted_key::is_pk('pk_test_abc_123') && restricted_key::is_pk('pk_live_X'), 'pk ok');
        $this->check(!restricted_key::is_pk("pk_test_abc\n") && !restricted_key::is_pk('pk_test_') && !restricted_key::is_pk('pk_test_a-b') && !restricted_key::is_pk('rk_test_abc') && !restricted_key::is_pk(null), 'pk rejects');
        $this->eq(restricted_key::key_mode('rk_test_x'), 'test', 'mode rk test');
        $this->eq(restricted_key::key_mode('sk_live_x'), 'live', 'mode sk live');
        $this->eq(restricted_key::key_mode('pk_live_x'), 'live', 'mode pk live');
        $this->eq(restricted_key::key_mode('xx_live_x'), null, 'mode other');
        $this->eq(restricted_key::key_mode('rk_prod_x'), null, 'mode bad');
        $this->eq(restricted_key::key_mode(null), null, 'mode null');
        $this->eq(restricted_key::key_type($rk16), 'rk', 'type rk');
        $this->eq(restricted_key::key_type('sk_test_abc'), 'sk', 'type sk');
        $this->eq(restricted_key::key_type('pk_test_abc'), 'other', 'type pk');
        $this->eq(restricted_key::key_type('rk_test_short'), 'other', 'type short rk');
        $this->eq(restricted_key::key_type(null), 'other', 'type null');
    }
}
