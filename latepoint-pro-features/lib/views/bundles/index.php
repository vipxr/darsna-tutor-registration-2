<?php
/**
 * @var $bundles OsBundleModel[]
 */
?>
<?php if($bundles){ ?>
    <div class="os-item-category-w">
      <div class="os-bundles-list">
        <?php foreach ($bundles as $bundle): ?>
					<div class="os-bundle-wrapper os-bundle-status-<?php echo $bundle->status; ?>">
						<div class="os-bundle">
						  <div class="os-bundle-header">
						    <?php if($bundle->is_hidden()) echo '<i class="latepoint-icon latepoint-icon-eye-off bundle-hidden"></i>'; ?>
						    <h3 class="bundle-name"><?php echo $bundle->name; ?></h3>
						    <div class="bundle-price"><?php echo $bundle->get_formatted_charge_amount(); ?></div>
						  </div>
						  <div class="os-bundle-body">
						    <div class="os-bundle-services-wrapper">
						      <div class="label">
							      <div><?php _e('Service', 'latepoint-pro-features'); ?></div>
							      <div><?php _e('Quantity', 'latepoint-pro-features'); ?></div>
						      </div>
						      <div class="bundle-services">
						        <?php
						        foreach($bundle->get_services() as $index => $service){
											echo '<div class="bundle-service">';
											echo '<div>'.$service->name.'</div>';
											echo '<div>'.$service->join_attributes['quantity'].'</div>';
											echo '</div>';
						        } ?>
						      </div>
						    </div>
						  </div>
						  <div class="os-bundle-foot">
						    <a href="<?php echo OsRouterHelper::build_link(OsRouterHelper::build_route_name('bundles', 'edit'), array('id' => $bundle->id) ) ?>" class="latepoint-btn latepoint-btn-block latepoint-btn-secondary">
						      <i class="latepoint-icon latepoint-icon-edit-3"></i>
						      <span><?php _e('Edit Bundle', 'latepoint-pro-features'); ?></span>
						    </a>
						  </div>
						</div>
						<div class="os-bundle-shadow"></div>
						<div class="os-bundle-shadow"></div>
					</div>
        <?php endforeach; ?>
        <a class="create-bundle-link-w" href="<?php echo OsRouterHelper::build_link(OsRouterHelper::build_route_name('bundles', 'new') ) ?>">
          <div class="create-bundle-link-i">
            <div class="add-bundle-graphic-w">
              <div class="add-bundle-plus"><i class="latepoint-icon latepoint-icon-plus4"></i></div>
            </div>
            <div class="add-bundle-label"><?php _e('Add Bundle', 'latepoint-pro-features'); ?></div>
          </div>
        </a>
      </div>
    </div>
<?php }else{ ?>
  <div class="no-results-w">
    <div class="icon-w"><i class="latepoint-icon latepoint-icon-book"></i></div>
    <h2><?php _e('No Bundles Found', 'latepoint-pro-features'); ?></h2>
    <a href="<?php echo OsRouterHelper::build_link(OsRouterHelper::build_route_name('bundles', 'new') ) ?>" class="latepoint-btn">
      <i class="latepoint-icon latepoint-icon-plus-square"></i>
      <span><?php _e('Create a Bundle', 'latepoint-pro-features'); ?></span>
    </a>
  </div>
<?php } ?>