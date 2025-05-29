<?php
/**
 * @var $agents OsAgentModel[]
 * @var $disabled_agents OsAgentModel[]
 */
?>
<?php if($agents){ ?>
	<div class="index-agent-boxes">
		<?php
			$today_date = new OsWpDateTime('today');
			foreach($agents as $agent){ ?>
				<a href="<?php echo OsRouterHelper::build_link(OsRouterHelper::build_route_name('agents', 'edit_form'), ['id' => $agent->id] ); ?>" class="agent-box-w agent-status-<?php echo esc_attr($agent->status); ?>">
					<div class="agent-edit-icon"><i class="latepoint-icon latepoint-icon-edit-3"></i></div>
					<div class="agent-info-w">
						<div class="agent-avatar" style="background-image: url(<?php echo esc_url($agent->avatar_url); ?>)"></div>
						<div class="agent-info">
							<div class="agent-name"><?php echo esc_html($agent->full_name); ?></div>
							<div class="agent-phone"><?php echo esc_html($agent->phone); ?></div>
							<?php if($agent->wp_user_id) echo '<span class="agent-connection-icon"><img title="'.__('Connected to WordPress User', 'latepoint-pro-features').'" src="'.esc_attr(LatePoint::images_url().'wordpress-logo.png').'"/></span>'; ?>
							<?php do_action('latepoint_after_agent_info_on_index', $agent); ?>
						</div>
					</div>
					<div class="agent-schedule">
						<?php 
						$custom_work_periods = OsWorkPeriodsHelper::get_work_periods(new \LatePoint\Misc\Filter(['agent_id' => $agent->id, 'exact_match' => true]));
						if(!$custom_work_periods){
							$work_periods = OsWorkPeriodsHelper::get_work_periods(new \LatePoint\Misc\Filter());
						}else{
							$work_periods = $custom_work_periods;
						}
						$working_periods_with_weekdays = [];
				    if($work_periods){
				      foreach($work_periods as $work_period){
				        $working_periods_with_weekdays['day_'.$work_period->week_day][] = $work_period;
				      }
				    }

						for($i=1;$i<=7;$i++){
				      $is_day_off = true;
				      $period_forms_html = '';
				      if(isset($working_periods_with_weekdays['day_'.$i])){
				        $is_day_off = false;
				        // EXISTING WORK PERIOD
				        foreach($working_periods_with_weekdays['day_'.$i] as $work_period){
				          if($work_period->start_time === $work_period->end_time){
				            $is_day_off = true;
				          }
				        }
				      }
				      $status_class = $is_day_off ? 'is-off' : 'is-on';
							echo '<div class="schedule-day '.$status_class.'">'.OsBookingHelper::get_weekday_name_by_number($i).'</div>';
						} ?>
					</div>
					<?php OsAgentHelper::generate_day_schedule_info(new \LatePoint\Misc\Filter(['agent_id' => $agent->id, 'date_from' => $today_date->format('Y-m-d')])); ?>
				</a>
				<?php
			}
		?>
		<?php if(OsRolesHelper::can_user('agent__create')){ ?>
			<a href="<?php echo OsRouterHelper::build_link(OsRouterHelper::build_route_name('agents', 'new_form') ) ?>" class="create-agent-link-w">
        <div class="create-agent-link-i">
          <div class="add-agent-graphic-w">
            <div class="add-agent-plus"><i class="latepoint-icon latepoint-icon-plus4"></i></div>
          </div>
          <div class="add-agent-label"><?php _e('Add Agent', 'latepoint-pro-features'); ?></div>
        </div>
			</a>
		<?php } ?>
	</div>
<?php }else{ ?>
  <div class="no-results-w">
    <div class="icon-w"><i class="latepoint-icon latepoint-icon-users"></i></div>
    <h2><?php _e('No Active Agents Found', 'latepoint-pro-features'); ?></h2>
    <a href="<?php echo OsRouterHelper::build_link(OsRouterHelper::build_route_name('agents', 'new_form') ) ?>" class="latepoint-btn"><i class="latepoint-icon latepoint-icon-plus"></i><span><?php _e('Add First Agent', 'latepoint-pro-features'); ?></span></a>
  </div>
<?php } ?>
<?php if($disabled_agents){
    echo '<div class="disabled-items-wrapper">';
    echo '<div class="disabled-items-open-trigger"><span>'.sprintf(_n('%d Disabled Agent', '%d Disabled Agents', count($disabled_agents),'latepoint-pro-features'), count($disabled_agents)).'</span><i class="latepoint-icon latepoint-icon-chevron-down"></i></div>';
        echo '<div class="index-agent-boxes disabled-items-boxes">';
        foreach($disabled_agents as $agent){ ?>
            <a href="<?php echo OsRouterHelper::build_link(OsRouterHelper::build_route_name('agents', 'edit_form'), ['id' => $agent->id] ); ?>" class="agent-box-w agent-status-<?php echo esc_attr($agent->status); ?>">
                <div class="agent-edit-icon"><i class="latepoint-icon latepoint-icon-edit-3"></i></div>
                <div class="agent-info-w">
                    <div class="agent-avatar" style="background-image: url(<?php echo esc_url($agent->avatar_url); ?>)"></div>
                    <div class="agent-info">
                        <div class="agent-name"><?php echo esc_html($agent->full_name); ?></div>
                        <div class="agent-phone"><?php echo esc_html($agent->phone); ?></div>
                        <?php if($agent->wp_user_id) echo '<span class="agent-connection-icon"><img title="'.__('Connected to WordPress User', 'latepoint-pro-features').'" src="'.esc_attr(LatePoint::images_url().'wordpress-logo.png').'"/></span>'; ?>
                        <?php do_action('latepoint_after_agent_info_on_index', $agent); ?>
                    </div>
                </div>
            </a>
            <?php
        }
        echo '</div>';
    echo '</div>';
}