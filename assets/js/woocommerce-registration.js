/**
 * Darsna WooCommerce Registration JavaScript
 * Version: 4.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    const DarsnaRegistration = {
        
        init: function() {
            this.bindEvents();
            this.setupInitialState();
        },

        bindEvents: function() {
            // Store form reference
            this.$form = $('.woocommerce-form-register');
            
            // User type change
            $('#user_type').on('change', this.handleUserTypeChange.bind(this));
            
            // Form validation
            this.$form.on('submit', this.validateForm.bind(this));
            
            // Real-time validation
            $('#tutor_hourly_rate').on('blur', this.validateRate.bind(this));
        },

        setupInitialState: function() {
            this.handleUserTypeChange();
            // Note: initializeSelect2() is called in handleUserTypeChange() when user selects 'tutor'
        },
        
        initializeSelect2: function() {
            const subjectSelect = $('select[name="tutor_subjects"]');
            
            // Only proceed if the element exists and is visible
            if (subjectSelect.length === 0) {
                return;
            }
            
            // Check if any tutor field is visible
            if ($('#tutor_subjects_field').hasClass('darsna-field-hidden')) {
                return;
            }
            

            
            // Initialize Select2 for subjects multiselect - require it to be available
            if (typeof $.fn.select2 === 'undefined') {
                // Select2 library is not available. Falling back to standard select.
                return; // Exit gracefully instead of throwing error
            }
            
            // Destroy existing Select2 instance if it exists
            if (subjectSelect.hasClass('select2-hidden-accessible')) {
                subjectSelect.select2('destroy');
            }
            
            subjectSelect.select2({
                placeholder: 'Select subjects you can teach...',
                allowClear: true,
                width: '100%',
                theme: 'default',
                closeOnSelect: false
            });
        },





        handleUserTypeChange: function() {
            const userType = $('#user_type').val();
            const self = this;
            
            console.log('User type changed to:', userType);
            
            // Toggle tutor fields based on user type selection
            const isTutor = userType === 'tutor';
            ['tutor_subjects','tutor_bio','tutor_hourly_rate'].forEach(function(fieldName){
                const $wrap = $('#'+fieldName+'_field');
                console.log('Field:', fieldName, 'Element found:', $wrap.length > 0, 'Is tutor:', isTutor);
                $wrap.toggleClass('darsna-field-hidden', !isTutor)
                     .toggleClass('darsna-field-visible', isTutor);
            });
            
            if (isTutor) {
                // Reinitialize Select2 after fields are visible
                setTimeout(function() {
                    self.initializeSelect2();
                }, 50);
                this.setFieldsRequired(true);
            } else {
                this.setFieldsRequired(false);
                // Destroy Select2 when hiding fields
                const subjectSelect = $('select[name="tutor_subjects"]');
                if (subjectSelect.hasClass('select2-hidden-accessible')) {
                    subjectSelect.select2('destroy');
                }
            }
        },

        setFieldsRequired: function(required) {
            const fields = ['select[name="tutor_subjects"]', '#tutor_bio', '#tutor_hourly_rate'];
            
            fields.forEach(function(selector) {
                const $field = $(selector);
                if (required) {
                    $field.attr('required', 'required').prop('required', true);
                } else {
                    $field.removeAttr('required').prop('required', false);
                }
            });
        },

        validateForm: function(e) {
            let isValid = true;
            const errors = [];
            
            // Validate standard fields for all users
            const firstName = $('#billing_first_name').val().trim();
            if (firstName.length < 2) {
                errors.push('Please enter a valid first name (at least 2 characters).');
                isValid = false;
            }
            
            const lastName = $('#billing_last_name').val().trim();
            if (lastName.length < 2) {
                errors.push('Please enter a valid last name (at least 2 characters).');
                isValid = false;
            }
            
            if ($('#billing_country').val() === '') {
                errors.push('Please select your country.');
                isValid = false;
            }
            
            const phone = $('#billing_phone').val().trim();
            if (phone === '') {
                errors.push('Please enter your phone number.');
                isValid = false;
            } else if (!/^\+.{6,}$/.test(phone)) {
                errors.push('Please enter a valid phone number with country code (e.g., +9661234567).');
                isValid = false;
            }
            
            // Validate tutor-specific fields if user type is tutor
            if ($('#user_type').val() === 'tutor') {
                // Validate subjects
                if ($('select[name="tutor_subjects"]').val() === null || $('select[name="tutor_subjects"]').val().length === 0) {
                    errors.push('Please select at least one subject.');
                    isValid = false;
                }
                
                // Validate bio
                const bio = $('#tutor_bio').val().trim();
                if (bio.length < 25) {
                    errors.push('Bio must be at least 25 characters.');
                    isValid = false;
                }
                
                // Validate rate
                const rate = parseFloat($('#tutor_hourly_rate').val());
                if (isNaN(rate) || rate < 10 || rate > 100) {
                    errors.push('Hourly rate must be between $10 and $100.');
                    isValid = false;
                }
            }
            
            // Note: General terms validation is now handled server-side for all users
            
            if (!isValid) {
                e.preventDefault();
                this.showErrors(errors);
            }
            
            return isValid;
        },

        validateRate: function() {
            const $field = $('#tutor_hourly_rate');
            const rate = parseFloat($field.val());
            
            $field.removeClass('error');
            $('.rate-error').remove();
            
            if (!isNaN(rate) && (rate < 10 || rate > 100)) {
                $field.addClass('error');
                $field.after('<span class="rate-error" style="color: red;">Rate must be between $10 and $100</span>');
            }
        },

        showErrors: function(errors) {
            $('.woocommerce-error').remove();
            
            const errorHtml = '<ul class="woocommerce-error" role="alert">' +
                errors.map(error => '<li>' + error + '</li>').join('') +
                '</ul>';
            
            this.$form.prepend(errorHtml);
            
            // Scroll to errors
            $('html, body').animate({
                scrollTop: this.$form.offset().top - 100
            }, 300);
        }
    };

    // Initialize if registration form exists
    if ($('.woocommerce-form-register').length && $('#user_type').length) {
        console.log('Darsna Registration: Initializing form');
        DarsnaRegistration.init();
    } else {
        console.log('Darsna Registration: Form or user_type field not found');
        console.log('Form exists:', $('.woocommerce-form-register').length > 0);
        console.log('User type field exists:', $('#user_type').length > 0);
    }
});