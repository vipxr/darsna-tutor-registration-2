function latepoint_init_booking_messages_file_upload(){

  // UPLOAD/REMOVE IMAGE LINK LOGIC
  jQuery('body').on( 'click', '.os-bm-upload-file-btn', function( event ){
    var frame;
    var $input = jQuery(this);
    var $wrapper = $input.closest('.os-booking-messages-input-w');

    event.preventDefault();
    
    // If the media frame already exists, reopen it.
    if ( frame ) {
      frame.open();
      return false;
    }
    
    // Create a new media frame
    frame = wp.media({
      title: 'Select or Upload Media',
      button: { text: 'Use this media' },
      multiple: false
    });

    frame.on( 'select', function() {
      var attachment = frame.state().get('selection').first().toJSON();
      var avatar_url = $wrapper.data('avatar-url');
      jQuery('.booking-messages-wrapper .booking-messages-list').append('<div class="os-booking-message-attachment-w os-bm-agent"><div class="os-booking-message-attachment"><i class="latepoint-icon latepoint-icon-paperclip"></i><span>' + attachment.filename + '</span></div><div class="os-bm-info-w"><div class="os-bm-avatar" style="background-image:url('+ avatar_url +');"></div><div class="os-bm-date">'+ latepoint_helper.string_today + '</div></div></div>').scrollTop(jQuery('.booking-messages-wrapper .booking-messages-list')[0].scrollHeight);


      var params = { message: {
                        content: attachment.id, 
                        content_type: 'attachment',
                        author_type: $wrapper.data('author-type'),
                        booking_id: $wrapper.data('booking-id') 
                      }
                    };
      var data = { action: 'latepoint_route_call', route_name: $wrapper.data('route'), params: params, return_format: 'json' } 

      jQuery.ajax({
        type : "post",
        dataType : "json",
        url : latepoint_timestamped_ajaxurl(),
        data : data,
        success: function(response){
          $wrapper.find('.latepoint-btn').removeClass('os-loading');
          if(response.status === "success"){

          }else{
            alert(response.message);
          }
        }
      });
    });

    frame.open();
    
    return false;
  });
}


function latepoint_send_booking_message($wrapper){
    var $input = $wrapper.find('.os-booking-messages-input');
    var message_content = $input.val();

    if (!message_content) return;

    var params = { message: {
                    content: message_content, 
                    author_type: $wrapper.data('author-type'),
                    booking_id: $wrapper.data('booking-id') }
                  };
    var data = { action: 'latepoint_route_call', route_name: $wrapper.data('route'), params: params, return_format: 'json' } 
    $wrapper.find('.latepoint-btn').addClass('os-loading');
    jQuery('.booking-messages-wrapper').find('.os-bm-no-messages').remove();
    var avatar_url = $wrapper.data('avatar-url');
    jQuery('.booking-messages-wrapper .booking-messages-list').append('<div class="os-booking-message-w os-bm-agent"><div class="os-booking-message">' + message_content + '</div><div class="os-bm-info-w"><div class="os-bm-avatar" style="background-image:url('+ avatar_url +');"></div><div class="os-bm-date">'+ latepoint_helper.string_today + '</div></div></div>');
    latepoint_admin_messages_scroll_chat();

    $input.val('');
    jQuery.ajax({
      type : "post",
      dataType : "json",
      url : latepoint_timestamped_ajaxurl(),
      data : data,
      success: function(response){
        $wrapper.removeClass('os-is-typing');
        $wrapper.find('.latepoint-btn').removeClass('os-loading');
        if(response.status === "success"){

        }else{
          $input.val(message_content);
          alert(response.message);
        }
      }
    });
    return false;
}

function latepoint_init_booking_messages(){
  // FILE UPLOADER
  latepoint_init_booking_messages_file_upload();
  clearInterval(latepoint_helper.latepoint_message_refresh_timer);

  jQuery('body').on('click', '.os-bm-send-btn', function(event){
    var $wrapper = jQuery(this).closest('.os-booking-messages-input-w');
    latepoint_send_booking_message($wrapper);
    return false;
  });
  // INPUT TEXT BOX
  jQuery('body').on('keyup', '.os-booking-messages-input', function(event){
    var $input = jQuery(this);
    var $wrapper = $input.closest('.os-booking-messages-input-w');
    if(event.keyCode == 13){
      event.preventDefault();
      latepoint_send_booking_message($wrapper);
      return false;
    }else{
      if($input.val()){
        $wrapper.addClass('os-is-typing');
      }else{
        $wrapper.removeClass('os-is-typing');
      }
    }
  });


  latepoint_helper.latepoint_message_refresh_timer = setInterval(function(){
    if (!document.hidden && jQuery('.booking-messages-wrapper').length) {
      var $messages_panel = jQuery('.booking-messages-wrapper');
      var data = {
        action: 'latepoint_route_call',
        route_name: $messages_panel.data('check-unread-route'),
        params: {
          booking_id: $messages_panel.find('.os-booking-messages-input-w').data('booking-id'),
          viewer_user_type: 'backend_user'
        },
        return_format: 'json'
      }
      jQuery.ajax({
        type : "post",
        dataType : "json",
        url : latepoint_timestamped_ajaxurl(),
        data : data,
        success: function(response){
          if(response.status === "success"){
            if(response.message == 'yes'){
              latepoint_admin_reload_chat_messages();
            }
          }
        }
      });
    }
  }, 3000);
}


