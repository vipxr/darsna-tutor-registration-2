<?php
/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


if ( ! class_exists( 'OsAgentsController' ) ) :


	class OsAgentsController extends OsController {


		function __construct() {
			parent::__construct();

			$this->views_folder          = plugin_dir_path( __FILE__ ) . '../views/agents/';
			$this->vars['page_header']   = OsMenuHelper::get_menu_items_by_id( 'agents' );
			$this->vars['breadcrumbs'][] = array( 'label' => __( 'Agents', 'latepoint-pro-features' ), 'link' => OsRouterHelper::build_link( OsRouterHelper::build_route_name( 'agents', 'index' ) ) );
		}


		/*
		  Index of agents
		*/

		public function index() {
			$this->vars['breadcrumbs'][] = array( 'label' => __( 'Index', 'latepoint-pro-features' ), 'link' => false );

			$agents                        = new OsAgentModel();
			$this->vars['agents']          = $agents->should_be_active()->filter_allowed_records()->get_results_as_models();
			$this->vars['disabled_agents'] = $agents->where( [ 'status' => LATEPOINT_AGENT_STATUS_DISABLED ] )->filter_allowed_records()->get_results_as_models();

			$this->format_render( __FUNCTION__ );
		}


		/*
		  New agent form
		*/

		public function new_form() {
			$this->vars['page_header']   = __( 'Create New Agent', 'latepoint-pro-features' );
			$this->vars['breadcrumbs'][] = array( 'label' => __( 'Create New Agent', 'latepoint-pro-features' ), 'link' => false );

			$this->vars['agent']               = new OsAgentModel();
			$this->vars['wp_users_for_select'] = OsWpUserHelper::get_wp_users_for_select( [ 'role' => LATEPOINT_WP_AGENT_ROLE ] );

			$this->vars['custom_work_periods'] = [];
			$this->vars['is_custom_schedule']  = false;

			$services               = new OsServiceModel();
			$this->vars['services'] = $services->get_results_as_models();

			$locations               = new OsLocationModel();
			$this->vars['locations'] = $locations->get_results_as_models();


			$this->format_render( __FUNCTION__ );
		}

		public function quick_new() {
			$agent = new OsAgentModel();

			$this->vars['agent'] = $agent;

			$this->format_render( 'quick_edit' );
		}

		public function view_agent_log() {

			$activities = new OsActivityModel();
			$activities = $activities->where( [ 'agent_id' => absint( $this->params['agent_id'] ) ] )->order_by( 'id desc' )->get_results_as_models();

			$agent = new OsCustomerModel( $this->params['agent_id'] );

			$this->vars['agent']      = $agent;
			$this->vars['activities'] = $activities;

			$this->format_render( __FUNCTION__ );
		}

		public function quick_edit() {
			if ( ! filter_var( $this->params['id'], FILTER_VALIDATE_INT ) || ! OsAuthHelper::get_current_user()->check_if_allowed_record_id( $this->params['id'], 'agent' ) ) {
				$this->access_not_allowed();
			}
			$agent = new OsAgentModel( $this->params['id'] );

			$this->vars['agent'] = $agent;

			$this->vars['wp_users_for_select'] = OsWpUserHelper::get_wp_users_for_select( [ 'role' => LATEPOINT_WP_AGENT_ROLE ] );

			$custom_work_periods               = OsWorkPeriodsHelper::get_work_periods( new \LatePoint\Misc\Filter( [ 'agent_id' => $agent->id, 'exact_match' => true ] ), true );
			$this->vars['custom_work_periods'] = $custom_work_periods;
			$this->vars['is_custom_schedule']  = ( $custom_work_periods && ( count( $custom_work_periods ) > 0 ) );
			$services                          = new OsServiceModel();
			$this->vars['services']            = $services->get_results_as_models();
			$locations                         = new OsLocationModel();
			$this->vars['locations']           = $locations->get_results_as_models();

			$this->format_render( __FUNCTION__ );
		}


		/*
		  Edit agent
		*/

		public function edit_form() {
			if ( ! filter_var( $this->params['id'], FILTER_VALIDATE_INT ) || ! OsAuthHelper::get_current_user()->check_if_allowed_record_id( $this->params['id'], 'agent' ) ) {
				$this->access_not_allowed();
			}

			$this->vars['page_header']   = __( 'Edit Agent', 'latepoint-pro-features' );
			$this->vars['breadcrumbs'][] = array( 'label' => __( 'Edit Agent', 'latepoint-pro-features' ), 'link' => false );

			$agent_id = $this->params['id'];

			$agent = new OsAgentModel( $agent_id );

			if ( $agent->id ) {

				$this->vars['agent']               = $agent;
				$this->vars['wp_users_for_select'] = OsWpUserHelper::get_wp_users_for_select( [ 'role' => LATEPOINT_WP_AGENT_ROLE ] );

				$custom_work_periods               = OsWorkPeriodsHelper::get_work_periods( new \LatePoint\Misc\Filter( [ 'agent_id' => $agent_id, 'exact_match' => true ] ), true );
				$this->vars['custom_work_periods'] = $custom_work_periods;
				$this->vars['is_custom_schedule']  = ( $custom_work_periods && ( count( $custom_work_periods ) > 0 ) );
				$services                          = new OsServiceModel();
				$this->vars['services']            = $services->get_results_as_models();
				$locations                         = new OsLocationModel();
				$this->vars['locations']           = $locations->get_results_as_models();
			}

			$this->format_render( __FUNCTION__ );
		}


		/*
		  Create agent
		*/

		public function create() {
			$this->update();
		}


		/*
		  Update agent
		*/

		public function update() {
			$is_new_record = ( isset( $this->params['agent']['id'] ) && $this->params['agent']['id'] ) ? false : true;

			$this->check_nonce( $is_new_record ? 'new_agent' : 'edit_agent_' . $this->params['agent']['id'] );
			$agent = new OsAgentModel();
			$agent->set_data( $this->params['agent'] );
			$agent->set_features( $this->params['agent']['features'] );
			$extra_response_vars = array();

			if ( $agent->save() && ( empty( $this->params['agent']['services'] ) || $agent->save_locations_and_services( $this->params['agent']['services'] ) ) ) {
				if ( $is_new_record ) {
					$response_html = __( 'Agent Created. ID:', 'latepoint-pro-features' ) . $agent->id;
					OsActivitiesHelper::create_activity( array( 'code' => 'agent_create', 'agent_id' => $agent->id ) );
				} else {
					$response_html = __( 'Agent Updated. ID:', 'latepoint-pro-features' ) . $agent->id;
					OsActivitiesHelper::create_activity( array( 'code' => 'agent_update', 'agent_id' => $agent->id ) );
				}
				$status = LATEPOINT_STATUS_SUCCESS;
				// save schedules
				if ( $this->params['is_custom_schedule'] == 'on' ) {
					$agent->save_custom_schedule( $this->params['work_periods'] );
				} elseif ( $this->params['is_custom_schedule'] == 'off' ) {
					$agent->delete_custom_schedule();
				}
				$extra_response_vars['record_id'] = $agent->id;
				do_action( 'latepoint_agent_saved', $agent, $is_new_record, $this->params['agent'] );
			} else {
				$response_html = $agent->get_error_messages();
				$status        = LATEPOINT_STATUS_ERROR;
			}
			if ( $this->get_return_format() == 'json' ) {
				$this->send_json( array( 'status' => $status, 'message' => $response_html ) + $extra_response_vars );
			}
		}


		public function destroy() {
			if ( filter_var( $this->params['id'], FILTER_VALIDATE_INT ) ) {
				$this->check_nonce( 'destroy_agent_' . $this->params['id'] );
				$agent = new OsAgentModel( $this->params['id'] );
				if ( $agent->delete() ) {
					$status        = LATEPOINT_STATUS_SUCCESS;
					$response_html = __( 'Agent Removed', 'latepoint-pro-features' );
				} else {
					$status        = LATEPOINT_STATUS_ERROR;
					$response_html = __( 'Error Removing Agent', 'latepoint-pro-features' );
				}
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Error Removing Agent', 'latepoint-pro-features' );
			}

			if ( $this->get_return_format() == 'json' ) {
				$this->send_json( array( 'status' => $status, 'message' => $response_html ) );
			}
		}

		public function mini_profile() {
			if ( filter_var( $this->params['agent_id'], FILTER_VALIDATE_INT ) ) {
				$agent = new OsAgentModel( $this->params['agent_id'] );
				// check if booking ID was passed, to get more detailed information
				$filter = new \LatePoint\Misc\Filter();
				if ( filter_var( $this->params['booking_id'], FILTER_VALIDATE_INT ) ) {
					$booking               = new OsBookingModel( $this->params['booking_id'] );
					$this->vars['booking'] = $booking;
					$target_date           = new OsWpDateTime( $booking->start_date );
					if ( $booking->location_id ) {
						$filter->location_id = $booking->location_id;
					}
					if ( $booking->service_id ) {
						$filter->service_id = $booking->service_id;
					}
				} else {
					$this->vars['booking'] = false;
					$target_date           = new OsWpDateTime( 'today' );
				}
				$filter->agent_id     = $agent->id;
				$filter->date_from    = $target_date->format( 'Y-m-d' );
				$filter->statuses     = OsCalendarHelper::get_booking_statuses_to_display_on_calendar();
				$this->vars['filter'] = $filter;
				$this->vars['agent']  = $agent;
				$this->set_layout( 'none' );
				$response_html = $this->format_render_return( __FUNCTION__ );
			} else {
				$status        = LATEPOINT_STATUS_ERROR;
				$response_html = __( 'Error Accessing Agent', 'latepoint-pro-features' );
			}

			if ( $this->get_return_format() == 'json' ) {
				$this->send_json( array( 'status' => $status, 'message' => $response_html ) );
			}
		}

	}


endif;