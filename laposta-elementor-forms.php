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
 * Version:           1.0.0
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
		private $version = '1.0.0';
		
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
				$this->get_version(),
				true
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
			echo '<div class="notice notice-warning is-dismissible"><p>' . sprintf( __( '<strong>Laposta Elementor Forms</strong> requires <a href="%s" target="_blank"><strong>Elementor PRO</strong></a> to be installed and activated.', 'laposta-elementor-forms' ), admin_url() . 'plugin-install.php' ) . '</p></div>';
			
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
			echo '<div class="notice notice-warning is-dismissible"><p>' . sprintf( __( '<strong>Laposta Elementor Forms</strong> requires <strong>Elementor</strong> version %s or greater.', 'laposta-elementor-forms' ), self::$require_elementor_version ) . '</p></div>';
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