function latepoint_init_loaded_coversation($conversation){
  jQuery('.os-conversation-box.is-selected').removeClass('is-selected');
  $conversation.addClass('is-selected').removeClass('is-new').addClass('is-read');
  jQuery('.os-booking-messages-input-w').data('booking-id', $conversation.data('booking-id'));
  jQuery('.os-conversations-wrapper').removeClass('mobile-show-conversations').removeClass('mobile-show-booking-info');
  jQuery('.os-conversations-wrapper .booking-messages-list').parent().scrollTop(jQuery('.os-conversations-wrapper .booking-messages-list')[0].scrollHeight);
}

function latepoint_admin_reload_chat_messages(){
  var $messages_panel = jQuery('.booking-messages-wrapper');
  var data = {
    action: 'latepoint_route_call',
    route_name: $messages_panel.data('route'),
    params: {
      booking_id: $messages_panel.find('.os-booking-messages-input-w').data('booking-id'),
      viewer_user_type: 'backend_user'
    },
    return_format: 'json'
  }
  jQuery.ajax({
    type : "post",
    dataType : "json",
    url : latepoint_timestamped_ajaxurl(),
    data : data,
    success: function(response){
      if(response.status === "success"){
        $messages_panel.find('.booking-messages-list').html(response.message);
        latepoint_admin_messages_scroll_chat();
      }else{
        alert(response.message);
      }
    }
  });
}

function latepoint_admin_messages_scroll_chat(){
  if(jQuery('.booking-messages-wrapper .booking-messages-list').length) jQuery('.booking-messages-wrapper .booking-messages-list').parent().scrollTop(jQuery('.booking-messages-wrapper .booking-messages-list')[0].scrollHeight);
  if(jQuery('.os-conversations-wrapper .booking-messages-list').length) jQuery('.os-conversations-wrapper .booking-messages-list').parent().scrollTop(jQuery('.os-conversations-wrapper .booking-messages-list')[0].scrollHeight);
}

function latepoint_init_booking_messages_panel(){
  jQuery('.latepoint-admin').on('click', '.os-bm-open-quick-messages', function(){
    var $trigger_elem = jQuery(this);
    var $messages_info_w = $trigger_elem.closest('.booking-messages-info-w');
    $trigger_elem.addClass('os-loading');
    var route = $messages_info_w.data('route');
    var data = { action: 'latepoint_route_call', route_name: route, params: {booking_id: $messages_info_w.data('booking-id')}, return_format: 'json' }
    jQuery.ajax({
      type : "post",
      dataType : "json",
      url : latepoint_timestamped_ajaxurl(),
      data : data,
      success: function(response){
        $trigger_elem.removeClass('os-loading');
        if(response.status === "success"){
          latepoint_display_in_side_sub_panel(response.message);
          latepoint_admin_messages_scroll_chat();
          latepoint_init_booking_messages();
          jQuery('.os-booking-messages-input').trigger('focus');
        }else{
          alert(response.message, 'error');
        }
      }
    });
  });
}

