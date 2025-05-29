<?php 

class OsLicenseHelper {

  public static function get_license_key(){
    $license_info = self::get_license_info();
    return $license_info['license_key'];
  }

  public static function clear_license(){
    OsSettingsHelper::save_setting_by_name('is_active_license', 'no');
    OsSettingsHelper::save_setting_by_name('license_status_message', '');
    OsSettingsHelper::save_setting_by_name('license', '');
  }

  public static function get_license_info(){
    $license_info = OsSettingsHelper::get_settings_value('license');
    $license = array('full_name' => '', 'email' => '', 'license_key' => '');

    if($license_info){
      $license_arr = explode('*|||*', $license_info);
      $license['full_name'] = isset($license_arr[0]) ? $license_arr[0] : '';
      $license['email'] = isset($license_arr[1]) ? $license_arr[1] : '';
      $license['license_key'] = isset($license_arr[2]) ? $license_arr[2] : '';
    }

    $license['is_active'] = OsSettingsHelper::get_settings_value('is_active_license', 'no');
    $license['status_message'] = OsSettingsHelper::get_settings_value('license_status_message', false);

    return $license;
  }

  public static function is_license_active(){
    return (OsSettingsHelper::get_settings_value('is_active_license', 'no') == 'yes');
  }

  public static function verify_license_key($license_data){

    $license_key = trim(strtolower($license_data['license_key']));
    $license_owner_name = $license_data['full_name'];
    $license_owner_email = $license_data['email'];

    if(empty($license_data['license_key'])) return ['status' => LATEPOINT_STATUS_ERROR, 'message' => __('Please enter your license key', 'latepoint')];

    $glued_license = implode('*|||*', array($license_owner_name, $license_owner_email, $license_key));

    OsSettingsHelper::save_setting_by_name('license', $glued_license);

    // Mock license validation
    $is_valid_license = true;
    $message = __('License is valid. Thank you for your purchase.', 'latepoint');

    // Always set the license as valid
    $status = LATEPOINT_STATUS_SUCCESS;
    OsSettingsHelper::save_setting_by_name('is_active_license', 'yes');
    OsSettingsHelper::save_setting_by_name('license_status_message', $message);

    return ['status' => $status, 'message' => $message];
  }

}
