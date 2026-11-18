<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('MOODLE_INTERNAL', true);
require __DIR__ . '/../classes/restricted_key.php';

use enrol_paystack\restricted_key as rk;

$pass = 0;
$fail = 0;
function check($cond, $name) {
    global $pass, $fail;
    if ($cond) { $pass++; } else { $fail++; echo "FAIL: $name\n"; }
}
function eq($actual, $expected, $name) {
    check($actual === $expected, $name . ' (got ' . json_encode($actual) . ')');
}

const KEY = 'rk_test_' . 'abcdefghijklmnopqrstuvwx';
const DENIED = 'This restricted key is not permitted to perform this action';
const GONE = 'Sorry, this feature is no longer available.';
const MOR = 'This feature is not available to merchant of record integrations.';

// Stub: overrides keyed by probe path; default is a key with only the required scopes.
function run($overrides = array(), $key = KEY, &$calls = null) {
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
        return array('status' => 403, 'body' => array('message' => DENIED));
    };
    return rk::validate($key, array('request' => $stub));
}
function r($status, $message = null) {
    return array('status' => $status, 'body' => $message === null ? null : array('message' => $message));
}
$sens = array();
foreach (rk::PROBES as $p) {
    if (!empty($p['sensitive'])) { $sens[$p['scope']] = $p; }
}

// ok
$v = run();
eq($v['code'], 'ok', 'ok verdict');
eq($v['public_key'], 'pk_test_abc123', 'ok public key');
eq($v['reason'], null, 'ok reason');
eq(count(rk::PROBES), 14, 'probe count');
eq(count($sens), 12, 'sensitive count');

// baseline
$v = run(array('transaction?perPage=1' => r(403, DENIED)), KEY, $calls);
eq($v['code'], 'missing_scope', 'absent baseline missing_scope');
eq($v['scopes'], array('transactions:read'), 'absent baseline scopes');
eq(count($calls), 14, 'absent baseline still probes others');
$v = run(array('transaction?perPage=1' => r(500)), KEY, $calls);
eq($v['code'], 'unverifiable', 'indeterminate baseline');
eq($v['reason'], 'transient', 'baseline 500 transient');
eq(count($calls), 1, 'indeterminate baseline stops');
$v = run(array('transaction?perPage=1' => r(403, 'IP not allowed')), KEY, $calls);
eq($v['reason'], 'blocked', 'baseline non-scope 403 blocked');
eq(count($calls), 1, 'blocked stops');

// 401 rules
$v = run(array('transaction?perPage=1' => r(401)), KEY, $calls);
eq($v['code'], 'invalid_key', '401 baseline invalid_key');
eq(count($calls), 1, '401 baseline stops');
$v = run(array('refund?perPage=1' => r(401)));
eq($v['code'], 'sensitive_scope', '401 sensitive after present baseline = present');
eq($v['sensitive_scopes'], array('disputes_refunds'), '401 sensitive scope name');
$v = run(array('ident' => r(401)));
eq($v['code'], 'unverifiable', '401 on required after present baseline indeterminate');
$v = run(array('transaction?perPage=1' => r(403, DENIED), 'refund?perPage=1' => r(401)));
eq($v['code'], 'missing_scope', '401 sensitive after absent baseline not present');
eq($v['sensitive_scopes'], array(), '401 after absent baseline no sensitive');
$v = run(array('transaction?perPage=1' => r(403, DENIED), 'bulkcharge' => r(200)));
eq($v['sensitive_scopes'], array('bulk_charges'), 'missing and sensitive both reported');

// each sensitive probe
foreach ($sens as $scope => $p) {
    if ($scope === 'transactions:write') { continue; }
    foreach (array(200, 400) as $st) {
        $v = run(array($p['path'] => r($st)));
        eq($v['code'] . ':' . implode(',', $v['sensitive_scopes']), "sensitive_scope:$scope", "$scope present on $st");
    }
    foreach (array(404, 429, 500, 502, 0, 301, 302) as $st) {
        $v = run(array($p['path'] => r($st)));
        eq($v['code'], 'unverifiable', "$scope status $st unverifiable");
        eq($v['reason'], 'transient', "$scope status $st transient");
    }
    $v = run(array($p['path'] => r(403, 'Forbidden by IP')));
    eq($v['code'], 'unverifiable', "$scope other 403 unverifiable");
    eq($v['reason'], 'transient', "$scope other 403 not blocked (baseline fine)");
}

