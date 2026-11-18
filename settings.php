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
 * Plugin administration pages are defined here.
 *
 * @package     enrol_paystack
 * @category    admin
 * @copyright   2019 Paystack
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** Paystack live mode disabled. */
defined('LIVE_MODE_DISABLED') || define('LIVE_MODE_DISABLED', 0);

/** Paystack live mode enabled.*/
defined('LIVE_MODE_ENABLED') || define('LIVE_MODE_ENABLED', 1);

global $CFG, $OUTPUT;

if ($ADMIN->fulltree) {
    $webhook = "$CFG->wwwroot/enrol/paystack/webhook.php";
    $url = "https://dashboard.paystack.com/#/settings/developer";
    $text = '<p>Add this Webhook Url <span style="color:blue; text-decoration:underline;">' . $webhook . '</span> to your paystack account developer settings page <a href="' . $url .'" target="_blank">here</a></p>';
    $settings->add(new admin_setting_heading(
        'enrol_paystack_enrolname_short',
        '',
        get_string('pluginname_desc', 'enrol_paystack') . " " . $text
    ));

    $paystackplugin = enrol_get_plugin('paystack');
    $paystackconfig = get_config('enrol_paystack');
    $warnings = [];
    $activemode = $paystackplugin->get_mode() ? 'live' : 'test';
    if ($paystackplugin->get_secretkey() !== '' && $paystackplugin->get_webhooksecret() === '') {
        $warnings[] = get_string('warning_nowebhooksecret', 'enrol_paystack', $activemode);
    }
    foreach (['test', 'live'] as $keymode) {
        $rk = trim((string) ($paystackconfig->{"{$keymode}_restrictedkey"} ?? ''));
        if ($rk !== '' && ($paystackconfig->{"{$keymode}_rkchecked"} ?? '') !== hash('sha256', $rk)) {
            $warnings[] = get_string('warning_rkunchecked', 'enrol_paystack', $keymode);
        }
        if (strpos((string) ($paystackconfig->{"{$keymode}_secretkey"} ?? ''), 'rk_') === 0) {
            $warnings[] = get_string('warning_rkinsecretfield', 'enrol_paystack', $keymode);
        }
    }
    if ($warnings) {
        $html = '';
        foreach ($warnings as $warning) {
            $html .= $OUTPUT->notification($warning, \core\output\notification::NOTIFY_WARNING);
        }
        $settings->add(new admin_setting_heading('enrol_paystack_warnings', '', $html));
    }

    $options = array(
        LIVE_MODE_ENABLED  => get_string('mode_live', 'enrol_paystack'),
        LIVE_MODE_DISABLED => get_string('mode_test', 'enrol_paystack')
    );
    $settings->add(new admin_setting_configselect(
        'enrol_paystack/mode',
        get_string('mode', 'enrol_paystack'),
        get_string('mode_desc', 'enrol_paystack'),
        LIVE_MODE_DISABLED,
        $options
    ));

    $settings->add(new admin_setting_heading(
        'enrol_paystack_restrictedkeys',
        get_string('restrictedkeys', 'enrol_paystack'),
        get_string('restrictedkeys_desc', 'enrol_paystack')
    ));

    foreach (['test', 'live'] as $keymode) {
        $settings->add(new \enrol_paystack\admin_setting_restrictedkey(
            "enrol_paystack/{$keymode}_restrictedkey",
            get_string("{$keymode}_restrictedkey", 'enrol_paystack'),
            get_string("{$keymode}_restrictedkey_desc", 'enrol_paystack'),
            $keymode
        ));
    }

    $settings->add(new admin_setting_heading(
        'enrol_paystack_webhooksecrets',
        get_string('webhooksecrets', 'enrol_paystack'),
        get_string('webhooksecrets_desc', 'enrol_paystack')
    ));

    foreach (['test', 'live'] as $keymode) {
        $settings->add(new \enrol_paystack\admin_setting_webhooksecret(
            "enrol_paystack/{$keymode}_webhooksecret",
            get_string("{$keymode}_webhooksecret", 'enrol_paystack'),
            get_string('webhooksecret_desc', 'enrol_paystack'),
            ''
        ));
    }

    $settings->add(new admin_setting_heading(
        'enrol_paystack_secretkeys',
        get_string('secretkeys', 'enrol_paystack'),
        get_string('secretkeys_desc', 'enrol_paystack')
    ));

    foreach (['test', 'live'] as $keymode) {
        $settings->add(new \enrol_paystack\admin_setting_secretkey(
            "enrol_paystack/{$keymode}_secretkey",
            get_string("{$keymode}_secretkey", 'enrol_paystack'),
            get_string("{$keymode}_secretkey_desc", 'enrol_paystack'),
            $keymode
        ));

        $settings->add(new admin_setting_configtext(
            "enrol_paystack/{$keymode}_publickey",
            get_string("{$keymode}_publickey", 'enrol_paystack'),
            get_string("{$keymode}_publickey_desc", 'enrol_paystack'),
            '',
            PARAM_TEXT
        ));
    }

    $settings->add(new admin_setting_configcheckbox(
        'enrol_paystack/mailstudents',
        get_string('mailstudents', 'enrol_paystack'),
        '',
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_paystack/mailteachers',
        get_string('mailteachers', 'enrol_paystack'),
        '',
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_paystack/mailadmins',
        get_string('mailadmins', 'enrol_paystack'),
        '',
        0
    ));

    // Note: let's reuse the ext sync constants and strings here, internally it is very similar,
    // it describes what should happen when users are not supposed to be enrolled any more.
    $options = array(
        ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
    );
    $settings->add(new admin_setting_configselect(
        'enrol_paystack/expiredaction',
        get_string('expiredaction', 'enrol_paystack'),
        get_string('expiredaction_help', 'enrol_paystack'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES,
        $options
    ));

    // Profile fields to use in the selector
    $customfieldrecords = $DB->get_records('user_info_field');
    if ($customfieldrecords) {
        $customfields = [];
        foreach ($customfieldrecords as $customfieldrecord) {
            $customfields[$customfieldrecord->shortname] = $customfieldrecord->name;
        }
        asort($customfields);
        $settings->add(new admin_setting_configmultiselect('enrol_paystack/customfields',
                get_string('customfields', 'enrol_paystack'), get_string('customfields_desc', 'enrol_paystack'),
                [], $customfields));
    }

    // --- enrol instance defaults ----------------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'enrol_paystack_defaults',
        get_string('enrolinstancedefaults', 'admin'),
        get_string('enrolinstancedefaults_desc', 'admin')
    ));

    $options = array(
        ENROL_INSTANCE_ENABLED  => get_string('yes'),
        ENROL_INSTANCE_DISABLED => get_string('no')
    );
    $settings->add(new admin_setting_configselect(
        'enrol_paystack/status',
        get_string('status', 'enrol_paystack'),
        get_string('status_desc', 'enrol_paystack'),
        ENROL_INSTANCE_DISABLED,
        $options
    ));

    $currencies = enrol_get_plugin('paystack')->get_currencies();
    $settings->add(new admin_setting_configselect(
        'enrol_paystack/currency',
        get_string('currency', 'enrol_paystack'),
        '',
        'NGN',
        $currencies
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_paystack/maxenrolled',
        get_string('maxenrolled', 'enrol_paystack'),
        get_string('maxenrolled_help', 'enrol_paystack'),
        0,
        PARAM_INT
    ));
    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect(
            'enrol_paystack/roleid',
            get_string('defaultrole', 'enrol_paystack'),
            get_string('defaultrole_desc', 'enrol_paystack'),
            $student->id,
            $options
        ));
    }
    $settings->add(new admin_setting_configduration(
        'enrol_paystack/enrolperiod',
        get_string('enrolperiod', 'enrol_paystack'),
        get_string('enrolperiod_desc', 'enrol_paystack'),
        0
    ));
}
