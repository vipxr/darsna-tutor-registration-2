<?php
/**
 * @var $current_step_code string
 * @var $booking OsBookingModel
 * @var $restrictions array
 * @var $presets array
 */
?>
<?php $preselected_location = (isset($presets['selected_location']) && !empty($presets['selected_location'])) ? new OslocationModel($presets['selected_location']) : false; ?>
<div class="step-locations-w latepoint-step-content" data-step-code="<?php echo $current_step_code; ?>"
     data-next-btn-label="<?php echo OsStepsHelper::get_next_btn_label_for_step($current_step_code); ?>"
     data-clear-action="clear_step_locations">
	<?php
	if (OsSettingsHelper::steps_show_location_categories() && !$preselected_location) {
		// Generate categorized locations list
		OsLocationHelper::generate_locations_and_categories_list(false, $show_locations_arr);
	} else {
		OsLocationHelper::generate_locations_list($locations, $preselected_location);
	}
	echo OsFormHelper::hidden_field('booking[location_id]', $booking->location_id, ['class' => 'latepoint_location_id', 'skip_id' => true]); ?>
</div>