// DOCUMENT READY
jQuery(document).ready(function( $ ) {

  jQuery('.os-conversations-wrapper').on('click', '.os-toggle-conversations-panel', function(){
    jQuery('.os-conversations-wrapper').toggleClass('mobile-show-conversations');
  });
  jQuery('.os-conversations-wrapper').on('click', '.os-toggle-conversation-booking-info-panel', function(){
    jQuery('.os-conversations-wrapper').toggleClass('mobile-show-booking-info');
  });

	latepoint_init_booking_messages_panel();

  if(jQuery('.os-conversations-wrapper').length){
    latepoint_admin_messages_scroll_chat();
    latepoint_init_booking_messages();
    jQuery('.os-booking-messages-input').trigger('focus');
  }

	$('.osc-search-input').on('keyup', function(e){
		if(e.which == 27){
			// esc
			$(this).val('');
    	$('.os-search-conversations-list').html('').hide();
    	$('.os-conversations-list').show();
      $this.closest('.osc-search-wrapper').removeClass('os-loading');
		}else{
			var $this = $(this);
			if(!$this.val()){
	    	$('.os-search-conversations-list').html('').hide();
	    	$('.os-conversations-list').show();
	      $this.closest('.osc-search-wrapper').removeClass('os-loading');
	    	return false;
			}
      var route = $(this).data('route');
      var params = {query: $this.val()};

      var data = { action: 'latepoint_route_call', route_name: route, params: params, layout: 'none', return_format: 'json' }
      $this.closest('.osc-search-wrapper').addClass('os-loading');
      $.ajax({
        type : "post",
        dataType : "json",
        url : latepoint_timestamped_ajaxurl(),
        data : data,
        success: function(data){
					if(!$this.val()){
			    	$('.os-search-conversations-list').html('').hide();
			    	$('.os-conversations-list').show();
			    	return false;
					}
          $this.closest('.osc-search-wrapper').removeClass('os-loading');
          if(data.status === "success"){
          	$('.os-search-conversations-list').html(data.message).show();
          	$('.os-conversations-list').hide();
          }
        }
      });
		}
	});
});

function latepoint_webhook_removed($elem){
  $elem.closest('.os-webhook-form').remove();
}

function latepoint_webhook_updated($elem){
  location.reload();
}

function latepoint_init_webhooks_form(){
  jQuery('.latepoint-content-w').on('click', '.os-webhook-form-info', function(){
    jQuery(this).closest('.os-webhook-form').toggleClass('os-is-editing');
    return false;
  });
  jQuery('.latepoint-content-w').on('change', 'select.os-webhook-type-select', function(){
    if(jQuery(this).val() == 'select'){
      jQuery(this).closest('.os-webhook-form').find('.os-webhook-select-values').show();
    }else{
      jQuery(this).closest('.os-webhook-form').find('.os-webhook-select-values').hide();
    }
  });
  jQuery('.latepoint-content-w').on('keyup', '.os-webhook-name-input', function(){
    jQuery(this).closest('.os-webhook-form').find('.os-webhook-name').text(jQuery(this).val());
  });
}

( function( $ ) {
  "use strict";

  // DOCUMENT READY
  $( function() {
    latepoint_init_webhooks_form();
  });



} )( jQuery );

/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

class LatepointServiceExtrasAdminAddon {

  // Init
  constructor(){
    this.ready();
  }


  reload_service_extras_on_quick_form($booking_data_form){
    $booking_data_form.find('.latepoint-service-extras-for-booking-wrapper').addClass('os-loading');

    var route_name = $booking_data_form.find('.latepoint-service-extras-for-booking-wrapper').data('route-name');
    var form_data = new FormData($booking_data_form.closest('.order-quick-edit-form')[0]);
    form_data.append('order_item_id', $booking_data_form.data('order-item-id'));
    form_data.append('booking_id', $booking_data_form.data('booking-id'));
    var data = {
      action: latepoint_helper.route_action,
      route_name: route_name,
      params: latepoint_formdata_to_url_encoded_string(form_data),
      return_format: 'json'
    };

    jQuery.ajax({
      type: 'post',
      dataType : "json",
      url : latepoint_timestamped_ajaxurl(),
      data : data,
      success: (response) => {
        $booking_data_form.find('.latepoint-service-extras-for-booking-wrapper').removeClass('os-loading');
        if(response.status === latepoint_helper.response_status.success){
          $booking_data_form.find('.latepoint-service-extras-for-booking-wrapper').html(response.message);
          $booking_data_form.find('.latepoint-service-extras-for-booking-wrapper .os-late-select').lateSelect();

        }else{
          alert("Error!");
        }
      }
    });
  }


  ready(){
    jQuery(() => {
      let thisClass = this;

      jQuery('body').on('latepoint:initBookingDataForm', '.quick-order-form-w', (event) => {

        // handle change event for agent/service/location dropdown picker...
        jQuery(event.target).find('.os-affects-service-extras').on('change', (event) => {
          this.reload_service_extras_on_quick_form(jQuery(event.target).closest('.order-item-booking-data-form-wrapper'));
        });

        jQuery(event.target).on('click', '.clear-lateselect', function(){
          jQuery('#'+jQuery(this).data('lateselect-id')).val('').closest('.os-form-group').find('select').trigger('change');
          jQuery('.clear-missing-lateselect').remove();
        });
      });
    });
  }
}
window.latepointServiceExtrasAdminAddon = new LatepointServiceExtrasAdminAddon();

