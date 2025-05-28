# Darsna Tutor Registration Plugin

A WordPress plugin for tutor registration integrated with WooCommerce and LatePoint booking system.

## Version 4.0.0 - Modular Architecture

This version introduces a completely refactored modular architecture for better maintainability and easier development.

## File Structure

```
darsna-tutor-registration/
├── darsna-tutor-registration.php    # Main plugin file (entry point)
├── includes/                        # PHP classes
│   ├── class-darsna-tutor-main.php     # Main initialization class
│   ├── class-darsna-tutor-frontend.php # Frontend functionality
│   └── class-darsna-tutor-backend.php  # Backend/admin functionality
├── assets/                          # Static assets
│   ├── css/
│   │   └── frontend.css             # Frontend styles
│   └── js/
│       └── frontend.js              # Frontend JavaScript
└── README.md                        # This file
```

## Architecture Overview

### 1. Main Plugin File (`darsna-tutor-registration.php`)
- **Purpose**: Entry point and plugin registration
- **Responsibilities**:
  - Define plugin constants
  - Load main class
  - Handle activation/deactivation hooks
  - Plugin metadata

### 2. Main Class (`includes/class-darsna-tutor-main.php`)
- **Purpose**: Core initialization and dependency management
- **Responsibilities**:
  - Singleton pattern implementation
  - Dependency checking (WooCommerce, LatePoint, etc.)
  - Load and initialize other classes
  - Handle user deletion
  - Menu customization

### 3. Frontend Class (`includes/class-darsna-tutor-frontend.php`)
- **Purpose**: All frontend-facing functionality
- **Responsibilities**:
  - Enqueue CSS/JS assets
  - Render checkout form fields
  - Form validation
  - Save order metadata
  - Dynamic pricing

### 4. Backend Class (`includes/class-darsna-tutor-backend.php`)
- **Purpose**: Backend processing and LatePoint integration
- **Responsibilities**:
  - Order completion handling
  - Subscription status management
  - LatePoint agent creation/management
  - Schedule management
  - Agent activation/deactivation

### 5. Frontend Assets
- **CSS** (`assets/css/frontend.css`): Styling for checkout forms
- **JavaScript** (`assets/js/frontend.js`): Form interactions and validation

## Key Features

### Frontend Features
- **Service Selection**: Choose teaching subjects from LatePoint services
- **Rate Configuration**: Set hourly rates (5-50)
- **Bio/Experience**: Text area for tutor background
- **Schedule Management**: Select available days and hours
- **Real-time Validation**: Client-side form validation
- **Responsive Design**: Mobile-friendly interface

### Backend Features
- **Agent Management**: Create/update LatePoint agents
- **Schedule Sync**: Sync availability with LatePoint
- **Order Processing**: Handle WooCommerce order completion
- **Subscription Integration**: Manage subscription status changes
- **Role Management**: Assign/remove tutor roles

## Dependencies

- **WordPress**: 5.0+
- **PHP**: 7.4+
- **WooCommerce**: Latest version
- **WooCommerce Subscriptions**: Latest version
- **LatePoint**: v5.0+ (with fallback for older versions)

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through WordPress admin
3. Ensure all dependencies are installed and active
4. Configure WooCommerce and LatePoint as needed

## Configuration

### Rate Settings
```php
// In class-darsna-tutor-frontend.php
private const MIN_RATE = 5;     // Minimum hourly rate
private const MAX_RATE = 50;    // Maximum hourly rate
private const RATE_STEP = 5;    // Rate increment
```

### Default Schedule
```php
// In class-darsna-tutor-frontend.php
private const DEFAULT_SCHEDULE_DAYS = ['mon', 'tue', 'wed', 'thu', 'sun'];
private const DEFAULT_WORK_HOURS = ['start' => '09:00', 'end' => '17:00'];
```

## Hooks and Filters

### Actions
- `darsna_activate_agent` - Triggered when activating a tutor agent
- `woocommerce_order_status_completed` - Handle order completion
- `woocommerce_subscription_status_updated` - Handle subscription changes

### Filters
- `latepoint_full_amount_for_service` - Apply dynamic pricing
- `wp_nav_menu_items` - Customize navigation menu
- `woocommerce_cod_process_payment_order_status` - Set COD status

## Development Guidelines

### Adding New Features
1. **Frontend features**: Add to `class-darsna-tutor-frontend.php`
2. **Backend features**: Add to `class-darsna-tutor-backend.php`
3. **Core features**: Add to `class-darsna-tutor-main.php`

### Styling
- Add CSS to `assets/css/frontend.css`
- Follow BEM methodology for class naming
- Ensure responsive design

### JavaScript
- Add functionality to `assets/js/frontend.js`
- Use jQuery (already loaded)
- Follow the existing module pattern

### Best Practices
- Use singleton pattern for main classes
- Implement proper error handling and logging
- Cache expensive operations
- Follow WordPress coding standards
- Use proper sanitization and validation

## Troubleshooting

### Common Issues

1. **Plugin dependencies not met**
   - Check that WooCommerce, WooCommerce Subscriptions, and LatePoint are active
   - Verify minimum versions

2. **Agent not created in LatePoint**
   - Check error logs for LatePoint API issues
   - Verify LatePoint database tables exist
   - Ensure proper permissions

3. **Frontend styles not loading**
   - Check that assets are properly enqueued
   - Verify file paths and permissions
   - Clear any caching

### Debug Mode
Enable WordPress debug mode to see detailed error messages:
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Changelog

### Version 4.0.0
- **BREAKING**: Refactored to modular architecture
- Separated frontend and backend functionality
- Improved code organization and maintainability
- Enhanced error handling and logging
- Better asset management
- Responsive design improvements

## Support

For support and bug reports, please check the error logs first and provide:
- WordPress version
- Plugin versions (WooCommerce, LatePoint, etc.)
- Error messages from logs
- Steps to reproduce the issue

## License

GPL v2 or later