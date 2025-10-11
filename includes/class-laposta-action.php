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
			$fields_requiring_option_sync = [];

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
					$raw_field = isset( $raw_fields[ $field ] ) ? $raw_fields[ $field ] : null;
					$elementor_field_type = isset( $raw_field['field_type'] ) ? $raw_field['field_type'] : ( isset( $raw_field['type'] ) ? $raw_field['type'] : '' );
					$is_hidden_elementor_field = ( 'hidden' === $elementor_field_type );

                    $datatype_setting_key = '_laposta_field_datatype_' . $custom_field_name;
					$field_id_setting_key = '_laposta_field_id_' . $custom_field_name;
					$allow_new_options_setting_key = '_laposta_field_allow_new_options_' . $custom_field_name;

					$laposta_field_datatype = $settings[$datatype_setting_key] ?? '';
					$laposta_field_id = $settings[$field_id_setting_key] ?? '';
					$allow_new_options = isset( $settings[ $allow_new_options_setting_key ] ) && 'yes' === $settings[ $allow_new_options_setting_key ];

					$is_multi_select_datatype = in_array( $laposta_field_datatype, [ 'select_multiple', 'checkbox', 'multiselect' ], true );
					if ( $is_multi_select_datatype && $is_hidden_elementor_field ) {
						$field_value = $this->normalize_hidden_multi_value( $field_value );
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


                    if($allow_new_options && empty($laposta_field_id)) {
                        // get fields for the list to find the field ID by name
                        $fields_response = laposta_api_call( $settings['laposta_api_key'], 'v2/field?list_id=' . rawurlencode( $settings['listid'] ) );
                        if ( ! is_wp_error( $fields_response ) && ! empty( $fields_response['data'] ) && is_array( $fields_response['data'] ) ) {
                            foreach ($fields_response['data'] as $field_info) {
                                if(strtolower($field_info['field']['name']) == strtolower($custom_field_name)) {
                                    $laposta_field_id = $field_info['field']['field_id'];
                                    break;
                                }
                            }
                        }
                    }
                    if ( $allow_new_options && $is_multi_select_datatype && ! empty( $laposta_field_id ) ) {
						$field_values_for_sync = $data['custom_fields'][ $custom_field_name ];
						if ( ! is_array( $field_values_for_sync ) ) {
							$field_values_for_sync = $this->normalize_hidden_multi_value( $field_values_for_sync );
						}
						$field_values_for_sync = array_filter( array_map( 'strval', (array) $field_values_for_sync ), static function ( $value ) {
							return '' !== trim( $value );
						} );

						if ( ! empty( $field_values_for_sync ) ) {
							$fields_requiring_option_sync[] = [
								'field_id' => $laposta_field_id,
								'list_id' => $settings['listid'],
								'values'  => array_values( array_unique( array_map( 'trim', $field_values_for_sync ) ) ),
							];
						}
					}
				}
			}

            if ( ! empty( $fields_requiring_option_sync ) ) {
				$fields_requiring_option_sync = $this->merge_option_sync_requests( $fields_requiring_option_sync );
				$this->sync_laposta_field_options( $settings['laposta_api_key'], $fields_requiring_option_sync );
			}

			if ( $should_upsert && ! empty( $append_field_values ) && ! empty( $data['email'] ) ) {
				$member_endpoint = sprintf( 'v2/member/%s?list_id=%s', rawurlencode( $data['email'] ), rawurlencode( $settings['listid'] ) );
				$existing_member = laposta_api_call( $settings['laposta_api_key'], $member_endpoint );
                $is_error = isset($existing_member['error']['code']);
				if ( ! $is_error && ! empty( $existing_member['member']['custom_fields'] ) ) {
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
                    if($is_error && $existing_member['error']['code'] == 202) {
                        $data['custom_fields'] = $append_field_values;
                    }
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
		 * Merge option sync requests per field.
		 *
		 * @param array $requests
		 * @return array
		 */
		private function merge_option_sync_requests( array $requests ) {
			$merged = [];
			foreach ( $requests as $request ) {
				if ( empty( $request['field_id'] ) ) {
					continue;
				}
				$field_id = (string) $request['field_id'];
				if ( ! isset( $merged[ $field_id ] ) ) {
					$merged[ $field_id ] = [
						'field_id' => $field_id,
						'list_id' => isset( $request['list_id'] ) ? $request['list_id'] : '',
						'values'  => [],
					];
				}
				if ( ! empty( $request['values'] ) ) {
					$merged[ $field_id ]['values'] = array_values( array_unique( array_merge( $merged[ $field_id ]['values'], (array) $request['values'] ) ) );
				}
			}

			return array_values( $merged );
		}

		/**
		 * Ensure Laposta options exist for submitted values.
		 *
		 * @param string $api_key
		 * @param array  $fields
		 * @return void
		 */
		private function sync_laposta_field_options( $api_key, array $fields ) {
			foreach ( $fields as $field ) {
				if ( empty( $field['field_id'] ) || empty( $field['list_id'] ) || empty( $field['values'] ) ) {
					continue;
				}

				$field_id = (string) $field['field_id'];
				$list_id  = (string) $field['list_id'];
				$values   = array_values( array_filter( (array) $field['values'], static function ( $value ) {
					return '' !== trim( (string) $value );
				} ) );

				if ( empty( $values ) ) {
					continue;
				}

				$endpoint = sprintf( 'v2/field/%s?list_id=%s', rawurlencode( $field_id ), rawurlencode( $list_id ) );
				$response = laposta_api_call( $api_key, $endpoint );
				if ( is_wp_error( $response ) || empty( $response['field'] ) ) {
					if ( is_wp_error( $response ) && defined( 'LAPOSTA_DEBUG' ) && LAPOSTA_DEBUG ) {
						error_log( sprintf( '[Laposta Elementor Forms] option sync fetch error (%s): %s', $field_id, $response->get_error_message() ) );
					}
					continue;
				}

				$existing_options = [];
				if ( isset( $response['field']['options_full'] ) && is_array( $response['field']['options_full'] ) ) {
					foreach ( $response['field']['options_full'] as $option ) {
						if ( ! isset( $option['value'] ) || '' === trim( (string) $option['value'] ) ) {
							continue;
						}
						$existing_options[] = [
							'id'    => isset( $option['id'] ) ? $option['id'] : null,
							'value' => (string) $option['value'],
						];
					}
				}
				if ( empty( $existing_options ) && isset( $response['field']['options'] ) && is_array( $response['field']['options'] ) ) {
					foreach ( $response['field']['options'] as $option ) {
						if ( is_array( $option ) && isset( $option['value'] ) ) {
							$value = (string) $option['value'];
						} else {
							$value = (string) $option;
						}
						if ( '' === trim( $value ) ) {
							continue;
						}
						$existing_options[] = [
							'id'    => null,
							'value' => $value,
						];
					}
				}

				$existing_values = array_map( static function ( $option ) {
					return isset( $option['value'] ) ? (string) $option['value'] : '';
				}, $existing_options );

				$missing_values = array_values( array_diff( $values, $existing_values ) );
				if ( empty( $missing_values ) ) {
					continue;
				}

				$options_full_payload = [];
				foreach ( $existing_options as $option ) {
					if ( '' === $option['value'] ) {
						continue;
					}
					$payload_option = [
						'value' => $option['value'],
					];
					if ( ! empty( $option['id'] ) ) {
						$payload_option['id'] = $option['id'];
					}
					$options_full_payload[] = $payload_option;
				}

				foreach ( $missing_values as $new_value ) {
					$options_full_payload[] = [
						'value' => (string) $new_value,
					];
				}

				if ( empty( $options_full_payload ) ) {
					continue;
				}

				$modify_endpoint = sprintf( 'v2/field/%s', rawurlencode( $field_id ) );
				$payload = [
					'list_id' => $list_id,
					'options' => array_map( static function ( $option ) {
                        return isset( $option['value'] ) ? (string) $option['value'] : '';
                    }, $options_full_payload ),
				];

				$modify_response = laposta_api_call( $api_key, $modify_endpoint, 'POST', $payload, [
					'encoding' => 'form',
				] );

                if ( is_wp_error( $modify_response ) ) {
					if ( defined( 'LAPOSTA_DEBUG' ) && LAPOSTA_DEBUG ) {
						error_log( sprintf( '[Laposta Elementor Forms] option sync modify error (%s): %s', $field_id, $modify_response->get_error_message() ) );
					}
					continue;
				}

				if ( isset( $modify_response['error'] ) && defined( 'LAPOSTA_DEBUG' ) && LAPOSTA_DEBUG ) {
					error_log( sprintf( '[Laposta Elementor Forms] option sync modify response error (%s): %s', $field_id, wp_json_encode( $modify_response['error'] ) ) );
				}
			}
		}

		/**
		 * Convert hidden field values into an array for multi-select usage.
		 *
		 * @param mixed $value
		 * @return array
		 */
		private function normalize_hidden_multi_value( $value ) {
			if ( is_array( $value ) ) {
				$flattened = [];
				foreach ( $value as $item ) {
					if ( is_array( $item ) ) {
						$flattened = array_merge( $flattened, $this->normalize_hidden_multi_value( $item ) );
						continue;
					}
					if ( is_scalar( $item ) ) {
						$flattened[] = trim( (string) $item );
					}
				}
				return array_values( array_filter( array_unique( $flattened ), static function ( $entry ) {
					return '' !== $entry;
				} ) );
			}

			if ( is_scalar( $value ) ) {
				$parts = array_map( 'trim', explode( ',', (string) $value ) );
				return array_values( array_filter( array_unique( $parts ), static function ( $entry ) {
					return '' !== $entry;
				} ) );
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
						$mapped = $mapping[$scalar];
						if (is_string($mapped) || is_numeric($mapped)) {
							return trim((string) $mapped);
						}

						return $mapped;
					}

					return trim($scalar);
				}

				return $item;
			};

			if (is_array($value)) {
				return array_map($apply_mapping, $value);
			}

			return $apply_mapping($value);
		}
	}
