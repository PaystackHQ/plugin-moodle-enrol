<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Paystack API Class
 *
 * @package    enrol_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paystack;

defined('MOODLE_INTERNAL') || die();

class paystack
{
    public $plugin_name;
    public $public_key;
    public $secret_key;
    public $base_url;

    public function __construct($plugin, $pk, $sk)
    {
        $this->base_url = "https://api.paystack.co/";
        $this->plugin_name = $plugin;
        $this->public_key = $pk;
        $this->secret_key = $sk;
    }

    /**
     * Verify Payment Transaction
     *
     * @param string $reference
     * @param array $data
     * @return void
     */
    public function initialize_transaction($data)
    {
        $paystackUrl = $this->base_url . "transaction/initialize";

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $paystackUrl,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "authorization: Bearer " . $this->secret_key,
                "content-type: application/json",
                "cache-control: no-cache"
            ],
        ]);

        $request = curl_exec($curl);

        $res = json_decode($request, true);

        if (curl_errno($curl)) {
            throw new \moodle_exception(
                'errpaystackconnect',
                'enrol_paystack',
                '',
                array('url' => $paystackUrl, 'response' => $res),
                json_encode($data)
            );
        }

        return $res;
    }

    /**
     * Verify Payment Transaction
     *
     * @param string $reference
     * @return void
     */
    public function verify_transaction($reference)
    {
        $paystackUrl = $this->base_url . "transaction/verify/" . $reference;

        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $paystackUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "authorization: Bearer " . $this->secret_key,
                "content-type: application/json",
                "cache-control: no-cache"
            ],
        ]);

        $request = curl_exec($curl);
        // $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $res = json_decode($request, true);

        if (curl_errno($curl)) {
            throw new \moodle_exception(
                'errpaystackconnect',
                'enrol_paystack',
                '',
                array('url' => $paystackUrl, 'response' => $res),
                ''
            );
        }

        return $res;
    }

    /**
     * Track Payment Transactions from this Plugin
     *
     * @param string $trx_ref
     * @return void
     */
    public function log_transaction_success($reference)
    {
        //send reference to logger along with plugin name and public key
        $url = "https://plugin-tracker.paystackintegrations.com/log/charge_success";

        $params = [
            'public_key' => $this->public_key,
            'plugin_name'  => $this->plugin_name,
            'transaction_reference' => $reference,
        ];

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "content-type: application/json",
                "cache-control: no-cache"
            ],
        ]);

        // execute post
        curl_exec($curl);
    }

    /**
     * Validate Webhook Signature
     *
     * @param $input
     * @return boolean
     */
    public function validate_webhook($input)
    {
        return !hash_equals(hash_hmac('sha512', $input, (string) $this->secret_key), $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '');
    }
}
