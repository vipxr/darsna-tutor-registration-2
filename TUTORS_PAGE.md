# Tutors Page Documentation

This document explains how to use the new tutors listing page feature that displays all registered tutors with advanced filtering and search capabilities.

## Shortcode Usage

To display the tutors page on any WordPress page or post, use the following shortcode:

```
[darsna_tutors_page]
```

### Shortcode Parameters

The shortcode accepts the following optional parameters:

- `per_page` - Number of tutors to display per page (default: 12)
- `show_filters` - Whether to show filter controls (default: 'yes')

#### Examples:

```
[darsna_tutors_page per_page="8"]
[darsna_tutors_page show_filters="no"]
[darsna_tutors_page per_page="16" show_filters="yes"]
```

## Features

### 1. Search Functionality
- **Search Box**: Users can search tutors by name, expertise, or bio content
- **Real-time Search**: Results update as users type (with 300ms debounce)
- **Keyboard Support**: Press ESC to clear search, Enter to trigger search

### 2. Advanced Filtering

#### Country Filter
- Dropdown showing all countries where tutors are located
- Automatically populated from tutor registration data

#### Subject Filter
- Dropdown showing all available subjects/services
- Based on the services each tutor offers

#### Price Range Filter
- Predefined price ranges:
  - $0 - $20/hr
  - $20 - $40/hr
  - $40 - $60/hr
  - $60 - $100/hr
  - $100+/hr

#### Sorting Options
- Sort by Name (A-Z)
- Price: Low to High
- Price: High to Low
- Highest Rated (placeholder for future rating system)

### 3. Tutor Cards Display

Each tutor card shows:
- **Profile Photo**: With online status indicator
- **Name and Location**: Full name with country/city
- **Bio**: Short description (truncated to 20 words)
- **Subjects**: Up to 3 subject tags with "+X more" indicator
- **Experience**: Years of experience with graduation cap icon
- **Languages**: Spoken languages with speech icon
- **Pricing**: Starting price or price range
- **Action Buttons**: Contact Tutor and View Profile

### 4. Responsive Design

#### Desktop (1024px+)
- Grid layout with multiple columns
- Full filter bar with all options in one row

#### Tablet (768px - 1024px)
- Adjusted grid layout
- Stacked filter options

#### Mobile (< 768px)
- Single column layout
- Stacked filters
- Touch-friendly buttons
- Optimized font sizes

### 5. Accessibility Features

- **Keyboard Navigation**: Tab through cards, Enter to view profile
- **Screen Reader Support**: Proper ARIA labels and semantic HTML
- **Focus Management**: Clear focus indicators
- **Color Contrast**: WCAG compliant color schemes

### 6. Performance Features

- **AJAX Filtering**: No page reloads when filtering
- **Debounced Search**: Reduces server requests
- **Loading States**: Visual feedback during data loading
- **Lazy Loading**: Images load as they come into view
- **Caching**: Results are optimized for performance

## Customization

### CSS Customization

The tutors page uses the CSS file: `/assets/css/tutors-page.css`

Key CSS classes for customization:

```css
.darsna-tutors-page          /* Main container */
.tutors-filters              /* Filter section */
.tutors-grid                 /* Grid container */
.tutor-card                  /* Individual tutor card */
.tutor-avatar                /* Profile photo area */
.tutor-info                  /* Tutor information */
.tutor-actions               /* Action buttons */
```

### JavaScript Customization

The JavaScript file: `/assets/js/tutors-page.js` provides several utility functions:

```javascript
// Refresh tutors display
DarsnaTutorsPage.refreshTutors();

// Clear all filters
DarsnaTutorsPage.clearFilters();

// Search for specific term
DarsnaTutorsPage.searchTutors('mathematics');

// Filter by country
DarsnaTutorsPage.filterByCountry('United States');

// Filter by subject
DarsnaTutorsPage.filterBySubject('Mathematics');
```

### Custom Contact/Profile Actions

To implement custom contact and profile viewing functionality, define these functions:

```javascript
// Custom contact modal
function openContactModal(tutorId) {
    // Your custom contact implementation
}

// Custom profile viewer
function openTutorProfile(tutorId) {
    // Your custom profile implementation
}
```

## Database Integration

The tutors page integrates with the following database tables:

- `latepoint_agents` - Main tutor information
- `latepoint_services` - Available services/subjects
- `latepoint_agent_services` - Tutor-service relationships
- `latepoint_custom_prices` - Custom hourly rates

## AJAX Endpoints

The page uses the following AJAX endpoint:

- **Action**: `filter_tutors`
- **Method**: POST
- **Parameters**: search, country, subject, price_range, sort
- **Response**: HTML content and result count

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Dark Mode Support

The tutors page automatically adapts to system dark mode preferences using CSS media queries.

## Print Styles

Optimized print styles hide interactive elements and ensure proper card layout when printing.

## Future Enhancements

Planned features for future versions:

1. **Rating System**: Star ratings and reviews
2. **Availability Calendar**: Real-time availability display
3. **Advanced Search**: Search by multiple criteria
4. **Favorites**: Save favorite tutors
5. **Comparison Tool**: Compare multiple tutors
6. **Map Integration**: Geographic tutor locations
7. **Video Profiles**: Tutor introduction videos
8. **Instant Messaging**: Direct chat with tutors

## Troubleshooting

### Common Issues

1. **No tutors showing**: Check if tutors are marked as 'active' in the database
2. **Filters not working**: Ensure AJAX is enabled and nonce is valid
3. **Styling issues**: Check if CSS file is properly enqueued
4. **JavaScript errors**: Check browser console for errors

### Debug Mode

To enable debug mode, add this to your wp-config.php:

```php
define('DARSNA_TUTOR_DEBUG', true);
```

This will show additional console logs and error information.

## Support

For technical support or feature requests, please refer to the main plugin documentation or contact the development team.