/*
 * Copyright (c) 2023 LatePoint LLC. All rights reserved.
 */

class LatepointRoleManagerAddonAdmin {

	// Init
	constructor(){
		this.ready();
	}

	ready() {
    jQuery(() => {

    });
  }

	role_deleted($elem){
    $elem.closest('.os-form-block').remove();
	}

	init_edit_wp_user_form(){
		let $form = jQuery('.role-user-edit-form');
		$form.find('.os-late-select').lateSelect();
		$form.find('select.allowed_models_selector').on('change', function(){
			if(jQuery(this).val() == latepoint_helper.value_all){
				jQuery(this).closest('.os-form-group').next('div').hide();
			}else{
				jQuery(this).closest('.os-form-group').next('div').show();
			}
		});
		$form.find('input#custom_capabilities').on('change', function(){
			if(jQuery(this).val() == 'on'){
				$form.find('.custom-user-capabilities-w').show();
				$form.find('.default-user-capabilities-w').hide();
			}else{
				$form.find('.custom-user-capabilities-w').hide();
				$form.find('.default-user-capabilities-w').show();
			}
		});
	}

	init_new_role_form(){
		let $form = jQuery('.os-custom-roles-w .os-form-block:last-child');
    $form.addClass('os-is-editing').find('input[name="role[name]"]').trigger('select');
		this.init_role_form($form);
	}

	init_role_form($form){
		$form.find('input[name="role[name]"]').on('keyup', function(){
			$form.find('.update-from-name').text(jQuery(this).val());
		});
	}

}

window.latepointRoleManagerAddonAdmin = new LatepointRoleManagerAddonAdmin();

class LatepointTaxesAddon {

  // Init
  constructor(){
    this.ready();
  }

  init_new_tax_form(){
    this.init_tax_value_format(jQuery('.os-taxes-w .os-form-block:last-child'));
  }

  init_tax_value_format($tax_form){
    if($tax_form.find('.tax-type-selector').val() == 'fixed' && $tax_form.find('.tax-value').length) latepoint_mask_money($tax_form.find('.tax-value'));
    if($tax_form.find('.tax-type-selector').val() == 'percentage' && $tax_form.find('.tax-value').length) latepoint_mask_percent($tax_form.find('.tax-value'));
  }

  latepoint_tax_removed($elem){
    $elem.closest('.os-form-block').remove();
  }

  init_tax_forms(){
    jQuery('.os-taxes-w .os-form-block').each((index, form) => {
      this.init_tax_value_format(jQuery(form));
    });
  }

  ready(){
    jQuery(document).ready(() => {
      this.init_tax_forms();
      jQuery('.latepoint-content').on('change', '.tax-type-selector', (event) => {
        let $type_selector = jQuery(event.currentTarget);
        let $tax_form = $type_selector.closest('.os-form-block');
        $tax_form.find('.os-form-block-type').text($type_selector.val());
        this.init_tax_value_format($tax_form);
      });
    });
  }
}

window.latepointTaxesAddon = new LatepointTaxesAddon();

/*
 * Copyright (c) 2022 LatePoint LLC. All rights reserved.
 */

class LatepointCouponsAddon {

    // Init
    constructor() {
        this.ready();
    }

    reload_after_coupon_save(){
      if(jQuery('table.os-reload-on-coupon-update').length) latepoint_filter_table(jQuery('table.os-reload-on-coupon-update'), jQuery('table.os-reload-on-coupon-update'));
      latepoint_close_side_panel();
    }

    submit_quick_coupon_form(){
      let $quick_edit_form = jQuery('form.coupon-quick-edit-form');

      let errors = latepoint_validate_form($quick_edit_form);
      if(errors.length){
        let error_messages = errors.map(error =>  error.message ).join(', ');
        latepoint_add_notification(error_messages, 'error');
        return false;
      }

      $quick_edit_form.find('button[type="submit"]').addClass('os-loading');
      jQuery.ajax({
        type: "post",
        dataType: "json",
        processData: false,
        contentType: false,
        url: latepoint_timestamped_ajaxurl(),
        data: latepoint_create_form_data($quick_edit_form),
        success: (response) => {
          $quick_edit_form.find('button[type="submit"]').removeClass('os-loading');
          if(response.form_values_to_update){
            jQuery.each(response.form_values_to_update, function(name, value){
              $quick_edit_form.find('[name="'+ name +'"]').val(value);
            });
          }
          if (response.status === "success") {
            latepoint_add_notification(response.message);
            this.reload_after_coupon_save();
          }else{
            latepoint_add_notification(response.message, 'error');
          }
        }
      });

    }

