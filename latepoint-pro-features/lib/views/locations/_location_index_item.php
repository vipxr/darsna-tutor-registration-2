<div class="os-location os-location-status-<?php echo $location->status; ?>">
  <div class="os-location-body">
    <div class="os-location-address">
      <?php if($location->full_address){ ?>
        <iframe width="100%" height="240" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="<?php echo $location->get_google_maps_link(true); ?>"></iframe>
      <?php } ?>
    </div>

    <div class="os-location-header">
      <h3 class="location-name"><?php echo $location->name; ?></h3>
      <div class="os-location-info"><?php echo $location->full_address; ?></div>
      <a href="<?php echo OsRouterHelper::build_link(OsRouterHelper::build_route_name('locations', 'edit_form'), ['id' => $location->id] ); ?>" class="edit-location-btn">
        <i class="latepoint-icon latepoint-icon-edit-3"></i>
        <span><?php _e('Edit', 'latepoint-pro-features'); ?></span>
      </a>
    </div>
    <div class="os-location-agents">
      <div class="label"><?php _e('Agents:', 'latepoint-pro-features'); ?></div>
      <?php if($location->connected_agents){ ?>
        <div class="agents-avatars">
        <?php foreach($location->connected_agents as $agent){ ?>
          <div class="agent-avatar" style="background-image: url(<?php echo esc_url($agent->avatar_url); ?>)"></div>
        <?php } ?>
        </div>
      <?php }else{
        echo '<a href="'.OsRouterHelper::build_link(OsRouterHelper::build_route_name('locations', 'edit_form'), ['id' => $location->id] ).'" class="no-agents-for-location">'.__('No Agents Assigned', 'latepoint-pro-features').'</a>';
      } ?>
    </div>
  </div>
</div>