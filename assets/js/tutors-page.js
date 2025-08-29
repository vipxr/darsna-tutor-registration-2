/**
 * Darsna Tutors Page JavaScript
 * Version: 4.0.0
 * Includes mobile sidebar functionality (formerly fiverr-layout.js)
 */

jQuery(document).ready(function($) {
    'use strict';

    const DarsnaTutors = {
        currentPage: 1,
        perPage: 50,
        loading: false,
        hasMore: true,
        
        init: function() {
            this.bindEvents();
            this.initializeCards();
            this.initInfiniteScroll();
            this.initMobileLayout();
        },

        initializeCards: function() {
            // Wait for DOM to be fully loaded, then initialize cards
            setTimeout(() => {
                const $cards = $('.tutor-card');
                if ($cards.length > 0) {
                    // Ensure all tutor cards are visible by default
                    $cards.addClass('visible').removeClass('hidden');
                    // Hide no-results message if we have cards
                    $('#no-results').removeClass('visible').addClass('hidden');
                } else {
                    // Only show no-results if we're sure there are no cards after loading
                    setTimeout(() => {
                        if ($('.tutor-card').length === 0) {
                            $('#no-results').removeClass('hidden').addClass('visible');
                        }
                    }, 1000); // Give more time for AJAX loading
                }
            }, 100);
        },

        bindEvents: function() {
            // Search functionality
            $('#tutor-search').on('input', this.debounce(this.handleFilters.bind(this), 300));
            
            // Filter changes
            $('.filter-select').on('change', this.handleFilters.bind(this));
            
            // Clear filters
            $('#clear-filters').on('click', this.clearFilters.bind(this));
            
            // Book now
            $(document).on('click', '.btn-book-now', this.bookNow.bind(this));
            
            // Mobile layout events
            this.bindMobileEvents();
        },

        handleFilters: function() {
            const search = $('#tutor-search').val().toLowerCase();
            const subject = $('#subject-filter').val().toLowerCase();
            const location = $('#location-filter').val();
            const priceRange = $('#price-filter').val();
            const sort = $('#sort-filter').val();
            
            let visibleCards = 0;
            const $cards = $('.tutor-card');
            
            // Filter cards
            $cards.each(function() {
                const $card = $(this);
                let show = true;
                
                // Search filter
                if (search && show) {
                    const name = $card.data('name') || '';
                    const subjects = $card.data('subjects') || '';
            if (name.indexOf(search) === -1 && subjects.indexOf(search) === -1) {
                        show = false;
                    }
                }
                
                // Subject filter
                if (subject && show) {
                    const subjects = ($card.data('subjects') || '').toLowerCase();
            if (subjects.indexOf(subject) === -1) {
                        show = false;
                    }
                }
                
                // Location filter
                if (location && show) {
                    if ($card.data('country') !== location) {
                        show = false;
                    }
                }
                
                // Price filter
                if (priceRange && show) {
                    const minPrice = parseFloat($card.data('min-price')) || 0;
                    
                    switch(priceRange) {
                        case '0-30':
                            if (minPrice > 30) show = false;
                            break;
                        case '30-50':
                            if (minPrice < 30 || minPrice > 50) show = false;
                            break;
                        case '50-100':
                            if (minPrice < 50 || minPrice > 100) show = false;
                            break;
                        case '100+':
                            if (minPrice < 100) show = false;
                            break;
                    }
                }
                
                if (show) {
                    $card.removeClass('hidden').addClass('visible');
                    visibleCards++;
                } else {
                    $card.removeClass('visible').addClass('hidden');
                }
            });
            
            // Sort visible cards
            if (sort && visibleCards > 0) {
                this.sortCards(sort);
            }
            
            // Show/hide no results message - but only if we have cards loaded
            const totalCards = $('.tutor-card').length;
            if (totalCards > 0) {
                if (visibleCards === 0) {
                    $('#no-results').removeClass('hidden').addClass('visible');
                } else {
                    $('#no-results').removeClass('visible').addClass('hidden');
                }
            } else {
                // If no cards are loaded yet, hide the no-results message
                $('#no-results').removeClass('visible').addClass('hidden');
            }
        },

        sortCards: function(sortBy) {
            const $grid = $('#tutors-grid');
            const $cards = $grid.find('.tutor-card:visible');
            
            $cards.sort(function(a, b) {
                const $a = $(a);
                const $b = $(b);
                
                switch(sortBy) {
                    case 'price-low':
                        return (parseFloat($a.data('min-price')) || 0) - (parseFloat($b.data('min-price')) || 0);
                    case 'price-high':
                        return (parseFloat($b.data('min-price')) || 0) - (parseFloat($a.data('min-price')) || 0);
                    case 'name':
                    default:
                        return ($a.data('name') || '').localeCompare($b.data('name') || '');
                }
            });
            
            $cards.detach().appendTo($grid);
        },

        clearFilters: function() {
            $('#tutor-search').val('');
            $('.filter-select').val('');
            this.handleFilters();
        },



        bookNow: function(e) {
            e.preventDefault();
            const $trigger = $(e.currentTarget);
            const agentId = $trigger.data('agent-id');
            
            if (!agentId) {
                return;
            }
            
            // Create a temporary trigger element with proper LatePoint data attributes
            const $tempTrigger = $('<div></div>').attr({
                'data-selected-agent': agentId,
                'class': 'latepoint-book-button'
            }).hide().appendTo('body');
            
            // Trigger LatePoint booking using the correct function
            if (typeof window.latepoint_init_booking_form_by_trigger !== 'undefined') {
                window.latepoint_init_booking_form_by_trigger($tempTrigger);
                // Clean up temporary element after a short delay
                setTimeout(() => $tempTrigger.remove(), 1000);
            } else {
                $tempTrigger.remove();
                alert('Booking system is not available. Please contact support.');
            }
        },

        initInfiniteScroll: function() {
            const self = this;
            $(window).on('scroll', this.debounce(function() {
                if (self.shouldLoadMore()) {
                    self.loadMoreTutors();
                }
            }, 300));
        },

        shouldLoadMore: function() {
            if (this.loading || !this.hasMore) {
                return false;
            }
            
            const scrollTop = $(window).scrollTop();
            const windowHeight = $(window).height();
            const documentHeight = $(document).height();
            
            // Load more when user is 200px from bottom
            return (scrollTop + windowHeight >= documentHeight - 200);
        },

        loadMoreTutors: function() {
            if (this.loading || !this.hasMore) {
                return;
            }
            
            this.loading = true;
            this.currentPage++;
            
            // Show loading indicator
            this.showLoadingIndicator();
            
            $.post(darsna_tutors.ajax_url, {
                action: 'darsna_load_more_tutors',
                page: this.currentPage,
                per_page: this.perPage,
                nonce: darsna_tutors.nonce
            })
            .done((response) => {
                if (response.success) {
                    $('.tutors-grid').append(response.data.html);
                    this.hasMore = response.data.has_more;
                    
                    // Re-apply current filters to new cards
                    this.handleFilters();
                } else {
                    // Failed to load more tutors
                }
            })
            .fail((xhr, status, error) => {
                // AJAX error loading more tutors
            })
            .always(() => {
                this.loading = false;
                this.hideLoadingIndicator();
            });
        },

        showLoadingIndicator: function() {
            if (!$('.tutors-loading').length) {
                $('.tutors-grid').after('<div class="tutors-loading">Loading more tutors...</div>');
            }
        },

        hideLoadingIndicator: function() {
            $('.tutors-loading').remove();
        },

        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        // Mobile Layout Functionality (formerly fiverr-layout.js)
        initMobileLayout: function() {
            this.setupMobileOverlay();
        },

        bindMobileEvents: function() {
            // Mobile filter toggle
            $(document).on('click', '.mobile-filter-toggle', (e) => {
                e.preventDefault();
                this.toggleMobileSidebar();
            });

            // Sidebar close button
            $(document).on('click', '.sidebar-close', (e) => {
                e.preventDefault();
                this.closeMobileSidebar();
            });

            // Mobile overlay click
            $(document).on('click', '.mobile-overlay', (e) => {
                e.preventDefault();
                this.closeMobileSidebar();
            });

            // Window resize handler
            $(window).on('resize', () => {
                this.handleResize();
            });

            // ESC key to close sidebar
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeMobileSidebar();
                }
            });
        },

        setupMobileOverlay: function() {
            if (!$('.mobile-overlay').length) {
                $('body').append('<div class="mobile-overlay"></div>');
            }
        },

        toggleMobileSidebar: function() {
            const $sidebar = $('.tutors-sidebar');
            const $overlay = $('.mobile-overlay');
            
            if ($sidebar.hasClass('mobile-visible')) {
                this.closeMobileSidebar();
            } else {
                this.openMobileSidebar();
            }
        },

        openMobileSidebar: function() {
            const $sidebar = $('.tutors-sidebar');
            const $overlay = $('.mobile-overlay');
            
            $sidebar.addClass('mobile-visible');
            $overlay.addClass('visible');
            $('body').addClass('sidebar-open');
            
            // Prevent body scroll
            $('body').css('overflow', 'hidden');
        },

        closeMobileSidebar: function() {
            const $sidebar = $('.tutors-sidebar');
            const $overlay = $('.mobile-overlay');
            
            $sidebar.removeClass('mobile-visible');
            $overlay.removeClass('visible');
            $('body').removeClass('sidebar-open');
            
            // Restore body scroll
            $('body').css('overflow', '');
        },

        handleResize: function() {
            const windowWidth = $(window).width();
            
            // Close sidebar on desktop
            if (windowWidth >= 768) {
                this.closeMobileSidebar();
            }
        }
    };

    // Initialize
    if ($('.darsna-tutors-page').length) {
        DarsnaTutors.init();
    }
});