// granted_on
$granted = array(
    'subaccounts' => array('subaccount', array(MOR)),
    'webhooks' => array('integration/webhooks/events', array('Webhook endpoint management is not available for this integration')),
    'preauthorizations' => array('preauthorization', array('Merchant is not eligible for Preauthorization. Please contact support.', MOR)),
);
foreach ($granted as $scope => $g) {
    foreach ($g[1] as $msg) {
        foreach (array('array', 'string') as $form) {
            $resp = $form === 'array' ? r(403, $msg) : array('status' => 403, 'body' => $msg);
            $v = run(array($g[0] => $resp));
            eq($v['sensitive_scopes'], array($scope), "$scope granted_on $form");
        }
    }
    foreach (array('array', 'string') as $form) {
        $msg = $g[1][0] . ' ';
        $resp = $form === 'array' ? r(403, $msg) : array('status' => 403, 'body' => $msg);
        $v = run(array($g[0] => $resp));
        eq($v['code'], 'unverifiable', "$scope near-miss $form");
    }
}
$v = run(array('transfer/config' => r(403, MOR)));
eq($v['code'], 'unverifiable', 'granted_on not shared across probes');
$v = run(array('subaccount' => r(404, MOR)));
eq($v['code'], 'unverifiable', 'granted_on message needs 403');

// write probe
$v = run(array('transaction/check_authorization' => r(404, GONE)));
eq($v['code'] . ':' . implode(',', $v['sensitive_scopes']), 'sensitive_scope:transactions:write', 'write 404 exact');
$v = run(array('transaction/check_authorization' => array('status' => 404, 'body' => GONE)));
eq($v['sensitive_scopes'], array('transactions:write'), 'write 404 exact string body');
$v = run(array('transaction/check_authorization' => r(404, 'Route not found')));
eq($v['code'], 'unverifiable', 'write 404 other message');
$v = run(array('transaction/check_authorization' => r(404)));
eq($v['code'], 'unverifiable', 'write 404 no message');
$v = run(array('transaction/check_authorization' => r(200)));
eq($v['code'], 'unverifiable', 'write 200 not present');
$v = run(array('transaction/check_authorization' => r(400)));
eq($v['code'], 'unverifiable', 'write 400 not present');

// scope message near miss vs exact
$v = run(array('ident' => r(403, DENIED)));
eq($v['code'] . ':' . implode(',', $v['scopes']), 'missing_scope:account:read', 'ident absent');
$v = run(array('ident' => array('status' => 403, 'body' => DENIED)));
eq($v['scopes'], array('account:read'), 'absent string body');
foreach (array(DENIED . '.', strtolower(DENIED), ' ' . DENIED) as $near) {
    $v = run(array('ident' => r(403, $near)));
    eq($v['code'], 'unverifiable', 'near-miss array: ' . $near);
    $v = run(array('ident' => array('status' => 403, 'body' => $near)));
    eq($v['code'], 'unverifiable', 'near-miss string: ' . $near);
}
$v = run(array('refund?perPage=1' => array('status' => 403, 'body' => array('message' => array(DENIED)))));
eq($v['code'], 'unverifiable', 'non-string message');

// precedence
$v = run(array('ident' => r(403, DENIED), 'refund?perPage=1' => r(200), 'bulkcharge' => r(500)));
eq($v['code'], 'missing_scope', 'missing beats indeterminate');
eq($v['sensitive_scopes'], array('disputes_refunds'), 'missing carries sensitive');
$v = run(array('refund?perPage=1' => r(200), 'bulkcharge' => r(500)));
eq($v['code'], 'sensitive_scope', 'sensitive beats indeterminate');
$v = run(array('settlement/instant/profile' => r(200)));
eq($v['sensitive_scopes'], array('payouts'), 'only last sensitive present');
$v = run(array('refund?perPage=1' => r(200), 'settlement/instant/profile' => r(200), 'transaction/check_authorization' => r(404, GONE)));
eq($v['sensitive_scopes'], array('transactions:write', 'disputes_refunds', 'payouts'), 'table order');
$v = run(array('transaction?perPage=1' => r(401), 'ident' => r(403, DENIED)));
eq($v['code'], 'invalid_key', 'invalid beats everything');

// account verdict
foreach (array(
    'missing pk' => array('data' => array()),
    'no data' => array('status' => true),
    'wrong mode' => array('data' => array('public_key' => 'pk_live_abc')),
    'non-pk' => array('data' => array('public_key' => 'sk_test_abc')),
    'non-string' => array('data' => array('public_key' => 123)),
    'trailing newline' => array('data' => array('public_key' => "pk_test_abc\n")),
) as $name => $body) {
    $v = run(array('ident' => array('status' => 200, 'body' => $body)));
    eq($v['code'], 'account', "account: $name");
    eq($v['public_key'], null, "account no pk: $name");
}
$v = run(array('ident' => array('status' => 200, 'body' => 'not json')));
eq($v['code'], 'account', 'account: raw string body');
$v = run(array(), 'rk_live_' . 'abcdefghijklmnopqrstuvwx');
eq($v['code'], 'account', 'account: test pk with live rk');

