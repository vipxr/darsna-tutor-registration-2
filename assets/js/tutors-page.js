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