    init_quick_coupon_form() {
        let $coupon_form_wrapper = jQuery('.quick-coupon-form-w');
        latepoint_init_input_masks($coupon_form_wrapper);
        $coupon_form_wrapper.find('.os-late-select').lateSelect();


        $coupon_form_wrapper.find('.coupon-quick-edit-form').on('submit', (event) => {
            if (jQuery(event.target).find('button[type="submit"]').hasClass('os-loading')) return false;
            event.preventDefault();
            this.submit_quick_coupon_form();
        });
    }

    ready() {
        jQuery(document).ready(() => {
            jQuery('body.latepoint-admin').on('click', '.quick-order-form-w .apply-coupon-button', function () {
                latepoint_reload_price_breakdown();
                return false;
            });

            jQuery('body.latepoint-admin').on('change', '.quick-order-form-w #apply_coupon_toggler', function () {
                if (jQuery(this).val() == 'off') {
                    jQuery('#order_coupon_code').val('');
                    latepoint_reload_price_breakdown();
                }
            });
        });
    }
}


window.latepointCouponsAddon = new LatepointCouponsAddon();

/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class LatepointCustomFieldsAdminAddon {

  // Init
  constructor(){
    this.ready();
  }

  init_new_custom_field_form(){
    this.init_custom_fields_conditions_form();
  }

  add_custom_field_condition($btn, response){
    $btn.closest('.cf-condition').after(response.message);
    this.init_custom_fields_conditions_form();
  }


  init_google_places_autosuggest($wrapper){
    if($wrapper.find('.latepoint-google-places-autocomplete').length){
      if(typeof google !== 'undefined' && typeof google.maps !== 'undefined'){
        $wrapper.find('.latepoint-google-places-autocomplete').each((index, input) => {
          if(jQuery(input).hasClass('os-initialized')) return true;
          const options = {
            fields: ["formatted_address"]
          };
          if(latepoint_helper.google_places_country_restriction) options.componentRestrictions = {country: latepoint_helper.google_places_country_restriction};
          let autocomplete = new google.maps.places.Autocomplete(input, options);
          jQuery(input).addClass('os-initialized');
        });
      }else{
        console.error('Error loading Google API library. Check if API keys is entered correctly.');
      }
    }
  }

  init_custom_fields_conditions_form(){
    jQuery('.os-late-select').lateSelect();
  }

  init_file_upload_fields($wrapper){
    $wrapper.find('.os-form-file-upload-group').each(function(){
      // do nothing if already initialized
      if(jQuery(this).hasClass('os-initialized')) return true;

      jQuery(this).on('click', '.os-uploaded-file-info', function(){
        if(!jQuery(this).hasClass('is-uploaded')) return false;
      });
      // click on remove file button
      jQuery(this).on('click', '.uf-remove', function(){
        var $file_info = jQuery(this).closest('.os-form-group').find('.os-uploaded-file-info');
        var $file_input = jQuery(this).closest('.os-form-group').find('input[type="file"]');
        if($file_input.hasClass('required') && $file_info.has('is-uploaded')){
          // file input is required and file was uploaded before, we can't clear it unless they pick another file to replace currento one
          if(confirm(latepoint_helper.custom_fields_remove_required_file_prompt)) $file_input.trigger('click');
        }else{
          if($file_info.hasClass('is-uploaded')){
            // file was uploaded before/ remove it from model and remove the hidden field that was carrying the url value
            if(!confirm(latepoint_helper.custom_fields_remove_file_prompt)) return false;
            var route_name = $file_info.closest('.os-form-group').find('input[type="file"]').data('route-name');
            var params = $file_info.closest('.os-form-group').find('input[type="file"]').data('params');
            var data = {  action: latepoint_helper.route_action,
              route_name: route_name,
              params: params,
              return_format: 'json' }
            jQuery.ajax({
              type: "post",
              dataType: "json",
              url: latepoint_timestamped_ajaxurl(),
              data: data,
              success: function (data) {
                if (data.status === "success") {
                  $file_info.closest('.os-form-group').find('input[type="hidden"]').remove();
                }
              }
            });
          }
          jQuery(this).closest('.os-form-group').find('.os-uploaded-file-info').hide();
          $file_input.val(null).trigger('change');
        }
        return false;
      });

      // file for upload was selected, or cleared
      jQuery(this).on('change', 'input[type="file"]', function(){
        if(this.files.length){
          jQuery(this).closest('.os-form-group').find('.os-uploaded-file-info').show().attr('href', '#').attr('target', '_self').find('.uf-name').text(this.files[0].name);
          jQuery(this).closest('.os-form-group').find('.os-upload-file-input-w').hide();
        }else{
          jQuery(this).closest('.os-form-group').find('.os-uploaded-file-info').hide().removeClass('is-uploaded');
          jQuery(this).closest('.os-form-group').find('.os-upload-file-input-w').show();
        }
      });
      jQuery(this).addClass('os-initialized');
    });
  }

  reload_conditional_custom_fields_on_booking_data_form($booking_data_form){
    $booking_data_form.find('.latepoint-custom-fields-for-booking-wrapper').each((index, elem) => {
      let $custom_fields_wrapper = jQuery(elem);
      let $booking_data_form = $custom_fields_wrapper.closest('.order-item-booking-data-form-wrapper');

      $custom_fields_wrapper.addClass('os-loading');

      var route_name = $custom_fields_wrapper.data('route-name');
      var form_data = new FormData($booking_data_form.closest('.order-quick-edit-form')[0]);
      form_data.append('order_item_id', $booking_data_form.data('order-item-id'));
      form_data.append('booking_id', $booking_data_form.data('booking-id'));
      var data = {
        action: latepoint_helper.route_action,
        route_name: route_name,
        params: latepoint_formdata_to_url_encoded_string(form_data),
        return_format: 'json'
      };

      jQuery.ajax({
        type: 'post',
        dataType : "json",
        url : latepoint_timestamped_ajaxurl(),
        data : data,
        success: (response) => {
          $custom_fields_wrapper.removeClass('os-loading');
          if(response.status === latepoint_helper.response_status.success){
            $custom_fields_wrapper.html(response.message);
            latepoint_init_input_masks($custom_fields_wrapper);
            this.init_google_places_autosuggest($custom_fields_wrapper);
          }else{
            alert("Error!");
          }
        }
      });
    })

  }


  ready(){
    jQuery(() => {
      this.init_custom_fields_conditions_form();

      let $customer_form = jQuery('.customer-form-wrapper');
      if($customer_form.length){
        this.init_file_upload_fields($customer_form);
        this.init_google_places_autosuggest($customer_form);
      }

      let thisClass = this;

      jQuery('.latepoint-content-w').on('change', 'select.os-form-block-type-select', function(){
        jQuery(this).closest('.os-form-block').find('.custom-fields-google-places-api-status, .custom-fields-select-values').hide();
        switch(jQuery(this).val()){
          case 'select':
          case 'multiselect':
            jQuery(this).closest('.os-form-block').find('.custom-fields-select-values').show();
            break;
          case 'google_address_autocomplete':
            jQuery(this).closest('.os-form-block').find('.custom-fields-google-places-api-status').show();
            break;
        }
        thisClass.init_custom_fields_default_value_field(jQuery(this));
      });

      jQuery('body').on('latepoint:initInputMasks', '.quick-order-form-w', (event) => {
        this.init_file_upload_fields(jQuery(event.target));
        this.init_google_places_autosuggest(jQuery(event.target));
      });

      jQuery('body').on('latepoint:initBookingDataForm', '.quick-order-form-w', (event) => {
        let $booking_data_form = jQuery(event.target);
        this.init_file_upload_fields($booking_data_form);
        this.init_google_places_autosuggest($booking_data_form);


        // handle change event for agent/service/location dropdown picker...
        $booking_data_form.find('.os-affects-custom-fields').on('change', (event) => {
          this.reload_conditional_custom_fields_on_booking_data_form($booking_data_form);
        });
      });

      jQuery('.os-custom-fields-w').on('change', 'select.custom-field-condition-property', (event) => {
        var $select = jQuery(event.currentTarget);
        var params = {
          property: $select.val(),
          custom_field_id: $select.closest('.os-form-block').data('os-form-block-id'),
          condition_id: $select.closest('.cf-condition').data('condition-id')
        }
        var route_name = $select.data('route');
        var data = {  action: latepoint_helper.route_action,
          route_name: route_name,
          params: params,
          return_format: 'json' }

        jQuery.ajax({
          type: 'post',
          dataType : "json",
          url : latepoint_timestamped_ajaxurl(),
          data : data,
          success: (response) => {
            if(response.status === latepoint_helper.response_status.success){
              $select.closest('.cf-condition').find('.custom-field-condition-values-w').replaceWith(response.message);
              this.init_custom_fields_conditions_form();
            }else{
              alert("Error!");
            }
          }
        });
      });
      jQuery('.os-custom-fields-w').on('click', '.cf-remove-condition', (event) => {
        if(jQuery(event.currentTarget).closest('.cf-conditions').find('.cf-condition').length  > 1){
          jQuery(event.currentTarget).closest('.cf-condition').remove();
        }else{
          alert('You need to have at least one condition if your custom field is set to be conditional.')
        }
        return false;
      });

      this.init_custom_fields_conditions_form();
    });
  }

  init_custom_fields_default_value_field($field) {
    const fieldType = $field.val();
    let $formBlock = $field.closest('.os-form-block');
    let $defaultValueField = $formBlock.find(`*[name="custom_fields[${$formBlock.data('os-form-block-id')}][value]"]`);
    let $defaultValueFieldRow = $defaultValueField.closest('.custom-fields-default-value-row');

    if (JSON.parse(latepoint_helper.custom_field_types_with_default_value).includes(fieldType)) {
      let params = {
        field_type: $field.val(),
        field_name: `custom_fields[${$formBlock.data('os-form-block-id')}][value]`,
        field_value: $defaultValueField.val()
      };
      let data = {
        action: latepoint_helper.route_action,
        route_name: latepoint_helper.custom_field_default_value_field_html_route,
        params: params,
        return_format: 'json'
      };
      jQuery.ajax({
        type: 'post',
        dataType : "json",
        url : latepoint_timestamped_ajaxurl(),
        data : data,
        success: (response) => {
          if(response.status === latepoint_helper.response_status.success){
            $defaultValueField.closest('.os-form-group').replaceWith(response.message);
            $defaultValueFieldRow.show();
            latepoint_init_input_masks($formBlock);
          }else{
            $defaultValueField.val('');
            $defaultValueFieldRow.hide();
            alert(response.message);
          }
        }
      });
    } else {
      $defaultValueField.val('');
      $defaultValueFieldRow.hide();
    }
  }
}
window.latepointCustomFieldsAdminAddon = new LatepointCustomFieldsAdminAddon();