// transport / throwing
$v = rk::validate(KEY, array('request' => function () { throw new \RuntimeException('boom ' . KEY); }));
eq($v['code'], 'unverifiable', 'throwing requester');
eq($v['reason'], 'transient', 'throwing requester transient');
eq(substr_count(json_encode($v), KEY), 0, 'throw no key leak');
$v = rk::validate(KEY, array('request' => function () { return null; }));
eq($v['reason'], 'transient', 'null response status 0');
$v = run(array('transaction?perPage=1' => r(0)));
eq($v['code'], 'unverifiable', 'baseline status 0');

// summary and leakage
$v = run();
check(strpos($v['probes'], 'transactions:read:200,account:read:200,transactions:write:403') === 0, 'probes summary');
foreach (array(run(), run(array('ident' => r(403, DENIED))), run(array('transaction?perPage=1' => r(401))), run(array('refund?perPage=1' => r(200)))) as $i => $v) {
    check(strpos(json_encode($v), KEY) === false, "no key in verdict $i");
    check(strpos(json_encode($v), 'abcdefghij') === false, "no key slice in verdict $i");
}

// options
$seen = null;
rk::validate(KEY, array('base_url' => 'https://example.test/api', 'request' => function ($m, $u) use (&$seen) {
    $seen = $u;
    return array('status' => 401, 'body' => null);
}));
eq($seen, 'https://example.test/api/transaction?perPage=1', 'base_url option');
$hdr = null;
$meth = array();
rk::validate(KEY, array('request' => function ($m, $u, $h, $b) use (&$hdr, &$meth) {
    $hdr = $h;
    $meth[$u] = array($m, $b);
    return array('status' => 200, 'body' => null);
}));
eq($hdr['Authorization'], 'Bearer ' . KEY, 'bearer header');
eq($meth['https://api.paystack.co/transaction/check_authorization'], array('POST', '{}'), 'write probe POST {}');

eq(rk::CONCURRENCY, 5, 'concurrency constant');

// shape matrix
$rk16 = 'rk_live_' . str_repeat('a', 16);
check(rk::is_rk($rk16), 'rk min length');
check(!rk::is_rk('rk_live_' . str_repeat('a', 15)), 'rk too short');
check(rk::is_rk('rk_test_' . str_repeat('a', 248)), 'rk 256 ok');
check(!rk::is_rk('rk_test_' . str_repeat('a', 249)), 'rk 257 rejected');
check(!rk::is_rk($rk16 . "\n"), 'rk trailing newline');
check(!rk::is_rk($rk16 . ' '), 'rk trailing space');
check(!rk::is_rk(' ' . $rk16), 'rk leading space');
check(!rk::is_rk('rk_test_' . str_repeat('a', 8) . ' ' . str_repeat('a', 8)), 'rk inner space');
check(!rk::is_rk('rk_test_' . str_repeat('a', 15) . "\u{00e9}"), 'rk unicode');
check(!rk::is_rk('rk_prod_' . str_repeat('a', 16)), 'rk bad mode');
check(!rk::is_rk('sk_test_' . str_repeat('a', 16)), 'sk not rk');
check(!rk::is_rk(null) && !rk::is_rk(123) && !rk::is_rk(array()), 'rk non-string');
check(rk::is_pk('pk_test_abc_123') && rk::is_pk('pk_live_X'), 'pk ok');
check(!rk::is_pk("pk_test_abc\n") && !rk::is_pk('pk_test_') && !rk::is_pk('pk_test_a-b') && !rk::is_pk('rk_test_abc') && !rk::is_pk(null), 'pk rejects');
eq(rk::key_mode('rk_test_x'), 'test', 'mode rk test');
eq(rk::key_mode('sk_live_x'), 'live', 'mode sk live');
eq(rk::key_mode('pk_live_x'), 'live', 'mode pk live');
eq(rk::key_mode('xx_live_x'), null, 'mode other');
eq(rk::key_mode('rk_prod_x'), null, 'mode bad');
eq(rk::key_mode(null), null, 'mode null');
eq(rk::key_type($rk16), 'rk', 'type rk');
eq(rk::key_type('sk_test_abc'), 'sk', 'type sk');
eq(rk::key_type('pk_test_abc'), 'other', 'type pk');
eq(rk::key_type('rk_test_short'), 'other', 'type short rk');
eq(rk::key_type(null), 'other', 'type null');
eq(rk::last4('rk_test_abcdefghijkl'), 'ijkl', 'last4 at 20');
eq(rk::last4('rk_test_abcdefghijk'), null, 'last4 at 19');
eq(rk::last4(null), null, 'last4 null');

echo "$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
