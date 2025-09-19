<?php

/**
 *
 * @link              https://fullstak.nl/
 * @since 1.0.0
 * @package           Laposta_Elementor_Forms
 *
 * @wordpress-plugin
 * Plugin Name:       Laposta Elementor Forms Integration
 * Plugin URI:        https://fullstak.nl/
 * Description:       Simple plugin that let's you use Elementor forms to register visitors to your Laposta relation list.
 * Version:           2.1.0
 * Author:            Bram Hammer
 * Author URI:        https://fullstak.nl//
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       laposta-elementor-forms
 * Domain Path:       /languages
 *
 * Elementor tested up to: 3.14
 * Elementor Pro tested up to: 3.14
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( ! defined( 'LAPOSTA_BASE' ) ) {
    define( 'LAPOSTA_BASE', 'https://api.laposta.nl/' );
}

if ( ! defined( 'LAPOSTA_DEBUG' ) ) {
    define( 'LAPOSTA_DEBUG', false );
}

// If class `Jet_Woo_Builder` doesn't exists yet.
if ( ! class_exists( 'Laposta_Elementor_Forms' ) ) {

    /**
     * Sets up and initializes the plugin.
     */
    #[AllowDynamicProperties]
    class Laposta_Elementor_Forms {

        /**
         * A reference to an instance of this class.
         *
         * @since 1.0.0
         * @access private
         * @var    object
         */
        private static $instance = null;

        /**
         * Plugin version
         *
         * @var string
         */
        private $version = '2.1.0';

        /**
         * @var bool Debug mode
         */
        private $debug = false;

        /**
         * Require Elementor Version
         *
         * @since 1.8.0
         * @var string Elementor version required to run the plugin.
         */
        private static $require_elementor_version = '3.0.0';

        /**
         * Holder for base plugin URL
         *
         * @since 1.0.0
         * @access private
         * @var    string
         */
        private $plugin_url = null;

        /**
         * Holder for base plugin path
         *
         * @since 1.0.0
         * @access private
         * @var    string
         */
        private $plugin_path = null;

        /**
         * Sets up needed actions/filters for the plugin to initialize.
         *
         * @since 1.0.0
         * @access public
         * @return void
         */
        public function __construct() {

            // Set debug mode
            $this->debug = defined( 'LAPOSTA_DEBUG' ) && LAPOSTA_DEBUG;

            // Internationalize the text strings used.
            add_action( 'init', [$this, 'lang'], -999 );

            // Load files.
            add_action( 'init', [$this, 'init'], -999 );

            // Register activation and deactivation hook.
            register_activation_hook( __FILE__, [$this, 'activation']);
            register_deactivation_hook( __FILE__, [$this, 'deactivation']);

        }

        /**
         * Register.
         *
         * Register Elementor Forms action.
         *
         * @since 1.0.0
         * @param ElementorPro\Modules\Forms\Registrars\Form_Actions_Registrar $form_actions_registrar
         * @access public
         *
         * @return void
         */
        public function register_action( $form_actions_registrar ) {
            include_once( __DIR__ .  '/includes/class-laposta-action.php' );
            $form_actions_registrar->register( new \Laposta_Action() );
        }

        /**
         * Returns plugin version
         *
         * @return string
         */
        public function get_version() {
            return $this->version;
        }

        /**
         * Lang.
         *
         * Loads the translation files.
         *
         * @since 1.0.0
         * @access public
         *
         * @return void
         */
        public function lang() {
            load_plugin_textdomain( 'laposta-elementor-forms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }

        /**
         * Init.
         *
         * Manually init required actions.
         *
         * @since 1.0.0
         * @access public
         *
         * @return void
         */
        public function init() {

            // Check if Elementor installed and activated.
            if ( ! did_action( 'elementor/loaded' ) ) {
                add_action( 'admin_notices', [ $this, 'admin_notice_missing_main_plugin' ] );
                return;
            }

            // Check for required Elementor version.
            if ( ! version_compare( ELEMENTOR_VERSION, self::$require_elementor_version, '>=' ) ) {
                add_action( 'admin_notices', [ $this, 'admin_notice_required_elementor_version' ] );
                return;
            }

            // Check if Elementor pro is installed.
            if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
                add_action( 'admin_notices', [ $this, 'admin_notice_missing_main_plugin' ] );
                return;
            }

            // Init Elementor Forms integration.
            add_action( 'elementor_pro/forms/actions/register', [$this, 'register_action']);

            // Load field mapping javascript.
            add_action( 'elementor/editor/before_enqueue_scripts', [ $this, 'enqueue_editor_scripts' ] );
        }

        /**
         * Enqueue editor scripts.
         *
         * @since 1.0.0
         * @access public
         *
         * @return void
         */
        public function enqueue_editor_scripts() {

            // Register the script.
            wp_register_script(
                'laposta-elementor-forms-editor',
                plugin_dir_url( __FILE__ ) . 'assets/editor.js',
                [
                    'elementor-editor',
                ],
                $this->debug? time() : $this->version,
                true
            );

            wp_localize_script(
                'laposta-elementor-forms-editor',
                'lapostaElementorForms',
                [
                    'debug' => LAPOSTA_DEBUG,
                    'mappingLabels' => [
                        'formOption' => __( 'Formulier optie', 'laposta-elementor-forms' ),
                        'lapostaOption' => __( 'Laposta optie', 'laposta-elementor-forms' ),
                        'selectPrompt' => __( 'Kies...', 'laposta-elementor-forms' ),
                        'noFormOptions' => __( 'Selecteer een formulier veld met opties om een mapping te maken.', 'laposta-elementor-forms' ),
                        'noLapostaOptions' => __( 'Dit Laposta veld heeft geen opties.', 'laposta-elementor-forms' ),
                        'noMappingNeeded' => __( 'Geen extra mapping nodig voor dit veld.', 'laposta-elementor-forms' ),
                    ],
                ]
            );

            // Enqueue the script.
            wp_enqueue_script( 'laposta-elementor-forms-editor' );
        }

        /**
         * Show required plugins admin notice.
         *
         * @since 1.0.0
         * @access public
         *
         * @return void
         */
        public function admin_notice_missing_main_plugin() {

            /* translators: %s Elementor install/activate URL link. */
            $allowed_tags = [
                'strong' => [],
                'a'      => [
                    'href'   => [],
                    'target' => [],
                    'rel'    => [],
                ],
            ];

            echo '<div class="notice notice-warning is-dismissible"><p>' . sprintf( wp_kses( __( '<strong>Laposta Elementor Forms</strong> requires <a href="%s" target="_blank"><strong>Elementor PRO</strong></a> to be installed and activated.', 'laposta-elementor-forms' ), $allowed_tags ), admin_url() . 'plugin-install.php' ) . '</p></div>';

        }

        /**
         * Show minimum required Elementor version admin notice.
         *
         * @since 1.0.0
         * @access public
         *
         * @return void
         */
        public function admin_notice_required_elementor_version() {
            /* translators: %s Elementor required version. */
            $allowed_tags = [
                'strong' => [],
            ];

            echo '<div class="notice notice-warning is-dismissible"><p>' . sprintf( wp_kses( __( '<strong>Laposta Elementor Forms</strong> requires <strong>Elementor</strong> version %s or greater.', 'laposta-elementor-forms' ), $allowed_tags ), self::$require_elementor_version ) . '</p></div>';
        }

        /**
         * Do some stuff on plugin activation.
         *
         * @since 1.0.0
         * @return void
         */
        public function activation() {}

        /**
         * Do some stuff on plugin activation.
         *
         * @since 1.0.0
         * @return void
         */
        public function deactivation() {}

        /**
         * Returns the instance.
         *
         * @since 1.0.0
         * @access public
         * @return object
         */
        public static function get_instance() {

            // If the single instance hasn't been set, set it now.
            if ( null == self::$instance ) {
                self::$instance = new self;
            }

            return self::$instance;

        }

    }

}

