<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://fullstak.nl/
 * @since      1.0.0
 *
 * @package    Laposta_Elementor_Forms
 * @subpackage Laposta_Elementor_Forms/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Laposta_Elementor_Forms
 * @subpackage Laposta_Elementor_Forms/includes
 * @author     Bram Hammer <bram.h@fullstak.nl>
 */
class Laposta_Elementor_Forms_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'laposta-elementor-forms',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
