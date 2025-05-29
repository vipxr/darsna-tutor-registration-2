<?php
/**
 * @var $coupon OsCouponModel
 **/
?>

<div class="os-form-w quick-coupon-form-w <?php echo ( $coupon->is_new_record() ) ? 'is-new-coupon' : 'is-existing-coupon'; ?>"
     data-refresh-route-name="<?php echo OsRouterHelper::build_route_name( 'coupons', 'quick_edit' ); ?>">
    <form action=""
          data-route-name="<?php echo ( $coupon->is_new_record() ) ? OsRouterHelper::build_route_name( 'coupons', 'create' ) : OsRouterHelper::build_route_name( 'coupons', 'update' ); ?>"
          class="coupon-quick-edit-form">
        <div class="os-form-header">
			<?php if ( $coupon->is_new_record() ) { ?>
                <h2><?php _e( 'New Coupon', 'latepoint-pro-features' ); ?></h2>
			<?php } else { ?>
                <h2><?php _e( 'Edit Coupon', 'latepoint-pro-features' ); ?></h2>
			<?php } ?>
            <a href="#" class="latepoint-side-panel-close latepoint-side-panel-close-trigger"><i class="latepoint-icon latepoint-icon-x"></i></a>
        </div>
        <div class="os-form-content">
			<?php if ( ! $coupon->is_new_record() ) { ?>
                <div class="quick-booking-info">
					<?php echo '<span>' . __( 'Coupon ID:', 'latepoint-pro-features' ) . '</span><strong>' . $coupon->id . '</strong>'; ?>
					<?php if ( OsAuthHelper::get_current_user()->has_capability( 'activity__view' ) ) {
						echo '<a href="#" data-coupon-id="' . $coupon->id . '" data-route="' . OsRouterHelper::build_route_name( 'coupons', 'view_coupon_log' ) . '" class="quick-coupon-form-view-log-btn"><i class="latepoint-icon latepoint-icon-clock"></i>' . __( 'History', 'latepoint-pro-features' ) . '</a>';
					} ?>
                </div>
			<?php } ?>

            <?php echo OsFormHelper::text_field( 'coupon[name]', __( 'Name (For Internal Use)', 'latepoint-pro-features' ), $coupon->name, [ 'theme'    => 'simple' ] ); ?>
            <?php echo OsFormHelper::text_field( 'coupon[code]', __( 'Code', 'latepoint-pro-features' ), $coupon->code, [ 'theme'    => 'simple' ] ); ?>
            <div class="os-row">
                <div class="os-col-6">
                    <?php echo OsFormHelper::number_field('coupon[discount_value]', __('Discount Value', 'latepoint-pro-features'), number_format(($coupon->discount_value ?? 0), 2), null, null, [ 'theme'    => 'simple', 'step' => '.01' ]); ?>
                </div>
                <div class="os-col-6">
                    <?php echo OsFormHelper::select_field('coupon[discount_type]', __('Discount Type', 'latepoint-pro-features'), ['percent' => __('Percent', 'latepoint-pro-features'), 'fixed' => __('Fixed Value', 'latepoint-pro-features')], $coupon->discount_type); ?>
                </div>
            </div>
            <div class="os-row">
                <div class="os-col-12">
                    <?php echo OsFormHelper::select_field('coupon[status]', __('Status', 'latepoint-pro-features'), OsCouponsHelper::get_statuses_list(), $coupon->status); ?>
                </div>
            </div>
            <div class="os-form-sub-header">
                <h3><?php _e( 'Time Restrictions', 'latepoint-pro-features' ); ?></h3>
            </div>
            <div class="latepoint-message latepoint-message-subtle">
                <?php esc_html_e('You can set a specific time frame for when this coupon is active, which is helpful for seasonal promotions. Leaving either field blank will remove any time restrictions.', 'latepoint-pro-features'); ?>
            </div>

            <div class="os-row">
                <div class="os-col-6">
                <?php echo OsFormHelper::text_field('coupon[active_from]', __('Active From', 'latepoint-pro-features'), OsWpDateTime::date_from_db_format($coupon->active_from), ['theme' => 'simple', 'class' => 'os-mask-date']); ?>
                </div>
                <div class="os-col-6">
                <?php echo OsFormHelper::text_field('coupon[active_to]', __('Active To', 'latepoint-pro-features'), OsWpDateTime::date_from_db_format($coupon->active_to), ['theme' => 'simple', 'class' => 'os-mask-date']); ?>
                </div>
            </div>
            <div class="os-form-sub-header">
                <h3><?php _e( 'Use Restrictions', 'latepoint-pro-features' ); ?></h3>
            </div>
            <div class="latepoint-message latepoint-message-subtle">
                <?php esc_html_e('Here you can specify which service, agent or customer this coupon is applicable to. Leaving a field blank will make it applicable to all.', 'latepoint-pro-features'); ?>
            </div>
            <div class="os-row">
                <div class="os-col-12">
                    <?php echo OsFormHelper::multi_select_field('coupon[rules][service_ids]', __('Services', 'latepoint-pro-features'),OsFormHelper::model_options_for_multi_select('service'), explode(',', $coupon->get_rule('service_ids', ''))); ?>
                    <?php echo OsFormHelper::multi_select_field('coupon[rules][agent_ids]', __('Agents', 'latepoint-pro-features'),OsFormHelper::model_options_for_multi_select('agent'), explode(',', $coupon->get_rule('agent_ids', ''))); ?>
                    <?php echo OsFormHelper::multi_select_field('coupon[rules][customer_ids]', __('Customers', 'latepoint-pro-features'),OsFormHelper::model_options_for_multi_select('customer'), explode(',', $coupon->get_rule('customer_ids', ''))); ?>
                    <?php echo OsFormHelper::multi_select_field('coupon[rules][bundle_ids]', __('Bundles', 'latepoint-pro-features'),OsFormHelper::model_options_for_multi_select('bundle'), explode(',', $coupon->get_rule('bundle_ids', ''))); ?>
                </div>
            </div>
            <div class="os-form-sub-header">
                <h3><?php _e( 'Usage Limits', 'latepoint-pro-features' ); ?></h3>
            </div>
            <div class="latepoint-message latepoint-message-subtle">
                <?php esc_html_e('You can set a limit on how many times this coupon can be used, either per customer or in total. Leave blank to have no limits.', 'latepoint-pro-features'); ?>
            </div>

            <div class="os-row">
                <div class="os-col-4">
                    <?php echo OsFormHelper::number_field('coupon[rules][limit_per_customer]', __('Per Customer', 'latepoint-pro-features'), $coupon->get_rule('limit_per_customer'), null, null, ['theme' => 'simple']); ?>
                </div>
                <div class="os-col-4">
                    <?php echo OsFormHelper::number_field('coupon[rules][limit_per_order]', __('Per Order', 'latepoint-pro-features'), $coupon->get_rule('limit_per_order'), null, null, ['theme' => 'simple']); ?>
                </div>
                <div class="os-col-4">
                    <?php echo OsFormHelper::number_field('coupon[rules][limit_total]', __('Total', 'latepoint-pro-features'), $coupon->get_rule('limit_total'), null, null, ['theme' => 'simple']); ?>
                </div>
            </div>

            <div class="os-form-sub-header">
                <h3><?php _e( 'Order Count Requirements', 'latepoint-pro-features' ); ?></h3>
            </div>
            <div class="latepoint-message latepoint-message-subtle">
                <?php esc_html_e('You can set requirements on how many orders a customer needs to have in order to use this coupon. Leave blank to have no requirements.', 'latepoint-pro-features'); ?>
            </div>
            <div class="os-row">
                <div class="os-col-6">
                    <?php echo OsFormHelper::number_field('coupon[rules][orders_more]', __('Minimum', 'latepoint-pro-features'), $coupon->get_rule('orders_more'), null, null, ['theme' => 'simple']); ?>
                </div>
                <div class="os-col-6">
                    <?php echo OsFormHelper::number_field('coupon[rules][orders_less]', __('Maximum', 'latepoint-pro-features'), $coupon->get_rule('orders_less'), null, null, ['theme' => 'simple']); ?>
                </div>
            </div>
        </div>
        <div class="os-form-buttons os-quick-form-buttons">
			<?php
			if ( $coupon->is_new_record() ) {
				if ( OsRolesHelper::can_user( 'coupon__create' ) ) {
					echo '<button name="submit" type="submit" class="latepoint-btn latepoint-btn-block latepoint-btn-lg">' . __( 'Create Coupon', 'latepoint-pro-features' ) . '</button>';
				}
			} else {
				if ( OsRolesHelper::can_user( 'coupon__edit' ) ) {
					echo '<div class="os-full">';
					echo '<button name="submit" type="submit" class="latepoint-btn latepoint-btn-block latepoint-btn-lg">' . __( 'Save Changes', 'latepoint-pro-features' ) . '</button>';
					echo '</div>';
				}
				if ( OsRolesHelper::can_user( 'coupon__delete' ) ) {
					echo '<div class="os-compact">';
					echo '<a href="#" class=" remove-coupon-btn latepoint-btn latepoint-btn-secondary latepoint-btn-lg latepoint-btn-just-icon" 
	                data-os-prompt="' . __( 'Are you sure you want to delete this coupon?', 'latepoint-pro-features' ) . '" 
	                data-os-redirect-to="' . OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'coupons', 'index' ) ) . '" 
	                data-os-params="' . OsUtilHelper::build_os_params( [ 'id' => $coupon->id ], 'destroy_coupon_' . $coupon->id ) . '" 
	                data-os-success-action="redirect" 
	                data-os-action="' . OsRouterHelper::build_route_name( 'coupons', 'destroy' ) . '"
	                title="' . __( 'Delete Coupon', 'latepoint-pro-features' ) . '">
		                <i class="latepoint-icon latepoint-icon-trash1"></i>
	                </a>';
					echo '</div>';
				}
			}
			?>
        </div>
		<?php
		echo OsFormHelper::hidden_field( 'coupon[id]', $coupon->id );
		wp_nonce_field( $coupon->is_new_record() ? 'new_coupon' : 'edit_coupon_' . $coupon->id );
		?>
    </form>
</div>
