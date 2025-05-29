(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        initTutorsPage();
    });
    
    function initTutorsPage() {
        // Debounce function
        function debounce(func, wait) {
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
        
        // Handle tutor card actions
        $(document).on('click', '.contact-tutor', handleContactTutor);
        $(document).on('click', '.view-profile', handleViewProfile);
        
        // Removed outdated handleFilters and clearAllFilters functions - only using enhanced versions now
        
        // Removed outdated utility functions - only using enhanced versions now
        
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
        const $enhancedUrgentHelpFilter = $('#enhanced-urgent-help-filter');
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
        $enhancedUrgentHelpFilter.on('change', handleEnhancedFilters);
        $enhancedSortFilter.on('change', handleEnhancedFilters);
        $enhancedClearButton.on('click', clearEnhancedFilters);
        
        // Handle enhanced checkbox label clicks
        $('.enhanced-urgent-help-checkbox').on('click', function(e) {
            if (e.target.type !== 'checkbox') {
                const checkbox = $(this).find('input[type="checkbox"]');
                checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
            }
        });
        
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
                urgent_help: $enhancedUrgentHelpFilter.is(':checked') ? 'yes' : '',
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
                    maxPrice: parseFloat($item.data('max-price')) || 0,
                    urgentHelp: $item.data('urgent-help') || false
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
                
                // Urgent help filter
                if (filters.urgent_help === 'yes' && !itemData.urgentHelp) {
                    visible = false;
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
            $enhancedUrgentHelpFilter.prop('checked', false);
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
            
            // Use MutationObserver to watch for new images with data-src attribute
            const mutationObserver = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            // Check if the node itself has data-src
                            if (node.hasAttribute && node.hasAttribute('data-src')) {
                                imageObserver.observe(node);
                            }
                            // Check for child elements with data-src
                            const images = node.querySelectorAll ? node.querySelectorAll('img[data-src]') : [];
                            images.forEach(function(img) {
                                imageObserver.observe(img);
                            });
                        }
                    });
                });
            });
            
            // Start observing
            mutationObserver.observe(document.body, {
                childList: true,
                subtree: true
            });
            
            // Observe existing images
            $('img[data-src]').each(function() {
                imageObserver.observe(this);
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