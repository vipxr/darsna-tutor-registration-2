(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        initTutorsPage();
    });
    
    function initTutorsPage() {
        // Cache DOM elements
        const $searchInput = $('#tutor-search');
        const $countryFilter = $('#country-filter');
        const $subjectFilter = $('#subject-filter');
        const $priceFilter = $('#price-filter');
        const $urgentHelpFilter = $('#urgent-help-filter');
        const $sortFilter = $('#sort-filter');
        const $clearButton = $('#clear-filters');
        const $tutorsGrid = $('#tutors-grid');
        const $loadingSpinner = $('#loading-spinner');
        const $noResults = $('#no-results');
        
        // Debounce timer for search
        let searchTimer;
        
        // Event listeners
        $searchInput.on('input', debounce(handleFilters, 300));
        $countryFilter.on('change', handleFilters);
        $subjectFilter.on('change', handleFilters);
        $priceFilter.on('change', handleFilters);
        $urgentHelpFilter.on('change', handleFilters);
        $sortFilter.on('change', handleFilters);
        $clearButton.on('click', clearAllFilters);
        
        // Handle tutor card actions
        $(document).on('click', '.contact-tutor', handleContactTutor);
        $(document).on('click', '.view-profile', handleViewProfile);
        
        // Handle filter changes
        function handleFilters() {
            const filters = {
                search: $searchInput.val().trim(),
                country: $countryFilter.val(),
                subject: $subjectFilter.val(),
                price_range: $priceFilter.val(),
                urgent_help: $urgentHelpFilter.val(),
                sort: $sortFilter.val()
            };
            
            // Show loading state
            showLoading();
            
            // Make AJAX request
            $.ajax({
                url: darsna_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'filter_tutors',
                    nonce: darsna_ajax.nonce,
                    ...filters
                },
                success: function(response) {
                    hideLoading();
                    
                    if (response.success) {
                        updateTutorsGrid(response.data.html, response.data.count);
                    } else {
                        showError('Failed to load tutors. Please try again.');
                    }
                },
                error: function() {
                    hideLoading();
                    showError('Network error. Please check your connection and try again.');
                }
            });
        }
        
        // Clear all filters
        function clearAllFilters() {
            $searchInput.val('');
            $countryFilter.val('');
            $subjectFilter.val('');
            $priceFilter.val('');
            $urgentHelpFilter.val('');
            $sortFilter.val('name');
            
            handleFilters();
        }
        
        // Update tutors grid
        function updateTutorsGrid(html, count) {
            $tutorsGrid.html(html);
            
            if (count === 0) {
                $noResults.show();
            } else {
                $noResults.hide();
                
                // Animate new cards
                $('.tutor-card').each(function(index) {
                    $(this).css({
                        opacity: 0,
                        transform: 'translateY(20px)'
                    }).delay(index * 50).animate({
                        opacity: 1
                    }, 300).css('transform', 'translateY(0)');
                });
            }
            
            // Update results count (if you want to show it)
            updateResultsCount(count);
        }
        
        // Show loading state
        function showLoading() {
            $loadingSpinner.show();
            $tutorsGrid.css('opacity', '0.5');
            $noResults.hide();
        }
        
        // Hide loading state
        function hideLoading() {
            $loadingSpinner.hide();
            $tutorsGrid.css('opacity', '1');
        }
        
        // Show error message
        function showError(message) {
            // Create error notification
            const $error = $('<div class="tutor-error-message">' + message + '</div>');
            $error.css({
                background: '#fee2e2',
                color: '#dc2626',
                padding: '12px 16px',
                borderRadius: '8px',
                margin: '16px 0',
                border: '1px solid #fecaca',
                textAlign: 'center'
            });
            
            // Remove existing error messages
            $('.tutor-error-message').remove();
            
            // Add new error message
            $tutorsGrid.before($error);
            
            // Auto-remove after 5 seconds
            setTimeout(function() {
                $error.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        // Update results count
        function updateResultsCount(count) {
            // Remove existing count
            $('.results-count').remove();
            
            // Add new count
            const countText = count === 1 ? '1 tutor found' : count + ' tutors found';
            const $count = $('<div class="results-count">' + countText + '</div>');
            $count.css({
                color: '#6b7280',
                fontSize: '14px',
                marginBottom: '16px',
                textAlign: 'center'
            });
            
            $tutorsGrid.before($count);
        }
        
        // Handle contact tutor
        function handleContactTutor(e) {
            e.preventDefault();
            const tutorId = $(this).data('tutor-id');
            
            // You can customize this behavior
            // For now, we'll show a simple modal or redirect
            if (typeof window.openContactModal === 'function') {
                window.openContactModal(tutorId);
            } else {
                // Fallback: scroll to contact form or show alert
                alert('Contact functionality will be implemented. Tutor ID: ' + tutorId);
                // You could also redirect to a contact page:
                // window.location.href = '/contact-tutor/?tutor_id=' + tutorId;
            }
        }
        
        // Handle view profile
        function handleViewProfile(e) {
            e.preventDefault();
            const tutorId = $(this).data('tutor-id');
            
            // You can customize this behavior
            if (typeof window.openTutorProfile === 'function') {
                window.openTutorProfile(tutorId);
            } else {
                // Fallback: redirect to profile page
                // window.location.href = '/tutor-profile/?tutor_id=' + tutorId;
                alert('Profile view functionality will be implemented. Tutor ID: ' + tutorId);
            }
        }
        
        // Debounce function
        function debounce(func, wait) {
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(searchTimer);
                    func(...args);
                };
                clearTimeout(searchTimer);
                searchTimer = setTimeout(later, wait);
            };
        }
        
        // Initialize tooltips (if you want to add them)
        initTooltips();
        
        // Initialize lazy loading for images
        initLazyLoading();
        
        // Initialize keyboard navigation
        initKeyboardNavigation();
    }
    
    // Enhanced Tutors Shortcode Functionality
    function initEnhancedTutors() {
        // Cache enhanced tutors elements
        const $enhancedSearch = $('#enhanced-tutor-search');
        const $enhancedSubjectFilter = $('#enhanced-subject-filter');
        const $enhancedCountryFilter = $('#enhanced-country-filter');
        const $enhancedPriceFilter = $('#enhanced-price-filter');
        const $enhancedSortFilter = $('#enhanced-sort-filter');
        const $enhancedClearButton = $('#enhanced-clear-filters');
        const $enhancedGrid = $('#enhanced-tutors-grid');
        const $enhancedLoading = $('#enhanced-loading-spinner');
        const $enhancedNoResults = $('#enhanced-no-results');
        const $enhancedPopup = $('#enhanced-tutor-popup');
        const $enhancedPopupBody = $('#enhanced-popup-body');
        
        // Only initialize if enhanced elements exist
        if ($enhancedGrid.length === 0) return;
        
        // Enhanced debounce function for this scope
        function enhancedDebounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        // Event listeners for enhanced tutors
        $enhancedSearch.on('input', enhancedDebounce(handleEnhancedFilters, 300));
        $enhancedSubjectFilter.on('change', handleEnhancedFilters);
        $enhancedCountryFilter.on('change', handleEnhancedFilters);
        $enhancedPriceFilter.on('change', handleEnhancedFilters);
        $enhancedSortFilter.on('change', handleEnhancedFilters);
        $enhancedClearButton.on('click', clearEnhancedFilters);
        
        // Enhanced popup handlers
        $(document).on('click', '.enhanced-view-details', handleEnhancedViewDetails);
        $(document).on('click', '.enhanced-popup-close', closeEnhancedPopup);
        $(document).on('click', '.enhanced-tutor-popup', function(e) {
            if (e.target === this) {
                closeEnhancedPopup();
            }
        });
        
        // Enhanced booking handlers
        $(document).on('click', '.enhanced-book-now, .enhanced-book-tutor', handleEnhancedBooking);
        $(document).on('click', '.enhanced-contact-tutor', handleEnhancedContact);
        
        // Handle enhanced filters
        function handleEnhancedFilters() {
            const filters = {
                search: $enhancedSearch.val().trim(),
                country: $enhancedCountryFilter.val(),
                subject: $enhancedSubjectFilter.val(),
                price_range: $enhancedPriceFilter.val(),
                sort: $enhancedSortFilter.val()
            };
            
            // Show loading state
            showEnhancedLoading();
            
            // Filter tutors client-side for better performance
            filterEnhancedTutorsClientSide(filters);
        }
        
        // Client-side filtering for enhanced tutors
        function filterEnhancedTutorsClientSide(filters) {
            const $tutorItems = $('.enhanced-tutor-item');
            let visibleCount = 0;
            
            $tutorItems.each(function() {
                const $item = $(this);
                const itemData = {
                    name: $item.data('name') || '',
                    country: $item.data('country') || '',
                    services: $item.data('services') || '',
                    minPrice: parseFloat($item.data('min-price')) || 0,
                    maxPrice: parseFloat($item.data('max-price')) || 0
                };
                
                let visible = true;
                
                // Search filter
                if (filters.search) {
                    const searchTerm = filters.search.toLowerCase();
                    const searchableText = `${itemData.name} ${itemData.services}`.toLowerCase();
                    if (searchableText.indexOf(searchTerm) === -1) {
                        visible = false;
                    }
                }
                
                // Country filter
                if (filters.country && itemData.country !== filters.country) {
                    visible = false;
                }
                
                // Subject filter
                if (filters.subject) {
                    if (itemData.services.indexOf(filters.subject.toLowerCase()) === -1) {
                        visible = false;
                    }
                }
                
                // Price filter
                if (filters.price_range) {
                    const minPrice = itemData.minPrice;
                    switch (filters.price_range) {
                        case '0-20':
                            if (minPrice > 20) visible = false;
                            break;
                        case '20-40':
                            if (minPrice < 20 || minPrice > 40) visible = false;
                            break;
                        case '40-60':
                            if (minPrice < 40 || minPrice > 60) visible = false;
                            break;
                        case '60-100':
                            if (minPrice < 60 || minPrice > 100) visible = false;
                            break;
                        case '100+':
                            if (minPrice < 100) visible = false;
                            break;
                    }
                }
                
                if (visible) {
                    $item.show();
                    visibleCount++;
                } else {
                    $item.hide();
                }
            });
            
            // Apply sorting to visible items
            if (filters.sort && visibleCount > 0) {
                sortEnhancedTutors(filters.sort);
            }
            
            // Show/hide no results message
            if (visibleCount === 0) {
                $enhancedNoResults.show();
            } else {
                $enhancedNoResults.hide();
            }
            
            hideEnhancedLoading();
        }
        
        // Sort enhanced tutors
        function sortEnhancedTutors(sortBy) {
            const $visibleItems = $('.enhanced-tutor-item:visible');
            const $parent = $enhancedGrid;
            
            const sortedItems = $visibleItems.sort(function(a, b) {
                const $a = $(a);
                const $b = $(b);
                
                switch (sortBy) {
                    case 'price-low':
                        return parseFloat($a.data('min-price') || 0) - parseFloat($b.data('min-price') || 0);
                    case 'price-high':
                        return parseFloat($b.data('min-price') || 0) - parseFloat($a.data('min-price') || 0);
                    case 'name':
                    default:
                        return ($a.data('name') || '').localeCompare($b.data('name') || '');
                }
            });
            
            // Re-append sorted items
            sortedItems.detach().appendTo($parent);
        }
        
        // Clear enhanced filters
        function clearEnhancedFilters() {
            $enhancedSearch.val('');
            $enhancedSubjectFilter.val('');
            $enhancedCountryFilter.val('');
            $enhancedPriceFilter.val('');
            $enhancedSortFilter.val('name');
            
            handleEnhancedFilters();
        }
        
        // Handle enhanced view details
        function handleEnhancedViewDetails() {
            const tutorId = $(this).data('tutor-id');
            
            if (!tutorId) return;
            
            // Show loading in popup
            $enhancedPopupBody.html('<div class="enhanced-popup-loading"><div class="spinner"></div><p>Loading tutor details...</p></div>');
            $enhancedPopup.show();
            
            // Make AJAX request for tutor details
            $.ajax({
                url: darsna_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_tutor_details',
                    nonce: darsna_ajax.nonce,
                    tutor_id: tutorId
                },
                success: function(response) {
                    if (response.success) {
                        $enhancedPopupBody.html(response.data.html);
                    } else {
                        $enhancedPopupBody.html('<div class="enhanced-popup-error"><p>Failed to load tutor details. Please try again.</p></div>');
                    }
                },
                error: function() {
                    $enhancedPopupBody.html('<div class="enhanced-popup-error"><p>Network error. Please check your connection and try again.</p></div>');
                }
            });
        }
        
        // Close enhanced popup
        function closeEnhancedPopup() {
            $enhancedPopup.hide();
            $enhancedPopupBody.empty();
        }
        
        // Handle enhanced booking
        function handleEnhancedBooking() {
            const agentId = $(this).data('selected-agent');
            
            if (!agentId) return;
            
            // Integrate with LatePoint booking system
            // This will trigger LatePoint's booking flow
            if (typeof latepoint_init_booking_form_by_trigger !== 'undefined') {
                latepoint_init_booking_form_by_trigger($(this));
            } else {
                // Fallback: redirect to booking page or show booking modal
                console.log('Book tutor:', agentId);
                alert('Booking functionality will integrate with LatePoint booking system.');
            }
        }
        
        // Handle enhanced contact
        function handleEnhancedContact() {
            const tutorEmail = $(this).data('tutor-email');
            
            if (tutorEmail) {
                window.location.href = `mailto:${tutorEmail}`;
            } else {
                alert('Contact information not available.');
            }
        }
        
        // Enhanced loading functions
        function showEnhancedLoading() {
            $enhancedLoading.show();
            $enhancedGrid.css('opacity', '0.5');
        }
        
        function hideEnhancedLoading() {
            $enhancedLoading.hide();
            $enhancedGrid.css('opacity', '1');
        }
    }
    
    // Initialize enhanced tutors when document is ready
    $(document).ready(function() {
        initEnhancedTutors();
    });
    
    // Initialize tooltips
    function initTooltips() {
        // Simple tooltip implementation
        $(document).on('mouseenter', '[data-tooltip]', function() {
            const $this = $(this);
            const tooltipText = $this.data('tooltip');
            
            if (tooltipText) {
                const $tooltip = $('<div class="custom-tooltip">' + tooltipText + '</div>');
                $tooltip.css({
                    position: 'absolute',
                    background: '#1f2937',
                    color: 'white',
                    padding: '6px 10px',
                    borderRadius: '6px',
                    fontSize: '12px',
                    zIndex: 1000,
                    whiteSpace: 'nowrap',
                    pointerEvents: 'none'
                });
                
                $('body').append($tooltip);
                
                const rect = this.getBoundingClientRect();
                $tooltip.css({
                    top: rect.top - $tooltip.outerHeight() - 5,
                    left: rect.left + (rect.width / 2) - ($tooltip.outerWidth() / 2)
                });
            }
        }).on('mouseleave', '[data-tooltip]', function() {
            $('.custom-tooltip').remove();
        });
    }
    
    // Initialize lazy loading for images
    function initLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                            observer.unobserve(img);
                        }
                    }
                });
            });
            
            // Observe all images with data-src attribute
            $(document).on('DOMNodeInserted', function() {
                $('img[data-src]').each(function() {
                    imageObserver.observe(this);
                });
            });
        }
    }
    
    // Initialize keyboard navigation
    function initKeyboardNavigation() {
        $(document).on('keydown', function(e) {
            // ESC key to clear search
            if (e.key === 'Escape') {
                $('#tutor-search').val('').trigger('input');
            }
            
            // Enter key on search to trigger search
            if (e.key === 'Enter' && $(e.target).is('#tutor-search')) {
                e.preventDefault();
                $(e.target).trigger('input');
            }
        });
        
        // Focus management for accessibility
        $('.tutor-card').attr('tabindex', '0').on('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).find('.view-profile').click();
            }
        });
    }
    
    // Utility function to format price
    window.formatPrice = function(price) {
        if (!price || price === '0') {
            return 'Contact for pricing';
        }
        return '$' + parseFloat(price).toFixed(0) + '/hr';
    };
    
    // Utility function to truncate text
    window.truncateText = function(text, maxLength) {
        if (text.length <= maxLength) {
            return text;
        }
        return text.substr(0, maxLength) + '...';
    };
    
    // Export functions for external use
    window.DarsnaTutorsPage = {
        refreshTutors: function() {
            $('#tutor-search').trigger('input');
        },
        clearFilters: function() {
            $('#clear-filters').click();
        },
        searchTutors: function(query) {
            $('#tutor-search').val(query).trigger('input');
        },
        filterByCountry: function(country) {
            $('#country-filter').val(country).trigger('change');
        },
        filterBySubject: function(subject) {
            $('#subject-filter').val(subject).trigger('change');
        }
    };
    
})(jQuery);