/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

class LatepointInvoicesAdminFeature {

    // Init
    constructor() {
        this.ready();
    }

    ready() {
        jQuery(() => {
            jQuery('body').on('latepoint:initOrderEditForm', (e) => {
                let $order_form = jQuery(e.currentTarget);
                return this.init_invoices_on_order_form($order_form);
            });
        });
    }

    async change_invoice_status(){
        let $invoice_document = jQuery('.invoice-document');
            $invoice_document.addClass('os-loading');
            let invoice_id = $invoice_document.data('invoice-id');
            let route = $invoice_document.data('route');
            let data = {
                action: 'latepoint_route_call',
                route_name: route,
                params: {
                    invoice_id: invoice_id,
                    status: jQuery('.invoice-change-status-selector').val()
                },
                return_format: 'json'
            }
            try {

                let response = await jQuery.ajax({
                    type: "post",
                    dataType: "json",
                    url: latepoint_timestamped_ajaxurl(),
                    data: data
                });
                $invoice_document.removeClass('os-loading');
                if (response.status === "success") {
                    jQuery('.invoice-document').replaceWith(response.message);
                    this.init_invoice_preview();
                    this.reload_invoice_tile(invoice_id);
                    return true;
                } else {
                    alert(response.message, 'error');
                    throw new Error(response.message);
                }
            } catch (e) {
                throw e;
            }
    }

