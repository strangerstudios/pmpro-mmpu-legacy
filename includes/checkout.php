<?php

/**
 * Get the levels being purchased/removed at checkout and set them to globals.
 * Set up $_REQUEST so that core PMPro functions work as expected.
 *
 * @since TBD
 */
function pmpro_mmpul_checkout_preheader_before_get_level_at_checkout() {
	global $pmpro_mmpul_levels_being_purchased, $pmpro_mmpul_levels_being_removed;

	// Levels can be passed in via $_REQUEST['level'] or $_REQUEST['pmpro_level'].
	$pmpro_mmpul_levels_being_purchased = isset( $_REQUEST['pmpro_level'] ) ? $_REQUEST['pmpro_level'] : ( isset( $_REQUEST['level'] ) ? $_REQUEST['level'] : null );

	// If $levels_being_purchased is empty or does not have a space, we are not purchasing multiple levels
	// and don't need to do anything. Wipe the global and return.
	if ( empty( $pmpro_mmpul_levels_being_purchased ) || strpos( $pmpro_mmpul_levels_being_purchased, ' ' ) === false ) {
		$pmpro_mmpul_levels_being_purchased = null;
		return;
	}

	// Make levels into array and map intval() to ensure we're only dealing with integers.
	$pmpro_mmpul_levels_being_purchased = array_map( 'intval', explode( ' ', $pmpro_mmpul_levels_being_purchased ) );

	// Get the IDs of all levels that the user currently has for future reference.
	$user_levels = pmpro_getMembershipLevelsForUser();
	$user_level_ids = array_map( 'intval', wp_list_pluck( $user_levels, 'ID' ) );

	// If the user passed levels to remove via $_REQUEST['dellevels'], save them to $pmpro_mmpul_levels_being_removed.
	if ( ! empty( $_REQUEST['dellevels'] ) ) {
		$pmpro_mmpul_levels_being_removed = array_map( 'intval', explode( ' ', $_REQUEST['dellevels'] ) );

		// If any of the levels being removed are not in the user's current levels or are being purchased, unset them.
		foreach ( $pmpro_mmpul_levels_being_removed as $key => $remove_level ) {
			if ( ! in_array( $remove_level, $user_level_ids ) || in_array( $remove_level, $pmpro_mmpul_levels_being_purchased ) ) {
				unset( $pmpro_mmpul_levels_being_removed[ $key ] );
			}
		}
	} else {
		$pmpro_mmpul_levels_being_removed = array();
	}

	// User is trying to purchase multiple levels, but we only want to allow this if there are aren't multiple levels from the
	// same "one level per group" group. If there are, redirect to the levels page.
	$level_groups  = pmpro_get_level_groups_in_order();
	foreach ( $level_groups as $level_group ) {
		// Check if this level group is set to allow multiple selections.
		if ( intval( $level_group->allow_multiple_selections ) > 0 ) {
			continue;
		}

		// Get the levels in this group.
		$level_ids_in_group = array_map( 'intval', pmpro_get_level_ids_for_group( $level_group->id ) );

		// Check if multiple levels in this group are being purchased.
		$levels_in_group_being_purchased = array_intersect( $pmpro_mmpul_levels_being_purchased, $level_ids_in_group );

		// If multiple levels in this group are being purchased, redirect to the levels page.
		if ( count( $levels_in_group_being_purchased ) > 1 ) {
			wp_redirect( pmpro_url( 'levels' ) );
			exit;
		} elseif ( count( $levels_in_group_being_purchased ) == 1 ) {
			// If only one level in this group is being purchased, if the user has other levels in this group, add them to $pmpro_mmpul_levels_being_removed.
			$level_in_group_being_purchased = array_shift( $levels_in_group_being_purchased );
			$other_levels_in_group = array_diff( $level_ids_in_group, array( $level_in_group_being_purchased ) );
			$current_user_levels_in_group = array_intersect( $user_level_ids, $other_levels_in_group );
			if ( ! empty( $current_user_levels_in_group ) ) {
				$pmpro_mmpul_levels_being_removed = array_merge( $pmpro_mmpul_levels_being_removed, $current_user_levels_in_group );
			}
		}
	}

	// Remove duplicates from both arrays.
	$pmpro_mmpul_levels_being_purchased = array_unique( $pmpro_mmpul_levels_being_purchased );
	$pmpro_mmpul_levels_being_removed = array_unique( $pmpro_mmpul_levels_being_removed );

	// If we're here, we're moving forward with purchasing multiple levels. We've already saved the levels being purchased, so
	// let's set $_REQUEST['pmpro_level'] to the first paid level being purchased to not cause errors in core.
	foreach ( $pmpro_mmpul_levels_being_purchased as $level_id ) {
		$level = pmpro_getLevel( $level_id );
		if ( ! pmpro_isLevelFree( $level ) ) {
			$_REQUEST['pmpro_level'] = $level_id;
			break;
		}
	}
	if ( ! isset( $_REQUEST['pmpro_level'] ) ) {
		$_REQUEST['pmpro_level'] = $pmpro_mmpul_levels_being_purchased[0];
	}

	// Add JS to "fix" checkout page fields.
	add_action( 'pmpro_checkout_after_form', 'pmpro_mmpul_checkout_after_form' );

	// Add JS to process additional level purchases after initial checkout.
	// Run on late priority to try to maintain compatibility with other Add Ons. We will rerun this filter for each additional checkout.
	add_action( 'pmpro_after_checkout', 'pmpro_mmpul_after_checkout', 100, 2 );

	// Always send user to the account page after checkout so that they can see their current levels and try again if needed.
	add_filter( 'pmpro_confirmation_url', 'pmpro_mmpul_confirmation_url' );
}

