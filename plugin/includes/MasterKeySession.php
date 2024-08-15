<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

//require_once MASTERKEY_PLUGIN_DIR . 'includes/QRCode.php';
require_once 'QRCode.php';
use splitbrain\phpQRCode\QRCode;

/**
 *
 * MasterKey connector
 * @package     masterkey
 * @copyright   2024 Central Data System Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class MasterKeySession {
    private static $qrquality_map = array(
        'low' => 'qrl',
        'medium' => 'qrm',
        'quartile' => 'qrq',
        'high' => 'qrh'
    );

    private $kbapiurl;
    private $passwordlessurl;
    private $apikey;

    public $digest;
    public $kbapihost;
    public $passwordlesshost;
    public $session;
    public $token;
    public $secret;
    public $error;

    private static function genRandomString($length = 32) {
        return str_replace('+', '-', str_replace('/', '.', substr(base64_encode(random_bytes($length)), 0, $length)));
    }

    private static function curl_kbapi(string $kbapiurl, string $method, string $apikey, string $endpoint, ?array $data = null) {
        $curl = curl_init();

        curl_setopt_array($curl, [
        CURLOPT_URL => $kbapiurl . $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 1,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_POSTFIELDS => $data != null ? json_encode($data) : null,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "X-Api-Key: " . $apikey
        ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            error_log('ERROR: MasterKey: ' . print_r($err, TRUE));
            return (object)["success" => false, "message" => $err];
        } else {
            if (empty($response)) {
                error_log('ERROR: MasterKey: Empty response - ', print_r($kbapiurl, TRUE), print_r($method, TRUE));
                return (object)["success" => false, "message" => "Empty response"];
            }
            $ret = json_decode($response);
            if (!$ret->success) error_log('ERROR: MasterKey: ' . print_r($ret->message, TRUE));
            return $ret;
        }
    }

    private static function gen_urls(string $kbapihost, string $passwordlesshost) {
        $kbapiurl = 'https://' . $kbapihost . '/api/v1/';
        $passwordlessurl = 'https://' . $passwordlesshost . '/app/';
        return [$kbapiurl, $passwordlessurl];
    }

    public function __construct(string $kbapihost, string $passwordlesshost, string $apikey, string $digest="MD5") {
        [$kbapiurl, $passwordlessurl] = MasterKeySession::gen_urls($kbapihost, $passwordlesshost);

        $this->kbapihost        = $kbapihost;
        $this->kbapiurl         = $kbapiurl;
        $this->passwordlesshost = $passwordlesshost;
        $this->passwordlessurl  = $passwordlessurl;
        $this->apikey           = $apikey;
        $this->digest           = $digest;

        $resp = MasterKeySession::curl_kbapi($kbapiurl, "POST", $apikey, "session", []);
        if ($resp->success) {
            $this->session = $resp->session;
            $this->token   = $resp->token;
            $this->secret  = MasterKeySession::genRandomString(32);
        } else {
            $this->error = $resp->message;
        }
    }

    /**
     * Generate QR code.
     *
     * @param 'low'|'medium'|'quartile'|'high' $elevel error correction level
     * 
     * @return QRCode
     */
    public function qrcode($elevel = 'medium') {
        return QRCode::svg($this->passwordlessurl . $this->session . $this->secret, $elevel);
    }

    public function check_session() {
        $resp = MasterKeySession::curl_kbapi($this->kbapiurl, "GET", $this->apikey, "session/" . $this->session);
        return $resp->success;
    }

    public function retrieve_form() {
        $resp = MasterKeySession::curl_kbapi($this->kbapiurl, "POST", $this->apikey, "session/" . $this->session, ['session' => $this->session, 'token' => $this->token]);
        if (!$resp->success) {
            throw new Exception("Failed to retrieve keys: " . $resp->message);
        }

        $resp->decoded = $this->decode_form($resp->keys, $resp->form);

        return $resp;
    }

    public function decode_form(object $keys, object $form) {
        $enc_key_table = [];
        foreach ($keys as $key => $value) {
        $ekey = hash($this->digest, $key . $this->secret);
        $enc_key_table[$ekey] = $value;
        }
        $resp = [];
        foreach ($form as $key => $value) {
        $decoded = '';
        foreach ($value as $v) {
            $decoded .= $enc_key_table[$v];
        }
        $resp[$key] = $decoded;
        }
        return $resp;
    }

    public function retrieve_decrypted_form() {
        $resp = MasterKeySession::curl_kbapi($this->kbapiurl, "POST", $this->apikey, "session/decrypt/" . $this->session, ['session' => $this->session, 'token' => $this->token, 'secret' => $this->secret]);
        if (!$resp->success) {
        throw new Exception("Failed to retrieve keys: " . $resp->message);
        }
        return $resp;
    }

    public function delete() {
        MasterKeySession::curl_kbapi($this->kbapiurl, "DELETE", $this->apikey, "session/" . $this->session);
    }
}