    init_quick_invoice_settings_form(){
        jQuery('.invoices-info-w').addClass('setting-new-invoice');
        jQuery('.invoice-settings-wrapper')[0].scrollIntoView();
        latepoint_init_input_masks(jQuery('.invoice-settings-wrapper'));
        latepoint_init_daterangepicker(jQuery('.invoice-settings-wrapper .os-date-range-picker'));

        jQuery('.invoice-settings-close').on('click', (e) => {
            jQuery('.quick-invoice-settings-form-wrapper').html('');
            jQuery('.invoices-info-w').removeClass('setting-new-invoice');
            return false;
        });

        jQuery('.create-invoice-button').on('click', async (e) => {
            e.preventDefault();
            let $button = jQuery(e.currentTarget);
            if($button.hasClass('os-loading')) return false;

            let $invoice_settings_form = jQuery('.invoice-settings-wrapper');
            let data = new FormData();
            data.append('params', $invoice_settings_form.find('select, textarea, input').serialize());
            data.append('action', latepoint_helper.route_action);
            data.append('route_name', $invoice_settings_form.data('route'));
            data.append('return_format', 'json');


            $button.addClass('os-loading');
            try {

                let response = await jQuery.ajax({
                    type: "post",
                    dataType: "json",
                    url: latepoint_timestamped_ajaxurl(),
                    data: latepoint_formdata_to_url_encoded_string(data),
                });
                if (response.status === "success") {
                    $button.removeClass('os-loading');
                    if (response.status === "success") {
                        jQuery('.quick-invoice-settings-form-wrapper').html('');
                        jQuery('.invoices-info-w .list-of-invoices').append(response.message);
                    } else {
                        alert(response.message, 'error');
                    }
                    jQuery('.invoices-info-w').removeClass('setting-new-invoice');
                    return true;
                } else {
                    alert('Error');
                    throw new Error(response.message);
                }
            } catch (e) {
                throw e;
            }
        });
    }


