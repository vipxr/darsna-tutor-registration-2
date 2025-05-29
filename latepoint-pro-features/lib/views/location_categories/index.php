<div class="os-categories-ordering-w" data-category-order-update-route="<?php echo OsRouterHelper::build_route_name('location_categories', 'update_order_of_categories'); ?>">
	<div class="os-category-children">
	<?php OsLocationHelper::generate_location_categories_list(); ?>
	<?php if(is_array($uncategorized_locations)){
		foreach($uncategorized_locations as $location){
			echo '<div class="item-in-category-w status-'.$location->status.'" data-id="'.$location->id.'">
							<div class="os-category-item-drag"></div>
							<div class="os-category-item-name">'.$location->name.'</div>
							<div class="os-category-item-meta">'.__('ID: ', 'latepoint-pro-features').'<span>'.$location->id.'</span></div>
						</div>';
		}
	} ?>
	</div>
	<div class="add-item-category-box add-item-category-trigger">
		<div class="add-item-category-graphic-w">
			<div class="add-item-category-plus"><i class="latepoint-icon latepoint-icon-plus4"></i></div>
		</div>
		<div class="add-item-category-label"><?php _e('Create New Category', 'latepoint-pro-features'); ?></div>
	</div>
	<div class="os-form-w os-category-w editing os-new-item-category-form-w" style="display:none;">
		<div class="os-category-head">
			<div class="os-category-name"><?php _e('Create New Location Category', 'latepoint-pro-features'); ?></div>
		</div>
		<div class="os-category-body">
			<?php 
			$location_category = new OsLocationCategoryModel();
			include('_form.php'); ?>
		</div>
	</div>
</div>