if ( ! function_exists( 'laposta_elementor_forms' ) ) {

    /**
     * Returns instance of the plugin class.
     *
     * @since 1.0.0
     * @return object
     */
    function laposta_elementor_forms() {
        return Laposta_Elementor_Forms::get_instance();
    }

}

laposta_elementor_forms();

// Hook for handling the boards request
add_action('wp_ajax_fetch_laposta_lists', 'fetch_laposta_lists');
add_action('wp_ajax_nopriv_fetch_laposta_lists', 'fetch_laposta_lists');

function laposta_api_call($api_key, $path, $method = 'GET', $data = [])
{
    $api_key = sanitize_text_field($api_key);

    if($method === 'POST') {
        $response = wp_remote_post(LAPOSTA_BASE . $path, [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("$api_key:"),
            ],
            'body' => json_encode($data),
        ]);
    } else {
        $response = wp_remote_get(LAPOSTA_BASE . $path, [
            'method' => 'GET',
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("$api_key:"),
            ],
            'body' => [],
        ]);
    }

    if (is_wp_error($response)) {
        wp_send_json_error('Error fetching lists: ' . $response->get_error_message());
    }

    try{
        return json_decode(wp_remote_retrieve_body($response), true);
    } catch (Exception $e) {
        wp_send_json_error('Error decoding response: ' . $e->getMessage());
        exit();
    }
}

