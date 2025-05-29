<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsRolesController' ) ) :


  class OsRolesController extends OsController {



    function __construct(){
      parent::__construct();

      $this->views_folder = plugin_dir_path( __FILE__ ) . '../views/roles/';
      $this->vars['breadcrumbs'][] = ['label' => __('Roles', 'latepoint-pro-features'), 'link' => OsRouterHelper::build_link(['roles', 'index'] )];
			$this->vars['page_header'] = OsMenuHelper::get_menu_items_by_id('settings');
	    $this->vars['pre_page_header'] = OsMenuHelper::get_label_by_id('settings');
    }

		function update_wp_user(){
			if(!filter_var($this->params['wp_user_id'], FILTER_VALIDATE_INT)) wp_die(__('Invalid User ID', 'latepoint-pro-features'));

			$wp_user = get_user_by('ID', $this->params['wp_user_id']);

			$user = \LatePoint\Misc\User::load_from_wp_user($wp_user);

      $status = LATEPOINT_STATUS_SUCCESS;
			$allowed_records = [];
			if($user->backend_user_type == LATEPOINT_USER_TYPE_CUSTOM){
				$save_custom = false;
				foreach(OsRolesHelper::get_model_types_for_allowed_records() as $model_type){
					if($this->params['allowed_records'][$model_type] == 'custom'){
						$save_custom = true;
						$allowed_records[$model_type] = explode(',', $this->params['allowed_records']['custom'][$model_type]);
					}else{
						$allowed_records[$model_type] = LATEPOINT_ALL;
					}
				}
				if($save_custom){
					// only save when we actually have custom access set, otherwise just clear it so it uses role based permissions
					$user->set_custom_allowed_records($allowed_records);
				}else{
					$user->clear_custom_allowed_records();
				}
			}else{
				$user->clear_custom_allowed_records();
			}

			if($this->params['custom_capabilities'] == LATEPOINT_VALUE_ON){
				if($this->params['capabilities']){
					$capabilities = [];
					foreach($this->params['capabilities'] as $capability => $on_off){
						if($on_off != LATEPOINT_VALUE_OFF) $capabilities[] = $capability;
					}
					$user->set_custom_capabilities($capabilities);
				}
			}else{
				$user->clear_custom_capabilities();
			}

      $response_html = __('User Roles Updated', 'latepoint-pro-features');
      if($this->get_return_format() == 'json'){
        $this->send_json(array('status' => $status, 'message' => $response_html));
      }
		}

		function destroy(){
			// get all custom roles, to make sure we are deleting a custom role and not some other role
      if(!empty($this->params['wp_role'])){
        if(OsRolesHelper::delete($this->params['wp_role'])){
          $status = LATEPOINT_STATUS_SUCCESS;
          $response_html = __('Role Removed', 'latepoint-pro-features');
        }else{
          $status = LATEPOINT_STATUS_ERROR;
          $response_html = __('Error Removing Role', 'latepoint-pro-features');
        }
      }else{
        $status = LATEPOINT_STATUS_ERROR;
        $response_html = __('Invalid Role', 'latepoint-pro-features');
      }
      if($this->get_return_format() == 'json'){
        $this->send_json(array('status' => $status, 'message' => $response_html));
      }
		}

		function save(){
      if($this->params['role']){

				if($this->params['role']['capabilities']){
					$capabilities = [];
					// transform passed params (from toggle boxes) into proper permitted actions array
					foreach($this->params['role']['capabilities'] as $capability => $on_off){
						if($on_off != LATEPOINT_VALUE_OFF) $capabilities[] = $capability;
					}
					$this->params['role']['capabilities'] = $capabilities;
				}
				$result = OsRolesHelper::save_from_params($this->params['role']);
				if(is_wp_error($result)){
	        $status = LATEPOINT_STATUS_ERROR;
					$response_html = $result->get_error_messages();
				}else{
	        $status = LATEPOINT_STATUS_SUCCESS;
					$response_html = __('Custom Role saved', 'latepoint-pro-features');
				}
      }else{
        $status = LATEPOINT_STATUS_ERROR;
        $response_html = __('Invalid params', 'latepoint-pro-features');
      }
      if($this->get_return_format() == 'json'){
        $this->send_json(array('status' => $status, 'message' => $response_html));
      }
		}

		function new_form(){
      $this->set_layout('none');
			$this->vars['role'] = new \LatePoint\Misc\Role(LATEPOINT_USER_TYPE_CUSTOM);
			$this->vars['available_actions_grouped'] = OsRolesHelper::get_all_available_actions_list_grouped();

      $this->format_render(__FUNCTION__);
		}

		function index(){
			$this->vars['available_actions_grouped'] = OsRolesHelper::get_all_available_actions_list_grouped();

			$default_user_roles = OsRolesHelper::get_default_roles();
			$custom_user_roles = OsRolesHelper::get_custom_roles(true);
			$this->vars['default_user_roles'] = $default_user_roles;
			$this->vars['custom_user_roles'] = $custom_user_roles;
      $this->format_render(__FUNCTION__);
		}

		function edit_wp_user(){
			$wp_user = get_user_by('id', $this->params['id']);
			$user = \LatePoint\Misc\User::load_from_wp_user($wp_user);

			$this->vars['user'] = $user;
			$this->vars['available_actions_grouped'] = OsRolesHelper::get_all_available_actions_list_grouped();


      if($this->get_return_format() == 'json'){
        wp_send_json(['status' => 'success', 'message' => $this->render($this->views_folder.'edit_wp_user', 'none')]);
      }
		}



  }

endif;