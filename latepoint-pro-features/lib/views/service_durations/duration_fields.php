<?php
/**
 * @var $duration array
 **/
?>
<div class="duration-box service-duration-box extra-duration">
  <div class="os-row">
    <div class="os-col-lg-3">
      <?php echo OsFormHelper::text_field('service[durations]['.$duration['id'].'][name]', __('Optional Duration Name', 'latepoint-pro-features'), $duration['name'] ?? '',['theme' => 'bordered']); ?>
    </div>
    <div class="os-col-lg-3">
      <?php echo OsFormHelper::text_field('service[durations]['.$duration['id'].'][duration]', __('Service Duration (minutes)', 'latepoint-pro-features'), $duration['duration'],['theme' => 'bordered']); ?>
    </div>
    <div class="os-col-lg-3">
      <?php echo OsFormHelper::money_field('service[durations]['.$duration['id'].'][charge_amount]', __('Charge Amount', 'latepoint-pro-features'), $duration['charge_amount'],['theme' => 'bordered']); ?>
    </div>
    <div class="os-col-lg-3">
      <?php echo OsFormHelper::money_field('service[durations]['.$duration['id'].'][deposit_amount]', __('Deposit Amount', 'latepoint-pro-features'), $duration['deposit_amount'],['theme' => 'bordered']); ?>
    </div>
  </div>
  <a href="#" class="os-remove-duration"><i class="latepoint-icon latepoint-icon-cross"></i></a>
</div>