/**
 * "Fix" fields on checkout form for MMPU checkout.
 *
 * @since TBD
 */
function pmpro_mmpul_checkout_after_form() {
	global $pmpro_mmpul_levels_being_purchased, $pmpro_mmpul_levels_being_removed;

	// Fix levels being purchased.
	if ( ! empty( $pmpro_mmpul_levels_being_purchased ) ) {
		?>
		<script>
			// Set the value of input #pmpro_level to $pmpro_mmpul_levels_being_purchased imploded with spaces.
			document.getElementById( 'pmpro_level' ).value = '<?php echo implode( ' ', $pmpro_mmpul_levels_being_purchased ); ?>';
		</script>
		<?php
	}

	// Add field for levels being removed.
	if ( ! empty( $pmpro_mmpul_levels_being_removed ) ) {
		?>
		<script>
			// Create a new hidden input after #pmpro_level with the value of $pmpro_mmpul_levels_being_removed imploded with spaces.
			var pmpro_mmpul_removed_levels = document.createElement( 'input' );
			pmpro_mmpul_removed_levels.type = 'hidden';
			pmpro_mmpul_removed_levels.name = 'dellevels';
			pmpro_mmpul_removed_levels.value = '<?php echo implode( ' ', $pmpro_mmpul_levels_being_removed ); ?>';
			document.getElementById( 'pmpro_level' ).parentNode.insertBefore( pmpro_mmpul_removed_levels, document.getElementById( 'pmpro_level' ).nextSibling );
		</script>
		<?php
	}

	// Hide .pmpro_level_name_text and .pmpro_level_description_text.
	?>
	<style>
		.pmpro_level_name_text, .pmpro_level_description_text {
			display: none;
		}
	</style>
	<?php

	// Get the details of the levels being purchased/removed.
	$level_add_details = array();
	foreach ( $pmpro_mmpul_levels_being_purchased as $level_id ) {
		$level = pmpro_getLevel( $level_id );
		$level_add_details[] = $level->name . ' (' . pmpro_getLevelCost( $level, true, true ) . pmpro_getLevelExpiration( $level ) . ')';
	}
	$level_remove_details = array();
	foreach ( $pmpro_mmpul_levels_being_removed as $level_id ) {
		$level = pmpro_getLevel( $level_id );
		$level_remove_details[] = $level->name;
	}
	?>
	<script>
		const levels_to_add = <?php echo json_encode( $level_add_details ); ?>;
		const levels_to_remove = <?php echo json_encode( $level_remove_details ); ?>;
		console.log( levels_to_add );
		console.log( levels_to_remove );
		var level_cost_html = '<h4><?php _e( 'Adding Levels', 'pmpro-multiple-memberships-per-user' ); ?></h4><ul>';
		levels_to_add.forEach( function( level ) {
			level_cost_html += '<li>' + level + '</li>';
		} );
		level_cost_html += '</ul>';
		if ( levels_to_remove.length > 0 ) {
			level_cost_html += '<h4><?php _e( 'Removing Levels', 'pmpro-multiple-memberships-per-user' ); ?></h4><ul>';
			levels_to_remove.forEach( function( level ) {
				level_cost_html += '<li>' + level + '</li>';
			} );
			level_cost_html += '</ul>';
		}
		document.getElementById( 'pmpro_level_cost' ).innerHTML = level_cost_html;

	</script>
	<?php
}

