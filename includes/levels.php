<?php

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