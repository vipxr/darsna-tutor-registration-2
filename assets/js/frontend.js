/**
 * Frontend JavaScript for Darsna Tutor Registration
 * 
 * @package Darsna_Tutor_Registration
 * @since 4.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Tutor Registration Handler
     */
    const TutorRegistration = {
        
        /**
         * Initialize the module
         */
        init: function() {
            this.bindEvents();
            this.initializeFields();
            this.setupValidation();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Schedule day toggles
            $(document).on('change', '.day-checkbox input[type="checkbox"]', this.handleDayToggle);
            
            // Time validation
            $(document).on('change', '#schedule_start, #schedule_end', this.validateTimeRange);
            
            // Service selection
            $(document).on('change', '#tutor_service', this.handleServiceChange);
            
            // Rate selection
            $(document).on('change', '#tutor_rate', this.handleRateChange);
            
            // Form submission
            $(document).on('submit', 'form.checkout', this.handleFormSubmit);
            
            // Real-time validation
            $(document).on('blur', '#tutor-registration-fields input, #tutor-registration-fields select, #tutor-registration-fields textarea', this.validateField);
        },
        
        /**
         * Initialize field states
         */
        initializeFields: function() {
            // Set default schedule if none selected
            const checkedDays = $('.day-checkbox input[type="checkbox"]:checked');
            if (checkedDays.length === 0) {
                // Select default days (Mon, Tue, Wed, Thu, Sun)
                const defaultDays = ['mon', 'tue', 'wed', 'thu', 'sun'];
                defaultDays.forEach(function(day) {
                    $(`input[value="${day}"]`).prop('checked', true);
                });
            }
            
            // Set default times if empty
            if (!$('#schedule_start').val()) {
                $('#schedule_start').val('09:00');
            }
            if (!$('#schedule_end').val()) {
                $('#schedule_end').val('17:00');
            }
            
            this.updateScheduleDisplay();
        },
        
        /**
         * Setup form validation
         */
        setupValidation: function() {
            // Add required attributes
            $('#tutor_service, #tutor_rate').attr('required', true);
            
            // Custom validation messages
            $('#tutor_service')[0].setCustomValidity('');
            $('#tutor_rate')[0].setCustomValidity('');
        },
        
        /**
         * Handle day checkbox toggle
         */
        handleDayToggle: function() {
            TutorRegistration.updateScheduleDisplay();
            TutorRegistration.validateScheduleDays();
        },
        
        /**
         * Update schedule display
         */
        updateScheduleDisplay: function() {
            const checkedDays = $('.day-checkbox input[type="checkbox"]:checked');
            const timeInputs = $('.schedule-times');
            
            if (checkedDays.length > 0) {
                timeInputs.show();
            } else {
                timeInputs.hide();
            }
        },
        
        /**
         * Validate time range
         */
        validateTimeRange: function() {
            const startTime = $('#schedule_start').val();
            const endTime = $('#schedule_end').val();
            
            if (startTime && endTime) {
                const start = new Date(`2000-01-01 ${startTime}`);
                const end = new Date(`2000-01-01 ${endTime}`);
                
                if (start >= end) {
                    $('#schedule_end')[0].setCustomValidity('End time must be after start time');
                    TutorRegistration.showFieldError('#schedule_end', 'End time must be after start time');
                } else {
                    $('#schedule_end')[0].setCustomValidity('');
                    TutorRegistration.clearFieldError('#schedule_end');
                }
            }
        },
        
        /**
         * Validate schedule days
         */
        validateScheduleDays: function() {
            const checkedDays = $('.day-checkbox input[type="checkbox"]:checked');
            const daysContainer = $('.schedule-days');
            
            if (checkedDays.length === 0) {
                TutorRegistration.showFieldError('.schedule-days', 'Please select at least one available day');
            } else {
                TutorRegistration.clearFieldError('.schedule-days');
            }
        },
        
        /**
         * Handle service selection change
         */
        handleServiceChange: function() {
            const serviceId = $(this).val();
            
            if (serviceId) {
                TutorRegistration.clearFieldError('#tutor_service');
                // You can add additional logic here, like updating rates based on service
            } else {
                TutorRegistration.showFieldError('#tutor_service', 'Please select a teaching subject');
            }
        },
        
        /**
         * Handle rate selection change
         */
        handleRateChange: function() {
            const rate = $(this).val();
            
            if (rate) {
                TutorRegistration.clearFieldError('#tutor_rate');
                // Update any dynamic pricing displays
                TutorRegistration.updatePricingDisplay(rate);
            } else {
                TutorRegistration.showFieldError('#tutor_rate', 'Please select your hourly rate');
            }
        },
        
        /**
         * Update pricing display
         */
        updatePricingDisplay: function(rate) {
            // You can add logic here to show pricing information
            console.log('Selected rate:', rate);
        },
        
        /**
         * Validate individual field
         */
        validateField: function() {
            const field = $(this);
            const fieldId = field.attr('id');
            
            switch (fieldId) {
                case 'tutor_service':
                    if (!field.val()) {
                        TutorRegistration.showFieldError(field, 'Please select a teaching subject');
                    } else {
                        TutorRegistration.clearFieldError(field);
                    }
                    break;
                    
                case 'tutor_rate':
                    if (!field.val()) {
                        TutorRegistration.showFieldError(field, 'Please select your hourly rate');
                    } else {
                        TutorRegistration.clearFieldError(field);
                    }
                    break;
                    
                case 'schedule_start':
                case 'schedule_end':
                    TutorRegistration.validateTimeRange();
                    break;
            }
        },
        
        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            let isValid = true;
            
            // Validate required fields
            const requiredFields = ['#tutor_service', '#tutor_rate'];
            requiredFields.forEach(function(selector) {
                const field = $(selector);
                if (!field.val()) {
                    TutorRegistration.showFieldError(field, 'This field is required');
                    isValid = false;
                }
            });
            
            // Validate schedule days
            const checkedDays = $('.day-checkbox input[type="checkbox"]:checked');
            if (checkedDays.length === 0) {
                TutorRegistration.showFieldError('.schedule-days', 'Please select at least one available day');
                isValid = false;
            }
            
            // Validate time range
            const startTime = $('#schedule_start').val();
            const endTime = $('#schedule_end').val();
            
            if (!startTime || !endTime) {
                TutorRegistration.showFieldError('.schedule-times', 'Please set your availability hours');
                isValid = false;
            } else {
                const start = new Date(`2000-01-01 ${startTime}`);
                const end = new Date(`2000-01-01 ${endTime}`);
                
                if (start >= end) {
                    TutorRegistration.showFieldError('.schedule-times', 'End time must be after start time');
                    isValid = false;
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                TutorRegistration.scrollToFirstError();
                return false;
            }
            
            // Show loading state
            TutorRegistration.showLoadingState();
        },
        
        /**
         * Show field error
         */
        showFieldError: function(field, message) {
            const $field = $(field);
            const $container = $field.closest('.form-row, .schedule-days, .schedule-times');
            
            // Remove existing error
            $container.find('.field-error').remove();
            
            // Add error message
            $container.append(`<div class="field-error" style="color: #e74c3c; font-size: 12px; margin-top: 5px;">${message}</div>`);
            
            // Add error styling
            $field.css('border-color', '#e74c3c');
        },
        
        /**
         * Clear field error
         */
        clearFieldError: function(field) {
            const $field = $(field);
            const $container = $field.closest('.form-row, .schedule-days, .schedule-times');
            
            // Remove error message
            $container.find('.field-error').remove();
            
            // Remove error styling
            $field.css('border-color', '');
        },
        
        /**
         * Scroll to first error
         */
        scrollToFirstError: function() {
            const firstError = $('.field-error').first();
            if (firstError.length) {
                $('html, body').animate({
                    scrollTop: firstError.offset().top - 100
                }, 500);
            }
        },
        
        /**
         * Show loading state
         */
        showLoadingState: function() {
            $('#tutor-registration-fields').addClass('tutor-loading');
            $('input[type="submit"]').prop('disabled', true).val('Processing...');
        },
        
        /**
         * Hide loading state
         */
        hideLoadingState: function() {
            $('#tutor-registration-fields').removeClass('tutor-loading');
            $('input[type="submit"]').prop('disabled', false).val('Place Order');
        }
    };
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Only initialize on checkout page
        if ($('#tutor-registration-fields').length) {
            TutorRegistration.init();
        }
    });
    
    /**
     * Handle AJAX errors
     */
    $(document).ajaxError(function() {
        TutorRegistration.hideLoadingState();
    });
    
    /**
     * Handle AJAX success
     */
    $(document).ajaxSuccess(function() {
        // Re-initialize if the checkout form is updated
        if ($('#tutor-registration-fields').length) {
            TutorRegistration.initializeFields();
        }
    });
    
})(jQuery);