/**
 * Process additional level purchases after initial checkout.
 *
 * @since TBD
 *
 * @param int $user_id ID of the user checking out.
 * @param MemberOrder $order Order object for the checkout.
 */
function pmpro_mmpul_after_checkout( $user_id, $order ) {
	global $pmpro_mmpul_levels_being_purchased, $pmpro_mmpul_levels_being_removed, $pmpro_level;

	// We've already processed the level set in $order, so remove it from $pmpro_mmpul_levels_being_purchased.
	$initial_checkout_level_index = array_search( $order->membership_id, $pmpro_mmpul_levels_being_purchased );
	if ( $initial_checkout_level_index !== false ) {
		unset( $pmpro_mmpul_levels_being_purchased[ $initial_checkout_level_index ] );
	}

	// Unhook this function so that we don't run this again during our additional checkouts.
	remove_action( 'pmpro_after_checkout', 'pmpro_mmpul_after_checkout', 100, 2 );

	// Now, for each level still in $pmpro_mmpul_levels_being_purchased, process an additional checkout.
	foreach ( $pmpro_mmpul_levels_being_purchased as $level_id ) {
		// Get the level object.
		$pmpro_level = pmpro_getLevel( $level_id );

		// Set $_REQUEST['pmpro_level'] to the level ID so that pmpro_getLevelAtCheckout() runs as expected.
		$_REQUEST['pmpro_level'] = $level_id;

		// Create a new order for this level.
		$new_order = pmpro_build_order_for_checkout();

		// Process the checkout.
		if ( empty( $new_order->process() ) ) {
			// Payment failed. Uh oh. Bail so that user can be sent back to the account page to try again.
			return;
		}

		// If we're here, the checkout was successful. Give the user their level.
		pmpro_complete_async_checkout( $new_order );
	}

	// All of our additional checkouts are complete. Now, let's get all of the levels that the user now has.
	// If any are in $pmpro_mmpul_levels_being_removed, remove them.
	$user_levels = pmpro_getMembershipLevelsForUser();
	$user_level_ids = array_map( 'intval', wp_list_pluck( $user_levels, 'ID' ) );
	$levels_to_remove = array_intersect( $user_level_ids, $pmpro_mmpul_levels_being_removed );
	foreach ( $levels_to_remove as $level_to_remove ) {
		pmpro_cancelMembershipLevel( $level_to_remove, $user_id, 'changed' );
	}
}

/**
 * Always send user to the account page after checkout so that they can see their current levels and try again if needed.
 *
 * @since TBD
 *
 * @param string $url URL to redirect to.
 */
function pmpro_mmpul_confirmation_url( $url ) {
	return pmpro_url( 'account' );
}