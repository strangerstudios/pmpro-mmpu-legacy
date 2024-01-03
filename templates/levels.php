<?php
global $wpdb, $pmpro_msg, $pmpro_msgt, $current_user;

$pmpro_levels = pmpro_getAllLevels( false, true );

$incoming_levels = pmpro_getMembershipLevelsForUser();

$level_groups  = pmpro_get_level_groups_in_order();

$pmpro_levels = apply_filters( "pmpro_levels_array", $pmpro_levels );

if ( $pmpro_msg ) {
	?>
	<div class="pmpro_message <?php echo $pmpro_msgt ?>"><?php echo $pmpro_msg ?></div>
	<?php
}
?>
<div id="pmpro_mmpu_levels">
	<div id="pmpro_mmpu_groups">
		<?php
		foreach ( $level_groups as $level_group ) {
			$levels_in_group = pmpro_get_level_ids_for_group( $level_group->id );
			if ( empty( $levels_in_group ) ) {
				continue;
			}

			?>
			<div id="pmpro_mmpu_group-<?php echo $level_group->id; ?>"
			     class="pmpro_mmpu_group <?php if ( intval( $level_group->allow_multiple_selections ) == 0 ) { ?>selectone<?php } ?>">
				<h2 class="pmpro_mmpu_group-name"><?php echo $level_group->name ?></h2>
				<p class="pmpro_mmpu_group-type">
					<?php
					if ( intval( $level_group->allow_multiple_selections ) > 0 ) {
						esc_html_e( 'You can choose multiple levels from this group.', 'pmpro-mmpu-legacy' );
					} else {
						esc_html_e( 'You can only choose one level from this group.', 'pmpro-mmpu-legacy' );
					}
					?>
				</p>
				<?php

				foreach ( $levels_in_group as $level ) {

					?>
					<div id="pmpro_mmpu_level-<?php echo $pmpro_levels[ $level ]->id; ?>"
					     class="pmpro_mmpu_level group<?php echo $level_group->id; ?> <?php if ( isset($level_group->allow_multiple_selections) && intval( $level_group->allow_multiple_selections ) == 0 ) {
						     echo 'selectone';
					     } ?>">
						<div class="pmpro_level-info">
							<h3 class="pmpro_level-name"><?php echo $pmpro_levels[ $level ]->name; ?></h3>
							<p class="pmpro_level-price">
								<?php
								if ( pmpro_isLevelFree( $pmpro_levels[ $level ] ) ) {
									esc_html_e( 'Free', 'pmpro-mmpu-legacy' );
								} else {
									echo pmpro_getLevelCost( $pmpro_levels[ $level ], true, true );
								}
								?>
							</p> <!-- end pmpro_level-price -->
							<?php
							$expiration_text = pmpro_getLevelExpiration( $pmpro_levels[ $level ] );
							if ( ! empty( $expiration_text ) ) {
								?>
								<p class="pmpro_level-expiration">
									<?php echo $expiration_text; ?>
								</p> <!-- end pmpro_level-expiration -->
								<?php
							}
							?>
						</div> <!-- end pmpro_level-info -->
						<div class="pmpro_level-action">
							<?php
							if ( $level_group->allow_multiple_selections > 0 ) {
								?>
								<!-- change message class wrap to success for selected or error if removing -->
								<label
									class="pmpro_level-select <?php if ( pmpro_hasMembershipLevel( $pmpro_levels[ $level ]->id ) ) {
										echo "pmpro_level-select-current";
									} ?>" for="level-<?php echo $pmpro_levels[ $level ]->id ?>"><input type="checkbox"
								                                                                       id="level-<?php echo $pmpro_levels[ $level ]->id ?>"
								                                                                       data-groupid="<?php echo $level_group->id ?>" <?php checked( pmpro_hasMembershipLevel( $pmpro_levels[ $level ]->id ), true ); ?>>&nbsp;&nbsp;<?php esc_html_e( 'Add', 'pmpro-mmpu-legacy' ); ?>
								</label>
								<?php
							} else {
								?>
								<!-- change message class wrap to success for selected or error if removing -->
								<label
									class="pmpro_level-select <?php if ( pmpro_hasMembershipLevel( $pmpro_levels[ $level ]->id ) ) {
										echo "pmpro_level-select-current";
									} ?>" for="level-<?php echo $pmpro_levels[ $level ]->id ?>"><input type="checkbox"
								                                                                       id="level-<?php echo $pmpro_levels[ $level ]->id ?>"
								                                                                       data-groupid="<?php echo $level_group->id; ?>" <?php checked( pmpro_hasMembershipLevel( $pmpro_levels[ $level ]->id ), true ); ?>>&nbsp;&nbsp;<?php esc_html_e( 'Select', 'pmpro-mmpu-legacy' ); ?>
								</label>
								<?php
							}
							?>
						</div> <!-- end pmpro_level-action -->
					</div> <!-- end pmpro_mmpu_level-ID -->
					<?php
				}
				?>
			</div> <!-- end pmpro_mmpu_group-ID -->
			<?php
		}
		?>
		<div class="pmpro_mmpu_checkout">
			<div class="pmpro_mmpu_level">
				<div class="pmpro_level-info"></div> <!-- end pmpro_level-info -->
				<div class="pmpro_level-action">
					<input class="pmpro_mmpu_checkout-button" type="button" value="<?php esc_attr_e( 'Checkout', 'pmpro-mmpu-legacy' ) ?>" disabled="disabled">
				</div> <!-- end pmpro_level-action -->
			</div> <!-- end pmpro_mmpu_level -->
		</div> <!-- end pmpro_mmpu_checkout -->

	</div> <!-- end pmpro_mmpu_groups -->
	<div id="pmpro_mmpu_level_selections">
		<aside class="widget">
			<h3 class="widget-title"><?php esc_html_e( 'Membership Selections', 'pmpro-mmpu-legacy' ); ?></h3>
			<div id="pmpro_mmpu_level_summary"><?php esc_html_e( 'Select levels to complete checkout.', 'pmpro-mmpu-legacy' ); ?></div>
			<p><input class="pmpro_mmpu_checkout-button" type="button" value="<?php esc_attr_e( 'Checkout', 'pmpro-mmpu-legacy' ) ?>" disabled="disabled"></p>
		</aside>
	</div> <!-- end pmpro_mmpu_level_selections -->
</div> <!-- end #pmpro_mmpu_levels -->
<nav id="nav-below" class="navigation" role="navigation">
	<div class="nav-previous alignleft">
		<?php if ( ! empty( $current_user->membership_level->id ) ) { ?>
			<a href="<?php echo pmpro_url( "account" ) ?>">&larr; <?php esc_html_e( 'Return to Your Account', 'pmpro-mmpu-legacy' ); ?></a>
		<?php } else { ?>
			<a href="<?php echo home_url() ?>">&larr; <?php esc_html_e( 'Return to Home', 'pmpro-mmpu-legacy' ); ?></a>
		<?php } ?>
	</div>
</nav>
<style>
	input.selected {
		background-color: rgb(0, 122, 204);
		color: #000000;
	}
</style>
