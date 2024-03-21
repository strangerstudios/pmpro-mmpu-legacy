<?php
/**
* Plugin Name: Paid Memberships Pro - Multiple Memberships per User (Legacy Plugin)
* Plugin URI: http://www.paidmembershipspro.com/add-ons/pmpro-mmpu-legacy/
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

define( 'PMPRO_MMPU_LEGACY_FILE', __FILE__ );
define( 'PMPRO_MMPU_LEGACY_DIR', dirname(__FILE__) ); // signals our presence to the mother ship, and other add-ons
define( 'PMPRO_MMPU_LEGACY_VER', '0.1' ); // Version string to signal cache refresh during JS/CSS updates

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

	// If we're in the admin, we don't need to load any of this. Bail.
	if ( is_admin() ) {		
		return;
    }

	// If PMPro is not active or if not using PMPro 3.0+ (including beta), bail.
	if ( ! defined( 'PMPRO_VERSION' ) || ! class_exists( 'PMPro_Subscription' ) ) {
		return;
	}

	// If the chosen gateway does not support multiple level checkout, bail.
	if ( ! pmpro_mmpul_gateway_supports_multiple_level_checkout() ) {
		return;
	}

	// Check if we are on the levels page.
	if ( is_page( $pmpro_pages['levels'] ) || ( !empty( $post->post_content ) && false !== stripos( $post->post_content, '[pmpro_advanced_levels' ) ) ) {
		// Check if the levels multiselect page is disabled.
		if ( ! apply_filters( 'pmprommpu_disable_levels_multiselect_page', false ) ) {
			// Set up the multiselect levels page.
			include_once( PMPRO_MMPU_LEGACY_DIR . '/includes/levels.php' );
			add_action( 'wp_enqueue_scripts', 'pmpro_mmpul_enqueue_scripts' );
			add_filter( 'pmpro_pages_custom_template_path', 'pmpro_mmpul_custom_template_path', 10, 2 );
		}
	}

	// Hook our checkout code.
	include_once( PMPRO_MMPU_LEGACY_DIR . '/includes/checkout.php' );
	add_action( 'pmpro_checkout_preheader_before_get_level_at_checkout', 'pmpro_mmpul_checkout_preheader_before_get_level_at_checkout' );
}
add_action( 'wp', 'pmpro_mmpul_hook_functions', 0 );

/**
 * Check if the current gateway supports multiple level checkout.
 */
function pmpro_mmpul_gateway_supports_multiple_level_checkout( $gateway = null ) {
    if ( empty( $gateway ) ) {
		$gateway = pmpro_getOption( 'gateway' );
	}
	
    // Core gateways.
	$has_support = ! in_array( $gateway, array( 'paypalexpress', 'paypalstandard' ) );

	// If Stripe Checkout is being used, there is not support.
	$has_support = ( $has_support && ! ( $gateway === 'stripe' && PMProGateway_stripe::using_stripe_checkout() ) );

	// If the Add PayPal Express Add On is being used, there is not support.
	$has_support = ( $has_support && ! function_exists( 'pmproappe_plugin_row_meta' ) );

	// If Pay By Check Add On is being used, there is not support.
	$has_support = ( $has_support && ! function_exists( 'pmpropbc_plugin_row_meta' ) );
	
    return apply_filters( 'pmprommpu_gateway_supports_multiple_level_checkout', $has_support, $gateway );
}
