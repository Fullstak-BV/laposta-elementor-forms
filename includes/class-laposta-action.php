<?php
	
	if ( ! defined( 'ABSPATH' ) ) {
		exit; // Exit if accessed directly.
	}
	
	/**
	 * Elementor form laposta action.
	 *
	 * Custom Elementor form action which will send data to Laposta.
	 *
	 * @since 1.0.0
	 */
	class Laposta_Action extends \ElementorPro\Modules\Forms\Classes\Action_Base {
		/**
		 * Get action name.
		 *
		 * Retrieve action name.
		 *
		 * @since 1.0.0
		 * @access public
		 * @return string
		 */
		public function get_name() {
			return 'laposta';
		}
		
		/**
		 * Get action label.
		 *
		 * Retrieve action label.
		 *
		 * @since 1.0.0
		 * @access public
		 * @return string
		 */
		public function get_label() {
			return __( 'Laposta', 'laposta-elementor-forms' );
		}
		
		/**
		 * Register action controls.
		 *
		 * Add required fields for Laposta to work.
		 *
		 * @since 1.0.0
		 * @access public
		 * @param \Elementor\Widget_Base $widget
		 */
		public function register_settings_section( $widget ) {
			$widget->start_controls_section(
				'section_laposta',
				[
					'label' => __( 'Laposta', 'laposta-elementor-forms' ),
					'condition' => [
						'submit_actions' => $this->get_name(),
					],
				]
			);
			
			$widget->add_control(
				'apikey',
				[
					'label' => __( 'API Key', 'laposta-elementor-forms' ),
					'type' => \Elementor\Controls_Manager::TEXT,
					'label_block' => true,
					'separator' => 'before',
					'description' => __( '', 'laposta-elementor-forms' ),
				]
			);
			$widget->add_control(
				'listid',
				[
					'label' => __( 'Lijst ID', 'laposta-elementor-forms' ),
					'type' => \Elementor\Controls_Manager::TEXT,
					'label_block' => true,
					'separator' => 'before',
					'description' => __( '', 'laposta-elementor-forms' ),
				]
			);
			
			$widget->add_control(
				'laposta_email',
				[
					'label' => __( 'E-mailadres veld', 'laposta-elementor-forms' ),
					'type' => \Elementor\Controls_Manager::SELECT,
					'label_block' => true,
					'separator' => 'before',
					'description' => __( 'Welk veld in je formulier is het e-mailadres?', 'laposta-elementor-forms' ),
					'options' => []
				]
			);
			$widget->end_controls_section();
		}
		
		/**
		 * Run action.
		 *
		 * External server after form submission.
		 *
		 * @since 1.0.0
		 * @access public
		 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record
		 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
		 */
		public function run( $record, $ajax_handler ) {
			$settings = $record->get( 'form_settings' );
			$base = 'https://api.laposta.nl/';
			$path = 'v2/member';
			
			if ( empty( $settings['apikey'] ) || empty( $settings['listid'] ) || empty( $settings['laposta_email'] ) ) {
				return;
			}
			
			$ch = curl_init($base.$path.'?list_id='.$settings['listid']);
			
			$raw_fields = $record->get( 'fields' );
			$data = [
				'list_id' => $settings['listid'],
				'ip' => \ElementorPro\Core\Utils::get_client_ip(),
				'email' => $raw_fields[$settings['laposta_email']]['value'],
				'custom_fields' => [], // @TODO: add custom fields support
				'source_url' => get_home_url()
			];
			
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($ch, CURLOPT_USERPWD, $settings['apikey'] . ':');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			$response = json_decode(curl_exec($ch));
			curl_close($ch);
			
			if (isset($response->error->code)) {
				switch($response->error->code):
					case 203:
						$ajax_handler->add_error_message('We konden geen lijst vinden om je voor in te schrijven.');
						break;
					case 204:
						$ajax_handler->add_error_message('Dit e-mailadres is al ingeschreven.');
						break;
					case 208:
						$ajax_handler->add_error_message('Dit e-mailadres is ongeldig.');
						break;
					default:
						$ajax_handler->add_error_message('Er is iets misgegaan. Probeer het later nog eens.');
						break;
				endswitch;
			}
		}
		
		/**
		 * On export.
		 *
		 * Settings/fields when exporting.
		 *
		 * @since 1.0.0
		 * @access public
		 * @param array $element
		 */
		public function on_export($element) {}
	}