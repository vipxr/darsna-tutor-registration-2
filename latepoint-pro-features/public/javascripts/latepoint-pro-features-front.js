( function( $ ) {
  "use strict";

  function latepoint_init_booking_messages_file_upload(){

    // UPLOAD/REMOVE IMAGE LINK LOGIC
    $('.latepoint-chat-box-w').on( 'click', '.os-bm-upload-file-btn', function( event ){
      var frame;
      var $input = $(this);
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
        $('.booking-messages-list').append('<div class="os-booking-message-attachment-w os-bm-customer"><div class="os-booking-message-attachment"><i class="latepoint-icon latepoint-icon-paperclip"></i><span>' + attachment.filename + '</span></div><div class="os-bm-info-w"><div class="os-bm-avatar" style="background-image:url('+ avatar_url +');"></div><div class="os-bm-date">'+ latepoint_helper.string_today + '</div></div></div>').scrollTop($('.booking-messages-list')[0].scrollHeight);


        var params = { message: {
                          content: attachment.id, 
                          content_type: 'attachment',
                          author_type: $wrapper.data('author-type'),
                          booking_id: $wrapper.data('booking-id') 
                        }
                      };
        var data = { action: 'latepoint_route_call', route_name: $wrapper.data('route'), params: params, return_format: 'json' } 

        $.ajax({
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
      var params = { message: {
                      content: message_content, 
                      author_type: $wrapper.data('author-type'),
                      booking_id: $wrapper.data('booking-id') }
                    };
      var data = { action: 'latepoint_route_call', route_name: $wrapper.data('route'), params: params, return_format: 'json' } 
      $wrapper.find('.latepoint-btn').addClass('os-loading');
      $('.booking-messages-list').find('.os-bm-no-messages').remove();
      var avatar_url = $wrapper.data('avatar-url');
      $('.booking-messages-list').append('<div class="os-booking-message-w os-bm-customer"><div class="os-booking-message">' + message_content + '</div><div class="os-bm-info-w"><div class="os-bm-avatar" style="background-image:url('+ avatar_url +');"></div><div class="os-bm-date">'+ latepoint_helper.string_today + '</div></div></div>');
      latepoint_messages_scroll_chat();

      $input.val('');
      $.ajax({
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

  function latepoint_messages_scroll_chat(){
    jQuery('.booking-messages-list').scrollTop(jQuery('.booking-messages-list')[0].scrollHeight);
  }

  function latepoint_reload_chat_messages(booking_id, show_loading){
    var $chatbox = $('.latepoint-chat-box-w');
    if(!$chatbox.length) return false;
    if(show_loading) $chatbox.addClass('os-loading');
    var data = {
      action: 'latepoint_route_call',
      route_name: $chatbox.data('route'),
      params: {
        booking_id: booking_id,
        viewer_user_type: 'customer'
      },
      return_format: 'json'
    }
    $.ajax({
      type : "post",
      dataType : "json",
      url : latepoint_timestamped_ajaxurl(),
      data : data,
      success: function(response){
        if(show_loading) $chatbox.removeClass('os-loading');
        if(response.status === "success"){
          $chatbox.find('.booking-messages-list').html(response.message);
          latepoint_messages_scroll_chat();
          $('.os-booking-messages-input-w').data('booking-id', booking_id);
        }else{
          alert(response.message);
        }
      }
    });
  }

  function latepoint_init_booking_messages_chat_box(){
    $('.lc-conversation').on('click', function(){
      var booking_id = $(this).data('booking-id');
      $('.lc-conversation.lc-selected').removeClass('lc-selected');
      $(this).addClass('lc-selected');
      latepoint_reload_chat_messages(booking_id, true);
      return false;
    });

    clearInterval(latepoint_helper.latepoint_message_refresh_timer);
    if($('.latepoint-chat-box-w').length && $('.lc-conversation').length){
      latepoint_helper.latepoint_message_refresh_timer = setInterval(function(){
        if (!document.hidden) {
          var route = $('.latepoint-chat-box-w').data('check-unread-route');
          var data = { action: 'latepoint_route_call', route_name: route, params: {booking_id: $('.lc-conversation.lc-selected').data('booking-id'), viewer_user_type: 'customer'}, return_format: 'json' } 
          $.ajax({
            type : "post",
            dataType : "json",
            url : latepoint_timestamped_ajaxurl(),
            data : data,
            success: function(response){
              if(response.status === "success"){
                if(response.message == 'yes'){
                  latepoint_reload_chat_messages($('.lc-conversation.lc-selected').data('booking-id'), false);
                }
              }
            }
          });
        }
      }, 3000);
    }

    $('.os-bm-send-btn').on('click', function(event){
      var $wrapper = $(this).closest('.os-booking-messages-input-w');
      latepoint_send_booking_message($wrapper);
      return false;
    });
    // INPUT TEXT BOX
    $('.os-booking-messages-input').on('keyup', function(event){
      var $input = $(this);
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
  }



  // DOCUMENT READY
  $( function() {
    latepoint_init_booking_messages_chat_box();
    $('.latepoint-trigger-messages-tab').on('click', function(){
      latepoint_reload_chat_messages($('.lc-conversation.lc-selected').data('booking-id'), false);
    });
  });


} )( jQuery );

/*
 * Copyright (c) 2022 LatePoint LLC. All rights reserved.
 */

class LatepointCustomFieldsFrontAddon {

  // Init
  constructor() {
    this.ready();
  }

  init_google_places_autosuggest($wrapper){
    if($wrapper.find('.latepoint-google-places-autocomplete').length){
      if(typeof google !== 'undefined'){
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
        console.error('Error loading Google API library');
      }
    }
  }

  init_file_upload_fields($wrapper){
    $wrapper.find('.os-form-file-upload-group').each(function() {
      // do nothing if already initialized
      if (jQuery(this).hasClass('os-initialized')) return true;
      jQuery(this).on('click', '.os-uploaded-file-info', function () {
        if (!jQuery(this).hasClass('is-uploaded')) return false;
      });
      // click on remove file button
      jQuery(this).on('click', '.uf-remove', function () {
        var $file_info = jQuery(this).closest('.os-form-group').find('.os-uploaded-file-info');
        var $file_input = jQuery(this).closest('.os-form-group').find('input[type="file"]');
        if ($file_input.hasClass('required') && $file_info.has('is-uploaded')) {
          // file input is required and file was uploaded before, we can't clear it unless they pick another file to replace currento one
          if (confirm(latepoint_helper.custom_fields_remove_required_file_prompt)) $file_input.trigger('click');
        } else {
          if ($file_info.hasClass('is-uploaded')) {
            // file was uploaded before/ remove it from model and remove the hidden field that was carrying the url value
            if (!confirm(latepoint_helper.custom_fields_remove_file_prompt)) return false;
            var route_name = $file_info.closest('.os-form-group').find('input[type="file"]').data('route-name');
            var params = $file_info.closest('.os-form-group').find('input[type="file"]').data('params');
            var data = {
              action: latepoint_helper.route_action,
              route_name: route_name,
              params: params,
              return_format: 'json'
            }
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
      jQuery(this).on('change', 'input[type="file"]', function () {
        if (this.files.length) {
          jQuery(this).closest('.os-form-group').find('.os-uploaded-file-info').show().attr('href', '#').attr('target', '_self').find('.uf-name').text(this.files[0].name);
          jQuery(this).closest('.os-form-group').find('.os-upload-file-input-w').hide();
        } else {
          jQuery(this).closest('.os-form-group').find('.os-uploaded-file-info').hide().removeClass('is-uploaded');
          jQuery(this).closest('.os-form-group').find('.os-upload-file-input-w').show();
        }
      });
    });
  }

  ready(){
    jQuery(document).ready(() => {
      let $customer_cabinet = jQuery('.tab-content-customer-info-form');
      if($customer_cabinet.length){
        this.init_file_upload_fields($customer_cabinet);
        this.init_google_places_autosuggest($customer_cabinet);
      }
      // init custom fields, this is triggered when custom fields step for boooking or customer is initialised
      jQuery('body').on('latepoint:initStep', '.latepoint-booking-form-element', (e, data) => {
        var $step_content = jQuery('.latepoint-step-content[data-step-code="' + data.step_code + '"]');
        this.init_file_upload_fields($step_content);
        this.init_google_places_autosuggest($step_content);
        latepoint_init_form_masks();
			});
    });
  }
}


window.latepointCustomFieldsFrontAddon = new LatepointCustomFieldsFrontAddon();

/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */
class LatepointGroupBookingsFrontFeature {

  // Init
  constructor() {
    this.ready();
  }

  init_total_attendees_selector($booking_form_element) {

  }

  ready(){
    jQuery(document).ready(() => {
      jQuery('body').on('latepoint:initStep:booking__group_bookings', '.latepoint-booking-form-element', (e, data) => {
        let $step_content = jQuery('.latepoint-step-content[data-step-code="booking__group_bookings"]');

        $step_content.on('change', '.total-attendees-selector-input', function () {
          let max_capacity = jQuery(this).closest('.total-attendees-selector-w').data('max-capacity');
          let min_capacity = jQuery(this).closest('.total-attendees-selector-w').data('min-capacity');
          let new_value = jQuery(this).val();
          new_value = Math.min(Number(max_capacity), Number(new_value));
          new_value = Math.max(Number(min_capacity), Number(new_value));
          jQuery(this).val(new_value);
          let new_value_formatted = new_value + ' ' + ((new_value > 1) ? jQuery(this).data('summary-plural') : jQuery(this).data('summary-singular'));

          let $booking_form_element = jQuery(this).closest('.latepoint-booking-form-element');
          latepoint_reload_summary($booking_form_element);
        });
        $step_content.on('click', '.total-attendees-selector', function () {
          let add_value = (jQuery(this).hasClass('total-attendees-selector-plus')) ? 1 : -1;
          let max_capacity = jQuery(this).closest('.total-attendees-selector-w').data('max-capacity');
          let min_capacity = jQuery(this).closest('.total-attendees-selector-w').data('min-capacity');
          let current_value = jQuery(this).closest('.total-attendees-selector-w').find('input.total-attendees-selector-input').val();
          let new_value = (Number(current_value) > 0) ? Math.max((Number(current_value) + add_value), 1) : 1;
          new_value = Math.min(Number(max_capacity), new_value);
          new_value = Math.max(Number(min_capacity), new_value);
          jQuery(this).closest('.total-attendees-selector-w').find('input').val(new_value).trigger('change');
          return false;
        });
			});
    });
  }
}


window.latepointGroupBookingsFrontFeature = new LatepointGroupBookingsFrontFeature();

/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */
class LatepointRecurringBookingsFrontFeature {

    // Init
    constructor() {
        this.ready();
    }

    ready() {
        jQuery(document).ready(() => {
            jQuery('body').on('latepoint:initStep:booking__recurring_bookings', '.latepoint-booking-form-element', (e, data) => {
                let $booking_form_element = jQuery(e.target);
                let $step_content = $booking_form_element.find('.latepoint-step-content[data-step-code="booking__recurring_bookings"]');
                this.init_recurrence_rules($step_content.find('.os-recurrence-rules'));

                return this.preview_recurring_bookings($booking_form_element);
            });
            jQuery('body').on('latepoint:initBookingForm', '.latepoint-booking-form-element', (e, data) => {
                let $booking_form_element = jQuery(e.target);
                $booking_form_element.on('click', '.os-recurring-bookings-unfold', (e) => {
                   e.preventDefault();
                   jQuery(e.currentTarget).closest('.cart-item-wrapper').toggleClass('show-all-recurring-bookings');
                });
                return true;
            });
        });
    }

    init_recurrence_rules($recurrence_rules) {
        let $step_content = $recurrence_rules.closest('.latepoint-step-content');
        let $booking_form_element = $recurrence_rules.closest('.latepoint-booking-form-element');

        $recurrence_rules.on('change', 'select, input', (e) => {
            $booking_form_element.find('.os-recurrence-rules input[name="recurrence[rules][changed]"]').val('yes');
            return this.preview_recurring_bookings($booking_form_element);
        });

        $recurrence_rules.on('change', 'select[name="recurrence[rules][repeat_end_operator]"]', (e) => {
            let $select = jQuery(e.currentTarget);
            $recurrence_rules.attr('data-ends', $select.val());
        });

        $recurrence_rules.on('change', 'select[name="recurrence[rules][repeat_unit]"]', (e) => {
            let $select = jQuery(e.currentTarget);
            $recurrence_rules.attr('data-repeat-unit', $select.val());
        });

        $recurrence_rules.on('click', '.os-end-recurrence-datetime-picker', async (e) => {
            let $trigger = jQuery(e.currentTarget);
            $trigger.addClass('os-loading');
            let $booking_form_element = $trigger.closest('.latepoint-booking-form-element');
            $booking_form_element.find('.latepoint-footer').addClass('force-hide');


            let data = new FormData();
            data.append('params', jQuery.param({preselected_day: $trigger.data('preselected-day')}));
            data.append('action', latepoint_helper.route_action);
            data.append('route_name', $trigger.data('route-name'));
            data.append('return_format', 'json');

            try {
                let response = await jQuery.ajax({
                    type: "post",
                    dataType: "json",
                    processData: false,
                    contentType: false,
                    url: latepoint_timestamped_ajaxurl(),
                    data: data
                });
                if (response.status == 'success') {
                    $step_content.addClass('show-datepicker').find('.os-recurrence-datepicker-wrapper').html(response.message);
                    this.init_calendar_navigation($step_content.find('.os-recurrence-datepicker-wrapper .os-dates-and-times-w'));
                    let $day = $step_content.find('.os-recurrence-datepicker-wrapper .os-day[data-date="' + $trigger.data('preselected-day') + '"]');

                    $day.addClass('selected');
                    $trigger.removeClass('os-loading');
                } else {
                    $booking_form_element.find('.latepoint-footer').removeClass('force-hide');
                    throw new Error(response.message);
                }
            } catch (e) {
                console.log(e);
                throw (e);
            }
        });

        $recurrence_rules.on('click', '.os-start-recurrence-datetime-picker', async (e) => {
            e.preventDefault();
            let $trigger = jQuery(e.currentTarget);
            return this.load_datetime_picker($trigger, $trigger.data('start-datetime-utc'));
        });

        $recurrence_rules.on('click', '.os-recurrence-weekdays .weekday', (e) => {
            let $weekday = jQuery(e.currentTarget);
            let total_selected = $weekday.closest('.os-recurrence-weekdays').find('.os-weekday-selected').length;

            if ($weekday.hasClass('os-weekday-selected') && total_selected > 1) {
                $weekday.removeClass('os-weekday-selected');
            } else {
                $weekday.addClass('os-weekday-selected');
            }
            $weekday.closest('.os-recurrence-weekdays').find('input[name="recurrence[rules][repeat_on_weekdays]"]').val($weekday.closest('.os-recurrence-weekdays').find('.os-weekday-selected').map(function () {
                return jQuery(this).data('weekday');
            }).get().join(',')).trigger('change');
            return false;
        });
    }

    async load_datetime_picker($trigger, preselected_datetime_utc){
        $trigger.addClass('os-loading');
        let $booking_form_element = $trigger.closest('.latepoint-booking-form-element');
        $booking_form_element.removeClass('step-content-loaded').addClass('step-content-loading');
        let $booking_form = $booking_form_element.find('form.latepoint-form');
        let $recurring_step_content = $booking_form_element.find('.step-recurring-bookings-w.latepoint-step-content');
        $booking_form_element.find('.latepoint-footer').addClass('force-hide');


        try {
            let response = await jQuery.ajax({
                type: "post",
                dataType: "json",
                processData: false,
                contentType: false,
                url: latepoint_timestamped_ajaxurl(),
                data: latepoint_create_form_data($booking_form, latepoint_helper.pick_datetime_on_calendar_route, { preselected_datetime_utc: preselected_datetime_utc }),
            });
            if (response.status == 'success') {
                $recurring_step_content.addClass('show-datepicker').find('.os-recurrence-datepicker-wrapper').html(response.message);
                this.init_calendar_navigation($recurring_step_content.find('.os-recurrence-datepicker-wrapper .os-dates-and-times-w'));
                let $day = $recurring_step_content.find('.os-recurrence-datepicker-wrapper .os-day.selected');
                latepoint_generate_day_timeslots($day);
                $booking_form_element.removeClass('step-content-loading').addClass('step-content-mid-loading');
                setTimeout(function () {
                    $booking_form_element.removeClass('step-content-mid-loading').addClass('step-content-loaded');
                    $recurring_step_content.find('.time-selector-w')[0].scrollIntoView({block: "nearest", behavior: 'smooth'});
                }, 50);

                $trigger.removeClass('os-loading');
            } else {
                $booking_form_element.find('.latepoint-footer').removeClass('force-hide');
                throw new Error(response.message);
            }
        } catch (e) {
            console.log(e);
            throw (e);
        }
    }

    init_calendar_navigation($calendar) {
        $calendar.find('.os-month-next-btn').on('click', async () => {
            return this.calendar_load_new_month($calendar, 'next');
        });
        $calendar.find('.os-month-prev-btn').on('click', async () => {
            return this.calendar_load_new_month($calendar, 'prev');
        });
    }


    calendar_set_month_label($calendar) {
        $calendar.find('.os-current-month-label .current-year').text($calendar.find('.os-monthly-calendar-days-w.active').data('calendar-year'));
        $calendar.find('.os-current-month-label .current-month').text($calendar.find('.os-monthly-calendar-days-w.active').data('calendar-month-label'));
    }


    async calendar_load_new_month($calendar, direction = 'next') {
        try {
            let $active_month = $calendar.find('.os-monthly-calendar-days-w.active');
            let $month_to_load = (direction === 'next') ? $active_month.next('.os-monthly-calendar-days-w') : $active_month.prev('.os-monthly-calendar-days-w');

            if ($month_to_load.length) {
                $active_month.removeClass('active');
                $month_to_load.addClass('active');
                if(direction === 'next') {
                    $calendar.find('.os-month-prev-btn').removeClass('disabled');
                }
                this.calendar_set_month_label($calendar);
                return true;
            } else {
                let $btn = (direction === 'next') ? $calendar.find('.os-month-next-btn') : $calendar.find('.os-month-prev-btn');
                let next_month_route_name = $btn.data('route');
                $btn.addClass('os-loading');
                let calendar_year = $active_month.data('calendar-year');
                let calendar_month = $active_month.data('calendar-month');
                if(direction === 'next') {
                    if (calendar_month == 12) {
                        calendar_year = calendar_year + 1;
                        calendar_month = 1;
                    } else {
                        calendar_month = calendar_month + 1;
                    }
                }else{
                    if (calendar_month == 1) {
                        calendar_year = calendar_year - 1;
                        calendar_month = 12;
                    } else {
                        calendar_month = calendar_month - 1;
                    }
                }
                let form_data = new FormData($calendar.closest('.latepoint-form')[0]);
                form_data.set('target_date_string', `${calendar_year}-${calendar_month}-1`);
                let params = latepoint_formdata_to_url_encoded_string(form_data);
                let data = {
                    action: latepoint_helper.route_action,
                    route_name: next_month_route_name,
                    params: params,
                    layout: 'none',
                    return_format: 'json'
                }
                let response = await jQuery.ajax({
                    type: "post",
                    dataType: "json",
                    url: latepoint_timestamped_ajaxurl(),
                    data: data
                });
                $btn.removeClass('os-loading');
                if (response.status === "success") {
                    if(direction === 'next'){
                        $calendar.find('.os-months').append(response.message);
                        $active_month.removeClass('active').next('.os-monthly-calendar-days-w').addClass('active');
                    }else{
                        $calendar.find('.os-months').prepend(response.message);
                        $active_month.removeClass('active').prev('.os-monthly-calendar-days-w').addClass('active');
                    }
                    this.calendar_set_month_label($calendar);
                    return true;
                } else {
                    console.log(response.message);
                    return false;
                }

            }
        } catch (e) {
            console.log(e);
            alert('Error:' + e);
            return false;
        }
    }



    init_recurring_bookings_preview($booking_form_element) {
        $booking_form_element.find('.recurring-bookings-preview-continue-btn').on('click', function (e){
            e.preventDefault();
            jQuery(this).closest('.latepoint-w').removeClass('show-summary-on-mobile');
            latepoint_trigger_next_btn($booking_form_element);
            return false;
        });


        $booking_form_element.find('.rb-bookings-info-link').on('click keydown', function(event) {
            event.preventDefault();

            if(event.type === 'keydown' && event.key !== ' ' &&  event.key !== 'Enter') return;
            jQuery(this).closest('.latepoint-w').toggleClass('show-summary-on-mobile');
            return false;
        });

        $booking_form_element.find('.recurring-booking-preview').on('click', '.rbp-time-edit', (e) => {
            e.preventDefault();
            let $trigger = jQuery(e.currentTarget).closest('.recurring-booking-preview');
            $trigger.closest('.recurring-bookings-preview-wrapper').find('.recurring-booking-preview.is-editing').removeClass('is-editing');
            $trigger.addClass('is-editing');
            $trigger.closest('.latepoint-w').removeClass('show-summary-on-mobile');
            return this.load_datetime_picker($trigger, $trigger.data('start-datetime-utc'));
        });

        $booking_form_element.find('.recurring-booking-preview').on('click', '.rbp-checkbox', (e) => {
            let $checkbox = jQuery(e.currentTarget);
            let $preview = $checkbox.closest('.recurring-booking-preview');
            let $recurring_bookings_fields = $checkbox.closest('.latepoint-booking-form-element').find('.os-recurrence-selection-fields-wrapper');
            if ($preview.hasClass('rbp-is-on')) {
                if($checkbox.closest('.recurring-bookings-preview-wrapper').find('.rbp-is-on').length > 1){
                    $preview.removeClass('rbp-is-on').addClass('rbp-is-off');
                    $recurring_bookings_fields.find('input[name="recurrence[overrides]['+$preview.data('stamp')+'][unchecked]"]').val('yes');
                }else{
                    alert('At least one has to be selected');
                }
            } else {
                $preview.removeClass('rbp-is-off').addClass('rbp-is-on');
                $recurring_bookings_fields.find('input[name="recurrence[overrides]['+$preview.data('stamp')+'][unchecked]"]').val('no');
            }

            $checkbox.closest('.latepoint-booking-form-element').find('.os-recurrence-rules input[name="recurrence[rules][changed]"]').val('no');
            return this.preview_recurring_bookings($checkbox.closest('.latepoint-booking-form-element'), true);
        });
    }

    async preview_recurring_bookings($booking_form_element, reload_price_only = false) {
        latepoint_hide_next_btn($booking_form_element);

        $booking_form_element.closest('.latepoint-w').removeClass('latepoint-without-summary').addClass('latepoint-with-summary').addClass('latepoint-summary-is-open');

        let $summary_panel = $booking_form_element.closest('.latepoint-with-summary');
        if (!$summary_panel.length) return;

        if(!reload_price_only) $booking_form_element.find('.latepoint-summary-w').addClass('os-loading');

        $booking_form_element.find('.recurring-bookings-preview-total-wrapper').addClass('os-loading');
        let $booking_form = $booking_form_element.find('.latepoint-form');
        let form_data = new FormData($booking_form[0]);
        let data = {
            action: latepoint_helper.route_action,
            route_name: latepoint_helper.recurring_bookings_preview_route,
            params: latepoint_formdata_to_url_encoded_string(form_data),
            layout: 'none',
            return_format: 'json'
        }

        try {
            let response = await jQuery.ajax({
                type: "post",
                dataType: "json",
                url: latepoint_timestamped_ajaxurl(),
                data: data
            });
            if (response.status === 'success') {
                if(reload_price_only){
                    $booking_form_element.find('.recurring-bookings-preview-total-wrapper').html(response.price_info).removeClass('os-loading');
                    $booking_form_element.find('.os-recurrence-preview-information').html(response.bookings_info);
                }else{
                    $booking_form_element.find('.os-summary-contents').html(response.preview);
                    $booking_form_element.find('.os-recurrence-selection-fields-wrapper').html(response.fields);
                    $booking_form_element.find('.os-recurrence-preview-information').html(response.bookings_info);
                    $booking_form_element.find('.latepoint-summary-w').removeClass('os-loading');
                    this.init_recurring_bookings_preview($booking_form_element);
                }
                latepoint_show_next_btn($booking_form_element);
                return true;
            } else {
                throw new Error(response.message ? response.message : 'Error reloading summary');
            }
        } catch (e) {
            throw e;
        }

    }

    async reload_recurrence_rules($booking_form_element, changed = true) {
        $booking_form_element.find('.os-recurrence-rules input[name="recurrence[rules][changed]"]').val(changed ? 'yes' : 'no');
        $booking_form_element.find('.latepoint-summary-w').addClass('os-loading');
        let $booking_form = $booking_form_element.find('.latepoint-form');
        let form_data = new FormData($booking_form[0]);
        let data = {
            action: latepoint_helper.route_action,
            route_name: $booking_form_element.find('.os-recurrence-rules').data('route-name'),
            params: latepoint_formdata_to_url_encoded_string(form_data),
            layout: 'none',
            return_format: 'json'
        }

        try {
            let response = await jQuery.ajax({
                type: "post",
                dataType: "json",
                url: latepoint_timestamped_ajaxurl(),
                data: data
            });
            if (response.status === 'success') {
                $booking_form_element.find('.os-recurrence-rules').replaceWith(response.message);
                $booking_form_element.find('.os-recurrence-datepicker-wrapper').html('').closest('.step-recurring-bookings-w').removeClass('show-datepicker');
                this.init_recurrence_rules($booking_form_element.find('.os-recurrence-rules'));
                $booking_form_element.find('.latepoint-footer').removeClass('force-hide');
                return await this.preview_recurring_bookings($booking_form_element);
            } else {
                throw new Error(response.message ? response.message : 'Error reloading summary');
            }
        } catch (e) {
            throw e;
        }
    }
}


window.latepointRecurringBookingsFrontFeature = new LatepointRecurringBookingsFrontFeature();

function latepoint_init_facebook_login($wrapper) {

    let is_booking_form = $wrapper.hasClass('latepoint-booking-form-element');

    if (!$wrapper.find('#facebook-signin-btn').length) return;
    $wrapper.find('#facebook-signin-btn').on('click', function () {
        FB.login(function (response) {
            if (response.status === 'connected' && response.authResponse) {
                var params = {token: response.authResponse.accessToken};
                var data = {
                    action: latepoint_helper.route_action,
                    route_name: $wrapper.find('#facebook-signin-btn').data('login-action'),
                    params: jQuery.param(params),
                    layout: 'none',
                    return_format: 'json'
                };
                if(is_booking_form) latepoint_step_content_change_start($wrapper);
                jQuery.ajax({
                    type: "post",
                    dataType: "json",
                    url: latepoint_timestamped_ajaxurl(),
                    data: data,
                    success: function (data) {
                        if(is_booking_form){
                            // booking form
                            if (data.status === "success") {
                                if(is_booking_form) latepoint_reload_step($wrapper);
                            } else {
                                latepoint_show_message_inside_element(data.message, $wrapper.find('.os-step-existing-customer-login-w '));
                                if(is_booking_form) latepoint_step_content_change_end(false, $wrapper);
                            }
                        }else{
                            // login form
                            if (data.status === "success") {
                                location.reload();
                            }else{
                                latepoint_show_message_inside_element(data.message, $wrapper);
                            }
                        }
                    }
                });
            } else {

            }
        }, {scope: 'public_profile,email'});
    });
}

function latepoint_process_google_login(response, $booking_form_element = false) {

    var params = {
        token: response.credential
    };
    var data = {
        action: latepoint_helper.route_action,
        route_name: latepoint_helper.social_login_google_route,
        params: jQuery.param(params),
        layout: 'none',
        return_format: 'json'
    };
    if ($booking_form_element) latepoint_step_content_change_start($booking_form_element);
    jQuery.ajax({
        type: "post",
        dataType: "json",
        url: latepoint_timestamped_ajaxurl(),
        data: data,
        success: function (data) {
            if (data.status === "success") {
                if ($booking_form_element) {
                    latepoint_reload_step($booking_form_element);
                } else {
                    location.reload();
                }
            } else {
                latepoint_show_message_inside_element(data.message, $booking_form_element.find('.os-step-existing-customer-login-w '));
                latepoint_step_content_change_end(false, $booking_form_element);
            }
        }
    });
}

async function latepoint_init_google_login($wrapper) {
    if (!$wrapper.find('#google-signin-btn').length || typeof google === 'undefined') return;
    var googleUser = {};

    let is_booking_form = $wrapper.hasClass('latepoint-booking-form-element');

    if(!window.latepoint_is_google_initialized){
        google.accounts.id.initialize({
            client_id: latepoint_helper.social_login_google_client_id,
            callback: (response) => is_booking_form ? latepoint_process_google_login(response, $wrapper) : latepoint_process_google_login(response)
        });
        window.latepoint_is_google_initialized = true;
    }



    google.accounts.id.renderButton(
        $wrapper.find('#google-signin-btn')[0],
        {theme: "outline", size: "medium", position: "center"}  // customization attributes
    );


}

function latepoint_init_customer_social_login() {

    if (jQuery('.latepoint-login-form-w').length){
        jQuery('.latepoint-login-form-w').each(function(){
            latepoint_init_facebook_login(jQuery(this));
            latepoint_init_google_login(jQuery(this));
        });
    }
}


jQuery(document).ready(() => {
    jQuery('body').on('latepoint:initStep:customer', '.latepoint-booking-form-element', (e, data) => {
        latepoint_init_facebook_login(jQuery(e.target));
        latepoint_init_google_login(jQuery(e.target));
    });
});

function latepoint_init_timezone_picker($booking_form_element) {


    $booking_form_element.on('change', '.latepoint_timezone_name', function (e) {
        var $field = jQuery(this);
        var data = {
            action: latepoint_helper.route_action,
            route_name: latepoint_helper.change_timezone_route,
            params: {timezone_name: jQuery(this).val()},
            layout: 'none',
            return_format: 'json'
        }
        $booking_form_element.removeClass('step-content-loaded').addClass('step-content-loading');
        jQuery.ajax({
            type: "post",
            dataType: "json",
            url: latepoint_timestamped_ajaxurl(),
            data: data,
            success: function (data) {
                $booking_form_element.removeClass('step-content-loading');
                if (data.status === "success") {
                    // reload datepicker if its the step
                    if($field.closest('.latepoint-booking-form-element').length){
                        if ($field.closest('.latepoint-booking-form-element').hasClass('current-step-booking__datepicker')) {
                            latepoint_reload_step($field.closest('.latepoint-booking-form-element'));
                        }
                    }else{
                        latepoint_reload_reschedule_calendar($field.closest('.reschedule-calendar-datepicker'));
                    }
                } else {

                }
            }
        });
    });

    if (!latepoint_helper.is_timezone_selected) {
        const tzid = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (tzid) {
            if (tzid != $booking_form_element.find('.latepoint_timezone_name').val()) $booking_form_element.find('.latepoint_timezone_name').val(tzid).trigger('change');
        }
    }

    $booking_form_element.on('click', '.os-timezone-info-value', async function (e) {
        let $trigger = jQuery(e.currentTarget);
        $trigger.addClass('os-loading');
        let $container = false;
        if($trigger.closest('.latepoint-booking-form-element').length){
            $container = $trigger.closest('.latepoint-booking-form-element');
        }else{
            $container = $trigger.closest('.reschedule-calendar-datepicker');
        }
        let route_name = $trigger.data('route');

        let response = await jQuery.ajax({
            type: "post",
            dataType: "json",
            url: latepoint_timestamped_ajaxurl(),
            data: {
                action: 'latepoint_route_call',
                route_name: route_name,
                params: {timezone_name: $container.find('.latepoint_timezone_name').val()},
                layout: 'none',
                return_format: 'json'
            }
        });

        if (response.status === "success") {
            if ($container.find('.os-timezone-selector-wrapper-with-shadow').length) {
                $container.find('.os-timezone-selector-wrapper-with-shadow').remove();
            }
            if($container.hasClass('reschedule-calendar-datepicker')){
                $container.append(response.message);
            }else{
                $container.find('.latepoint-form-w').append(response.message);
            }
            latepoint_init_timezone_picker_search($container);
            $trigger.removeClass('os-loading');
        } else {
            throw new Error(response.message);
        }
    });

}

function latepoint_init_timezone_picker_search($container) {
    let $search_input = $container.find('.os-timezones-filter-input');
    $search_input.trigger('focus');

    let $timezone_selector_wrapper = $container.find('.os-timezone-selector-wrapper-with-shadow');


    $container.find('.os-timezone-selector-close').on('click', function (e) {
        $container.find('.os-timezone-selector-wrapper-with-shadow').remove();
    });

    $timezone_selector_wrapper.on('click', '.os-timezone-selector-option ', function (e) {
        $container.find('.latepoint_timezone_name').val(jQuery(this).data('value')).trigger('change');
        $container.find('.os-timezone-selector-wrapper-with-shadow').remove();
        return false;
    });

    $search_input.on('keyup', function (e) {
        if (e.keyCode === 27) {
            // esc
            $container.find('.os-timezone-selector-wrapper-with-shadow').remove();
            return;
        }
        let searchText = jQuery(this).val().toLowerCase();
        let matchFound = false;
        if (searchText) {
            jQuery('.os-selected-timezone-info').hide();
        } else {
            jQuery('.os-selected-timezone-info').show();
        }

        // Process each timezone group
        jQuery('.os-timezone-group').each(function () {
            let groupMatchFound = false;

            // Check each timezone option in this group
            jQuery(this).find('.os-timezone-selector-option').each(function () {
                let tzValue = jQuery(this).attr('data-value') || '';
                let tzName = jQuery(this).text() || '';

                // Check if the timezone matches the search text
                if (tzValue.toLowerCase().includes(searchText) || tzName.toLowerCase().includes(searchText)) {
                    jQuery(this).show();
                    groupMatchFound = true;
                    matchFound = true;
                } else {
                    jQuery(this).hide();
                }
            });

            // Show/hide the group based on whether any matches were found
            if (groupMatchFound) {
                jQuery(this).show();
            } else {
                jQuery(this).hide();
            }
        });

        // If no matches at all, show a message
        if (!matchFound && searchText !== '') {
            if (jQuery('.os-timezone-no-matches').length === 0) {
                jQuery('.os-timezones-list').append('<div class="os-timezone-no-matches">' + jQuery('.os-timezones-filter-input').data('not-found-message') + '</div>');
            } else {
                jQuery('.os-timezone-no-matches').show();
            }
        } else {
            jQuery('.os-timezone-no-matches').hide();
        }
    })
}


jQuery(document).ready(() => {

    jQuery('body').on('latepoint:initBookingForm', '.latepoint-booking-form-element', (e) => {
        let $booking_form_element = jQuery(e.currentTarget);
        latepoint_init_timezone_picker($booking_form_element);
    });
});

/*
 * Copyright (c) 2024 LatePoint LLC. All rights reserved.
 */

// @codekit-prepend "_messages-front.js";
// @codekit-prepend "_custom-fields-front.js";
// @codekit-prepend "_group-bookings-front.js";
// @codekit-prepend "_recurring-bookings-front.js";
// @codekit-prepend "_social-front.js";
// @codekit-prepend "_timezone-front.js";

// DOCUMENT READY
jQuery(document).ready(function ($) {
    latepoint_init_customer_social_login();
});