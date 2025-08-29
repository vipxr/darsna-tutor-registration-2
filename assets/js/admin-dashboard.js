/**
 * Darsna Admin Dashboard JavaScript
 * Version: 4.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    const DarsnaAdmin = {
        
        currentPayoutId: null,
        
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Export payouts
            $('#export-payouts').on('click', this.exportPayouts.bind(this));
            
            // Mark single payout as paid
            $(document).on('click', '.mark-paid', this.markPaidSingle.bind(this));
            
            // Bulk mark as paid
            $('#bulk-mark-paid').on('click', this.markPaidBulk.bind(this));
            
            // Select all checkboxes
            $('#select-all-payouts').on('change', this.selectAll.bind(this));
            
            // Modal events
            $('#confirm-mark-paid').on('click', this.confirmMarkPaid.bind(this));
            $('#cancel-mark-paid').on('click', this.closeModal.bind(this));
        },

        exportPayouts: function(e) {
            e.preventDefault();
            
            // Get current filters
            const params = new URLSearchParams(window.location.search);
            params.set('action', 'darsna_export_payouts');
            params.set('nonce', darsna_admin.export_payouts_nonce);
            
            // Trigger download
            window.location.href = darsna_admin.ajax_url + '?' + params.toString();
        },

        markPaidSingle: function(e) {
            e.preventDefault();
            this.currentPayoutId = $(e.currentTarget).data('payout-id');
            this.openModal();
        },

        markPaidBulk: function(e) {
            e.preventDefault();
            
            const selectedIds = [];
            $('input[name="payout_ids[]"]:checked').each(function() {
                selectedIds.push($(this).val());
            });
            
            if (selectedIds.length === 0) {
                alert('Please select at least one payout.');
                return;
            }
            
            this.currentPayoutId = selectedIds;
            this.openModal();
        },

        confirmMarkPaid: function() {
            const reference = $('#payout-reference').val();
            
            if (Array.isArray(this.currentPayoutId)) {
                // Bulk update
                $.post(darsna_admin.ajax_url, {
                    action: 'darsna_bulk_mark_paid',
                    payout_ids: this.currentPayoutId,
                    reference: reference,
                    nonce: darsna_admin.bulk_mark_paid_nonce
                })
                .done(function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                    }
                });
            } else {
                // Single update
                $.post(darsna_admin.ajax_url, {
                    action: 'darsna_mark_payout_paid',
                    payout_id: this.currentPayoutId,
                    reference: reference,
                    nonce: darsna_admin.mark_payout_paid_nonce
                })
                .done(function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                    }
                });
            }
            
            this.closeModal();
        },

        selectAll: function(e) {
            const isChecked = $(e.currentTarget).is(':checked');
            $('input[name="payout_ids[]"]').prop('checked', isChecked);
        },

        openModal: function() {
            $('#mark-paid-modal').removeClass('hidden').addClass('visible');
            $('#payout-reference').val('').focus();
        },

        closeModal: function() {
            $('#mark-paid-modal').removeClass('visible').addClass('hidden');
            $('#payout-reference').val('');
            this.currentPayoutId = null;
        }
    };

    // Initialize
    DarsnaAdmin.init();
});
