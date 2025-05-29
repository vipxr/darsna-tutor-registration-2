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
            // Multi-service interface events
            $(document).on('click', '#add-service-btn', this.addServiceRow);
            $(document).on('click', '.remove-service-btn', this.removeServiceRow);
            
            // Schedule functionality removed - handled by LatePoint
            
            $(document).on('change', '.service-select', this.handleServiceChange);
            
            // Legacy single service events (for backward compatibility)
            $(document).on('change', '#tutor_service', this.handleServiceChange);
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
            // Schedule initialization removed - handled by LatePoint
        },
        
        /**
         * Setup form validation
         */
        setupValidation: function() {
            // Check if we have the new multi-service interface
            if ($('#tutor-services-container').length > 0) {
                // New multi-service validation is handled in validateServices function
                return;
            }
            
            // Legacy single service validation (for backward compatibility)
            const serviceSelect = $('#tutor_service');
            const rateSelect = $('#tutor_rate');
            
            if (serviceSelect.length > 0 && serviceSelect[0]) {
                serviceSelect.attr('required', true);
                if (serviceSelect[0].setCustomValidity) {
                    serviceSelect[0].setCustomValidity('');
                }
            }
            
            if (rateSelect.length > 0 && rateSelect[0]) {
                rateSelect.attr('required', true);
                if (rateSelect[0].setCustomValidity) {
                    rateSelect[0].setCustomValidity('');
                }
            }
        },
        

        
        // handleDayScheduleToggle method removed - handled by LatePoint
        
        // validateTimeRange method removed - handled by LatePoint
        
        // validateScheduleDays method removed - handled by LatePoint
        
        /**
         * Handle service selection change
         */
        handleServiceChange: function() {
            const $select = $(this);
            const serviceId = $select.val();
            
            // Check if this is a multi-service row
            if ($select.hasClass('service-select')) {
                const $row = $select.closest('.service-row');
                const $rateSelect = $row.find('.rate-select');
            
            if (serviceId) {
                TutorRegistration.clearFieldError($select);
                // Auto-fill default rate if available and rate not selected
                const defaultRate = $select.find('option:selected').data('default-rate');
                if (defaultRate && !$rateSelect.val()) {
                    // Find the closest rate option to the default rate
                    const closestRate = Math.round(parseFloat(defaultRate));
                    if (closestRate >= 5 && closestRate <= 50) {
                        $rateSelect.val(closestRate);
                    }
                }
                } else {
                    TutorRegistration.showFieldError($select, 'Please select a teaching subject');
                }
            } else {
                // Legacy single service handling
                if (serviceId) {
                    TutorRegistration.clearFieldError('#tutor_service');
                } else {
                    TutorRegistration.showFieldError('#tutor_service', 'Please select a teaching subject');
                }
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
            } else {
                TutorRegistration.showFieldError('#tutor_rate', 'Please enter an hourly rate');
            }
        },
        
        /**
         * Add a new service row
         */
        addServiceRow: function() {
            const $container = $('#tutor-services-container');
            const $rows = $container.find('.service-row');
            const newIndex = $rows.length;
            
            // Get the template row (first row)
            const $template = $rows.first();
            if ($template.length === 0) {
                console.error('No service row template found');
                return;
            }
            
            // Clone and update the template
            const $newRow = $template.clone();
            
            // Update form field names and IDs
            $newRow.find('select, input').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                if (name) {
                    const newName = name.replace(/\[\d+\]/, '[' + newIndex + ']');
                    $field.attr('name', newName);
                }
                
                const id = $field.attr('id');
                if (id) {
                    const newId = id.replace(/_\d+$/, '_' + newIndex);
                    $field.attr('id', newId);
                }
            });
            
            // Clear values
            $newRow.find('select').val('');
            $newRow.find('input').val('');
            
            // Update data-index
            $newRow.attr('data-index', newIndex);
            
            // Show remove button
            $newRow.find('.remove-service-btn').show();
            
            // Append to container
            $container.append($newRow);
            
            // Focus on the new service dropdown
            $newRow.find('.service-select').focus();
            
            // Update remove button visibility
            TutorRegistration.updateRemoveButtons();
        },
        
        /**
         * Remove a service row
         */
        removeServiceRow: function() {
            const $row = $(this).closest('.service-row');
            const $container = $('#tutor-services-container');
            
            // Don't remove if it's the only row
            if ($container.find('.service-row').length <= 1) {
                return;
            }
            
            $row.remove();
            
            // Update remove button visibility
            TutorRegistration.updateRemoveButtons();
            
            // Re-index remaining rows
            TutorRegistration.reindexServiceRows();
        },
        
        /**
         * Update remove button visibility
         */
        updateRemoveButtons: function() {
            const $container = $('#tutor-services-container');
            const $rows = $container.find('.service-row');
            
            if ($rows.length <= 1) {
                $rows.find('.remove-service-btn').hide();
            } else {
                $rows.find('.remove-service-btn').show();
            }
        },
        
        /**
         * Re-index service rows after removal
         */
        reindexServiceRows: function() {
            const $container = $('#tutor-services-container');
            const $rows = $container.find('.service-row');
            
            $rows.each(function(index) {
                const $row = $(this);
                
                // Update form field names
                $row.find('select, input').each(function() {
                    const $field = $(this);
                    const name = $field.attr('name');
                    if (name) {
                        const newName = name.replace(/\[\d+\]/, '[' + index + ']');
                        $field.attr('name', newName);
                    }
                    
                    const id = $field.attr('id');
                    if (id) {
                        const newId = id.replace(/_\d+$/, '_' + index);
                        $field.attr('id', newId);
                    }
                });
                
                // Update data-index
                $row.attr('data-index', index);
            });
        },
        
        /**
         * Validate services
         */
        validateServices: function() {
            const $container = $('#tutor-services-container');
            if ($container.length === 0) {
                return true; // No multi-service interface
            }
            
            const $rows = $container.find('.service-row');
            let hasValidService = false;
            const selectedServices = [];
            let hasDuplicate = false;
            
            $rows.each(function() {
                const $row = $(this);
                const serviceId = $row.find('.service-select').val();
                const rate = parseFloat($row.find('.rate-select').val()) || 0;
                
                // Reset row styling
                $row.removeClass('error valid');
                
                if (serviceId && rate > 0) {
                    // Check for duplicates
                    if (selectedServices.includes(serviceId)) {
                        $row.addClass('error');
                        hasDuplicate = true;
                    } else {
                        $row.addClass('valid');
                        selectedServices.push(serviceId);
                        hasValidService = true;
                    }
                } else if (serviceId || rate > 0) {
                    // Incomplete row
                    $row.addClass('error');
                }
            });
            
            // Show validation messages
            if (hasDuplicate) {
                TutorRegistration.showFieldError($container, 'Please remove duplicate subjects');
                return false;
            } else if (!hasValidService) {
                TutorRegistration.showFieldError($container, 'Please select at least one subject with a rate');
                return false;
            } else {
                TutorRegistration.clearFieldError($container);
                return true;
            }
        },
        
        /**
         * Show field error
         */
        showFieldError: function(field, message) {
            const $field = $(field);
            $field.addClass('error');
            
            // Remove existing error message
            $field.siblings('.error-message').remove();
            
            // Add new error message
            $field.after('<div class="error-message">' + message + '</div>');
        },
        
        /**
         * Clear field error
         */
        clearFieldError: function(field) {
            const $field = $(field);
            $field.removeClass('error');
            $field.siblings('.error-message').remove();
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
                    
                // Schedule field cases removed
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
            
            // Schedule validation removed - handled by LatePoint
            
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
            const $container = $field.closest('.form-row');
            
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
            const $container = $field.closest('.form-row');
            
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
        
        // Initialize multi-service functionality
        initMultiServiceFunctionality();
    });
    
    /**
     * Initialize multi-service functionality
     */
    function initMultiServiceFunctionality() {
        // Service selection change (delegated)
        $(document).on('change', '.service-dropdown', function() {
            const $row = $(this).closest('.service-row');
            const $rateSelect = $row.find('.rate-select');
            const defaultRate = $(this).find('option:selected').data('default-rate');
            
            // Auto-fill default rate if rate not selected
            if (defaultRate && !$rateSelect.val()) {
                const closestRate = Math.round(parseFloat(defaultRate));
                if (closestRate >= 5 && closestRate <= 50) {
                    $rateSelect.val(closestRate);
                }
            }
            
            validateServices();
        });
        
        // Rate select validation (delegated)
        $(document).on('change', '.rate-select', function() {
            validateServices();
        });
        
        // Initial validation
        validateServices();
    }
    
    /**
     * Validate services
     */
    function validateServices() {
        const $container = $('#tutor-services-container');
        const $rows = $container.find('.service-row');
        let hasValidService = false;
        const selectedServices = [];
        let hasDuplicate = false;
        
        $rows.each(function() {
            const $row = $(this);
            const serviceId = $row.find('.service-dropdown').val();
            const rate = parseFloat($row.find('.rate-select').val()) || 0;
            
            // Reset row styling
            $row.removeClass('error valid');
            
            if (serviceId && rate > 0) {
                // Check for duplicates
                if (selectedServices.includes(serviceId)) {
                    $row.addClass('error');
                    hasDuplicate = true;
                } else {
                    $row.addClass('valid');
                    selectedServices.push(serviceId);
                    hasValidService = true;
                }
            } else if (serviceId || rate > 0) {
                // Partially filled
                $row.addClass('error');
            }
        });
        
        // Update container styling
        if (hasValidService && !hasDuplicate) {
            $container.removeClass('error').addClass('valid');
        } else {
            $container.removeClass('valid').addClass('error');
        }
        
        return hasValidService && !hasDuplicate;
    }
    
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