// Additional utility functions for integration

// Function to open contact modal (to be implemented by theme/other plugins)
function openContactModal(tutorId) {
    // This function should be implemented by your theme or another plugin
    // Example implementation:
    /*
    const modal = document.createElement('div');
    modal.innerHTML = `
        <div class="contact-modal">
            <div class="modal-content">
                <h3>Contact Tutor</h3>
                <form id="contact-tutor-form">
                    <input type="hidden" name="tutor_id" value="${tutorId}">
                    <input type="text" name="name" placeholder="Your Name" required>
                    <input type="email" name="email" placeholder="Your Email" required>
                    <textarea name="message" placeholder="Your Message" required></textarea>
                    <button type="submit">Send Message</button>
                    <button type="button" onclick="closeContactModal()">Cancel</button>
                </form>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    */
    console.log('Contact modal should be implemented for tutor ID:', tutorId);
}

// Function to open tutor profile (to be implemented by theme/other plugins)
function openTutorProfile(tutorId) {
    // This function should be implemented by your theme or another plugin
    // Example: redirect to profile page
    // window.location.href = `/tutor-profile/?id=${tutorId}`;
    console.log('Tutor profile should be implemented for tutor ID:', tutorId);
}

// Function to close contact modal
function closeContactModal() {
    const modal = document.querySelector('.contact-modal');
    if (modal) {
        modal.parentNode.removeChild(modal);
    }
}