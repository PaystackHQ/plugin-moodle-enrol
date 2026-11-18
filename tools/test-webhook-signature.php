<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('MOODLE_INTERNAL', true);
require __DIR__ . '/../classes/webhook_signature.php';

use enrol_paystack\webhook_signature as ws;

$pass = 0;
$fail = 0;
function check($cond, $name) {
    global $pass, $fail;
    if ($cond) { $pass++; } else { $fail++; echo "FAIL: $name\n"; }
}

const WHSEC = 'whsec_abcdefghijklmnop1234';
const NOW = 1800000000;
const BODY = '{"event":"charge.success","data":{"reference":"abc"}}';

function sig($t, $body, $key) {
    return hash_hmac('sha512', $t . '.' . $body, (string) $key);
}
function server($header) {
    return array('HTTP_PAYSTACK_SIGNATURE' => $header);
}
function auth($header, $body = BODY, $now = NOW, $key = WHSEC) {
    return ws::is_authentic($body, server($header), $now, $key);
}

$good = sig(NOW, BODY, WHSEC);
check(auth('t=' . NOW . ',v1=sha512:' . $good), 'valid header');
check(auth('t=' . NOW . ', v1=sha512:' . $good), 'valid header with space after comma');
check(auth('t=' . NOW . ',v1=sha512:' . str_repeat('0', 128) . ',v1=sha512:' . $good), 'second v1 matches');
check(!auth('t=' . NOW . ',v1=' . $good), 'v1 without colon ignored');
check(!auth('t=' . NOW . ',v1=sha512:' . str_repeat('0', 128)), 'wrong signature');
check(!auth('t=' . NOW . ',v1=sha512:zz' . substr($good, 2)), 'bad hex');
check(!auth('t=' . NOW . ',v1=sha512:' . $good, BODY . ' '), 'tampered body');
check(!auth('t=' . NOW . ',v1=sha512:' . $good, BODY, NOW, WHSEC . 'x'), 'different secret');

foreach (array(-301 => false, -300 => true, 300 => true, 301 => false) as $skew => $ok) {
    $t = NOW + $skew;
    check(auth('t=' . $t . ',v1=sha512:' . sig($t, BODY, WHSEC)) === $ok, "skew $skew");
}

check(!auth('t=12a,v1=sha512:' . sig('12a', BODY, WHSEC), BODY, 12), 'non-digit t');
check(!auth('t=-' . NOW . ',v1=sha512:' . sig('-' . NOW, BODY, WHSEC)), 'negative t');
check(!auth('v1=sha512:' . $good), 'missing t');
check(!auth(''), 'empty header');

foreach (array(null, '', false, 'whsec_short', 'sk_test_' . 'abcdefghijklmnopqrstu', 'whsec_abcdefghijklmnop123 ', "whsec_abcdefghijklmnop1234\n") as $i => $key) {
    $h = 't=' . NOW . ',v1=sha512:' . sig(NOW, BODY, $key);
    check(!auth($h, BODY, NOW, $key), "rejected secret #$i");
}

check(!ws::is_authentic(BODY, array(), NOW, WHSEC), 'missing header');
$legacy = hash_hmac('sha512', BODY, 'sk_test_' . 'abcdefghijklmnopqrstu');
check(!ws::is_authentic(BODY, array('HTTP_X_PAYSTACK_SIGNATURE' => $legacy), NOW, WHSEC), 'legacy header only');
check(!ws::is_authentic(BODY, array('HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', BODY, WHSEC)), NOW, WHSEC), 'legacy header signed with whsec');

check(ws::is_secret('whsec_abcdefghijklmnop'), 'is_secret min length');
check(!ws::is_secret('whsec_abcdefghijklmno'), 'is_secret too short');
check(!ws::is_secret('whsec_abcdefghijklmnop é'), 'is_secret non-ascii');
check(!ws::is_secret(123), 'is_secret non-string');

echo "$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
