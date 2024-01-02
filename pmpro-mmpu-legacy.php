<?php
/**
* Plugin Name: Paid Memberships Pro - Multiple Memberships per User (Legacy Plugin)
* Plugin URI: http://www.paidmembershipspro.com/pmpro-multiple-memberships-per-user/
* Description: Preserve some aspects of the old MMPU add on for PMPro 3.0+.
* Version: 0.1
* Author: Paid Memberships Pro
* Author URI: https://www.paidmembershipspro.com
* Text Domain: pmpro-mmpu-legacy
* Domain Path: /languages
*/

/**
 * Copyright 2011-2024	Stranger Studios
 * (email : info@paidmembershipspro.com)
 * GPLv2 Full license details in license.txt
 */

/*
	The Story
	* The old MMPU add on was merged into PMPro 3.0.
    * While PMPro 3.0 allows users to have multiple memberships, they can only check out for 1 level at a time.
    
    Included in this plugin:
    * We make sure the site is using a supported gateway (Stripe or Braintree onsite)
    * We bring back the multiselect levels page template
    * We show the cumulative price at checkout and confirmation.
    * We loop through all levels and process each subscription cumulatively.
*/

define("PMPRO_MMPU_LEGACY_DIR", dirname(__FILE__)); // signals our presence to the mother ship, and other add-ons
define("PMPRO_MMPU_LEGACY_VER", "0.1"); // Version string to signal cache refresh during JS/CSS updates

/**
 * Load the text domain.
 *
 * @since 0.1
 */
function pmpro_mmpul_load_plugin_text_domain() {
	load_plugin_textdomain( 'pmpro-mmpu-legacy', false, basename( PMPRO_MMPU_LEGACY_DIR ) . '/languages' );
}
add_action( 'init', 'pmpro_mmpul_load_plugin_text_domain' );

/**
 * Load the plugin's code if requirements are met.
 * 
 * @since 0.1
 */
function pmpro_mmpul_hook_functions() {
	global $pmpro_pages, $post;

	// If we're in the admin, PMPro is not active, or the current gateway doesn't support multiple level checkout, bail.
	if ( is_admin() || ! defined( 'PMPRO_VERSION' ) || ! pmpro_mmpul_gateway_supports_multiple_level_checkout() ) {		
		return;
    }

	// Check if we are on the levels page.
	if ( is_page( $pmpro_pages['levels'] ) || ( !empty( $post->post_content ) && false !== stripos( $post->post_content, '[pmpro_advanced_levels' ) ) ) {
		// Check if the levels multiselect page is disabled.
		if ( ! apply_filters( 'pmprommpu_disable_levels_multiselect_page', false ) ) {
			// Set up the multiselect levels page.
			add_action( 'wp_enqueue_scripts', 'pmpro_mmpul_enqueue_scripts' );
			add_filter( 'pmpro_pages_custom_template_path', 'pmpro_mmpul_custom_template_path', 10, 2 );
		}
	}

}
add_action( 'wp', 'pmpro_mmpul_hook_functions' );

/**
 * Load scripts and styles for the multiselect levels page.
 */
function pmpro_mmpul_enqueue_scripts() {
	// Load styles.
	$csspath = plugins_url("css/frontend.css", __FILE__);
	wp_enqueue_style( 'pmpro_mmpul_frontend', $csspath, array(), PMPRO_MMPU_LEGACY_VER, "screen");

	// Load script.
	$incoming_levels  = pmpro_getMembershipLevelsForUser();
	$available_levels = pmpro_getAllLevels( false, true );

	$selected_levels = array();
	$level_elements  = array();
	$current_levels  = array();
	$all_levels      = array();

	if ( false !== $incoming_levels ) { // At this point, we're not disabling others in the group for initial selections, because if they're here, they probably want to change them.

		foreach ( $incoming_levels as $curlev ) {

			$selected_levels[]             = "level-{$curlev->id}";
			$level_elements[]              = "input#level{$curlev->id}";
			$current_levels[ $curlev->id ] = $curlev->name;
		}
	}

	if ( false !== $available_levels ) {

		foreach ( $available_levels as $lvl ) {
			$all_levels[ $lvl->id ] = $lvl->name;
		}
	}

	wp_register_script( 'pmpro-mmpul-levels', plugins_url( '/js/levels.js', __FILE__ ), array( 'jquery' ), PMPRO_MMPU_LEGACY_VER, true );
	wp_localize_script( 'pmpro-mmpul-levels', 'pmprolvl',
		array(
			'settings'       => array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'timeout' => apply_filters( "pmpro_ajax_timeout", 5000, "applydiscountcode" ),
				'cancel_lnk' => esc_url_raw( pmpro_url( 'cancel', '') ),
				'checkout_lnk' => esc_url_raw( pmpro_url( 'checkout', '' ) ),
			),
			'lang'           => array(
				'selected_label'     => __( 'Selected', 'pmpro-mmpu-legacy' ),
				'current_levels'     => _x( 'Current Levels', 'title for currently selected levels', 'pmpro-mmpu-legacy' ),
				'added_levels'       => _x( 'Added Levels', 'title for added levels', 'pmpro-mmpu-legacy' ),
				'removed_levels'     => _x( 'Removed Levels', 'title for removed levels', 'pmpro-mmpu-legacy' ),
				'none'               => _x( 'None', 'value displayed when no levels selected', 'pmpro-mmpu-legacy' ),
				'no_levels_selected' => __( 'No levels selected.', 'pmpro-mmpu-legacy' ),
			),
			'alllevels'   => $all_levels,
			'selectedlevels' => $selected_levels,
			'levelelements'  => $level_elements,
			'currentlevels'  => $current_levels,
		)
	);
	wp_enqueue_script( 'pmpro-mmpul-levels');
}

/**
 * Tell PMPro to look for templates in this plugin's templates/ folder.
 */
function pmpro_mmpul_custom_template_path( $templates, $page_name ) {		
	$templates[] = plugin_dir_path(__FILE__) . 'templates/' . $page_name . '.php';	
	
	return $templates;
}

/**
 * Check if the current gateway supports multiple level checkout.
 */
function pmpro_mmpul_gateway_supports_multiple_level_checkout( $gateway = null ) {
    if ( empty( $gateway ) ) {
		$gateway = pmpro_getOption( 'gateway' );
	}
	
    // Core gateways.
	$has_support = ! in_array( $gateway, array( 'paypalexpress', 'paypalstandard' ) );
	
    return apply_filters( 'pmprommpu_gateway_supports_multiple_level_checkout', $has_support, $gateway );
}