function fetch_laposta_lists() {
    $api_key = sanitize_text_field($_POST['api_key']);

    $path = 'v2/list';
    $get_list = laposta_api_call($api_key, $path);

    if (isset($get_list['data'])) {
        wp_send_json_success($get_list['data']);
    } else {
        wp_send_json_error('No lists found.');
    }
}

add_action('wp_ajax_fetch_laposta_list_fields', 'fetch_laposta_list_fields');
add_action('wp_ajax_nopriv_fetch_laposta_list_fields', 'fetch_laposta_list_fields');

function fetch_laposta_list_fields() {
    if ( ! isset( $_POST['api_key'], $_POST['list_id'] ) ) {
        wp_send_json_error(
            [ 'message' => __( 'Missing Laposta request parameters.', 'laposta-elementor-forms' ) ],
            400
        );
    }

    $api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ) );
    $list_id = sanitize_text_field( wp_unslash( $_POST['list_id'] ) );

    if ( '' === $api_key || '' === $list_id ) {
        wp_send_json_error(
            [ 'message' => __( 'Invalid Laposta request parameters.', 'laposta-elementor-forms' ) ],
            400
        );
    }

    $path      = 'v2/field';
    $endpoint  = $path . '?list_id=' . rawurlencode( $list_id );
    $response  = laposta_api_call( $api_key, $endpoint );

    if ( is_wp_error( $response ) ) {
        if ( defined( 'LAPOSTA_DEBUG' ) && LAPOSTA_DEBUG ) {
            error_log( sprintf( '[Laposta Elementor Forms] fetch_laposta_list_fields transport error: %s', $response->get_error_message() ) );
        }

        wp_send_json_error(
            [ 'message' => __( 'Connectivity issue: unable to reach Laposta right now.', 'laposta-elementor-forms' ) ],
            500
        );
    }

    if ( isset( $response['data'] ) ) {
        wp_send_json_success( $response['data'] );
    }

    $default_message = __( 'Unable to fetch Laposta fields for the selected list.', 'laposta-elementor-forms' );
    $error_message   = $default_message;

    if ( isset( $response['error']['message'] ) && '' !== $response['error']['message'] ) {
        $error_message = sanitize_text_field( $response['error']['message'] );
    }

    if ( defined( 'LAPOSTA_DEBUG' ) && LAPOSTA_DEBUG ) {
        error_log( sprintf( '[Laposta Elementor Forms] fetch_laposta_list_fields error: %s', wp_json_encode( $response ) ) );
    }

    wp_send_json_error(
        [ 'message' => $error_message ],
        500
    );
}
