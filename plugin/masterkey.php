<?php
/**
 * Plugin Name: MasterKey
 * Plugin URI: https://github.com/central-data-systems/masterkey-wordpress.git
 * Description: MasterKey connector for Wordpress.
 * Version: 1.0.0
 * Author: Central Data System Pty Ltd
 * Author URI: https://central-data.net
 * License: GPLv3
 * License URI: https://www.gnu.org/copyleft/gpl.html
 */

if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('MASTERKEY_VERSION', '1.0.0');
define('MASTERKEY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MASTERKEY_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include the necessary parts of phpqrcode library
require_once MASTERKEY_PLUGIN_DIR . 'includes/MasterKeySession.php';

/**
 * The core plugin class.
 */
class MasterKey {
    static private $setting2class = [
        'none' => '',
        'left' => 'horiz-start',
        'right' => 'horiz-end',
        'above' => 'vert-start',
        'below' => 'vert-end',
    ];

    static private function create_new_mk_session() {
        $apikey = get_option('masterkey_apikey');
        $kbapi_host = get_option('masterkey_kbapihost');
        $passwordless_host = get_option('masterkey_passwordlesshost');
        return new MasterKeySession($kbapi_host, $passwordless_host, $apikey);
    }

    /**
     * Delete MasterKey session
     *
     */
    static public function delete_masterkey_session() {
        $mk = wp_cache_get('masterkey');
        if ((!$mk || empty($mk->session)) && !empty($_SESSION['masterkey'])) $mk = unserialize($_SESSION['masterkey']);
        if ($mk && !empty($mk->session)) $mk->delete();
        unset($_SESSION['masterkey']);
        wp_cache_delete('masterkey');
    }

    private $rbk; // Block type registry
    private $logo; // svg logo

    /**
     * Initialize the plugin.
     */
    public function __construct() {
        $this->logo = file_get_contents(MASTERKEY_PLUGIN_DIR . 'logo.svg');
        add_action('init', array($this, 'init'));

        add_action('login_init', array($this, 'sess_init'));
        add_action('login_form', array($this, 'render_qrcode_login'), 10, 1);
        add_action('login_enqueue_scripts', array($this, 'enqueue_login_scripts'));
        add_action('login_footer', array($this, 'inject_keyboard'));

        add_action('wp_authenticate', array($this, 'authenticate'), 10, 2);
        add_action('authenticate', array($this, 'authenticate_after'), 100, 3);

        // Settings
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Initialize the plugin.
     */
    public function init() {
        if (!session_id()) {
            session_start();
        }
        $this->rbk = $this->register_block();
    }

    /**
     * Register editor block
     */
    public function register_block() {
        wp_register_script(
            'masterkey-block-js',
            MASTERKEY_PLUGIN_URL . 'js/masterkey-block.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
            MASTERKEY_VERSION
        );
        wp_localize_script( 'masterkey-block-js', "masterkeyVars", ['secured_by' => MASTERKEY_PLUGIN_URL . 'images/mk-secured-by.svg'] );

        $rbk = register_block_type('masterkey/login-block', array(
            'editor_script' => 'masterkey-block-js',
            'render_callback' => array($this, 'render_block'),
            'attributes' => [
                'use_form' => ['enum' => ['none', 'left', 'right', 'above', 'below'], 'default' => 'none'],
            ]
        ));
        wp_enqueue_block_style('masterkey/login-block', [
            'handle' => 'masterkey-block-css',
            'src' => MASTERKEY_PLUGIN_URL . 'css/masterkey.css',
        ]);
        wp_enqueue_block_style('masterkey/login-block', [
            'handle' => 'masterkey-wp-login-css',
            'src' => MASTERKEY_PLUGIN_URL . 'css/login-form.css', //  '/wp-admin/css/login.css',
        ]);
        return $rbk;
    }

    /**
     * Render block
     */
    public function render_block($attributes) {
        $is_editor = defined('REST_REQUEST') && REST_REQUEST;
        $use_form = $attributes['use_form'];
        $is_mobile = wp_is_mobile();
        if ($is_editor || is_admin()) {
            if (!$is_editor) return '** MasterKey **';
            return $this->render_masterkey($this->logo, $use_form, true);
        }
        if ($is_mobile) add_action('wp_footer', array($this, 'inject_keyboard'));
        $mk = $this->sess_init();
        if (!$mk || empty($mk->session)) return;
        $this->enqueue_mk_scripts();
        if ($is_mobile) return;
        $ecl = get_option('masterkey_qrquality', 'medium');
        return $this->render_masterkey($mk->qrcode($ecl), $use_form);
    }

    /**
     * Fires on the login page to initialize the MasterKey session.
     */
    public function sess_init() {
        $mk = wp_cache_get( 'masterkey');
        if ($mk && $mk->session) {
            if ($mk->check_session()) {
                $_SESSION['masterkey'] = serialize($mk);
                return;
            }
            wp_cache_delete('masterkey');
            unset($_SESSION['masterkey']);
        }

        if (isset($_SESSION['masterkey'])) $mk = unserialize($_SESSION['masterkey']);
        if (!$mk || !$mk->session || !$mk->check_session()) {
            $mk = MasterKey::create_new_mk_session();
            $_SESSION['masterkey'] = serialize($mk);
        }
        wp_cache_set('masterkey', $mk);
        return $mk;
    }

    /**
     * Enqueue scripts and styles.
     */
    public function enqueue_login_scripts() {
        $this->enqueue_mk_scripts();
        wp_enqueue_style('masterkey-css', MASTERKEY_PLUGIN_URL . 'css/masterkey.css', array(), MASTERKEY_VERSION);
    }

    private function enqueue_mk_scripts() {
        $mk = wp_cache_get('masterkey');
        if (!$mk || empty($mk->session)) return;

        //wp_enqueue_script('bankvault-api-js', 'https://' . $mk->kbapihost . (wp_is_mobile() ? '/js/BankVaultMobile.js' : '/js/BankVaultApi.js'), array(), null);
        if (wp_is_mobile()) {
            wp_enqueue_script('bankvault-md5-js', 'https://' . $mk->kbapihost . '/js/md5.js', array(), null);
            wp_enqueue_script('bankvault-api-js', 'https://' . $mk->kbapihost . '/js/BankVaultMobile.js', array(), null);
        } else {
            wp_enqueue_script('bankvault-api-js', 'https://' . $mk->kbapihost . '/js/BankVaultApi.js', array(), null);
        }
        //wp_enqueue_script('bankvault-api-js', MASTERKEY_PLUGIN_URL . (wp_is_mobile() ? 'js/BankVaultMobile.js' : 'js/BankVaultApi.js'), array(), MASTERKEY_VERSION, true);
        wp_enqueue_script('masterkey-init-js', MASTERKEY_PLUGIN_URL . 'js/masterkey-init.js', array('jquery'), MASTERKEY_VERSION, true);
        wp_localize_script('masterkey-init-js', 'masterkeyLoginData', array(
            'kbapihost' => $mk->kbapihost,
            'session' => $mk->session,
            'secret' => wp_is_mobile() ? $mk->secret : null,
        ));
    }

    private function render_masterkey($svg, $use_form) {
        $login_form = $use_form !== 'none' ? '<div class="wp-block-masterkey-login-block login-form-block"><h1>Login</h1>' . wp_login_form(array('echo' => false)) . '</div>' : null;
        ob_start();
        $this->render_qrcode($svg, $use_form, $login_form);
        $output = ob_get_contents();
        ob_end_clean();
        return $output;
    }

    /**
     * Add QR code to login form.
     */
    public function render_qrcode_login() {
        if (wp_is_mobile()) return;
        $mk = wp_cache_get('masterkey');
        if (!$mk || empty($mk->session)) return;
        $ecl = get_option('masterkey_qrquality', 'medium');
        $this->render_qrcode($mk->qrcode($ecl));
        ?> <script>
            document.addEventListener("DOMContentLoaded", () => jQuery('#masterkey-wrapper').prependTo('#loginform'));
        </script> <?php
    }

    /**
     * Add QR code to login form.
     */
    private function render_qrcode($svg, $use_form = 'below', $form = null) {
        $class = self::$setting2class[$use_form];
        ?><div id="masterkey-wrapper" class="<?php echo $class; ?>">
            <div id="masterkey-scan">
                <h1>Scan to Login</h1>
                <div id="masterkey-scan-img">
                    <?php echo $svg; ?>
                    <div id="masterkey-secured-by">
                        <?php echo file_get_contents(MASTERKEY_PLUGIN_DIR . 'images/mk-secured-by.svg'); ?>
                    </div>
                </div>
            </div>
            <?php if ($use_form !== 'none') { ?><div id="masterkey-divider"><div></div><p>or</p><div></div></div> <?php } ?>
            <?php if ($form) echo $form;?>
        </div><?php
    // } else {
    //     echo '<p>' . $mk->error . '</p>';
    // }
    }

    /**
     * Fires in the login page footer.
     *
     */
    function inject_keyboard() : void {
        if (!wp_is_mobile()) return;
        $mk = wp_cache_get('masterkey');
        if( !$mk || empty($mk->session) ) return;
        ?>
        <aside tabindex="-1" role="dialog" aria-labelledby="modal-label" id="bankvault-keyboard" style="display: none;">
          <iframe id="iframe" sandbox="allow-scripts" style="height:300px;width:100%;border:none;background-color: #f0f0f1;" frameborder="0" src="https://<?php echo esc_attr($mk->kbapihost); ?>/keyboard/?shuffle=none"></iframe>
        </aside>
        <aside tabindex="-1" role="dialog" aria-labelledby="modal-label" aria-hidden="true" id="bankvault-mobilevault" style="display: none;">
          <iframe id="iframe-vault" sandbox="allow-same-origin allow-scripts allow-modals" style="height:0px;width:100%;margin-bottom:0x;border:none;" frameborder="0" src="https://<?php echo esc_attr($mk->passwordlesshost); ?>/vault/"></iframe>
        </aside>
        <?php
    }

    /**
     * Intercept login form submission.
     *
     * @param null|WP_User|WP_Error $user     WP_User if the user is authenticated.
     *                                        WP_Error or null otherwise.
     * @param string                $username Username or email address.
     * @param string                $password User password
     *
     * @return null|WP_User|WP_Error
     */
    public function authenticate(&$username, &$password) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        $mk = wp_cache_get('masterkey');
        if (!$mk || empty($mk->session)) return;// $user;

        $resp = $mk->retrieve_form();
        MasterKey::delete_masterkey_session();
        if (!$resp->success) {
            return;// new WP_Error('masterkey_error', $resp->message);
        }

        if (!empty($resp->decoded['log']) && !empty($resp->decoded['pwd'])) {
            $username = $resp->decoded['log'];
            $password = $resp->decoded['pwd'];
        } else if (!empty($resp->decoded['login']) && !empty($resp->decoded['password'])) {
            $username = $resp->decoded['login'];
            $password = $resp->decoded['password'];
        }
        //return null;
    }

    /**
     * Fires after a user has successfully authenticated.
     *
     * @param null|WP_User|WP_Error $user     WP_User if the user is authenticated.
     *                                        WP_Error or null otherwise.
     * @param string                $username Username or email address.
     * @param string                $password User password
     *
     * @return null|WP_User|WP_Error
     */
    public function authenticate_after($user, $username, $password) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return $user;
        if (is_null($user) || is_wp_error($user)) {
            MasterKey::delete_masterkey_session();
            $this->sess_init();
        }
        return $user;
    }

    // ============================ Configuration ============================
    //
    /**
     * Add admin menu.
     */
    public function add_admin_menu() {
        add_options_page(
            'MasterKey Settings',
            'MasterKey',
            'manage_options',
            'masterkey',
            array($this, 'display_admin_page')
        );
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        register_setting('masterkey_settings', 'masterkey_apikey');
        register_setting('masterkey_settings', 'masterkey_kbapihost', ['default' => 'kbapi.bankvault.com']);
        register_setting('masterkey_settings', 'masterkey_passwordlesshost', ['default' => 'passwordless.bankvault.com']);
        register_setting('masterkey_settings', 'masterkey_qrquality', ['default' => 'medium']);
    }

    /**
     * Display admin page.
     */
    public function display_admin_page() {
        ?>
        <div class="wrap">
            <h1>MasterKey Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('masterkey_settings');
                do_settings_sections('masterkey_settings');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">API Key</th>
                        <td><input type="password" name="masterkey_apikey" value="<?php echo esc_attr(get_option('masterkey_apikey')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">KbApi Host</th>
                        <td><input type="text" name="masterkey_kbapihost" value="<?php echo esc_attr(get_option('masterkey_kbapihost')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Passwordless Host</th>
                        <td><input type="text" name="masterkey_passwordlesshost" value="<?php echo esc_attr(get_option('masterkey_passwordlesshost')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">QR Code Quality</th>
                        <td>
                            <select name="masterkey_qrquality">
                                <?php
                                $current_quality = get_option('masterkey_qrquality', 'medium');
                                $quality_options = array(
                                    'low' => 'Low',
                                    'medium' => 'Medium',
                                    'quartile' => 'Quartile',
                                    'high' => 'High'
                                );
                                foreach ($quality_options as $value => $label) {
                                    echo '<option value="' . esc_attr($value) . '" ' . selected($current_quality, $value, false) . '>' . esc_html($label) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

// Initialize the plugin
new MasterKey();