    init_invoice_data_form(){
        latepoint_init_input_masks(jQuery('.invoice-data-form'));
        latepoint_init_daterangepicker(jQuery('.invoice-data-form .os-date-range-picker'));
    }

    init_email_invoice_form(){
      let $form = jQuery('.email-invoice-form');
    }

    init_invoice_data_updated(data){
        if(data.status == 'success'){
            latepoint_lightbox_close();
            this.init_invoice_preview();
            let $invoice_document = jQuery('.invoice-document');
            let invoice_id = $invoice_document.data('invoice-id');
            this.reload_invoice_tile(invoice_id);
        }else{
            alert(data.message);
        }
    }


    init_invoice_preview() {
        jQuery('.invoice-change-status-selector').on('change', async (e) => {
            return await this.change_invoice_status();
        })
    }

    async reload_invoice_tile(invoice_id){
        let $invoice_element = jQuery('.list-of-invoices .os-invoice-wrapper[data-invoice-id="'+invoice_id+'"]');

        $invoice_element.addClass('os-loading');
        let route = $invoice_element.data('reload-tile-route');
        let data = {
            action: 'latepoint_route_call',
            route_name: route,
            params: {
                invoice_id: invoice_id
            },
            return_format: 'json'
        }
        try {

            let response = await jQuery.ajax({
                type: "post",
                dataType: "json",
                url: latepoint_timestamped_ajaxurl(),
                data: data
            });
            if (response.status === "success") {
                $invoice_element.removeClass('os-loading');
                if (response.status === "success") {
                    let was_selected = $invoice_element.hasClass('selected');
                    $invoice_element.replaceWith(response.message);
                    if(was_selected){
                        let $invoice_element = jQuery('.list-of-invoices .os-invoice-wrapper[data-invoice-id="'+invoice_id+'"]');
                        $invoice_element.addClass('selected');
                    }
                } else {
                    alert(response.message, 'error');
                }
                return true;
            } else {
                throw new Error(response.message);
            }
        } catch (e) {
            throw e;
        }
    }

    async init_invoices_on_order_form($order_form) {
        $order_form.on('click', '.os-invoice-wrapper', async (e) => {
            jQuery('.os-invoice-wrapper.selected').removeClass('selected');
            let $invoice_element = jQuery(e.currentTarget);

            $invoice_element.addClass('os-loading');
            let route = $invoice_element.data('route');
            let data = {
                action: 'latepoint_route_call',
                route_name: route,
                params: {
                    id: $invoice_element.data('invoice-id')
                },
                return_format: 'json'
            }
            try {

                let response = await jQuery.ajax({
                    type: "post",
                    dataType: "json",
                    url: latepoint_timestamped_ajaxurl(),
                    data: data
                });
                if (response.status === "success") {
                    $invoice_element.removeClass('os-loading').addClass('selected');
                    if (response.status === "success") {
                        latepoint_display_in_side_sub_panel(response.message);
                        jQuery('body').addClass('has-side-sub-panel');
                        jQuery('.latepoint-deselect-invoice-trigger').on('click', (e) => {
                            e.preventDefault();
                            jQuery('.os-invoice-wrapper.selected').removeClass('selected');
                        })
                        this.init_invoice_preview();
                    } else {
                        alert(response.message, 'error');
                    }
                    return true;
                } else {
                    throw new Error(response.message);
                }
            } catch (e) {
                throw e;
            }
        });
    }


}

window.latepointInvoicesAdminFeature = new LatepointInvoicesAdminFeature();


/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

// @codekit-prepend "_messages-admin.js";
// @codekit-prepend "_webhooks-admin.js";
// @codekit-prepend "_service-extras-admin.js";
// @codekit-prepend "_role-manager-admin.js";
// @codekit-prepend "_taxes-admin.js";
// @codekit-prepend "_coupons-admin.js";
// @codekit-prepend "_custom-fields-admin.js";
// @codekit-prepend "_invoices-admin.js";