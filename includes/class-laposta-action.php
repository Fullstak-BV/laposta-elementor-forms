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
				'laposta_api_key',
				[
					'label' => __( 'API Key', 'laposta-elementor-forms' ),
					'type' => \Elementor\Controls_Manager::TEXT,
					'label_block' => true,
					'description' => __( '', 'laposta-elementor-forms' ),
				]
			);
			$widget->add_control(
				'listid',
				[
					'label' => __( 'List', 'laposta-elementor-forms' ),
					'type' => \Elementor\Controls_Manager::SELECT,
					'label_block' => true,
					'description' => __( '', 'laposta-elementor-forms' ),
                    'options' => []
				]
			);
            $widget->add_control(
                'laposta_api_fields_heading',
                [
                    'label' => esc_html__( 'API fields mapping', 'laposta-elementor-forms' ),
                    'type' => \Elementor\Controls_Manager::HEADING,
                    'separator' => 'before'
                ]
            );

            $widget->add_control(
                'laposta_api_fields',
                [
                    'label' => esc_html__( '', 'laposta-elementor-forms' ),
                    'type' => \Elementor\Controls_Manager::RAW_HTML,
                    'raw' => '<div class="elementor-control-field-description">Map the form fields to the Laposta fields.</div>'
                ]
            );

            $widget->add_control(
                'hr',
                [
                    'type' => \Elementor\Controls_Manager::DIVIDER,
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
			$path = 'v2/member';
			
			if ( empty( $settings['laposta_api_key'] ) || empty( $settings['listid'] ) ) {
				return;
			}
			
			$raw_fields = $record->get( 'fields' );
			$data = [
				'list_id' => $settings['listid'],
				'ip' => \ElementorPro\Core\Utils::get_client_ip(),
				'email' => '',
				'custom_fields' => [],
				'source_url' => get_home_url()
			];

            //$ajax_handler->add_error_message(print_r($settings, true));
            foreach ($settings as $key => $field) {
                if (stripos($key, '_laposta_field_') === 0) {
                    $custom_field_name = substr($key, strlen('_laposta_field_'));
                    if($custom_field_name==='null') continue;
                    if($custom_field_name==='email') {
                        $data['email'] = $raw_fields[$field]['value'];
                        continue;
                    }
                    $data['custom_fields'][$custom_field_name] = $raw_fields[$field]['value'];
                }
            }
            $response = laposta_api_call($settings['laposta_api_key'], $path, 'POST', $data);

			if (isset($response['error']['code'])) {
				switch($response['error']['code']):
					case 203:
						$ajax_handler->add_error_message(__('This email address is already subscribed.', 'laposta-elementor-forms'));
						break;
					case 204:
						$ajax_handler->add_error_message(__('This email address is already subscribed.', 'laposta-elementor-forms'));
						break;
					case 208:
						$ajax_handler->add_error_message(__('This email address is already subscribed.', 'laposta-elementor-forms'));
						break;
					default:
						$ajax_handler->add_error_message(__('An error occurred while subscribing.', 'laposta-elementor-forms'));
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