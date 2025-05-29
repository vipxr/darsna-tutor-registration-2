<?php

class OsAddonsHelper {


	public static function get_routed_addons() : array{
		return json_decode(OsSettingsHelper::get_settings_value('routed_addons', ''), true) ?? [];
	}

	public static function add_routed_addon(string $addon_name) : array{
		$addons = self::get_routed_addons();
		$addons[] = $addon_name;
		$addons = array_unique($addons);
		OsSettingsHelper::save_setting_by_name('routed_addons', wp_json_encode($addons));
		return $addons;
	}

	public static function remove_routed_addon(string $addon_name) : array{
		$addons = self::get_routed_addons();
		$addons = array_diff($addons, [$addon_name]);
		OsSettingsHelper::save_setting_by_name('routed_addons', wp_json_encode($addons));
		return $addons;
	}

  public static function get_addon_download_info($addon_name){
    if(empty($addon_name)) return false;

    $license = OsLicenseHelper::get_license_info();

    $post = array(
      '_nonce'        => wp_create_nonce('addon_download'),
      'license_key'   => $license['license_key'],
      'domain'        => OsUtilHelper::get_site_url(),
      'user_ip'       => OsUtilHelper::get_user_ip(),
      'addon_name'    => $addon_name,
    );


    $request = wp_remote_post( OsUpdatesHelper::get_remote_url("/wp/addons/get-download-info"), array('body' => $post, 'sslverify' => false));

    if( !is_wp_error($request) || wp_remote_retrieve_response_code($request) === 200){
      $response = json_decode($request['body'], true);
      $url = $response['addon_info']['url'];
      $plugin_path = $response['addon_info']['plugin_path'];
      $version = $response['addon_info']['version'];
      return ['url' => $url, 'plugin_path' => $plugin_path, 'version' => $version];
    }else{
			if (is_wp_error($request)) OsDebugHelper::log('Remote Post Error', 'curl_error', ['error' => $request->get_error_messages()]);
      return __('Connection Error. Please try again in a few minutes or contact us via email addons@latepoint.com. KLJSD734', 'latepoint-pro-features');
    }

  }

  public static function is_addon_installed($addon_plugin_path){
		if(empty($addon_plugin_path)) return false;
    return is_file(self::get_addon_plugin_path($addon_plugin_path));
  }

  public static function get_addon_plugin_path($addon_path){
    if ( ! is_file( $addon_dir = WPMU_PLUGIN_DIR . '/'. $addon_path ) ) {
        if ( ! is_file( $addon_dir = WP_PLUGIN_DIR . '/'. $addon_path ) )
            $addon_dir = $addon_path;
    }
    return $addon_dir;
  }


  public static function activate_addon($plugin_path){
    $result = activate_plugins( $plugin_path );
    return $result;
  }


  public static function deactivate_addon($plugin_path){
    $result = deactivate_plugins( $plugin_path );
    return $result;
  }

  // addon_info['url', 'plugin_path', 'version']
  public static function install_addon($addon_info){
    if($addon_info['url'] && $addon_info['plugin_path']){
      include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
      include_once( LATEPOINT_ADDON_PRO_ABSPATH . 'lib/helpers/plugin_upgrader.php' );
      $upgrader = new OsPluginUpgrader(new WP_Ajax_Upgrader_Skin());
      if(is_plugin_active($addon_info['plugin_path'])){
        // already installed, update if version is lower
	      if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
        $installed_plugin_data = get_plugin_data(self::get_addon_plugin_path($addon_info['plugin_path']));
        if(version_compare($addon_info['version'], $installed_plugin_data['Version']) > 0){
          // updating
          $result = defined('LATEPOINT_FAKE_UPDATES') ? true : $upgrader->upgrade_from_url($addon_info['plugin_path'], $addon_info['url'] );
        }else{
          // already same version
          $result = true;
        }
      }else{
        // install
          $result = defined('LATEPOINT_FAKE_UPDATES') ? true : $upgrader->install( $addon_info['url'] );
          if ( !is_wp_error( $result ) ) {
            $result = self::activate_addon( $addon_info['plugin_path'] );
            if ( !is_wp_error( $result ) ) $result = true;
          }
      }
      return $result;
    }else{
      return new WP_Error('invalid_addon', __('Error installing addon! Invalid info KFE73463', 'latepoint-pro-features'));
    }

  }

}
