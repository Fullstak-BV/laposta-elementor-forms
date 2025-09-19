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


            foreach ($settings as $key => $field) {
                if (stripos($key, '_laposta_field_') === 0) {
                    $custom_field_name = substr($key, strlen('_laposta_field_'));
                    if($custom_field_name==='null') continue;

                    $field_value = $this->get_submitted_field_value($raw_fields, $field);
                    if (null === $field_value) {
                        continue;
                    }

                    if($custom_field_name==='email') {
                        $data['email'] = $field_value;
                        continue;
                    }
                    $data['custom_fields'][$custom_field_name] = $this->map_field_values(
                        $field_value,
                        $this->parse_field_mapping(
                            isset($settings['_laposta_field_mapping_'.$custom_field_name]) ? $settings['_laposta_field_mapping_'.$custom_field_name] : ''
                        )
                    );
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

		/**
		 * Retrieve a submitted field value.
		 *
		 * Uses raw checkbox values when present to support multiple selections.
		 *
		 * @param array  $raw_fields
		 * @param string $field_key
		 * @return mixed|null
		 */
		private function get_submitted_field_value($raw_fields, $field_key) {
			if (empty($field_key) || !isset($raw_fields[$field_key])) {
				return null;
			}

			$field_data = $raw_fields[$field_key];

			if (isset($field_data['raw_value'])) {
				$raw_value = $field_data['raw_value'];
				if (is_array($raw_value)) {
					return $raw_value;
				}

				if (!isset($field_data['value'])) {
					return $raw_value;
				}
			}

			if (isset($field_data['value'])) {
				return $field_data['value'];
			}

			return null;
		}

		/**
		 * Convert saved mapping text into an associative array.
		 *
		 * @param string $mapping_text
		 * @return array
		 */
		private function parse_field_mapping($mapping_text) {
			if (empty($mapping_text)) {
				return [];
			}

			if (is_array($mapping_text)) {
				return $mapping_text;
			}

			if (is_string($mapping_text)) {
				$trimmed = trim($mapping_text);
				if ('' === $trimmed) {
					return [];
				}

				$decoded = json_decode($trimmed, true);
				if (JSON_ERROR_NONE === json_last_error() && is_array($decoded)) {
					return $decoded;
				}

				$lines = preg_split('/\r\n|\r|\n/', $trimmed);
				$mapping = [];

				foreach ($lines as $line) {
					$line = trim($line);
					if ('' === $line) {
						continue;
					}

					if (preg_match('/\s*(.+?)\s*(=>|=|:)\s*(.*)$/', $line, $matches)) {
						$source = trim($matches[1]);
						$target = trim($matches[3]);
						if ('' === $source) {
							continue;
						}
						$mapping[$source] = $target;
					}
				}

				return $mapping;
			}

			return [];
		}

		/**
		 * Apply mapping to submitted values.
		 *
		 * @param mixed $value
		 * @param array $mapping
		 * @return mixed
		 */
        private function map_field_values($value, $mapping) {
            if (empty($mapping)) {
                return $value;
            }

            $apply_mapping = function ($item) use ($mapping) {
                if (is_scalar($item)) {
                    $scalar = (string) $item;
                    if (array_key_exists($scalar, $mapping)) {
                        return $mapping[$scalar];
                    }
                }

                return $item;
            };

            if (is_array($value)) {
                return array_map($apply_mapping, $value);
            }

            return $apply_mapping($value);
        }
	}
