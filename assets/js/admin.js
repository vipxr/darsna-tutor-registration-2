/**
 * Admin Dashboard JavaScript for Darsna Tutor Registration
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        DarsnaAdmin.init();
    });

    const DarsnaAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initModals();
            this.initBulkActions();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Agent status toggle buttons
            $(document).on('click', '.activate-agent', this.handleActivateAgent);
            $(document).on('click', '.deactivate-agent', this.handleDeactivateAgent);
            
            // Agent actions
            $(document).on('click', '.view-agent', this.handleViewAgent);
            $(document).on('click', '.edit-agent', this.handleEditAgent);
            $(document).on('click', '.delete-agent', this.handleDeleteAgent);
            
            // Bulk select
            $(document).on('change', '#cb-select-all-1', this.handleSelectAll);
            $(document).on('change', 'input[name="agent[]"]', this.handleSelectAgent);
            
            // Modal close
            $(document).on('click', '.darsna-modal-close', this.closeModal);
            $(document).on('click', '.darsna-modal', function(e) {
                if (e.target === this) {
                    DarsnaAdmin.closeModal();
                }
            });
            
            // Form submissions
            $(document).on('submit', '#agent-edit-form', this.handleAgentUpdate);
        },

        /**
         * Initialize modal functionality
         */
        initModals: function() {
            // Create modal if it doesn't exist
            if ($('#agent-details-modal').length === 0) {
                $('body').append(this.getModalHTML());
            }
        },

        /**
         * Initialize bulk actions
         */
        initBulkActions: function() {
            $(document).on('click', '#doaction', function(e) {
                e.preventDefault();
                
                const action = $('#bulk-action-selector-top').val();
                const selectedAgents = $('input[name="agent[]"]:checked').map(function() {
                    return $(this).val();
                }).get();
                
                if (action === '-1') {
                    alert(darsna_admin.strings.error);
                    return;
                }
                
                if (selectedAgents.length === 0) {
                    alert('Please select at least one agent.');
                    return;
                }
                
                DarsnaAdmin.handleBulkAction(action, selectedAgents);
            });
        },

        /**
         * Handle bulk actions
         */
        handleBulkAction: function(action, agentIds) {
            let confirmMessage = '';
            
            switch (action) {
                case 'activate':
                    confirmMessage = 'Are you sure you want to activate the selected agents?';
                    break;
                case 'deactivate':
                    confirmMessage = 'Are you sure you want to deactivate the selected agents?';
                    break;
                case 'delete':
                    confirmMessage = 'Are you sure you want to delete the selected agents? This action cannot be undone.';
                    break;
            }
            
            if (!confirm(confirmMessage)) {
                return;
            }
            
            this.showLoading();
            
            const promises = agentIds.map(agentId => {
                switch (action) {
                    case 'activate':
                        return this.updateAgentStatus(agentId, 'active');
                    case 'deactivate':
                        return this.updateAgentStatus(agentId, 'inactive');
                    case 'delete':
                        return this.deleteAgent(agentId);
                }
            });
            
            Promise.all(promises)
                .then(() => {
                    this.hideLoading();
                    this.showNotice('Bulk action completed successfully.', 'success');
                    location.reload();
                })
                .catch(() => {
                    this.hideLoading();
                    this.showNotice('Some operations failed. Please try again.', 'error');
                });
        },

        /**
         * Handle activate agent
         */
        handleActivateAgent: function(e) {
            e.preventDefault();
            
            const agentId = $(this).data('agent-id');
            
            if (!confirm(darsna_admin.strings.confirm_activate)) {
                return;
            }
            
            DarsnaAdmin.updateAgentStatus(agentId, 'active')
                .then(() => {
                    DarsnaAdmin.showNotice(darsna_admin.strings.success, 'success');
                    location.reload();
                })
                .catch(() => {
                    DarsnaAdmin.showNotice(darsna_admin.strings.error, 'error');
                });
        },

        /**
         * Handle deactivate agent
         */
        handleDeactivateAgent: function(e) {
            e.preventDefault();
            
            const agentId = $(this).data('agent-id');
            
            if (!confirm(darsna_admin.strings.confirm_deactivate)) {
                return;
            }
            
            DarsnaAdmin.updateAgentStatus(agentId, 'inactive')
                .then(() => {
                    DarsnaAdmin.showNotice(darsna_admin.strings.success, 'success');
                    location.reload();
                })
                .catch(() => {
                    DarsnaAdmin.showNotice(darsna_admin.strings.error, 'error');
                });
        },

        /**
         * Handle view agent
         */
        handleViewAgent: function(e) {
            e.preventDefault();
            
            const agentId = $(this).data('agent-id');
            DarsnaAdmin.showAgentDetails(agentId);
        },

        /**
         * Handle edit agent
         */
        handleEditAgent: function(e) {
            e.preventDefault();
            
            const agentId = $(this).data('agent-id');
            DarsnaAdmin.showAgentEditForm(agentId);
        },

        /**
         * Handle delete agent
         */
        handleDeleteAgent: function(e) {
            e.preventDefault();
            
            const agentId = $(this).data('agent-id');
            
            if (!confirm(darsna_admin.strings.confirm_delete)) {
                return;
            }
            
            DarsnaAdmin.deleteAgent(agentId)
                .then(() => {
                    DarsnaAdmin.showNotice(darsna_admin.strings.success, 'success');
                    location.reload();
                })
                .catch(() => {
                    DarsnaAdmin.showNotice(darsna_admin.strings.error, 'error');
                });
        },

        /**
         * Handle select all checkbox
         */
        handleSelectAll: function() {
            const isChecked = $(this).prop('checked');
            $('input[name="agent[]"]').prop('checked', isChecked);
        },

        /**
         * Handle individual agent selection
         */
        handleSelectAgent: function() {
            const totalCheckboxes = $('input[name="agent[]"]').length;
            const checkedCheckboxes = $('input[name="agent[]"]:checked').length;
            
            $('#cb-select-all-1').prop('checked', totalCheckboxes === checkedCheckboxes);
        },

        /**
         * Update agent status via AJAX
         */
        updateAgentStatus: function(agentId, status) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: darsna_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'darsna_update_agent_status',
                        agent_id: agentId,
                        status: status,
                        nonce: darsna_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(response.data);
                        }
                    },
                    error: function() {
                        reject('Network error');
                    }
                });
            });
        },

        /**
         * Delete agent via AJAX
         */
        deleteAgent: function(agentId) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: darsna_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'darsna_delete_agent',
                        agent_id: agentId,
                        nonce: darsna_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(response.data);
                        }
                    },
                    error: function() {
                        reject('Network error');
                    }
                });
            });
        },

        /**
         * Get agent details via AJAX
         */
        getAgentDetails: function(agentId) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: darsna_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'darsna_get_agent_details',
                        agent_id: agentId,
                        nonce: darsna_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(response.data);
                        }
                    },
                    error: function() {
                        reject('Network error');
                    }
                });
            });
        },

        /**
         * Show agent details in modal
         */
        showAgentDetails: function(agentId) {
            this.showLoading();
            
            this.getAgentDetails(agentId)
                .then((data) => {
                    this.hideLoading();
                    this.renderAgentDetails(data);
                    this.openModal();
                })
                .catch(() => {
                    this.hideLoading();
                    this.showNotice(darsna_admin.strings.error, 'error');
                });
        },

        /**
         * Show agent edit form in modal
         */
        showAgentEditForm: function(agentId) {
            this.showLoading();
            
            this.getAgentDetails(agentId)
                .then((data) => {
                    this.hideLoading();
                    this.renderAgentEditForm(data);
                    this.openModal();
                })
                .catch(() => {
                    this.hideLoading();
                    this.showNotice(darsna_admin.strings.error, 'error');
                });
        },

        /**
         * Render agent details
         */
        renderAgentDetails: function(data) {
            const agent = data.agent;
            const services = data.services;
            
            let servicesHTML = '<ul class="services-list">';
            if (services && services.length > 0) {
                services.forEach(service => {
                    servicesHTML += `
                        <li>
                            <span class="service-name">${service.name}</span>
                            <span class="service-rate">$${parseFloat(service.charge_amount || 0).toFixed(2)}</span>
                        </li>
                    `;
                });
            } else {
                servicesHTML += '<li>No services assigned</li>';
            }
            servicesHTML += '</ul>';
            
            const html = `
                <div class="agent-details">
                    <div class="agent-details-section">
                        <h3>Personal Information</h3>
                        <div class="agent-details-field">
                            <strong>Name:</strong>
                            <span>${agent.first_name} ${agent.last_name}</span>
                        </div>
                        <div class="agent-details-field">
                            <strong>Email:</strong>
                            <span>${agent.email}</span>
                        </div>
                        <div class="agent-details-field">
                            <strong>Phone:</strong>
                            <span>${agent.phone || 'Not provided'}</span>
                        </div>
                        <div class="agent-details-field">
                            <strong>Status:</strong>
                            <span class="status-badge status-${agent.status}">${agent.status.charAt(0).toUpperCase() + agent.status.slice(1)}</span>
                        </div>
                        <div class="agent-details-field">
                            <strong>Created:</strong>
                            <span>${new Date(agent.created_at).toLocaleDateString()}</span>
                        </div>
                    </div>
                    
                    <div class="agent-details-section">
                        <h3>Services & Pricing</h3>
                        ${servicesHTML}
                    </div>
                </div>
                
                <div class="agent-details-section">
                    <h3>Additional Information</h3>
                    <div class="agent-details-field">
                        <strong>Bio:</strong>
                        <span>${agent.bio || 'No bio provided'}</span>
                    </div>
                    <div class="agent-details-field">
                        <strong>Features:</strong>
                        <span>${agent.features || 'No features listed'}</span>
                    </div>
                </div>
            `;
            
            $('#agent-details-content').html(html);
        },

        /**
         * Render agent edit form
         */
        renderAgentEditForm: function(data) {
            const agent = data.agent;
            const services = data.services || [];
            const allServices = data.all_services || [];
            const schedule = data.schedule || {};
            
            // Build services selection
            let servicesHTML = '';
            if (allServices.length > 0) {
                allServices.forEach(service => {
                    const assignedService = services.find(s => s.id == service.id);
                    const isAssigned = !!assignedService;
                    const customRate = assignedService ? (assignedService.custom_rate || service.charge_amount) : service.charge_amount;
                    
                    servicesHTML += `
                        <div class="service-item">
                            <label>
                                <input type="checkbox" name="services[]" value="${service.id}" ${isAssigned ? 'checked' : ''}>
                                ${service.name}
                            </label>
                            <div class="service-rate">
                                <label>Rate (USD):</label>
                                <input type="number" name="service_rates[${service.id}]" value="${parseFloat(customRate || 0).toFixed(2)}" step="0.01" min="0">
                            </div>
                        </div>
                    `;
                });
            } else {
                servicesHTML = '<p>No services available</p>';
            }
            
            // Build schedule fields
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            const dayLabels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            
            let scheduleHTML = '';
            days.forEach((day, index) => {
                const daySchedule = schedule[day] || {};
                const isEnabled = daySchedule.enabled || false;
                const startTime = daySchedule.start_time || '09:00';
                const endTime = daySchedule.end_time || '17:00';
                
                scheduleHTML += `
                    <div class="schedule-day">
                        <label class="day-label">
                            <input type="checkbox" name="schedule[${day}][enabled]" value="1" ${isEnabled ? 'checked' : ''}>
                            ${dayLabels[index]}
                        </label>
                        <div class="time-inputs">
                            <input type="time" name="schedule[${day}][start_time]" value="${startTime}" ${!isEnabled ? 'disabled' : ''}>
                            <span>to</span>
                            <input type="time" name="schedule[${day}][end_time]" value="${endTime}" ${!isEnabled ? 'disabled' : ''}>
                        </div>
                    </div>
                `;
            });
            
            const html = `
                <form id="agent-edit-form" data-agent-id="${agent.id}">
                    <div class="agent-edit-tabs">
                        <nav class="nav-tab-wrapper">
                            <a href="#tab-personal" class="nav-tab nav-tab-active">Personal Info</a>
                            <a href="#tab-services" class="nav-tab">Services & Rates</a>
                            <a href="#tab-schedule" class="nav-tab">Schedule</a>
                        </nav>
                        
                        <div id="tab-personal" class="tab-content active">
                            <table class="darsna-form-table">
                                <tr>
                                    <th>First Name</th>
                                    <td><input type="text" name="first_name" value="${agent.first_name}" required></td>
                                </tr>
                                <tr>
                                    <th>Last Name</th>
                                    <td><input type="text" name="last_name" value="${agent.last_name}" required></td>
                                </tr>
                                <tr>
                                    <th>Email</th>
                                    <td><input type="email" name="email" value="${agent.email}" required></td>
                                </tr>
                                <tr>
                                    <th>Phone</th>
                                    <td><input type="text" name="phone" value="${agent.phone || ''}"></td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td>
                                        <select name="status">
                                            <option value="active" ${agent.status === 'active' ? 'selected' : ''}>Active</option>
                                            <option value="inactive" ${agent.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                            <option value="pending" ${agent.status === 'pending' ? 'selected' : ''}>Pending</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Bio</th>
                                    <td><textarea name="bio" rows="4">${agent.bio || ''}</textarea></td>
                                </tr>
                                <tr>
                                    <th>Features</th>
                                    <td><textarea name="features" rows="3">${agent.features || ''}</textarea></td>
                                </tr>
                            </table>
                        </div>
                        
                        <div id="tab-services" class="tab-content">
                            <h3>Assign Services and Set Rates</h3>
                            <div class="services-list">
                                ${servicesHTML}
                            </div>
                        </div>
                        
                        <div id="tab-schedule" class="tab-content">
                            <h3>Weekly Schedule</h3>
                            <div class="schedule-container">
                                ${scheduleHTML}
                            </div>
                        </div>
                    </div>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">Update Agent</button>
                        <button type="button" class="button darsna-modal-close">Cancel</button>
                    </p>
                </form>
            `;
            
            $('#agent-details-content').html(html);
            
            // Initialize tab functionality
            this.initEditFormTabs();
        },
        
        /**
         * Initialize edit form tabs
         */
        initEditFormTabs: function() {
            // Tab switching
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                const target = $(this).attr('href');
                
                // Update active tab
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Update active content
                $('.tab-content').removeClass('active');
                $(target).addClass('active');
            });
            
            // Schedule day enable/disable functionality
            $('input[name*="[enabled]"]').on('change', function() {
                const dayContainer = $(this).closest('.schedule-day');
                const timeInputs = dayContainer.find('input[type="time"]');
                
                if ($(this).is(':checked')) {
                    timeInputs.prop('disabled', false);
                } else {
                    timeInputs.prop('disabled', true);
                }
            });
        },

        /**
         * Handle agent update form submission
         */
        handleAgentUpdate: function(e) {
            e.preventDefault();
            
            const form = $(this);
            const agentId = form.data('agent-id');
            
            // Collect form data
            const formData = new FormData(form[0]);
            
            // Collect services and rates
            const services = [];
            const serviceRates = {};
            
            form.find('input[name="services[]"]:checked').each(function() {
                const serviceId = $(this).val();
                services.push(serviceId);
                
                const rateInput = form.find(`input[name="service_rates[${serviceId}]"]`);
                if (rateInput.length) {
                    serviceRates[serviceId] = rateInput.val();
                }
            });
            
            // Collect schedule data
            const schedule = {};
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            
            days.forEach(day => {
                const enabled = form.find(`input[name="schedule[${day}][enabled]"]`).is(':checked');
                const startTime = form.find(`input[name="schedule[${day}][start_time]"]`).val();
                const endTime = form.find(`input[name="schedule[${day}][end_time]"]`).val();
                
                schedule[day] = {
                    enabled: enabled,
                    start_time: startTime,
                    end_time: endTime
                };
            });
            
            DarsnaAdmin.showLoading();
            
            $.ajax({
                url: darsna_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'darsna_update_agent',
                    agent_id: agentId,
                    form_data: form.serialize(),
                    services: services,
                    service_rates: serviceRates,
                    schedule: schedule,
                    nonce: darsna_admin.nonce
                },
                success: function(response) {
                    DarsnaAdmin.hideLoading();
                    if (response.success) {
                        DarsnaAdmin.closeModal();
                        DarsnaAdmin.showNotice(darsna_admin.strings.success, 'success');
                        location.reload();
                    } else {
                        DarsnaAdmin.showNotice(response.data || darsna_admin.strings.error, 'error');
                    }
                },
                error: function() {
                    DarsnaAdmin.hideLoading();
                    DarsnaAdmin.showNotice(darsna_admin.strings.error, 'error');
                }
            });
        },

        /**
         * Open modal
         */
        openModal: function() {
            $('#agent-details-modal').show();
            $('body').addClass('modal-open');
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('#agent-details-modal').hide();
            $('body').removeClass('modal-open');
            $('#agent-details-content').empty();
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            $('.wrap').addClass('loading');
        },

        /**
         * Hide loading state
         */
        hideLoading: function() {
            $('.wrap').removeClass('loading');
        },

        /**
         * Show notice
         */
        showNotice: function(message, type = 'info') {
            const notice = $(`
                <div class="notice notice-${type} is-dismissible darsna-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $('.wrap h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notice.fadeOut(() => notice.remove());
            }, 5000);
            
            // Manual dismiss
            notice.on('click', '.notice-dismiss', function() {
                notice.fadeOut(() => notice.remove());
            });
        },

        /**
         * Get modal HTML
         */
        getModalHTML: function() {
            return `
                <div id="agent-details-modal" class="darsna-modal" style="display: none;">
                    <div class="darsna-modal-content">
                        <div class="darsna-modal-header">
                            <h2>Agent Details</h2>
                            <span class="darsna-modal-close">&times;</span>
                        </div>
                        <div class="darsna-modal-body">
                            <div id="agent-details-content"></div>
                        </div>
                    </div>
                </div>
            `;
        }
    };

    // Export for global access
    window.DarsnaAdmin = DarsnaAdmin;

})(jQuery);