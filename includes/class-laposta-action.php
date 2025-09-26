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
					'description' => __( 'Enter the Laposta API key found in your Laposta account settings.', 'laposta-elementor-forms' ),
				]
			);
			$widget->add_control(
				'listid',
				[
					'label' => __( 'List', 'laposta-elementor-forms' ),
					'type' => \Elementor\Controls_Manager::SELECT,
					'label_block' => true,
					'description' => __( 'Choose the Laposta list that should receive the submitted form entries.', 'laposta-elementor-forms' ),
                    'options' => []
				]
			);

            // Upsert switch - update existing subscriber if email exists
            $widget->add_control(
                'upsert',
                [
                    'label' => __( 'Update existing subscriber (upsert)', 'laposta-elementor-forms' ),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => __( 'Yes', 'laposta-elementor-forms' ),
                    'label_off' => __( 'No', 'laposta-elementor-forms' ),
                    'return_value' => 'yes',
                    'default' => 'yes',
                    'description' => __( 'If enabled, an existing subscriber with the same email will be updated instead of causing an error.', 'laposta-elementor-forms' ),
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
                    'label' => '',
                    'type' => \Elementor\Controls_Manager::RAW_HTML,
                    'raw' => '<div class="elementor-control-field-description">' . esc_html__( 'Map the form fields to the Laposta fields.', 'laposta-elementor-forms' ) . '</div>'
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
			$should_upsert = isset( $settings['upsert'] ) ? ( 'yes' === $settings['upsert'] ) : true;
			$options = [];
			if ( $should_upsert ) {
				$options['upsert'] = true;
			}

			$data = [
				'list_id' => $settings['listid'],
				'ip' => \ElementorPro\Core\Utils::get_client_ip(),
				'email' => '',
				'custom_fields' => [],
				'source_url' => get_home_url()
			];

			if ( ! empty( $options ) ) {
				$data['options'] = $options;
			}

			$append_field_values = [];

			foreach ( $settings as $key => $field ) {
				if ( stripos( $key, '_laposta_field_' ) === 0 ) {
					$custom_field_name = substr( $key, strlen( '_laposta_field_' ) );
					if ( 'null' === $custom_field_name ) {
						continue;
					}

					$field_value = $this->get_submitted_field_value( $raw_fields, $field );
					if ( null === $field_value ) {
						continue;
					}

					if ( 'email' === $custom_field_name ) {
						$data['email'] = $field_value;
						continue;
					}
					$data['custom_fields'][ $custom_field_name ] = $this->map_field_values(
						$field_value,
						$this->parse_field_mapping(
							isset( $settings[ '_laposta_field_mapping_' . $custom_field_name ] ) ? $settings[ '_laposta_field_mapping_' . $custom_field_name ] : ''
						)
					);

					$append_setting_key   = '_laposta_field_append_' . $custom_field_name;
					$should_append_field = isset( $settings[ $append_setting_key ] ) && 'yes' === $settings[ $append_setting_key ];
					if ( $should_append_field ) {
						$append_field_values[ $custom_field_name ] = (array) $data['custom_fields'][ $custom_field_name ];
					}
				}
			}

			if ( $should_upsert && ! empty( $append_field_values ) && ! empty( $data['email'] ) ) {
				$member_endpoint = sprintf( 'v2/member/%s?list_id=%s', rawurlencode( $data['email'] ), rawurlencode( $settings['listid'] ) );
				$existing_member = laposta_api_call( $settings['laposta_api_key'], $member_endpoint );
				if ( ! is_wp_error( $existing_member ) && ! empty( $existing_member['member']['custom_fields'] ) ) {
					$existing_custom_fields = $existing_member['member']['custom_fields'];
					foreach ( $append_field_values as $field_key => $new_values ) {
						$existing_values = [];
						if ( isset( $existing_custom_fields[ $field_key ] ) ) {
							$existing_values = $existing_custom_fields[ $field_key ];
						}
						if ( ! is_array( $existing_values ) ) {
							if ( '' === $existing_values || null === $existing_values ) {
								$existing_values = [];
							} else {
								$existing_values = [ $existing_values ];
							}
						}
						$merged_values = array_values( array_unique( array_merge( $existing_values, (array) $new_values ) ) );
						$data['custom_fields'][ $field_key ] = $merged_values;
					}
				} elseif ( is_wp_error( $existing_member ) && defined( 'LAPOSTA_DEBUG' ) && LAPOSTA_DEBUG ) {
					error_log( sprintf( '[Laposta Elementor Forms] upsert append fetch error: %s', $existing_member->get_error_message() ) );
				}
			}
			$response = laposta_api_call( $settings['laposta_api_key'], $path, 'POST', $data );
			if ( is_wp_error( $response ) ) {
				$ajax_handler->add_error_message( __( 'Unable to communicate with Laposta at the moment.', 'laposta-elementor-forms' ) );
				if ( defined( 'LAPOSTA_DEBUG' ) && LAPOSTA_DEBUG ) {
					error_log( sprintf( '[Laposta Elementor Forms] form submission transport error: %s', $response->get_error_message() ) );
					}
					return;
				}
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
