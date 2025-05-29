<?php if($uncategorized_locations){ ?>
    <div class="os-item-category-w">
      <div class="os-form-sub-header sub-level"><h3><?php _e('Uncategorized', 'latepoint-pro-features'); ?></h3></div>
      <div class="os-locations-list">
        <?php foreach ($uncategorized_locations as $location): ?>
          <?php include('_location_index_item.php'); ?>
        <?php endforeach; ?>
        <a class="create-location-link-w" href="<?php echo OsRouterHelper::build_link(OsRouterHelper::build_route_name('locations', 'new_form') ) ?>">
          <div class="create-location-link-i">
            <div class="add-location-graphic-w">
              <div class="add-location-plus"><i class="latepoint-icon latepoint-icon-plus4"></i></div>
            </div>
            <div class="add-location-label"><?php _e('Add Location', 'latepoint-pro-features'); ?></div>
          </div>
        </a>
      </div>
    </div>
<?php } ?>
<?php if($location_categories){ ?>
  <?php foreach ($location_categories as $location_category): ?>
    <div class="os-item-category-w">
      <div class="os-form-sub-header sub-level"><h3><?php echo $location_category->name; ?></h3></div>
      <div class="os-locations-list">
      <?php 
        if($location_category->active_locations){ ?>
          <?php foreach ($location_category->active_locations as $location): ?>
            <?php include('_location_index_item.php'); ?>
          <?php endforeach; ?>
          <?php 
        } ?>
        <a class="create-location-link-w" href="<?php echo OsRouterHelper::build_link(['locations', 'new_form'], ['location_category_id' => $location_category->id] ); ?>">
          <div class="create-location-link-i">
            <div class="add-location-graphic-w">
              <div class="add-location-plus"><i class="latepoint-icon latepoint-icon-plus4"></i></div>
            </div>
            <div class="add-location-label"><?php _e('Add Location', 'latepoint-pro-features'); ?></div>
          </div>
        </a>
      </div>
    </div>
  <?php endforeach; ?>
 
<?php }else{ ?>
  <?php if(!$uncategorized_locations){ ?>
    <div class="no-results-w">
      <div class="icon-w"><i class="latepoint-icon latepoint-icon-book"></i></div>
      <h2><?php _e('No Active Locations Found', 'latepoint-pro-features'); ?></h2>
      <a href="<?php echo OsRouterHelper::build_link(OsRouterHelper::build_route_name('locations', 'new_form') ) ?>" class="latepoint-btn">
        <i class="latepoint-icon latepoint-icon-plus-square"></i>
        <span><?php _e('Add Location', 'latepoint-pro-features'); ?></span>
      </a>
    </div>
  <?php } ?>
<?php } ?>


<?php if($disabled_locations){
    echo '<div class="disabled-items-wrapper">';
    echo '<div class="disabled-items-open-trigger"><span>'.sprintf(_n('%d Disabled Location', '%d Disabled Locations', count($disabled_locations),'latepoint-pro-features'), count($disabled_locations)).'</span><i class="latepoint-icon latepoint-icon-chevron-down"></i></div>';
        echo '<div class="os-locations-list disabled-items-boxes">';
        foreach($disabled_locations as $location){
            include('_location_index_item.php');
        }
        echo '</div>';
    echo '</div>';
}
